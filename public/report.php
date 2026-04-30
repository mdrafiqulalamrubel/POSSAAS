<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'General Report';
$tid = tid();

// Load company settings
$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([$tid]);
$cs = $cs_stmt->fetch() ?: [];
$company_name = !empty($cs['company_name']) ? $cs['company_name'] : $user['tenant_name'];
$logo_path    = $cs['logo_path'] ?? '';
$currency_sym = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

$from    = $_GET['from']      ?? date('Y-m-01');
$to      = $_GET['to']        ?? date('Y-m-d');
$br_id   = (int)($_GET['branch_id'] ?? 0);   // 0 = all accessible
$group   = $_GET['group']     ?? 'month';     // month | week | day

$branches = get_branches();
// scope: if user is locked to a branch, force it
if ($user['branch_id']) $br_id = (int)$user['branch_id'];

// Build branch filter
if ($br_id) {
    $br_where_inc = 'AND i.branch_id=?';
    $br_where_exp = 'AND e.branch_id=?';
    $br_params_inc = [$tid,$br_id,$from,$to];
    $br_params_exp = [$tid,$br_id,$from,$to];
} else {
    $br_where_inc = '';
    $br_where_exp = '';
    $br_params_inc = [$tid,$from,$to];
    $br_params_exp = [$tid,$from,$to];
}

// ── Summary totals ────────────────────────────────────────────
$s = db()->prepare("SELECT
    COALESCE(SUM(total),0) gross_income,
    COALESCE(SUM(paid),0)  collected,
    COALESCE(SUM(total)-SUM(paid),0) outstanding,
    COUNT(*) inv_count
  FROM income i WHERE i.tenant_id=? $br_where_inc AND i.date BETWEEN ? AND ? AND i.status!='cancelled'");
$s->execute($br_params_inc); $inc_sum = $s->fetch();

$s = db()->prepare("SELECT COALESCE(SUM(amount),0) total_exp, COUNT(*) exp_count
  FROM expenses e WHERE e.tenant_id=? $br_where_exp AND e.date BETWEEN ? AND ? AND e.status!='cancelled'");
$s->execute($br_params_exp); $exp_sum = $s->fetch();

// Purchases
$br_where_pur = $br_id ? 'AND p.branch_id=?' : '';
$br_params_pur = $br_id ? [$tid,$br_id,$from,$to] : [$tid,$from,$to];
$s = db()->prepare("SELECT COALESCE(SUM(total),0) total_pur, COUNT(*) pur_count
  FROM purchases p WHERE p.tenant_id=? $br_where_pur AND p.date BETWEEN ? AND ? AND p.status!='cancelled'");
$s->execute($br_params_pur); $pur_sum = $s->fetch();

$net = (float)$inc_sum['collected'] - (float)$exp_sum['total_exp'] - (float)$pur_sum['total_pur'];

// ── Time-series (grouped) ─────────────────────────────────────
$grp_fmt = match($group) {
    'week' => "YEARWEEK(date,1)",
    'day'  => "DATE(date)",
    default=> "DATE_FORMAT(date,'%Y-%m')",
};
$grp_lbl = match($group) {
    'week' => "CONCAT(YEAR(date),'-W',LPAD(WEEK(date,1),2,'0'))",
    'day'  => "DATE_FORMAT(date,'%d %b %Y')",
    default=> "DATE_FORMAT(date,'%b %Y')",
};

$s = db()->prepare("SELECT $grp_lbl period, $grp_fmt grp_key,
    COALESCE(SUM(total),0) income, COALESCE(SUM(paid),0) collected
  FROM income i WHERE i.tenant_id=? $br_where_inc AND i.date BETWEEN ? AND ? AND i.status!='cancelled'
  GROUP BY grp_key,period ORDER BY grp_key");
$s->execute($br_params_inc); $inc_series = $s->fetchAll();

$s = db()->prepare("SELECT $grp_lbl period, $grp_fmt grp_key,
    COALESCE(SUM(amount),0) expenses
  FROM expenses e WHERE e.tenant_id=? $br_where_exp AND e.date BETWEEN ? AND ? AND e.status!='cancelled'
  GROUP BY grp_key,period ORDER BY grp_key");
$s->execute($br_params_exp); $exp_series = $s->fetchAll();

// merge
$periods = [];
foreach ($inc_series as $r) $periods[$r['grp_key']] = ['period'=>$r['period'],'income'=>$r['income'],'collected'=>$r['collected'],'expenses'=>0];
foreach ($exp_series as $r) {
    if (!isset($periods[$r['grp_key']])) $periods[$r['grp_key']] = ['period'=>$r['period'],'income'=>0,'collected'=>0,'expenses'=>0];
    $periods[$r['grp_key']]['expenses'] = $r['expenses'];
}
ksort($periods);

// ── By branch (only shown if all branches) ──────────────────
$br_breakdown = [];
if (!$br_id) {
    $s = db()->prepare("SELECT b.name, COALESCE(SUM(i.paid),0) income, COALESCE(SUM(i.total),0) gross
      FROM income i JOIN branches b ON b.id=i.branch_id
      WHERE i.tenant_id=? AND i.date BETWEEN ? AND ? AND i.status!='cancelled'
      GROUP BY b.id,b.name ORDER BY income DESC");
    $s->execute([$tid,$from,$to]); $br_inc = $s->fetchAll();

    $s = db()->prepare("SELECT b.name, COALESCE(SUM(e.amount),0) expenses
      FROM expenses e JOIN branches b ON b.id=e.branch_id
      WHERE e.tenant_id=? AND e.date BETWEEN ? AND ? AND e.status!='cancelled'
      GROUP BY b.id,b.name ORDER BY expenses DESC");
    $s->execute([$tid,$from,$to]); $br_exp = $s->fetchAll();

    $bi = array_column($br_inc,null,'name');
    $be = array_column($br_exp,null,'name');
    $all_br = array_unique(array_merge(array_keys($bi),array_keys($be)));
    foreach ($all_br as $bn) {
        $br_breakdown[] = [
            'name'    => $bn,
            'income'  => (float)($bi[$bn]['income']??0),
            'gross'   => (float)($bi[$bn]['gross']??0),
            'expenses'=> (float)($be[$bn]['expenses']??0),
        ];
    }
}

// ── Expense by category ───────────────────────────────────────
$s = db()->prepare("SELECT COALESCE(ec.name,'Uncategorized') cat, COALESCE(SUM(e.amount),0) total
  FROM expenses e LEFT JOIN expense_categories ec ON ec.id=e.category_id
  WHERE e.tenant_id=? $br_where_exp AND e.date BETWEEN ? AND ? AND e.status!='cancelled'
  GROUP BY cat ORDER BY total DESC");
$s->execute($br_params_exp); $exp_cats = $s->fetchAll();

ob_start();
?>
<!-- Filters -->
<div class="card" style="margin-bottom:16px">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <div class="form-group" style="margin:0"><label>From</label><input type="date" name="from" value="<?= h($from) ?>"></div>
    <div class="form-group" style="margin:0"><label>To</label><input type="date" name="to" value="<?= h($to) ?>"></div>
    <?php if (!$user['branch_id']): ?>
    <div class="form-group" style="margin:0"><label>Branch</label>
      <select name="branch_id">
        <option value="0">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $br_id==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin:0"><label>Group by</label>
      <select name="group">
        <option value="month" <?= $group=='month'?'selected':'' ?>>Month</option>
        <option value="week"  <?= $group=='week'?'selected':''  ?>>Week</option>
        <option value="day"   <?= $group=='day'?'selected':''   ?>>Day</option>
      </select>
    </div>
    <button class="btn btn-primary">Generate</button>
    <button type="button" class="btn btn-outline" onclick="window.print()">🖨 Print</button>
  </form>
</div>

<!-- KPIs -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Gross Income</div><div class="stat-value income"><?= money($inc_sum['gross_income']) ?></div></div>
  <div class="stat-card"><div class="stat-label">Collected</div><div class="stat-value income"><?= money($inc_sum['collected']) ?></div><div style="font-size:11px;color:var(--c-muted)"><?= $inc_sum['inv_count'] ?> invoices</div></div>
  <div class="stat-card"><div class="stat-label">Outstanding</div><div class="stat-value" style="color:var(--c-warn)"><?= money($inc_sum['outstanding']) ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Purchases</div><div class="stat-value expense"><?= money($pur_sum['total_pur']) ?></div><div style="font-size:11px;color:var(--c-muted)"><?= $pur_sum['pur_count'] ?> orders</div></div>
  <div class="stat-card"><div class="stat-label">Total Expenses</div><div class="stat-value expense"><?= money($exp_sum['total_exp']) ?></div><div style="font-size:11px;color:var(--c-muted)"><?= $exp_sum['exp_count'] ?> records</div></div>
  <div class="stat-card"><div class="stat-label">Net Profit</div><div class="stat-value <?= $net>=0?'income':'expense' ?>"><?= money($net) ?></div></div>
</div>

<!-- Time series table -->
<div class="card">
  <div class="card-title">Period Breakdown</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Period</th><th style="text-align:right">Gross Income</th><th style="text-align:right">Collected</th><th style="text-align:right">Expenses</th><th style="text-align:right">Net</th></tr></thead>
      <tbody>
      <?php $t_inc=0;$t_col=0;$t_exp=0; ?>
      <?php foreach ($periods as $row): $t_inc+=$row['income'];$t_col+=$row['collected'];$t_exp+=$row['expenses'];$pnet=$row['collected']-$row['expenses']; ?>
        <tr>
          <td><?= h($row['period']) ?></td>
          <td style="text-align:right"><?= money($row['income']) ?></td>
          <td style="text-align:right;color:var(--c-income)"><?= money($row['collected']) ?></td>
          <td style="text-align:right;color:var(--c-expense)"><?= money($row['expenses']) ?></td>
          <td style="text-align:right;font-weight:600;color:<?= $pnet>=0?'var(--c-income)':'var(--c-expense)' ?>"><?= money($pnet) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$periods): ?><tr><td colspan="5" style="text-align:center;color:var(--c-muted)">No data in period</td></tr><?php endif; ?>
      </tbody>
      <?php if ($periods): ?>
      <tfoot>
        <tr style="font-weight:700;background:var(--c-bg)">
          <td>TOTAL</td>
          <td style="text-align:right"><?= money($t_inc) ?></td>
          <td style="text-align:right;color:var(--c-income)"><?= money($t_col) ?></td>
          <td style="text-align:right;color:var(--c-expense)"><?= money($t_exp) ?></td>
          <td style="text-align:right;font-weight:700;color:<?= ($t_col-$t_exp)>=0?'var(--c-income)':'var(--c-expense)' ?>"><?= money($t_col-$t_exp) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- Branch breakdown -->
<?php if ($br_breakdown): ?>
<div class="card">
  <div class="card-title">By Branch</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Branch</th><th style="text-align:right">Gross Income</th><th style="text-align:right">Collected</th><th style="text-align:right">Expenses</th><th style="text-align:right">Net</th></tr></thead>
      <tbody>
      <?php foreach ($br_breakdown as $r): $pnet=$r['income']-$r['expenses']; ?>
        <tr>
          <td><?= h($r['name']) ?></td>
          <td style="text-align:right"><?= money($r['gross']) ?></td>
          <td style="text-align:right;color:var(--c-income)"><?= money($r['income']) ?></td>
          <td style="text-align:right;color:var(--c-expense)"><?= money($r['expenses']) ?></td>
          <td style="text-align:right;font-weight:600;color:<?= $pnet>=0?'var(--c-income)':'var(--c-expense)' ?>"><?= money($pnet) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Expense categories -->
<?php if ($exp_cats): ?>
<div class="card">
  <div class="card-title">Expenses by Category</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Category</th><th style="text-align:right">Amount</th><th>Share</th></tr></thead>
      <tbody>
      <?php $tot=(float)$exp_sum['total_exp']; foreach ($exp_cats as $r): $pct=$tot>0?round($r['total']/$tot*100,1):0; ?>
        <tr>
          <td><?= h($r['cat']) ?></td>
          <td style="text-align:right;color:var(--c-expense)"><?= money($r['total']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:120px;height:8px;background:var(--c-border);border-radius:4px;overflow:hidden">
                <div style="width:<?= $pct ?>%;height:100%;background:var(--c-expense);border-radius:4px"></div>
              </div>
              <span style="font-size:12px;color:var(--c-muted)"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
