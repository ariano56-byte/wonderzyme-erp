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
function canExport()      { $r = getUserRole(); return $r === 'admin' || $r === 'manager'; }
function canManageUsers() { return getUserRole() === 'admin'; }

function jsonOut($data, $code = 200) {
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function logActivity($pdo, $action, $module) {
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, module) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $action, $module]);
}

header('Content-Type: application/json');
$method   = $_SERVER['REQUEST_METHOD'];
$request  = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$resource = $request[0] ?? '';
$id       = $request[1] ?? null;

if ($resource !== 'login' && !isLoggedIn()) {
    jsonOut(['error' => 'Unauthorized'], 401);
}

// ============================================
// RAW MATERIALS
// ============================================
if ($resource === 'raw-materials') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM raw_materials ORDER BY name");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO raw_materials (id, name, sku, qty, unit_cost, supplier, reorder_level) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['name'], $data['sku'] ?? null, $data['qty'] ?? 0, $data['unit_cost'] ?? 0, $data['supplier'] ?? null, $data['reorder_level'] ?? 10]);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE raw_materials SET name=?, sku=?, qty=?, unit_cost=?, supplier=?, reorder_level=? WHERE id=?");
            $stmt->execute([$data['name'], $data['sku'] ?? null, $data['qty'] ?? 0, $data['unit_cost'] ?? 0, $data['supplier'] ?? null, $data['reorder_level'] ?? 10, $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM raw_materials WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// PRODUCTS
// ============================================
elseif ($resource === 'products') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO products (id, name, sku, category, qty, sell_price) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['name'], $data['sku'] ?? null, $data['category'] ?? null, $data['qty'] ?? 0, $data['sell_price'] ?? 0]);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, sku=?, category=?, qty=?, sell_price=? WHERE id=?");
            $stmt->execute([$data['name'], $data['sku'] ?? null, $data['category'] ?? null, $data['qty'] ?? 0, $data['sell_price'] ?? 0, $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// PRODUCT MATERIALS (BOM)
// ============================================
elseif ($resource === 'product-materials') {
    if ($method === 'GET') {
        $productId = $_GET['product_id'] ?? '';
        if (!$productId) jsonOut(['error' => 'product_id required'], 400);
        $stmt = $pdo->prepare("
            SELECT pm.*, rm.name as material_name, rm.unit_cost 
            FROM product_materials pm 
            JOIN raw_materials rm ON pm.material_id = rm.id 
            WHERE pm.product_id = ?
        ");
        $stmt->execute([$productId]);
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $pdo->prepare("DELETE FROM product_materials WHERE product_id = ?")->execute([$data['product_id']]);
        foreach ($data['materials'] as $mat) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO product_materials (id, product_id, material_id, quantity_needed) VALUES (?,?,?,?)");
            $stmt->execute([$newId, $data['product_id'], $mat['material_id'], $mat['quantity_needed']]);
        }
        jsonOut(['success' => true]);
    }
}
// ============================================
// EMPLOYEES
// ============================================
elseif ($resource === 'employees') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT e.*, u.username as user_username FROM employees e LEFT JOIN users u ON e.user_id = u.id");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO employees (id, name, dept, role, email, phone, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['name'], $data['dept'] ?? null, $data['role'] ?? null, $data['email'] ?? null, $data['phone'] ?? null, $data['status'] ?? 'active']);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE employees SET name=?, dept=?, role=?, email=?, phone=?, status=? WHERE id=?");
            $stmt->execute([$data['name'], $data['dept'] ?? null, $data['role'] ?? null, $data['email'] ?? null, $data['phone'] ?? null, $data['status'] ?? 'active', $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// TASKS (with notifications)
// ============================================
elseif ($resource === 'tasks') {
    if ($method === 'GET') {
        if (getUserRole() === 'staff') {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assignee = ? ORDER BY created_at DESC");
            $stmt->execute([$_SESSION['username']]);
        } else {
            $stmt = $pdo->query("SELECT * FROM tasks ORDER BY created_at DESC");
        }
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (getUserRole() === 'staff') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) jsonOut(['error' => 'Staff cannot create tasks'], 403);
            $check = $pdo->prepare("SELECT assignee FROM tasks WHERE id = ?");
            $check->execute([$data['id']]);
            $task = $check->fetch();
            if (!$task || $task['assignee'] !== $_SESSION['username']) jsonOut(['error' => 'You can only update your own tasks'], 403);
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare("UPDATE tasks SET title=?, assignee=?, due=?, status=?, priority=?, notes=?, comments=? WHERE id=?");
            $stmt->execute([$data['title'], $data['assignee'] ?? null, $data['due'] ?? null, $data['status'] ?? 'todo', $data['priority'] ?? 'medium', $data['notes'] ?? null, $data['comments'] ?? null, $data['id']]);
            // Create notification for assignee
            if (!empty($data['assignee'])) {
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $userStmt->execute([$data['assignee']]);
                $userId = $userStmt->fetchColumn();
                if ($userId) {
                    $notifId = uniqid();
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (id, user_id, type, title, message, related_id) VALUES (?,?,?,?,?,?)");
                    $notifStmt->execute([$notifId, $userId, 'task_assigned', 'New Task Assigned', "You have been assigned: {$data['title']}", $data['id']]);
                }
            }
            jsonOut(['success' => true, 'id' => $data['id']]);
        } else {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO tasks (id, title, assignee, due, status, priority, notes, comments) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['title'], $data['assignee'] ?? null, $data['due'] ?? null, $data['status'] ?? 'todo', $data['priority'] ?? 'medium', $data['notes'] ?? null, $data['comments'] ?? null]);
            jsonOut(['success' => true, 'id' => $newId]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// SALES (with platform types and product cost)
// ============================================
elseif ($resource === 'sales') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM sales ORDER BY sale_date DESC");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $qty = (float)($data['qty'] ?? 0);
        $unitprice = (float)($data['unitprice'] ?? 0);
        $productId = $data['product_id'] ?? null;
        $productCost = 0;
        if ($productId) {
            $costStmt = $pdo->prepare("SELECT SUM(pm.quantity_needed * rm.unit_cost) as total_cost FROM product_materials pm JOIN raw_materials rm ON pm.material_id = rm.id WHERE pm.product_id = ?");
            $costStmt->execute([$productId]);
            $productCost = (float)($costStmt->fetch()['total_cost'] ?? 0);
        }
        $unitcost = $productCost;
        $amount = $qty * $unitprice;
        $cost = $qty * $unitcost;
        // Deduct product quantity
        if ($productId) {
            $pdo->prepare("UPDATE products SET qty = qty - ? WHERE id = ? AND qty >= ?")->execute([$qty, $productId, $qty]);
            // Deduct raw materials
            $matStmt = $pdo->prepare("SELECT material_id, quantity_needed FROM product_materials WHERE product_id = ?");
            $matStmt->execute([$productId]);
            while ($mat = $matStmt->fetch()) {
                $pdo->prepare("UPDATE raw_materials SET qty = qty - ? WHERE id = ?")->execute([$qty * $mat['quantity_needed'], $mat['material_id']]);
            }
        }
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO sales (id, platform_type, store_name, event_name, product_id, product_name, qty, unitprice, unitcost, amount, cost, sale_date, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['platform_type'] ?? 'online', $data['store_name'] ?? null, $data['event_name'] ?? null, $productId, $data['product_name'] ?? null, $qty, $unitprice, $unitcost, $amount, $cost, $data['sale_date'] ?? date('Y-m-d'), $data['notes'] ?? null]);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE sales SET platform_type=?, store_name=?, event_name=?, product_id=?, product_name=?, qty=?, unitprice=?, unitcost=?, amount=?, cost=?, sale_date=?, notes=? WHERE id=?");
            $stmt->execute([$data['platform_type'] ?? 'online', $data['store_name'] ?? null, $data['event_name'] ?? null, $productId, $data['product_name'] ?? null, $qty, $unitprice, $unitcost, $amount, $cost, $data['sale_date'] ?? date('Y-m-d'), $data['notes'] ?? null, $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// EXPENSES (with raw material purchase)
// ============================================
elseif ($resource === 'expenses') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM expenses ORDER BY expense_date DESC");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $amount = (float)($data['amount'] ?? 0);
        $materialId = $data['material_id'] ?? null;
        $qty = (float)($data['quantity'] ?? 1);
        if ($materialId && $data['category'] === 'Raw Materials') {
            $matStmt = $pdo->prepare("SELECT unit_cost FROM raw_materials WHERE id = ?");
            $matStmt->execute([$materialId]);
            $unitCost = (float)($matStmt->fetch()['unit_cost'] ?? 0);
            $amount = $qty * $unitCost;
            $pdo->prepare("UPDATE raw_materials SET qty = qty + ? WHERE id = ?")->execute([$qty, $materialId]);
        }
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO expenses (id, description, category, amount, quantity, unit_cost, material_id, status, expense_date) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['description'] ?? null, $data['category'] ?? null, $amount, $qty, $unitCost ?? 0, $materialId, $data['status'] ?? 'pending', $data['expense_date'] ?? date('Y-m-d')]);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE expenses SET description=?, category=?, amount=?, quantity=?, unit_cost=?, material_id=?, status=?, expense_date=? WHERE id=?");
            $stmt->execute([$data['description'] ?? null, $data['category'] ?? null, $amount, $qty, $unitCost ?? 0, $materialId, $data['status'] ?? 'pending', $data['expense_date'] ?? date('Y-m-d'), $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// RETURNS (with loss calculation)
// ============================================
elseif ($resource === 'returns') {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM returns ORDER BY return_date DESC");
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST') {
        if (!canEdit()) jsonOut(['error' => 'Permission denied'], 403);
        $data = json_decode(file_get_contents('php://input'), true);
        $returnQty = (float)($data['return_quantity'] ?? 0);
        $lossPerUnit = (float)($data['loss_per_unit'] ?? 0);
        if (empty($data['id'])) {
            $newId = uniqid();
            $stmt = $pdo->prepare("INSERT INTO returns (id, original_sale_id, product_sku, return_quantity, return_reason, loss_per_unit, return_date, status) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$newId, $data['original_sale_id'] ?? null, $data['product_sku'] ?? null, $returnQty, $data['return_reason'] ?? 'Damaged Item', $lossPerUnit, $data['return_date'] ?? date('Y-m-d'), $data['status'] ?? 'pending']);
            jsonOut(['success' => true, 'id' => $newId]);
        } else {
            $stmt = $pdo->prepare("UPDATE returns SET original_sale_id=?, product_sku=?, return_quantity=?, return_reason=?, loss_per_unit=?, return_date=?, status=? WHERE id=?");
            $stmt->execute([$data['original_sale_id'] ?? null, $data['product_sku'] ?? null, $returnQty, $data['return_reason'] ?? 'Damaged Item', $lossPerUnit, $data['return_date'] ?? date('Y-m-d'), $data['status'] ?? 'pending', $data['id']]);
            jsonOut(['success' => true, 'id' => $data['id']]);
        }
    } elseif ($method === 'DELETE' && $id) {
        if (!canDelete()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM returns WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// NOTIFICATIONS
// ============================================
elseif ($resource === 'notifications') {
    if ($method === 'GET') {
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute([$_SESSION['username']]);
        $userId = $userStmt->fetchColumn();
        if (!$userId) jsonOut([]);
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$userId]);
        jsonOut($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($method === 'POST' && isset($request[1]) && $request[1] === 'read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
// ============================================
// DASHBOARD
// ============================================
elseif ($resource === 'dashboard') {
    if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
    $rawTotal = $pdo->query("SELECT SUM(qty * unit_cost) FROM raw_materials")->fetchColumn();
    $productsTotal = $pdo->query("SELECT SUM(qty * sell_price) FROM products")->fetchColumn();
    $salesRev = $pdo->query("SELECT SUM(amount) FROM sales")->fetchColumn();
    $salesCost = $pdo->query("SELECT SUM(cost) FROM sales")->fetchColumn();
    $profit = $salesRev - $salesCost;
    $expTotal = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn();
    $returnLoss = $pdo->query("SELECT SUM(calculated_loss) FROM returns WHERE status='approved'")->fetchColumn();
    $lowStock = $pdo->query("SELECT COUNT(*) FROM raw_materials WHERE qty < reorder_level")->fetchColumn();
    $openTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'done'")->fetchColumn();
    $monthly = $pdo->query("SELECT DATE_FORMAT(sale_date, '%Y-%m') as month, SUM(amount) as rev, SUM(cost) as cost FROM sales WHERE sale_date IS NOT NULL GROUP BY month ORDER BY month DESC LIMIT 12")->fetchAll();
    jsonOut(['rawTotal' => $rawTotal, 'productsTotal' => $productsTotal, 'salesRev' => $salesRev, 'profit' => $profit, 'expTotal' => $expTotal, 'returnLoss' => $returnLoss, 'lowStock' => $lowStock, 'openTasks' => $openTasks, 'monthly' => $monthly]);
}
// ============================================
// USERS & AUTH (original)
// ============================================
elseif ($resource === 'login') {
    if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    $user = $stmt->fetch();
    if ($user && password_verify($input['password'], $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        jsonOut(['success' => true, 'role' => $user['role'], 'username' => $user['username']]);
    }
    jsonOut(['error' => 'Invalid credentials'], 401);
}
elseif ($resource === 'logout') {
    session_destroy();
    jsonOut(['success' => true]);
}
elseif ($resource === 'users') {
    if ($method === 'GET') {
        if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->query("SELECT id, username, role, email FROM users");
        jsonOut($stmt->fetchAll());
    } elseif ($method === 'POST') {
        if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
        $input = json_decode(file_get_contents('php://input'), true);
        $hash = password_hash($input['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, email) VALUES (?,?,?,?)");
        $stmt->execute([$input['username'], $hash, $input['role'] ?? 'staff', $input['email'] ?? null]);
        jsonOut(['success' => true, 'username' => $input['username']]);
    } elseif ($method === 'DELETE' && $id && $id !== 'admin') {
        if (!canManageUsers()) jsonOut(['error' => 'Permission denied'], 403);
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    }
}
elseif ($resource === 'change-password') {
    if ($method !== 'POST') jsonOut(['error' => 'Method not allowed'], 405);
    $input = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && password_verify($input['old'], $user['password_hash'])) {
        $newHash = password_hash($input['new'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $_SESSION['user_id']]);
        jsonOut(['success' => true]);
    }
    jsonOut(['error' => 'Current password incorrect'], 401);
}
elseif ($resource === 'bulk-delete') {
    if ($method !== 'POST' || !canDelete()) jsonOut(['error' => 'Permission denied'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    $table = $input['module'];
    $ids = $input['ids'];
    $allowed = ['raw_materials', 'products', 'tasks', 'employees', 'sales', 'expenses', 'returns'];
    if (!in_array($table, $allowed)) jsonOut(['error' => 'Invalid module'], 400);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM $table WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    jsonOut(['success' => true]);
}
elseif ($resource === 'activity-log') {
    if ($method !== 'GET') jsonOut(['error' => 'Method not allowed'], 405);
    $stmt = $pdo->prepare("SELECT action, module, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    jsonOut($stmt->fetchAll());
}
else {
    jsonOut(['error' => 'Not found'], 404);
}
