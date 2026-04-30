<?php
$user  = auth();
$flash = get_flash();
$branches = get_branches();
$active_branch = brid();
// Load company settings for logo/name
$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([tid()]);
$cs = $cs_stmt->fetch() ?: [];
$app_display_name = !empty($cs['company_name']) ? $cs['company_name'] : APP_NAME;
// Logo URL resolution
$logo_url = '';
if (!empty($cs['logo_path'])) {
    $lp = $cs['logo_path'];
    if (file_exists(__DIR__ . '/../public/' . $lp)) {
        $logo_url = APP_URL . '/' . $lp;
    } elseif (file_exists(__DIR__ . '/' . $lp)) {
        $logo_url = APP_URL . '/' . $lp;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title ?? 'POS') ?> — <?= h($app_display_name) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
</head>
<body>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <?php if ($logo_url): ?>
      <img src="<?= h($logo_url) ?>" style="height:36px;max-width:120px;object-fit:contain;margin-bottom:2px">
      <span class="logo-text" style="font-size:12px;opacity:.8"><?= h($app_display_name) ?></span>
    <?php else: ?>
      <span class="logo-icon">⬡</span>
      <span class="logo-text"><?= h($app_display_name) ?></span>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= APP_URL ?>/branch-switch.php" class="branch-form">
    <select name="branch_id" onchange="this.form.submit()" class="branch-select">
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $b['id']==$active_branch?'selected':'' ?>>
          <?= h($b['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <nav class="nav">
    <?php $p = basename($_SERVER['PHP_SELF']); ?>
    <a href="<?= APP_URL ?>/index.php"        class="nav-link <?= $p=='index.php'?'active':'' ?>"><span class="nav-icon">◈</span> Dashboard</a>

    <div class="nav-section">INCOME</div>
    <a href="<?= APP_URL ?>/income.php"        class="nav-link <?= $p=='income.php'?'active':'' ?>"><span class="nav-icon">↑</span> Invoices</a>
    <a href="<?= APP_URL ?>/income-add.php"    class="nav-link <?= $p=='income-add.php'?'active':'' ?>"><span class="nav-icon">+</span> New Invoice</a>

    <div class="nav-section">INVENTORY</div>
    <a href="<?= APP_URL ?>/items.php"                           class="nav-link <?= $p=='items.php'||$p=='item-add.php'?'active':'' ?>"><span class="nav-icon">📦</span> Items</a>
    <a href="<?= APP_URL ?>/inventory-report.php"                class="nav-link <?= $p=='inventory-report.php'&&($_GET['filter']??'')!='reorder'?'active':'' ?>"><span class="nav-icon">📋</span> Inventory Report</a>
    <a href="<?= APP_URL ?>/inventory-report.php?filter=reorder" class="nav-link <?= $p=='inventory-report.php'&&($_GET['filter']??'')==='reorder'?'active':'' ?>"><span class="nav-icon">⚠</span> Low Stock Report</a>

    <div class="nav-section">EXPENSES</div>
    <a href="<?= APP_URL ?>/expenses.php"      class="nav-link <?= $p=='expenses.php'?'active':'' ?>"><span class="nav-icon">↓</span> Expenses</a>
    <a href="<?= APP_URL ?>/expense-add.php"   class="nav-link <?= $p=='expense-add.php'?'active':'' ?>"><span class="nav-icon">+</span> Add Expense</a>

    <div class="nav-section">REPORTS</div>
    <a href="<?= APP_URL ?>/report.php"        class="nav-link <?= $p=='report.php'?'active':'' ?>"><span class="nav-icon">▦</span> General Report</a>

    <?php if (in_array($user['role'],['admin','superadmin'])): ?>
    <div class="nav-section">SETTINGS</div>
    <a href="<?= APP_URL ?>/customers.php"     class="nav-link <?= $p=='customers.php'?'active':'' ?>"><span class="nav-icon">◎</span> Customers</a>
    <a href="<?= APP_URL ?>/users.php"         class="nav-link <?= $p=='users.php'?'active':'' ?>"><span class="nav-icon">◉</span> Users</a>
    <a href="<?= APP_URL ?>/branches.php"      class="nav-link <?= $p=='branches.php'?'active':'' ?>"><span class="nav-icon">◳</span> Branches</a>
    <a href="<?= APP_URL ?>/company-setup.php" class="nav-link <?= $p=='company-setup.php'?'active':'' ?>"><span class="nav-icon">🏢</span> Company Setup</a>
    <a href="<?= APP_URL ?>/admin-cms.php"     class="nav-link <?= $p=='admin-cms.php'?'active':'' ?>"><span class="nav-icon">⚙</span> Admin CMS</a>
    <?php if ($user['role']==='superadmin'): ?>
    <a href="<?= APP_URL ?>/superadmin.php"    class="nav-link <?= $p=='superadmin.php'?'active':'' ?>" style="color:#f59e0b"><span class="nav-icon">🌐</span> Companies</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/billing.php"       class="nav-link <?= $p=='billing.php'?'active':'' ?>"><span class="nav-icon">💳</span> Billing</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="user-badge">
      <strong><?= h($user['name']) ?></strong>
      <span><?= h(ucfirst($user['role'])) ?> · <?= h($app_display_name) ?></span>
    </div>
    <a href="<?= APP_URL ?>/logout.php" class="btn-logout">Sign out</a>
  </div>
</aside>

<div class="main-wrap">
  <header class="top-bar">
    <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <h1 class="page-heading"><?= h($page_title ?? '') ?></h1>
    <span class="top-bar-branch"><?php
      foreach ($branches as $b) if ($b['id']==$active_branch) echo h($b['name']);
    ?></span>
  </header>

  <?php if ($flash): ?>
  <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
  <?php endif; ?>

  <main class="content">
    <?= $content ?? '' ?>
  </main>
</div>

<script src="<?= APP_URL ?>/assets/app.js"></script>
</body>
</html>
