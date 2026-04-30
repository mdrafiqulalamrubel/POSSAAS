<?php
/**
 * report-sales.php
 * Sales Report with Payment Method column (Cash, bKash, Nagad, Card, UPay).
 *
 * Requires income.payment_method column:
 * ALTER TABLE income ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cash' AFTER paid;
 */
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Sales Report';
$tid = tid(); $bid = brid();

// ── Filters ───────────────────────────────────────────────
$from      = $_GET['from']    ?? date('Y-m-01');
$to        = $_GET['to']      ?? date('Y-m-d');
$status    = $_GET['status']  ?? '';
$cust_id   = (int)($_GET['customer_id'] ?? 0);
$pay_method= trim($_GET['pay_method'] ?? '');
$search    = trim($_GET['q'] ?? '');

$per_page  = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page-1)*$per_page;

// ── Build WHERE ───────────────────────────────────────────
$where  = ['i.tenant_id=?','i.branch_id=?'];
$params = [$tid, $bid];

if ($from && $to)       { $where[] = 'i.date BETWEEN ? AND ?'; $params[] = $from; $params[] = $to; }
if ($status)            { $where[] = 'i.status=?';             $params[] = $status; }
if ($cust_id > 0)       { $where[] = 'i.customer_id=?';        $params[] = $cust_id; }
if ($pay_method !== '')  { $where[] = 'i.payment_method=?';     $params[] = $pay_method; }
if ($search !== '') {
    $where[]  = '(i.invoice_no LIKE ? OR c.name LIKE ?)';
    $like = '%'.$search.'%';
    $params[] = $like; $params[] = $like;
}

$whereStr = implode(' AND ', $where);
$joinStr  = 'FROM income i LEFT JOIN customers c ON c.id=i.customer_id';

// ── Summary stats ─────────────────────────────────────────
$sum_stmt = db()->prepare(
    "SELECT
       COUNT(*) as cnt,
       COALESCE(SUM(i.total),0) as total_sales,
       COALESCE(SUM(i.paid),0)  as total_paid,
       COALESCE(SUM(i.total)-SUM(i.paid),0) as total_due,
       COALESCE(SUM(CASE WHEN i.payment_method='cash'  THEN i.paid ELSE 0 END),0) as cash_total,
       COALESCE(SUM(CASE WHEN i.payment_method='bkash' THEN i.paid ELSE 0 END),0) as bkash_total,
       COALESCE(SUM(CASE WHEN i.payment_method='nagad' THEN i.paid ELSE 0 END),0) as nagad_total,
       COALESCE(SUM(CASE WHEN i.payment_method='card'  THEN i.paid ELSE 0 END),0) as card_total,
       COALESCE(SUM(CASE WHEN i.payment_method='upay'  THEN i.paid ELSE 0 END),0) as upay_total
     $joinStr WHERE $whereStr"
);
$sum_stmt->execute($params);
$summary = $sum_stmt->fetch();

// ── Total rows for pagination ─────────────────────────────
$cnt_stmt = db()->prepare("SELECT COUNT(*) $joinStr WHERE $whereStr");
$cnt_stmt->execute($params);
$total_rows  = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page-1)*$per_page;

// ── Fetch rows ────────────────────────────────────────────
$rows_stmt = db()->prepare(
    "SELECT i.*, c.name cust_name, c.phone cust_phone
     $joinStr WHERE $whereStr
     ORDER BY i.date DESC, i.id DESC
     LIMIT $per_page OFFSET $offset"
);
$rows_stmt->execute($params);
$rows = $rows_stmt->fetchAll();

// ── Customers list for filter ─────────────────────────────
$custs_stmt = db()->prepare('SELECT id, name FROM customers WHERE tenant_id=? ORDER BY name LIMIT 500');
$custs_stmt->execute([$tid]);
$all_custs = $custs_stmt->fetchAll();

// ── Payment method display helper ────────────────────────
function pay_badge(string $method): string {
    $map = [
        'cash'  => ['💵 Cash',  'pay-cash'],
        'card'  => ['💳 Card',  'pay-card'],
        'bkash' => ['💚 bKash', 'pay-bkash'],
        'nagad' => ['📲 Nagad', 'pay-nagad'],
        'upay'  => ['🪙 UPay',  'pay-upay'],
    ];
    $m = strtolower(trim($method));
    $info = $map[$m] ?? ['❓ ' . ucfirst($m ?: 'N/A'), 'pay-other'];
    return '<span class="pay-badge ' . $info[1] . '">' . $info[0] . '</span>';
}

$currency = !empty($_SESSION['currency'] ?? '') ? $_SESSION['currency'] : '৳';

// ── CSV Export ────────────────────────────────────────────
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    $exp_stmt = db()->prepare(
        "SELECT i.*, c.name cust_name, c.phone cust_phone $joinStr WHERE $whereStr ORDER BY i.date DESC, i.id DESC"
    );
    $exp_stmt->execute($params);
    $exp_rows = $exp_stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Invoice No','Date','Customer','Phone','Total','Paid','Due','Status','Payment Method']);
    foreach ($exp_rows as $r) {
        fputcsv($out, [
            $r['invoice_no'] ?? '', $r['date'] ?? '',
            $r['cust_name'] ?? 'Walk-in', $r['cust_phone'] ?? '',
            $r['total'] ?? 0, $r['paid'] ?? 0,
            ($r['total']-$r['paid']),
            ucfirst($r['status'] ?? ''),
            ucfirst($r['payment_method'] ?? 'cash')
        ]);
    }
    fclose($out); exit;
}

ob_start();
?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-size:18px;font-weight:800;margin:0">📈 Sales Report</h2>
    <div style="font-size:13px;color:#6b7280;margin-top:2px"><?= date('d M Y', strtotime($from)) ?> — <?= date('d M Y', strtotime($to)) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv','page'=>1])) ?>" class="btn btn-outline btn-sm no-print">⬇ CSV</a>
    <button class="btn btn-outline btn-sm no-print" onclick="window.print()">🖨 Print</button>
  </div>
</div>

<!-- Summary cards -->
<div class="report-summary" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
  <div class="rs-card">
    <div class="rs-label">Invoices</div>
    <div class="rs-value" style="color:var(--c-primary)"><?= number_format($summary['cnt']) ?></div>
  </div>
  <div class="rs-card">
    <div class="rs-label">Total Sales</div>
    <div class="rs-value"><?= $currency ?><?= number_format($summary['total_sales'],2) ?></div>
  </div>
  <div class="rs-card">
    <div class="rs-label">Collected</div>
    <div class="rs-value" style="color:var(--c-income)"><?= $currency ?><?= number_format($summary['total_paid'],2) ?></div>
  </div>
  <div class="rs-card">
    <div class="rs-label">Due Amount</div>
    <div class="rs-value" style="color:var(--c-expense)"><?= $currency ?><?= number_format($summary['total_due'],2) ?></div>
  </div>
</div>

<!-- Payment method breakdown -->
<div class="card no-print" style="padding:14px 18px;margin-bottom:16px">
  <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#374151">💳 Payment Method Breakdown</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php
    $pm_breakdown = [
      'cash'  => ['💵 Cash',  $summary['cash_total'],  'pay-cash'],
      'bkash' => ['💚 bKash', $summary['bkash_total'], 'pay-bkash'],
      'nagad' => ['📲 Nagad', $summary['nagad_total'], 'pay-nagad'],
      'card'  => ['💳 Card',  $summary['card_total'],  'pay-card'],
      'upay'  => ['🪙 UPay',  $summary['upay_total'],  'pay-upay'],
    ];
    foreach ($pm_breakdown as $key => [$label, $amt, $cls]):
      if ($amt > 0):
    ?>
    <div style="background:#f8f9fb;border:1.5px solid #dde1e9;border-radius:8px;padding:10px 16px;min-width:130px">
      <div style="font-size:12px;color:#6b7280;margin-bottom:2px"><?= $label ?></div>
      <div style="font-size:18px;font-weight:800"><?= $currency ?><?= number_format($amt,2) ?></div>
    </div>
    <?php endif; endforeach; ?>
  </div>
</div>

<!-- Filters -->
<form method="get" class="card no-print" style="padding:14px;margin-bottom:16px">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="min-width:120px"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="min-width:120px"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <div class="form-group" style="min-width:130px">
      <label>Status</label>
      <select name="status">
        <option value="">All Status</option>
        <?php foreach(['paid','unpaid','partial','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:140px">
      <label>Payment Method</label>
      <select name="pay_method">
        <option value="">All Methods</option>
        <?php foreach(['cash'=>'💵 Cash','card'=>'💳 Card','bkash'=>'💚 bKash','nagad'=>'📲 Nagad','upay'=>'🪙 UPay'] as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $pay_method===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:160px">
      <label>Customer</label>
      <select name="customer_id">
        <option value="">All Customers</option>
        <?php foreach ($all_custs as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $cust_id==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:170px">
      <label>Search Invoice / Customer</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Invoice # or name">
    </div>
    <div class="form-group" style="min-width:80px">
      <label>Per page</label>
      <select name="per_page">
        <?php foreach([25,50,100,200] as $pp): ?>
          <option value="<?= $pp ?>" <?= $per_page==$pp?'selected':'' ?>><?= $pp ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">🔍 Filter</button>
    <a href="report-sales.php" class="btn btn-outline">✕ Reset</a>
  </div>
</form>

<!-- Sales table -->
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Date</th>
          <th>Customer</th>
          <th style="text-align:right">Total</th>
          <th style="text-align:right">Paid</th>
          <th style="text-align:right">Due</th>
          <th>Payment Method</th>
          <th>Status</th>
          <th class="no-print">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="9" style="text-align:center;padding:40px;color:#9ca3af">No sales found for the selected filters.</td></tr>
        <?php else:
          $row_total = 0; $row_paid = 0; $row_due = 0;
          foreach ($rows as $r):
            $due = (float)$r['total'] - (float)$r['paid'];
            $row_total += (float)$r['total'];
            $row_paid  += (float)$r['paid'];
            $row_due   += $due;
        ?>
        <tr>
          <td style="font-weight:700;font-family:monospace;font-size:13px"><?= h($r['invoice_no'] ?? '') ?></td>
          <td style="font-size:13px;white-space:nowrap"><?= h($r['date'] ?? '') ?></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= h($r['cust_name'] ?? 'Walk-in Customer') ?></div>
            <?php if (!empty($r['cust_phone'])): ?><div style="font-size:11px;color:#6b7280"><?= h($r['cust_phone']) ?></div><?php endif; ?>
          </td>
          <td style="text-align:right;font-weight:700"><?= $currency ?><?= number_format((float)$r['total'],2) ?></td>
          <td style="text-align:right;color:var(--c-income);font-weight:700"><?= $currency ?><?= number_format((float)$r['paid'],2) ?></td>
          <td style="text-align:right;<?= $due>0?'color:var(--c-expense);font-weight:700':'' ?>"><?= $currency ?><?= number_format($due,2) ?></td>
          <td><?= pay_badge($r['payment_method'] ?? 'cash') ?></td>
          <td>
            <?php
            $sc = ['paid'=>'badge-paid','unpaid'=>'badge-unpaid','partial'=>'badge-partial','cancelled'=>'badge-cancelled'];
            $st = strtolower($r['status'] ?? '');
            echo '<span class="badge '.($sc[$st]??'badge-draft').'">'.ucfirst($st).'</span>';
            ?>
          </td>
          <td class="no-print">
            <a href="invoice-view.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">👁</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot>
        <tr>
          <td colspan="3" style="font-weight:800;font-size:13px">Page Total (<?= count($rows) ?> rows)</td>
          <td style="text-align:right;font-weight:800"><?= $currency ?><?= number_format($row_total,2) ?></td>
          <td style="text-align:right;font-weight:800;color:var(--c-income)"><?= $currency ?><?= number_format($row_paid,2) ?></td>
          <td style="text-align:right;font-weight:800;color:var(--c-expense)"><?= $currency ?><?= number_format($row_due,2) ?></td>
          <td colspan="3"></td>
        </tr>
        <tr style="background:#f0f7ff">
          <td colspan="3" style="font-weight:800;font-size:14px;color:#1a56db">GRAND TOTAL (<?= number_format($total_rows) ?> invoices)</td>
          <td style="text-align:right;font-weight:900;font-size:15px"><?= $currency ?><?= number_format((float)$summary['total_sales'],2) ?></td>
          <td style="text-align:right;font-weight:900;font-size:15px;color:var(--c-income)"><?= $currency ?><?= number_format((float)$summary['total_paid'],2) ?></td>
          <td style="text-align:right;font-weight:900;font-size:15px;color:var(--c-expense)"><?= $currency ?><?= number_format((float)$summary['total_due'],2) ?></td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination no-print">
  <?php
  $qs = array_merge($_GET, ['per_page'=>$per_page]);
  if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>1])) ?>">«</a>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$page-1])) ?>">‹</a>
  <?php endif;
  for ($i=max(1,$page-3); $i<=min($total_pages,$page+3); $i++): ?>
    <?php if ($i==$page): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="?<?= http_build_query(array_merge($qs,['page'=>$i])) ?>"><?= $i ?></a>
    <?php endif;
  endfor;
  if ($page < $total_pages): ?>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$page+1])) ?>">›</a>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$total_pages])) ?>">»</a>
  <?php endif; ?>
  <span style="color:#6b7280;font-size:13px;margin-left:6px">Page <?= $page ?>/<?= $total_pages ?> · <?= number_format($total_rows) ?> invoices</span>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
