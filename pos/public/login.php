<?php
require_once __DIR__ . '/../src/core.php';
if (auth()) { redirect('index.php'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('index.php');
    } else {
        $error = 'Invalid credentials or inactive account.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/style.css">
<style>
  body { background: #1e1f2e; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .login-box { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 380px; }
  .login-logo { text-align: center; margin-bottom: 28px; }
  .login-logo .icon { font-size: 36px; color: var(--c-primary); display: block; margin-bottom: 6px; }
  .login-logo h1 { font-size: 20px; font-weight: 700; }
  .login-logo p  { font-size: 12px; color: var(--c-muted); }
  .form-group { margin-bottom: 14px; }
  .btn-block { width: 100%; justify-content: center; padding: 11px; font-size: 14px; }
  .login-footer { margin-top: 20px; font-size: 12px; color: var(--c-muted); text-align: center; }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <span class="icon">⬡</span>
    <h1><?= APP_NAME ?></h1>
    <p>Multi-Tenant POS Platform</p>
  </div>
  <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
  <?php if (isset($_GET['timeout'])): ?><div class="alert alert-warning">Session expired. Please sign in again.</div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required autofocus placeholder="you@company.com" value="admin@demo.com">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••" value="password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
  </form>
  <div class="login-footer">Demo: admin@demo.com / password</div>
</div>
</body>
</html>
