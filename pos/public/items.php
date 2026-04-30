<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Items / Inventory';
$tid = tid(); $bid = brid();

// Filter
$search  = trim($_GET['q'] ?? '');
$cat     = trim($_GET['cat'] ?? '');
$expiry  = $_GET['expiry'] ?? '';

$where = 'WHERE i.tenant_id=? AND i.branch_id=? AND i.is_active=1';
$params = [$tid, $bid];

if ($search) { $where .= ' AND (i.name LIKE ? OR i.sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat)    { $where .= ' AND i.category=?'; $params[] = $cat; }
if ($expiry === 'expired')  { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE()'; }
if ($expiry === '7days')    { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; }
if ($expiry === '30days')   { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'; }

$total = db()->prepare("SELECT COUNT(*) FROM items i $where");
$total->execute($params); $total = (int)$total->fetchColumn();
$pg = paginate($total, 20, (int)($_GET['page'] ?? 1));

$stmt = db()->prepare("SELECT * FROM items i $where ORDER BY i.name ASC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Categories for filter
$cats = db()->prepare('SELECT DISTINCT category FROM items WHERE tenant_id=? AND branch_id=? AND category IS NOT NULL AND category != "" ORDER BY category');
$cats->execute([$tid, $bid]);
$categories = $cats->fetchAll(PDO::FETCH_COLUMN);

// Expiry alerts counts
$exp_soon = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
$exp_soon->execute([$tid, $bid]); $exp_soon = (int)$exp_soon->fetchColumn();

$exp_gone = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()');
$exp_gone->execute([$tid, $bid]); $exp_gone = (int)$exp_gone->fetchColumn();

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($exp_gone > 0): ?>
    <a href="?expiry=expired" class="alert alert-error" style="padding:8px 14px;border-radius:var(--radius);text-decoration:none;font-size:13px">
      ⚠ <?= $exp_gone ?> item(s) EXPIRED
    </a>
    <?php endif; ?>
    <?php if ($exp_soon > 0): ?>
    <a href="?expiry=30days" class="alert alert-warning" style="padding:8px 14px;border-radius:var(--radius);text-decoration:none;font-size:13px">
      ⏰ <?= $exp_soon ?> expiring within 30 days
    </a>
    <?php endif; ?>
  </div>
  <a href="<?= APP_URL ?>/item-add.php" class="btn btn-primary">+ Add Item</a>
</div>

<!-- Filters -->
<div class="card" style="padding:16px;margin-bottom:16px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="min-width:200px;margin:0">
      <label>Search</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Name or SKU…">
    </div>
    <div class="form-group" style="min-width:150px;margin:0">
      <label>Category</label>
      <select name="cat">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c) ?>" <?= $c==$cat?'selected':'' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:150px;margin:0">
      <label>Expiry</label>
      <select name="expiry">
        <option value="">All</option>
        <option value="7days"  <?= $expiry=='7days' ?'selected':'' ?>>Expiring in 7 days</option>
        <option value="30days" <?= $expiry=='30days'?'selected':'' ?>>Expiring in 30 days</option>
        <option value="expired"<?= $expiry=='expired'?'selected':'' ?>>Already expired</option>
      </select>
    </div>
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= APP_URL ?>/items.php" class="btn btn-outline">Clear</a>
  </form>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th><th>SKU</th><th>Category</th><th>Qty</th>
          <th>Unit Price</th><th>Expiry Date</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it):
        $today = date('Y-m-d');
        $exp   = $it['expiry_date'];
        $exp_class = '';
        $exp_label = $exp ? fmt_date($exp) : '—';
        if ($exp) {
          $diff = (strtotime($exp) - strtotime($today)) / 86400;
          if ($diff < 0)  { $exp_class = 'badge-unpaid'; $exp_label = '⚠ ' . fmt_date($exp) . ' (EXPIRED)'; }
          elseif ($diff <= 7)  { $exp_class = 'badge-unpaid'; $exp_label = '⚠ ' . fmt_date($exp); }
          elseif ($diff <= 30) { $exp_class = 'badge-partial'; $exp_label = '⏰ ' . fmt_date($exp); }
        }
        $low_stock = $it['reorder_level'] > 0 && $it['quantity'] <= $it['reorder_level'];
      ?>
        <tr>
          <td><strong><?= h($it['name']) ?></strong>
            <?php if ($it['notes']): ?><br><small style="color:var(--c-muted)"><?= h(substr($it['notes'],0,60)) ?><?= strlen($it['notes'])>60?'…':'' ?></small><?php endif; ?>
          </td>
          <td><?= h($it['sku'] ?: '—') ?></td>
          <td><?= h($it['category'] ?: '—') ?></td>
          <td>
            <span style="<?= $low_stock?'color:var(--c-expense);font-weight:600':'' ?>">
              <?= h($it['quantity'] + 0) ?> <?= h($it['unit']) ?>
            </span>
            <?php if ($low_stock): ?><br><small style="color:var(--c-expense)">Low stock</small><?php endif; ?>
          </td>
          <td><?= money((float)$it['unit_price']) ?></td>
          <td><?php if ($exp && $exp_class): ?><span class="badge <?= $exp_class ?>"><?= $exp_label ?></span><?php else: ?><?= $exp_label ?><?php endif; ?></td>
          <td><span class="badge badge-<?= $it['is_active']?'paid':'cancelled' ?>"><?= $it['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <a href="<?= APP_URL ?>/item-add.php?id=<?= $it['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <a href="<?= APP_URL ?>/item-delete.php?id=<?= $it['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this item?')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:30px">No items found. <a href="<?= APP_URL ?>/item-add.php">Add your first item →</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div style="padding:16px" class="pagination">
    <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <?php if ($i==$pg['page']): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($cat) ?>&expiry=<?= urlencode($expiry) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
