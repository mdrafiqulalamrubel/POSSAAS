<?php
/**
 * memberships.php
 * Manage membership tiers (Gold, Silver, Platinum etc.)
 * Each tier has: name, min_points threshold, discount %, color.
 */
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Membership Tiers';
$tid = tid();

// Handle create / update / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';
    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $min_pts  = (int)($_POST['min_points'] ?? 0);
        $disc     = (float)($_POST['discount_pct'] ?? 0);
        $color    = $_POST['color'] ?? 'blue';
        $active   = isset($_POST['is_active']) ? 1 : 0;
        if ($id) {
            db()->prepare('UPDATE membership_tiers SET name=?,min_points=?,discount_pct=?,color=?,is_active=? WHERE id=? AND tenant_id=?')
                ->execute([$name,$min_pts,$disc,$color,$active,$id,$tid]);
            log_activity('update','Membership',"Updated tier: $name");
        } else {
            db()->prepare('INSERT INTO membership_tiers (tenant_id,name,min_points,discount_pct,color,is_active) VALUES (?,?,?,?,?,?)')
                ->execute([$tid,$name,$min_pts,$disc,$color,$active]);
            log_activity('create','Membership',"Created tier: $name");
        }
        set_flash('success','Membership tier saved.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM membership_tiers WHERE id=? AND tenant_id=?')->execute([$id,$tid]);
        log_activity('delete','Membership',"Deleted tier ID: $id");
        set_flash('success','Tier deleted.');
    }
    header('Location: memberships.php'); exit;
}

$tiers = db()->prepare('SELECT * FROM membership_tiers WHERE tenant_id=? ORDER BY min_points ASC');
$tiers->execute([$tid]);
$tiers = $tiers->fetchAll();

// Customer counts per tier
$counts = [];
$cs = db()->prepare('SELECT membership_tier_id, COUNT(*) c FROM customers WHERE tenant_id=? GROUP BY membership_tier_id');
$cs->execute([$tid]);
foreach ($cs->fetchAll() as $row) $counts[$row['membership_tier_id']] = $row['c'];

ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <h2 style="font-size:18px;font-weight:800;margin:0">🎖 Membership Tiers</h2>
  <button class="btn btn-primary" onclick="openTierModal()">+ New Tier</button>
</div>

<?php if (empty($tiers)): ?>
<div class="card" style="text-align:center;padding:50px;color:#9ca3af">
  <div style="font-size:40px;margin-bottom:12px">🎖</div>
  <div style="font-weight:700;margin-bottom:6px">No membership tiers yet</div>
  <div style="font-size:13px;margin-bottom:18px">Create tiers like Silver, Gold, Platinum to reward loyal customers.</div>
  <button class="btn btn-primary" onclick="openTierModal()">+ Create First Tier</button>
</div>
<?php else: ?>
<div class="expcat-grid">
  <?php foreach ($tiers as $t):
    $clr_map = ['blue'=>'#1a56db','gold'=>'#d97706','silver'=>'#6b7280','platinum'=>'#7e3af2','green'=>'#057a55','red'=>'#c81e1e'];
    $bg = $clr_map[$t['color']] ?? '#1a56db';
  ?>
  <div class="expcat-card" style="border-left:4px solid <?= $bg ?>">
    <div class="expcat-icon" style="background:<?= $bg ?>20">
      <span style="font-size:22px">🎖</span>
    </div>
    <div style="flex:1;min-width:0">
      <div class="expcat-name"><?= h($t['name']) ?></div>
      <div class="expcat-count">Min <?= number_format($t['min_points']) ?> pts · <?= $t['discount_pct'] ?>% off</div>
      <div style="font-size:11px;color:#6b7280;margin-top:2px"><?= $counts[$t['id']] ?? 0 ?> customers · <?= $t['is_active']?'<span style="color:#057a55">Active</span>':'<span style="color:#9ca3af">Inactive</span>' ?></div>
    </div>
    <div style="display:flex;gap:6px;flex-shrink:0">
      <button class="btn btn-outline btn-sm" onclick='openTierModal(<?= json_encode($t) ?>)'>✎</button>
      <form method="post" onsubmit="return confirm('Delete this tier?')">
        <input type="hidden" name="_action" value="delete">
        <input type="hidden" name="id" value="<?= $t['id'] ?>">
        <button class="btn btn-danger btn-sm">✕</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Customers in each tier -->
<div class="card">
  <div class="card-title">Customers by Tier</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Phone</th><th>Points</th><th>Tier</th><th>Actions</th></tr></thead>
      <tbody>
        <?php
        $custs = db()->prepare(
            'SELECT c.*, mt.name tier_name, mt.color tier_color FROM customers c
             LEFT JOIN membership_tiers mt ON mt.id=c.membership_tier_id
             WHERE c.tenant_id=? ORDER BY c.loyalty_points DESC LIMIT 100'
        );
        $custs->execute([$tid]);
        foreach ($custs->fetchAll() as $c):
          $tc = $c['tier_color'] ?? '';
          $clr_map2 = ['blue'=>'#1a56db','gold'=>'#d97706','silver'=>'#6b7280','platinum'=>'#7e3af2','green'=>'#057a55','red'=>'#c81e1e'];
          $tbg = $clr_map2[$tc] ?? '#6b7280';
        ?>
        <tr>
          <td style="font-weight:600"><?= h($c['name']) ?></td>
          <td><?= h($c['phone'] ?? '') ?></td>
          <td><strong style="color:#1a56db"><?= number_format($c['loyalty_points'] ?? 0) ?></strong> pts</td>
          <td>
            <?php if ($c['tier_name']): ?>
              <span style="background:<?= $tbg ?>20;color:<?= $tbg ?>;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700"><?= h($c['tier_name']) ?></span>
            <?php else: ?>
              <span style="color:#9ca3af;font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td><a href="loyalty-points.php?customer_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">⭐ Points</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Tier Modal -->
<div id="tierModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:5000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:14px;padding:30px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <h3 id="tierModalTitle" style="font-size:18px;font-weight:800;margin-bottom:18px">New Membership Tier</h3>
    <form method="post">
      <input type="hidden" name="_action" value="save">
      <input type="hidden" name="id" id="tier_id" value="0">
      <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div class="form-group full">
          <label>Tier Name *</label>
          <input type="text" name="name" id="tier_name" required placeholder="e.g. Gold, Silver, Platinum">
        </div>
        <div class="form-group">
          <label>Min Points Required</label>
          <input type="number" name="min_points" id="tier_min_points" value="0" min="0">
        </div>
        <div class="form-group">
          <label>Discount % on Purchases</label>
          <input type="number" name="discount_pct" id="tier_discount" value="0" min="0" max="100" step="0.01">
        </div>
        <div class="form-group">
          <label>Color Theme</label>
          <select name="color" id="tier_color">
            <option value="blue">Blue</option>
            <option value="gold">Gold</option>
            <option value="silver">Silver</option>
            <option value="platinum">Platinum / Purple</option>
            <option value="green">Green</option>
            <option value="red">Red</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <label style="display:flex;align-items:center;gap:8px;font-size:14px;text-transform:none;letter-spacing:0">
            <input type="checkbox" name="is_active" id="tier_active" value="1" checked style="width:auto;padding:0"> Active
          </label>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" class="btn btn-outline" onclick="closeTierModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Tier</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTierModal(t) {
  var m = document.getElementById('tierModal');
  m.style.display = 'flex';
  if (t) {
    document.getElementById('tierModalTitle').textContent = 'Edit Membership Tier';
    document.getElementById('tier_id').value         = t.id;
    document.getElementById('tier_name').value       = t.name;
    document.getElementById('tier_min_points').value = t.min_points;
    document.getElementById('tier_discount').value   = t.discount_pct;
    document.getElementById('tier_color').value      = t.color;
    document.getElementById('tier_active').checked   = t.is_active == 1;
  } else {
    document.getElementById('tierModalTitle').textContent = 'New Membership Tier';
    document.getElementById('tier_id').value         = 0;
    document.getElementById('tier_name').value       = '';
    document.getElementById('tier_min_points').value = 0;
    document.getElementById('tier_discount').value   = 0;
    document.getElementById('tier_color').value      = 'blue';
    document.getElementById('tier_active').checked   = true;
  }
}
function closeTierModal() { document.getElementById('tierModal').style.display='none'; }
document.getElementById('tierModal').addEventListener('click', function(e){ if(e.target===this) closeTierModal(); });
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
