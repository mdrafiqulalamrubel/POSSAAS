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

$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([$tid]); $cs = $cs_stmt->fetch() ?: [];

$company_name    = !empty($cs['company_name'])  ? $cs['company_name']  : ($user['tenant_name'] ?? APP_NAME);
$company_address = trim(implode(', ', array_filter([$cs['address']??'',$cs['city']??'',$cs['country']??''])));
$company_phone   = $cs['phone']   ?? $inv['branch_phone'] ?? '';
$company_phone2  = $cs['phone2']  ?? '';
$company_email   = $cs['email']   ?? '';
$company_website = $cs['website'] ?? '';
$footer_note     = !empty($cs['footer_note']) ? $cs['footer_note'] : 'Thank you for your business!';
$currency_sym    = !empty($cs['currency'])    ? $cs['currency']    : CURRENCY;
$tax_label_str   = !empty($cs['tax_label'])   ? $cs['tax_label']   : TAX_LABEL;
$logo_url = '';
if (!empty($cs['logo_path'])) $logo_url = APP_URL . '/' . ltrim($cs['logo_path'], '/');

$balance_due = $inv['total'] - $inv['paid'];

// Parse payment method
$raw_notes   = trim($inv['notes'] ?? '');
$pay_method  = trim($inv['payment_method'] ?? '');
if (!$pay_method) {
    if (preg_match('/Payment[:\s]+([\w\s]+?)(?:[·\|\n]|$)/i', $raw_notes, $pm)) {
        $pay_method = trim($pm[1]);
    }
}
$display_notes = trim(preg_replace('/[·|]?\s*Payment[:\s]+[\w\s]+/i', '', $raw_notes));
$display_notes = trim(preg_replace('/^POS Sale\s*[·|]?\s*/i', '', $display_notes));
$display_notes = trim($display_notes, ' ·|');

$pay_icons = [
    'cash'   => '💵', 'card'   => '💳', 'credit' => '💳',
    'debit'  => '💳', 'bkash'  => '📱', 'nagad'  => '📱',
    'rocket' => '📱', 'bank'   => '🏦', 'cheque' => '🏦',
    'check'  => '🏦', 'mobile' => '📱', 'online' => '🌐',
];
$pay_key  = strtolower($pay_method);
$pay_icon = '💰';
foreach ($pay_icons as $k => $ico) {
    if (str_contains($pay_key, $k)) { $pay_icon = $ico; break; }
}
$is_pos = stripos($raw_notes, 'POS Sale') !== false;

// Cashier / served-by (check common column names)
$cashier = trim($inv['cashier_name'] ?? $inv['served_by'] ?? $inv['created_by_name'] ?? '');

ob_start();
?>

<!-- ── Action Buttons (no-print) ─────────────────────────── -->
<div class="no-print inv-actions">
  <button onclick="printThermal()" class="btn btn-primary">
    🧾 <span class="t-en">Print Receipt</span><span class="t-bn">রসিদ প্রিন্ট</span>
  </button>
  <button onclick="printA4()" class="btn btn-outline">
    🖨 <span class="t-en">A4 Print</span><span class="t-bn">A4 প্রিন্ট</span>
  </button>
  <button onclick="window.print()" class="btn btn-outline">
    📄 <span class="t-en">PDF</span><span class="t-bn">পিডিএফ</span>
  </button>
  <a href="<?= APP_URL ?>/income-add.php?id=<?= $inv['id'] ?>" class="btn btn-outline">
    ✏ <span class="t-en">Edit</span><span class="t-bn">সম্পাদনা</span>
  </a>
  <a href="<?= APP_URL ?>/income.php" class="btn btn-outline">
    ← <span class="t-en">Back</span><span class="t-bn">ফিরে যান</span>
  </a>
</div>

<!-- ── Receipt Box ───────────────────────────────────────── -->
<div class="receipt-wrap" id="invoice-print-area">

  <!-- ── HEADER: centered company info ── -->
  <div class="receipt-header">
    <?php if ($logo_url): ?>
      <img src="<?= h($logo_url) ?>" class="receipt-logo" alt="logo">
    <?php endif; ?>
    <div class="receipt-company"><?= h($company_name) ?></div>
    <?php if ($inv['branch_name']): ?>
      <div class="receipt-sub"><?= h($inv['branch_name']) ?></div>
    <?php endif; ?>
    <?php if ($company_address): ?>
      <div class="receipt-sub"><?= h($company_address) ?></div>
    <?php endif; ?>
    <?php if ($company_phone): ?>
      <div class="receipt-sub">
        📞 <?= h($company_phone) ?><?= $company_phone2 ? ' / ' . h($company_phone2) : '' ?>
      </div>
    <?php endif; ?>
    <?php if ($company_email): ?>
      <div class="receipt-sub">✉ <?= h($company_email) ?></div>
    <?php endif; ?>
    <?php if ($company_website): ?>
      <div class="receipt-sub">🌐 <?= h($company_website) ?></div>
    <?php endif; ?>
  </div>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── INVOICE META ── -->
  <div class="receipt-meta">
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Invoice :</span>
      <span class="receipt-meta-value"><?= h($inv['invoice_no']) ?></span>
    </div>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Date :</span>
      <span class="receipt-meta-value"><?= fmt_date($inv['date']) ?></span>
    </div>
    <?php if ($inv['due_date'] && $inv['due_date'] !== '0000-00-00'): ?>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Due Date :</span>
      <span class="receipt-meta-value"><?= fmt_date($inv['due_date']) ?></span>
    </div>
    <?php endif; ?>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Customer :</span>
      <span class="receipt-meta-value"><?= h($cust_name) ?></span>
    </div>
    <?php if ($inv['cust_phone']): ?>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Phone :</span>
      <span class="receipt-meta-value"><?= h($inv['cust_phone']) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($cashier): ?>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Cashier :</span>
      <span class="receipt-meta-value"><?= h($cashier) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($is_pos): ?>
    <div class="receipt-meta-row">
      <span class="receipt-meta-label">Type :</span>
      <span class="receipt-meta-value" style="color:#5b21b6;font-weight:700">🖥 POS Sale</span>
    </div>
    <?php endif; ?>
  </div>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── LINE ITEMS ── -->
  <table class="receipt-items">
    <thead>
      <tr>
        <th class="col-item">Item</th>
        <th class="col-qty">Qty×Price</th>
        <th class="col-total">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td class="col-item"><strong><?= h($it['description']) ?></strong></td>
        <td class="col-qty">
          <?= rtrim(rtrim(number_format((float)$it['qty'],3,'.',','),'0'),'.') ?>
          × <?= $currency_sym . number_format((float)$it['unit_price'],2) ?>
        </td>
        <td class="col-total">
          <?= $currency_sym . number_format((float)($it['total'] ?? 0) ?: (float)$it['qty']*(float)$it['unit_price'],2) ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
      <tr><td colspan="3" style="text-align:center;padding:16px;color:var(--c-muted)">No items</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── TOTALS ── -->
  <div class="receipt-totals">
    <div class="receipt-totals-row">
      <span>Subtotal</span>
      <span><?= $currency_sym . number_format((float)$inv['subtotal'],2) ?></span>
    </div>
    <?php if ((float)$inv['tax_amount'] > 0): ?>
    <div class="receipt-totals-row" style="color:var(--c-warn)">
      <span><?= h($tax_label_str) ?><?= $inv['tax_pct'] > 0 ? ' (' . $inv['tax_pct'] . '%)' : '' ?></span>
      <span>+ <?= $currency_sym . number_format((float)$inv['tax_amount'],2) ?></span>
    </div>
    <?php elseif ((float)$inv['tax_amount'] == 0): ?>
    <div class="receipt-totals-row" style="color:var(--c-income);font-size:12px">
      <span>VAT</span>
      <span>✅ Included</span>
    </div>
    <?php endif; ?>
    <?php if ((float)$inv['discount'] > 0): ?>
    <div class="receipt-totals-row">
      <span>Discount</span>
      <span style="color:var(--c-income)">-<?= $currency_sym . number_format((float)$inv['discount'],2) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── GRAND TOTAL (bold, big) ── -->
  <div class="receipt-grand-total">
    <span>TOTAL</span>
    <span><?= $currency_sym . number_format((float)$inv['total'],2) ?></span>
  </div>

  <?php if ((float)$inv['paid'] > 0): ?>
  <div class="receipt-totals" style="margin-top:8px">
    <div class="receipt-totals-row" style="color:var(--c-income)">
      <span>Paid</span>
      <span><?= $currency_sym . number_format((float)$inv['paid'],2) ?></span>
    </div>
    <div class="receipt-totals-row" style="color:var(--c-expense);font-weight:700">
      <span>Balance Due</span>
      <span><?= $currency_sym . number_format($balance_due,2) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── PAYMENT METHOD ── -->
  <?php if ($pay_method): ?>
  <div class="receipt-payment">
    Payment: <?= h(ucfirst($pay_method)) ?>
  </div>
  <?php endif; ?>

  <!-- ── STATUS BADGE ── -->
  <div style="text-align:center;margin:10px 0">
    <span class="badge badge-<?= h($inv['status']) ?>" style="font-size:12px;padding:3px 14px">
      <?= strtoupper($inv['status']) ?>
    </span>
  </div>

  <!-- ── NOTES ── -->
  <?php if ($display_notes): ?>
  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>
  <div class="receipt-notes">
    <strong>Notes:</strong><br><?= nl2br(h($display_notes)) ?>
  </div>
  <?php endif; ?>

  <div class="receipt-divider">- - - - - - - - - - - - - - - - - - - - - - -</div>

  <!-- ── FOOTER ── -->
  <div class="receipt-footer">
    <div style="margin-bottom:4px"><?= h($inv['invoice_no']) ?></div>
    <?= nl2br(h($footer_note)) ?>
    <div style="margin-top:6px;font-weight:600">— <?= h($company_name) ?> —</div>
  </div>

</div><!-- /receipt-wrap -->

<style>
/* ════════════════════════════════════════
   RECEIPT LAYOUT
════════════════════════════════════════ */
.inv-actions {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.receipt-wrap {
  max-width: 420px;
  margin: 0 auto;
  background: #fff;
  border: 1px solid var(--c-border, #e5e7eb);
  border-radius: 10px;
  padding: 28px 24px;
  font-family: 'Courier New', Courier, monospace;
  font-size: 13px;
  box-shadow: 0 2px 12px rgba(0,0,0,.07);
}

/* Header */
.receipt-header {
  text-align: center;
  margin-bottom: 4px;
}
.receipt-logo {
  max-height: 64px;
  max-width: 180px;
  object-fit: contain;
  display: block;
  margin: 0 auto 10px;
}
.receipt-company {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: .02em;
  font-family: inherit;
  margin-bottom: 4px;
}
.receipt-sub {
  font-size: 12px;
  color: var(--c-muted, #6b7280);
  line-height: 1.7;
}

/* Divider */
.receipt-divider {
  text-align: center;
  color: var(--c-muted, #9ca3af);
  font-size: 11px;
  letter-spacing: .05em;
  margin: 10px 0;
  overflow: hidden;
  white-space: nowrap;
}

/* Meta rows */
.receipt-meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.receipt-meta-row {
  display: flex;
  gap: 6px;
  font-size: 13px;
}
.receipt-meta-label {
  color: var(--c-muted, #6b7280);
  min-width: 90px;
  flex-shrink: 0;
}
.receipt-meta-value {
  font-weight: 500;
}

/* Items table */
.receipt-items {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.receipt-items thead th {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--c-muted, #6b7280);
  font-weight: 600;
  padding-bottom: 4px;
}
.receipt-items .col-item   { text-align: left; width: 45%; }
.receipt-items .col-qty    { text-align: center; width: 35%; }
.receipt-items .col-total  { text-align: right; width: 20%; }
.receipt-items tbody td {
  padding: 5px 0;
  vertical-align: top;
}

/* Totals */
.receipt-totals {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.receipt-totals-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: var(--c-muted, #6b7280);
}

/* Grand total */
.receipt-grand-total {
  display: flex;
  justify-content: space-between;
  font-size: 20px;
  font-weight: 800;
  letter-spacing: .02em;
  padding: 6px 0;
}

/* Payment */
.receipt-payment {
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  padding: 4px 0;
}

/* Notes */
.receipt-notes {
  font-size: 12px;
  color: var(--c-muted, #6b7280);
  text-align: center;
  line-height: 1.6;
}

/* Footer */
.receipt-footer {
  text-align: center;
  font-size: 12px;
  color: var(--c-muted, #6b7280);
  line-height: 1.8;
}

/* ════════════════════════════════════════
   THERMAL PRINT OVERRIDE (80mm)
════════════════════════════════════════ */
body.print-mode-thermal .receipt-wrap {
  max-width: 76mm !important;
  padding: 4mm 5mm;
  font-size: 10px;
  box-shadow: none;
  border: none;
  border-radius: 0;
}
body.print-mode-thermal .receipt-company { font-size: 14px; }
body.print-mode-thermal .receipt-grand-total { font-size: 16px; }
body.print-mode-thermal .inv-actions { display: none; }

/* ════════════════════════════════════════
   A4 PRINT OVERRIDE
════════════════════════════════════════ */
body.print-mode-a4 .receipt-wrap {
  max-width: 190mm !important;
  padding: 15mm;
  box-shadow: none;
  border: none;
  border-radius: 0;
}

/* ════════════════════════════════════════
   PRINT MEDIA
════════════════════════════════════════ */
@media print {
  .no-print, .inv-actions, .sidebar, .top-bar { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  .content   { padding: 0 !important; }
  .receipt-wrap { box-shadow: none; border: none; border-radius: 0; }
}
</style>

<script>
function printA4() {
  document.body.classList.add('print-mode-a4');
  window.print();
  setTimeout(function(){ document.body.classList.remove('print-mode-a4'); }, 500);
}
function printThermal() {
  document.body.classList.add('print-mode-thermal');
  window.print();
  setTimeout(function(){ document.body.classList.remove('print-mode-thermal'); }, 500);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
