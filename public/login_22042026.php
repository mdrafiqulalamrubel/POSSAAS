<?php
require_once __DIR__ . '/../src/core.php';
if (auth()) { redirect('index.php'); }

$error = '';
$lic_warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = login($_POST['email'] ?? '', $_POST['password'] ?? '');
    if ($result === true) {
        log_activity('login', 'Auth', 'User logged in', $_POST['email'] ?? '');
        // Warn on dashboard if expiry is within 7 days
        $days = lic_days_remaining();
        if ($days >= 0 && $days <= 7) {
            $_SESSION['lic_warning'] = "⚠ Your software license expires in $days day(s). Please contact your administrator.";
        }
        redirect('index.php');
    } elseif ($result === 'license_expired') {
        $lic = lic_read();
        $exp = $lic ? date('d M Y', strtotime($lic['expiry'])) : 'unknown';
        $error = "license_expired:$exp";
    } elseif ($result === 'license_missing') {
        $error = 'license_missing';
    } elseif ($result === 'license_mismatch') {
        $error = 'license_mismatch';
    } else {
        $error = 'invalid_credentials';
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
  .login-box { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 400px; }
  .login-logo { text-align: center; margin-bottom: 28px; }
  .login-logo .icon { font-size: 36px; color: var(--c-primary); display: block; margin-bottom: 6px; }
  .login-logo h1 { font-size: 20px; font-weight: 700; }
  .login-logo p  { font-size: 12px; color: var(--c-muted); }
  .form-group { margin-bottom: 14px; }
  .btn-block { width: 100%; justify-content: center; padding: 11px; font-size: 14px; }
  .login-footer { margin-top: 20px; font-size: 12px; color: var(--c-muted); text-align: center; }
  .lic-error-box { background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:16px 18px;margin-bottom:18px }
  .lic-error-box .lic-icon { font-size:28px;display:block;text-align:center;margin-bottom:8px }
  .lic-error-box .lic-title { font-size:15px;font-weight:800;color:#c81e1e;text-align:center;margin-bottom:6px }
  .lic-error-box .lic-msg   { font-size:13px;color:#7f1d1d;text-align:center;line-height:1.6 }
  .lic-error-box .lic-contact { display:block;margin-top:10px;text-align:center;font-size:12px;color:#9ca3af }
</style>
</head>
<body>
<div class="login-box">
  <div class="login-logo">
    <span class="icon">⬡</span>
    <h1><?= APP_NAME ?></h1>
    <p>Multi-Tenant POS Platform</p>
  </div>

  <?php if (str_starts_with($error, 'license_expired:')): ?>
    <?php $exp_date = substr($error, 16); ?>
    <div class="lic-error-box">
      <span class="lic-icon">🔒</span>
      <div class="lic-title">Software License Expired</div>
      <div class="lic-msg">
        Your license expired on <strong><?= h($exp_date) ?></strong>.<br>
        Access has been suspended.
      </div>
      <span class="lic-contact">Please contact your administrator to renew your subscription.</span>
    </div>

  <?php elseif ($error === 'license_missing'): ?>
    <div class="lic-error-box">
      <span class="lic-icon">❌</span>
      <div class="lic-title">No License Found</div>
      <div class="lic-msg">
        No valid license file was detected on this system.<br>
        The software cannot be activated.
      </div>
      <span class="lic-contact">Please contact your administrator to install a valid license.</span>
    </div>

  <?php elseif ($error === 'license_mismatch'): ?>
    <div class="lic-error-box">
      <span class="lic-icon">⚠</span>
      <div class="lic-title">License Mismatch</div>
      <div class="lic-msg">
        The installed license does not match this account.<br>
        Unauthorized access attempt detected.
      </div>
      <span class="lic-contact">Please contact your administrator immediately.</span>
    </div>

  <?php elseif ($error === 'invalid_credentials'): ?>
    <div class="alert alert-error">Invalid email or password. Please try again.</div>

  <?php endif; ?>

  <?php if (isset($_GET['timeout'])): ?>
    <div class="alert alert-warning">Session expired. Please sign in again.</div>
  <?php endif; ?>

  <?php
  // Only show form if error is NOT a license block
  $is_license_block = in_array($error, ['license_missing','license_mismatch'])
                   || str_starts_with($error, 'license_expired:');
  ?>

  <?php if (!$is_license_block): ?>
  <form method="post">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required autofocus placeholder="you@company.com"
             value="<?= h($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Sign In →</button>
  </form>
  <div class="login-footer">© <?= date('Y') ?> <?= APP_NAME ?></div>
  <?php else: ?>
  <div style="text-align:center;margin-top:8px">
    <a href="login.php" class="btn btn-outline" style="font-size:13px">← Try Again</a>
  </div>
  <?php endif; ?>

</div>
</body>
</html>