<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('cashier');
$page_title='Dues Report';
$tid=tid();$bid=brid();
$search=trim($_GET['q']??'');

$where='WHERE i.tenant_id=? AND i.branch_id=? AND i.status IN("unpaid","partial")';
$params=[$tid,$bid];
if($search){$where.=' AND (i.customer_name LIKE ? OR i.invoice_no LIKE ?)';$params[]="%$search%";$params[]="%$search%";}

$stmt=db()->prepare("SELECT i.*, (i.total-i.paid) due FROM income i $where ORDER BY due DESC");
$stmt->execute($params);$rows=$stmt->fetchAll();

$total_due=array_sum(array_column($rows,'due'));
ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div>
    <h2 style="font-size:16px;font-weight:700"><span class="t-en">Customer Dues Report</span><span class="t-bn">গ্রাহক বকেয়া রিপোর্ট</span></h2>
    <p style="color:var(--c-muted);font-size:13px"><span class="t-en">Total Outstanding:</span><span class="t-bn">মোট বকেয়া:</span> <strong style="color:var(--c-expense)"><?= money($total_due) ?></strong></p>
  </div>
  <button onclick="window.print()" class="btn btn-outline no-print">🖨 <span class="t-en">Print</span><span class="t-bn">প্রিন্ট</span></button>
</div>
<div class="card" style="padding:12px;margin-bottom:16px" class="no-print">
  <form method="get" style="display:flex;gap:10px">
    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search customer / invoice…" style="flex:1">
    <button class="btn btn-outline">Filter</button>
    <a href="?" class="btn btn-outline">Clear</a>
  </form>
</div>
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Total</th><th>Paid</th><th style="color:var(--c-expense)">Due</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/invoice-view.php?id=<?= $r['id'] ?>"><?= h($r['invoice_no']) ?></a></td>
          <td><?= h($r['customer_name']?:'—') ?></td>
          <td><?= fmt_date($r['date']) ?></td>
          <td><?= money($r['total']) ?></td>
          <td><?= money($r['paid']) ?></td>
          <td style="font-weight:700;color:var(--c-expense)"><?= money($r['due']) ?></td>
          <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--c-muted)">No dues found ✅</td></tr><?php endif; ?>
      </tbody>
      <?php if($rows): ?>
      <tfoot><tr style="font-weight:700;background:var(--c-bg)"><td colspan="5">Total</td><td style="color:var(--c-expense)"><?= money($total_due) ?></td><td></td></tr></tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
