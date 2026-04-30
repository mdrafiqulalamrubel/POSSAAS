<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Purchases';
$tid = tid(); $bid = brid();

$cs = db()->prepare('SELECT currency FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur = $cs['currency'] ?? CURRENCY;

$search = trim($_GET['q']     ?? '');
$status = $_GET['status']     ?? '';
$from   = $_GET['from']       ?? date('Y-m-01');
$to     = $_GET['to']         ?? date('Y-m-d');
$pg     = max(1,(int)($_GET['page']??1));
$per    = 20;

$where  = 'p.tenant_id=? AND p.branch_id=? AND p.date BETWEEN ? AND ?';
$params = [$tid,$bid,$from,$to];
if ($status) { $where .= ' AND p.status=?'; $params[] = $status; }
if ($search) { $where .= ' AND (p.ref_no LIKE ? OR p.supplier_name LIKE ?)';
               $params[] = "%$search%"; $params[] = "%$search%"; }

$cnt = db()->prepare("SELECT COUNT(*) FROM purchases p WHERE $where");
$cnt->execute($params); $total_rows = (int)$cnt->fetchColumn();
$pag = paginate($total_rows, $per, $pg);

$stmt = db()->prepare("SELECT * FROM purchases p WHERE $where ORDER BY p.date DESC, p.id DESC
  LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$stmt->execute($params); $rows = $stmt->fetchAll();

$sum = db()->prepare("SELECT COALESCE(SUM(total),0) tot, COALESCE(SUM(paid),0) paid
  FROM purchases p WHERE $where AND p.status != 'cancelled'");
$sum->execute($params); $totals = $sum->fetch();

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div class="stats-grid" style="margin:0;gap:12px">
    <div class="stat-card" style="padding:12px 20px">
      <div class="stat-label">Total Purchased</div>
      <div class="stat-value expense"><?= $cur.number_format($totals['tot'],2) ?></div>
    </div>
    <div class="stat-card" style="padding:12px 20px">
      <div class="stat-label">Amount Paid</div>
      <div class="stat-value income"><?= $cur.number_format($totals['paid'],2) ?></div>
    </div>
    <div class="stat-card" style="padding:12px 20px">
      <div class="stat-label">Balance Due</div>
      <div class="stat-value" style="color:var(--c-warn)"><?= $cur.number_format($totals['tot']-$totals['paid'],2) ?></div>
    </div>
  </div>
  <a href="<?= APP_URL ?>/purchase-add.php" class="btn btn-primary">+ New Purchase</a>
</div>

<div class="card" style="padding:14px;margin-bottom:14px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="form-group" style="margin:0"><label>Search</label><input type="text" name="q" value="<?= h($search) ?>" placeholder="Ref / Supplier…"></div>
    <div class="form-group" style="margin:0"><label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['draft','received','partial','paid','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary">Filter</button>
    <a href="<?= APP_URL ?>/purchases.php" class="btn btn-outline">Clear</a>
  </form>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Ref No</th><th>Date</th><th>Supplier</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $badge = match($r['status']) {
          'paid'      => 'badge-paid',
          'received'  => 'badge-partial',
          'partial'   => 'badge-partial',
          'cancelled' => 'badge-cancelled',
          default     => 'badge-unpaid'
        };
      ?>
        <tr>
          <td><strong><?= h($r['ref_no']) ?></strong></td>
          <td><?= fmt_date($r['date']) ?></td>
          <td><?= h($r['supplier_name'] ?: '—') ?></td>
          <td style="text-align:right"><?= $cur.number_format($r['total'],2) ?></td>
          <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['paid'],2) ?></td>
          <td style="text-align:right;color:var(--c-warn)"><?= $cur.number_format($r['balance'],2) ?></td>
          <td><span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span></td>
          <td>
            <a href="<?= APP_URL ?>/purchase-view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View</a>
            <a href="<?= APP_URL ?>/purchase-add.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:30px">No purchases found. <a href="<?= APP_URL ?>/purchase-add.php">Add first purchase →</a></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['pages']>1): ?>
  <div style="padding:14px" class="pagination">
    <?php for($i=1;$i<=$pag['pages'];$i++): ?>
      <?php if($i==$pag['page']): ?><span class="current"><?=$i?></span>
      <?php else: ?><a href="?page=<?=$i?>&from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&q=<?=urlencode($search)?>&status=<?=urlencode($status)?>"><?=$i?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
