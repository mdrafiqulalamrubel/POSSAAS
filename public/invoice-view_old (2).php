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

ob_start();
?>

<!-- ── Action Buttons (no-print) ─────────────────────────── -->
<div class="no-print" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <button onclick="printA4()" class="btn btn-outline">
    🖨 <span class="t-en">A4 Print</span><span class="t-bn">A4 প্রিন্ট</span>
  </button>
  <button onclick="printThermal()" class="btn btn-outline">
    🧾 <span class="t-en">Thermal (80mm)</span><span class="t-bn">থার্মাল প্রিন্ট</span>
  </button>
  <button onclick="window.print()" class="btn btn-primary">
    🖨 <span class="t-en">Print / PDF</span><span class="t-bn">প্রিন্ট / পিডিএফ</span>
  </button>
  <a href="<?= APP_URL ?>/income-add.php?id=<?= $inv['id'] ?>" class="btn btn-outline">
    ✏ <span class="t-en">Edit</span><span class="t-bn">সম্পাদনা</span>
  </a>
  <a href="<?= APP_URL ?>/income.php" class="btn btn-outline">
    ← <span class="t-en">Back</span><span class="t-bn">ফিরে যান</span>
  </a>
</div>

<!-- ── Invoice Box ───────────────────────────────────────── -->
<div class="invoice-box" id="invoice-print-area">

  <!-- Header: Logo + Company Info | Invoice Meta -->
  <div class="invoice-header">
    <div>
      <?php if ($logo_url): ?>
        <img src="<?= h($logo_url) ?>" style="max-height:72px;max-width:200px;object-fit:contain;margin-bottom:10px;display:block">
      <?php endif; ?>
      <div class="invoice-company"><?= h($company_name) ?></div>
      <div style="font-size:13px;color:var(--c-muted);line-height:1.8;margin-top:4px">
        <?php if ($company_address): ?><?= h($company_address) ?><br><?php endif; ?>
        <?php if ($company_phone):   ?>📞 <?= h($company_phone) ?><?php endif; ?>
        <?php if ($company_phone2):  ?> &nbsp;/&nbsp; <?= h($company_phone2) ?><?php endif; ?>
        <?php if ($company_email):   ?><br>✉ <?= h($company_email) ?><?php endif; ?>
        <?php if ($company_website): ?><br>🌐 <?= h($company_website) ?><?php endif; ?>
      </div>
    </div>
    <div class="invoice-meta">
      <div style="font-size:11px;text-transform:uppercase;color:var(--c-muted);letter-spacing:.1em;margin-bottom:4px">INVOICE</div>
      <div class="inv-no"><?= h($inv['invoice_no']) ?></div>
      <div class="inv-date" style="margin-top:6px">Date: <?= fmt_date($inv['date']) ?></div>
      <?php if ($inv['due_date'] && $inv['due_date'] !== '0000-00-00'): ?>
      <div class="inv-date">Due: <?= fmt_date($inv['due_date']) ?></div>
      <?php endif; ?>
      <div style="margin-top:8px">
        <span class="badge badge-<?= h($inv['status']) ?>" style="font-size:13px;padding:4px 14px">
          <?= strtoupper($inv['status']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- Bill From / Bill To -->
  <div class="invoice-parties">
    <div>
      <div class="party-label">Bill From</div>
      <strong style="font-size:15px"><?= h($company_name) ?></strong><br>
      <?php if ($inv['branch_name']): ?>
        <span style="font-size:13px;color:var(--c-muted)"><?= h($inv['branch_name']) ?></span><br>
      <?php endif; ?>
      <?php if ($company_address): ?>
        <span style="font-size:12px;color:var(--c-muted)"><?= h($company_address) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <div class="party-label">Bill To</div>
      <strong style="font-size:15px"><?= h($cust_name) ?></strong>
      <?php if ($inv['cust_phone']):   ?><br><span style="font-size:13px">📞 <?= h($inv['cust_phone']) ?></span><?php endif; ?>
      <?php if ($inv['cust_email']):   ?><br><span style="font-size:13px">✉ <?= h($inv['cust_email']) ?></span><?php endif; ?>
      <?php if ($inv['cust_address']): ?><br><span style="font-size:12px;color:var(--c-muted)"><?= h($inv['cust_address']) ?></span><?php endif; ?>
    </div>
  </div>

  <!-- Line Items -->
  <div class="table-wrap" style="margin-bottom:16px">
    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Description</th>
          <th style="text-align:right;width:80px">Qty</th>
          <th style="text-align:right;width:120px">Unit Price</th>
          <th style="text-align:right;width:120px">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i => $it): ?>
        <tr>
          <td style="color:var(--c-muted)"><?= $i+1 ?></td>
          <td><strong><?= h($it['description']) ?></strong></td>
          <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$it['qty'],3,'.',','),'0'),'.') ?></td>
          <td style="text-align:right"><?= $currency_sym . number_format((float)$it['unit_price'],2) ?></td>
          <td style="text-align:right;font-weight:600"><?= $currency_sym . number_format((float)($it['total'] ?? 0) ?: (float)$it['qty']*(float)$it['unit_price'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--c-muted)">No items</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="invoice-totals">
    <table class="totals-table">
      <tr>
        <td style="color:var(--c-muted)">Subtotal</td>
        <td style="text-align:right"><?= $currency_sym . number_format((float)$inv['subtotal'],2) ?></td>
      </tr>
      <?php if ((float)$inv['tax_pct'] > 0): ?>
      <tr>
        <td style="color:var(--c-muted)"><?= h($tax_label_str) ?> (<?= $inv['tax_pct'] ?>%)</td>
        <td style="text-align:right"><?= $currency_sym . number_format((float)$inv['tax_amount'],2) ?></td>
      </tr>
      <?php endif; ?>
      <?php if ((float)$inv['discount'] > 0): ?>
      <tr>
        <td style="color:var(--c-muted)">Discount</td>
        <td style="text-align:right;color:var(--c-income)">-<?= $currency_sym . number_format((float)$inv['discount'],2) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="grand-total">
        <td>Total</td>
        <td style="text-align:right;color:var(--c-primary)"><?= $currency_sym . number_format((float)$inv['total'],2) ?></td>
      </tr>
      <?php if ((float)$inv['paid'] > 0): ?>
      <tr>
        <td style="color:var(--c-income)">Paid</td>
        <td style="text-align:right;color:var(--c-income)"><?= $currency_sym . number_format((float)$inv['paid'],2) ?></td>
      </tr>
      <tr>
        <td style="color:var(--c-expense);font-weight:700">Balance Due</td>
        <td style="text-align:right;color:var(--c-expense);font-weight:700"><?= $currency_sym . number_format($balance_due,2) ?></td>
      </tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- Payment Method + Notes -->
  <?php
  // Parse payment method — check dedicated column first, then fallback to notes
  $raw_notes   = trim($inv['notes'] ?? '');
  $pay_method  = trim($inv['payment_method'] ?? '');
  $display_notes = $raw_notes;

  if (!$pay_method) {
    // Extract "Payment: X" from notes (legacy POS format)
    if (preg_match('/Payment[:\s]+([\w\s]+?)(?:[·\|\n]|$)/i', $raw_notes, $pm)) {
        $pay_method = trim($pm[1]);
    }
  }
  // Always clean notes display — remove POS Sale and Payment fragments
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
  ?>

  <?php if ($pay_method || $is_pos): ?>
  <div style="margin-top:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <?php if ($is_pos): ?>
      <span style="background:#ede9fe;color:#5b21b6;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700">🖥 POS Sale</span>
    <?php endif; ?>
    <?php if ($pay_method): ?>
      <span style="background:#d1fae5;color:#065f46;padding:5px 16px;border-radius:20px;font-size:13px;font-weight:700">
        <?= $pay_icon ?> Payment: <?= h(ucfirst($pay_method)) ?>
      </span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($display_notes): ?>
  <div style="margin-top:12px;padding:14px;background:#f8f9fb;border-radius:var(--radius);border-left:3px solid var(--c-primary);font-size:13px">
    <strong>Notes:</strong><br><?= nl2br(h($display_notes)) ?>
  </div>
  <?php endif; ?>

  <!-- Footer -->
  <div style="margin-top:32px;padding-top:16px;border-top:1px solid var(--c-border);text-align:center;font-size:12px;color:var(--c-muted)">
    <?= h($footer_note) ?>
  </div>

</div><!-- /invoice-box -->

<style>
/* ── Thermal override (80mm) ── */
body.print-mode-thermal .invoice-box {
  max-width: 76mm !important;
  padding: 6mm;
  font-size: 10px;
  box-shadow: none;
  border: none;
}
body.print-mode-thermal .invoice-header  { flex-direction: column; gap: 6px; }
body.print-mode-thermal .invoice-meta    { text-align: left; }
body.print-mode-thermal .invoice-company { font-size: 14px; }
body.print-mode-thermal .inv-no          { font-size: 14px; }
body.print-mode-thermal .invoice-parties { grid-template-columns: 1fr; gap: 8px; padding: 8px; }
body.print-mode-thermal thead th         { font-size: 10px; }
body.print-mode-thermal tbody td         { font-size: 10px; padding: 4px 6px; }
body.print-mode-thermal .invoice-totals  { justify-content: flex-start; }
body.print-mode-thermal .totals-table    { min-width: 100%; }
body.print-mode-thermal .status-banner   { font-size: 12px; }

/* ── A4 override (210mm) ── */
body.print-mode-a4 .invoice-box {
  max-width: 190mm !important;
  padding: 15mm;
  box-shadow: none;
  border: none;
}

@media print {
  .no-print, .sidebar, .top-bar { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  .content   { padding: 0 !important; }
  .invoice-box { box-shadow: none; border: none; }
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
