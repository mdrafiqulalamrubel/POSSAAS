<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();

// Migration: ensure payment_method column exists
try {
    db()->exec("ALTER TABLE income ADD COLUMN payment_method VARCHAR(50) NULL AFTER notes");
} catch (PDOException $e) { /* column already exists — safe to ignore */ }
$tid = tid(); $bid = brid();
$edit_id = (int)($_GET['id'] ?? 0);
$page_title = $edit_id ? 'Edit Invoice' : 'New Invoice';

// Load company settings for currency
$cs_stmt = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs_stmt->execute([$tid]);
$cs = $cs_stmt->fetch() ?: [];
$currency_sym = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

// Load for edit
$inv = null; $items = [];
if ($edit_id) {
    $s = db()->prepare('SELECT * FROM income WHERE id=? AND tenant_id=? AND branch_id=?');
    $s->execute([$edit_id, $tid, $bid]);
    $inv = $s->fetch();
    if (!$inv) { flash('error','Invoice not found'); redirect('income.php'); }
    $si = db()->prepare('SELECT * FROM income_items WHERE income_id=?');
    $si->execute([$edit_id]); $items = $si->fetchAll();
}

// Save invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $rows = [];
    $subtotal = 0;
    foreach (($data['items']['description'] ?? []) as $i => $desc) {
        if (trim($desc) === '') continue;
        $qty      = (float)($data['items']['qty'][$i] ?? 1);
        $price    = (float)($data['items']['unit_price'][$i] ?? 0);
        $item_id  = (int)($data['items']['item_id'][$i] ?? 0);   // ← capture item_id
        $item_vat = (float)($data['items']['vat_pct'][$i] ?? 0);
        $line_sub = $qty * $price;
        $line_vat = $item_vat > 0 ? ($line_sub * $item_vat / 100) : 0;
        $rows[]    = [
            'desc'    => $desc,
            'qty'     => $qty,
            'price'   => $price,
            'total'   => $line_sub,
            'item_id' => $item_id,           // ← store in rows
            'vat_pct' => $item_vat,
            'vat_amt' => $line_vat,
        ];
        $subtotal += $line_sub;
    }
    // tax_amount: sum of per-item VAT amounts
    $tax_pct  = (float)($data['tax_pct']  ?? 0);
    $discount = (float)($data['discount'] ?? 0);
    $vat_from_items = array_sum(array_column($rows, 'vat_amt'));
    if ($vat_from_items > 0) {
        $tax_amt = $vat_from_items;
        $tax_pct = ($subtotal > 0) ? round($tax_amt / $subtotal * 100, 2) : 0;
    } else {
        $tax_amt = $subtotal * $tax_pct / 100;
    }
    $total = $subtotal + $tax_amt - $discount;
    $paid     = (float)($data['paid'] ?? 0);
    $status   = 'unpaid';
    if ($paid >= $total && $total > 0) $status = 'paid';
    elseif ($paid > 0) $status = 'partial';
    if (($data['status'] ?? '') === 'draft' || ($data['status'] ?? '') === 'cancelled') $status = $data['status'];

    $cust_id   = ($data['customer_id'] ?? '') ?: null;
    $cust_name = trim($data['customer_name'] ?? '');

    // Auto-save new customer if name given but no ID
    if ($cust_name && !$cust_id) {
        $phone = trim($data['customer_phone'] ?? '');
        $email = trim($data['customer_email'] ?? '');
        $chk = $phone
            ? db()->prepare('SELECT id FROM customers WHERE tenant_id=? AND phone=? LIMIT 1')
            : db()->prepare('SELECT id FROM customers WHERE tenant_id=? AND name=? LIMIT 1');
        $chk->execute([$tid, $phone ?: $cust_name]);
        $cust_id = $chk->fetchColumn() ?: null;
        if (!$cust_id) {
            db()->prepare('INSERT INTO customers (tenant_id,name,phone,email) VALUES (?,?,?,?)')
                ->execute([$tid, $cust_name, $phone, $email]);
            $cust_id = db()->lastInsertId();
        }
    }

    // ── Ensure income_items has item_id, vat_pct, vat_amount columns ──
    try {
        db()->exec("ALTER TABLE income_items ADD COLUMN item_id INT NULL DEFAULT NULL AFTER income_id");
    } catch (PDOException $e) {}
    try {
        db()->exec("ALTER TABLE income_items ADD COLUMN vat_pct DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER unit_price");
    } catch (PDOException $e) {}
    try {
        db()->exec("ALTER TABLE income_items ADD COLUMN vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER vat_pct");
    } catch (PDOException $e) {}

    db()->beginTransaction();
    try {
        if ($edit_id) {
            // ── RESTORE stock for original items before overwriting ──
            $old_items = db()->prepare('SELECT item_id, qty FROM income_items WHERE income_id=? AND item_id IS NOT NULL AND item_id > 0');
            $old_items->execute([$edit_id]);
            $restore = db()->prepare('UPDATE items SET quantity = quantity + ? WHERE id=? AND tenant_id=? AND branch_id=?');
            foreach ($old_items->fetchAll() as $oi) {
                $restore->execute([$oi['qty'], $oi['item_id'], $tid, $bid]);
            }

            $pay_method_upd = trim($data['payment_method'] ?? '');
            db()->prepare('UPDATE income SET customer_id=?,customer_name=?,date=?,due_date=?,
                           subtotal=?,tax_pct=?,tax_amount=?,discount=?,total=?,paid=?,status=?,notes=?,payment_method=?
                           WHERE id=? AND tenant_id=?')
                ->execute([$cust_id,$cust_name,$data['date'],$data['due_date']??null,
                           $subtotal,$tax_pct,$tax_amt,$discount,$total,$paid,$status,$data['notes']??'',$pay_method_upd,
                           $edit_id,$tid]);
            db()->prepare('DELETE FROM income_items WHERE income_id=?')->execute([$edit_id]);
            $inc_id = $edit_id;
        } else {
            $inv_no = next_invoice_no($bid);
            $pay_method_val = trim($data['payment_method'] ?? '');
            db()->prepare('INSERT INTO income (tenant_id,branch_id,invoice_no,customer_id,customer_name,
                           date,due_date,subtotal,tax_pct,tax_amount,discount,total,paid,status,notes,payment_method,created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$tid,$bid,$inv_no,$cust_id,$cust_name,$data['date'],($data['due_date']??'')?:null,
                           $subtotal,$tax_pct,$tax_amt,$discount,$total,$paid,$status,$data['notes']??'',$pay_method_val,uid()]);
            $inc_id = db()->lastInsertId();
        }

        // ── INSERT income_items + DEDUCT stock ────────────────────────
        $ins   = db()->prepare('INSERT INTO income_items (income_id,item_id,description,qty,unit_price,vat_pct,vat_amount) VALUES (?,?,?,?,?,?,?)');
        $deduct = db()->prepare('UPDATE items SET quantity = quantity - ? WHERE id=? AND tenant_id=? AND branch_id=? AND quantity >= 0');
        foreach ($rows as $r) {
            $ins->execute([$inc_id, $r['item_id'] ?: null, $r['desc'], $r['qty'], $r['price'], $r['vat_pct'], $r['vat_amt']]);
            // ← STOCK DEDUCTION — only for items that have a valid item_id
            if ($r['item_id'] > 0) {
                $deduct->execute([$r['qty'], $r['item_id'], $tid, $bid]);
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        flash('error', 'Save failed: ' . $e->getMessage());
        redirect('income-add.php' . ($edit_id ? '?id='.$edit_id : ''));
    }

    log_activity('sale', 'Income', ($edit_id ? 'Invoice updated' : 'Invoice created').": $inv_no", json_encode(['total'=>$total,'items'=>count($rows)]));
    flash('success', $edit_id ? 'Invoice updated.' : "Invoice created: $inv_no");
    redirect('invoice-view.php?id=' . $inc_id);
}

$today = date('Y-m-d');
ob_start();
?>
<style>
/* ── Customer panel ── */
#cust_panel { background:var(--c-bg);border:1px solid var(--c-border);border-radius:var(--radius);padding:14px;margin-top:8px;display:none }
.ac-dropdown { display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;background:var(--c-card);border:1px solid var(--c-border);border-radius:var(--radius);box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1000;max-height:240px;overflow-y:auto }
.ac-item { padding:9px 14px;cursor:pointer;border-bottom:1px solid var(--c-border);font-size:13px }
.ac-item:hover,.ac-item.selected { background:var(--c-bg) }
/* ── Barcode/item search ── */
#barcode_field { font-size:15px;font-weight:600;width:100%;border:2px solid var(--c-primary,#4f46e5);border-radius:var(--radius);padding:9px 12px }
#barcode_field:focus { outline:none;box-shadow:0 0 0 3px rgba(79,70,229,.18) }
#item_dropdown { background:var(--c-card);border:1px solid var(--c-border);border-radius:var(--radius);box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1000;max-height:320px;overflow-y:auto;margin-top:4px }
.item-row { padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center }
.item-row:hover { background:var(--c-bg) }
#found_qty, #found_price { border:2px solid var(--c-income,#16a34a);border-radius:var(--radius);padding:6px 8px }
#found_qty:focus, #found_price:focus { outline:none;box-shadow:0 0 0 3px rgba(22,163,74,.18) }
</style>

<form method="post" id="invoice_form">
<!-- ── Customer Section ─────────────────────────────────────── -->
<div class="card">
  <div class="card-title">👤 Customer</div>
  <div class="form-grid">
    <div class="form-group" style="position:relative;grid-column:1/-1">
      <label>Search Customer (name / phone)</label>
      <input type="text" id="cust_search_input" placeholder="Type to search existing customers…" autocomplete="off"
             oninput="custSearch(this.value)" onfocus="if(this.value.length>0)custSearch(this.value)">
      <div class="ac-dropdown" id="cust_dropdown"></div>
      <input type="hidden" name="customer_id"    id="f_customer_id"    value="<?= h($inv['customer_id']??'') ?>">
      <input type="hidden" name="customer_name"  id="f_customer_name"  value="<?= h($inv['customer_name']??'') ?>">
      <input type="hidden" name="customer_phone" id="f_customer_phone" value="">
      <input type="hidden" name="customer_email" id="f_customer_email" value="">
      <div id="cust_tag" style="margin-top:6px;font-size:13px;color:var(--c-income);display:none">
        ✅ <span id="cust_tag_name"></span> — <a href="#" onclick="clearCustomer();return false" style="color:var(--c-expense);font-size:12px">✕ Change</a>
      </div>
    </div>
  </div>

  <!-- New customer quick-add panel -->
  <div id="cust_panel">
    <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:var(--c-warn,#f59e0b)">⚠ Customer not found — Add new:</div>
    <div class="form-grid">
      <div class="form-group" style="margin-bottom:8px">
        <label>Name *</label>
        <input type="text" id="new_cust_name" placeholder="Full name">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Phone</label>
        <input type="text" id="new_cust_phone" placeholder="01XXXXXXXXX">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Email</label>
        <input type="email" id="new_cust_email" placeholder="email@example.com">
      </div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="saveNewCustomer()">💾 Save Customer & Use</button>
    <button type="button" class="btn btn-outline btn-sm" onclick="useWalkin()">Walk-in (no save)</button>
  </div>
</div>

<!-- ── Invoice Meta ─────────────────────────────────────────── -->
<div class="card">
  <div class="form-grid">
    <div class="form-group">
      <label>Invoice Date</label>
      <input type="date" name="date" required value="<?= h($inv['date']??$today) ?>">
    </div>
    <div class="form-group">
      <label>Due Date</label>
      <input type="date" name="due_date" value="<?= h($inv['due_date']??'') ?>">
    </div>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <?php foreach (['draft','unpaid','partial','paid','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($inv['status']??'unpaid')==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Amount Paid (<?= $currency_sym ?>)</label>
      <input type="number" step="0.01" name="paid" value="<?= h($inv['paid']??'0') ?>">
    </div>
    <div class="form-group">
      <label>Payment Method</label>
      <select name="payment_method" id="f_payment_method">
        <?php
        $pm_options = ['cash'=>'💵 Cash','card'=>'💳 Card','nagad'=>'📲 Nagad','bkash'=>'💚 bKash','upay'=>'🪙 UPay','bank'=>'🏦 Bank Transfer','cheque'=>'🏦 Cheque','other'=>'💰 Other'];
        $cur_pm = $inv['payment_method'] ?? '';
        foreach ($pm_options as $val => $label):
        ?>
          <option value="<?= $val ?>" <?= $cur_pm===$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>

<!-- ── Barcode / Item Search ────────────────────────────────── -->
<div class="card" style="padding:16px;border:2px solid var(--c-primary,#4f46e5)">
  <div class="card-title" style="margin-bottom:8px">🔍 Scan / Search Item</div>

  <!-- Step 1: Code Entry -->
  <div id="step_code">
    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Enter Barcode / SKU / Item Name</label>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" id="barcode_field"
             placeholder="📷 Scan barcode or type name / SKU…"
             autocomplete="off"
             oninput="itemSearch(this.value)"
             onkeydown="handleBarcodeKey(event)"
             style="flex:1">
      <span id="search_spinner" style="display:none;color:var(--c-muted);font-size:18px">⏳</span>
    </div>
    <div id="item_dropdown"></div>
    <small style="color:var(--c-muted);margin-top:4px;display:block">
      Exact barcode match adds item instantly. Press <kbd>Enter</kbd> to pick the first result.
    </small>
  </div>

  <!-- Step 2: Found — show item name & confirm -->
  <div id="step_found" style="display:none;margin-top:8px;padding:12px;background:var(--c-bg);border:1px solid var(--c-income,#16a34a);border-radius:var(--radius)">
    <div style="font-size:13px;font-weight:700;color:var(--c-income,#16a34a);margin-bottom:8px">✅ Item Found</div>
    <div style="display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:end">
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Item Name</label>
        <div id="found_item_name" style="font-size:15px;font-weight:700;padding:6px 0"></div>
      </div>
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Qty</label>
        <input type="number" id="found_qty" value="1" min="0.001" step="0.001"
               style="width:72px;font-size:15px;font-weight:700">
      </div>
      <div>
        <label style="font-size:12px;color:var(--c-muted)">Price (<?= $currency_sym ?>)</label>
        <input type="number" id="found_price" value="0.00" step="0.01"
               style="width:96px;font-size:15px;font-weight:700">
      </div>
    </div>
    <div id="found_stock_info" style="font-size:12px;margin-top:4px;color:var(--c-muted)"></div>
    <div style="margin-top:10px;display:flex;gap:8px">
      <button type="button" class="btn btn-primary btn-sm" id="btn_add_to_invoice">➕ Add to Invoice</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="resetSearch()">✕ Cancel</button>
    </div>
  </div>

  <!-- Step 3: Not Found — prompt to add to inventory -->
  <div id="new_item_panel" style="display:none;margin-top:8px;padding:14px;background:var(--c-bg);border:1px solid var(--c-warn,#f59e0b);border-radius:var(--radius)">
    <div style="font-size:13px;font-weight:700;color:var(--c-warn,#f59e0b);margin-bottom:10px">
      ⚠ "<span id="not_found_code"></span>" not found in inventory — Add it?
    </div>
    <div class="form-grid">
      <div class="form-group" style="margin-bottom:8px">
        <label>Item Name *</label>
        <input type="text" id="new_item_name" placeholder="Item name">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Barcode / SKU</label>
        <input type="text" id="new_item_sku" placeholder="Barcode">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Unit Price (<?= $currency_sym ?>)</label>
        <input type="number" id="new_item_price" step="0.01" placeholder="0.00">
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Unit</label>
        <select id="new_item_unit">
          <?php foreach (['pcs','kg','g','litre','ml','box','dozen','pack','bottle','bag'] as $u): ?>
            <option value="<?= $u ?>"><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:8px">
        <label>Category</label>
        <input type="text" id="new_item_cat" placeholder="Optional">
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-primary btn-sm" onclick="saveNewItem()">💾 Save to Inventory & Add</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="addManualItem()">Add Without Saving</button>
      <button type="button" class="btn btn-danger btn-sm" onclick="resetSearch()">✕ Cancel</button>
    </div>
  </div>
</div>

<!-- ── Line Items Table ─────────────────────────────────────── -->
<div class="card">
  <div class="card-title">Line Items</div>
  <div class="table-wrap" style="overflow-x:auto">
    <table id="items_table" style="width:100%;table-layout:fixed">
      <thead>
        <tr>
          <th style="width:40%">Description</th>
          <th style="width:12%">Qty</th>
          <th style="width:18%">Unit Price (<?= $currency_sym ?>)</th>
          <th style="width:16%;text-align:right">Total</th>
          <th style="width:8%;text-align:center">Stock</th>
          <th style="width:6%;text-align:center"></th>
        </tr>
      </thead>
      <tbody id="items_body">
      <?php if ($items): foreach ($items as $it): ?>
        <tr>
          <td>
            <input type="hidden" name="items[item_id][]" value="<?= h($it['item_id'] ?? '') ?>">
            <input type="hidden" name="items[vat_pct][]" value="<?= h($it['vat_pct'] ?? 0) ?>">
            <input name="items[description][]" value="<?= h($it['description']) ?>" required style="width:100%;box-sizing:border-box">
          </td>
          <td><input name="items[qty][]" type="number" step="0.001" value="<?= h($it['qty']) ?>" style="width:100%;box-sizing:border-box" oninput="calcItems()"></td>
          <td><input name="items[unit_price][]" type="number" step="0.01" value="<?= h($it['unit_price']) ?>" style="width:100%;box-sizing:border-box" oninput="calcItems()"></td>
          <td class="item-total" style="text-align:right;font-weight:600;white-space:nowrap"><?= $currency_sym.number_format($it['total'],2) ?></td>
          <td class="item-stock" style="text-align:center;font-size:11px;color:var(--c-muted)">—</td>
          <td style="text-align:center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcItems()">✕</button></td>
        </tr>
      <?php endforeach; else: ?>
        <tr>
          <td>
            <input type="hidden" name="items[item_id][]" value="">
            <input type="hidden" name="items[vat_pct][]" value="0">
            <input name="items[description][]" placeholder="Item description" required style="width:100%;box-sizing:border-box">
          </td>
          <td><input name="items[qty][]" type="number" step="0.001" value="1" style="width:100%;box-sizing:border-box" oninput="calcItems()"></td>
          <td><input name="items[unit_price][]" type="number" step="0.01" value="0.00" style="width:100%;box-sizing:border-box" oninput="calcItems()"></td>
          <td class="item-total" style="text-align:right;font-weight:600;white-space:nowrap">0.00</td>
          <td class="item-stock" style="text-align:center;font-size:11px;color:var(--c-muted)">—</td>
          <td style="text-align:center"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove();calcItems()">✕</button></td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <button type="button" class="btn btn-outline" style="margin-top:10px" id="btn_add_line">+ Add Line</button>
</div>

<!-- ── Totals + Notes ───────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr minmax(0,300px);gap:20px;align-items:start">
  <div class="card" style="min-width:0">
    <div class="card-title">Notes</div>
    <textarea name="notes" rows="4" placeholder="Optional notes or payment instructions..." style="width:100%;box-sizing:border-box"><?= h($inv['notes']??'') ?></textarea>
  </div>
  <div class="card" style="min-width:0;overflow:hidden">
    <table class="totals-table" style="width:100%;table-layout:fixed">
      <col style="width:55%"><col style="width:45%">
      <tr>
        <td>Subtotal</td>
        <td style="text-align:right;white-space:nowrap"><?= $currency_sym ?> <span id="disp_subtotal"><?= number_format($inv['subtotal']??0,2) ?></span></td>
      </tr>
      <tr>
        <td><?= TAX_LABEL ?> %: <input type="number" step="0.01" id="tax_pct" name="tax_pct" value="<?= h($inv['tax_pct']??'0') ?>" style="width:52px" oninput="calcItems()"></td>
        <td style="text-align:right;white-space:nowrap"><?= $currency_sym ?> <span id="disp_tax"><?= number_format($inv['tax_amount']??0,2) ?></span></td>
      </tr>
      <tr>
        <td>Discount</td>
        <td style="text-align:right"><input type="number" step="0.01" id="discount" name="discount" value="<?= h($inv['discount']??'0') ?>" style="width:80px;text-align:right;box-sizing:border-box" oninput="calcItems()"></td>
      </tr>
      <tr class="grand-total">
        <td><strong>Total</strong></td>
        <td style="text-align:right;white-space:nowrap"><strong><?= $currency_sym ?> <span id="disp_total"><?= number_format($inv['total']??0,2) ?></span></strong></td>
      </tr>
    </table>
  </div>
</div>

<div class="no-print" style="display:flex;gap:10px;margin-top:8px">
  <button type="submit" class="btn btn-primary">💾 Save Invoice</button>
  <a href="<?= APP_URL ?>/income.php" class="btn btn-outline">Cancel</a>
</div>
</form>

<script>
const APP_URL = '<?= APP_URL ?>';
const CUR     = '<?= addslashes($currency_sym) ?>';

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function showMsg(msg) {
  var d = document.createElement('div');
  d.className = 'alert alert-success';
  d.style.cssText = 'position:fixed;top:70px;right:20px;z-index:9999;padding:10px 18px;border-radius:8px;font-size:13px;max-width:320px;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7';
  d.textContent = msg;
  document.body.appendChild(d);
  setTimeout(function(){ d.remove(); }, 3000);
}

// ═══════════════════════════════════════════════════════════════
// CUSTOMER AUTOCOMPLETE
// ═══════════════════════════════════════════════════════════════
var custTimer = null;
var custResults = [];

function custSearch(q) {
  clearTimeout(custTimer);
  var dd = document.getElementById('cust_dropdown');
  if (!q.trim()) { dd.style.display='none'; return; }
  custTimer = setTimeout(function() {
    fetch(APP_URL + '/api-customers-search.php?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        custResults = data;
        dd.innerHTML = '';
        if (!data.length) {
          var item = document.createElement('div');
          item.className = 'ac-item';
          item.style.color = 'var(--c-muted)';
          item.textContent = 'No customers found — ';
          var cAddLink = document.createElement('a');
          cAddLink.href = '#';
          cAddLink.textContent = '➕ Add';
          (function(qq){ cAddLink.addEventListener('click', function(e){ e.preventDefault(); showNewCustPanel(qq); }); })(q);
          item.appendChild(cAddLink);
          dd.appendChild(item);
          document.getElementById('cust_panel').style.display = 'block';
          document.getElementById('new_cust_name').value = q;
        } else {
          data.forEach(function(c, i){
            var item = document.createElement('div');
            item.className = 'ac-item';
            item.innerHTML = '<strong>' + esc(c.name) + '</strong>' + (c.phone ? ' &nbsp;📞 ' + esc(c.phone) : '') + (c.email ? '<br><small style="color:var(--c-muted)">' + esc(c.email) + '</small>' : '');
            item.addEventListener('click', function(){ selectCustomer(i); });
            dd.appendChild(item);
          });
          var addNew = document.createElement('div');
          addNew.className = 'ac-item';
          addNew.style.color = 'var(--c-income)';
          addNew.textContent = '➕ Add new customer…';
          addNew.addEventListener('click', function(){ showNewCustPanel(q); });
          dd.appendChild(addNew);
        }
        dd.style.display = 'block';
      });
  }, 200);
}

function selectCustomer(idx) {
  var c = custResults[idx];
  document.getElementById('f_customer_id').value    = c.id;
  document.getElementById('f_customer_name').value  = c.name;
  document.getElementById('f_customer_phone').value = c.phone || '';
  document.getElementById('f_customer_email').value = c.email || '';
  document.getElementById('cust_search_input').value = c.name;
  document.getElementById('cust_dropdown').style.display = 'none';
  document.getElementById('cust_panel').style.display    = 'none';
  document.getElementById('cust_tag_name').textContent = c.name + (c.phone ? ' · ' + c.phone : '');
  document.getElementById('cust_tag').style.display = 'block';
  document.getElementById('barcode_field').focus();
}

function showNewCustPanel(name) {
  document.getElementById('cust_dropdown').style.display = 'none';
  document.getElementById('cust_panel').style.display    = 'block';
  document.getElementById('new_cust_name').value = name || '';
  document.getElementById('new_cust_phone').focus();
}

function saveNewCustomer() {
  var name  = document.getElementById('new_cust_name').value.trim();
  var phone = document.getElementById('new_cust_phone').value.trim();
  var email = document.getElementById('new_cust_email').value.trim();
  if (!name) { alert('Customer name is required'); return; }
  var fd = new FormData();
  fd.append('name', name); fd.append('phone', phone); fd.append('email', email);
  fetch(APP_URL + '/api-save-customer.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.error) { alert(data.error); return; }
      document.getElementById('f_customer_id').value    = data.id;
      document.getElementById('f_customer_name').value  = data.name;
      document.getElementById('f_customer_phone').value = data.phone || '';
      document.getElementById('f_customer_email').value = data.email || '';
      document.getElementById('cust_search_input').value = data.name;
      document.getElementById('cust_panel').style.display = 'none';
      document.getElementById('cust_tag_name').textContent = data.name + (data.phone ? ' · ' + data.phone : '') + (data.saved ? ' ✓ Saved' : ' (exists)');
      document.getElementById('cust_tag').style.display = 'block';
      document.getElementById('barcode_field').focus();
      showMsg(data.saved ? 'Customer "' + data.name + '" saved!' : 'Existing customer used.');
    });
}

function useWalkin() {
  document.getElementById('f_customer_id').value   = '';
  document.getElementById('f_customer_name').value = 'Walk-in Customer';
  document.getElementById('cust_panel').style.display = 'none';
  document.getElementById('cust_tag_name').textContent = 'Walk-in Customer';
  document.getElementById('cust_tag').style.display = 'block';
  document.getElementById('barcode_field').focus();
}

function clearCustomer() {
  document.getElementById('f_customer_id').value   = '';
  document.getElementById('f_customer_name').value = '';
  document.getElementById('cust_search_input').value = '';
  document.getElementById('cust_tag').style.display  = 'none';
  document.getElementById('cust_panel').style.display = 'none';
  document.getElementById('cust_search_input').focus();
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('#cust_search_input') && !e.target.closest('#cust_dropdown'))
    document.getElementById('cust_dropdown').style.display = 'none';
});

// ═══════════════════════════════════════════════════════════════
// BARCODE / ITEM SEARCH
// ═══════════════════════════════════════════════════════════════
var itemTimer   = null;
var itemResults = [];
var foundItem   = null;
var lastQuery   = '';

function itemSearch(q) {
  clearTimeout(itemTimer);
  var dd = document.getElementById('item_dropdown');
  q = q.trim();
  if (!q) { dd.style.display = 'none'; return; }
  lastQuery = q;
  document.getElementById('search_spinner').style.display = 'inline';

  itemTimer = setTimeout(function() {
    fetch(APP_URL + '/api-items-search.php?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(data){
        document.getElementById('search_spinner').style.display = 'none';
        itemResults = data;

        // Exact SKU match → instantly show Found panel
        var exact = null;
        for (var i = 0; i < data.length; i++) {
          if (data[i].sku && data[i].sku.toLowerCase() === q.toLowerCase()) {
            exact = data[i]; break;
          }
        }
        if (exact) { dd.style.display = 'none'; showFoundPanel(exact); return; }

        dd.innerHTML = '';
        if (!data.length) {
          var row = document.createElement('div');
          row.className = 'item-row';
          row.style.color = 'var(--c-muted)';
          row.innerHTML = 'No items found for "<strong>' + esc(q) + '</strong>"';
          var link = document.createElement('a');
          link.href = '#';
          link.style.cssText = 'float:right;color:var(--c-warn)';
          link.textContent = '➕ Add to inventory';
          link.addEventListener('click', function(e){ e.preventDefault(); showNewItemPanel(q); });
          row.appendChild(link);
          dd.appendChild(row);
          dd.style.display = 'block';
          showNewItemPanel(q);
          return;
        }

        data.forEach(function(it, idx){
          var row = document.createElement('div');
          row.className = 'item-row';

          var left = document.createElement('div');
          var nameEl = document.createElement('strong');
          nameEl.textContent = it.name;
          left.appendChild(nameEl);
          if (it.sku) {
            var skuEl = document.createElement('span');
            skuEl.style.cssText = 'font-size:11px;color:var(--c-muted);font-family:monospace';
            skuEl.textContent = ' [' + it.sku + ']';
            left.appendChild(skuEl);
          }
          var catEl = document.createElement('small');
          catEl.style.color = 'var(--c-muted)';
          catEl.textContent = it.category || '';
          left.appendChild(document.createElement('br'));
          left.appendChild(catEl);

          var right = document.createElement('div');
          right.style.cssText = 'text-align:right;min-width:90px';
          var priceEl = document.createElement('div');
          priceEl.style.fontWeight = '700';
          priceEl.textContent = CUR + parseFloat(it.unit_price).toFixed(2);
          var stockEl = document.createElement('div');
          stockEl.style.cssText = 'font-size:11px;color:' + (it.quantity <= 0 ? 'var(--c-expense)' : 'var(--c-income)');
          stockEl.textContent = it.quantity <= 0 ? '🚫 Out of stock' : '✓ ' + parseFloat(it.quantity).toFixed(2) + ' ' + it.unit;
          right.appendChild(priceEl);
          right.appendChild(stockEl);

          row.appendChild(left);
          row.appendChild(right);
          row.addEventListener('click', (function(item){ return function(){ pickFromDropdown(item); }; })(it));
          dd.appendChild(row);
        });

        var addNew = document.createElement('div');
        addNew.className = 'item-row';
        addNew.style.cssText = 'color:var(--c-income);font-size:13px';
        addNew.textContent = '➕ New item not listed above…';
        addNew.addEventListener('click', function(){ showNewItemPanel(q); });
        dd.appendChild(addNew);
        dd.style.display = 'block';
      })
      .catch(function(){ document.getElementById('search_spinner').style.display = 'none'; });
  }, 220);
}

function pickFromDropdown(it) {
  document.getElementById('item_dropdown').style.display = 'none';
  showFoundPanel(it);
}

function showFoundPanel(it) {
  foundItem = it;
  document.getElementById('item_dropdown').style.display = 'none';
  document.getElementById('new_item_panel').style.display = 'none';
  document.getElementById('found_item_name').textContent = it.name + (it.sku ? '  [' + it.sku + ']' : '');
  document.getElementById('found_qty').value   = 1;
  document.getElementById('found_price').value = parseFloat(it.unit_price).toFixed(2);
  var stock = parseFloat(it.quantity);
  var stockEl = document.getElementById('found_stock_info');
  if (stock <= 0) {
    stockEl.innerHTML = '<span style="color:var(--c-expense)">🚫 Out of stock</span>';
  } else {
    stockEl.textContent = '✓ ' + stock.toFixed(2) + ' ' + (it.unit||'') + ' in stock';
    stockEl.style.color = 'var(--c-income)';
  }
  document.getElementById('step_found').style.display = 'block';
  document.getElementById('found_qty').select();
}

function confirmAddFound() {
  if (!foundItem) return;
  var qty   = parseFloat(document.getElementById('found_qty').value)   || 1;
  var price = parseFloat(document.getElementById('found_price').value) || 0;
  addRowToTable(foundItem.name, qty, price, parseFloat(foundItem.quantity), foundItem.unit, foundItem.id, foundItem.vat_pct||0);
  showMsg('Added: ' + foundItem.name);
  resetSearch();
}

function handleBarcodeKey(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    var dd = document.getElementById('item_dropdown');
    if (dd.style.display !== 'none' && itemResults.length > 0) {
      pickFromDropdown(itemResults[0]);
    } else if (lastQuery && !itemResults.length) {
      showNewItemPanel(lastQuery);
    }
  }
  if (e.key === 'Escape') { resetSearch(); }
}

function showNewItemPanel(q) {
  document.getElementById('item_dropdown').style.display = 'none';
  document.getElementById('step_found').style.display    = 'none';
  document.getElementById('new_item_panel').style.display = 'block';
  document.getElementById('not_found_code').textContent   = q;
  var isBarcode = /^\d{6,}$/.test(q);
  document.getElementById('new_item_name').value = isBarcode ? '' : q;
  document.getElementById('new_item_sku').value  = isBarcode ? q  : '';
  if (isBarcode) document.getElementById('new_item_name').focus();
  else           document.getElementById('new_item_price').focus();
}

function resetSearch() {
  foundItem   = null;
  itemResults = [];
  lastQuery   = '';
  document.getElementById('barcode_field').value          = '';
  document.getElementById('item_dropdown').style.display   = 'none';
  document.getElementById('step_found').style.display      = 'none';
  document.getElementById('new_item_panel').style.display  = 'none';
  document.getElementById('barcode_field').focus();
}

function saveNewItem() {
  var name  = document.getElementById('new_item_name').value.trim();
  var sku   = document.getElementById('new_item_sku').value.trim();
  var price = parseFloat(document.getElementById('new_item_price').value) || 0;
  var unit  = document.getElementById('new_item_unit').value;
  var cat   = document.getElementById('new_item_cat').value.trim();
  if (!name) { alert('Item name is required'); return; }
  var fd = new FormData();
  fd.append('name', name); fd.append('sku', sku);
  fd.append('unit_price', price); fd.append('unit', unit); fd.append('category', cat);
  fetch(APP_URL + '/api-save-item.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (data.error) { alert(data.error); return; }
      addRowToTable(data.name, 1, data.unit_price, data.quantity, data.unit, data.id, data.vat_pct||0);
      resetSearch();
      showMsg(data.saved ? '"' + data.name + '" saved to inventory & added!' : '"' + data.name + '" already existed — added to invoice');
    });
}

function addManualItem() {
  var name  = document.getElementById('new_item_name').value.trim();
  var price = parseFloat(document.getElementById('new_item_price').value) || 0;
  if (!name) { alert('Item name is required'); return; }
  addRowToTable(name, 1, price, null, '', '', 0);
  resetSearch();
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('#step_code') && !e.target.closest('#item_dropdown') &&
      !e.target.closest('#step_found') && !e.target.closest('#new_item_panel'))
    document.getElementById('item_dropdown').style.display = 'none';
});

// ═══════════════════════════════════════════════════════════════
// LINE ITEM TABLE
// ═══════════════════════════════════════════════════════════════
function addItemRow() {
  var tbody = document.getElementById('items_body');
  var tr = document.createElement('tr');

  // Hidden: item_id (for stock deduction)
  var hidId = document.createElement('input');
  hidId.type = 'hidden';
  hidId.name = 'items[item_id][]';
  hidId.value = '';
  hidId.className = 'row-item-id';

  // Hidden: vat_pct (per-item VAT)
  var hidVat = document.createElement('input');
  hidVat.type = 'hidden';
  hidVat.name = 'items[vat_pct][]';
  hidVat.value = '0';
  hidVat.className = 'row-vat-pct';

  // Description
  var td1 = document.createElement('td');
  td1.appendChild(hidId);
  td1.appendChild(hidVat);
  var inp1 = document.createElement('input');
  inp1.name = 'items[description][]';
  inp1.placeholder = 'Description';
  inp1.required = true;
  inp1.style.cssText = 'width:100%;box-sizing:border-box';
  td1.appendChild(inp1);

  // Qty
  var td2 = document.createElement('td');
  var inp2 = document.createElement('input');
  inp2.name = 'items[qty][]';
  inp2.type = 'number';
  inp2.step = '0.001';
  inp2.value = '1';
  inp2.style.cssText = 'width:100%;box-sizing:border-box';
  inp2.addEventListener('input', calcItems);
  td2.appendChild(inp2);

  // Unit price
  var td3 = document.createElement('td');
  var inp3 = document.createElement('input');
  inp3.name = 'items[unit_price][]';
  inp3.type = 'number';
  inp3.step = '0.01';
  inp3.value = '0.00';
  inp3.style.cssText = 'width:100%;box-sizing:border-box';
  inp3.addEventListener('input', calcItems);
  td3.appendChild(inp3);

  // Total
  var td4 = document.createElement('td');
  td4.className = 'item-total';
  td4.style.cssText = 'text-align:right;font-weight:600;white-space:nowrap';
  td4.textContent = '0.00';

  // Stock
  var td5 = document.createElement('td');
  td5.className = 'item-stock';
  td5.style.cssText = 'text-align:center;font-size:11px;color:var(--c-muted)';
  td5.textContent = '—';

  // Remove button
  var td6 = document.createElement('td');
  td6.style.textAlign = 'center';
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn btn-danger btn-sm';
  btn.textContent = '✕';
  btn.addEventListener('click', function() {
    tr.remove();
    calcItems();
  });
  td6.appendChild(btn);

  tr.appendChild(td1);
  tr.appendChild(td2);
  tr.appendChild(td3);
  tr.appendChild(td4);
  tr.appendChild(td5);
  tr.appendChild(td6);
  tbody.appendChild(tr);
  calcItems();
  return tr;
}

function addRowToTable(name, qty, price, stockQty, unit, itemId, vatPct) {
  var row = addItemRow();
  row.querySelector('[name="items[description][]"]').value    = name;
  row.querySelector('[name="items[qty][]"]').value            = qty;
  row.querySelector('[name="items[unit_price][]"]').value     = parseFloat(price).toFixed(2);
  // Set item_id and vat_pct for stock deduction
  var idInput  = row.querySelector('.row-item-id');
  var vatInput = row.querySelector('.row-vat-pct');
  if (idInput)  idInput.value  = itemId  || '';
  if (vatInput) vatInput.value = vatPct  || '0';
  var sc = row.querySelector('.item-stock');
  if (stockQty === null || stockQty === undefined) {
    sc.textContent = '—';
  } else if (stockQty <= 0) {
    sc.innerHTML = '<span style="color:var(--c-expense)">0</span>';
  } else {
    sc.textContent = stockQty.toFixed(1) + ' ' + (unit||'');
    sc.style.color = 'var(--c-income)';
  }
  calcItems();
}

function calcItems() {
  var sub = 0;
  document.querySelectorAll('#items_body tr').forEach(function(tr) {
    var qEl = tr.querySelector('[name="items[qty][]"]');
    var pEl = tr.querySelector('[name="items[unit_price][]"]');
    var q = parseFloat(qEl ? qEl.value : 0) || 0;
    var p = parseFloat(pEl ? pEl.value : 0) || 0;
    var t = q * p;
    var tc = tr.querySelector('.item-total');
    if (tc) tc.textContent = t.toFixed(2);
    sub += t;
  });
  var taxEl  = document.getElementById('tax_pct');
  var discEl = document.getElementById('discount');
  var subEl  = document.getElementById('disp_subtotal');
  var taxDEl = document.getElementById('disp_tax');
  var totEl  = document.getElementById('disp_total');
  var tax_pct  = taxEl  ? (parseFloat(taxEl.value)  || 0) : 0;
  var discount = discEl ? (parseFloat(discEl.value) || 0) : 0;
  var tax_amt  = sub * tax_pct / 100;
  var total    = sub + tax_amt - discount;
  if (subEl)  subEl.textContent  = sub.toFixed(2);
  if (taxDEl) taxDEl.textContent = tax_amt.toFixed(2);
  if (totEl)  totEl.textContent  = total.toFixed(2);
}

// ── Init ──────────────────────────────────────────────────────
window.addEventListener('load', function() {
  var custId   = document.getElementById('f_customer_id').value;
  var custName = document.getElementById('f_customer_name').value;
  if (custId && custName) {
    document.getElementById('cust_search_input').value = custName;
    document.getElementById('cust_tag_name').textContent = custName;
    document.getElementById('cust_tag').style.display = 'block';
  }
  // Wire Add to Invoice button
  var btnAdd = document.getElementById('btn_add_to_invoice');
  if (btnAdd) btnAdd.addEventListener('click', confirmAddFound);
  // Wire + Add Line button
  var btnLine = document.getElementById('btn_add_line');
  if (btnLine) btnLine.addEventListener('click', addItemRow);
  // Wire found_qty Enter key
  var fq = document.getElementById('found_qty');
  if (fq) fq.addEventListener('keydown', function(e){ if (e.key==='Enter'){e.preventDefault();confirmAddFound();} });
  // Wire existing PHP-rendered delete buttons
  document.querySelectorAll('#items_body .btn-danger').forEach(function(btn){
    btn.addEventListener('click', function(){ this.closest('tr').remove(); calcItems(); });
    btn.removeAttribute('onclick');
  });
  document.getElementById('barcode_field').focus();
  calcItems();
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
