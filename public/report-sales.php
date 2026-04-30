<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Sales Report';
$tid = tid();

$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur          = !empty($cs['currency'])     ? $cs['currency']     : CURRENCY;
$company_name = !empty($cs['company_name']) ? $cs['company_name'] : $user['tenant_name'];
$company_addr = trim(implode(', ', array_filter([$cs['address']??'', $cs['city']??'', $cs['country']??''])));
$company_phone= $cs['phone'] ?? '';
$logo_url     = !empty($cs['logo_path']) ? APP_URL.'/'.$cs['logo_path'] : '';

$from   = $_GET['from']      ?? date('Y-m-01');
$to     = $_GET['to']        ?? date('Y-m-d');
$br_id  = (int)($_GET['branch_id'] ?? 0);
$group  = $_GET['group']     ?? 'month';
$status = $_GET['status']    ?? '';
$pay_filter = $_GET['pay_method'] ?? '';
$branches = get_branches();
if ($user['branch_id']) $br_id = (int)$user['branch_id'];

// Build WHERE
$bw = $br_id ? 'AND i.branch_id=?' : '';
$bp = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];
$sw = $status ? "AND i.status='$status'" : "AND i.status!='cancelled'";
$pw = $pay_filter ? "AND COALESCE(i.payment_method,'cash')='$pay_filter'" : '';

// ── Summary totals ────────────────────────────────────────────
$s = db()->prepare("SELECT
    COALESCE(SUM(total),0)              gross,
    COALESCE(SUM(paid),0)               collected,
    COALESCE(SUM(total)-SUM(paid),0)    outstanding,
    COALESCE(SUM(discount),0)           discount,
    COALESCE(SUM(tax_amount),0)         tax,
    COUNT(*)                            cnt
  FROM income i
  WHERE i.tenant_id=? $bw AND i.date BETWEEN ? AND ? $sw $pw");
$s->execute($bp); $sum = $s->fetch();

// ── Payment method breakdown ──────────────────────────────────
$pm_labels = [
    'cash'  => '💵 Cash',   'card'   => '💳 Card',
    'bkash' => '💚 bKash',  'nagad'  => '📲 Nagad',
    'upay'  => '📱 Upay',   'vangri' => '🪙 Vangri',
    'bank'  => '🏦 Bank',   'other'  => '💬 Other',
];
$s = db()->prepare("SELECT
    COALESCE(payment_method,'cash') pm,
    COUNT(*)                        cnt,
    COALESCE(SUM(total),0)          gross,
    COALESCE(SUM(paid),0)           collected
  FROM income i
  WHERE i.tenant_id=? $bw AND i.date BETWEEN ? AND ? $sw
  GROUP BY pm ORDER BY gross DESC");
$s->execute($bp); $pay_breakdown = $s->fetchAll();

// ── Period series ─────────────────────────────────────────────
$grp_fmt = match($group) {
    'week'  => "YEARWEEK(date,1)",
    'day'   => "DATE(date)",
    default => "DATE_FORMAT(date,'%Y-%m')",
};
$grp_lbl = match($group) {
    'week'  => "CONCAT(YEAR(date),'-W',LPAD(WEEK(date,1),2,'0'))",
    'day'   => "DATE_FORMAT(date,'%d %b %Y')",
    default => "DATE_FORMAT(date,'%b %Y')",
};
$s = db()->prepare("SELECT $grp_lbl period, $grp_fmt grp_key,
    COUNT(*) cnt,
    COALESCE(SUM(total),0)    gross,
    COALESCE(SUM(paid),0)     collected,
    COALESCE(SUM(discount),0) discount,
    COALESCE(SUM(tax_amount),0) tax,
    COALESCE(SUM(total)-SUM(paid),0) outstanding
  FROM income i
  WHERE i.tenant_id=? $bw AND i.date BETWEEN ? AND ? $sw $pw
  GROUP BY grp_key,period ORDER BY grp_key");
$s->execute($bp); $series = $s->fetchAll();

// ── Invoice list (for detailed view) ─────────────────────────
$s = db()->prepare("SELECT i.invoice_no, i.date,
    COALESCE(i.customer_name, c.name, 'Walk-in') cust,
    i.total, i.paid, i.discount, i.balance,
    i.status, COALESCE(i.payment_method,'cash') pm
  FROM income i
  LEFT JOIN customers c ON c.id=i.customer_id
  WHERE i.tenant_id=? $bw AND i.date BETWEEN ? AND ? $sw $pw
  ORDER BY i.date DESC, i.id DESC
  LIMIT 200");
$s->execute($bp); $invoices = $s->fetchAll();

// ── Top items sold ────────────────────────────────────────────
$bw2 = $br_id ? 'AND i.branch_id=?' : '';
$bp2 = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];
$s = db()->prepare("SELECT ii.description, SUM(ii.qty) total_qty, SUM(ii.total) total_rev
  FROM income_items ii JOIN income i ON i.id=ii.income_id
  WHERE i.tenant_id=? $bw2 AND i.date BETWEEN ? AND ? AND i.status!='cancelled'
  GROUP BY ii.description ORDER BY total_rev DESC LIMIT 15");
$s->execute($bp2); $top_items = $s->fetchAll();

// ── Top customers ─────────────────────────────────────────────
$s = db()->prepare("SELECT COALESCE(customer_name,'Walk-in') cust,
    COUNT(*) cnt, SUM(total) gross, SUM(paid) collected
  FROM income i WHERE i.tenant_id=? $bw AND i.date BETWEEN ? AND ? $sw
  GROUP BY customer_name ORDER BY gross DESC LIMIT 10");
$s->execute($bp); $top_custs = $s->fetchAll();

ob_start();
?>
<style>
@media print {
  .no-print,.top-bar,.sidebar,.sidebar-toggle-tab,.content > .card:first-child { display:none!important }
  .main-wrap { margin:0!important }
  .content   { padding:0!important }
  body       { background:#fff }
  .card      { box-shadow:none!important; border:1px solid #ddd!important; break-inside:avoid }
  .print-header { display:block!important }
  table      { font-size:11px }
  th,td      { padding:5px 7px!important }
  .stat-card { border:1px solid #ccc!important; padding:10px!important }
  .stats-grid{ grid-template-columns:repeat(4,1fr)!important }
}
.print-header {
  display:none;
  border-bottom:2px solid #1a56db;
  padding-bottom:14px;
  margin-bottom:20px;
}
.print-header h2 { font-size:20px; font-weight:800; color:#1a56db }
.print-header p  { font-size:12px; color:#6b7280; margin-top:2px }
.pm-badge {
  display:inline-flex; align-items:center; gap:4px;
  padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600;
}
.pm-cash   { background:#d1fae5; color:#065f46 }
.pm-card   { background:#dbeafe; color:#1e40af }
.pm-bkash  { background:#fce7f3; color:#9d174d }
.pm-nagad  { background:#ffedd5; color:#9a3412 }
.pm-upay   { background:#ede9fe; color:#5b21b6 }
.pm-vangri { background:#cffafe; color:#155e75 }
.pm-bank   { background:#f3f4f6; color:#374151 }
.pm-other  { background:#f3f4f6; color:#374151 }
</style>

<!-- Print header (hidden on screen, shown when printing) -->
<div class="print-header">
  <div style="display:flex;justify-content:space-between;align-items:flex-start">
    <div>
      <?php if ($logo_url): ?>
        <img src="<?= h($logo_url) ?>" style="height:48px;margin-bottom:8px;display:block">
      <?php endif; ?>
      <h2><?= h($company_name) ?></h2>
      <?php if ($company_addr): ?><p><?= h($company_addr) ?></p><?php endif; ?>
      <?php if ($company_phone): ?><p>📞 <?= h($company_phone) ?></p><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:18px;font-weight:800">SALES REPORT</div>
      <p>Period: <?= h(date('d M Y', strtotime($from))) ?> — <?= h(date('d M Y', strtotime($to))) ?></p>
      <p>Generated: <?= date('d M Y H:i') ?></p>
      <?php if ($br_id): ?>
        <p>Branch: <?php foreach($branches as $b) if($b['id']==$br_id) echo h($b['name']); ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Filter bar -->
<div class="card no-print" style="margin-bottom:16px;padding:14px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <?php if (!$user['branch_id']): ?>
    <div class="form-group" style="margin:0"><label>Branch</label>
      <select name="branch_id"><option value="0">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?=$b['id']?>" <?=$br_id==$b['id']?'selected':''?>><?=h($b['name'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin:0"><label>Status</label>
      <select name="status">
        <option value="">All (excl. cancelled)</option>
        <?php foreach(['draft','unpaid','partial','paid'] as $s): ?>
          <option value="<?=$s?>" <?=$status==$s?'selected':''?>><?=ucfirst($s)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0"><label>Payment Method</label>
      <select name="pay_method">
        <option value="">All Methods</option>
        <?php foreach (array_keys($pm_labels) as $k): ?>
          <option value="<?=$k?>" <?=$pay_filter===$k?'selected':''?>><?=$pm_labels[$k]?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0"><label>Group by</label>
      <select name="group">
        <option value="month" <?=$group==='month'?'selected':''?>>Month</option>
        <option value="week"  <?=$group==='week'?'selected':''?>>Week</option>
        <option value="day"   <?=$group==='day'?'selected':''?>>Day</option>
      </select>
    </div>
    <button class="btn btn-primary">Generate</button>
    <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print</button>
  </form>
</div>

<!-- KPI Summary -->
<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-label">Gross Sales</div>
    <div class="stat-value income"><?= $cur.number_format($sum['gross'],2) ?></div>
    <div style="font-size:11px;color:var(--c-muted);margin-top:3px"><?= number_format($sum['cnt']) ?> invoices</div>
  </div>
  <div class="stat-card green">
    <div class="stat-label">Collected</div>
    <div class="stat-value income"><?= $cur.number_format($sum['collected'],2) ?></div>
  </div>
  <div class="stat-card orange">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value" style="color:var(--c-warn)"><?= $cur.number_format($sum['outstanding'],2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Discounts</div>
    <div class="stat-value" style="color:var(--c-muted)"><?= $cur.number_format($sum['discount'],2) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Tax Collected</div>
    <div class="stat-value"><?= $cur.number_format($sum['tax'],2) ?></div>
  </div>
</div>

<!-- Payment Method Breakdown -->
<?php if ($pay_breakdown): ?>
<div class="card">
  <div class="card-title">💳 Sales by Payment Method</div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Payment Method</th>
        <th style="text-align:right">Invoices</th>
        <th style="text-align:right">Gross Sales</th>
        <th style="text-align:right">Collected</th>
        <th style="text-align:right">Share %</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $total_gross = (float)$sum['gross'];
    foreach ($pay_breakdown as $r):
        $pct = $total_gross > 0 ? round($r['gross'] / $total_gross * 100, 1) : 0;
        $pmk = $r['pm'];
        $pm_display = $pm_labels[$pmk] ?? ucfirst($pmk);
    ?>
      <tr>
        <td>
          <span class="pm-badge pm-<?= h($pmk) ?>"><?= h($pm_display) ?></span>
        </td>
        <td style="text-align:right"><?= number_format($r['cnt']) ?></td>
        <td style="text-align:right;font-weight:600"><?= $cur.number_format($r['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['collected'],2) ?></td>
        <td style="text-align:right">
          <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
            <div style="width:80px;height:7px;background:var(--c-border);border-radius:4px;overflow:hidden">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--c-primary);border-radius:4px"></div>
            </div>
            <span style="font-size:12px;font-weight:600"><?= $pct ?>%</span>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:700;background:var(--c-bg)">
        <td>TOTAL</td>
        <td style="text-align:right"><?= number_format($sum['cnt']) ?></td>
        <td style="text-align:right"><?= $cur.number_format($sum['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($sum['collected'],2) ?></td>
        <td style="text-align:right">100%</td>
      </tr>
    </tfoot>
  </table></div>
</div>
<?php endif; ?>

<!-- Period Breakdown -->
<div class="card">
  <div class="card-title">📅 Sales by Period</div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>Period</th>
        <th style="text-align:right">Invoices</th>
        <th style="text-align:right">Gross Sales</th>
        <th style="text-align:right">Collected</th>
        <th style="text-align:right">Outstanding</th>
        <th style="text-align:right">Discount</th>
        <th style="text-align:right">Tax</th>
      </tr>
    </thead>
    <tbody>
    <?php $tg=0;$tc=0;$td=0;$tt=0;$tic=0;$to2=0;
    foreach ($series as $r):
        $tg+=$r['gross'];$tc+=$r['collected'];$td+=$r['discount'];
        $tt+=$r['tax'];$tic+=$r['cnt'];$to2+=$r['outstanding'];
    ?>
      <tr>
        <td style="font-weight:600"><?= h($r['period']) ?></td>
        <td style="text-align:right"><?= number_format($r['cnt']) ?></td>
        <td style="text-align:right;font-weight:600"><?= $cur.number_format($r['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['collected'],2) ?></td>
        <td style="text-align:right;color:var(--c-warn)"><?= $cur.number_format($r['outstanding'],2) ?></td>
        <td style="text-align:right;color:var(--c-muted)"><?= $cur.number_format($r['discount'],2) ?></td>
        <td style="text-align:right"><?= $cur.number_format($r['tax'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$series): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--c-muted);padding:20px">No data for selected period</td></tr>
    <?php endif; ?>
    </tbody>
    <?php if ($series): ?>
    <tfoot>
      <tr style="font-weight:700;background:var(--c-bg)">
        <td>TOTAL</td>
        <td style="text-align:right"><?= number_format($tic) ?></td>
        <td style="text-align:right"><?= $cur.number_format($tg,2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($tc,2) ?></td>
        <td style="text-align:right;color:var(--c-warn)"><?= $cur.number_format($to2,2) ?></td>
        <td style="text-align:right"><?= $cur.number_format($td,2) ?></td>
        <td style="text-align:right"><?= $cur.number_format($tt,2) ?></td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table></div>
</div>

<!-- Invoice List -->
<?php if ($invoices): ?>
<div class="card">
  <div class="card-title">🧾 Invoice Details (<?= count($invoices) ?><?= count($invoices)>=200?' — showing first 200':'' ?>)</div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>#</th>
        <th>Invoice No</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Payment</th>
        <th style="text-align:right">Total</th>
        <th style="text-align:right">Paid</th>
        <th style="text-align:right">Balance</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($invoices as $i => $r): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= $i+1 ?></td>
        <td>
          <a href="<?= APP_URL ?>/invoice-view.php?id=" style="color:var(--c-primary);font-weight:600">
            <?= h($r['invoice_no']) ?>
          </a>
        </td>
        <td><?= date('d M Y', strtotime($r['date'])) ?></td>
        <td><?= h(mb_substr($r['cust'],0,20)) ?></td>
        <td><span class="pm-badge pm-<?= h($r['pm']) ?>"><?= h($pm_labels[$r['pm']] ?? ucfirst($r['pm'])) ?></span></td>
        <td style="text-align:right;font-weight:600"><?= $cur.number_format($r['total'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['paid'],2) ?></td>
        <td style="text-align:right;color:<?= $r['balance']>0?'var(--c-warn)':'var(--c-income)' ?>;font-weight:<?= $r['balance']>0?'700':'400' ?>">
          <?= $cur.number_format($r['balance'],2) ?>
        </td>
        <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Top Selling Items -->
<?php if ($top_items): ?>
<div class="card">
  <div class="card-title">📦 Top Selling Items</div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>#</th><th>Item</th>
        <th style="text-align:right">Qty Sold</th>
        <th style="text-align:right">Revenue</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($top_items as $i => $r): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= $i+1 ?></td>
        <td><?= h($r['description']) ?></td>
        <td style="text-align:right"><?= number_format($r['total_qty']+0, 2) ?></td>
        <td style="text-align:right;color:var(--c-income);font-weight:600"><?= $cur.number_format($r['total_rev'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<!-- Top Customers -->
<?php if ($top_custs): ?>
<div class="card">
  <div class="card-title">👥 Top Customers</div>
  <div class="table-wrap"><table>
    <thead>
      <tr>
        <th>#</th><th>Customer</th>
        <th style="text-align:right">Invoices</th>
        <th style="text-align:right">Gross</th>
        <th style="text-align:right">Collected</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($top_custs as $i => $r): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= $i+1 ?></td>
        <td><?= h($r['cust']) ?></td>
        <td style="text-align:right"><?= number_format($r['cnt']) ?></td>
        <td style="text-align:right"><?= $cur.number_format($r['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income);font-weight:600"><?= $cur.number_format($r['collected'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
