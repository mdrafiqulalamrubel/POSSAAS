<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Dashboard';
$tid = tid(); $bid = brid();

$from = date('Y-m-01');
$to   = date('Y-m-d');

// Stats for current month
$stmt = db()->prepare('SELECT COALESCE(SUM(total),0) total, COALESCE(SUM(paid),0) paid
                       FROM income WHERE tenant_id=? AND branch_id=? AND date BETWEEN ? AND ? AND status!="cancelled"');
$stmt->execute([$tid, $bid, $from, $to]);
$inc = $stmt->fetch();

$stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) total FROM expenses
                       WHERE tenant_id=? AND branch_id=? AND date BETWEEN ? AND ? AND status!="cancelled"');
$stmt->execute([$tid, $bid, $from, $to]);
$exp = $stmt->fetchColumn();

$income_total = (float)$inc['total'];
$income_paid  = (float)$inc['paid'];
$expense_total= (float)$exp;
$profit       = $income_paid - $expense_total;

// Recent invoices
$stmt = db()->prepare('SELECT i.*, c.name cust_name FROM income i
                       LEFT JOIN customers c ON c.id=i.customer_id
                       WHERE i.tenant_id=? AND i.branch_id=? ORDER BY i.date DESC, i.id DESC LIMIT 8');
$stmt->execute([$tid, $bid]);
$recent = $stmt->fetchAll();

// Recent expenses
$stmt = db()->prepare('SELECT e.*, ec.name cat_name FROM expenses e
                       LEFT JOIN expense_categories ec ON ec.id=e.category_id
                       WHERE e.tenant_id=? AND e.branch_id=? ORDER BY e.date DESC, e.id DESC LIMIT 8');
$stmt->execute([$tid, $bid]);
$recent_exp = $stmt->fetchAll();

ob_start();
?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Income (<?= date('M Y') ?>)</div>
    <div class="stat-value income"><?= money($income_total) ?></div>
    <div style="font-size:12px;color:var(--c-muted);margin-top:4px">Collected: <?= money($income_paid) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Expenses (<?= date('M Y') ?>)</div>
    <div class="stat-value expense"><?= money($expense_total) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Net Profit</div>
    <div class="stat-value profit" style="color:<?= $profit>=0?'var(--c-income)':'var(--c-expense)' ?>"><?= money($profit) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Outstanding</div>
    <div class="stat-value" style="color:var(--c-warn)"><?= money($income_total - $income_paid) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- Recent Invoices -->
  <div class="card">
    <div class="card-title">Recent Invoices
      <a href="<?= APP_URL ?>/income-add.php" class="btn btn-primary btn-sm" style="float:right">+ New</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/invoice-view.php?id=<?= $r['id'] ?>"><?= h($r['invoice_no']) ?></a></td>
            <td><?= h($r['cust_name'] ?: $r['customer_name'] ?: '—') ?></td>
            <td><?= money($r['total']) ?></td>
            <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="4" style="text-align:center;color:var(--c-muted)">No invoices yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Expenses -->
  <div class="card">
    <div class="card-title">Recent Expenses
      <a href="<?= APP_URL ?>/expense-add.php" class="btn btn-outline btn-sm" style="float:right">+ Add</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent_exp as $r): ?>
          <tr>
            <td><?= fmt_date($r['date']) ?></td>
            <td><?= h($r['cat_name'] ?: $r['supplier'] ?: '—') ?></td>
            <td><?= money($r['amount']) ?></td>
            <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent_exp): ?><tr><td colspan="4" style="text-align:center;color:var(--c-muted)">No expenses yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
