<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$tid = tid(); $bid = brid();
$id = (int)($_GET['id'] ?? 0);

$s = db()->prepare('SELECT i.*, c.name cust_full, c.email cust_email, c.phone cust_phone, c.address cust_address,
                    b.name branch_name, b.address branch_address, b.phone branch_phone
                    FROM income i
                    LEFT JOIN customers c ON c.id=i.customer_id
                    LEFT JOIN branches b ON b.id=i.branch_id
                    WHERE i.id=? AND i.tenant_id=?');
$s->execute([$id, $tid]);
$inv = $s->fetch();
if (!$inv) { flash('error','Invoice not found'); redirect('income.php'); }

$si = db()->prepare('SELECT * FROM income_items WHERE income_id=? ORDER BY id');
$si->execute([$id]); $items = $si->fetchAll();

$page_title = 'Invoice ' . $inv['invoice_no'];
$cust_name  = $inv['cust_full'] ?: $inv['customer_name'] ?: 'Walk-in Customer';

// Load company settings
$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([$tid]);
$cs = $cs_stmt->fetch() ?: [];
$company_name    = !empty($cs['company_name'])   ? $cs['company_name']   : $user['tenant_name'];
$company_address = trim(implode(', ', array_filter([
    $cs['address'] ?? '', $cs['city'] ?? '', $cs['country'] ?? ''
])));
$company_phone   = !empty($cs['phone'])   ? $cs['phone']   : ($inv['branch_phone'] ?? '');
$company_phone2  = $cs['phone2'] ?? '';
$company_email   = $cs['email']   ?? '';
$company_website = $cs['website'] ?? '';
$footer_note     = !empty($cs['footer_note']) ? $cs['footer_note'] : 'Thank you for your business · ' . $company_name;
$currency_sym    = !empty($cs['currency'])    ? $cs['currency']    : CURRENCY;
$tax_label_str   = !empty($cs['tax_label'])   ? $cs['tax_label']   : TAX_LABEL;

// Resolve logo URL
$logo_url = '';
$logo_path = $cs['logo_path'] ?? '';
if ($logo_path) {
    if (file_exists(__DIR__ . '/' . $logo_path)) {
        $logo_url = APP_URL . '/' . $logo_path;
    } elseif (file_exists(__DIR__ . '/../public/' . $logo_path)) {
        $logo_url = APP_URL . '/' . $logo_path;
    }
}

ob_start();
?>
<div class="no-print" style="display:flex;gap:10px;margin-bottom:20px">
  <button onclick="window.print()" class="btn btn-primary">🖨 Print / PDF</button>
  <a href="<?= APP_URL ?>/income-add.php?id=<?= $inv['id'] ?>" class="btn btn-outline">Edit</a>
  <a href="<?= APP_URL ?>/income.php" class="btn btn-outline">← Back</a>
</div>

<div class="invoice-box">
  <!-- Header -->
  <div class="invoice-header">
    <div>
      <?php if ($logo_url): ?>
        <img src="<?= h($logo_url) ?>" style="max-height:70px;max-width:220px;object-fit:contain;margin-bottom:10px;display:block">
      <?php endif; ?>
      <div class="invoice-company"><?= h($company_name) ?></div>
      <div style="font-size:13px;color:var(--c-muted)">
        <?php if ($company_address): ?><?= h($company_address) ?><br><?php endif; ?>
        <?php if ($company_phone): ?>📞 <?= h($company_phone) ?><?php endif; ?>
        <?php if ($company_phone2): ?> &nbsp;/&nbsp; <?= h($company_phone2) ?><?php endif; ?>
        <?php if ($company_email): ?><br>✉ <?= h($company_email) ?><?php endif; ?>
        <?php if ($company_website): ?><br>🌐 <?= h($company_website) ?><?php endif; ?>
      </div>
    </div>
    <div class="invoice-meta">
      <div style="font-size:12px;text-transform:uppercase;color:var(--c-muted);margin-bottom:4px">Invoice</div>
      <div class="inv-no"><?= h($inv['invoice_no']) ?></div>
      <div class="inv-date">Date: <?= fmt_date($inv['date']) ?></div>
      <?php if ($inv['due_date']): ?>
      <div class="inv-date">Due: <?= fmt_date($inv['due_date']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Parties -->
  <div class="invoice-parties">
    <div>
      <div class="party-label">Bill From</div>
      <strong><?= h($company_name) ?></strong><br>
      <span style="font-size:13px;color:var(--c-muted)"><?= h($inv['branch_name']) ?></span>
      <?php if ($company_address): ?><br><span style="font-size:12px;color:var(--c-muted)"><?= h($company_address) ?></span><?php endif; ?>
    </div>
    <div>
      <div class="party-label">Bill To</div>
      <strong><?= h($cust_name) ?></strong>
      <?php if ($inv['cust_email']): ?><br><span style="font-size:12px"><?= h($inv['cust_email']) ?></span><?php endif; ?>
      <?php if ($inv['cust_phone']): ?><br><span style="font-size:12px"><?= h($inv['cust_phone']) ?></span><?php endif; ?>
      <?php if ($inv['cust_address']): ?><br><span style="font-size:12px;color:var(--c-muted)"><?= h($inv['cust_address']) ?></span><?php endif; ?>
    </div>
  </div>

  <!-- Items -->
  <div class="table-wrap" style="margin-bottom:20px">
    <table>
      <thead>
        <tr><th>#</th><th>Description</th><th style="text-align:right">Qty</th>
            <th style="text-align:right">Unit Price</th><th style="text-align:right">Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $it): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= h($it['description']) ?></td>
          <td style="text-align:right"><?= rtrim(rtrim(number_format($it['qty'],3,'.',','),'0'),'.') ?></td>
          <td style="text-align:right"><?= $currency_sym . number_format($it['unit_price'],2) ?></td>
          <td style="text-align:right"><?= $currency_sym . number_format($it['total'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="invoice-totals">
    <table class="totals-table">
      <tr><td>Subtotal</td><td style="text-align:right"><?= $currency_sym . number_format($inv['subtotal'],2) ?></td></tr>
      <?php if ($inv['tax_pct'] > 0): ?>
      <tr><td><?= h($tax_label_str) ?> (<?= $inv['tax_pct'] ?>%)</td><td style="text-align:right"><?= $currency_sym . number_format($inv['tax_amount'],2) ?></td></tr>
      <?php endif; ?>
      <?php if ($inv['discount'] > 0): ?>
      <tr><td>Discount</td><td style="text-align:right">-<?= $currency_sym . number_format($inv['discount'],2) ?></td></tr>
      <?php endif; ?>
      <tr class="grand-total"><td>Total</td><td style="text-align:right"><?= $currency_sym . number_format($inv['total'],2) ?></td></tr>
      <?php if ($inv['paid'] > 0): ?>
      <tr><td style="color:var(--c-income)">Paid</td><td style="text-align:right;color:var(--c-income)"><?= $currency_sym . number_format($inv['paid'],2) ?></td></tr>
      <tr><td style="color:var(--c-expense)">Balance Due</td><td style="text-align:right;color:var(--c-expense)"><?= $currency_sym . number_format($inv['balance'] ?? ($inv['total']-$inv['paid']),2) ?></td></tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Notes -->
  <?php if ($inv['notes']): ?>
  <div style="margin-top:24px;padding:14px;background:var(--c-bg);border-radius:var(--radius);font-size:13px">
    <strong>Notes:</strong><br><?= nl2br(h($inv['notes'])) ?>
  </div>
  <?php endif; ?>

  <!-- Status banner -->
  <div class="status-banner status-<?= in_array($inv['status'],['paid'])?'paid':'unpaid' ?>">
    <?= strtoupper($inv['status']) ?>
  </div>

  <div style="margin-top:24px;text-align:center;font-size:11px;color:var(--c-muted)">
    <?= h($footer_note) ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
