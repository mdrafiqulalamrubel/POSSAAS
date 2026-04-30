<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('cashier');$page_title='Purchase Returns';$tid=tid();$bid=brid();
$stmt=db()->prepare('SELECT * FROM purchase_returns WHERE tenant_id=? AND branch_id=? ORDER BY id DESC');
$stmt->execute([$tid,$bid]);$rows=$stmt->fetchAll();
ob_start();?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="font-size:16px;font-weight:700"><span class="t-en">Purchase Returns</span><span class="t-bn">ক্রয় ফেরত</span></h2>
  <a href="<?= APP_URL ?>/purchase-return-add.php" class="btn btn-primary">+ <span class="t-en">New Return</span><span class="t-bn">নতুন ফেরত</span></a>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
  <thead><tr><th>Return No</th><th>Supplier</th><th>Date</th><th>Total</th><th></th></tr></thead>
  <tbody>
  <?php foreach($rows as $r): ?>
    <tr><td><?= h($r['return_no']) ?></td><td><?= h($r['supplier_name']?:'—') ?></td><td><?= fmt_date($r['date']) ?></td><td style="color:var(--c-expense)"><?= money($r['total']) ?></td><td><a href="?del=<?= $r['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">Del</a></td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--c-muted)">No returns yet</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
