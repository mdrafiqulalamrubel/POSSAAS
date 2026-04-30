<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('cashier');
$page_title='Supplier Dues Report';
$tid=tid();$bid=brid();
$search=trim($_GET['q']??'');

$where='WHERE p.tenant_id=? AND p.branch_id=? AND p.status IN("unpaid","partial")';
$params=[$tid,$bid];
if($search){$where.=' AND (p.supplier_name LIKE ? OR p.ref_no LIKE ?)';$params[]="%$search%";$params[]="%$search%";}

$stmt=db()->prepare("SELECT p.*, (p.total-p.paid) due FROM purchases p $where ORDER BY due DESC");
$stmt->execute($params);$rows=$stmt->fetchAll();
$total_due=array_sum(array_column($rows,'due'));

// Summary by supplier
$sum=[];
foreach($rows as $r){
  $k=$r['supplier_name']?:'Unknown';
  if(!isset($sum[$k]))$sum[$k]=['name'=>$k,'total'=>0,'paid'=>0,'due'=>0,'count'=>0];
  $sum[$k]['total']+=$r['total'];$sum[$k]['paid']+=$r['paid'];$sum[$k]['due']+=$r['due'];$sum[$k]['count']++;
}
ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div>
    <h2 style="font-size:16px;font-weight:700"><span class="t-en">Supplier Dues Report</span><span class="t-bn">সাপ্লায়ার বকেয়া রিপোর্ট</span></h2>
    <p style="color:var(--c-muted);font-size:13px">Total: <strong style="color:var(--c-expense)"><?= money($total_due) ?></strong></p>
  </div>
  <button onclick="window.print()" class="btn btn-outline no-print">🖨 Print</button>
</div>

<?php if($sum): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-title">Summary by Supplier</div>
  <div class="table-wrap"><table>
    <thead><tr><th>Supplier</th><th>Invoices</th><th>Total</th><th>Paid</th><th>Due</th></tr></thead>
    <tbody>
    <?php foreach($sum as $s): ?>
      <tr><td><strong><?= h($s['name']) ?></strong></td><td><?= $s['count'] ?></td><td><?= money($s['total']) ?></td><td><?= money($s['paid']) ?></td><td style="font-weight:700;color:var(--c-expense)"><?= money($s['due']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<div class="card" style="padding:0">
  <div class="table-wrap"><table>
    <thead><tr><th>Ref No</th><th>Supplier</th><th>Date</th><th>Total</th><th>Paid</th><th style="color:var(--c-expense)">Due</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($rows as $r): ?>
      <tr>
        <td><a href="<?= APP_URL ?>/purchase-view.php?id=<?= $r['id'] ?>"><?= h($r['ref_no']) ?></a></td>
        <td><?= h($r['supplier_name']?:'—') ?></td>
        <td><?= fmt_date($r['date']) ?></td>
        <td><?= money($r['total']) ?></td>
        <td><?= money($r['paid']) ?></td>
        <td style="font-weight:700;color:var(--c-expense)"><?= money($r['due']) ?></td>
        <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--c-muted)">No dues ✅</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
