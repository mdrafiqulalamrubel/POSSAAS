<?php
/**
 * loyalty-setup.php
 * Admin configures loyalty point earning rules.
 * e.g. "Every 100 taka purchase = 1 point"
 * Settings stored in company_settings as JSON or separate columns.
 *
 * SQL (run once):
 * ALTER TABLE company_settings
 *   ADD COLUMN IF NOT EXISTS loyalty_earn_amount  DECIMAL(10,2) NOT NULL DEFAULT 100.00,
 *   ADD COLUMN IF NOT EXISTS loyalty_earn_points  INT NOT NULL DEFAULT 1,
 *   ADD COLUMN IF NOT EXISTS loyalty_redeem_value DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
 *   ADD COLUMN IF NOT EXISTS loyalty_enabled      TINYINT(1) NOT NULL DEFAULT 1,
 *   ADD COLUMN IF NOT EXISTS loyalty_min_redeem   INT NOT NULL DEFAULT 10,
 *   ADD COLUMN IF NOT EXISTS loyalty_expiry_days  INT NOT NULL DEFAULT 0;
 */
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Loyalty Setup';
$tid = tid();

// Load current settings
$cs_row = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_row->execute([$tid]);
$cs = $cs_row->fetch() ?: [];

$earn_amount   = (float)($cs['loyalty_earn_amount']  ?? 100);
$earn_points   = (int)  ($cs['loyalty_earn_points']  ?? 1);
$redeem_value  = (float)($cs['loyalty_redeem_value'] ?? 1);
$enabled       = (int)  ($cs['loyalty_enabled']      ?? 1);
$min_redeem    = (int)  ($cs['loyalty_min_redeem']   ?? 10);
$expiry_days   = (int)  ($cs['loyalty_expiry_days']  ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_earn_amount  = max(1, (float)$_POST['loyalty_earn_amount']);
    $new_earn_points  = max(1, (int)$_POST['loyalty_earn_points']);
    $new_redeem_value = max(0, (float)$_POST['loyalty_redeem_value']);
    $new_enabled      = isset($_POST['loyalty_enabled']) ? 1 : 0;
    $new_min_redeem   = max(0, (int)$_POST['loyalty_min_redeem']);
    $new_expiry_days  = max(0, (int)$_POST['loyalty_expiry_days']);

    // Upsert company_settings row
    $exists = db()->prepare('SELECT id FROM company_settings WHERE tenant_id=?');
    $exists->execute([$tid]);
    if ($exists->fetch()) {
        db()->prepare('UPDATE company_settings SET
            loyalty_earn_amount=?, loyalty_earn_points=?, loyalty_redeem_value=?,
            loyalty_enabled=?, loyalty_min_redeem=?, loyalty_expiry_days=?
            WHERE tenant_id=?')
            ->execute([$new_earn_amount, $new_earn_points, $new_redeem_value,
                       $new_enabled, $new_min_redeem, $new_expiry_days, $tid]);
    } else {
        db()->prepare('INSERT INTO company_settings
            (tenant_id, loyalty_earn_amount, loyalty_earn_points, loyalty_redeem_value,
             loyalty_enabled, loyalty_min_redeem, loyalty_expiry_days)
            VALUES (?,?,?,?,?,?,?)')
            ->execute([$tid, $new_earn_amount, $new_earn_points, $new_redeem_value,
                       $new_enabled, $new_min_redeem, $new_expiry_days]);
    }

    log_activity('update', 'Loyalty Setup',
        "Updated loyalty rules: every ৳$new_earn_amount = $new_earn_points pt(s). Redeem: 1pt = ৳$new_redeem_value");
    set_flash('success', '✅ Loyalty rules saved successfully.');
    header('Location: loyalty-setup.php'); exit;
}

$currency = !empty($cs['currency']) ? $cs['currency'] : '৳';

ob_start();
?>

<div style="max-width:680px">

<div class="loyalty-setup-card" style="margin-bottom:24px">
  <h2 style="font-size:18px;font-weight:800;margin-bottom:4px">⭐ Loyalty Points Setup</h2>
  <p style="font-size:13px;color:#6b7280;margin-bottom:22px">
    Configure how customers earn and redeem loyalty points on purchases.
  </p>

  <!-- Live preview card -->
  <div class="loyalty-rate-display" id="ratePreview">
    <div class="lrd-sub">Current Earning Rule</div>
    <div class="lrd-big" id="previewText">
      Every <?= $currency ?><?= number_format($earn_amount,0) ?> purchase = <?= $earn_points ?> point<?= $earn_points!=1?'s':'' ?>
    </div>
    <div class="lrd-sub" id="previewRedeem">1 point = <?= $currency ?><?= number_format($redeem_value,2) ?> value</div>
  </div>

  <form method="post">
    <!-- Enable / Disable -->
    <div style="background:#f8f9fb;border:1.5px solid var(--c-border);border-radius:10px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
      <label class="toggle-switch" style="display:flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;letter-spacing:0;font-size:14px;font-weight:600;color:var(--c-text)">
        <input type="checkbox" name="loyalty_enabled" value="1" <?= $enabled?'checked':'' ?>
               id="loyaltyToggle" onchange="updatePreview()"
               style="width:auto;padding:0;accent-color:#1a56db;width:18px;height:18px">
        Enable Loyalty Points Program
      </label>
      <span id="enableStatus" style="font-size:12px;font-weight:700;<?= $enabled?'color:#057a55':'color:#9ca3af' ?>">
        <?= $enabled ? '✓ Active' : '✗ Disabled' ?>
      </span>
    </div>

    <!-- Earning rule -->
    <div style="background:#f0f7ff;border:1.5px solid #bfdbfe;border-radius:10px;padding:18px;margin-bottom:16px">
      <div style="font-weight:800;font-size:14px;margin-bottom:14px;color:#1e40af">📈 Points Earning Rule</div>
      <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;margin-bottom:4px">
        <div class="form-group">
          <label>Purchase Amount (<?= $currency ?>)</label>
          <input type="number" name="loyalty_earn_amount" id="inp_earn_amount"
                 value="<?= $earn_amount ?>" min="1" step="1" oninput="updatePreview()"
                 style="font-size:20px;font-weight:800;text-align:center;border-color:#3b82f6">
          <span style="font-size:11px;color:#6b7280;margin-top:3px">Amount spent to earn points</span>
        </div>
        <div style="text-align:center;font-size:28px;color:#6b7280;padding-top:14px">=</div>
        <div class="form-group">
          <label>Points Earned</label>
          <input type="number" name="loyalty_earn_points" id="inp_earn_points"
                 value="<?= $earn_points ?>" min="1" step="1" oninput="updatePreview()"
                 style="font-size:20px;font-weight:800;text-align:center;border-color:#3b82f6">
          <span style="font-size:11px;color:#6b7280;margin-top:3px">Points per amount above</span>
        </div>
      </div>
      <div style="font-size:12px;color:#1e40af;background:#dbeafe;padding:8px 12px;border-radius:8px;margin-top:8px">
        💡 Example: Set 100 and 1 → Customer spending <?= $currency ?>500 earns 5 points.
        Set 50 and 2 → <?= $currency ?>500 earns 20 points.
      </div>
    </div>

    <!-- Redemption rule -->
    <div style="background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:18px;margin-bottom:16px">
      <div style="font-weight:800;font-size:14px;margin-bottom:14px;color:#065f46">🔄 Redemption Value</div>
      <div class="form-group" style="max-width:260px">
        <label>Value per 1 Point (<?= $currency ?>)</label>
        <input type="number" name="loyalty_redeem_value" id="inp_redeem_value"
               value="<?= $redeem_value ?>" min="0" step="0.01" oninput="updatePreview()"
               style="font-size:18px;font-weight:700;text-align:center;border-color:#6ee7b7">
        <span style="font-size:11px;color:#6b7280;margin-top:3px">e.g. 1.00 = each point worth <?= $currency ?>1</span>
      </div>
    </div>

    <!-- Advanced rules -->
    <div style="background:#fafafa;border:1.5px solid var(--c-border);border-radius:10px;padding:18px;margin-bottom:20px">
      <div style="font-weight:800;font-size:14px;margin-bottom:14px;color:#374151">⚙ Advanced Rules</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="form-group">
          <label>Minimum Points to Redeem</label>
          <input type="number" name="loyalty_min_redeem" value="<?= $min_redeem ?>" min="0" step="1">
          <span style="font-size:11px;color:#6b7280;margin-top:2px">0 = no minimum</span>
        </div>
        <div class="form-group">
          <label>Points Expiry (days)</label>
          <input type="number" name="loyalty_expiry_days" value="<?= $expiry_days ?>" min="0" step="1">
          <span style="font-size:11px;color:#6b7280;margin-top:2px">0 = never expire</span>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="padding:13px 30px;font-size:15px">
      💾 Save Loyalty Rules
    </button>
  </form>
</div>

<!-- Summary table -->
<div class="card">
  <div class="card-title">📊 Points Calculator Preview</div>
  <table>
    <thead><tr><th>Purchase Amount</th><th>Points Earned</th><th>Redemption Value</th></tr></thead>
    <tbody id="calcTable">
      <?php
      $amounts = [100, 250, 500, 1000, 2000, 5000];
      foreach ($amounts as $amt):
        $pts = floor($amt / $earn_amount) * $earn_points;
        $val = $pts * $redeem_value;
      ?>
      <tr>
        <td><?= $currency ?><?= number_format($amt) ?></td>
        <td><strong style="color:#1a56db"><?= number_format($pts) ?> pts</strong></td>
        <td style="color:#057a55"><?= $currency ?><?= number_format($val, 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</div><!-- /max-width -->

<script>
var currency = '<?= addslashes($currency) ?>';
function updatePreview(){
  var ea  = parseFloat(document.getElementById('inp_earn_amount').value)  || 100;
  var ep  = parseFloat(document.getElementById('inp_earn_points').value)  || 1;
  var rv  = parseFloat(document.getElementById('inp_redeem_value').value) || 1;
  var en  = document.getElementById('loyaltyToggle').checked;

  document.getElementById('previewText').textContent =
    'Every ' + currency + ea.toLocaleString() + ' purchase = ' + ep + ' point' + (ep!==1?'s':'');
  document.getElementById('previewRedeem').textContent =
    '1 point = ' + currency + rv.toFixed(2) + ' value';
  document.getElementById('enableStatus').textContent = en ? '✓ Active' : '✗ Disabled';
  document.getElementById('enableStatus').style.color = en ? '#057a55' : '#9ca3af';

  // Rebuild calculator table
  var rows = '';
  [100,250,500,1000,2000,5000].forEach(function(amt){
    var pts = Math.floor(amt/ea)*ep;
    var val = (pts*rv).toFixed(2);
    rows += '<tr><td>' + currency + amt.toLocaleString() + '</td>'
          + '<td><strong style="color:#1a56db">' + pts.toLocaleString() + ' pts</strong></td>'
          + '<td style="color:#057a55">' + currency + parseFloat(val).toLocaleString(undefined,{minimumFractionDigits:2}) + '</td></tr>';
  });
  document.getElementById('calcTable').innerHTML = rows;
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
