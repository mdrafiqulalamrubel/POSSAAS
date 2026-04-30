<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$tid = tid(); $bid = brid();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect('purchases.php'); }

$s = db()->prepare('SELECT * FROM purchases WHERE id=? AND tenant_id=?');
$s->execute([$id,$tid]); $pur = $s->fetch();
if (!$pur) { flash('error','Purchase not found'); redirect('purchases.php'); }

$items = db()->prepare('SELECT * FROM purchase_items WHERE purchase_id=?');
$items->execute([$id]); $items = $items->fetchAll();

$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$cur = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;
$company = !empty($cs['company_name']) ? $cs['company_name'] : $user['tenant_name'];

$page_title = 'Purchase ' . $pur['ref_no'];
$badge = match($pur['status']) {
  'paid' => 'badge-paid', 'received' => 'badge-partial',
  'partial' => 'badge-partial', 'cancelled' => 'badge-cancelled', default => 'badge-unpaid'
};

ob_start();
?>
<div class="no-print" style="display:flex;gap:10px;margin-bottom:16px">
  <button onclick="window.print()" class="btn btn-outline">🖨 Print</button>
  <a href="<?= APP_URL ?>/purchase-add.php?id=<?= $pur['id'] ?>" class="btn btn-outline">✏ Edit</a>
  <a href="<?= APP_URL ?>/purchases.php" class="btn btn-outline">← Back</a>
  <?php if ($pur['status'] !== 'cancelled'): ?>
  <a href="<?= APP_URL ?>/purchase-delete.php?id=<?= $pur['id'] ?>"
     class="btn btn-danger" onclick="return confirm('Cancel this purchase? Stock will be restored.')">✕ Cancel</a>
  <?php endif; ?>
</div>

<div class="card" style="max-width:800px;margin:0 auto">
  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:16px;border-bottom:2px solid var(--c-border)">
    <div>
      <?php if (!empty($cs['logo_path']) && file_exists(__DIR__.'/'.$cs['logo_path'])): ?>
        <img src="<?= APP_URL.'/'.h($cs['logo_path']) ?>" style="height:48px;margin-bottom:8px;display:block">
      <?php endif; ?>
      <div style="font-size:20px;font-weight:800"><?= h($company) ?></div>
      <?php if ($cs['address']): ?><div style="font-size:13px;color:var(--c-muted)"><?= h($cs['address']) ?></div><?php endif; ?>
      <?php if ($cs['phone']): ?><div style="font-size:13px;color:var(--c-muted)">📞 <?= h($cs['phone']) ?></div><?php endif; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:22px;font-weight:800;color:var(--c-primary)">PURCHASE ORDER</div>
      <div style="font-size:18px;font-weight:700"><?= h($pur['ref_no']) ?></div>
      <div style="margin-top:6px"><span class="badge <?= $badge ?>"><?= ucfirst($pur['status']) ?></span></div>
    </div>
  </div>

  <!-- Meta -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <div>
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--c-muted);margin-bottom:6px">Supplier</div>
      <div style="font-weight:600"><?= h($pur['supplier_name'] ?: 'N/A') ?></div>
    </div>
    <div style="text-align:right">
      <table style="margin-left:auto;font-size:13px">
        <tr><td style="color:var(--c-muted);padding-right:16px">Date:</td><td><strong><?= fmt_date($pur['date']) ?></strong></td></tr>
        <tr><td style="color:var(--c-muted)">Branch:</td><td><?= h(array_column(get_branches(),'name','id')[$pur['branch_id']] ?? '—') ?></td></tr>
      </table>
    </div>
  </div>

  <!-- Items -->
  <table style="width:100%;margin-bottom:20px">
    <thead>
      <tr style="background:var(--c-bg)">
        <th style="padding:8px;text-align:left">#</th>
        <th style="padding:8px;text-align:left">Description</th>
        <th style="padding:8px;text-align:right">Qty</th>
        <th style="padding:8px;text-align:right">Unit Price</th>
        <th style="padding:8px;text-align:right">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
      <tr style="border-bottom:1px solid var(--c-border)">
        <td style="padding:8px;color:var(--c-muted)"><?= $i+1 ?></td>
        <td style="padding:8px"><?= h($it['description']) ?></td>
        <td style="padding:8px;text-align:right"><?= $it['qty']+0 ?></td>
        <td style="padding:8px;text-align:right"><?= $cur.number_format($it['unit_price'],2) ?></td>
        <td style="padding:8px;text-align:right;font-weight:600"><?= $cur.number_format($it['total'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totals -->
  <div style="display:flex;justify-content:flex-end">
    <table style="min-width:260px;font-size:14px">
      <tr><td style="padding:4px 16px 4px 0;color:var(--c-muted)">Subtotal</td><td style="text-align:right;font-weight:600"><?= $cur.number_format($pur['subtotal'],2) ?></td></tr>
      <?php if ($pur['tax_amount'] > 0): ?>
      <tr><td style="padding:4px 16px 4px 0;color:var(--c-muted)">Tax (<?= $pur['tax_pct'] ?>%)</td><td style="text-align:right"><?= $cur.number_format($pur['tax_amount'],2) ?></td></tr>
      <?php endif; ?>
      <?php if ($pur['discount'] > 0): ?>
      <tr><td style="padding:4px 16px 4px 0;color:var(--c-muted)">Discount</td><td style="text-align:right;color:var(--c-income)">-<?= $cur.number_format($pur['discount'],2) ?></td></tr>
      <?php endif; ?>
      <tr style="border-top:2px solid var(--c-border)"><td style="padding:8px 16px 4px 0;font-weight:700;font-size:16px">TOTAL</td><td style="text-align:right;font-weight:800;font-size:16px;color:var(--c-primary)"><?= $cur.number_format($pur['total'],2) ?></td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:var(--c-income)">Paid</td><td style="text-align:right;color:var(--c-income)"><?= $cur.number_format($pur['paid'],2) ?></td></tr>
      <tr><td style="padding:4px 16px 4px 0;color:var(--c-warn);font-weight:600">Balance Due</td><td style="text-align:right;color:var(--c-warn);font-weight:700"><?= $cur.number_format($pur['balance'],2) ?></td></tr>
    </table>
  </div>

  <?php if ($pur['notes']): ?>
  <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--c-border)">
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:var(--c-muted);margin-bottom:4px">Notes</div>
    <div style="font-size:13px"><?= nl2br(h($pur['notes'])) ?></div>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
