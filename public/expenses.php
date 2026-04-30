<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Expenses';
$tid = tid(); $bid = brid();

$search = trim($_GET['q']   ?? '');
$cat    = (int)($_GET['cat'] ?? 0);
$status = $_GET['status']   ?? '';
$from   = $_GET['from']     ?? date('Y-m-01');
$to     = $_GET['to']       ?? date('Y-m-d');
$pg     = max(1,(int)($_GET['page']??1));
$per    = 20;

$where = 'e.tenant_id=? AND e.branch_id=? AND e.date BETWEEN ? AND ?';
$params = [$tid,$bid,$from,$to];
if ($cat)    { $where .= ' AND e.category_id=?'; $params[] = $cat; }
if ($status) { $where .= ' AND e.status=?';      $params[] = $status; }
if ($search) { $where .= ' AND (e.supplier LIKE ? OR e.description LIKE ?)';
               $params[] = "%$search%"; $params[] = "%$search%"; }

$cnt = db()->prepare("SELECT COUNT(*) FROM expenses e WHERE $where");
$cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pag = paginate($total,$per,$pg);

$stmt = db()->prepare("SELECT e.*, ec.name cat_name FROM expenses e
  LEFT JOIN expense_categories ec ON ec.id=e.category_id
  WHERE $where ORDER BY e.date DESC, e.id DESC
  LIMIT {$pag['per_page']} OFFSET {$pag['offset']}");
$stmt->execute($params); $rows = $stmt->fetchAll();

$cats = db()->prepare('SELECT * FROM expense_categories WHERE tenant_id=? ORDER BY name');
$cats->execute([$tid]); $categories = $cats->fetchAll();

// total for period
$ts = db()->prepare("SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE $where AND e.status!='cancelled'");
$ts->execute($params); $period_total = (float)$ts->fetchColumn();

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="form-group" style="margin:0"><label>Category</label>
      <select name="cat">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cat==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0"><label>Status</label>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['pending','approved','paid','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:160px"><label>Search</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Supplier / description">
    </div>
    <button class="btn btn-outline">Filter</button>
    <a href="<?= APP_URL ?>/expense-add.php" class="btn btn-primary">+ Add Expense</a>
  </form>
</div>

<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card">
    <div class="stat-label">Total Expenses (period)</div>
    <div class="stat-value expense"><?= money($period_total) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Records</div>
    <div class="stat-value"><?= number_format($total) ?></div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Category</th><th>Supplier</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= fmt_date($r['date']) ?></td>
          <td><?= h($r['cat_name'] ?? '—') ?></td>
          <td><?= h($r['supplier'] ?? '—') ?></td>
          <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['description'] ?? '') ?></td>
          <td style="color:var(--c-expense);font-weight:600"><?= money($r['amount']) ?></td>
          <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td><a href="<?= APP_URL ?>/expense-add.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center;color:var(--c-muted);padding:24px">No expenses found</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['pages']>1): ?>
  <div class="pagination">
    <?php for ($i=1;$i<=$pag['pages'];$i++): $q=http_build_query(array_merge($_GET,['page'=>$i])); ?>
      <?php if($i==$pag['page']): ?><span class="current"><?= $i ?></span>
      <?php else: ?><a href="?<?= $q ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
