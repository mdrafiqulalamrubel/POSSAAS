<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Inventory Report';
$tid = tid(); $bid = brid();

// Load company settings
$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([$tid]);
$cs = $cs_stmt->fetch() ?: [];
$company_name = !empty($cs['company_name']) ? $cs['company_name'] : $user['tenant_name'];
$logo_path    = $cs['logo_path'] ?? '';

// Filters
$filter  = $_GET['filter'] ?? 'all';   // all | low | out | reorder
$search  = trim($_GET['q'] ?? '');
$cat     = trim($_GET['cat'] ?? '');

// Base where
$where  = 'WHERE tenant_id=? AND branch_id=? AND is_active=1';
$params = [$tid, $bid];
if ($search) { $where .= ' AND (name LIKE ? OR sku LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat)    { $where .= ' AND category=?'; $params[] = $cat; }

// Summary counts
$sumRow = db()->prepare("SELECT
    COUNT(*) total_items,
    COALESCE(SUM(quantity * unit_price),0) stock_value,
    SUM(quantity <= 0) out_of_stock,
    SUM(reorder_level > 0 AND quantity <= reorder_level) low_stock,
    SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) expired
  FROM items $where");
$sumRow->execute($params);
$summary = $sumRow->fetch();

// Apply filter after summary
$filter_where = $where;
$filter_params = $params;
switch ($filter) {
    case 'low':    $filter_where .= ' AND reorder_level > 0 AND quantity <= reorder_level AND quantity > 0'; break;
    case 'out':    $filter_where .= ' AND quantity <= 0'; break;
    case 'reorder':$filter_where .= ' AND reorder_level > 0 AND quantity <= reorder_level'; break;
    case 'expired':$filter_where .= ' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()'; break;
}

$total_count = (int)db()->prepare("SELECT COUNT(*) FROM items $filter_where")->execute($filter_params) ? db()->prepare("SELECT COUNT(*) FROM items $filter_where")->execute($filter_params) : 0;
$cnt_stmt = db()->prepare("SELECT COUNT(*) FROM items $filter_where");
$cnt_stmt->execute($filter_params);
$total_count = (int)$cnt_stmt->fetchColumn();

$pg = paginate($total_count, 25, (int)($_GET['page'] ?? 1));

$stmt = db()->prepare("SELECT * FROM items $filter_where ORDER BY
    CASE WHEN reorder_level > 0 AND quantity <= reorder_level THEN 0 ELSE 1 END,
    quantity ASC, name ASC
    LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($filter_params);
$items = $stmt->fetchAll();

// Categories
$cats = db()->prepare('SELECT DISTINCT category FROM items WHERE tenant_id=? AND branch_id=? AND category IS NOT NULL AND category != "" ORDER BY category');
$cats->execute([$tid, $bid]);
$categories = $cats->fetchAll(PDO::FETCH_COLUMN);

ob_start();
?>
<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-label">Total Items</div>
    <div class="stat-value"><?= number_format($summary['total_items']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Stock Value</div>
    <div class="stat-value income"><?= money((float)$summary['stock_value']) ?></div>
  </div>
  <div class="stat-card" style="<?= $summary['low_stock'] > 0 ? 'border-left:3px solid var(--c-warn)' : '' ?>">
    <div class="stat-label">⚠ Low / At Reorder</div>
    <div class="stat-value" style="color:var(--c-warn)"><?= number_format($summary['low_stock']) ?></div>
  </div>
  <div class="stat-card" style="<?= $summary['out_of_stock'] > 0 ? 'border-left:3px solid var(--c-expense)' : '' ?>">
    <div class="stat-label">🚫 Out of Stock</div>
    <div class="stat-value expense"><?= number_format($summary['out_of_stock']) ?></div>
  </div>
  <?php if ($summary['expired'] > 0): ?>
  <div class="stat-card" style="border-left:3px solid var(--c-expense)">
    <div class="stat-label">☠ Expired Items</div>
    <div class="stat-value expense"><?= number_format($summary['expired']) ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card" style="padding:16px;margin-bottom:16px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0">
      <label>Search</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Name or SKU…">
    </div>
    <div class="form-group" style="margin:0">
      <label>Category</label>
      <select name="cat">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c) ?>" <?= $c==$cat?'selected':'' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0">
      <label>Status Filter</label>
      <select name="filter">
        <option value="all"    <?= $filter=='all'    ?'selected':'' ?>>All Items</option>
        <option value="reorder"<?= $filter=='reorder'?'selected':'' ?>>At/Below Reorder Level</option>
        <option value="low"    <?= $filter=='low'    ?'selected':'' ?>>Low Stock (not zero)</option>
        <option value="out"    <?= $filter=='out'    ?'selected':'' ?>>Out of Stock</option>
        <option value="expired"<?= $filter=='expired'?'selected':'' ?>>Expired</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <a href="inventory-report.php" class="btn btn-outline">Clear</a>
    <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print</button>
  </form>
</div>

<!-- Items Table -->
<div class="card" style="padding:0">
  <div style="padding:16px 20px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center">
    <strong>Inventory Items</strong>
    <span style="font-size:13px;color:var(--c-muted)"><?= number_format($total_count) ?> item(s)</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Item Name</th>
          <th>SKU</th>
          <th>Category</th>
          <th style="text-align:right">In Stock</th>
          <th style="text-align:right">Reorder Level</th>
          <th style="text-align:right">Unit Price</th>
          <th style="text-align:right">Stock Value</th>
          <th>Status</th>
          <th>Expiry</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it):
        $low    = $it['reorder_level'] > 0 && $it['quantity'] <= $it['reorder_level'];
        $out    = $it['quantity'] <= 0;
        $exp    = $it['expiry_date'];
        $today  = date('Y-m-d');
        $exp_ok = !$exp || $exp > $today;
        $row_style = '';
        if ($out)  $row_style = 'background:rgba(255,59,48,.04)';
        elseif ($low) $row_style = 'background:rgba(255,149,0,.04)';
      ?>
      <tr style="<?= $row_style ?>">
        <td>
          <strong><?= h($it['name']) ?></strong>
          <?php if ($it['notes']): ?><br><small style="color:var(--c-muted)"><?= h(substr($it['notes'],0,50)) ?></small><?php endif; ?>
        </td>
        <td style="font-family:monospace;font-size:12px"><?= h($it['sku'] ?: '—') ?></td>
        <td><?= h($it['category'] ?: '—') ?></td>
        <td style="text-align:right;font-weight:600;color:<?= $out?'var(--c-expense)':($low?'var(--c-warn)':'inherit') ?>">
          <?= rtrim(rtrim(number_format($it['quantity'], 3),'0'),'.') ?> <?= h($it['unit']) ?>
        </td>
        <td style="text-align:right;color:var(--c-muted)">
          <?= $it['reorder_level'] > 0 ? rtrim(rtrim(number_format($it['reorder_level'],3),'0'),'.') . ' ' . h($it['unit']) : '—' ?>
        </td>
        <td style="text-align:right"><?= money((float)$it['unit_price']) ?></td>
        <td style="text-align:right"><?= money((float)$it['quantity'] * (float)$it['unit_price']) ?></td>
        <td>
          <?php if ($out): ?>
            <span class="badge badge-unpaid">Out of Stock</span>
          <?php elseif ($low): ?>
            <span class="badge badge-partial">Low Stock</span>
          <?php else: ?>
            <span class="badge badge-paid">OK</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($exp): ?>
            <?php $diff = (strtotime($exp) - strtotime($today)) / 86400; ?>
            <span class="badge <?= $diff < 0 ? 'badge-unpaid' : ($diff <= 30 ? 'badge-partial' : '') ?>">
              <?= $diff < 0 ? '⚠ Expired ' : '' ?><?= fmt_date($exp) ?>
            </span>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--c-muted);padding:30px">No items match this filter.</td></tr>
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
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($cat) ?>&filter=<?= urlencode($filter) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
