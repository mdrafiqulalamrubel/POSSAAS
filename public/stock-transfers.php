<?php
require_once __DIR__.'/../src/core.php';
$user=require_auth('cashier');$page_title='Stock Transfers';$tid=tid();$bid=brid();
$stmt=db()->prepare('SELECT st.*,bf.name from_name,bt.name to_name FROM stock_transfers st LEFT JOIN branches bf ON bf.id=st.from_branch_id LEFT JOIN branches bt ON bt.id=st.to_branch_id WHERE st.tenant_id=? ORDER BY st.id DESC');
$stmt->execute([$tid]);$rows=$stmt->fetchAll();
ob_start();?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <h2 style="font-size:16px;font-weight:700"><span class="t-en">Stock Transfers</span><span class="t-bn">স্টক ট্রান্সফার</span></h2>
  <a href="<?= APP_URL ?>/stock-transfer-add.php" class="btn btn-primary">+ <span class="t-en">New Transfer</span><span class="t-bn">নতুন ট্রান্সফার</span></a>
</div>
<div class="card" style="padding:0"><div class="table-wrap"><table>
  <thead><tr><th>Transfer No</th><th>From Branch</th><th>To Branch</th><th>Date</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach($rows as $r): ?>
    <tr><td><?= h($r['transfer_no']) ?></td><td><?= h($r['from_name']?:'—') ?></td><td><?= h($r['to_name']?:'—') ?></td><td><?= fmt_date($r['date']) ?></td><td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--c-muted)">No transfers yet</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?php $content=ob_get_clean();include __DIR__.'/../templates/layout.php';
