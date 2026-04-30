<?php
/**
 * loyalty-points.php
 * View and manage customer loyalty points.
 * Customers earn points per purchase. Cashier can redeem via customer's loyalty password.
 */
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Loyalty Points';
$tid = tid();

// Handle earn / redeem / set-password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'earn') {
        $cid    = (int)$_POST['customer_id'];
        $pts    = (int)$_POST['points'];
        $note   = trim($_POST['note'] ?? 'Manual earn');
        $inv_id = (int)($_POST['invoice_id'] ?? 0);
        db()->prepare('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id=? AND tenant_id=?')
            ->execute([$pts,$cid,$tid]);
        db()->prepare('INSERT INTO loyalty_transactions (tenant_id,customer_id,invoice_id,type,points,note) VALUES (?,?,?,?,?,?)')
            ->execute([$tid,$cid,$inv_id,'earn',$pts,$note]);
        // Auto-upgrade tier
        _upgrade_tier($cid, $tid);
        log_activity('update','Loyalty',"Earned $pts pts for customer ID $cid. Note: $note");
        set_flash('success',"✅ $pts points added.");
        header('Location: loyalty-points.php?customer_id='.$cid); exit;
    }

    if ($action === 'redeem') {
        $cid      = (int)$_POST['customer_id'];
        $pts      = (int)$_POST['points'];
        $password = trim($_POST['redeem_password'] ?? '');
        $note     = trim($_POST['note'] ?? 'Points redeemed');

        // Verify password
        $cust = db()->prepare('SELECT * FROM customers WHERE id=? AND tenant_id=?');
        $cust->execute([$cid,$tid]);
        $cust = $cust->fetch();
        if (!$cust) { set_flash('error','Customer not found.'); header('Location: loyalty-points.php?customer_id='.$cid); exit; }

        if (empty($cust['membership_password']) || !password_verify($password, $cust['membership_password'])) {
            set_flash('error','❌ Incorrect loyalty password. Redemption denied.');
            header('Location: loyalty-points.php?customer_id='.$cid); exit;
        }
        if ($cust['loyalty_points'] < $pts) {
            set_flash('error','Insufficient points. Available: '.$cust['loyalty_points']);
            header('Location: loyalty-points.php?customer_id='.$cid); exit;
        }
        db()->prepare('UPDATE customers SET loyalty_points = loyalty_points - ? WHERE id=? AND tenant_id=?')
            ->execute([$pts,$cid,$tid]);
        db()->prepare('INSERT INTO loyalty_transactions (tenant_id,customer_id,type,points,note) VALUES (?,?,?,?,?)')
            ->execute([$tid,$cid,'redeem',$pts,$note]);
        log_activity('update','Loyalty',"Redeemed $pts pts for customer ID $cid");
        set_flash('success',"✅ $pts points redeemed successfully.");
        header('Location: loyalty-points.php?customer_id='.$cid); exit;
    }

    if ($action === 'set_password') {
        $cid  = (int)$_POST['customer_id'];
        $pass = trim($_POST['new_password'] ?? '');
        if (strlen($pass) < 4) { set_flash('error','Password must be at least 4 characters.'); header('Location: loyalty-points.php?customer_id='.$cid); exit; }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        db()->prepare('UPDATE customers SET membership_password=? WHERE id=? AND tenant_id=?')->execute([$hash,$cid,$tid]);
        log_activity('update','Loyalty',"Set loyalty password for customer ID $cid");
        set_flash('success','Loyalty password updated.');
        header('Location: loyalty-points.php?customer_id='.$cid); exit;
    }
}

// Auto-tier-upgrade helper
function _upgrade_tier(int $cid, int $tid): void {
    $pts_row = db()->prepare('SELECT loyalty_points FROM customers WHERE id=? AND tenant_id=?');
    $pts_row->execute([$cid,$tid]);
    $pts = (int)($pts_row->fetchColumn() ?? 0);
    $tier = db()->prepare('SELECT id FROM membership_tiers WHERE tenant_id=? AND is_active=1 AND min_points<=? ORDER BY min_points DESC LIMIT 1');
    $tier->execute([$tid,$pts]);
    $tier_id = $tier->fetchColumn() ?: null;
    db()->prepare('UPDATE customers SET membership_tier_id=? WHERE id=? AND tenant_id=?')->execute([$tier_id,$cid,$tid]);
}

// Selected customer
$sel_cid = (int)($_GET['customer_id'] ?? 0);
$sel_cust = null;
if ($sel_cid) {
    $cs = db()->prepare('SELECT c.*, mt.name tier_name, mt.color tier_color, mt.discount_pct FROM customers c LEFT JOIN membership_tiers mt ON mt.id=c.membership_tier_id WHERE c.id=? AND c.tenant_id=?');
    $cs->execute([$sel_cid,$tid]);
    $sel_cust = $cs->fetch() ?: null;
}

// Transaction history
$history = [];
if ($sel_cid) {
    $h = db()->prepare('SELECT * FROM loyalty_transactions WHERE customer_id=? AND tenant_id=? ORDER BY created_at DESC LIMIT 50');
    $h->execute([$sel_cid,$tid]);
    $history = $h->fetchAll();
}

// All customers (for search/select)
$all_custs = db()->prepare('SELECT id,name,phone,loyalty_points FROM customers WHERE tenant_id=? ORDER BY name LIMIT 300');
$all_custs->execute([$tid]);
$all_custs = $all_custs->fetchAll();

ob_start();
?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start">

<!-- LEFT: Customer selector -->
<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 16px;background:#1a1d2e;color:#fff;font-weight:700;font-size:14px">⭐ Select Customer</div>
  <div style="padding:10px 12px;border-bottom:1px solid #eef0f5">
    <input type="text" id="custSearch" placeholder="🔍 Search customer…" oninput="filterCusts(this.value)"
           style="width:100%;padding:8px 10px;border:1.5px solid #dde1e9;border-radius:8px;font-size:13px">
  </div>
  <div id="custList" style="max-height:500px;overflow-y:auto">
    <?php foreach ($all_custs as $c): ?>
    <a href="loyalty-points.php?customer_id=<?= $c['id'] ?>"
       class="cust-list-item"
       data-name="<?= strtolower(h($c['name'])) ?>"
       style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #f3f4f6;text-decoration:none;color:inherit;transition:background .1s;<?= $c['id']==$sel_cid?'background:#e8ecff;':''; ?>">
      <div>
        <div style="font-weight:600;font-size:13px"><?= h($c['name']) ?></div>
        <div style="font-size:11px;color:#6b7280"><?= h($c['phone'] ?? '') ?></div>
      </div>
      <div style="font-weight:800;font-size:13px;color:#1a56db"><?= number_format($c['loyalty_points'] ?? 0) ?> pts</div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- RIGHT: Points detail -->
<div>
<?php if (!$sel_cust): ?>
<div class="card" style="text-align:center;padding:60px;color:#9ca3af">
  <div style="font-size:50px;margin-bottom:14px">⭐</div>
  <div style="font-weight:700;font-size:16px;margin-bottom:6px">Select a customer</div>
  <div style="font-size:13px">Choose a customer from the list to view and manage their loyalty points.</div>
</div>
<?php else:
  $clr_map = ['blue'=>['#1a56db','#1e3a8a'],'gold'=>['#b45309','#d97706'],'silver'=>['#4b5563','#9ca3af'],'platinum'=>['#7e3af2','#5521b5'],'green'=>['#057a55','#046243'],'red'=>['#c81e1e','#9b1c1c']];
  $tc = $sel_cust['tier_color'] ?? 'blue';
  [$c1,$c2] = $clr_map[$tc] ?? ['#1a56db','#1e3a8a'];
?>

<!-- Membership card -->
<div class="membership-card" style="background:linear-gradient(135deg,<?= $c1 ?>,<?= $c2 ?>);margin-bottom:16px">
  <div class="mc-bg">🎖</div>
  <div class="mc-tier"><?= h($sel_cust['tier_name'] ?? 'No Tier') ?></div>
  <div class="mc-name"><?= h($sel_cust['name']) ?></div>
  <div style="font-size:12px;opacity:.75;margin-bottom:10px"><?= h($sel_cust['phone'] ?? '') ?></div>
  <div class="mc-pts"><?= number_format($sel_cust['loyalty_points'] ?? 0) ?> <span style="font-size:18px;font-weight:400">Points</span></div>
  <?php if ($sel_cust['discount_pct'] > 0): ?>
  <div style="font-size:12px;opacity:.8;margin-top:6px">🏷 <?= $sel_cust['discount_pct'] ?>% discount on purchases</div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">

  <!-- Earn Points -->
  <div class="card" style="padding:16px">
    <div style="font-weight:800;font-size:14px;margin-bottom:12px;color:#057a55">➕ Earn Points</div>
    <form method="post">
      <input type="hidden" name="_action" value="earn">
      <input type="hidden" name="customer_id" value="<?= $sel_cid ?>">
      <div class="form-group" style="margin-bottom:8px">
        <label>Points to Add</label>
        <input type="number" name="points" min="1" required placeholder="e.g. 50">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label>Note</label>
        <input type="text" name="note" placeholder="Reason / Invoice #">
      </div>
      <button type="submit" class="btn btn-success btn-sm" style="width:100%">➕ Add Points</button>
    </form>
  </div>

  <!-- Redeem Points (password protected) -->
  <div class="card" style="padding:16px">
    <div style="font-weight:800;font-size:14px;margin-bottom:12px;color:#c81e1e">🔄 Redeem Points</div>
    <form method="post">
      <input type="hidden" name="_action" value="redeem">
      <input type="hidden" name="customer_id" value="<?= $sel_cid ?>">
      <div class="form-group" style="margin-bottom:8px">
        <label>Points to Redeem</label>
        <input type="number" name="points" min="1" max="<?= $sel_cust['loyalty_points'] ?>" required placeholder="e.g. 100">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>🔐 Loyalty Password</label>
        <input type="password" name="redeem_password" required placeholder="Customer's password" autocomplete="off">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label>Note</label>
        <input type="text" name="note" placeholder="Redemption reason">
      </div>
      <button type="submit" class="btn btn-danger btn-sm" style="width:100%">🔄 Redeem</button>
    </form>
  </div>

  <!-- Set / Change Password -->
  <div class="card" style="padding:16px">
    <div style="font-weight:800;font-size:14px;margin-bottom:12px;color:#7e3af2">🔐 Loyalty Password</div>
    <div style="font-size:12px;color:#6b7280;margin-bottom:10px">Set or change the customer's loyalty redemption password. Share this with the customer only.</div>
    <form method="post">
      <input type="hidden" name="_action" value="set_password">
      <input type="hidden" name="customer_id" value="<?= $sel_cid ?>">
      <div class="form-group" style="margin-bottom:8px">
        <label>New Password (min 4 chars)</label>
        <input type="password" name="new_password" required placeholder="Set password" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-sm" style="background:#7e3af2;color:#fff;width:100%">🔐 Set Password</button>
    </form>
    <div style="font-size:11px;color:#9ca3af;margin-top:8px">
      Password status: <?= !empty($sel_cust['membership_password']) ? '<span style="color:#057a55;font-weight:700">✓ Set</span>' : '<span style="color:#c81e1e">Not set</span>' ?>
    </div>
  </div>
</div>

<!-- Transaction History -->
<div class="card">
  <div class="card-title">Transaction History</div>
  <?php if (empty($history)): ?>
  <div style="text-align:center;padding:30px;color:#9ca3af;font-size:13px">No transactions yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Type</th><th>Points</th><th>Note</th></tr></thead>
      <tbody>
        <?php foreach ($history as $h): ?>
        <tr class="loyalty-history-row">
          <td style="font-size:12px;white-space:nowrap"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></td>
          <td><?php if ($h['type']==='earn'): ?><span class="badge badge-active">➕ Earn</span><?php else: ?><span class="badge badge-return">🔄 Redeem</span><?php endif; ?></td>
          <td class="<?= $h['type']==='earn'?'pts-earn':'pts-redeem' ?>"><?= $h['type']==='earn'?'+':'-' ?><?= number_format($h['points']) ?></td>
          <td style="font-size:13px"><?= h($h['note']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
</div><!-- /right -->
</div>

<script>
function filterCusts(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.cust-list-item').forEach(function(el) {
    el.style.display = el.dataset.name.includes(q) ? '' : 'none';
  });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
