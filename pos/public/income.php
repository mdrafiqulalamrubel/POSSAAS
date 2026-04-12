<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Invoices';
$tid = tid(); $bid = brid();

$search  = trim($_GET['q']   ?? '');
$status  = $_GET['status']   ?? '';
$from    = $_GET['from']     ?? date('Y-m-01');
$to      = $_GET['to']       ?? date('Y-m-d');
$pg      = max(1, (int)($_GET['page'] ?? 1));
$per     = 20;

$where = 'i.tenant_id=? AND i.branch_id=? AND i.date BETWEEN ? AND ?';
$params = [$tid, $bid, $from, $to];
if ($status) { $where .= ' AND i.status=?'; $params[] = $status; }
if ($search)  { $where .= ' AND (i.invoice_no LIKE ? OR i.customer_name LIKE ? OR c.name LIKE ?)';
                $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$cnt = db()->prepare("SELECT COUNT(*) FROM income i LEFT JOIN customers c ON c.id=i.customer_id WHERE $where");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pag = paginate($total, $per, $pg);

$stmt = db()->prepare("SELECT i.*, c.name cust_name FROM income i
  LEFT JOIN customers c ON c.id=i.customer_id
  WHERE $where ORDER BY i.date DESC, i.id DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$stmt->execute($params);
$rows = $stmt->fetchAll();

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="form-group" style="margin:0"><label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['draft','unpaid','partial','paid','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px"><label>Search</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Invoice # or customer">
    </div>
    <button class="btn btn-outline">Filter</button>
    <a href="<?= APP_URL ?>/income-add.php" class="btn btn-primary">+ New Invoice</a>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Date</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/invoice-view.php?id=<?= $r['id'] ?>"><?= h($r['invoice_no']) ?></a></td>
          <td><?= fmt_date($r['date']) ?></td>
          <td><?= h($r['cust_name'] ?: $r['customer_name'] ?: '—') ?></td>
          <td><?= money($r['total']) ?></td>
          <td style="color:var(--c-income)"><?= money($r['paid']) ?></td>
          <td style="color:<?= $r['balance']>0?'var(--c-expense)':'var(--c-income)' ?>"><?= money($r['balance']) ?></td>
          <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td style="white-space:nowrap">
            <a href="<?= APP_URL ?>/invoice-view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View</a>
            <a href="<?= APP_URL ?>/income-edit.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;color:var(--c-muted);padding:24px">No invoices found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pag['pages'] > 1): ?>
  <div class="pagination">
    <?php for ($i=1; $i<=$pag['pages']; $i++): ?>
      <?php $q = http_build_query(array_merge($_GET,['page'=>$i])); ?>
      <?php if ($i==$pag['page']): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?<?= $q ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
