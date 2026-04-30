<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Purchase Report';
$tid = tid();
$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$br_id  = (int)($_GET['branch_id'] ?? 0);
$group  = $_GET['group']  ?? 'month';
$branches = get_branches();
if ($user['branch_id']) $br_id = (int)$user['branch_id'];

$bw = $br_id ? 'AND p.branch_id=?' : '';
$bp = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];

// Summary
$s = db()->prepare("SELECT COALESCE(SUM(total),0) gross, COALESCE(SUM(paid),0) paid,
    COALESCE(SUM(total)-SUM(paid),0) balance, COUNT(*) cnt
  FROM purchases p WHERE p.tenant_id=? $bw AND p.date BETWEEN ? AND ? AND p.status!='cancelled'");
$s->execute($bp); $sum = $s->fetch();

// Period series
$grp_fmt = match($group) { 'week'=>"YEARWEEK(date,1)", 'day'=>"DATE(date)", default=>"DATE_FORMAT(date,'%Y-%m')" };
$grp_lbl = match($group) { 'week'=>"CONCAT(YEAR(date),'-W',LPAD(WEEK(date,1),2,'0'))", 'day'=>"DATE_FORMAT(date,'%d %b %Y')", default=>"DATE_FORMAT(date,'%b %Y')" };
$s = db()->prepare("SELECT $grp_lbl period, $grp_fmt grp_key,
    COUNT(*) cnt, COALESCE(SUM(total),0) gross, COALESCE(SUM(paid),0) paid
  FROM purchases p WHERE p.tenant_id=? $bw AND p.date BETWEEN ? AND ? AND p.status!='cancelled'
  GROUP BY grp_key,period ORDER BY grp_key");
$s->execute($bp); $series = $s->fetchAll();

// Top purchased items
$bw2 = $br_id ? 'AND p.branch_id=?' : '';
$bp2 = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];
$s = db()->prepare("SELECT pi.description, SUM(pi.qty) total_qty, SUM(pi.total) total_cost
  FROM purchase_items pi JOIN purchases p ON p.id=pi.purchase_id
  WHERE p.tenant_id=? $bw2 AND p.date BETWEEN ? AND ? AND p.status!='cancelled'
  GROUP BY pi.description ORDER BY total_cost DESC LIMIT 15");
$s->execute($bp2); $top_items = $s->fetchAll();

// By supplier
$s = db()->prepare("SELECT COALESCE(supplier_name,'Unknown') supp, COUNT(*) cnt,
    SUM(total) gross, SUM(paid) paid
  FROM purchases p WHERE p.tenant_id=? $bw AND p.date BETWEEN ? AND ? AND p.status!='cancelled'
  GROUP BY supplier_name ORDER BY gross DESC LIMIT 10");
$s->execute($bp); $top_supps = $s->fetchAll();

ob_start();
?>
<div class="card" style="margin-bottom:16px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <?php if (!$user['branch_id']): ?>
    <div class="form-group" style="margin:0"><label>Branch</label>
      <select name="branch_id"><option value="0">All</option>
        <?php foreach ($branches as $b): ?><option value="<?=$b['id']?>" <?=$br_id==$b['id']?'selected':''?>><?=h($b['name'])?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <div class="form-group" style="margin:0"><label>Group by</label>
      <select name="group">
        <option value="month" <?=$group=='month'?'selected':''?>>Month</option>
        <option value="week"  <?=$group=='week'?'selected':''?>>Week</option>
        <option value="day"   <?=$group=='day'?'selected':''?>>Day</option>
      </select></div>
    <button class="btn btn-primary">Generate</button>
    <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print</button>
  </form>
</div>

<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Total Purchased</div><div class="stat-value expense"><?= $cur.number_format($sum['gross'],2) ?></div><div style="font-size:11px;color:var(--c-muted)"><?= $sum['cnt'] ?> orders</div></div>
  <div class="stat-card"><div class="stat-label">Amount Paid</div><div class="stat-value income"><?= $cur.number_format($sum['paid'],2) ?></div></div>
  <div class="stat-card"><div class="stat-label">Balance Due</div><div class="stat-value" style="color:var(--c-warn)"><?= $cur.number_format($sum['balance'],2) ?></div></div>
</div>

<div class="card">
  <div class="card-title">Purchases by Period</div>
  <div class="table-wrap"><table>
    <thead><tr><th>Period</th><th style="text-align:right">Orders</th><th style="text-align:right">Total Cost</th><th style="text-align:right">Paid</th><th style="text-align:right">Balance</th></tr></thead>
    <tbody>
    <?php $tg=0;$tp=0;$tic=0; foreach ($series as $r): $tg+=$r['gross'];$tp+=$r['paid'];$tic+=$r['cnt']; ?>
      <tr>
        <td><?= h($r['period']) ?></td><td style="text-align:right"><?= $r['cnt'] ?></td>
        <td style="text-align:right;color:var(--c-expense)"><?= $cur.number_format($r['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['paid'],2) ?></td>
        <td style="text-align:right;color:var(--c-warn)"><?= $cur.number_format($r['gross']-$r['paid'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$series): ?><tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:20px">No data</td></tr><?php endif; ?>
    </tbody>
    <?php if ($series): ?>
    <tfoot><tr style="font-weight:700;background:var(--c-bg)">
      <td>TOTAL</td><td style="text-align:right"><?= $tic ?></td>
      <td style="text-align:right;color:var(--c-expense)"><?= $cur.number_format($tg,2) ?></td>
      <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($tp,2) ?></td>
      <td style="text-align:right;color:var(--c-warn)"><?= $cur.number_format($tg-$tp,2) ?></td>
    </tr></tfoot>
    <?php endif; ?>
  </table></div>
</div>

<?php if ($top_items): ?>
<div class="card">
  <div class="card-title">Most Purchased Items</div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Item</th><th style="text-align:right">Total Qty</th><th style="text-align:right">Total Cost</th></tr></thead>
    <tbody>
    <?php foreach ($top_items as $i => $r): ?>
      <tr><td style="color:var(--c-muted)"><?= $i+1 ?></td><td><?= h($r['description']) ?></td>
        <td style="text-align:right"><?= $r['total_qty']+0 ?></td>
        <td style="text-align:right;color:var(--c-expense);font-weight:600"><?= $cur.number_format($r['total_cost'],2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if ($top_supps): ?>
<div class="card">
  <div class="card-title">Top Suppliers</div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Supplier</th><th style="text-align:right">Orders</th><th style="text-align:right">Total</th><th style="text-align:right">Paid</th></tr></thead>
    <tbody>
    <?php foreach ($top_supps as $i => $r): ?>
      <tr><td style="color:var(--c-muted)"><?= $i+1 ?></td><td><?= h($r['supp']) ?></td>
        <td style="text-align:right"><?= $r['cnt'] ?></td>
        <td style="text-align:right;color:var(--c-expense);font-weight:600"><?= $cur.number_format($r['gross'],2) ?></td>
        <td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($r['paid'],2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/../templates/layout.php';
