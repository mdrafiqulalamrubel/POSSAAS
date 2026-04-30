<?php
$user             = auth();
$flash            = get_flash();
$branches         = get_branches();
$active_branch    = brid();
$cs_stmt          = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([tid()]);
$cs               = $cs_stmt->fetch() ?: [];
$app_display_name = !empty($cs['company_name']) ? $cs['company_name'] : APP_NAME;
$logo_url         = '';
if (!empty($cs['logo_path'])) $logo_url = APP_URL . '/' . ltrim($cs['logo_path'], '/');

// License expiry warning
$lic_days = lic_days_remaining();
$lic_warn = ($lic_days >= 0 && $lic_days <= 7);

// Session license warning (set after login)
$session_lic_warn = $_SESSION['lic_warning'] ?? null;
unset($_SESSION['lic_warning']);
?><!DOCTYPE html>
<html lang="en" id="html-root" data-lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title ?? 'POS') ?> — <?= h($app_display_name) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-w:           270px;
  --sidebar-collapsed-w: 64px;
  --topbar-h:            58px;
  --transition:          0.25s cubic-bezier(.4,0,.2,1);
}

/* ── Sidebar ──────────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  transition: width var(--transition), transform var(--transition);
  overflow: hidden;
  position: fixed; top: 0; left: 0; height: 100vh;
  z-index: 200;
  display: flex;
  flex-direction: column;
}
.sidebar .nav {
  flex: 1 1 0;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: #3a3d56 transparent;
  min-height: 0;
}
.sidebar .nav::-webkit-scrollbar       { width: 4px; }
.sidebar .nav::-webkit-scrollbar-thumb { background: #3a3d56; border-radius: 4px; }
.sidebar .nav::-webkit-scrollbar-track { background: transparent; }
.sidebar-logo   { flex-shrink: 0; }
.branch-form    { flex-shrink: 0; }
.sidebar-footer { flex-shrink: 0; }

/* ── Collapsed state ──────────────────────────────────────── */
body.sidebar-collapsed .sidebar            { width: var(--sidebar-collapsed-w); }
body.sidebar-collapsed .nav-text,
body.sidebar-collapsed .sidebar-logo-text,
body.sidebar-collapsed .nav-section,
body.sidebar-collapsed .user-badge,
body.sidebar-collapsed .btn-logout .nav-text { display: none !important; }
body.sidebar-collapsed .branch-form        { padding: 6px 4px; }
body.sidebar-collapsed .branch-select      { font-size: 0; padding: 7px 4px; text-align: center; }
body.sidebar-collapsed .sidebar-logo       { justify-content: center; padding: 16px 0; }
body.sidebar-collapsed .nav-link           { justify-content: center; padding: 12px 0; }
body.sidebar-collapsed .nav-icon           { font-size: 18px; }
body.sidebar-collapsed .btn-logout         { text-align: center; padding: 8px 0; }
body.sidebar-collapsed .nav-icon-only      { display: inline !important; }
body.sidebar-collapsed .nav-link           { position: relative; }
body.sidebar-collapsed .nav-link:hover::after {
  content: attr(title);
  position: absolute;
  left: calc(var(--sidebar-collapsed-w) + 8px);
  top: 50%; transform: translateY(-50%);
  background: #1f2937; color: #fff;
  padding: 5px 12px; border-radius: 6px;
  font-size: 13px; white-space: nowrap;
  z-index: 500; pointer-events: none;
  box-shadow: 0 4px 12px rgba(0,0,0,.3);
}

/* ── Main content ─────────────────────────────────────────── */
.main-wrap { margin-left: var(--sidebar-w); transition: margin-left var(--transition); }
body.sidebar-collapsed .main-wrap { margin-left: var(--sidebar-collapsed-w); }

/* ── Toggle tab ───────────────────────────────────────────── */
.sidebar-toggle-tab {
  position: fixed;
  top: calc(var(--topbar-h) + 12px);
  left: var(--sidebar-w);
  width: 20px; height: 48px;
  background: #1a56db;
  border-radius: 0 8px 8px 0;
  cursor: pointer; z-index: 300;
  display: flex; align-items: center; justify-content: center;
  transition: left var(--transition), background .15s;
  box-shadow: 2px 2px 8px rgba(0,0,0,.25);
  border: none; padding: 0;
}
.sidebar-toggle-tab:hover { background: #1648c0; }
body.sidebar-collapsed .sidebar-toggle-tab { left: var(--sidebar-collapsed-w); }
.sidebar-toggle-tab .tab-arrow {
  color: #fff; font-size: 13px; font-weight: 700;
  line-height: 1; transition: transform var(--transition); user-select: none;
}
.sidebar-toggle-tab .tab-arrow::after              { content: '‹'; }
body.sidebar-collapsed .sidebar-toggle-tab .tab-arrow::after { content: '›'; }

/* ── Nav group accordion ──────────────────────────────────── */
.nav-group { border-bottom: 1px solid rgba(255,255,255,.06); }
.nav-group-header {
  width: 100%; display: flex; align-items: center; gap: 8px;
  padding: 9px 16px; background: none; border: none; cursor: pointer;
  color: #7b7e9e; font-size: 11px; font-weight: 800;
  text-transform: uppercase; letter-spacing: .09em;
  text-align: left; transition: background .12s, color .12s;
  border-left: 3px solid transparent;
}
.nav-group-header:hover           { background: rgba(255,255,255,.04); color: #b0b3cc; }
.nav-group.open .nav-group-header { color: #c5c8e0; background: rgba(255,255,255,.05); border-left-color: #3f83f8; }
.nav-group-arrow { margin-left: auto; font-size: 10px; transition: transform var(--transition); opacity: .5; }
.nav-group.open  .nav-group-arrow { transform: rotate(90deg); opacity: 1; }

/* Collapsible body — max-height transition */
.nav-group-body {
  max-height: 0;
  overflow: hidden;
  background: rgba(0,0,0,.15);
  transition: max-height 0.28s cubic-bezier(.4,0,.2,1);
}
.nav-group.open .nav-group-body { max-height: 600px; }
.nav-group-body .nav-link       { padding-left: 28px; font-size: 13px; }

/* ── Top-bar sign out ─────────────────────────────────────── */
.topbar-signout {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 13px; background: #c81e1e; color: #fff;
  border-radius: 7px; font-size: 12.5px; font-weight: 700;
  text-decoration: none; white-space: nowrap; margin-left: 6px;
  transition: background .15s, transform .1s; flex-shrink: 0;
}
.topbar-signout:hover { background: #991b1b; transform: translateY(-1px); }

/* ── Mobile overlay ───────────────────────────────────────── */
.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 190; }
.sidebar-overlay.active { display: block; }

/* ── License warning banner ───────────────────────────────── */
.lic-warning-bar {
  background: #fffbeb; border-bottom: 2px solid #f59e0b;
  padding: 8px 20px; font-size: 13px; font-weight: 600;
  color: #92400e; display: flex; align-items: center; gap: 8px;
}

/* ── Mobile ───────────────────────────────────────────────── */
@media (max-width: 768px) {
  .sidebar    { width: var(--sidebar-w) !important; transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-wrap  { margin-left: 0 !important; }
  .sidebar-toggle-tab { display: none !important; }
  .menu-toggle { display: block !important; }
}
@media (min-width: 769px) { .menu-toggle { display: none; } }

@media print {
  .sidebar, .sidebar-toggle-tab, .top-bar, .lic-warning-bar,
  .no-print, .sidebar-overlay { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  @page { margin: 10mm; }
}
</style>
</head>
<body class="<?= (isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed']==='1') ? 'sidebar-collapsed' : '' ?>">

<!-- ══ SIDEBAR ════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <!-- Logo -->
  <div class="sidebar-logo">
    <?php if ($logo_url): ?>
      <img src="<?= h($logo_url) ?>" style="height:38px;max-width:130px;object-fit:contain;border-radius:6px">
      <span class="nav-text sidebar-logo-text" style="font-size:13px;opacity:.85;margin-left:4px"><?= h($app_display_name) ?></span>
    <?php else: ?>
      <span style="font-size:26px;color:#3f83f8;flex-shrink:0">⬡</span>
      <span class="nav-text sidebar-logo-text" style="font-size:15px;font-weight:800"><?= h($app_display_name) ?></span>
    <?php endif; ?>
  </div>

  <!-- Branch switcher -->
  <form method="post" action="<?= APP_URL ?>/branch-switch.php" class="branch-form">
    <select name="branch_id" onchange="this.form.submit()" class="branch-select">
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $b['id']==$active_branch?'selected':'' ?>><?= h($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <!-- Nav -->
  <nav class="nav">
    <?php $p = basename($_SERVER['PHP_SELF']); ?>

    <!-- Dashboard -->
    <a href="<?= APP_URL ?>/index.php"
       class="nav-link <?= $p==='index.php'?'active':'' ?>"
       title="Dashboard">
      <span class="nav-icon">🏠</span>
      <span class="nav-text"><span class="t-en">Dashboard</span><span class="t-bn">ড্যাশবোর্ড</span></span>
    </a>

    <!-- ── INCOME ────────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['pos.php','income.php','income-add.php','sales-returns.php','sales-return-add.php'])?'open':'' ?>" id="group-income">
      <div class="nav-group-header" onclick="toggleNavGroup('group-income')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">📋 INCOME</span><span class="t-bn">📋 আয়</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/pos.php" class="nav-link <?= $p==='pos.php'?'active':'' ?>" title="POS Screen">
          <span class="nav-icon">🖥</span><span class="nav-text"><span class="t-en">POS Screen</span><span class="t-bn">পস স্ক্রিন</span></span>
        </a>
        <a href="<?= APP_URL ?>/income.php" class="nav-link <?= $p==='income.php'?'active':'' ?>" title="Invoices">
          <span class="nav-icon">↑</span><span class="nav-text"><span class="t-en">Invoices</span><span class="t-bn">ইনভয়েস</span></span>
        </a>
        <a href="<?= APP_URL ?>/income-add.php" class="nav-link <?= $p==='income-add.php'?'active':'' ?>" title="New Invoice">
          <span class="nav-icon">+</span><span class="nav-text"><span class="t-en">New Invoice</span><span class="t-bn">নতুন ইনভয়েস</span></span>
        </a>
        <a href="<?= APP_URL ?>/sales-returns.php" class="nav-link <?= in_array($p,['sales-returns.php','sales-return-add.php'])?'active':'' ?>" title="Sales Return">
          <span class="nav-icon">↩</span><span class="nav-text"><span class="t-en">Sales Return</span><span class="t-bn">বিক্রয় ফেরত</span></span>
        </a>
      </div>
    </div>

    <!-- ── INVENTORY ─────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['items.php','item-add.php','barcode-print.php','inventory-report.php','stock-transfers.php','stock-transfer-add.php'])?'open':'' ?>" id="group-inventory">
      <div class="nav-group-header" onclick="toggleNavGroup('group-inventory')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">📦 INVENTORY</span><span class="t-bn">📦 স্টক</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/items.php" class="nav-link <?= in_array($p,['items.php','item-add.php'])?'active':'' ?>" title="Items">
          <span class="nav-icon">📦</span><span class="nav-text"><span class="t-en">Items</span><span class="t-bn">পণ্য তালিকা</span></span>
        </a>
        <a href="<?= APP_URL ?>/barcode-print.php" class="nav-link <?= $p==='barcode-print.php'?'active':'' ?>" title="Barcode Print">
          <span class="nav-icon">▌▌</span><span class="nav-text"><span class="t-en">Barcode Print</span><span class="t-bn">বারকোড প্রিন্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/inventory-report.php" class="nav-link <?= $p==='inventory-report.php'&&($_GET['filter']??'')!=='reorder'?'active':'' ?>" title="Inventory Report">
          <span class="nav-icon">📋</span><span class="nav-text"><span class="t-en">Inventory Report</span><span class="t-bn">স্টক রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/inventory-report.php?filter=reorder" class="nav-link <?= $p==='inventory-report.php'&&($_GET['filter']??'')==='reorder'?'active':'' ?>" title="Low Stock">
          <span class="nav-icon">⚠</span><span class="nav-text"><span class="t-en">Low Stock</span><span class="t-bn">কম স্টক</span></span>
        </a>
        <a href="<?= APP_URL ?>/items.php?expiry=expired" class="nav-link" title="Expired Items">
          <span class="nav-icon">⏰</span><span class="nav-text"><span class="t-en">Expired Items</span><span class="t-bn">মেয়াদোত্তীর্ণ</span></span>
        </a>
        <a href="<?= APP_URL ?>/stock-transfers.php" class="nav-link <?= in_array($p,['stock-transfers.php','stock-transfer-add.php'])?'active':'' ?>" title="Stock Transfer">
          <span class="nav-icon">🔄</span><span class="nav-text"><span class="t-en">Stock Transfer</span><span class="t-bn">স্টক ট্রান্সফার</span></span>
        </a>
      </div>
    </div>

    <!-- ── PURCHASES ─────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['purchases.php','purchase-add.php','purchase-returns.php','purchase-return-add.php'])?'open':'' ?>" id="group-purchases">
      <div class="nav-group-header" onclick="toggleNavGroup('group-purchases')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">🛒 PURCHASES</span><span class="t-bn">🛒 ক্রয়</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/purchases.php" class="nav-link <?= $p==='purchases.php'?'active':'' ?>" title="Purchases">
          <span class="nav-icon">🛒</span><span class="nav-text"><span class="t-en">Purchases</span><span class="t-bn">ক্রয় তালিকা</span></span>
        </a>
        <a href="<?= APP_URL ?>/purchase-add.php" class="nav-link <?= $p==='purchase-add.php'?'active':'' ?>" title="New Purchase">
          <span class="nav-icon">+</span><span class="nav-text"><span class="t-en">New Purchase</span><span class="t-bn">নতুন ক্রয়</span></span>
        </a>
        <a href="<?= APP_URL ?>/purchase-returns.php" class="nav-link <?= in_array($p,['purchase-returns.php','purchase-return-add.php'])?'active':'' ?>" title="Purchase Return">
          <span class="nav-icon">↩</span><span class="nav-text"><span class="t-en">Purchase Return</span><span class="t-bn">ক্রয় ফেরত</span></span>
        </a>
      </div>
    </div>

    <!-- ── EXPENSES ──────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['expenses.php','expense-add.php','expense-categories.php'])?'open':'' ?>" id="group-expenses">
      <div class="nav-group-header" onclick="toggleNavGroup('group-expenses')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">💸 EXPENSES</span><span class="t-bn">💸 খরচ</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/expenses.php" class="nav-link <?= $p==='expenses.php'?'active':'' ?>" title="Expenses">
          <span class="nav-icon">↓</span><span class="nav-text"><span class="t-en">Expenses</span><span class="t-bn">খরচ তালিকা</span></span>
        </a>
        <a href="<?= APP_URL ?>/expense-add.php" class="nav-link <?= $p==='expense-add.php'?'active':'' ?>" title="Add Expense">
          <span class="nav-icon">+</span><span class="nav-text"><span class="t-en">Add Expense</span><span class="t-bn">খরচ যোগ</span></span>
        </a>
        <a href="<?= APP_URL ?>/expense-categories.php" class="nav-link <?= $p==='expense-categories.php'?'active':'' ?>" title="Expense Categories">
          <span class="nav-icon">🏷</span><span class="nav-text"><span class="t-en">Expense Categories</span><span class="t-bn">খরচের ধরন</span></span>
        </a>
      </div>
    </div>

    <!-- ── CUSTOMERS ─────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['customers.php','memberships.php','membership-add.php','loyalty-points.php'])?'open':'' ?>" id="group-customers">
      <div class="nav-group-header" onclick="toggleNavGroup('group-customers')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">👥 CUSTOMERS</span><span class="t-bn">👥 গ্রাহক</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/customers.php" class="nav-link <?= $p==='customers.php'?'active':'' ?>" title="Customers">
          <span class="nav-icon">👤</span><span class="nav-text"><span class="t-en">Customers</span><span class="t-bn">গ্রাহক তালিকা</span></span>
        </a>
        <a href="<?= APP_URL ?>/memberships.php" class="nav-link <?= in_array($p,['memberships.php','membership-add.php'])?'active':'' ?>" title="Memberships">
          <span class="nav-icon">🎖</span><span class="nav-text"><span class="t-en">Memberships</span><span class="t-bn">সদস্যপদ</span></span>
        </a>
        <a href="<?= APP_URL ?>/loyalty-points.php" class="nav-link <?= $p==='loyalty-points.php'?'active':'' ?>" title="Loyalty Points">
          <span class="nav-icon">⭐</span><span class="nav-text"><span class="t-en">Loyalty Points</span><span class="t-bn">লয়্যালটি পয়েন্ট</span></span>
        </a>
      </div>
    </div>

    <!-- ── REPORTS ───────────────────────────────────────── -->
    <div class="nav-group <?= in_array($p,['report.php','report-sales.php','report-dues.php','report-supplier-dues.php','report-purchases.php','report-expenses.php'])?'open':'' ?>" id="group-reports">
      <div class="nav-group-header" onclick="toggleNavGroup('group-reports')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">📊 REPORTS</span><span class="t-bn">📊 রিপোর্ট</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/report.php" class="nav-link <?= $p==='report.php'?'active':'' ?>" title="General Report">
          <span class="nav-icon">▦</span><span class="nav-text"><span class="t-en">General Report</span><span class="t-bn">সাধারণ রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-sales.php" class="nav-link <?= $p==='report-sales.php'?'active':'' ?>" title="Sales Report">
          <span class="nav-icon">📈</span><span class="nav-text"><span class="t-en">Sales Report</span><span class="t-bn">বিক্রয় রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-dues.php" class="nav-link <?= $p==='report-dues.php'?'active':'' ?>" title="Dues Report">
          <span class="nav-icon">💰</span><span class="nav-text"><span class="t-en">Dues Report</span><span class="t-bn">বকেয়া রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-supplier-dues.php" class="nav-link <?= $p==='report-supplier-dues.php'?'active':'' ?>" title="Supplier Dues">
          <span class="nav-icon">🏭</span><span class="nav-text"><span class="t-en">Supplier Dues</span><span class="t-bn">সাপ্লায়ার বকেয়া</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-purchases.php" class="nav-link <?= $p==='report-purchases.php'?'active':'' ?>" title="Purchase Report">
          <span class="nav-icon">📦</span><span class="nav-text"><span class="t-en">Purchase Report</span><span class="t-bn">ক্রয় রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-expenses.php" class="nav-link <?= $p==='report-expenses.php'?'active':'' ?>" title="Expenses Report">
          <span class="nav-icon">📉</span><span class="nav-text"><span class="t-en">Expenses Report</span><span class="t-bn">খরচ রিপোর্ট</span></span>
        </a>
      </div>
    </div>

    <!-- ── SETTINGS (admin/superadmin only) ──────────────── -->
    <?php if (in_array($user['role'] ?? '', ['admin','superadmin'])): ?>
    <div class="nav-group <?= in_array($p,['users.php','branches.php','company-setup.php','admin-cms.php','activity-logs.php','superadmin.php','subscription.php'])?'open':'' ?>" id="group-settings">
      <div class="nav-group-header" onclick="toggleNavGroup('group-settings')">
        <div class="nav-section">
          <span class="nav-text"><span class="t-en">⚙ SETTINGS</span><span class="t-bn">⚙ সেটিংস</span></span>
        </div>
        <span class="nav-group-arrow">›</span>
      </div>
      <div class="nav-group-body">
        <a href="<?= APP_URL ?>/users.php" class="nav-link <?= $p==='users.php'?'active':'' ?>" title="Users">
          <span class="nav-icon">👥</span><span class="nav-text"><span class="t-en">Users</span><span class="t-bn">ব্যবহারকারী</span></span>
        </a>
        <a href="<?= APP_URL ?>/branches.php" class="nav-link <?= $p==='branches.php'?'active':'' ?>" title="Branches">
          <span class="nav-icon">🏢</span><span class="nav-text"><span class="t-en">Branches</span><span class="t-bn">শাখা</span></span>
        </a>
        <a href="<?= APP_URL ?>/company-setup.php" class="nav-link <?= $p==='company-setup.php'?'active':'' ?>" title="Company Setup">
          <span class="nav-icon">⚙</span><span class="nav-text"><span class="t-en">Company Setup</span><span class="t-bn">কোম্পানি সেটআপ</span></span>
        </a>
        <a href="<?= APP_URL ?>/admin-cms.php" class="nav-link <?= $p==='admin-cms.php'?'active':'' ?>" title="Admin CMS">
          <span class="nav-icon">🛠</span><span class="nav-text"><span class="t-en">Admin CMS</span><span class="t-bn">অ্যাডমিন</span></span>
        </a>
        <a href="<?= APP_URL ?>/activity-logs.php" class="nav-link <?= $p==='activity-logs.php'?'active':'' ?>" title="Activity Logs">
          <span class="nav-icon">📝</span><span class="nav-text"><span class="t-en">Activity Logs</span><span class="t-bn">কার্যক্রম লগ</span></span>
        </a>
        <a href="<?= APP_URL ?>/subscription.php"
           class="nav-link <?= $p==='subscription.php'?'active':'' ?>"
           title="Subscription"
           style="<?= $lic_warn ? 'color:#f59e0b;font-weight:700' : '' ?>">
          <span class="nav-icon"><?= $lic_warn ? '⚠' : '🔐' ?></span>
          <span class="nav-text">
            <span class="t-en">Subscription<?= $lic_warn ? " ($lic_days d)" : '' ?></span>
            <span class="t-bn">সাবস্ক্রিপশন</span>
          </span>
        </a>
        <?php if (($user['role'] ?? '') === 'superadmin'): ?>
        <a href="<?= APP_URL ?>/superadmin.php" class="nav-link <?= $p==='superadmin.php'?'active':'' ?>" style="color:#f59e0b" title="Companies">
          <span class="nav-icon">🌐</span><span class="nav-text"><span class="t-en">Companies</span><span class="t-bn">কোম্পানি</span></span>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <div class="user-badge">
      <strong class="nav-text"><?= h($user['name']) ?></strong>
      <span class="nav-text"><?= h(ucfirst($user['role'])) ?> · <?= h($app_display_name) ?></span>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn-logout" title="Sign Out">
      <span class="nav-text"><span class="t-en">➜ Sign Out</span><span class="t-bn">➜ সাইন আউট</span></span>
      <span class="nav-icon-only" style="display:none">➜</span>
    </a>
  </div>

</aside>

<!-- ══ TOGGLE TAB ═══════════════════════════════════════════ -->
<button class="sidebar-toggle-tab no-print" id="sidebarToggleTab"
        onclick="toggleSidebar()" title="Toggle sidebar">
  <span class="tab-arrow"></span>
</button>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebarMobile()"></div>

<!-- ══ MAIN ══════════════════════════════════════════════════ -->
<div class="main-wrap" id="mainWrap">

  <header class="top-bar">
    <button class="menu-toggle" id="menuToggle" onclick="toggleSidebarMobile()">☰</button>
    <h1 class="page-heading"><?= h($page_title ?? '') ?></h1>
    <span class="top-bar-branch"><?php
      foreach ($branches as $b) if ($b['id'] == $active_branch) echo h($b['name']);
    ?></span>
    <div class="lang-toggle no-print" style="margin-left:8px">
      <button class="lang-btn active" id="btn-lang-en" onclick="setLang('en')">EN</button>
      <button class="lang-btn"        id="btn-lang-bn" onclick="setLang('bn')">বাং</button>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="topbar-signout no-print"
       onclick="return confirm('Sign out?')" title="Sign Out">
      <span class="t-en">⎋ Sign Out</span><span class="t-bn">⎋ বের হন</span>
    </a>
  </header>

  <!-- License expiry warning bar -->
  <?php if ($lic_warn): ?>
  <div class="lic-warning-bar no-print">
    ⚠ Your software license expires in <strong><?= $lic_days ?> day(s)</strong>.
    &nbsp;<a href="<?= APP_URL ?>/subscription.php" style="color:#92400e;font-weight:800;text-decoration:underline">Renew Now →</a>
  </div>
  <?php endif; ?>

  <!-- Post-login license warning (one-time session message) -->
  <?php if ($session_lic_warn): ?>
  <div class="alert alert-warning" style="margin:0;border-radius:0">
    <?= h($session_lic_warn) ?>
    &nbsp;<a href="<?= APP_URL ?>/subscription.php" style="font-weight:700">Renew →</a>
  </div>
  <?php endif; ?>

  <!-- Flash message -->
  <?php if ($flash): ?>
  <div class="alert alert-<?= h($flash['type']) ?>" style="margin:0"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <main class="content"><?= $content ?? '' ?></main>

</div>

<script src="<?= APP_URL ?>/assets/app.js"></script>
<script>
// ── Language ───────────────────────────────────────────────
function setLang(lang) {
  document.getElementById('html-root').setAttribute('data-lang', lang);
  document.getElementById('btn-lang-en').classList.toggle('active', lang === 'en');
  document.getElementById('btn-lang-bn').classList.toggle('active', lang === 'bn');
  localStorage.setItem('pos_lang', lang);
}
(function(){ var l = localStorage.getItem('pos_lang'); if (l) setLang(l); })();

// ── Sidebar collapse (desktop) ─────────────────────────────
function toggleSidebar() {
  var collapsed = document.body.classList.toggle('sidebar-collapsed');
  document.cookie = 'sidebar_collapsed=' + (collapsed?'1':'0') + ';path=/;max-age=31536000';
}
(function(){
  if (document.cookie.indexOf('sidebar_collapsed=1') !== -1)
    document.body.classList.add('sidebar-collapsed');
})();

// ── Sidebar (mobile) ───────────────────────────────────────
function toggleSidebarMobile() {
  var s = document.getElementById('sidebar');
  var o = document.getElementById('sidebarOverlay');
  var open = s.classList.toggle('open');
  o.classList.toggle('active', open);
  document.body.classList.toggle('sidebar-mobile-open', open);
}
function closeSidebarMobile() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
  document.body.classList.remove('sidebar-mobile-open');
}
document.querySelectorAll('.nav-link').forEach(function(l) {
  l.addEventListener('click', function() {
    if (window.innerWidth <= 768) closeSidebarMobile();
  });
});

// ── Nav group accordion ────────────────────────────────────
function toggleNavGroup(id) {
  if (document.body.classList.contains('sidebar-collapsed')) return;
  var group = document.getElementById(id);
  if (!group) return;
  var isOpen = group.classList.toggle('open');
  try {
    var states = JSON.parse(localStorage.getItem('nav_groups') || '{}');
    states[id] = isOpen;
    localStorage.setItem('nav_groups', JSON.stringify(states));
  } catch(e) {}
}

// Restore saved states + auto-open active group
document.addEventListener('DOMContentLoaded', function() {
  try {
    var states = JSON.parse(localStorage.getItem('nav_groups') || '{}');
    Object.keys(states).forEach(function(id) {
      var g = document.getElementById(id);
      if (!g) return;
      // Only apply saved state if page didn't already open it via PHP
      if (states[id] && !g.classList.contains('open')) g.classList.add('open');
      if (!states[id]) g.classList.remove('open');
    });
  } catch(e) {}
  // Always ensure the group containing the active link is open
  var active = document.querySelector('.nav-link.active');
  if (active) {
    var body = active.closest('.nav-group-body');
    if (body) {
      var group = body.closest('.nav-group');
      if (group) group.classList.add('open');
    }
  }
});
</script>
</body>
</html>
