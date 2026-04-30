<?php
require_once __DIR__ . '/../src/core.php';
$page_title = 'Payment Successful';
ob_start();
?>
<div style="max-width:500px;margin:60px auto;text-align:center">
  <div style="font-size:60px;margin-bottom:16px">🎉</div>
  <h2 style="font-size:26px;font-weight:700;margin-bottom:8px">Payment Successful!</h2>
  <p style="color:var(--c-muted);margin-bottom:24px">Your subscription is now active. Thank you for upgrading!</p>
  <a href="<?= APP_URL ?>/index.php" class="btn btn-primary">Go to Dashboard →</a>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
