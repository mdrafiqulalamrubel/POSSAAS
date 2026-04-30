<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$tid  = tid();
$page_title = 'Billing & Subscription';

$stmt = db()->prepare('SELECT s.*, t.plan FROM subscriptions s JOIN tenants t ON t.id=s.tenant_id WHERE s.tenant_id=?');
$stmt->execute([$tid]);
$sub = $stmt->fetch();

$plans = [
    'basic' => ['name'=>'Basic','price'=> PLAN_BASIC_PRICE,'features'=>['1 Branch','Up to 5 Users','Invoicing & Expenses','Reports','Items / Inventory'],'stripe_price'=>STRIPE_PRICE_BASIC],
    'pro'   => ['name'=>'Pro',  'price'=> PLAN_PRO_PRICE,  'features'=>['Unlimited Branches','Unlimited Users','All Basic features','Priority Support','API Access'],'stripe_price'=>STRIPE_PRICE_PRO],
];

ob_start();
?>
<div style="max-width:860px">

<?php if ($sub && $sub['status'] === 'active'): ?>
<div class="alert alert-success" style="margin-bottom:20px">
  ✅ Your <strong><?= ucfirst($sub['plan']) ?></strong> plan is active.
  <?php if ($sub['current_period_end']): ?>
    Renews on <?= date('d M Y', strtotime($sub['current_period_end'])) ?>.
  <?php endif; ?>
</div>
<?php elseif ($sub && $sub['status'] === 'past_due'): ?>
<div class="alert alert-error" style="margin-bottom:20px">
  ⚠ Your payment is past due. Please update your billing to keep your account active.
</div>
<?php elseif (!$sub || $sub['status'] === 'trialing'): ?>
<div class="alert alert-warning" style="margin-bottom:20px">
  ⏳ You are on a free trial. Upgrade to keep access after your trial ends.
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
<?php foreach ($plans as $key => $plan):
  $is_current = ($sub['plan'] ?? '') === $key && in_array($sub['status'] ?? '', ['active','trialing']);
?>
  <div class="card" style="<?= $is_current ? 'border:2px solid var(--c-primary)' : '' ?>">
    <?php if ($is_current): ?><div style="background:var(--c-primary);color:#fff;text-align:center;padding:6px 0;margin:-20px -20px 16px;border-radius:var(--radius) var(--radius) 0 0;font-size:12px;font-weight:600">✓ CURRENT PLAN</div><?php endif; ?>
    <div style="font-size:22px;font-weight:700;margin-bottom:4px"><?= $plan['name'] ?></div>
    <div style="font-size:32px;font-weight:800;color:var(--c-primary);margin-bottom:16px">
      $<?= $plan['price'] ?><span style="font-size:14px;font-weight:400;color:var(--c-muted)">/month</span>
    </div>
    <ul style="list-style:none;margin-bottom:20px">
      <?php foreach ($plan['features'] as $f): ?>
        <li style="padding:5px 0;font-size:13.5px">✅ <?= h($f) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if (!$is_current): ?>
      <form method="post" action="<?= APP_URL ?>/billing-checkout.php">
        <input type="hidden" name="plan" value="<?= $key ?>">
        <button type="submit" class="btn btn-primary" style="width:100%">
          Upgrade to <?= $plan['name'] ?> — $<?= $plan['price'] ?>/mo
        </button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= APP_URL ?>/billing-portal.php">
        <button type="submit" class="btn btn-outline" style="width:100%">Manage / Cancel</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<!-- Billing history -->
<?php
$events = db()->prepare('SELECT * FROM billing_events WHERE tenant_id=? ORDER BY created_at DESC LIMIT 20');
$events->execute([$tid]);
$evts = $events->fetchAll();
?>
<?php if ($evts): ?>
<div class="card">
  <div class="card-title">Billing History</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Event</th><th>Stripe ID</th></tr></thead>
      <tbody>
      <?php foreach ($evts as $e): ?>
        <tr>
          <td><?= date('d M Y H:i', strtotime($e['created_at'])) ?></td>
          <td><?= h($e['event_type']) ?></td>
          <td style="font-family:monospace;font-size:12px"><?= h($e['stripe_id']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card" style="background:#eff6ff;border-color:#bfdbfe">
  <div style="font-weight:600;margin-bottom:8px">🔑 Setup Instructions</div>
  <ol style="margin-left:18px;font-size:13.5px;line-height:2">
    <li>Create a <a href="https://stripe.com" target="_blank">Stripe account</a> at stripe.com</li>
    <li>Go to <strong>Developers → API Keys</strong> and copy your keys into <code>config.php</code></li>
    <li>Create two Products in Stripe (Basic $<?= PLAN_BASIC_PRICE ?>/mo and Pro $<?= PLAN_PRO_PRICE ?>/mo)</li>
    <li>Copy the <strong>Price IDs</strong> (starting with <code>price_</code>) into <code>config.php</code></li>
    <li>Set your webhook URL to: <code><?= APP_BASE_URL ?>/pos/public/billing-webhook.php</code></li>
    <li>Copy the webhook signing secret into <code>config.php</code></li>
  </ol>
</div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
