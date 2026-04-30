<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$tid = tid(); $bid = brid();
$page_title = 'POS — Point of Sale';

$cs = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
$cs->execute([$tid]); $cs = $cs->fetch() ?: [];
$currency_sym = !empty($cs['currency']) ? $cs['currency'] : CURRENCY;

$cats = db()->prepare('SELECT DISTINCT category FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND category IS NOT NULL AND category != "" ORDER BY category');
$cats->execute([$tid, $bid]);
$categories = $cats->fetchAll(PDO::FETCH_COLUMN);

$branch_name = '';
foreach (get_branches() as $b) {
    if ($b['id'] == brid()) { $branch_name = $b['name']; break; }
}

ob_start();
?>
<style>
.content{padding:0!important;overflow:hidden}
body{overflow:hidden}

/* ═══ CUSTOMER DISPLAY BAR ═══════════════════════════════════ */
.pos-customer-bar{
  background:linear-gradient(135deg,#0f1117 0%,#1a1d2e 100%);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 20px;height:72px;flex-shrink:0;
  border-bottom:2px solid #2e3248;gap:20px;
}
.pcb-left{display:flex;flex-direction:column;gap:2px;min-width:200px}
.pcb-branch{font-size:12px;color:#6b7280;font-weight:600;letter-spacing:.05em;text-transform:uppercase}
.pcb-cust-name{font-size:20px;font-weight:800;color:#fff;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px}
.pcb-center{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center}
.pcb-total-label{font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:2px}
.pcb-total-amount{font-size:42px;font-weight:900;color:#fbbf24;letter-spacing:2px;line-height:1;font-variant-numeric:tabular-nums;text-shadow:0 0 30px rgba(251,191,36,.3)}
.pcb-right{display:flex;flex-direction:column;align-items:flex-end;gap:2px}
.pcb-clock-time{font-size:28px;font-weight:800;color:#fff;font-variant-numeric:tabular-nums;letter-spacing:2px;line-height:1}
.pcb-clock-date{font-size:11px;color:#6b7280}

/* ═══ MAIN ═══════════════════════════════════════════════════ */
.pos-main{display:flex;height:calc(100vh - 56px - 72px);overflow:hidden;position:relative}

/* ═══ DIVIDER ════════════════════════════════════════════════ */
.pos-divider{width:6px;background:#dde1e9;cursor:col-resize;flex-shrink:0;position:relative;z-index:10;transition:background .15s;display:flex;align-items:center;justify-content:center}
.pos-divider:hover,.pos-divider.dragging{background:#4f46e5}
.pos-divider::after{content:'⋮⋮';font-size:14px;color:#9ca3af;line-height:1;letter-spacing:-2px}
.pos-divider:hover::after,.pos-divider.dragging::after{color:#fff}

/* ═══ LEFT PANEL ═════════════════════════════════════════════ */
.pos-left{display:flex;flex-direction:column;background:#fff;overflow:hidden;width:520px;min-width:320px;max-width:75%;flex-shrink:0}
.pos-topbar{display:flex;align-items:center;justify-content:space-between;background:#1a1d2e;padding:7px 12px;gap:6px;flex-shrink:0}
.top-btns{display:flex;gap:5px;flex-wrap:wrap}
.ptb-btn{padding:6px 11px;border-radius:6px;border:none;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:opacity .15s}
.ptb-btn:hover{opacity:.82}
.ptb-red{background:#c81e1e;color:#fff}.ptb-orange{background:#d97706;color:#fff}
.ptb-green{background:#057a55;color:#fff}.ptb-blue{background:#1a56db;color:#fff}
.ptb-dark{background:#374151;color:#fff}.ptb-purple{background:#7c3aed;color:#fff}

.pos-search-row{display:flex;gap:8px;padding:10px 12px;background:#f8f9fb;border-bottom:1px solid #dde1e9;align-items:center;flex-shrink:0}
.pos-cust-wrap{position:relative;flex:1 1 180px;min-width:0}
.pos-cust-wrap input{width:100%;padding:9px 11px;border:1.5px solid #dde1e9;border-radius:8px;font-size:14px;background:#fff;color:#1a1d23;box-sizing:border-box;font-weight:600}
.pos-cust-wrap input:focus{outline:none;border-color:#1a56db}
.new-cust-btn{background:#1a56db;color:#fff;border:none;border-radius:50%;width:30px;height:30px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.pos-barcode-wrap{position:relative;flex:1 1 240px;min-width:0;display:flex;align-items:center;gap:6px}
#barcode_input{flex:1;padding:9px 11px;border:2px solid #1a56db;border-radius:8px;font-size:14px;font-weight:600;background:#fff;color:#1a1d23}
#barcode_input:focus{outline:none;box-shadow:0 0 0 3px rgba(26,86,219,.15)}
.bc-add-btn{background:#1a56db;color:#fff;border:none;border-radius:50%;width:30px;height:30px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}

.pos-meta-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding:8px 12px;background:#f8f9fb;border-bottom:1px solid #dde1e9;flex-shrink:0}
.pos-meta-row select,.pos-meta-row input{width:100%;padding:7px 9px;border:1.5px solid #dde1e9;border-radius:7px;font-size:12px;background:#fff;color:#1a1d23;box-sizing:border-box}

.pos-cart-table-wrap{flex:1;overflow-y:auto;min-height:0}
.pos-cart-table{width:100%;border-collapse:collapse;font-size:15px}
.pos-cart-table thead th{background:#f1f3f7;padding:11px 12px;text-align:left;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#374151;border-bottom:2px solid #dde1e9;position:sticky;top:0;z-index:2}
.pos-cart-table tbody td{padding:10px 12px;border-bottom:1px solid #eef0f5;vertical-align:middle}
.pos-cart-table tbody tr:hover{background:#f5f7ff}
.pos-cart-table .td-name{font-weight:700;font-size:15px}
.pos-cart-table .td-name small{display:block;font-size:12px;color:#6b7280;font-weight:400}
.pos-cart-table .td-qty input{width:68px;text-align:center;border:1.5px solid #dde1e9;border-radius:6px;padding:6px;font-size:15px;font-weight:700;background:#fff}
.pos-cart-table .td-price input{width:88px;text-align:right;border:1.5px solid #dde1e9;border-radius:6px;padding:6px;font-size:15px;background:#fff}
.pos-cart-table .td-total{font-weight:800;text-align:right;color:#1a56db;font-size:16px;white-space:nowrap}
.pos-cart-table .td-del button{background:none;border:none;color:#c81e1e;font-size:20px;cursor:pointer;padding:2px 6px}
.cart-empty-row td{text-align:center;padding:50px 20px;color:#9ca3af;font-size:15px}

.pos-bottom{flex-shrink:0;border-top:2px solid #dde1e9;background:#fff}
.pos-totals-grid{display:grid;grid-template-columns:1fr 1fr;padding:12px 14px 8px;border-bottom:1px solid #eef0f5}
.tg-row{display:flex;justify-content:space-between;padding:4px 8px;font-size:14px}
.tg-row label{color:#6b7280;font-size:13px;font-weight:600;text-transform:uppercase}
.tg-row.tg-total{font-size:15px;font-weight:800;color:#c81e1e;background:#fff8f8;border-radius:6px;padding:6px 8px}
.tg-row input{width:100px;text-align:right;border:1.5px solid #dde1e9;border-radius:6px;padding:4px 8px;font-size:14px;font-weight:700}
.pos-total-bar{background:#1a1d23;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:12px 18px}
.pos-total-bar .tb-label{font-size:14px;font-weight:700;opacity:.75}
.pos-total-bar .tb-value{font-size:28px;font-weight:900;color:#fbbf24;letter-spacing:1px}

.pos-pay-row{display:flex;gap:0;border-bottom:1px solid #eef0f5}
.pay-method-btn{flex:1;padding:11px 6px;border:none;border-right:1px solid #eef0f5;background:#f8f9fb;font-size:13px;font-weight:700;cursor:pointer;transition:all .15s;display:flex;align-items:center;justify-content:center;gap:4px;color:#374151}
.pay-method-btn:last-child{border-right:none}
.pay-method-btn:hover{background:#e8ecff;color:#1a56db}
.pay-method-btn.active{background:#1a56db;color:#fff}
.pos-action-row{display:grid;grid-template-columns:repeat(7,1fr);gap:0;border-bottom:1px solid #eef0f5}
.pos-act-btn{padding:10px 4px;border:none;border-right:1px solid #eef0f5;font-size:11.5px;font-weight:700;cursor:pointer;text-align:center;transition:opacity .15s}
.pos-act-btn:last-child{border-right:none}
.pos-act-btn:hover{opacity:.82}
.pos-checkout-wrap{padding:10px 12px;display:flex;gap:8px}
.btn-pos-checkout{flex:1;padding:15px;background:linear-gradient(135deg,#057a55,#046243);color:#fff;border:none;border-radius:10px;font-size:17px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s}
.btn-pos-checkout:hover{background:linear-gradient(135deg,#046243,#034d36)}
.btn-pos-checkout:disabled{background:#9ca3af;cursor:not-allowed}
.btn-pos-clear{padding:15px 18px;background:#fff;color:#c81e1e;border:2px solid #c81e1e;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:all .15s}
.btn-pos-clear:hover{background:#c81e1e;color:#fff}

/* ═══ RIGHT ══════════════════════════════════════════════════ */
.pos-right{flex:1;display:flex;flex-direction:column;background:#eef0f5;overflow:hidden;min-width:280px}
.pos-filter-bar{display:flex;align-items:center;gap:8px;padding:9px 14px;background:#fff;border-bottom:1px solid #dde1e9;flex-shrink:0;flex-wrap:wrap}
.pos-filter-bar select{padding:7px 10px;border:1.5px solid #dde1e9;border-radius:8px;font-size:13px;background:#fff;color:#1a1d23;min-width:140px}
.pos-filter-bar .refresh-btn{padding:7px 14px;border:1.5px solid #dde1e9;border-radius:8px;font-size:13px;font-weight:600;background:#fff;color:#374151;cursor:pointer}
.pos-filter-bar .refresh-btn:hover{background:#f5f7ff;border-color:#1a56db}
.pos-filter-bar .recent-btn{padding:7px 14px;border:1.5px solid #374151;border-radius:8px;font-size:13px;font-weight:600;background:#1a1d23;color:#fff;cursor:pointer;margin-left:auto}
.item-count{font-size:12px;color:#6b7280}
.pos-cat-tabs{display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;background:#fff;border-bottom:1px solid #dde1e9;flex-shrink:0}
.pos-cat-tab{padding:5px 16px;border-radius:20px;border:1.5px solid #dde1e9;font-size:12.5px;font-weight:600;cursor:pointer;background:#f8f9fb;color:#374151;transition:all .15s;white-space:nowrap}
.pos-cat-tab:hover{background:#e8ecff;border-color:#1a56db;color:#1a56db}
.pos-cat-tab.active{background:#1a56db;color:#fff;border-color:#1a56db}
.pos-products-grid{flex:1;overflow-y:auto;padding:12px 14px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;align-content:start}
.product-card{background:#fff;border:1.5px solid #dde1e9;border-radius:10px;padding:10px 8px;cursor:pointer;transition:all .15s;text-align:center;position:relative;user-select:none}
.product-card:hover{border-color:#1a56db;box-shadow:0 4px 14px rgba(26,86,219,.15);transform:translateY(-2px)}
.product-card:active{transform:scale(.97)}
.product-card .p-img-wrap{width:72px;height:72px;margin:0 auto 7px;border-radius:8px;overflow:hidden;background:#f0f2f5;display:flex;align-items:center;justify-content:center}
.product-card .p-img-wrap img{width:100%;height:100%;object-fit:cover}
.product-card .p-icon{font-size:30px}
.product-card .p-name{font-size:12px;font-weight:700;color:#1a1d23;line-height:1.3;margin-bottom:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:28px}
.product-card .p-sku{font-size:10px;color:#9ca3af;font-family:monospace;margin-bottom:2px}
.product-card .p-price{font-size:13.5px;font-weight:800;color:#1a56db;margin-bottom:2px}
.product-card .p-stock{font-size:11px;color:#6b7280}
.product-card .p-stock.low{color:#c81e1e;font-weight:700}
.product-card.out-of-stock{opacity:.55;cursor:not-allowed}
.product-card.out-of-stock::after{content:'OUT';position:absolute;top:6px;right:6px;background:#c81e1e;color:#fff;font-size:9px;padding:2px 6px;border-radius:4px;font-weight:800}
.product-card.flash-added{background:#1a56db;border-color:#1a56db}
.product-card.flash-added .p-name,.product-card.flash-added .p-price,.product-card.flash-added .p-stock,.product-card.flash-added .p-sku{color:#fff}
.no-products-msg{grid-column:1/-1;text-align:center;padding:50px 20px;color:#9ca3af;font-size:14px}
.cust-dropdown,.barcode-dropdown{position:absolute;top:calc(100% + 2px);left:0;right:0;background:#fff;border:1px solid #dde1e9;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);z-index:1000;max-height:200px;overflow-y:auto;display:none}
.cust-drop-item,.bc-drop-item{padding:9px 12px;cursor:pointer;font-size:14px;border-bottom:1px solid #f3f4f6;transition:background .1s}
.cust-drop-item:hover,.bc-drop-item:hover{background:#f5f7ff}
.bc-drop-item{display:flex;justify-content:space-between;align-items:center}
.bdi-name{font-size:13px;font-weight:600;color:#1a1d23}.bdi-sku{font-size:11px;color:#9ca3af;font-family:monospace}
.bdi-price{font-size:13px;font-weight:700;color:#1a56db}.bdi-stock{font-size:11px;color:#6b7280}
.pos-cart-table-wrap::-webkit-scrollbar,.pos-products-grid::-webkit-scrollbar{width:5px}
.pos-cart-table-wrap::-webkit-scrollbar-thumb,.pos-products-grid::-webkit-scrollbar-thumb{background:#c0c4cf;border-radius:4px}

/* ═══ THERMAL RECEIPT PRINT ═══════════════════════════════════ */
.receipt-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:10000;align-items:center;justify-content:center}
.receipt-overlay.open{display:flex}
.receipt-preview-box{background:#fff;border-radius:10px;padding:20px;width:340px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.35);position:relative}
.receipt-preview-box .rp-actions{display:flex;gap:8px;margin-bottom:14px}
.rp-actions button{flex:1;padding:9px;border:none;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer}
.rp-print-btn{background:#057a55;color:#fff}
.rp-close-btn{background:#f3f4f6;color:#374151}
.receipt-preview-box .rp-close-x{position:absolute;top:10px;right:12px;background:none;border:none;font-size:20px;cursor:pointer;color:#9ca3af}

@media print {
  body *{visibility:hidden!important}
  #thermal_receipt_content,#thermal_receipt_content *{visibility:visible!important}
  #thermal_receipt_content{
    position:fixed!important;inset:0!important;
    display:flex!important;justify-content:center!important;
    background:#fff!important;padding:0!important;margin:0!important;
  }
  .receipt-overlay,.receipt-overlay *{visibility:hidden!important}
  #thermal_receipt_content .thermal-paper{visibility:visible!important}
}

/* Thermal paper styles */
.thermal-paper{
  width:80mm;max-width:80mm;font-family:'Courier New',Courier,monospace;
  font-size:12px;color:#000;background:#fff;padding:6mm 4mm;margin:0 auto;
  line-height:1.45;
}
.thermal-paper .th-logo{text-align:center;font-size:15px;font-weight:900;letter-spacing:1px;margin-bottom:1mm}
.thermal-paper .th-address{text-align:center;font-size:10px;margin-bottom:1mm}
.thermal-paper .th-phone{text-align:center;font-size:10px;margin-bottom:2mm}
.thermal-paper .th-divider{border:none;border-top:1px dashed #000;margin:2mm 0}
.thermal-paper .th-meta{font-size:10px;margin-bottom:1mm}
.thermal-paper .th-items-header{display:flex;font-size:10px;font-weight:700;border-bottom:1px solid #000;padding-bottom:1mm;margin-bottom:1mm}
.thermal-paper .th-item-row{display:flex;font-size:11px;padding:0.5mm 0;align-items:flex-start}
.thermal-paper .th-item-name{flex:1;word-break:break-word;padding-right:2mm}
.thermal-paper .th-item-qty{width:22mm;text-align:center;flex-shrink:0}
.thermal-paper .th-item-total{width:20mm;text-align:right;flex-shrink:0;font-weight:700}
.thermal-paper .th-totals{margin-top:2mm}
.thermal-paper .th-total-row{display:flex;justify-content:space-between;font-size:11px;padding:0.3mm 0}
.thermal-paper .th-grand{font-size:14px;font-weight:900;border-top:2px solid #000;margin-top:2mm;padding-top:2mm;display:flex;justify-content:space-between}
.thermal-paper .th-pay{font-size:11px;text-align:center;margin-top:2mm;font-weight:700}
.thermal-paper .th-footer{text-align:center;font-size:10px;margin-top:3mm;line-height:1.6}
.thermal-paper .th-barcode{text-align:center;font-size:10px;letter-spacing:2px;margin-top:2mm;font-family:monospace}

/* ═══ CSV UPLOAD MODAL ═══════════════════════════════════════ */
.csv-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center}
.csv-modal-overlay.open{display:flex}
.csv-modal{background:#fff;border-radius:14px;padding:28px 30px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);position:relative}
.csv-modal h3{margin:0 0 6px;font-size:18px;font-weight:800;color:#1a1d23}
.csv-modal .csv-subtitle{font-size:13px;color:#6b7280;margin-bottom:18px}
.csv-tabs{display:flex;gap:8px;margin-bottom:18px}
.csv-tab-btn{flex:1;padding:9px;border:2px solid #dde1e9;border-radius:8px;background:#f8f9fb;font-size:13px;font-weight:700;cursor:pointer;color:#374151;transition:all .15s}
.csv-tab-btn.active{border-color:#1a56db;background:#1a56db;color:#fff}
.csv-format-box{background:#f1f5f9;border:1px solid #dde1e9;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#374151;line-height:1.7}
.csv-format-box strong{color:#1a1d23;display:block;margin-bottom:4px}
.csv-format-box code{font-family:monospace;background:#e2e8f0;padding:1px 5px;border-radius:4px;font-size:11px}
.csv-drop-zone{border:2px dashed #dde1e9;border-radius:10px;padding:28px 20px;text-align:center;cursor:pointer;transition:all .15s;margin-bottom:14px}
.csv-drop-zone:hover,.csv-drop-zone.dragover{border-color:#1a56db;background:#f0f4ff}
.csv-drop-zone input[type=file]{display:none}
.csv-drop-zone .dz-icon{font-size:32px;margin-bottom:6px}
.csv-drop-zone .dz-text{font-size:13px;color:#6b7280;font-weight:600}
.csv-drop-zone .dz-hint{font-size:11px;color:#9ca3af;margin-top:3px}
.csv-upload-btn{width:100%;padding:12px;background:linear-gradient(135deg,#1a56db,#1448b8);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer;transition:opacity .15s}
.csv-upload-btn:hover{opacity:.88}
.csv-upload-btn:disabled{background:#9ca3af;cursor:not-allowed}
.csv-close-btn{position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af;line-height:1}
.csv-close-btn:hover{color:#c81e1e}
.csv-result{margin-top:12px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;display:none}
.csv-result.ok{background:#ecfdf5;color:#057a55;border:1px solid #6ee7b7}
.csv-result.err{background:#fef2f2;color:#c81e1e;border:1px solid #fca5a5}
</style>

<!-- CUSTOMER DISPLAY BAR -->
<div class="pos-customer-bar">
  <div class="pcb-left">
    <div class="pcb-branch">📍 <?= h($branch_name) ?></div>
    <div class="pcb-cust-name" id="pcb_cust_name">Walk-in Customer</div>
  </div>
  <div class="pcb-center">
    <div class="pcb-total-label">TOTAL BILL</div>
    <div class="pcb-total-amount" id="pcb_total"><?= $currency_sym ?> 0.00</div>
  </div>
  <div class="pcb-right">
    <div class="pcb-clock-time" id="pcb_clock">--:--:--</div>
    <div class="pcb-clock-date" id="pcb_date"></div>
  </div>
</div>

<!-- MAIN POS AREA -->
<div class="pos-main" id="pos_main">

  <!-- LEFT: ORDER PANEL -->
  <div class="pos-left" id="pos_left">
    <div class="pos-topbar">
      <div class="top-btns">
        <button class="ptb-btn ptb-dark"   onclick="window.location='<?= APP_URL ?>/index.php'">🏠 Home</button>        
        <button class="ptb-btn ptb-orange" onclick="window.location='<?= APP_URL ?>/sales-returns.php'">↩ Sell Return</button>
        <button class="ptb-btn ptb-green"  onclick="window.location='<?= APP_URL ?>/income.php'">📋 Order List</button>
        <button class="ptb-btn ptb-blue"   onclick="window.location='<?= APP_URL ?>/income.php'">📑 Sell list</button>
        <button class="ptb-btn ptb-purple" onclick="window.location='<?= APP_URL ?>/expense-add.php'">💸 Add Expense</button>                
        <button class="ptb-btn ptb-red"    onclick="confirmClose()">✕ Reg. Close</button>
        <button class="ptb-btn" style="background:#059669;color:#fff" onclick="printReceipt()" title="Print Receipt (Ctrl+P)">🖨 Print <small style="opacity:.75;font-size:10px">[Ctrl+P]</small></button>
      </div>
    </div>

    <div class="pos-search-row">
      <div class="pos-cust-wrap">
        <input type="text" id="pos_cust_input" placeholder="👤 Walk-in Customer"
               oninput="searchCustomer(this.value)" autocomplete="off">
        <input type="hidden" id="pos_cust_id" value="">
        <div class="cust-dropdown" id="cust_dd"></div>
      </div>
      <button class="new-cust-btn" onclick="window.open('<?= APP_URL ?>/customers.php','_blank')">+</button>
      <div class="pos-barcode-wrap" style="position:relative">
        <span style="font-size:18px;color:#6b7280;flex-shrink:0">🔍</span>
        <input type="text" id="barcode_input"
               placeholder="Enter Product name / SKU / Scan barcode"
               oninput="barcodeSearch(this.value)" onkeydown="barcodeKeydown(event)" autocomplete="off">
        <button class="bc-add-btn" onclick="barcodeAddFirst()">+</button>
        <div class="barcode-dropdown" id="bc_dd"></div>
      </div>
    </div>

    <div class="pos-meta-row">
      <select id="pos_price_type">
        <option>Default Selling Price</option><option>Wholesale Price</option><option>Special Price</option>
      </select>
      <input type="text" id="pos_ref" placeholder="Ref / Commission Agent">
      <input type="text" id="pos_date_disp" readonly>
    </div>

    <div class="pos-cart-table-wrap">
      <table class="pos-cart-table" id="cart_table">
        <thead><tr>
          <th style="width:36%">Item(s)</th>
          <th style="width:14%;text-align:center">Quantity</th>
          <th style="width:18%;text-align:right">Price</th>
          <th style="width:14%;text-align:center">VAT</th>
          <th style="width:14%;text-align:right">Total</th>
          <th style="width:4%"></th>
        </tr></thead>
        <tbody id="cart_tbody">
          <tr class="cart-empty-row"><td colspan="6">🛒 No items yet — scan barcode or click a product</td></tr>
        </tbody>
      </table>
    </div>

    <div class="pos-bottom">
      <div class="pos-totals-grid">
        <div>
          <div class="tg-row"><label>Total Item</label><span id="disp_item_count" style="font-weight:700;font-size:15px">0.00</span></div>
          <div class="tg-row" style="margin-top:4px"><label>Discount</label>
            <div style="display:flex;gap:5px;align-items:center">
              <button id="disc_pct_btn" onclick="setDiscMode('pct')" style="padding:4px 9px;border-radius:5px;border:1.5px solid #dde1e9;font-size:13px;font-weight:700;cursor:pointer;background:#f8f9fb">%</button>
              <button id="disc_fix_btn" onclick="setDiscMode('fixed')" style="padding:4px 9px;border-radius:5px;border:1.5px solid #1a56db;font-size:13px;font-weight:700;cursor:pointer;background:#1a56db;color:#fff">Fixed</button>
              <input type="number" id="pos_discount" value="0" min="0" step="0.01"
                     style="width:100px;text-align:right;padding:5px 9px;border:1.5px solid #dde1e9;border-radius:6px;font-size:14px;font-weight:700" oninput="recalc()">
            </div>
          </div>
          <div class="tg-row" style="margin-top:4px"><label>Shipping</label>
            <input type="number" id="pos_shipping" value="0" min="0" step="0.01"
                   style="width:100px;text-align:right;padding:5px 9px;border:1.5px solid #dde1e9;border-radius:6px;font-size:14px" oninput="recalc()">
          </div>
        </div>
        <div>
          <div class="tg-row"><label>Subtotal:</label><span id="disp_subtotal" style="font-weight:700;font-size:15px">0.00</span></div>
          <div class="tg-row" id="pos_vat_row" style="display:none;color:#f59e0b"><label style="color:#f59e0b">VAT (+):</label><span id="disp_vat" style="font-weight:700">0.00</span></div>
          <div class="tg-row tg-total" style="margin-top:4px"><label style="color:#c81e1e">Invoice Dis.(-)</label><span id="disp_disc">0.00</span></div>
          <div class="tg-row" style="margin-top:4px"><label>Total Bill:</label><span id="disp_total2" style="font-weight:800;color:#1a1d23;font-size:16px">0.00</span></div>
        </div>
      </div>
      <div class="pos-total-bar">
        <span class="tb-label">TOTAL BILL:</span>
        <span class="tb-value" id="disp_total_main"><?= $currency_sym ?> 0.00</span>
      </div>
      <div class="pos-pay-row">
        <button class="pay-method-btn active" id="pm_cash"   onclick="selPay('cash',this)">💵 Cash</button>
        <button class="pay-method-btn"        id="pm_card"   onclick="selPay('card',this)">💳 Card</button>
        <button class="pay-method-btn"        id="pm_nagad"  onclick="selPay('nagad',this)">📲 Nagad</button>
        <button class="pay-method-btn"        id="pm_bkash"  onclick="selPay('bkash',this)">💚 bKash</button>
        <button class="pay-method-btn"        id="pm_upay" onclick="selPay('upay',this)">🪙 UPay</button>
      </div>      
      <div class="pos-checkout-wrap">
        <button class="btn-pos-checkout" id="btn_checkout" onclick="checkout()" disabled>✅ Complete Sale</button>
        <button class="btn-pos-clear" onclick="clearCart()">🗑 Clear Cart</button>
      </div>
    </div>
  </div><!-- /pos-left -->

  <!-- DIVIDER -->
  <div class="pos-divider" id="pos_divider"></div>

  <!-- RIGHT: PRODUCT GRID -->
  <div class="pos-right" id="pos_right">
    <div class="pos-filter-bar">
      <select id="cat_filter_select" onchange="selectCatFromSelect(this.value)">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h(htmlspecialchars($c, ENT_QUOTES)) ?>"><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="item-count" id="pos_count"></span>
      <button class="refresh-btn" onclick="fetchProducts()">↺ Refresh</button>
      <button class="recent-btn" onclick="window.location='<?= APP_URL ?>/income.php'">🕐 Recent History</button>
    </div>
    <div class="pos-cat-tabs" id="cat_tabs">
      <div class="pos-cat-tab active" onclick="selectCat('',this)">All</div>
      <?php foreach ($categories as $c): ?>
        <div class="pos-cat-tab" onclick="selectCat('<?= h(addslashes($c)) ?>',this)"><?= h($c) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="pos-products-grid" id="products_grid">
      <div class="no-products-msg">⏳ Loading products…</div>
    </div>
  </div>
</div><!-- /pos-main -->

<!-- THERMAL RECEIPT PREVIEW MODAL -->
<div class="receipt-overlay" id="receipt_overlay" onclick="if(event.target===this)closeReceipt()">
  <div class="receipt-preview-box">
    <button class="rp-close-x" onclick="closeReceipt()">✕</button>
    <div class="rp-actions">
      <button class="rp-print-btn" onclick="triggerPrint()">🖨 Print Receipt</button>
      <button class="rp-close-btn" onclick="closeReceipt()">✕ Close</button>
    </div>
    <div id="thermal_receipt_content">
      <!-- receipt rendered by JS -->
    </div>
  </div>
</div>

<!-- CSV IMPORT MODAL -->
<div class="csv-modal-overlay" id="csv_modal_overlay" onclick="closeCsvModalOutside(event)">
  <div class="csv-modal">
    <button class="csv-close-btn" onclick="closeCsvModal()">✕</button>
    <h3>📥 CSV Import</h3>
    <p class="csv-subtitle">Upload a CSV file to bulk-import Items or Clients into the system.</p>
    <div class="csv-tabs">
      <button class="csv-tab-btn active" id="csv_tab_items" onclick="switchCsvTab('items')">📦 Item List</button>
      <button class="csv-tab-btn"        id="csv_tab_clients" onclick="switchCsvTab('clients')">👥 Client List</button>
    </div>

    <!-- Items format -->
    <div id="csv_fmt_items" class="csv-format-box">
      <strong>📄 File Name: <code>items_import.csv</code></strong>
      Required columns (first row must be the header):<br>
      <code>name</code> · <code>sku</code> · <code>category</code> · <code>unit</code> · <code>unit_price</code> · <code>quantity</code> · <code>reorder_level</code><br><br>
      Example row:<br>
      <code>Mouse Pad A240, MPAD, Accessories, pcs, 250, 4, 2</code>
    </div>

    <!-- Clients format -->
    <div id="csv_fmt_clients" class="csv-format-box" style="display:none">
      <strong>📄 File Name: <code>clients_import.csv</code></strong>
      Required columns (first row must be the header):<br>
      <code>name</code> · <code>phone</code> · <code>email</code> · <code>address</code> · <code>opening_balance</code><br><br>
      Example row:<br>
      <code>Rahim Uddin, 01711000000, rahim@email.com, Dhaka, 0</code>
    </div>

    <label class="csv-drop-zone" id="csv_drop_zone" for="csv_file_input"
           ondragover="csvDragOver(event)" ondragleave="csvDragLeave(event)" ondrop="csvDrop(event)">
      <div class="dz-icon">📂</div>
      <div class="dz-text" id="csv_dz_text">Click to browse or drag &amp; drop CSV file here</div>
      <div class="dz-hint">Only .csv files accepted</div>
      <input type="file" id="csv_file_input" accept=".csv" onchange="csvFileSelected(this)">
    </label>

    <button class="csv-upload-btn" id="csv_upload_btn" onclick="csvUpload()" disabled>⬆ Upload &amp; Import</button>
    <div class="csv-result" id="csv_result"></div>
  </div>
</div>

<form id="pos_form" method="POST" action="<?= APP_URL ?>/income-add.php" style="display:none">
  <input type="hidden" name="customer_id"     id="fc_cust_id">
  <input type="hidden" name="customer_name"   id="fc_cust_name">
  <input type="hidden" name="date"            id="fc_date">
  <input type="hidden" name="paid"            id="fc_paid">
  <input type="hidden" name="tax_pct"         value="0">
  <input type="hidden" name="discount"        id="fc_discount">
  <input type="hidden" name="notes"           id="fc_notes">
  <input type="hidden" name="payment_method"  id="fc_payment_method" value="cash">
  <input type="hidden" name="tax_amount"      id="fc_tax_amount"     value="0">
  <input type="hidden" name="status"          value="paid">
  <div id="fc_items"></div>
</form>

<script>
const APP_URL='<?= APP_URL ?>';
const CUR='<?= addslashes($currency_sym) ?>';
let allProducts=[],cart=[],activeCat='',payMethod='cash',discMode='fixed';

// Clock
function tickClock(){
  const now=new Date(),hh=String(now.getHours()).padStart(2,'0'),mm=String(now.getMinutes()).padStart(2,'0'),ss=String(now.getSeconds()).padStart(2,'0');
  document.getElementById('pcb_clock').textContent=hh+':'+mm+':'+ss;
  const days=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.getElementById('pcb_date').textContent=days[now.getDay()]+', '+now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
}
tickClock(); setInterval(tickClock,1000);
document.getElementById('pos_date_disp').value=new Date().toLocaleDateString('en-GB')+' '+new Date().toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});

// Draggable divider
(function(){
  const divider=document.getElementById('pos_divider'),left=document.getElementById('pos_left'),main=document.getElementById('pos_main');
  let dragging=false,startX=0,startW=0;
  divider.addEventListener('mousedown',e=>{dragging=true;startX=e.clientX;startW=left.offsetWidth;divider.classList.add('dragging');document.body.style.cursor='col-resize';document.body.style.userSelect='none';});
  document.addEventListener('mousemove',e=>{if(!dragging)return;const delta=e.clientX-startX,newW=Math.max(320,Math.min(startW+delta,main.offsetWidth*0.78));left.style.width=newW+'px';localStorage.setItem('pos_left_w',newW);});
  document.addEventListener('mouseup',()=>{if(!dragging)return;dragging=false;divider.classList.remove('dragging');document.body.style.cursor='';document.body.style.userSelect='';});
  divider.addEventListener('touchstart',e=>{dragging=true;startX=e.touches[0].clientX;startW=left.offsetWidth;},{passive:true});
  document.addEventListener('touchmove',e=>{if(!dragging)return;const delta=e.touches[0].clientX-startX,newW=Math.max(320,Math.min(startW+delta,main.offsetWidth*0.78));left.style.width=newW+'px';},{passive:true});
  document.addEventListener('touchend',()=>{dragging=false;});
  const saved=localStorage.getItem('pos_left_w');if(saved)left.style.width=Math.max(320,parseInt(saved))+'px';
})();

// Products
async function fetchProducts(){
  document.getElementById('products_grid').innerHTML='<div class="no-products-msg">⏳ Loading…</div>';
  try{const r=await fetch(APP_URL+'/api-pos-items.php');allProducts=await r.json();renderProducts(allProducts);}
  catch(e){document.getElementById('products_grid').innerHTML='<div class="no-products-msg">❌ Failed to load</div>';}
}
function renderProducts(products){
  const filtered=activeCat?products.filter(p=>p.category===activeCat):products;
  const q=document.getElementById('barcode_input').value.trim().toLowerCase();
  const shown=q?filtered.filter(p=>p.name.toLowerCase().includes(q)||(p.sku&&p.sku.toLowerCase().includes(q))):filtered;
  document.getElementById('pos_count').textContent=shown.length+' item(s)';
  if(!shown.length){document.getElementById('products_grid').innerHTML='<div class="no-products-msg">📦 No products found</div>';return;}
  document.getElementById('products_grid').innerHTML=shown.map(p=>{
    const qty=parseFloat(p.quantity),oos=qty<=0,low=p.reorder_level>0&&qty<=p.reorder_level&&qty>0;
    const img=p.image_path?`<img src="${APP_URL}/${p.image_path}" onerror="this.style.display='none'">`:`<span class="p-icon">📦</span>`;
    return `<div class="product-card ${oos?'out-of-stock':''}" id="pc_${p.id}" onclick="${oos?'':'addToCart('+p.id+')'}">
      <div class="p-img-wrap">${img}</div><div class="p-name">${esc(p.name)}</div>
      ${p.sku?`<div class="p-sku">${esc(p.sku)}</div>`:''}
      <div class="p-price">${CUR} ${parseFloat(p.unit_price).toFixed(2)}${p.vat_pct>0?` <span style="font-size:10px;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:4px">+${p.vat_pct}% VAT</span>`:' <span style="font-size:9px;color:#059669">VAT✓</span>'}</div>
      <div class="p-stock ${low?'low':''}">${oos?'🚫 Out of stock':(low?'⚠ ':'')+`(Qty.${qty.toFixed(0)})`}</div>
    </div>`;
  }).join('');
}
function selectCat(cat,el){activeCat=cat;document.querySelectorAll('.pos-cat-tab').forEach(t=>t.classList.remove('active'));if(el)el.classList.add('active');document.getElementById('cat_filter_select').value=cat;renderProducts(allProducts);}
function selectCatFromSelect(val){activeCat=val;document.querySelectorAll('.pos-cat-tab').forEach(t=>t.classList.toggle('active',(val===''&&t.textContent.trim()==='All')||t.textContent.trim()===val));renderProducts(allProducts);}

let bcTimer=null;
function barcodeSearch(q){
  clearTimeout(bcTimer);const dd=document.getElementById('bc_dd');
  if(!q.trim()){dd.style.display='none';renderProducts(allProducts);return;}
  renderProducts(allProducts);
  bcTimer=setTimeout(()=>{
    const matches=allProducts.filter(p=>p.name.toLowerCase().includes(q.toLowerCase())||(p.sku&&p.sku.toLowerCase().includes(q.toLowerCase())));
    if(!matches.length){dd.style.display='none';return;}
    dd.innerHTML=matches.slice(0,10).map(p=>`<div class="bc-drop-item" onclick="addToCartFromBC(${p.id})"><div><div class="bdi-name">${esc(p.name)}</div><div class="bdi-sku">${p.sku?'['+esc(p.sku)+']':''}</div></div><div style="text-align:right"><div class="bdi-price">${CUR} ${parseFloat(p.unit_price).toFixed(2)}</div><div class="bdi-stock">${parseFloat(p.quantity)>0?'Qty:'+parseFloat(p.quantity).toFixed(0):'🚫 Out'}</div></div></div>`).join('');
    dd.style.display='block';
  },150);
}
function barcodeKeydown(e){if(e.key==='Enter'){e.preventDefault();barcodeAddFirst();}}
function barcodeAddFirst(){
  const q=document.getElementById('barcode_input').value.trim();if(!q)return;
  const exact=allProducts.find(p=>p.sku&&p.sku.toLowerCase()===q.toLowerCase());
  if(exact){addToCartById(exact.id);clearBCInput();return;}
  const match=allProducts.find(p=>p.name.toLowerCase().includes(q.toLowerCase())||(p.sku&&p.sku.toLowerCase().includes(q.toLowerCase())));
  if(match){addToCartById(match.id);clearBCInput();}
}
function addToCartFromBC(id){addToCartById(id);clearBCInput();}
function clearBCInput(){document.getElementById('barcode_input').value='';document.getElementById('bc_dd').style.display='none';renderProducts(allProducts);document.getElementById('barcode_input').focus();}
document.addEventListener('click',e=>{if(!e.target.closest('.pos-barcode-wrap'))document.getElementById('bc_dd').style.display='none';});

function addToCart(itemId){addToCartById(itemId);flashCard(itemId);}
function addToCartById(itemId){
  const p=allProducts.find(x=>x.id==itemId);if(!p||parseFloat(p.quantity)<=0)return;
  const ex=cart.find(c=>c.id==itemId);
  if(ex){ex.qty=Math.min(ex.qty+1,parseFloat(p.quantity));}
  else{cart.push({id:p.id,name:p.name,price:parseFloat(p.unit_price),vat:parseFloat(p.vat_pct||0),qty:1,stock:parseFloat(p.quantity),unit:p.unit,sku:p.sku||''});}
  renderCart();
}
function flashCard(id){const el=document.getElementById('pc_'+id);if(!el)return;el.classList.add('flash-added');setTimeout(()=>el.classList.remove('flash-added'),220);}
function setQty(idx,val){const q=parseFloat(val)||0;if(q<=0)cart.splice(idx,1);else cart[idx].qty=Math.min(q,cart[idx].stock);renderCart();}
function setPrice(idx,val){cart[idx].price=parseFloat(val)||0;renderCart();}
function removeFromCart(idx){cart.splice(idx,1);renderCart();}
function clearCart(){
  if(cart.length&&!confirm('Clear all items from cart?'))return;
  cart=[];document.getElementById('pos_cust_id').value='';document.getElementById('pos_cust_input').value='';
  document.getElementById('pos_discount').value='0';document.getElementById('pos_shipping').value='0';
  document.getElementById('pcb_cust_name').textContent='Walk-in Customer';renderCart();
}
function renderCart(){
  const tbody=document.getElementById('cart_tbody');
  if(!cart.length){tbody.innerHTML='<tr class="cart-empty-row"><td colspan="6">🛒 No items yet — scan barcode or click a product</td></tr>';document.getElementById('btn_checkout').disabled=true;recalc();return;}
  tbody.innerHTML=cart.map((item,i)=>{
    const lineTotal = item.price * item.qty;
    const vatAmt    = item.vat > 0 ? lineTotal * item.vat / 100 : 0;
    // VAT cell content
    let vatCell;
    if (item.vat > 0) {
      vatCell = `<td style="text-align:center;white-space:nowrap">
        <span style="display:inline-block;background:#fef3c7;color:#92400e;border-radius:5px;padding:2px 7px;font-size:12px;font-weight:700;line-height:1.4">
          +${item.vat}%<br>
          <span style="font-size:11px">${CUR} ${vatAmt.toFixed(2)}</span>
        </span>
      </td>`;
    } else {
      vatCell = `<td style="text-align:center">
        <span style="display:inline-block;background:#d1fae5;color:#065f46;border-radius:5px;padding:3px 8px;font-size:11px;font-weight:700">
          ✓ Incl.
        </span>
      </td>`;
    }
    return `<tr>
    <td class="td-name">${esc(item.name)}<small>${item.sku?'['+esc(item.sku)+'] · ':''}${item.stock.toFixed(0)} ${item.unit} in stock</small></td>
    <td class="td-qty" style="text-align:center"><input type="number" value="${item.qty}" min="0.001" step="0.001" onchange="setQty(${i},this.value)" style="width:68px;text-align:center"></td>
    <td class="td-price" style="text-align:right"><input type="number" value="${item.price.toFixed(2)}" min="0" step="0.01" onchange="setPrice(${i},this.value)" style="width:88px;text-align:right"></td>
    ${vatCell}
    <td class="td-total" style="text-align:right">
      <div style="font-weight:800;color:#1a56db;font-size:16px">${CUR} ${(lineTotal+vatAmt).toFixed(2)}</div>
      ${vatAmt>0?`<div style="font-size:10px;color:#9ca3af;margin-top:1px">${CUR}${lineTotal.toFixed(2)} + ${CUR}${vatAmt.toFixed(2)}</div>`:''}
    </td>
    <td class="td-del"><button onclick="removeFromCart(${i})">✕</button></td>
  </tr>`;
  }).join('');
  document.getElementById('btn_checkout').disabled=false;recalc();
}
function recalc(){
  const sub=cart.reduce((s,i)=>s+i.price*i.qty,0),shipping=parseFloat(document.getElementById('pos_shipping').value)||0;
  // Calculate VAT: for items where vat>0, add vat% on top of their line total
  const vatAmt=cart.reduce((s,i)=>s+(i.vat>0?(i.price*i.qty*i.vat/100):0),0);
  let discAmt=discMode==='pct'?sub*(parseFloat(document.getElementById('pos_discount').value)||0)/100:parseFloat(document.getElementById('pos_discount').value)||0;
  const total=Math.max(0,sub+vatAmt-discAmt+shipping),itemCount=cart.reduce((s,i)=>s+i.qty,0);
  document.getElementById('disp_item_count').textContent=itemCount.toFixed(2);
  document.getElementById('disp_subtotal').textContent=sub.toFixed(2);
  document.getElementById('disp_disc').textContent=discAmt.toFixed(2);
  document.getElementById('disp_total2').textContent=total.toFixed(2);
  document.getElementById('disp_total_main').textContent=CUR+' '+total.toFixed(2);
  document.getElementById('pcb_total').textContent=CUR+' '+total.toFixed(2);
  // Show/hide VAT row
  const vatRow=document.getElementById('pos_vat_row');
  if(vatRow){vatRow.style.display=vatAmt>0?'flex':'none';const vatEl=document.getElementById('disp_vat');if(vatEl)vatEl.textContent=vatAmt.toFixed(2);}
}
function setDiscMode(mode){
  discMode=mode;
  const on='padding:4px 9px;border-radius:5px;border:1.5px solid #1a56db;font-size:13px;font-weight:700;cursor:pointer;background:#1a56db;color:#fff';
  const off='padding:4px 9px;border-radius:5px;border:1.5px solid #dde1e9;font-size:13px;font-weight:700;cursor:pointer;background:#f8f9fb;color:#374151';
  document.getElementById('disc_pct_btn').style.cssText=mode==='pct'?on:off;
  document.getElementById('disc_fix_btn').style.cssText=mode==='fixed'?on:off;
  recalc();
}
function selPay(method,btn){payMethod=method;document.querySelectorAll('.pay-method-btn').forEach(b=>b.classList.remove('active'));if(btn)btn.classList.add('active');}
function checkout(){
  if(!cart.length)return;
  const sub=cart.reduce((s,i)=>s+i.price*i.qty,0),shipping=parseFloat(document.getElementById('pos_shipping').value)||0;
  const vatTotal=cart.reduce((s,i)=>s+(i.vat>0?(i.price*i.qty*i.vat/100):0),0);
  let discAmt=discMode==='pct'?sub*(parseFloat(document.getElementById('pos_discount').value)||0)/100:parseFloat(document.getElementById('pos_discount').value)||0;
  const total=Math.max(0,sub+vatTotal-discAmt+shipping),custId=document.getElementById('pos_cust_id').value,custName=document.getElementById('pos_cust_input').value.trim()||'Walk-in Customer',today=new Date().toISOString().slice(0,10);
  document.getElementById('fc_cust_id').value=custId;document.getElementById('fc_cust_name').value=custName;
  document.getElementById('fc_date').value=today;document.getElementById('fc_paid').value=total.toFixed(2);
  document.getElementById('fc_discount').value=discAmt.toFixed(2);
  document.getElementById('fc_payment_method').value=payMethod;
  var refNote=document.getElementById('pos_ref').value?'Ref: '+document.getElementById('pos_ref').value:'';
  document.getElementById('fc_notes').value='POS Sale'+(refNote?' · '+refNote:'');
  const fc=document.getElementById('fc_items');fc.innerHTML='';
  document.getElementById('fc_tax_amount').value=vatTotal.toFixed(2);
  cart.forEach(item=>{fc.innerHTML+=`<input type="hidden" name="items[description][]" value="${esc(item.name)}">`;fc.innerHTML+=`<input type="hidden" name="items[qty][]" value="${item.qty}">`;fc.innerHTML+=`<input type="hidden" name="items[unit_price][]" value="${item.price}">`;fc.innerHTML+=`<input type="hidden" name="items[item_id][]" value="${item.id}">`;fc.innerHTML+=`<input type="hidden" name="items[vat_pct][]" value="${item.vat}">`;});
  document.getElementById('pos_form').submit();
}
function saveQuotation(){alert('Quotation feature coming soon');}
function saveOrder(){checkout();}
function dueSell(){checkout();}
function receiveMoney(){alert('Receive TK coming soon');}
function confirmClose(){if(cart.length&&!confirm('You have items in cart. Close register anyway?'))return;window.location=APP_URL+'/index.php';}

let custTimer=null;
function searchCustomer(q){
  clearTimeout(custTimer);const dd=document.getElementById('cust_dd');
  document.getElementById('pcb_cust_name').textContent=q.trim()||'Walk-in Customer';
  if(!q.trim()||q.length<2){dd.style.display='none';return;}
  custTimer=setTimeout(async()=>{try{const r=await fetch(APP_URL+'/api-customers-search.php?q='+encodeURIComponent(q));const data=await r.json();dd.innerHTML='';if(!data.length){dd.style.display='none';return;}data.forEach(c=>{const d=document.createElement('div');d.className='cust-drop-item';d.innerHTML='<strong>'+esc(c.name)+'</strong>'+(c.phone?' · '+esc(c.phone):'')+(c.email?'<br><small style="color:#9ca3af">'+esc(c.email)+'</small>':'');d.onclick=()=>{document.getElementById('pos_cust_id').value=c.id;document.getElementById('pos_cust_input').value=c.name+(c.phone?' · '+c.phone:'');document.getElementById('pcb_cust_name').textContent=c.name;dd.style.display='none';};dd.appendChild(d);});dd.style.display='block';}catch(e){}},200);
}
document.addEventListener('click',e=>{if(!e.target.closest('.pos-cust-wrap'))document.getElementById('cust_dd').style.display='none';});
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
fetchProducts();

// ═══ THERMAL RECEIPT PRINT ═══════════════════════════════════
function printReceipt(){
  if(!cart.length){ alert('Cart is empty — add items first.'); return; }
  const sub=cart.reduce((s,i)=>s+i.price*i.qty,0);
  const vatTotal=cart.reduce((s,i)=>s+(i.vat>0?(i.price*i.qty*i.vat/100):0),0);
  const shipping=parseFloat(document.getElementById('pos_shipping').value)||0;
  let discAmt=discMode==='pct'?sub*(parseFloat(document.getElementById('pos_discount').value)||0)/100:parseFloat(document.getElementById('pos_discount').value)||0;
  const total=Math.max(0,sub+vatTotal-discAmt+shipping);
  const custName=document.getElementById('pos_cust_input').value.trim()||'Walk-in Customer';
  const now=new Date();
  const dateStr=now.toLocaleDateString('en-GB')+' '+now.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
  const invNo='POS-'+now.getFullYear()+String(now.getMonth()+1).padStart(2,'0')+String(now.getDate()).padStart(2,'0')+'-'+String(now.getTime()).slice(-4);
  const pmLabel={'cash':'Cash','card':'Card','nagad':'Nagad','bkash':'bKash','upay':'UPay'}[payMethod]||payMethod;
  const companyName='<?= addslashes(!empty($cs['company_name']) ? $cs['company_name'] : 'Daffodil Software Ltd') ?>';
  const companyAddress='<?= addslashes(!empty($cs['address']) ? $cs['address'] : '') ?>';
  const companyPhone='<?= addslashes(!empty($cs['phone']) ? $cs['phone'] : '') ?>';
  const branchName='<?= addslashes($branch_name) ?>';

  const rows=cart.map(item=>{
    const lineTotal=item.price*item.qty;
    const vatAmt=item.vat>0?lineTotal*item.vat/100:0;
    const vatNote=vatAmt>0?`\n      <div style="font-size:9px;color:#555;text-align:center">VAT(${item.vat}%): +${CUR}${vatAmt.toFixed(2)}</div>`:' <span style="font-size:9px">(VAT Incl.)</span>';
    return `
    <div class="th-item-row">
      <div class="th-item-name">${esc(item.name)}${item.sku?'<br><span style="font-size:9px;color:#555">['+esc(item.sku)+']</span>':''}</div>
      <div class="th-item-qty">${item.qty.toFixed(item.qty%1===0?0:2)} x ${CUR}${item.price.toFixed(2)}${vatNote}</div>
      <div class="th-item-total">${CUR}${(lineTotal+vatAmt).toFixed(2)}</div>
    </div>`;
  }).join('');

  document.getElementById('thermal_receipt_content').innerHTML=`
  <div class="thermal-paper">
    <div class="th-logo">${companyName}</div>
    ${branchName?`<div class="th-address">${branchName}</div>`:''}
    ${companyAddress?`<div class="th-address">${companyAddress}</div>`:''}
    ${companyPhone?`<div class="th-phone">📞 ${companyPhone}</div>`:''}
    <hr class="th-divider">
    <div class="th-meta">Invoice : <strong>${invNo}</strong></div>
    <div class="th-meta">Date    : ${dateStr}</div>
    <div class="th-meta">Customer: ${custName}</div>
    <div class="th-meta">Cashier : <?= addslashes($user['name'] ?? 'Cashier') ?></div>
    <hr class="th-divider">
    <div class="th-items-header">
      <div style="flex:1">Item</div>
      <div style="width:22mm;text-align:center">Qty×Price</div>
      <div style="width:20mm;text-align:right">Total</div>
    </div>
    ${rows}
    <hr class="th-divider">
    <div class="th-totals">
      <div class="th-total-row"><span>Subtotal</span><span>${CUR} ${sub.toFixed(2)}</span></div>
      ${vatTotal>0?`<div class="th-total-row" style="color:#92400e"><span>VAT (+)</span><span>${CUR} ${vatTotal.toFixed(2)}</span></div>`:''}
      ${discAmt>0?`<div class="th-total-row"><span>Discount (-)</span><span>${CUR} ${discAmt.toFixed(2)}</span></div>`:''}
      ${shipping>0?`<div class="th-total-row"><span>Shipping</span><span>${CUR} ${shipping.toFixed(2)}</span></div>`:''}
    </div>
    <div class="th-grand"><span>TOTAL</span><span>${CUR} ${total.toFixed(2)}</span></div>
    <div class="th-pay">Payment: ${pmLabel}</div>
    <hr class="th-divider">
    <div class="th-barcode">${invNo}</div>
    <div class="th-footer">Thank you for your purchase!<br>Please come again.<br>— ${companyName} —</div>
  </div>`;
  document.getElementById('receipt_overlay').classList.add('open');
}
function closeReceipt(){ document.getElementById('receipt_overlay').classList.remove('open'); }
function triggerPrint(){ window.print(); }

// Ctrl+P → open thermal receipt preview (overrides browser print)
document.addEventListener('keydown',function(e){
  if((e.ctrlKey||e.metaKey)&&e.key==='p'){ e.preventDefault(); printReceipt(); }
});

// ═══ CSV IMPORT MODAL ════════════════════════════════════════
let csvActiveTab = 'items', csvSelectedFile = null;
function openCsvModal(){ document.getElementById('csv_modal_overlay').classList.add('open'); csvReset(); }
function closeCsvModal(){ document.getElementById('csv_modal_overlay').classList.remove('open'); }
function closeCsvModalOutside(e){ if(e.target===document.getElementById('csv_modal_overlay')) closeCsvModal(); }
function switchCsvTab(tab){
  csvActiveTab = tab;
  document.getElementById('csv_tab_items').classList.toggle('active', tab==='items');
  document.getElementById('csv_tab_clients').classList.toggle('active', tab==='clients');
  document.getElementById('csv_fmt_items').style.display = tab==='items' ? '' : 'none';
  document.getElementById('csv_fmt_clients').style.display = tab==='clients' ? '' : 'none';
  csvReset();
}
function csvReset(){
  csvSelectedFile = null;
  document.getElementById('csv_file_input').value = '';
  document.getElementById('csv_dz_text').textContent = 'Click to browse or drag & drop CSV file here';
  document.getElementById('csv_upload_btn').disabled = true;
  const r = document.getElementById('csv_result'); r.style.display='none'; r.className='csv-result';
}
function csvFileSelected(input){
  if(!input.files.length) return;
  csvSelectedFile = input.files[0];
  document.getElementById('csv_dz_text').textContent = '✅ ' + csvSelectedFile.name;
  document.getElementById('csv_upload_btn').disabled = false;
}
function csvDragOver(e){ e.preventDefault(); document.getElementById('csv_drop_zone').classList.add('dragover'); }
function csvDragLeave(e){ document.getElementById('csv_drop_zone').classList.remove('dragover'); }
function csvDrop(e){
  e.preventDefault(); document.getElementById('csv_drop_zone').classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if(!file || !file.name.endsWith('.csv')){ showCsvResult('err','⚠ Please drop a valid .csv file.'); return; }
  csvSelectedFile = file;
  document.getElementById('csv_dz_text').textContent = '✅ ' + file.name;
  document.getElementById('csv_upload_btn').disabled = false;
}
async function csvUpload(){
  if(!csvSelectedFile){ showCsvResult('err','Please select a CSV file first.'); return; }
  const btn = document.getElementById('csv_upload_btn');
  btn.disabled = true; btn.textContent = '⏳ Uploading…';
  const fd = new FormData();
  fd.append('csv_file', csvSelectedFile);
  fd.append('type', csvActiveTab); // 'items' or 'clients'
  try {
    const r = await fetch(APP_URL + '/api-csv-import.php', { method:'POST', body: fd });
    const data = await r.json();
    if(data.success) showCsvResult('ok','✅ ' + (data.message || 'Import successful! Imported: ' + (data.imported||0) + ' rows.'));
    else showCsvResult('err','❌ ' + (data.message || 'Import failed.'));
  } catch(e) {
    showCsvResult('err','❌ Upload error. Make sure api-csv-import.php exists on the server.');
  }
  btn.disabled = false; btn.textContent = '⬆ Upload & Import';
  if(csvActiveTab==='items') fetchProducts();
}
function showCsvResult(type, msg){
  const r = document.getElementById('csv_result');
  r.className = 'csv-result ' + type; r.textContent = msg; r.style.display = 'block';
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
