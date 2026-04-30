<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Expenses Report';
$tid = tid();
$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

$from   = $_GET['from']   ?? date('Y-m-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$br_id  = (int)($_GET['branch_id'] ?? 0);
$group  = $_GET['group']  ?? 'month';
$cat_id = (int)($_GET['cat_id'] ?? 0);
$branches = get_branches();
if ($user['branch_id']) $br_id = (int)$user['branch_id'];

$bw = $br_id ? 'AND e.branch_id=?' : '';
$bp = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];
$cw = $cat_id ? 'AND e.category_id=?' : '';
$cp = $cat_id ? array_merge($bp,[$cat_id]) : $bp;

$cats = db()->prepare('SELECT * FROM expense_categories WHERE tenant_id=? ORDER BY name');
$cats->execute([$tid]); $categories = $cats->fetchAll();

// Summary
$s = db()->prepare("SELECT COALESCE(SUM(amount),0) total, COUNT(*) cnt
  FROM expenses e WHERE e.tenant_id=? $bw AND e.date BETWEEN ? AND ? $cw AND e.status!='cancelled'");
$s->execute($cp); $sum = $s->fetch();

// Period series
$grp_fmt = match($group) { 'week'=>"YEARWEEK(date,1)", 'day'=>"DATE(date)", default=>"DATE_FORMAT(date,'%Y-%m')" };
$grp_lbl = match($group) { 'week'=>"CONCAT(YEAR(date),'-W',LPAD(WEEK(date,1),2,'0'))", 'day'=>"DATE_FORMAT(date,'%d %b %Y')", default=>"DATE_FORMAT(date,'%b %Y')" };
$s = db()->prepare("SELECT $grp_lbl period, $grp_fmt grp_key, COUNT(*) cnt, COALESCE(SUM(amount),0) total
  FROM expenses e WHERE e.tenant_id=? $bw AND e.date BETWEEN ? AND ? $cw AND e.status!='cancelled'
  GROUP BY grp_key,period ORDER BY grp_key");
$s->execute($cp); $series = $s->fetchAll();

// By category
$s = db()->prepare("SELECT COALESCE(ec.name,'Uncategorized') cat, COALESCE(SUM(e.amount),0) total, COUNT(*) cnt
  FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
  WHERE e.tenant_id=? $bw AND e.date BETWEEN ? AND ? AND e.status!='cancelled'
  GROUP BY cat ORDER BY total DESC");
$s->execute($bp); $by_cat = $s->fetchAll();

// By supplier
$s = db()->prepare("SELECT COALESCE(supplier,'Unknown') supp, COUNT(*) cnt, SUM(amount) total
  FROM expenses e WHERE e.tenant_id=? $bw AND e.date BETWEEN ? AND ? $cw AND e.status!='cancelled'
  GROUP BY supplier ORDER BY total DESC LIMIT 10");
$s->execute($cp); $by_supp = $s->fetchAll();

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
    <div class="form-group" style="margin:0"><label>Category</label>
      <select name="cat_id"><option value="0">All</option>
        <?php foreach ($categories as $c): ?><option value="<?=$c['id']?>" <?=$cat_id==$c['id']?'selected':''?>><?=h($c['name'])?></option><?php endforeach; ?>
      </select></div>
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
  <div class="stat-card"><div class="stat-label">Total Expenses</div><div class="stat-value expense"><?= $cur.number_format($sum['total'],2) ?></div><div style="font-size:11px;color:var(--c-muted)"><?= $sum['cnt'] ?> records</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div class="card">
  <div class="card-title">Expenses by Period</div>
  <div class="table-wrap"><table>
    <thead><tr><th>Period</th><th style="text-align:right">Count</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
    <?php $tt=0;$tc=0; foreach ($series as $r): $tt+=$r['total'];$tc+=$r['cnt']; ?>
      <tr><td><?= h($r['period']) ?></td><td style="text-align:right"><?= $r['cnt'] ?></td>
        <td style="text-align:right;color:var(--c-expense);font-weight:600"><?= $cur.number_format($r['total'],2) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$series): ?><tr><td colspan="3" style="text-align:center;color:var(--c-muted);padding:20px">No data</td></tr><?php endif; ?>
    </tbody>
    <?php if ($series): ?>
    <tfoot><tr style="font-weight:700;background:var(--c-bg)"><td>TOTAL</td><td style="text-align:right"><?= $tc ?></td>
      <td style="text-align:right;color:var(--c-expense)"><?= $cur.number_format($tt,2) ?></td></tr></tfoot>
    <?php endif; ?>
  </table></div>
</div>

<div class="card">
  <div class="card-title">Expenses by Category</div>
  <div class="table-wrap"><table>
    <thead><tr><th>Category</th><th style="text-align:right">Count</th><th style="text-align:right">Amount</th><th>Share</th></tr></thead>
    <tbody>
    <?php $gt=(float)$sum['total']; foreach ($by_cat as $r): $pct=$gt>0?round($r['total']/$gt*100,1):0; ?>
      <tr><td><?= h($r['cat']) ?></td><td style="text-align:right"><?= $r['cnt'] ?></td>
        <td style="text-align:right;color:var(--c-expense);font-weight:600"><?= $cur.number_format($r['total'],2) ?></td>
        <td><div style="display:flex;align-items:center;gap:6px">
          <div style="width:80px;height:7px;background:var(--c-border);border-radius:4px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--c-expense);border-radius:4px"></div></div>
          <span style="font-size:11px;color:var(--c-muted)"><?= $pct ?>%</span></div></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
</div>

<?php if ($by_supp): ?>
<div class="card">
  <div class="card-title">Expenses by Supplier</div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>Supplier</th><th style="text-align:right">Count</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
    <?php foreach ($by_supp as $i => $r): ?>
      <tr><td style="color:var(--c-muted)"><?= $i+1 ?></td><td><?= h($r['supp']) ?></td>
        <td style="text-align:right"><?= $r['cnt'] ?></td>
        <td style="text-align:right;color:var(--c-expense);font-weight:600"><?= $cur.number_format($r['total'],2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/../templates/layout.php';
