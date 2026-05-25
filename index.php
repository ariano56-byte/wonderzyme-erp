<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wonderzyme ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ── Design tokens ── */
:root {
  --bg:        #f0f2f8;
  --sur:       #ffffff;
  --sur2:      #f5f7ff;
  --brd:       #e4e8f0;
  --txt:       #111827;
  --mut:       #6b7280;
  --pri:       #4f46e5;
  --pri-lt:    #eef2ff;
  --pri-h:     #4338ca;
  --grn:       #059669;
  --grn-lt:    #d1fae5;
  --red:       #dc2626;
  --red-lt:    #fee2e2;
  --yel:       #d97706;
  --yel-lt:    #fef3c7;
  --blu:       #2563eb;
  --blu-lt:    #dbeafe;
  --r:         12px;
  --r-sm:      8px;
  --r-lg:      16px;
  --shadow:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow-md: 0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.04);
  --shadow-lg: 0 8px 30px rgba(0,0,0,.12), 0 2px 8px rgba(0,0,0,.06);
  --sidebar-w: 240px;
  --topbar-h:  60px;
}
body.dark {
  --bg:     #0f1117;
  --sur:    #1a1d27;
  --sur2:   #222635;
  --brd:    #2e3347;
  --txt:    #e5e7eb;
  --mut:    #9ca3af;
  --pri:    #6366f1;
  --pri-lt: #1e1b4b;
  --pri-h:  #818cf8;
}

/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', system-ui, sans-serif;
  background: var(--bg);
  color: var(--txt);
  font-size: 14px;
  line-height: 1.5;
  display: flex;
  height: 100vh;
  overflow: hidden;
}

/* ── Sidebar ── */
#sidebar {
  width: var(--sidebar-w);
  background: var(--sur);
  border-right: 1px solid var(--brd);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  z-index: 10;
}
.logo {
  padding: 20px 16px 16px;
  border-bottom: 1px solid var(--brd);
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--pri) 0%, #7c3aed 100%);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.logo-text { font-weight: 700; font-size: 14px; color: var(--txt); line-height: 1.2; }
.logo-sub  { font-size: 11px; color: var(--mut); font-weight: 400; }

nav { flex: 1; padding: 10px 8px; overflow-y: auto; }
.nav-section { font-size: 10px; font-weight: 700; color: var(--mut); text-transform: uppercase; letter-spacing: .08em; padding: 12px 8px 4px; }
nav button {
  display: flex; align-items: center; gap: 10px;
  width: 100%; padding: 9px 10px;
  background: transparent; border: none; border-radius: var(--r-sm);
  font-size: 13px; font-weight: 500; cursor: pointer;
  color: var(--mut); text-align: left;
  transition: all .15s ease;
  margin-bottom: 1px;
}
nav button .nav-icon { width: 18px; height: 18px; flex-shrink: 0; opacity: .7; transition: opacity .15s; }
nav button:hover  { background: var(--sur2); color: var(--txt); }
nav button:hover .nav-icon { opacity: 1; }
nav button.active { background: var(--pri-lt); color: var(--pri); font-weight: 600; }
nav button.active .nav-icon { opacity: 1; }

.sidebar-footer {
  padding: 12px;
  border-top: 1px solid var(--brd);
  display: flex; align-items: center; gap: 8px;
}
.user-avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: linear-gradient(135deg, var(--pri), #7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
}
.user-info { flex: 1; min-width: 0; }
.user-name { font-size: 12px; font-weight: 600; color: var(--txt); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-role { font-size: 10px; color: var(--mut); }
.icon-btn {
  width: 28px; height: 28px; border-radius: 7px;
  background: transparent; border: 1px solid var(--brd);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--mut); transition: all .15s; flex-shrink: 0;
}
.icon-btn:hover { background: var(--sur2); color: var(--txt); border-color: var(--pri); }

/* ── Main ── */
#main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

#topbar {
  background: var(--sur);
  height: var(--topbar-h);
  padding: 0 20px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid var(--brd);
  gap: 12px;
  flex-shrink: 0;
}
.page-title { font-weight: 700; font-size: 16px; color: var(--txt); }
.topbar-right { display: flex; align-items: center; gap: 8px; }
.search-wrap { position: relative; }
.search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--mut); pointer-events: none; }
#gs {
  padding: 7px 12px 7px 34px;
  border: 1px solid var(--brd); border-radius: var(--r-sm);
  background: var(--bg); color: var(--txt); font-size: 13px;
  width: 220px; transition: border-color .15s, width .2s;
  font-family: inherit;
}
#gs:focus { outline: none; border-color: var(--pri); background: var(--sur); width: 260px; }

/* ── Content ── */
#content { flex: 1; overflow-y: auto; padding: 24px; width: 100%; min-width: 0; }

/* ── Buttons ── */
.btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: var(--r-sm);
  border: 1px solid var(--brd); background: var(--sur);
  cursor: pointer; font-size: 13px; font-weight: 500;
  color: var(--txt); transition: all .15s;
  font-family: inherit; white-space: nowrap;
}
.btn:hover { background: var(--sur2); border-color: var(--pri); color: var(--pri); }
.btn.pri { background: var(--pri); color: #fff; border-color: var(--pri); }
.btn.pri:hover { background: var(--pri-h); border-color: var(--pri-h); color: #fff; }
.btn.grn { background: var(--grn); color: #fff; border-color: var(--grn); }
.btn.dan { background: var(--red); color: #fff; border-color: var(--red); }
.btn.dan:hover { opacity: .9; }
.btn.sm { padding: 4px 10px; font-size: 12px; }
.btn.ghost { background: transparent; border-color: transparent; }
.btn.ghost:hover { background: var(--sur2); border-color: var(--brd); color: var(--txt); }

/* ── Cards ── */
.card {
  background: var(--sur);
  border: 1px solid var(--brd);
  border-radius: var(--r);
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: var(--shadow);
  width: 100%;
  box-sizing: border-box;
}
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px; flex-wrap: wrap; gap: 10px;
}
.card-title { font-size: 15px; font-weight: 700; color: var(--txt); }
.card-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Metrics grid ── */
.mg { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 14px; margin-bottom: 22px; width: 100%; }
.met {
  background: var(--sur); border: 1px solid var(--brd);
  border-radius: var(--r); padding: 18px 16px;
  box-shadow: var(--shadow); position: relative; overflow: hidden;
  transition: transform .15s, box-shadow .15s;
}
.met:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.met-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 12px;
}
.met .lbl { font-size: 11px; color: var(--mut); font-weight: 600; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
.met .val { font-size: 24px; font-weight: 800; color: var(--txt); letter-spacing: -.5px; }
.met .chg { font-size: 11px; color: var(--mut); margin-top: 3px; }
.met .accent { position: absolute; right: -10px; bottom: -10px; width: 60px; height: 60px; border-radius: 50%; opacity: .06; }

/* ── Tables ── */
.table-wrap { overflow-x: auto; border-radius: var(--r-sm); }
table { width: 100%; border-collapse: separate; border-spacing: 0; }
thead tr { background: var(--bg); }
th {
  padding: 10px 14px; text-align: left;
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--mut);
  border-bottom: 2px solid var(--brd); white-space: nowrap;
}
td {
  padding: 11px 14px; border-bottom: 1px solid var(--brd);
  font-size: 13px; vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tbody tr { transition: background .1s; }
tbody tr:hover td { background: var(--sur2); }

/* ── Badges ── */
.badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 600; white-space: nowrap;
}
.g  { background: var(--grn-lt); color: #065f46; }
.r  { background: var(--red-lt); color: #991b1b; }
.b  { background: var(--blu-lt); color: #1e40af; }
.y  { background: var(--yel-lt); color: #92400e; }
.gr { background: #f1f5f9;       color: #475569; }
.inv-b { font-size: 10px; padding: 1px 5px; border-radius: 4px; background: var(--blu-lt); color: var(--blu); margin-left: 4px; font-weight: 600; }

/* ── Modal ── */
.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); backdrop-filter: blur(4px);
  align-items: center; justify-content: center; z-index: 100;
}
.modal-bg.open { display: flex; animation: fadeIn .15s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.modal {
  background: var(--sur); border-radius: var(--r-lg);
  padding: 28px; width: 580px; max-width: 96vw;
  max-height: 92vh; overflow-y: auto;
  box-shadow: var(--shadow-lg);
  animation: slideUp .2s ease;
}
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 20px; color: var(--txt); }
.fa { display: flex; gap: 8px; margin-top: 20px; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--brd); }

/* ── Forms ── */
.fr { margin-bottom: 14px; }
.fr label { display: block; font-size: 11px; font-weight: 700; margin-bottom: 5px; color: var(--mut); text-transform: uppercase; letter-spacing: .06em; }
.fr input, .fr select, .fr textarea {
  width: 100%; padding: 9px 12px;
  border: 1.5px solid var(--brd); border-radius: var(--r-sm);
  background: var(--sur); color: var(--txt);
  font-size: 13px; font-family: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.fr input:focus, .fr select:focus, .fr textarea:focus {
  outline: none; border-color: var(--pri);
  box-shadow: 0 0 0 3px rgba(79,70,229,.1);
}
.fr.hl label { color: var(--pri); }
.fr.ro input, .fr.ro select { background: var(--sur2); color: var(--mut); }
.tip {
  font-size: 12px; color: var(--mut); margin-top: 4px; padding: 8px 12px;
  background: var(--sur2); border-radius: var(--r-sm);
  border-left: 3px solid var(--pri);
}
.bom-box {
  background: var(--sur2); border: 1.5px dashed var(--brd);
  border-radius: var(--r-sm); padding: 14px; margin-top: 8px;
}
.bom-box h4 { font-size: 11px; font-weight: 700; color: var(--pri); margin-bottom: 12px; text-transform: uppercase; letter-spacing: .06em; }
.bom-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
.bom-row select, .bom-row input {
  flex: 1; padding: 7px 10px; border: 1.5px solid var(--brd);
  border-radius: var(--r-sm); background: var(--sur); color: var(--txt); font-size: 13px;
}
.tabs { display: flex; gap: 2px; margin-bottom: 18px; border-bottom: 2px solid var(--brd); }
.tab {
  padding: 9px 18px; border: none; background: transparent; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--mut);
  border-bottom: 2px solid transparent; margin-bottom: -2px; border-radius: 0;
  transition: all .15s; font-family: inherit;
}
.tab:hover { color: var(--txt); }
.tab.active { color: var(--pri); border-bottom-color: var(--pri); }

/* ── Login ── */
#lo {
  position: fixed; inset: 0;
  background: linear-gradient(135deg, #e0e7ff 0%, #f0f2ff 50%, #faf5ff 100%);
  z-index: 200; display: flex; align-items: center; justify-content: center;
}
#lo.hidden { display: none; }
#lb {
  background: var(--sur); border-radius: 20px;
  padding: 36px 32px; width: 400px;
  box-shadow: var(--shadow-lg); border: 1px solid var(--brd);
}
.login-logo {
  display: flex; align-items: center; gap: 12px; margin-bottom: 24px; justify-content: center;
}
.login-logo-icon {
  width: 44px; height: 44px; border-radius: 12px;
  background: linear-gradient(135deg, var(--pri), #7c3aed);
  display: flex; align-items: center; justify-content: center;
}
#lb h2 { font-size: 22px; font-weight: 800; color: var(--txt); }
#lb .sub { color: var(--mut); font-size: 13px; margin-bottom: 22px; text-align: center; }
#le {
  color: var(--red); font-size: 13px; margin-bottom: 12px;
  padding: 10px 12px; background: var(--red-lt); border-radius: var(--r-sm);
  border-left: 3px solid var(--red);
}

/* ── Force PW ── */
.fpw {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.6); backdrop-filter: blur(6px);
  z-index: 300; display: none; align-items: center; justify-content: center;
}
.fpw.open { display: flex; }

/* ── Loader & Snack ── */
#loader {
  position: fixed; inset: 0; background: rgba(0,0,0,.25);
  z-index: 500; display: none; align-items: center; justify-content: center;
  flex-direction: column; gap: 12px;
}
#loader.show { display: flex; }
.spin {
  width: 38px; height: 38px;
  border: 3px solid rgba(255,255,255,.3); border-top-color: #fff;
  border-radius: 50%; animation: sp .7s linear infinite;
}
@keyframes sp { to { transform: rotate(360deg); } }
#sb {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #1e293b; color: #fff; padding: 12px 22px;
  border-radius: 10px; z-index: 300; display: none;
  font-size: 13px; font-weight: 500; min-width: 220px; text-align: center;
  box-shadow: 0 4px 20px rgba(0,0,0,.25);
}
#sb.show  { display: block; animation: slideUp .2s ease; }
#sb.err   { background: var(--red); }

/* ── Notifications ── */
#nd {
  display: none; position: fixed; right: 16px; top: 65px;
  background: var(--sur); border: 1px solid var(--brd);
  border-radius: var(--r); width: 300px; z-index: 50;
  box-shadow: var(--shadow-lg); max-height: 360px; overflow-y: auto;
}
.nd-header { padding: 12px 16px; font-weight: 700; font-size: 13px; border-bottom: 1px solid var(--brd); }
.ni { padding: 12px 16px; border-bottom: 1px solid var(--brd); font-size: 12px; cursor: pointer; transition: background .1s; }
.ni:hover { background: var(--sur2); }
.ni.unr { background: var(--pri-lt); }
.ni .tm { font-size: 10px; color: var(--mut); margin-top: 3px; }
#nc {
  background: var(--red); color: #fff; border-radius: 10px;
  font-size: 9px; font-weight: 700; padding: 1px 5px; margin-left: 3px; display: none;
}

/* ── Charts grid ── */
.charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
@media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }

/* ── Activity feed ── */
.activity-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 0; border-bottom: 1px solid var(--brd);
}
.activity-item:last-child { border-bottom: none; }
.activity-dot {
  width: 8px; height: 8px; border-radius: 50%; background: var(--pri);
  margin-top: 5px; flex-shrink: 0;
}
.activity-text { font-size: 13px; color: var(--txt); }
.activity-time { font-size: 11px; color: var(--mut); }

/* ── Empty state ── */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  padding: 52px 20px; color: var(--mut); gap: 10px;
}
.empty-state svg { opacity: .3; }
.empty-state p { font-size: 14px; }

/* ── Print ── */
@media print {
  #sidebar,#topbar,#loader,#sb,#nd,.modal-bg,.btn,.fa,.tabs .tab,
  .bom-box button,[onclick]{display:none!important}
  body{overflow:visible;height:auto}#main{overflow:visible}
  #content{overflow:visible;padding:0}
  table{border-collapse:collapse}th,td{border:1px solid #ccc!important}
  .card{box-shadow:none;border:1px solid #ccc}
  #ph{display:block!important}@page{margin:1cm}
}
#ph{display:none;font-size:12px;color:var(--mut);padding-bottom:8px;border-bottom:1px solid var(--brd);margin-bottom:12px}
.si { font-family: inherit; }
</style>
</head>
<body>

<!-- Force password change -->
<div class="fpw" id="fpw">
  <div class="modal" style="width:420px">
    <h3>🔒 Password Change Required</h3>
    <p style="color:var(--mut);font-size:13px;margin-bottom:20px">You must set a new password before continuing.</p>
    <div class="fr"><label>New Password (min 8 chars)</label><input id="fn" type="password"></div>
    <div class="fr"><label>Confirm New Password</label><input id="fc" type="password"></div>
    <div id="fe" style="color:var(--red);display:none;font-size:13px;margin-bottom:8px"></div>
    <button class="btn pri" style="width:100%;padding:11px;justify-content:center" onclick="submitFpw()">Set Password &amp; Continue</button>
  </div>
</div>

<!-- Login -->
<div id="lo">
  <div id="lb">
    <div class="login-logo">
      <div class="login-logo-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
      </div>
      <div>
        <div style="font-size:18px;font-weight:800;color:var(--txt)">Wonderzyme ERP</div>
        <div style="font-size:12px;color:var(--mut)">Business Management Platform</div>
      </div>
    </div>
    <div class="sub">Sign in to your account</div>
    <div class="fr" style="text-align:left">
      <label>Username</label>
      <input id="lu" autocomplete="username" placeholder="Enter username">
      <div id="luh" style="font-size:11px;color:var(--yel);display:none;margin-top:4px">⚠ Spaces will be replaced with underscores</div>
    </div>
    <div class="fr" style="text-align:left">
      <label>Password</label>
      <input id="lp" type="password" autocomplete="current-password" placeholder="Enter password">
    </div>
    <div id="le" style="display:none"></div>
    <button class="btn pri" style="width:100%;padding:11px;justify-content:center;font-size:14px" onclick="doLogin()">Sign In</button>
    <div style="margin-top:14px;color:var(--mut);font-size:12px;text-align:center;padding:8px;background:var(--sur2);border-radius:var(--r-sm)">
      🔑 Default: <strong>admin</strong> / <strong>admin</strong>
    </div>
  </div>
</div>

<!-- Sidebar -->
<div id="sidebar">
  <div class="logo">
    <div class="logo-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
    </div>
    <div>
      <div class="logo-text">Wonderzyme ERP</div>
      <div class="logo-sub">Management System</div>
    </div>
  </div>

  <nav id="nav">
    <div class="nav-section">Overview</div>
    <button data-page="dashboard" onclick="go('dashboard')">
      <i data-lucide="layout-dashboard" class="nav-icon"></i> Dashboard
    </button>

    <div class="nav-section">Inventory</div>
    <button data-page="raw-materials" onclick="go('raw-materials')">
      <i data-lucide="flask-conical" class="nav-icon"></i> Raw Materials
    </button>
    <button data-page="products" onclick="go('products')">
      <i data-lucide="package" class="nav-icon"></i> Products
    </button>

    <div class="nav-section">Operations</div>
    <button data-page="tasks" onclick="go('tasks')">
      <i data-lucide="check-square" class="nav-icon"></i> Tasks
    </button>
    <button data-page="employees" onclick="go('employees')">
      <i data-lucide="users" class="nav-icon"></i> Employees
    </button>

    <div class="nav-section">Finance</div>
    <button data-page="sales" onclick="go('sales')">
      <i data-lucide="trending-up" class="nav-icon"></i> Sales
    </button>
    <button data-page="expenses" onclick="go('expenses')">
      <i data-lucide="credit-card" class="nav-icon"></i> Expenses
    </button>
    <button data-page="returns" onclick="go('returns')">
      <i data-lucide="refresh-ccw" class="nav-icon"></i> Returns
    </button>
    <button data-page="returnsAnalytics" onclick="go('returnsAnalytics')">
      <i data-lucide="bar-chart-2" class="nav-icon"></i> Returns Analytics
    </button>
  </nav>

  <div class="sidebar-footer">
    <div class="user-avatar" id="sba">A</div>
    <div class="user-info">
      <div class="user-name" id="sbu">Loading…</div>
      <div class="user-role" id="sbr">—</div>
    </div>
    <button class="icon-btn" onclick="chpwModal()" title="Change Password">
      <i data-lucide="lock" style="width:14px;height:14px"></i>
    </button>
    <button class="icon-btn" onclick="settingsModal()" title="Settings">
      <i data-lucide="settings" style="width:14px;height:14px"></i>
    </button>
    <button class="icon-btn" onclick="doLogout()" title="Logout">
      <i data-lucide="log-out" style="width:14px;height:14px"></i>
    </button>
  </div>
</div>

<!-- Main -->
<div id="main">
  <div id="topbar">
    <div id="pt" class="page-title">Dashboard</div>
    <div class="topbar-right">
      <div class="search-wrap">
        <i data-lucide="search" style="width:15px;height:15px;position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--mut);pointer-events:none"></i>
        <input id="gs" class="si" placeholder="Search anything…" onkeypress="if(event.key==='Enter')gSearch()">
      </div>
      <button class="icon-btn" onclick="toggleNotif()" title="Notifications" style="position:relative">
        <i data-lucide="bell" style="width:15px;height:15px"></i>
        <span id="nc"></span>
      </button>
      <button class="icon-btn" onclick="toggleDark()" title="Toggle dark mode">
        <i data-lucide="moon" style="width:15px;height:15px" id="dark-icon"></i>
      </button>
    </div>
  </div>
  <div id="ph"></div>
  <div id="content">
    <div style="display:flex;align-items:center;justify-content:center;height:100%">
      <div class="spin" style="border-top-color:var(--pri);border-color:var(--brd)"></div>
    </div>
  </div>
</div>

<div id="nd">
  <div class="nd-header">🔔 Notifications</div>
</div>

<!-- Modal -->
<div class="modal-bg" id="mbg">
  <div class="modal">
    <h3 id="mt"></h3>
    <div id="mb"></div>
    <div class="fa">
      <button class="btn ghost" onclick="closeModal()">Cancel</button>
      <button class="btn pri" id="msb">Save Changes</button>
    </div>
  </div>
</div>

<div id="sb"></div>
<div id="loader">
  <div class="spin"></div>
  <div style="color:#fff;font-size:13px;font-weight:500">Loading…</div>
</div>

<script>
const BASE='/erp/api.php';
let role=null,page='dashboard',editId=null,bomRows=[];
let D={tasks:[],employees:[],sales:[],expenses:[],returns:[],rawMats:[],products:[],expCats:[]};
const REASONS=['Damaged Item','Expired','Wrong Item','Cancelled','Defective','Change of Mind','Other'];
const ECOM=['Shopee','Lazada','TikTok Shop','Shopify','Facebook Marketplace','Instagram','Other'];

const $=id=>document.getElementById(id);
const normU=s=>s.trim().replace(/\s+/g,'_');
const esc=v=>String(v??'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
function showL(){$('loader').classList.add('show')}
function hideL(){$('loader').classList.remove('show')}
function snack(m,e=false){
  const s=$('sb');s.textContent=m;
  s.className='show'+(e?' err':'');
  clearTimeout(s._t);s._t=setTimeout(()=>s.className='',3500);
}
function canE(){return role==='admin'||role==='manager'}
function canD(){return role==='admin'}
function isAdmin(){return role==='admin'}

async function api(ep,method='GET',body=null){
  const o={method,headers:{'Content-Type':'application/json'},credentials:'include'};
  if(body) o.body=JSON.stringify(body);
  const r=await fetch(`${BASE}/${ep}`,o);
  const t=await r.text();
  if(!r.ok){let m=t;try{m=JSON.parse(t).error||t}catch(e){}throw new Error(m)}
  return JSON.parse(t);
}

// ── Auth ──
$('lu').addEventListener('input',function(){$('luh').style.display=this.value.includes(' ')?'block':'none'});
$('lp').addEventListener('keypress',e=>{if(e.key==='Enter')doLogin()});

async function doLogin(){
  const u=normU($('lu').value),p=$('lp').value,er=$('le');
  er.style.display='none';
  if(!u||!p){er.textContent='Please enter username and password';er.style.display='block';return}
  try{
    showL();
    const r=await api('login','POST',{username:u,password:p});
    role=r.role;
    $('sbu').textContent=r.username;
    $('sbr').textContent=r.role.charAt(0).toUpperCase()+r.role.slice(1);
    $('sba').textContent=r.username.charAt(0).toUpperCase();
    $('lo').classList.add('hidden');
    if(r.force_pw){hideL();$('fpw').classList.add('open')}
    else{await initApp()}
  }catch(e){hideL();er.textContent=e.message||'Invalid credentials';er.style.display='block'}
}

async function doLogout(){
  try{await api('logout','POST')}catch(e){}
  location.reload();
}

async function submitFpw(){
  const n=$('fn').value,c=$('fc').value,er=$('fe');
  er.style.display='none';
  if(n.length<8){er.textContent='Minimum 8 characters required';er.style.display='block';return}
  if(n!==c){er.textContent='Passwords do not match';er.style.display='block';return}
  try{
    showL();await api('change-password','POST',{old:'admin',new:n});
    $('fpw').classList.remove('open');await initApp();
  }catch(e){hideL();er.textContent=e.message;er.style.display='block'}
}

async function initApp(){
  try{
    showL();
    const[t,em,s,ex,ret,rm,pr]=await Promise.all([
      api('tasks'),api('employees'),api('sales'),api('expenses'),
      api('returns'),api('raw-materials'),api('products')
    ]);
    D.tasks=t;D.employees=em;D.sales=s;D.expenses=ex;D.returns=ret;D.rawMats=rm;D.products=pr;
    await loadExpCats();
    hideL();
    lucide.createIcons();
    await go('dashboard');
    loadNotifs();
  }catch(e){hideL();snack('Init error: '+e.message,true)}
}

// ── Navigation ──
async function go(p){
  page=p;
  // Reset content area styles so flex-centering from loading state doesn't persist
  const ct=$('content');
  ct.removeAttribute('style');
  document.querySelectorAll('#nav button').forEach(b=>b.classList.remove('active'));
  const btn=document.querySelector(`#nav button[data-page="${p}"]`);
  if(btn) btn.classList.add('active');
  const titles={
    'dashboard':'Dashboard','raw-materials':'Raw Materials','products':'Products',
    'tasks':'Tasks','employees':'Employees','sales':'Sales','expenses':'Expenses',
    'returns':'Returns','returnsAnalytics':'Returns Analytics'
  };
  $('pt').textContent=titles[p]||p;
  showL();
  try{
    if(p==='dashboard') await renderDash();
    else if(p==='returnsAnalytics') await renderRetAna();
    else{
      const srcMap={'raw-materials':'rawMats',products:'products',tasks:'tasks',employees:'employees',sales:'sales',expenses:'expenses',returns:'returns'};
      const src=srcMap[p]||p;
      D[src]=await api(p==='raw-materials'?'raw-materials':p);
      renderMod(p);
    }
  }catch(e){$('content').innerHTML=`<div class="card" style="color:var(--red)"><strong>Error:</strong> ${esc(e.message)}</div>`}
  hideL();
}

// ── Dashboard ──
async function renderDash(){
  $('content').removeAttribute('style');
  const s=await api('dashboard');
  const mo=(s.monthly||[]).slice().reverse();
  const lb=mo.map(m=>m.month),rv=mo.map(m=>+m.rev),co=mo.map(m=>+m.cost);

  const metData=[
    {lbl:'Revenue',val:`₱${(+s.salesRev||0).toLocaleString()}`,icon:'trending-up',color:'#4f46e5',bg:'#eef2ff'},
    {lbl:'Profit',val:`₱${(+s.profit||0).toLocaleString()}`,icon:'dollar-sign',color:'#059669',bg:'#d1fae5'},
    {lbl:'Expenses',val:`₱${(+s.expTotal||0).toLocaleString()}`,icon:'credit-card',color:'#dc2626',bg:'#fee2e2'},
    {lbl:'Return Loss',val:`₱${(+s.returnLoss||0).toLocaleString()}`,icon:'refresh-ccw',color:'#d97706',bg:'#fef3c7'},
    {lbl:'Raw Mat. Value',val:`₱${(+s.rawVal||0).toLocaleString()}`,icon:'flask-conical',color:'#7c3aed',bg:'#ede9fe'},
    {lbl:'Products Value',val:`₱${(+s.prodVal||0).toLocaleString()}`,icon:'package',color:'#0891b2',bg:'#cffafe'},
    {lbl:'Low Stock',val:`${s.lowStock||0} items`,icon:'alert-triangle',color:'#ea580c',bg:'#ffedd5'},
    {lbl:'Open Tasks',val:`${s.openTasks||0}`,icon:'check-square',color:'#2563eb',bg:'#dbeafe'},
  ];

  $('content').innerHTML=`
    <div class="mg">
      ${metData.map(m=>`
        <div class="met">
          <div class="met-icon" style="background:${m.bg}">
            <i data-lucide="${m.icon}" style="width:18px;height:18px;color:${m.color}"></i>
          </div>
          <div class="lbl">${m.lbl}</div>
          <div class="val" style="color:${m.color}">${m.val}</div>
          <div class="accent" style="background:${m.color}"></div>
        </div>`).join('')}
    </div>
    <div class="charts-grid">
      <div class="card">
        <div class="card-header"><span class="card-title">Revenue vs Cost</span></div>
        <canvas id="c1" height="180"></canvas>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Monthly Trend</span></div>
        <canvas id="c2" height="180"></canvas>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><span class="card-title">Recent Activity</span></div>
      <div id="af" style="color:var(--mut);font-size:13px">Loading…</div>
    </div>`;

  lucide.createIcons();

  if(window.Chart){
    const chartDefaults={plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'rgba(0,0,0,.04)'}}}};
    new Chart($('c1'),{type:'bar',data:{labels:['Revenue','Cost','Profit'],datasets:[{data:[s.salesRev,+s.salesRev-(+s.profit),s.profit],backgroundColor:['#4f46e5','#ef4444','#059669'],borderRadius:6,borderSkipped:false}]},options:{...chartDefaults}});
    if(lb.length) new Chart($('c2'),{type:'line',data:{labels:lb,datasets:[{label:'Revenue',data:rv,borderColor:'#4f46e5',backgroundColor:'rgba(79,70,229,.08)',tension:.4,fill:true},{label:'Cost',data:co,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.05)',tension:.4,fill:true}]},options:{plugins:{legend:{display:true,labels:{font:{size:12},boxWidth:12}}},scales:{x:{grid:{display:false}},y:{grid:{color:'rgba(0,0,0,.04)'}}}}});
  }
  try{
    const a=await api('activity-log');
    $('af').innerHTML=a.length
      ? a.map(x=>`<div class="activity-item"><div class="activity-dot"></div><div><div class="activity-text">${esc(x.action)} <span style="color:var(--mut);font-size:12px">[${x.module}]</span></div><div class="activity-time">${new Date(x.created_at).toLocaleString()}</div></div></div>`).join('')
      : '<div style="text-align:center;padding:20px;color:var(--mut)">No activity yet</div>';
  }catch(e){}
}

// ── Module config ──
const MCFG={
  'raw-materials':{cols:['name','sku','category','unit','qty','cost_per_unit','reorder_level'],hdrs:['Name','SKU','Category','Unit','Qty','Cost/Unit','Reorder']},
  products:{cols:['name','sku','category','unit','qty','sellprice'],hdrs:['Name','SKU','Category','Unit','Qty','Sell Price']},
  tasks:{cols:['title','assignee','due','status','priority'],hdrs:['Title','Assignee','Due','Status','Priority']},
  employees:{cols:['name','dept','role','email','status','open_tasks'],hdrs:['Name','Dept','Role','Email','Status','Tasks']},
  sales:{cols:['product','channel_type','channel_detail','qty','amount','sale_date'],hdrs:['Product','Channel','Detail','Qty','Amount','Date']},
  expenses:{cols:['description','category','amount','status','expense_date'],hdrs:['Description','Category','Amount','Status','Date']},
  returns:{cols:['return_id','product_sku','return_reason','resolution_status','return_date','total_loss'],hdrs:['Return ID','SKU','Reason','Resolution','Date','Loss']},
};
const SRC={'raw-materials':'rawMats',products:'products',tasks:'tasks',employees:'employees',sales:'sales',expenses:'expenses',returns:'returns'};

function renderMod(mod){
  const cfg=MCFG[mod]||{cols:[],hdrs:[]};
  const src=SRC[mod]||mod;
  const items=D[src]||[];
  let html=`<div class="card">
    <div class="card-header">
      <span class="card-title">${mod.replace(/-/g,' ').replace(/^./,s=>s.toUpperCase())} <span style="font-size:12px;font-weight:400;color:var(--mut);margin-left:4px">${items.length} record${items.length!==1?'s':''}</span></span>
      <div class="card-actions">
        <button class="btn pri" onclick="openAdd('${mod}')">
          <i data-lucide="plus" style="width:14px;height:14px"></i> Add New
        </button>
        <button class="btn" onclick="go('${mod}')">
          <i data-lucide="refresh-cw" style="width:14px;height:14px"></i> Refresh
        </button>
        <button class="btn" onclick="window.print()">
          <i data-lucide="printer" style="width:14px;height:14px"></i> Print
        </button>
      </div>
    </div>
    <div class="table-wrap"><table><thead><tr>
      ${cfg.hdrs.map(h=>`<th>${h}</th>`).join('')}<th>Actions</th>
    </tr></thead><tbody>`;

  if(!items.length){
    html+=`<tr><td colspan="${cfg.cols.length+1}">
      <div class="empty-state">
        <i data-lucide="inbox" style="width:40px;height:40px"></i>
        <p>No records found</p>
        <button class="btn pri" onclick="openAdd('${mod}')">+ Add first record</button>
      </div>
    </td></tr>`;
  } else {
    items.forEach(item=>{
      html+=`<tr>`;
      cfg.cols.forEach(col=>{
        let v=item[col]??'-';
        if(col==='status'||col==='resolution_status'){
          const c=(v==='approved'||v==='active'||v==='done')?'g':(v==='pending'||v==='todo'||v==='in-progress')?'b':'r';
          v=`<span class="badge ${c}">${v}</span>`;
        } else if(col==='priority'){
          const c=v==='high'?'r':v==='medium'?'y':'b';v=`<span class="badge ${c}">${v}</span>`;
        } else if(col==='open_tasks'){
          const c=+v>0?'b':'gr';
          v=`<span class="badge ${c}" style="cursor:pointer" title="Filter tasks" onclick="filterTasksByEmp('${esc(item.name)}')">${v} task${+v!==1?'s':''}</span>`;
        } else if(col==='amount'||col==='total_loss'||col==='cost_per_unit'||col==='sellprice'){
          v=v!=='-'?'₱'+parseFloat(v).toLocaleString(undefined,{minimumFractionDigits:2}):v;
        } else if(col==='return_reason'){
          const c=v==='Damaged Item'||v==='Defective'?'r':v==='Expired'?'y':v==='Cancelled'?'gr':'b';
          v=v!=='-'?`<span class="badge ${c}">${v}</span>`:v;
        } else if(col==='category'&&mod==='expenses'&&item.invId){
          v=`${v}<span class="inv-b">inv</span>`;
        }
        html+=`<td>${v}</td>`;
      });
      const isInvExp=mod==='expenses'&&item.invId;
      html+=`<td style="white-space:nowrap">
        <button class="btn sm" onclick="openEdit('${mod}','${item.id}')">Edit</button>
        ${canD()&&!isInvExp?`<button class="btn sm dan" onclick="delItem('${mod}','${item.id}')">Delete</button>`:''}
      </td></tr>`;
    });
  }
  html+=`</tbody></table></div></div>`;
  $('content').innerHTML=html;
  lucide.createIcons();
}

async function delItem(mod,id){
  if(!confirm('Delete this item? This action cannot be undone.'))return;
  try{
    const ep=mod==='raw-materials'?`raw-materials/${id}`:mod==='products'?`products/${id}`:`${mod}/${id}`;
    await api(ep,'DELETE');snack('Deleted successfully');await go(mod);
  }catch(e){snack(e.message,true)}
}

async function filterTasksByEmp(name){
  await go('tasks');
  D.tasks=D.tasks.filter(t=>t.assignee===name);
  renderMod('tasks');snack(`Showing tasks for ${name}`);
}

async function loadExpCats(){
  try{D.expCats=await api('expense-categories')}
  catch(e){D.expCats=[{id:1,name:'Raw Material'},{id:2,name:'Food & Beverage'},{id:3,name:'Travel & Transport'},{id:4,name:'Marketing'},{id:5,name:'Utilities'},{id:6,name:'Packaging'},{id:7,name:'Salary & Wages'},{id:8,name:'Equipment'},{id:9,name:'Other'}]}
}
function catOpts(cur){return D.expCats.map(c=>`<option value="${esc(c.name)}" ${cur===c.name?'selected':''}>${esc(c.name)}</option>`).join('')}

function fr(lbl,html,hl=false,ro=false){return`<div class="fr${hl?' hl':''}${ro?' ro':''}"><label>${lbl}</label>${html}</div>`}
function fi(id,type='text',val='',extra=''){return`<input id="${id}" type="${type}" value="${esc(val)}" ${extra}>`}
function fsel(id,opts,cur){
  const o=opts.map(x=>{const[v,l]=Array.isArray(x)?x:[x,x];return`<option value="${esc(v)}" ${cur===v?'selected':''}>${esc(l)}</option>`}).join('');
  return`<select id="${id}">${o}</select>`;
}

function buildFields(mod,item=null){
  if(mod==='raw-materials') return `
    ${fr('Name',fi('f-name','text',item?.name))}
    ${fr('SKU',fi('f-sku','text',item?.sku))}
    ${fr('Category',fi('f-category','text',item?.category))}
    ${fr('Unit',fsel('f-unit',[['','— select —'],'g','kg','ml','L','pcs','sheets','rolls','bottles','sachets','packs','Other'],item?.unit||''))}
    ${fr('Quantity',fi('f-qty','number',item?.qty??0,'min="0"'))}
    ${fr('Cost per Unit (₱)',fi('f-cost_per_unit','number',item?.cost_per_unit??0,'step="0.01" min="0"'),true)}
    ${fr('Reorder Level (alert threshold)',fi('f-reorder_level','number',item?.reorder_level??'','min="0" placeholder="optional"'))}
    ${!item?`<div class="tip">💡 Adding a raw material will automatically create an <strong>approved</strong> expense for the total cost (qty × cost/unit).</div>`:''}`;

  if(mod==='products'){
    const rmOpts=(D.rawMats||[]).map(r=>[r.id,`${r.name} (${r.qty} ${r.unit||'units'} avail)`]);
    return `
    ${fr('Name',fi('f-name','text',item?.name))}
    ${fr('SKU',fi('f-sku','text',item?.sku))}
    ${fr('Category',fi('f-category','text',item?.category))}
    ${fr('Unit',fsel('f-unit',[['','— select —'],'pcs','box','pack','set','bottle','sachet','Other'],item?.unit||''))}
    ${fr('Quantity (units in stock)',fi('f-qty','number',item?.qty??0,'min="0"'))}
    ${fr('Sell Price (₱)',fi('f-sellprice','number',item?.sellprice??0,'step="0.01" min="0"'),true)}
    <div class="bom-box" id="bom-box">
      <h4>🔧 Bill of Materials (raw materials used per 1 unit)</h4>
      <div id="bom-rows"></div>
      <button type="button" class="btn sm" onclick="addBomRow()">+ Add Material</button>
    </div>
    ${!item?`<div class="tip" style="margin-top:8px">💡 Saving will deduct BOM quantities × qty from raw material stock.</div>`:''}`;
  }

  if(mod==='tasks'){
    const empOpts=(D.employees||[]).filter(e=>e.status==='active').map(e=>[e.id,e.name]);
    return `
    ${fr('Title',fi('f-title','text',item?.title))}
    ${fr('Assignee (Employee)',`<select id="f-assignee_id" onchange="syncAsgn(this)">
      <option value="">— Unassigned —</option>
      ${empOpts.map(([id,nm])=>`<option value="${id}" ${item?.assignee_id===id?'selected':''}>${esc(nm)}</option>`).join('')}
    </select>`,true)}
    <input type="hidden" id="f-assignee" value="${esc(item?.assignee||'')}">
    ${fr('Due Date',fi('f-due','date',item?.due))}
    ${fr('Status',fsel('f-status',['todo','in-progress','done'],item?.status||'todo'))}
    ${fr('Priority',fsel('f-priority',['low','medium','high'],item?.priority||'medium'),true)}
    ${fr('Notes',`<textarea id="f-notes" rows="3">${esc(item?.notes)}</textarea>`)}`;
  }

  if(mod==='employees') return `
    ${fr('Full Name',fi('f-name','text',item?.name))}
    ${fr('Department',fi('f-dept','text',item?.dept))}
    ${fr('Role / Title',fi('f-role','text',item?.role))}
    ${fr('Email',fi('f-email','email',item?.email))}
    ${fr('Phone',fi('f-phone','tel',item?.phone))}
    ${fr('Status',fsel('f-status',['active','inactive'],item?.status||'active'))}
    ${isAdmin()&&!item?fr('Create Login Account',`<label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:normal;text-transform:none;letter-spacing:0"><input type="checkbox" id="f-create_account" style="width:auto"> Auto-generate username & temporary password</label>`):''}`;

  if(mod==='sales'){
    const prodOpts=(D.products||[]).map(p=>[p.id,`${p.name}${p.sku?' ('+p.sku+')':''} — ${p.qty} in stock`]);
    return `
    ${fr('Channel Type',`<select id="f-channel_type" onchange="updCh()">
      <option value="">— select channel —</option>
      <option value="ecommerce" ${item?.channel_type==='ecommerce'?'selected':''}>E-commerce Platform</option>
      <option value="store" ${item?.channel_type==='store'?'selected':''}>Physical Store</option>
      <option value="event" ${item?.channel_type==='event'?'selected':''}>Event / Bazaar</option>
    </select>`,true)}
    <div id="ch-sub">
      <div id="ch-e" style="display:none">${fr('Platform',`<select id="f-epl"><option value="">— select —</option>${ECOM.map(p=>`<option value="${p}" ${item?.channel_detail===p?'selected':''}>${p}</option>`).join('')}</select>`)}</div>
      <div id="ch-s" style="display:none">${fr('Store Name',fi('f-stn','text',item?.channel_type==='store'?item?.channel_detail:'','placeholder="e.g. National Book Store"'))}</div>
      <div id="ch-v" style="display:none">${fr('Event Name',fi('f-evn','text',item?.channel_type==='event'?item?.channel_detail:'','placeholder="e.g. Manila FAME 2025"'))}</div>
    </div>
    ${fr('Product',`<select id="f-invId" onchange="onProd(this)">
      <option value="">— select product —</option>
      ${prodOpts.map(([id,l])=>`<option value="${id}" ${item?.invId===id?'selected':''}>${esc(l)}</option>`).join('')}
    </select>`,true)}
    <input type="hidden" id="f-product" value="${esc(item?.product||'')}">
    ${fr('Quantity',fi('f-qty','number',item?.qty??1,'min="1"'))}
    ${fr('Unit Price (₱)',fi('f-unitprice','number',item?.unitprice??0,'step="0.01" min="0"'))}
    ${fr('Unit Cost (₱)',fi('f-unitcost','number',item?.unitcost??0,'step="0.01" min="0"'))}
    ${fr('Sale Date',fi('f-sale_date','date',item?.sale_date))}
    ${fr('Notes',fi('f-notes','text',item?.notes))}`;
  }

  if(mod==='expenses'){
    const isLnk=item?.invId;
    return `
    ${fr('Description',fi('f-description','text',item?.description),false,isLnk)}
    ${fr('Category',isLnk?`<input id="f-category" value="${esc(item?.category||'')}" readonly>`:`<select id="f-category">${catOpts(item?.category)}</select>`)}
    ${fr('Quantity',fi('f-quantity','number',item?.quantity??1,'step="0.001" min="0"'),false,isLnk)}
    ${fr('Unit Cost (₱)',fi('f-unit_cost','number',item?.unit_cost??'','step="0.01" min="0" placeholder="optional"'),false,isLnk)}
    ${fr('Amount (₱)',fi('f-amount','number',item?.amount??0,'step="0.01" min="0"'),true)}
    ${fr('Status',fsel('f-status',['pending','approved','rejected'],item?.status||'pending'))}
    ${fr('Expense Date',fi('f-expense_date','date',item?.expense_date))}
    ${isLnk?`<div class="tip">🔗 Linked to raw material. Only <strong>Status</strong> can be changed here.</div>`:'<div class="tip">Amount = Quantity × Unit Cost (auto-calculated when both are filled).</div>'}`;
  }

  if(mod==='returns') return `
    ${fr('Return ID',fi('f-return_id','text',item?.return_id))}
    ${fr('Product SKU',fi('f-product_sku','text',item?.product_sku))}
    ${fr('Return Quantity',fi('f-return_quantity','number',item?.return_quantity??1,'min="1"'))}
    ${fr('Unit Cost at Time of Sale (₱)',fi('f-unit_cost','number',item?.unit_cost??0,'step="0.01" min="0"'))}
    ${fr('Return Reason',fsel('f-return_reason',REASONS,item?.return_reason||''),true)}
    ${fr('Resolution Status',fsel('f-resolution_status',['pending','approved','rejected'],item?.resolution_status||item?.status||'pending'))}
    ${fr('Return Date',fi('f-return_date','date',item?.return_date))}
    ${fr('Return Shipping Cost (₱)',fi('f-return_shipping_cost','number',item?.return_shipping_cost??0,'step="0.01" min="0"'))}
    ${fr('Restocking Labour Cost (₱)',fi('f-restocking_labor_cost','number',item?.restocking_labor_cost??0,'step="0.01" min="0"'))}
    ${fr('Disposal Fee (₱)',fi('f-disposal_fee','number',item?.disposal_fee??0,'step="0.01" min="0"'))}
    ${fr('Salvage Value Recovered (₱)',fi('f-salvage_value_recovered','number',item?.salvage_value_recovered??0,'step="0.01" min="0"'))}
    ${fr('Actual Loss (auto-calculated)',`<input id="f-total_loss" type="number" readonly style="background:var(--sur2);color:var(--pri);font-weight:700">`,true)}
    <div class="tip">Loss = (Unit Cost × Qty) + Shipping + Restocking + Disposal − Salvage</div>
    ${fr('Notes',`<textarea id="f-inspection_notes" rows="2">${esc(item?.inspection_notes)}</textarea>`)}`;
  return '';
}

function syncAsgn(sel){const opt=sel.options[sel.selectedIndex];if($('f-assignee'))$('f-assignee').value=opt?.textContent||''}
function updCh(){
  const v=$('f-channel_type')?.value;if(!v)return;
  $('ch-e').style.display=v==='ecommerce'?'block':'none';
  $('ch-s').style.display=v==='store'?'block':'none';
  $('ch-v').style.display=v==='event'?'block':'none';
}
function onProd(sel){
  const p=(D.products||[]).find(x=>x.id===sel.value);if(!p)return;
  if($('f-product'))$('f-product').value=p.name;
  if($('f-unitprice'))$('f-unitprice').value=p.sellprice||0;
  if($('f-unitcost'))$('f-unitcost').value=p.bom_cost||0;
}
function calcExp(){const q=parseFloat($('f-quantity')?.value||1)||1,u=parseFloat($('f-unit_cost')?.value||0);if(u&&$('f-amount'))$('f-amount').value=(q*u).toFixed(2)}
function calcLoss(){
  const q=parseFloat($('f-return_quantity')?.value||1)||1;
  const u=parseFloat($('f-unit_cost')?.value||0);
  const sh=parseFloat($('f-return_shipping_cost')?.value||0);
  const rs=parseFloat($('f-restocking_labor_cost')?.value||0);
  const df=parseFloat($('f-disposal_fee')?.value||0);
  const sv=parseFloat($('f-salvage_value_recovered')?.value||0);
  if($('f-total_loss'))$('f-total_loss').value=((u*q)+sh+rs+df-sv).toFixed(2);
}
function attachLive(mod){
  if(mod==='expenses'){['f-quantity','f-unit_cost'].forEach(id=>{const e=$(id);if(e)e.addEventListener('input',calcExp)})}
  if(mod==='returns'){['f-return_quantity','f-unit_cost','f-return_shipping_cost','f-restocking_labor_cost','f-disposal_fee','f-salvage_value_recovered'].forEach(id=>{const e=$(id);if(e)e.addEventListener('input',calcLoss)});calcLoss()}
  if(mod==='sales'){updCh();}
}

function renderBomRows(){
  const rm=D.rawMats||[];
  const opts=rm.map(m=>`<option value="${m.id}">${esc(m.name)} (${m.qty??0} ${m.unit||'units'})</option>`).join('');
  const el=$('bom-rows');if(!el)return;
  el.innerHTML=bomRows.map((r,i)=>`<div class="bom-row">
    <select onchange="bomRows[${i}].raw_material_id=this.value">
      <option value="">— material —</option>${opts.replace(`value="${r.raw_material_id}"`,`value="${r.raw_material_id}" selected`)}
    </select>
    <input type="number" value="${r.qty_per_unit||''}" min="0" step="0.001" placeholder="qty/unit" style="max-width:110px" onchange="bomRows[${i}].qty_per_unit=parseFloat(this.value)||0">
    <button type="button" class="btn sm dan" onclick="bomRows.splice(${i},1);renderBomRows()">×</button>
  </div>`).join('');
}
function addBomRow(){bomRows.push({raw_material_id:'',qty_per_unit:1});renderBomRows()}
async function loadBom(pid){
  try{const rows=await api(`product-materials?product_id=${pid}`);bomRows=rows.map(r=>({raw_material_id:r.raw_material_id,qty_per_unit:+r.qty_per_unit}))}
  catch(e){bomRows=[]}
  renderBomRows();
}

function collectForm(){
  const d={};
  document.querySelectorAll('#mb input,#mb select,#mb textarea').forEach(el=>{if(el.id)d[el.id.replace(/^f-/,'')]=el.value});
  return d;
}
function getChannelDetail(d){
  if(d.channel_type==='ecommerce') d.channel_detail=$('f-epl')?.value||'';
  else if(d.channel_type==='store') d.channel_detail=$('f-stn')?.value||'';
  else if(d.channel_type==='event') d.channel_detail=$('f-evn')?.value||'';
  return d;
}

let _item=null;
function openAdd(mod){
  editId=null;bomRows=[];_item=null;
  $('mt').textContent=`Add ${mod.replace(/-/g,' ').replace(/^./,s=>s.toUpperCase())}`;
  $('msb').textContent='Save';
  $('mb').innerHTML=buildFields(mod);
  $('mbg').classList.add('open');
  setTimeout(()=>{attachLive(mod);if(mod==='products')renderBomRows()},50);
  window._save=async()=>{
    let d=collectForm();
    if(mod==='sales') d=getChannelDetail(d);
    if(mod==='products') d.bom=bomRows.filter(r=>r.raw_material_id);
    if(!empty($('f-create_account'))&&$('f-create_account').checked) d.create_account=true;
    try{
      const r=await api(mod==='raw-materials'?'raw-materials':mod,'POST',d);
      closeModal();
      if(r.new_username) snack(`✓ Created. Login: ${r.new_username} / Temp PW: ${r.temp_password}`,false);
      else snack('Saved successfully ✓');
      await go(mod);
    }catch(e){snack(e.message,true)}
  };
}

function empty(el){return !el}

async function openEdit(mod,id){
  const src=SRC[mod]||mod;
  const item=(D[src]||[]).find(i=>i.id===id);if(!item)return;
  editId=id;bomRows=[];_item=item;
  if(mod==='tasks'&&!D.employees.length) D.employees=await api('employees');
  if(mod==='sales'&&!D.products.length) D.products=await api('products');
  $('mt').textContent=`Edit ${mod.replace(/-/g,' ').replace(/^./,s=>s.toUpperCase())}`;
  $('msb').textContent='Save Changes';
  $('mb').innerHTML=buildFields(mod,item);
  $('mbg').classList.add('open');
  setTimeout(async()=>{
    attachLive(mod);
    if(mod==='products'){if(!D.rawMats.length)D.rawMats=await api('raw-materials');await loadBom(id)}
    if(mod==='sales'&&item.channel_type){updCh()}
  },50);
  window._save=async()=>{
    let d=collectForm();d.id=editId;
    if(mod==='sales') d=getChannelDetail(d);
    if(mod==='products') d.bom=bomRows.filter(r=>r.raw_material_id);
    try{
      await api(mod==='raw-materials'?'raw-materials':mod,'POST',d);
      closeModal();snack('Saved successfully ✓');await go(mod);
    }catch(e){snack(e.message,true)}
  };
}

function closeModal(){$('mbg').classList.remove('open');editId=null;window._save=null;bomRows=[];_item=null}
$('msb').addEventListener('click',()=>{if(window._save)window._save()});
$('mbg').addEventListener('click',e=>{if(e.target===$('mbg'))closeModal()});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});

// ── Returns Analytics ──
async function renderRetAna(){
  $('content').removeAttribute('style');
  const s=await api('returns-analytics');
  const rl=Object.keys(s.reason),rv=Object.values(s.reason);
  const ml=Object.keys(s.month).sort(),mv=ml.map(m=>s.month[m]);
  const total=rv.reduce((a,b)=>a+b,0);
  $('content').innerHTML=`
    <div class="mg" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));max-width:600px">
      <div class="met">
        <div class="met-icon" style="background:#fee2e2"><i data-lucide="trending-down" style="width:18px;height:18px;color:#dc2626"></i></div>
        <div class="lbl">Total Return Loss</div>
        <div class="val" style="color:#dc2626">₱${total.toLocaleString(undefined,{minimumFractionDigits:2})}</div>
      </div>
      <div class="met">
        <div class="met-icon" style="background:#fef3c7"><i data-lucide="list" style="width:18px;height:18px;color:#d97706"></i></div>
        <div class="lbl">Distinct Reasons</div>
        <div class="val" style="color:#d97706">${rl.length}</div>
      </div>
    </div>
    <div class="charts-grid">
      <div class="card"><div class="card-header"><span class="card-title">Loss by Reason</span></div><canvas id="cr" height="240"></canvas></div>
      <div class="card"><div class="card-header"><span class="card-title">Monthly Loss Trend</span></div><canvas id="cm" height="240"></canvas></div>
    </div>`;
  lucide.createIcons();
  if(window.Chart&&rl.length){
    new Chart($('cr'),{type:'doughnut',data:{labels:rl,datasets:[{data:rv,backgroundColor:['#4f46e5','#ef4444','#059669','#f59e0b','#8b5cf6','#ec4899','#06b6d4'],borderWidth:2,borderColor:'var(--sur)'}]},options:{plugins:{legend:{position:'bottom',labels:{font:{size:12},boxWidth:12}}}}});
    if(ml.length) new Chart($('cm'),{type:'line',data:{labels:ml,datasets:[{label:'Loss (₱)',data:mv,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.08)',tension:.4,fill:true}]},options:{plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:'rgba(0,0,0,.04)'}}}}});
  }
}

// ── Settings ──
async function settingsModal(){
  if(!isAdmin()){snack('Admin only',true);return}
  let users=[];try{users=await api('users')}catch(e){}
  let html=`<h4 style="margin-bottom:12px;font-size:14px;font-weight:700">User Management</h4>
  <div class="table-wrap" style="margin-bottom:14px"><table><thead><tr><th>Username</th><th>Role</th><th>Actions</th></tr></thead><tbody>`;
  users.forEach(u=>{
    html+=`<tr><td style="font-weight:600">${u.username}</td><td><span class="badge ${u.role==='admin'?'r':u.role==='manager'?'y':'b'}">${u.role}</span></td><td style="display:flex;gap:4px;flex-wrap:wrap">`;
    if(u.username!=='admin'){html+=`<button class="btn sm" onclick="chRole('${u.username}')">Role</button><button class="btn sm" onclick="rstPw('${u.username}')">Reset PW</button><button class="btn sm dan" onclick="delUser('${u.username}')">Del</button>`}
    else html+=`<span class="badge g">Protected</span>`;
    html+=`</td></tr>`;
  });
  html+=`</tbody></table></div>
  <button class="btn pri" onclick="addUserModal()" style="margin-bottom:16px">+ Add User</button>
  <hr style="margin:12px 0;border-color:var(--brd);border-style:solid">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn" onclick="expData()"><i data-lucide="download" style="width:14px;height:14px"></i> Export JSON</button>
    <button class="btn dan" onclick="resetData()"><i data-lucide="alert-triangle" style="width:14px;height:14px"></i> Reset All Data</button>
  </div>`;
  $('mt').textContent='Settings & Users';$('mb').innerHTML=html;$('mbg').classList.add('open');window._save=null;
  $('msb').style.display='none';
  lucide.createIcons();
}

function addUserModal(){
  $('mt').textContent='Add New User';$('msb').style.display='';
  $('mb').innerHTML=`
    ${fr('Username *',`<input id="nu" placeholder="no spaces">`)}
    ${fr('Password *',`<input id="np" type="password">`)}
    ${fr('Role',`<select id="nr"><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Admin</option></select>`)}
    ${fr('Email',`<input id="ne" type="email">`)}`;
  window._save=async()=>{
    const u=normU($('nu').value),p=$('np').value;
    if(!u||!p){snack('Username & password required',true);return}
    try{const r=await api('users','POST',{username:u,password:p,role:$('nr').value,email:$('ne').value});closeModal();settingsModal();snack(`User "${r.username}" added ✓`)}
    catch(e){snack(e.message,true)}
  };
}

function expData(){const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([JSON.stringify(D,null,2)],{type:'application/json'}));a.download=`erp_backup_${new Date().toISOString().slice(0,10)}.json`;a.click()}
function resetData(){if(confirm('Delete ALL data? This cannot be undone.'))api('reset','POST').then(()=>{snack('Reset complete');setTimeout(()=>location.reload(),1500)}).catch(e=>snack(e.message,true))}
function chRole(u){const r=prompt('New role (admin/manager/staff):');if(r)api('users/role','POST',{username:u,role:r}).then(()=>{settingsModal();snack('Role updated ✓')}).catch(e=>snack(e.message,true))}
function delUser(u){if(confirm(`Delete user "${u}"?`))api(`users/${u}`,'DELETE').then(()=>{settingsModal();snack('User deleted')}).catch(e=>snack(e.message,true))}
function rstPw(u){const p=prompt(`New password for ${u}:`);if(p)api('users/reset-password','POST',{username:u,password:p}).then(()=>snack('Password reset ✓')).catch(e=>snack(e.message,true))}

function chpwModal(){
  $('mt').textContent='Change Password';$('msb').style.display='';$('msb').textContent='Save Changes';
  $('mb').innerHTML=`
    ${fr('Current Password',`<input id="co" type="password">`)}
    ${fr('New Password (min 8 chars)',`<input id="cn" type="password">`)}
    ${fr('Confirm New Password',`<input id="cc" type="password">`)}
    <div id="cpe" style="color:var(--red);display:none;font-size:13px;padding:8px;background:var(--red-lt);border-radius:var(--r-sm);margin-top:4px"></div>`;
  $('mbg').classList.add('open');
  window._save=async()=>{
    const o=$('co').value,n=$('cn').value,c=$('cc').value,er=$('cpe');er.style.display='none';
    if(!o||!n||!c){er.textContent='All fields are required';er.style.display='block';return}
    if(n.length<8){er.textContent='Minimum 8 characters required';er.style.display='block';return}
    if(n!==c){er.textContent='Passwords do not match';er.style.display='block';return}
    try{await api('change-password','POST',{old:o,new:n});closeModal();snack('Password changed ✓')}
    catch(e){er.textContent=e.message;er.style.display='block'}
  };
}

// ── Search ──
function gSearch(){
  const t=$('gs').value.trim().toLowerCase();if(!t)return;
  const flds={
    'raw-materials':i=>`${i.name} ${i.sku} ${i.category}`,
    products:i=>`${i.name} ${i.sku} ${i.category}`,
    tasks:i=>`${i.title} ${i.assignee}`,
    employees:i=>`${i.name} ${i.dept} ${i.role} ${i.email}`,
    sales:i=>`${i.product} ${i.channel_detail}`,
    expenses:i=>`${i.description} ${i.category}`,
    returns:i=>`${i.return_id} ${i.product_sku}`
  };
  const srcMap={'raw-materials':'rawMats',products:'products',tasks:'tasks',employees:'employees',sales:'sales',expenses:'expenses',returns:'returns'};
  for(const[mod,fn] of Object.entries(flds)){
    const src=srcMap[mod]||mod;
    if((D[src]||[]).some(i=>(fn(i)||'').toLowerCase().includes(t))){go(mod);return}
  }
  snack('No matches found');
}

// ── Notifications ──
async function loadNotifs(){
  try{
    const a=await api('activity-log');
    const recent=a.slice(0,5);
    $('nd').innerHTML=`<div class="nd-header">🔔 Notifications</div>`+
      (recent.map(x=>`<div class="ni"><div>${esc(x.action)}</div><div class="tm">${x.module} · ${new Date(x.created_at).toLocaleString()}</div></div>`).join('')||'<div class="ni">No notifications</div>');
    if(recent.length){$('nc').style.display='inline';$('nc').textContent=recent.length}
  }catch(e){}
}
function toggleNotif(){
  const nd=$('nd');
  nd.style.display=nd.style.display==='block'?'none':'block';
}
document.addEventListener('click',e=>{if(!e.target.closest('#nd')&&!e.target.closest('button[onclick="toggleNotif()"]'))$('nd').style.display='none'});

// ── Dark mode ──
function toggleDark(){
  document.body.classList.toggle('dark');
  localStorage.setItem('dark',document.body.classList.contains('dark'));
  $('dark-icon').setAttribute('data-lucide', document.body.classList.contains('dark')?'sun':'moon');
  lucide.createIcons();
}
if(localStorage.getItem('dark')==='true') document.body.classList.add('dark');

// ── Init ──
(async()=>{
  lucide.createIcons();
  try{
    const me=await api('whoami');
    if(me.user_id){
      role=me.role;
      $('sbu').textContent=me.username;
      $('sbr').textContent=me.role.charAt(0).toUpperCase()+me.role.slice(1);
      $('sba').textContent=me.username.charAt(0).toUpperCase();
      $('lo').classList.add('hidden');
      if(me.force_pw){$('fpw').classList.add('open')}
      else{await initApp()}
    }
  }catch(e){/* not logged in */}
})();
</script>
</body>
</html>
