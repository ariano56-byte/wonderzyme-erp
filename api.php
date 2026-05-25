<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db.php';

function isLoggedIn()     { return isset($_SESSION['user_id']); }
function getUserRole()    { return $_SESSION['role'] ?? null; }
function canEdit()        { $r = getUserRole(); return $r === 'admin' || $r === 'manager'; }
function canDelete()      { return getUserRole() === 'admin'; }
function canManageUsers() { return getUserRole() === 'admin'; }

// ✅ FIX: use 8-char hex ID — fits any VARCHAR(8+) column, avoids truncation
function genId() {
    return bin2hex(random_bytes(4)); // 8 hex chars, 4 billion combinations
}

function jsonOut($data, $code = 200) {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function normalizeUsername($u) {
    return preg_replace('/\s+/', '_', trim($u));
}

function refreshSession($pdo) {
    if (!isset($_SESSION['user_id'])) return;
    try {
        $s = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
        $s->execute([$_SESSION['user_id']]);
        $u = $s->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $_SESSION['role']     = $u['role'];
            $_SESSION['username'] = $u['username'];
            try {
                $s2 = $pdo->prepare("SELECT force_password_change FROM users WHERE id = ?");
                $s2->execute([$_SESSION['user_id']]);
                $fp = $s2->fetchColumn();
                $_SESSION['force_pw'] = (bool)$fp;
            } catch (Exception $e) { $_SESSION['force_pw'] = false; }
        } else {
            session_destroy();
            jsonOut(['error' => 'Session expired — please log in again.'], 401);
        }
    } catch (Exception $e) {
        jsonOut(['error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

function logActivity($pdo, $action, $module) {
    try {
        $s = $pdo->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, ?, ?)");
        $s->execute([$_SESSION['user_id'], $action, $module]);
    } catch (Exception $e) { /* non-fatal */ }
}

function createNotification($pdo, $userId, $message, $type = 'info', $taskId = null) {
    try {
        $s = $pdo->prepare("INSERT INTO notifications (id, user_id, task_id, message, type) VALUES (?,?,?,?,?)");
        $s->execute([genId(), $userId, $taskId, $message, $type]);
    } catch (Exception $e) { /* non-fatal */ }
}

function generateDueReminders($pdo) {
    try {
        $s = $pdo->query("SELECT t.id, t.title, t.due, t.assignee_id FROM tasks t WHERE t.status != 'done' AND t.assignee_id IS NOT NULL AND t.due IS NOT NULL AND DATEDIFF(t.due, CURDATE()) BETWEEN 0 AND 2");
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $task) {
            $today = date('Y-m-d');
            $dup = $pdo->prepare("SELECT id FROM notifications WHERE task_id = ? AND type = 'reminder' AND DATE(created_at) = ?");
            $dup->execute([$task['id'], $today]);
            if (!$dup->fetch()) {
                createNotification($pdo, $task['assignee_id'], "Reminder: Task '{$task['title']}' is due on {$task['due']}.", 'reminder', $task['id']);
            }
        }
    } catch (Exception $e) { /* non-fatal */ }
}

function getBomCost($pdo, $productId) {
    try {
        $s = $pdo->prepare("SELECT SUM(pm.qty_per_unit * COALESCE(r.cost_per_unit, 0)) AS bom_cost FROM product_materials pm JOIN raw_materials r ON r.id = pm.raw_material_id WHERE pm.product_id = ?");
        $s->execute([$productId]);
        return (float)($s->fetchColumn() ?? 0);
    } catch (Exception $e) { return 0; }
}

function checkBomStock($pdo, $productId, $qty) {
    try {
        $s = $pdo->prepare("SELECT pm.qty_per_unit, r.name, r.qty AS stock FROM product_materials pm JOIN raw_materials r ON r.id = pm.raw_material_id WHERE pm.product_id = ?");
        $s->execute([$productId]);
        $errors = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $needed = (float)$row['qty_per_unit'] * $qty;
            if ((float)$row['stock'] < $needed) {
                $errors[] = "Insufficient {$row['name']}: need $needed, have {$row['stock']}";
            }
        }
        return count($errors) ? implode('; ', $errors) : true;
    } catch (Exception $e) { return true; }
}

// ✅ FIX: deductBomMaterials now throws on failure so caller can report error
function deductBomMaterials($pdo, $productId, $qty) {
    $s = $pdo->prepare("SELECT pm.qty_per_unit, pm.raw_material_id FROM product_materials pm WHERE pm.product_id = ?");
    $s->execute([$productId]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $needed = (float)$row['qty_per_unit'] * (float)$qty;
        $pdo->prepare("UPDATE raw_materials SET qty = qty - ? WHERE id = ?")
            ->execute([$needed, $row['raw_material_id']]);
    }
    return count($rows); // returns number of materials deducted
}

// ✅ FIX: auto-create product_materials table if it doesn't exist
function ensureProductMaterialsTable($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_materials (
            id VARCHAR(8) NOT NULL PRIMARY KEY,
            product_id VARCHAR(8) NOT NULL,
            raw_material_id VARCHAR(8) NOT NULL,
            qty_per_unit DECIMAL(12,4) NOT NULL DEFAULT 1,
            INDEX idx_product_id (product_id),
            INDEX idx_raw_material_id (raw_material_id)
        )");
    } catch (Exception $e) {
        // MySQL index syntax may not apply — try simpler version (SQLite-compatible)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_materials (
                id VARCHAR(8) NOT NULL PRIMARY KEY,
                product_id VARCHAR(8) NOT NULL,
                raw_material_id VARCHAR(8) NOT NULL,
                qty_per_unit DECIMAL(12,4) NOT NULL DEFAULT 1
            )");
        } catch (Exception $e2) { /* table may already exist */ }
    }
}

// ── Routing ───────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
$method   = $_SERVER['REQUEST_METHOD'];
$parts    = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;

if ($resource !== 'login' && !isLoggedIn()) jsonOut(['error' => 'Unauthorized'], 401);
if ($resource !== 'login') refreshSession($pdo);

try {
switch ($resource) {

    // ── LOGIN ──────────────────────────────────────────────────────────────────
    case 'login':
        if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
        $in       = json_decode(file_get_contents('php://input'), true);
        $username = normalizeUsername($in['username'] ?? '');
        $password = $in['password'] ?? '';
        if (!$username || !$password) jsonOut(['error' => 'Username and password required'], 400);
        $s = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $s->execute([$username]);
        $user = $s->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['force_pw'] = (bool)($user['force_password_change'] ?? 0);
            generateDueReminders($pdo);
            jsonOut(['success' => true, 'role' => $user['role'], 'username' => $user['username'], 'force_pw' => $_SESSION['force_pw']]);
        }
        jsonOut(['error' => 'Invalid credentials'], 401);
        break;

    case 'logout':
        session_destroy();
        jsonOut(['success' => true]);
        break;

    case 'whoami':
        jsonOut(['user_id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'role' => $_SESSION['role'], 'force_pw' => $_SESSION['force_pw'] ?? false]);
        break;

    // ── USERS ──────────────────────────────────────────────────────────────────
    case 'users':
        if ($id === 'role') {
            if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
            if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
            $in = json_decode(file_get_contents('php://input'), true);
            if (!in_array($in['role'] ?? '', ['admin','manager','staff'])) jsonOut(['error' => 'Invalid role'], 400);
            $pdo->prepare("UPDATE users SET role = ? WHERE username = ?")->execute([$in['role'], $in['username']]);
            logActivity($pdo, "Changed role of {$in['username']} to {$in['role']}", 'users');
            jsonOut(['success' => true]);

        } elseif ($id === 'reset-password') {
            if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
            if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
            $in = json_decode(file_get_contents('php://input'), true);
            if (empty($in['password'])) jsonOut(['error' => 'Password required'], 400);
            try {
                $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE username = ?")->execute([password_hash($in['password'], PASSWORD_DEFAULT), $in['username']]);
            } catch (Exception $e) {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?")->execute([password_hash($in['password'], PASSWORD_DEFAULT), $in['username']]);
            }
            jsonOut(['success' => true]);

        } elseif ($method === 'GET') {
            if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
            $rows = $pdo->query("SELECT id, username, role, email FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
            jsonOut($rows);

        } elseif ($method === 'POST' && !$id) {
            if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
            $in       = json_decode(file_get_contents('php://input'), true);
            $username = normalizeUsername($in['username'] ?? '');
            $password = $in['password'] ?? '';
            $role     = $in['role']  ?? 'staff';
            $email    = $in['email'] ?? null;
            if (!$username || !$password) jsonOut(['error' => 'Username and password required'], 400);
            $dup = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $dup->execute([$username]);
            if ($dup->fetch()) jsonOut(['error' => "Username '$username' already exists"], 409);
            $newId = genId(); // ✅ FIX: short ID
            $hash  = password_hash($password, PASSWORD_DEFAULT);
            // Try with all columns, then progressively fallback
            $inserted = false;
            foreach ([
                ["INSERT INTO users (id, username, password_hash, role, email, force_password_change) VALUES (?,?,?,?,?,0)", [$newId,$username,$hash,$role,$email]],
                ["INSERT INTO users (id, username, password_hash, role, email) VALUES (?,?,?,?,?)", [$newId,$username,$hash,$role,$email]],
                ["INSERT INTO users (id, username, password_hash, role) VALUES (?,?,?,?)", [$newId,$username,$hash,$role]],
            ] as [$sql, $params]) {
                if ($inserted) break;
                try { $pdo->prepare($sql)->execute($params); $inserted = true; } catch (Exception $e) {}
            }
            if (!$inserted) jsonOut(['error' => 'Failed to create user — check database schema'], 500);
            logActivity($pdo, "Added user $username ($role)", 'users');
            jsonOut(['success' => true, 'id' => $newId, 'username' => $username]);

        } elseif ($method === 'DELETE' && $id) {
            if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
            if ($id === 'admin') jsonOut(['error' => 'Cannot delete protected admin account'], 403);
            $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$id]);
            logActivity($pdo, "Deleted user $id", 'users');
            jsonOut(['success' => true]);
        } else {
            jsonOut(['error' => 'Method not allowed'], 405);
        }
        break;

    // ── CHANGE PASSWORD ────────────────────────────────────────────────────────
    case 'change-password':
        if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
        $in  = json_decode(file_get_contents('php://input'), true);
        $old = $in['old'] ?? '';
        $new = $in['new'] ?? '';
        if (!$old || !$new) jsonOut(['error' => 'Both fields required'], 400);
        if (strlen($new) < 8) jsonOut(['error' => 'New password must be at least 8 characters'], 400);
        $s = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $s->execute([$_SESSION['user_id']]);
        $user = $s->fetch();
        if (!$user || !password_verify($old, $user['password_hash'])) jsonOut(['error' => 'Current password incorrect'], 401);
        try {
            $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 0 WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        } catch (Exception $e) {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        }
        $_SESSION['force_pw'] = false;
        logActivity($pdo, "Changed own password", 'users');
        jsonOut(['success' => true]);
        break;

    // ── NOTIFICATIONS ─────────────────────────────────────────────────────────
    case 'notifications':
        if ($id === 'read') {
            if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
            $in  = json_decode(file_get_contents('php://input'), true);
            $nid = $in['id'] ?? 'all';
            if ($nid === 'all') {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            } else {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$nid, $_SESSION['user_id']]);
            }
            jsonOut(['success' => true]);
        } else {
            if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
            try {
                $s = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
                $s->execute([$_SESSION['user_id']]);
                $notifs = $s->fetchAll(PDO::FETCH_ASSOC);
                $uc = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                $uc->execute([$_SESSION['user_id']]);
                jsonOut(['notifications' => $notifs, 'unread' => (int)$uc->fetchColumn()]);
            } catch (Exception $e) { jsonOut(['notifications' => [], 'unread' => 0]); }
        }
        break;

    case 'activity-log':
        if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
        try {
            $s = $pdo->prepare("SELECT action, module, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
            $s->execute([$_SESSION['user_id']]);
            jsonOut($s->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { jsonOut([]); }
        break;

    case 'bulk-delete':
        if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $in      = json_decode(file_get_contents('php://input'), true);
        $module  = $in['module'] ?? '';
        $ids     = $in['ids']    ?? [];
        $allowed = ['raw_materials','products','tasks','employees','sales','expenses','returns'];
        if (!in_array($module, $allowed) || !is_array($ids) || !count($ids)) jsonOut(['error' => 'Invalid request'], 400);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM $module WHERE id IN ($ph)")->execute($ids);
        jsonOut(['success' => true]);
        break;

    // ── RAW MATERIALS ─────────────────────────────────────────────────────────
    case 'raw-materials':
        if ($method === 'GET') {
            $rows = $pdo->query("SELECT * FROM raw_materials ORDER BY name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
            jsonOut($rows);

        } elseif ($method === 'POST') {
            if (getUserRole() === 'staff') {
                $data = json_decode(file_get_contents('php://input'), true);
                if (empty($data['id'])) jsonOut(['error' => 'Staff cannot create raw materials'], 403);
                $pdo->prepare("UPDATE raw_materials SET qty = ? WHERE id = ?")->execute([$data['qty'] ?? 0, $data['id']]);
                logActivity($pdo, "Updated qty for raw material {$data['id']}", 'raw_materials');
                jsonOut(['success' => true, 'id' => $data['id']]);
            }
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data         = json_decode(file_get_contents('php://input'), true);
            $name         = trim($data['name'] ?? '');
            $sku          = $data['sku']           ?? null;
            $category     = $data['category']      ?? null;
            $unit         = $data['unit']          ?? null;
            $qty          = (float)($data['qty']   ?? 0);
            $costPerUnit  = (float)($data['cost_per_unit'] ?? $data['price'] ?? 0);
            $reorderLevel = (isset($data['reorder_level']) && $data['reorder_level'] !== '' && is_numeric($data['reorder_level'])) ? (int)$data['reorder_level'] : null;
            if (!$name) jsonOut(['error' => 'Name is required'], 400);

            if (!empty($data['id'])) {
                // UPDATE
                try {
                    $pdo->prepare("UPDATE raw_materials SET name=?,sku=?,category=?,unit=?,qty=?,cost_per_unit=?,reorder_level=? WHERE id=?")
                        ->execute([$name,$sku,$category,$unit,$qty,$costPerUnit,$reorderLevel,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE raw_materials SET name=?,sku=?,category=?,qty=? WHERE id=?")
                        ->execute([$name,$sku,$category,$qty,$data['id']]);
                }
                logActivity($pdo, "Edited raw material $name", 'raw_materials');
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                // INSERT
                $newId = genId(); // ✅ FIX: short ID
                $inserted = false;
                foreach ([
                    ["INSERT INTO raw_materials (id,name,sku,category,unit,qty,cost_per_unit,reorder_level) VALUES (?,?,?,?,?,?,?,?)", [$newId,$name,$sku,$category,$unit,$qty,$costPerUnit,$reorderLevel]],
                    ["INSERT INTO raw_materials (id,name,sku,category,qty) VALUES (?,?,?,?,?)", [$newId,$name,$sku,$category,$qty]],
                    ["INSERT INTO raw_materials (id,name,qty) VALUES (?,?,?)", [$newId,$name,$qty]],
                ] as [$sql, $params]) {
                    if ($inserted) break;
                    try { $pdo->prepare($sql)->execute($params); $inserted = true; } catch (Exception $e) {}
                }
                if (!$inserted) jsonOut(['error' => 'Failed to save raw material'], 500);
                logActivity($pdo, "Added raw material $name", 'raw_materials');

                // Auto-create approved expense for purchase cost
                if ($costPerUnit > 0 && $qty > 0) {
                    $totalCost = $costPerUnit * $qty;
                    $expId = genId();
                    try {
                        $pdo->prepare("INSERT INTO expenses (id,description,category,amount,status,expense_date,invId,quantity,unit_cost) VALUES (?,?,?,?,?,?,?,?,?)")
                            ->execute([$expId, "Inventory: $name", 'Raw Material', $totalCost, 'approved', date('Y-m-d'), $newId, $qty, $costPerUnit]);
                    } catch (Exception $e) {
                        try {
                            $pdo->prepare("INSERT INTO expenses (id,description,category,amount,status,expense_date) VALUES (?,?,?,?,?,?)")
                                ->execute([$expId, "Inventory: $name", 'Raw Material', $totalCost, 'approved', date('Y-m-d')]);
                        } catch (Exception $e2) { /* non-fatal */ }
                    }
                    logActivity($pdo, "Auto-expense for $name (₱$totalCost)", 'expenses');
                }
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            try {
                $bom = $pdo->prepare("SELECT COUNT(*) FROM product_materials WHERE raw_material_id = ?");
                $bom->execute([$id]);
                if ((int)$bom->fetchColumn() > 0) jsonOut(['error' => 'Cannot delete — used in a product BOM. Remove it from the BOM first.'], 409);
            } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM raw_materials WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted raw material $id", 'raw_materials');
            jsonOut(['success' => true]);
        }
        break;

    // ── PRODUCTS ──────────────────────────────────────────────────────────────
    case 'products':
        if ($method === 'GET') {
            try {
                $rows = $pdo->query("SELECT * FROM products ORDER BY name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                jsonOut(['error' => 'Failed to load products: ' . $e->getMessage()], 500);
            }
            foreach ($rows as &$row) { $row['bom_cost'] = getBomCost($pdo, $row['id']); }
            jsonOut($rows);

        } elseif ($method === 'POST') {
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data      = json_decode(file_get_contents('php://input'), true);
            $name      = trim($data['name']      ?? '');
            $sku       = $data['sku']       ?? null;
            $category  = $data['category']  ?? null;
            $unit      = $data['unit']      ?? null;
            $qty       = (int)($data['qty'] ?? 0);
            $sellprice = (float)($data['sellprice'] ?? 0);
            $bom       = is_array($data['bom'] ?? null) ? $data['bom'] : [];
            // Filter out empty BOM rows
            $bom = array_filter($bom, fn($r) => !empty($r['raw_material_id']) && ($r['qty_per_unit'] ?? 0) > 0);
            $bom = array_values($bom);
            if (!$name) jsonOut(['error' => 'Product name is required'], 400);

            // ✅ FIX: ensure product_materials table exists before any BOM work
            ensureProductMaterialsTable($pdo);

            if (!empty($data['id'])) {
                // ── UPDATE ──
                try {
                    $oq = $pdo->prepare("SELECT qty FROM products WHERE id = ?");
                    $oq->execute([$data['id']]);
                    $oldQty = (int)($oq->fetchColumn() ?? 0);
                } catch (Exception $e) { $oldQty = 0; }

                try {
                    $pdo->prepare("UPDATE products SET name=?,sku=?,category=?,unit=?,qty=?,sellprice=? WHERE id=?")
                        ->execute([$name,$sku,$category,$unit,$qty,$sellprice,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE products SET name=?,sku=?,category=?,qty=? WHERE id=?")
                        ->execute([$name,$sku,$category,$qty,$data['id']]);
                }
                logActivity($pdo, "Edited product $name", 'products');

                // ✅ FIX: save BOM with explicit error reporting
                $bomWarning = null;
                if (count($bom) > 0) {
                    try {
                        $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?")->execute([$data['id']]);
                        foreach ($bom as $row) {
                            $pdo->prepare("INSERT INTO product_materials (id,product_id,raw_material_id,qty_per_unit) VALUES (?,?,?,?)")
                                ->execute([genId(), $data['id'], $row['raw_material_id'], (float)$row['qty_per_unit']]);
                        }
                    } catch (Exception $e) {
                        $bomWarning = 'BOM save failed: ' . $e->getMessage();
                    }
                }

                // ✅ FIX: deduct stock for the ADDITIONAL quantity produced
                if (!$bomWarning && $qty > $oldQty) {
                    $diff  = $qty - $oldQty;
                    $check = checkBomStock($pdo, $data['id'], $diff);
                    if ($check !== true) jsonOut(['error' => $check], 400);
                    try {
                        deductBomMaterials($pdo, $data['id'], $diff);
                        logActivity($pdo, "Deducted BOM stock for $name × $diff", 'raw_materials');
                    } catch (Exception $e) {
                        $bomWarning = 'Stock deduction failed: ' . $e->getMessage();
                    }
                }

                jsonOut(['success' => true, 'id' => $data['id'], 'warning' => $bomWarning]);

            } else {
                // ── INSERT ──
                $newId = genId(); // ✅ FIX: short ID
                $inserted = false;
                foreach ([
                    ["INSERT INTO products (id,name,sku,category,unit,qty,sellprice) VALUES (?,?,?,?,?,?,?)", [$newId,$name,$sku,$category,$unit,$qty,$sellprice]],
                    ["INSERT INTO products (id,name,sku,category,qty) VALUES (?,?,?,?,?)", [$newId,$name,$sku,$category,$qty]],
                    ["INSERT INTO products (id,name,qty) VALUES (?,?,?)", [$newId,$name,$qty]],
                ] as [$sql, $params]) {
                    if ($inserted) break;
                    try { $pdo->prepare($sql)->execute($params); $inserted = true; } catch (Exception $e) {}
                }
                if (!$inserted) jsonOut(['error' => 'Failed to save product'], 500);
                logActivity($pdo, "Added product $name", 'products');

                // ✅ FIX: save BOM rows then deduct raw material stock
                $bomWarning = null;
                if (count($bom) > 0) {
                    try {
                        foreach ($bom as $row) {
                            $pdo->prepare("INSERT INTO product_materials (id,product_id,raw_material_id,qty_per_unit) VALUES (?,?,?,?)")
                                ->execute([genId(), $newId, $row['raw_material_id'], (float)$row['qty_per_unit']]);
                        }
                    } catch (Exception $e) {
                        $bomWarning = 'BOM save failed: ' . $e->getMessage();
                    }

                    if (!$bomWarning && $qty > 0) {
                        $check = checkBomStock($pdo, $newId, $qty);
                        if ($check !== true) {
                            // Clean up the product and BOM we just inserted
                            try { $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?")->execute([$newId]); } catch (Exception $ex) {}
                            try { $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$newId]); } catch (Exception $ex) {}
                            jsonOut(['error' => $check], 400);
                        }
                        try {
                            deductBomMaterials($pdo, $newId, $qty);
                            logActivity($pdo, "Deducted BOM stock for new product $name × $qty", 'raw_materials');
                        } catch (Exception $e) {
                            $bomWarning = 'Stock deduction failed: ' . $e->getMessage();
                        }
                    }
                }

                jsonOut(['success' => true, 'id' => $newId, 'warning' => $bomWarning]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            try { $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?")->execute([$id]); } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted product $id", 'products');
            jsonOut(['success' => true]);
        }
        break;

    case 'product-materials':
        if ($method === 'GET') {
            $pid = $_GET['product_id'] ?? $id;
            if (!$pid) jsonOut(['error' => 'product_id required'], 400);
            try {
                $s = $pdo->prepare("SELECT pm.*, r.name AS material_name, r.unit AS material_unit, r.qty AS material_qty, COALESCE(r.cost_per_unit,0) AS cost_per_unit FROM product_materials pm JOIN raw_materials r ON r.id = pm.raw_material_id WHERE pm.product_id = ?");
                $s->execute([$pid]);
                jsonOut($s->fetchAll(PDO::FETCH_ASSOC));
            } catch (Exception $e) { jsonOut([]); }
        }
        break;

    // ── TASKS ─────────────────────────────────────────────────────────────────
    case 'tasks':
        if ($method === 'GET') {
            if (getUserRole() === 'staff') {
                $s = $pdo->prepare("SELECT * FROM tasks WHERE assignee = ? ORDER BY created_at DESC LIMIT 200");
                $s->execute([$_SESSION['username']]);
            } else {
                $s = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC LIMIT 200");
            }
            jsonOut($s->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($method === 'POST') {
            $data       = json_decode(file_get_contents('php://input'), true);
            $title      = trim($data['title']    ?? '');
            $assignee   = ($data['assignee'] ?? '') === '' ? null : $data['assignee'];
            $assigneeId = ($data['assignee_id'] ?? '') === '' ? null : $data['assignee_id'];
            $due        = ($data['due']      ?? '') === '' ? null : $data['due'];
            $status     = $data['status']   ?? 'todo';
            $priority   = $data['priority'] ?? 'medium';
            $notes      = ($data['notes']    ?? '') === '' ? null : $data['notes'];
            $comments   = ($data['comments'] ?? '') === '' ? null : $data['comments'];

            if (getUserRole() === 'staff') {
                if (empty($data['id'])) jsonOut(['error' => 'Staff cannot create tasks'], 403);
                $ck = $pdo->prepare("SELECT assignee FROM tasks WHERE id = ?");
                $ck->execute([$data['id']]);
                $t = $ck->fetch();
                if (!$t || $t['assignee'] !== $_SESSION['username']) jsonOut(['error' => 'Permission denied'], 403);
                $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$status, $data['id']]);
                logActivity($pdo, "Updated task status to $status", 'tasks');
                jsonOut(['success' => true, 'id' => $data['id']]);
            }
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);

            if (!empty($data['id'])) {
                try {
                    $prev = $pdo->prepare("SELECT assignee_id FROM tasks WHERE id = ?");
                    $prev->execute([$data['id']]);
                    $prevRow = $prev->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) { $prevRow = ['assignee_id' => null]; }

                try {
                    $pdo->prepare("UPDATE tasks SET title=?,assignee=?,assignee_id=?,due=?,status=?,priority=?,notes=?,comments=? WHERE id=?")
                        ->execute([$title,$assignee,$assigneeId,$due,$status,$priority,$notes,$comments,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE tasks SET title=?,assignee=?,due=?,status=?,priority=? WHERE id=?")
                        ->execute([$title,$assignee,$due,$status,$priority,$data['id']]);
                }
                logActivity($pdo, "Edited task $title", 'tasks');
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                $newId = genId(); // ✅ FIX: short ID
                try {
                    $pdo->prepare("INSERT INTO tasks (id,title,assignee,assignee_id,due,status,priority,notes,comments) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$newId,$title,$assignee,$assigneeId,$due,$status,$priority,$notes,$comments]);
                } catch (Exception $e) {
                    $pdo->prepare("INSERT INTO tasks (id,title,assignee,due,status,priority) VALUES (?,?,?,?,?,?)")
                        ->execute([$newId,$title,$assignee,$due,$status,$priority]);
                }
                logActivity($pdo, "Added task $title", 'tasks');
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted task", 'tasks');
            jsonOut(['success' => true]);
        }
        break;

    // ── EMPLOYEES ─────────────────────────────────────────────────────────────
    case 'employees':
        if ($method === 'GET') {
            try {
                $rows = $pdo->query("SELECT e.*, COUNT(t.id) AS open_tasks FROM employees e LEFT JOIN tasks t ON (t.assignee = e.name OR t.assignee_id = e.id) AND t.status != 'done' GROUP BY e.id ORDER BY e.name")->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                try {
                    $rows = $pdo->query("SELECT e.*, COUNT(t.id) AS open_tasks FROM employees e LEFT JOIN tasks t ON t.assignee = e.name AND t.status != 'done' GROUP BY e.id ORDER BY e.name")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e2) {
                    $rows = $pdo->query("SELECT *, 0 AS open_tasks FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            jsonOut($rows);

        } elseif ($method === 'POST') {
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data   = json_decode(file_get_contents('php://input'), true);
            $name   = trim($data['name']   ?? '');
            $dept   = $data['dept']   ?? null;
            $role   = $data['role']   ?? null;
            $email  = $data['email']  ?? null;
            $phone  = $data['phone']  ?? null;
            $status = $data['status'] ?? 'active';
            if (!$name) jsonOut(['error' => 'Name is required'], 400);

            if (!empty($data['id'])) {
                // ✅ FIX: try/catch fallback for UPDATE
                try {
                    $pdo->prepare("UPDATE employees SET name=?,dept=?,role=?,email=?,phone=?,status=? WHERE id=?")
                        ->execute([$name,$dept,$role,$email,$phone,$status,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE employees SET name=?,dept=?,role=?,email=?,status=? WHERE id=?")
                        ->execute([$name,$dept,$role,$email,$status,$data['id']]);
                }
                logActivity($pdo, "Edited employee $name", 'employees');
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                $newId = genId(); // ✅ FIX: short ID
                // ✅ FIX: try/catch fallback chain for INSERT
                $inserted = false;
                foreach ([
                    ["INSERT INTO employees (id,name,dept,role,email,phone,status) VALUES (?,?,?,?,?,?,?)", [$newId,$name,$dept,$role,$email,$phone,$status]],
                    ["INSERT INTO employees (id,name,dept,role,email,status) VALUES (?,?,?,?,?,?)", [$newId,$name,$dept,$role,$email,$status]],
                    ["INSERT INTO employees (id,name,status) VALUES (?,?,?)", [$newId,$name,$status]],
                ] as [$sql, $params]) {
                    if ($inserted) break;
                    try { $pdo->prepare($sql)->execute($params); $inserted = true; } catch (Exception $e) {}
                }
                if (!$inserted) jsonOut(['error' => 'Failed to save employee'], 500);
                logActivity($pdo, "Added employee $name", 'employees');

                if (!empty($data['create_account']) && canManageUsers()) {
                    $parts = explode(' ', strtolower(trim($name)));
                    $uname = normalizeUsername(($parts[0] ?? 'user') . '_' . ($parts[1] ?? substr($newId,0,4)));
                    $tmpPw = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
                    $uid   = genId();
                    $acctCreated = false;
                    foreach ([
                        ["INSERT INTO users (id,username,password_hash,role,email,force_password_change) VALUES (?,?,?,?,?,1)", [$uid,$uname,password_hash($tmpPw,PASSWORD_DEFAULT),'staff',$email]],
                        ["INSERT INTO users (id,username,password_hash,role,email) VALUES (?,?,?,?,?)", [$uid,$uname,password_hash($tmpPw,PASSWORD_DEFAULT),'staff',$email]],
                        ["INSERT INTO users (id,username,password_hash,role) VALUES (?,?,?,?)", [$uid,$uname,password_hash($tmpPw,PASSWORD_DEFAULT),'staff']],
                    ] as [$sql, $params]) {
                        if ($acctCreated) break;
                        try { $pdo->prepare($sql)->execute($params); $acctCreated = true; } catch (Exception $e) {}
                    }
                    if ($acctCreated) {
                        try { $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$uid,$newId]); } catch (Exception $e) {}
                        jsonOut(['success'=>true,'id'=>$newId,'new_username'=>$uname,'temp_password'=>$tmpPw]);
                    }
                }
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted employee $id", 'employees');
            jsonOut(['success' => true]);
        }
        break;

    // ── SALES ─────────────────────────────────────────────────────────────────
    case 'sales':
        if ($method === 'GET') {
            jsonOut($pdo->query("SELECT * FROM sales ORDER BY sale_date DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($method === 'POST') {
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data        = json_decode(file_get_contents('php://input'), true);
            $qty         = (float)($data['qty']       ?? 0);
            $unitprice   = (float)($data['unitprice']  ?? 0);
            $unitcost    = (float)($data['unitcost']   ?? 0);
            $amount      = $qty * $unitprice;
            $cost        = $qty * $unitcost;
            $platform    = $data['platform']      ?? null;
            $invId       = ($data['invId'] ?? '') === '' ? null : $data['invId'];
            $product     = $data['product']       ?? null;
            $saleDate    = $data['sale_date']     ?? null;
            $notes       = ($data['notes'] ?? '') === '' ? null : $data['notes'];
            $channelType = $data['channel_type']   ?? null;
            $channelDet  = $data['channel_detail'] ?? null;

            if (!empty($data['id'])) {
                $orig = $pdo->prepare("SELECT qty, invId FROM sales WHERE id = ?");
                $orig->execute([$data['id']]);
                $origRow = $orig->fetch(PDO::FETCH_ASSOC);
                try {
                    $pdo->prepare("UPDATE sales SET platform=?,invId=?,product=?,qty=?,unitprice=?,unitcost=?,amount=?,cost=?,sale_date=?,notes=?,channel_type=?,channel_detail=? WHERE id=?")
                        ->execute([$platform,$invId,$product,$qty,$unitprice,$unitcost,$amount,$cost,$saleDate,$notes,$channelType,$channelDet,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE sales SET platform=?,product=?,qty=?,amount=?,sale_date=? WHERE id=?")
                        ->execute([$platform,$product,$qty,$amount,$saleDate,$data['id']]);
                }
                logActivity($pdo, "Edited sale", 'sales');
                if ($invId && $origRow) {
                    $diff = $qty - (float)$origRow['qty'];
                    if ($diff != 0) {
                        try { $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ?")->execute([$diff, $invId]); } catch (Exception $e) {}
                    }
                }
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                if ($invId) {
                    try {
                        $stock = $pdo->prepare("SELECT qty, name FROM products WHERE id = ?");
                        $stock->execute([$invId]);
                        $sr = $stock->fetch(PDO::FETCH_ASSOC);
                        if ($sr && $qty > (float)$sr['qty']) jsonOut(['error' => "Insufficient stock for {$sr['name']}. Available: {$sr['qty']}, Requested: $qty"], 400);
                    } catch (Exception $e) {}
                }
                $newId = genId(); // ✅ FIX: short ID
                try {
                    $pdo->prepare("INSERT INTO sales (id,platform,invId,product,qty,unitprice,unitcost,amount,cost,sale_date,notes,channel_type,channel_detail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$newId,$platform,$invId,$product,$qty,$unitprice,$unitcost,$amount,$cost,$saleDate,$notes,$channelType,$channelDet]);
                } catch (Exception $e) {
                    $pdo->prepare("INSERT INTO sales (id,platform,product,qty,amount,sale_date) VALUES (?,?,?,?,?,?)")
                        ->execute([$newId,$platform,$product,$qty,$amount,$saleDate]);
                }
                logActivity($pdo, "Added sale of $product", 'sales');
                if ($invId && $qty > 0) {
                    try { $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ?")->execute([$qty, $invId]); } catch (Exception $e) {}
                }
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            $row = $pdo->prepare("SELECT qty, invId FROM sales WHERE id = ?");
            $row->execute([$id]);
            $saleRow = $row->fetch(PDO::FETCH_ASSOC);
            if ($saleRow && $saleRow['invId']) {
                try { $pdo->prepare("UPDATE products SET qty = qty + ? WHERE id = ?")->execute([$saleRow['qty'], $saleRow['invId']]); } catch (Exception $e) {}
            }
            $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted sale", 'sales');
            jsonOut(['success' => true]);
        }
        break;

    case 'expense-categories':
        if ($method === 'GET') {
            try {
                $rows = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll(PDO::FETCH_ASSOC);
                jsonOut($rows);
            } catch (Exception $e) {
                jsonOut([['id'=>1,'name'=>'Raw Material'],['id'=>2,'name'=>'Food & Beverage'],['id'=>3,'name'=>'Travel & Transport'],['id'=>4,'name'=>'Marketing'],['id'=>5,'name'=>'Utilities'],['id'=>6,'name'=>'Packaging'],['id'=>7,'name'=>'Salary & Wages'],['id'=>8,'name'=>'Equipment'],['id'=>9,'name'=>'Other']]);
            }
        } elseif ($method === 'POST') {
            if (!canManageUsers()) jsonOut(['error' => 'Admin only'], 403);
            $in = json_decode(file_get_contents('php://input'), true);
            if (!empty($in['id'])) {
                $pdo->prepare("UPDATE expense_categories SET name=?,is_active=? WHERE id=?")->execute([$in['name'],$in['is_active']??1,$in['id']]);
            } else {
                $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)")->execute([$in['name']]);
            }
            jsonOut(['success' => true]);
        }
        break;

    // ── EXPENSES ──────────────────────────────────────────────────────────────
    case 'expenses':
        if ($method === 'GET') {
            jsonOut($pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($method === 'POST') {
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data        = json_decode(file_get_contents('php://input'), true);
            $description = $data['description']  ?? null;
            $category    = $data['category']     ?? null;
            $quantity    = (float)($data['quantity']  ?? 1);
            $unitCost    = (isset($data['unit_cost']) && $data['unit_cost'] !== '') ? (float)$data['unit_cost'] : null;
            $amount      = $unitCost !== null ? $quantity * $unitCost : (float)($data['amount'] ?? 0);
            $status      = $data['status']       ?? 'pending';
            $expDate     = $data['expense_date'] ?? null;
            $invId       = ($data['invId'] ?? '') === '' ? null : $data['invId'];

            if (!empty($data['id'])) {
                try {
                    $linked = $pdo->prepare("SELECT invId FROM expenses WHERE id = ?");
                    $linked->execute([$data['id']]);
                    $lRow = $linked->fetch(PDO::FETCH_ASSOC);
                    if ($lRow && !empty($lRow['invId'])) {
                        $pdo->prepare("UPDATE expenses SET status = ? WHERE id = ?")->execute([$status, $data['id']]);
                        jsonOut(['success' => true, 'id' => $data['id']]);
                    }
                } catch (Exception $e) {}
                try {
                    $pdo->prepare("UPDATE expenses SET description=?,category=?,amount=?,status=?,expense_date=?,invId=?,quantity=?,unit_cost=? WHERE id=?")
                        ->execute([$description,$category,$amount,$status,$expDate,$invId,$quantity,$unitCost,$data['id']]);
                } catch (Exception $e) {
                    $pdo->prepare("UPDATE expenses SET description=?,category=?,amount=?,status=?,expense_date=? WHERE id=?")
                        ->execute([$description,$category,$amount,$status,$expDate,$data['id']]);
                }
                logActivity($pdo, "Edited expense", 'expenses');
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                $newId = genId(); // ✅ FIX: short ID
                try {
                    $pdo->prepare("INSERT INTO expenses (id,description,category,amount,status,expense_date,invId,quantity,unit_cost) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([$newId,$description,$category,$amount,$status,$expDate,$invId,$quantity,$unitCost]);
                } catch (Exception $e) {
                    $pdo->prepare("INSERT INTO expenses (id,description,category,amount,status,expense_date) VALUES (?,?,?,?,?,?)")
                        ->execute([$newId,$description,$category,$amount,$status,$expDate]);
                }
                logActivity($pdo, "Added expense: $description", 'expenses');
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            try {
                $linked = $pdo->prepare("SELECT invId FROM expenses WHERE id = ?");
                $linked->execute([$id]);
                $lRow = $linked->fetch(PDO::FETCH_ASSOC);
                if ($lRow && !empty($lRow['invId'])) jsonOut(['error' => 'Cannot delete inventory-linked expenses. Edit the raw material instead.'], 403);
            } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted expense", 'expenses');
            jsonOut(['success' => true]);
        }
        break;

    // ── RETURNS ───────────────────────────────────────────────────────────────
    case 'returns':
        if ($method === 'GET') {
            jsonOut($pdo->query("SELECT * FROM returns ORDER BY return_date DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($method === 'POST') {
            if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
            $data         = json_decode(file_get_contents('php://input'), true);
            $returnId     = $data['return_id']            ?? null;
            $origSaleId   = $data['original_sale_id']     ?? null;
            $productSku   = $data['product_sku']          ?? null;
            $returnQty    = (int)($data['return_quantity'] ?? 1);
            $returnReason = $data['return_reason']         ?? null;
            $resoStatus   = $data['resolution_status']    ?? 'pending';
            $returnDate   = $data['return_date']          ?? null;
            $inspNotes    = $data['inspection_notes']     ?? null;
            $unitCost     = (float)($data['unit_cost']               ?? 0);
            $shippingCost = (float)($data['return_shipping_cost']     ?? 0);
            $restockCost  = (float)($data['restocking_labor_cost']    ?? 0);
            $disposalFee  = (float)($data['disposal_fee']             ?? 0);
            $salvageVal   = (float)($data['salvage_value_recovered']  ?? 0);
            $totalLoss    = ($unitCost * $returnQty) + $shippingCost + $restockCost + $disposalFee - $salvageVal;

            if (!empty($data['id'])) {
                $saved = false;
                foreach ([
                    ["UPDATE returns SET return_id=?,original_sale_id=?,product_sku=?,return_quantity=?,return_reason=?,resolution_status=?,status=?,unit_cost=?,return_shipping_cost=?,restocking_labor_cost=?,disposal_fee=?,salvage_value_recovered=?,return_date=?,inspection_notes=?,total_loss=? WHERE id=?",
                     [$returnId,$origSaleId,$productSku,$returnQty,$returnReason,$resoStatus,$resoStatus,$unitCost,$shippingCost,$restockCost,$disposalFee,$salvageVal,$returnDate,$inspNotes,$totalLoss,$data['id']]],
                    ["UPDATE returns SET product_sku=?,return_quantity=?,resolution_status=?,return_date=?,total_loss=? WHERE id=?",
                     [$productSku,$returnQty,$resoStatus,$returnDate,$totalLoss,$data['id']]],
                    ["UPDATE returns SET product_sku=?,status=?,total_loss=? WHERE id=?",
                     [$productSku,$resoStatus,$totalLoss,$data['id']]],
                ] as [$sql, $params]) {
                    if ($saved) break;
                    try { $pdo->prepare($sql)->execute($params); $saved = true; } catch (Exception $e) {}
                }
                if (!$saved) jsonOut(['error' => 'Failed to update return'], 500);
                logActivity($pdo, "Edited return", 'returns');
                jsonOut(['success' => true, 'id' => $data['id']]);
            } else {
                $newId = genId(); // ✅ FIX: short ID
                $saved = false;
                foreach ([
                    ["INSERT INTO returns (id,return_id,original_sale_id,product_sku,return_quantity,return_reason,resolution_status,status,unit_cost,return_shipping_cost,restocking_labor_cost,disposal_fee,salvage_value_recovered,return_date,inspection_notes,total_loss) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                     [$newId,$returnId,$origSaleId,$productSku,$returnQty,$returnReason,$resoStatus,$resoStatus,$unitCost,$shippingCost,$restockCost,$disposalFee,$salvageVal,$returnDate,$inspNotes,$totalLoss]],
                    ["INSERT INTO returns (id,product_sku,return_quantity,resolution_status,return_date,total_loss) VALUES (?,?,?,?,?,?)",
                     [$newId,$productSku,$returnQty,$resoStatus,$returnDate,$totalLoss]],
                    ["INSERT INTO returns (id,product_sku,return_quantity,status,total_loss) VALUES (?,?,?,?,?)",
                     [$newId,$productSku,$returnQty,$resoStatus,$totalLoss]],
                ] as [$sql, $params]) {
                    if ($saved) break;
                    try { $pdo->prepare($sql)->execute($params); $saved = true; } catch (Exception $e) {}
                }
                if (!$saved) jsonOut(['error' => 'Failed to save return'], 500);
                logActivity($pdo, "Added return", 'returns');
                jsonOut(['success' => true, 'id' => $newId]);
            }

        } elseif ($method === 'DELETE' && $id) {
            if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
            $pdo->prepare("DELETE FROM returns WHERE id = ?")->execute([$id]);
            logActivity($pdo, "Deleted return", 'returns');
            jsonOut(['success' => true]);
        }
        break;

    case 'returns-analytics':
        if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
        try {
            $byReason = $pdo->query("SELECT COALESCE(return_reason,'Unknown') AS reason, SUM(total_loss) AS loss FROM returns GROUP BY reason")->fetchAll(PDO::FETCH_ASSOC);
            $byMonth  = $pdo->query("SELECT DATE_FORMAT(return_date,'%Y-%m') AS month, SUM(total_loss) AS loss FROM returns WHERE return_date IS NOT NULL GROUP BY month ORDER BY month")->fetchAll(PDO::FETCH_ASSOC);
            $reason = []; foreach ($byReason as $r) $reason[$r['reason']] = floatval($r['loss']);
            $month  = []; foreach ($byMonth  as $r) $month[$r['month']]   = floatval($r['loss']);
            jsonOut(['reason' => $reason, 'month' => $month]);
        } catch (Exception $e) { jsonOut(['reason' => [], 'month' => []]); }
        break;

    case 'dashboard':
        if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
        try { $rawVal    = $pdo->query("SELECT SUM(COALESCE(cost_per_unit,0)*qty) AS v FROM raw_materials")->fetch()['v'] ?? 0; } catch (Exception $e) { $rawVal = 0; }
        try { $prodVal   = $pdo->query("SELECT SUM(COALESCE(sellprice,0)*qty) AS v FROM products")->fetch()['v'] ?? 0; } catch (Exception $e) { $prodVal = 0; }
        try { $salesRev  = $pdo->query("SELECT SUM(amount) AS v FROM sales")->fetch()['v'] ?? 0; } catch (Exception $e) { $salesRev = 0; }
        try { $salesCost = $pdo->query("SELECT SUM(cost) AS v FROM sales")->fetch()['v'] ?? 0; } catch (Exception $e) { $salesCost = 0; }
        $profit = $salesRev - $salesCost;
        try { $expTotal   = $pdo->query("SELECT SUM(amount) AS v FROM expenses")->fetch()['v'] ?? 0; } catch (Exception $e) { $expTotal = 0; }
        try { $returnLoss = $pdo->query("SELECT SUM(total_loss) AS v FROM returns WHERE resolution_status='approved' OR status='approved'")->fetch()['v'] ?? 0; } catch (Exception $e) { $returnLoss = 0; }
        try { $lowRaw    = $pdo->query("SELECT COUNT(*) AS c FROM raw_materials WHERE qty <= COALESCE(reorder_level,5)")->fetch()['c'] ?? 0; } catch (Exception $e) { $lowRaw = 0; }
        try { $lowProd   = $pdo->query("SELECT COUNT(*) AS c FROM products WHERE qty = 0")->fetch()['c'] ?? 0; } catch (Exception $e) { $lowProd = 0; }
        try { $openTasks = $pdo->query("SELECT COUNT(*) AS c FROM tasks WHERE status != 'done'")->fetch()['c'] ?? 0; } catch (Exception $e) { $openTasks = 0; }
        try { $monthly   = $pdo->query("SELECT DATE_FORMAT(sale_date,'%Y-%m') AS month, SUM(amount) AS rev, SUM(cost) AS cost FROM sales WHERE sale_date IS NOT NULL AND sale_date <= CURDATE() GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $monthly = []; }
        jsonOut(['rawVal'=>$rawVal,'prodVal'=>$prodVal,'salesRev'=>$salesRev,'profit'=>$profit,'expTotal'=>$expTotal,'returnLoss'=>$returnLoss,'lowStock'=>$lowRaw+$lowProd,'lowRaw'=>$lowRaw,'lowProd'=>$lowProd,'openTasks'=>$openTasks,'monthly'=>$monthly]);
        break;

    default:
        jsonOut(['error' => 'Not found'], 404);
}
} catch (Exception $e) {
    jsonOut(['error' => 'Server error: ' . $e->getMessage()], 500);
}
