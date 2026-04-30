<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$page_title = 'Dashboard';
$tid = tid(); $bid = brid();
$from = date('Y-m-01'); $to = date('Y-m-d');

$s = db()->prepare('SELECT COALESCE(SUM(total),0) t, COALESCE(SUM(paid),0) p FROM income WHERE tenant_id=? AND branch_id=? AND date BETWEEN ? AND ? AND status!="cancelled"');
$s->execute([$tid,$bid,$from,$to]); $inc = $s->fetch();
$income_total = (float)$inc['t']; $income_paid = (float)$inc['p'];

$s = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id=? AND branch_id=? AND date BETWEEN ? AND ? AND status!="cancelled"');
$s->execute([$tid,$bid,$from,$to]); $exp = (float)$s->fetchColumn();

$s = db()->prepare('SELECT COALESCE(SUM(total),0) FROM purchases WHERE tenant_id=? AND branch_id=? AND date BETWEEN ? AND ? AND status!="cancelled"');
$s->execute([$tid,$bid,$from,$to]); $pur_total = (float)$s->fetchColumn();

$s = db()->prepare('SELECT COUNT(*) FROM customers WHERE tenant_id=?');
$s->execute([$tid]); $cust_count = (int)$s->fetchColumn();

$s = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1');
$s->execute([$tid,$bid]); $item_count = (int)$s->fetchColumn();

$s = db()->prepare('SELECT COALESCE(SUM(total),0)-COALESCE(SUM(paid),0) FROM income WHERE tenant_id=? AND branch_id=? AND status IN("unpaid","partial")');
$s->execute([$tid,$bid]); $dues = (float)$s->fetchColumn();

$profit = $income_paid - $exp;
$balance = $income_paid - $exp - $pur_total;

$recent_inv = db()->prepare('SELECT i.*, c.name cust_name FROM income i LEFT JOIN customers c ON c.id=i.customer_id WHERE i.tenant_id=? AND i.branch_id=? ORDER BY i.id DESC LIMIT 8');
$recent_inv->execute([$tid,$bid]); $recent_inv = $recent_inv->fetchAll();

ob_start();
?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start">

  <!-- LEFT: Clock + Stats + Quick Links + Recent -->
  <div>

    <!-- Clock -->
    <div class="dash-clock-wrap no-print" style="margin-bottom:20px">
      <div class="dash-clock-left">
        <div class="clock-time" id="dash-time">--:--:--</div>
        <div class="clock-ampm" id="dash-ampm"></div>
        <div class="clock-date" id="dash-date"></div>
      </div>
      <div class="dash-clock-right">🕐</div>
    </div>

    <!-- Stats Cards: 2 columns × 4 rows like reference image -->
    <div class="dash-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:20px">

      <a href="<?= APP_URL ?>/pos.php" class="dash-card" style="background:linear-gradient(135deg,#1a1d2e 0%,#1a56db 100%);grid-column:span 2;display:flex;align-items:center;justify-content:center;gap:18px;padding:22px 28px">
        <span style="font-size:48px;line-height:1">🖥</span>
        <div>
          <div style="font-size:11px;color:rgba(255,255,255,.7);font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:4px">Open Now</div>
          <div style="font-size:26px;font-weight:900;color:#fff;letter-spacing:1px">POS Screen</div>
          <div style="font-size:12px;color:rgba(255,255,255,.6);margin-top:2px">Point of Sale — Click to start selling</div>
        </div>
        <span style="margin-left:auto;font-size:32px;opacity:.4">→</span>
      </a>

      <a href="<?= APP_URL ?>/customers.php" class="dash-card dc-1">
        <span class="dc-bg-icon">👥</span>
        <div class="dc-label"><span class="t-en">Customer List</span><span class="t-bn">গ্রাহকের তালিকা</span></div>
        <div class="dc-value"><?= number_format($cust_count) ?></div>
      </a>

      <a href="<?= APP_URL ?>/items.php" class="dash-card dc-2">
        <span class="dc-bg-icon">📦</span>
        <div class="dc-label"><span class="t-en">All Products</span><span class="t-bn">সবগুলো পণ্যের তালিকা</span></div>
        <div class="dc-value"><?= number_format($item_count) ?></div>
      </a>

      <a href="<?= APP_URL ?>/purchases.php" class="dash-card dc-3">
        <span class="dc-bg-icon">🛒</span>
        <div class="dc-label"><span class="t-en">Purchases (<?= date('M') ?>)</span><span class="t-bn">পণ্য ক্রয়</span></div>
        <div class="dc-value"><?= money($pur_total) ?></div>
      </a>

      <a href="<?= APP_URL ?>/income.php" class="dash-card dc-4">
        <span class="dc-bg-icon">💵</span>
        <div class="dc-label"><span class="t-en">Sales (<?= date('M') ?>)</span><span class="t-bn">বিক্রি</span></div>
        <div class="dc-value"><?= money($income_total) ?></div>
      </a>

      <a href="<?= APP_URL ?>/expenses.php" class="dash-card dc-8">
        <span class="dc-bg-icon">💸</span>
        <div class="dc-label"><span class="t-en">Expenses (<?= date('M') ?>)</span><span class="t-bn">অন্যান্য খরচ</span></div>
        <div class="dc-value"><?= money($exp) ?></div>
      </a>

      <a href="<?= APP_URL ?>/report-dues.php" class="dash-card dc-5">
        <span class="dc-bg-icon">⚠</span>
        <div class="dc-label"><span class="t-en">Sales Due Amount</span><span class="t-bn">বিক্রি-Due Amount</span></div>
        <div class="dc-value"><?= money($dues) ?></div>
      </a>

      <a href="<?= APP_URL ?>/sales-returns.php" class="dash-card dc-7">
        <span class="dc-bg-icon">↩</span>
        <div class="dc-label"><span class="t-en">Sales Return</span><span class="t-bn">বিক্রি-Return</span></div>
        <div class="dc-value"><?= money(0) ?></div>
      </a>

      <div class="dash-card dc-6">
        <span class="dc-bg-icon">📊</span>
        <div class="dc-label"><span class="t-en">Balance / Profit</span><span class="t-bn">Balance</span></div>
        <div class="dc-value"><?= money($profit) ?></div>
      </div>

    </div>

    <!-- Recent Invoices -->
    <div class="card">
      <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span><span class="t-en">Recent Invoices</span><span class="t-bn">সাম্প্রতিক ইনভয়েস</span></span>
        <a href="<?= APP_URL ?>/income-add.php" class="btn btn-primary btn-sm">+&nbsp;<span class="t-en">New</span><span class="t-bn">নতুন</span></a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th><span class="t-en">Customer</span><span class="t-bn">গ্রাহক</span></th>
              <th><span class="t-en">Total</span><span class="t-bn">মোট</span></th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recent_inv as $r): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/invoice-view.php?id=<?= $r['id'] ?>" style="color:var(--c-primary);font-weight:600"><?= h($r['invoice_no']) ?></a></td>
              <td><?= h($r['cust_name'] ?: $r['customer_name'] ?: '—') ?></td>
              <td style="font-weight:600"><?= money($r['total']) ?></td>
              <td><span class="badge badge-<?= h($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recent_inv): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--c-muted);padding:24px">
              <span class="t-en">No invoices yet</span><span class="t-bn">কোনো ইনভয়েস নেই</span>
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /LEFT -->

  <!-- RIGHT: Quick Links -->
  <div>
    <div class="card">
      <div class="card-title" style="text-align:center;font-size:16px">
        <span class="t-en">Quick Links</span><span class="t-bn">দ্রুত লিংক</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">

        <a href="<?= APP_URL ?>/customers.php" class="quick-link ql-teal">
          <span class="ql-icon">👥</span>
          <span><span class="t-en">Customers</span><span class="t-bn">গ্রাহক তালিকা</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-dues.php" class="quick-link ql-red">
          <span class="ql-icon">💰</span>
          <span><span class="t-en">Due Report</span><span class="t-bn">বকেয়া রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/items.php" class="quick-link ql-purple">
          <span class="ql-icon">📦</span>
          <span><span class="t-en">All Products</span><span class="t-bn">সবগুলো পণ্য</span></span>
        </a>
        <a href="<?= APP_URL ?>/barcode-print.php" class="quick-link ql-ash"> 
          <span class="ql-icon">🏷</span>
          <span><span class="t-en">Print Barcode</span><span class="t-bn">বারকোড প্রিন্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/purchase-add.php" class="quick-link ql-orange">
          <span class="ql-icon">🛒</span>
          <span><span class="t-en">Purchase</span><span class="t-bn">পণ্য ক্রয় করুন</span></span>
        </a>
        <a href="<?= APP_URL ?>/income-add.php" class="quick-link ql-green">
          <span class="ql-icon">🧾</span>
          <span><span class="t-en">Make Invoice</span><span class="t-bn">বিক্রি করুন</span></span>
        </a>
        <a href="<?= APP_URL ?>/income.php" class="quick-link ql-blue">
          <span class="ql-icon">📋</span>
          <span><span class="t-en">Invoice List</span><span class="t-bn">Invoice (বিক্রি)</span></span>
        </a>
        <a href="<?= APP_URL ?>/inventory-report.php" class="quick-link ql-dark">
          <span class="ql-icon">📊</span>
          <span><span class="t-en">Stock Report</span><span class="t-bn">স্টক রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/expenses.php" class="quick-link ql-ash">
          <span class="ql-icon">💸</span>
          <span><span class="t-en">Expenses</span><span class="t-bn">অন্যান্য খরচ</span></span>
        </a>
        <a href="<?= APP_URL ?>/item-add.php" class="quick-link ql-teal">
          <span class="ql-icon">➕</span>
          <span><span class="t-en">Add Product</span><span class="t-bn">পণ্য যোগ করুন</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-purchases.php" class="quick-link ql-orange">
          <span class="ql-icon">📦</span>
          <span><span class="t-en">Purchase Report</span><span class="t-bn">পণ্য ক্রয়ের রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report-sales.php" class="quick-link ql-green">
          <span class="ql-icon">📈</span>
          <span><span class="t-en">Sales Report</span><span class="t-bn">পণ্য বিক্রির রিপোর্ট</span></span>
        </a>
        <a href="<?= APP_URL ?>/report.php" class="quick-link ql-blue">
          <span class="ql-icon">📊</span>
          <span><span class="t-en">Profit/Loss</span><span class="t-bn">ব্যবসার লাভ/ক্ষতি</span></span>
        </a>
        <a href="<?= APP_URL ?>/stock-transfers.php" class="quick-link ql-purple">
          <span class="ql-icon">🔄</span>
          <span><span class="t-en">Stock Transfer</span><span class="t-bn">Product Stock Transfer</span></span>
        </a>
        <a href="<?= APP_URL ?>/pos.php" class="quick-link" style="background:linear-gradient(135deg,#1a1d2e,#1a56db);color:#fff;grid-column:span 2;justify-content:center;gap:12px;padding:14px">
          <span class="ql-icon">🖥</span>
          <span style="font-size:14px;font-weight:800"><span class="t-en">Open POS Screen</span><span class="t-bn">POS খুলুন</span></span>
        </a>
        <a href="#" onclick="openDashCsvModal('items');return false;" class="quick-link ql-teal">
          <span class="ql-icon">📥</span>
          <span><span class="t-en">CSV: Items</span><span class="t-bn">পণ্য CSV</span></span>
        </a>
        <a href="#" onclick="openDashCsvModal('clients');return false;" class="quick-link ql-purple">
          <span class="ql-icon">📥</span>
          <span><span class="t-en">CSV: Clients</span><span class="t-bn">গ্রাহক CSV</span></span>
        </a>

      </div>
    </div>
  </div><!-- /RIGHT -->

</div>

<script>
function updateClock() {
  var now  = new Date();
  var h    = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
  var ampm = h >= 12 ? 'PM' : 'AM';
  var h12  = h % 12 || 12;
  var pad  = function(n){ return String(n).padStart(2,'0'); };
  var timeEl = document.getElementById('dash-time');
  var ampmEl = document.getElementById('dash-ampm');
  var dateEl = document.getElementById('dash-date');
  if (timeEl) timeEl.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
  if (ampmEl) ampmEl.textContent = pad(h12) + ':' + pad(m) + ':' + pad(s) + ' ' + ampm;
  if (dateEl) {
    var days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    dateEl.textContent = days[now.getDay()] + ', ' + months[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear();
  }
}
updateClock(); setInterval(updateClock, 1000);
</script>

<!-- ═══ CSV IMPORT MODAL (Dashboard) ═══════════════════════════════ -->
<style>
.dcsv-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center}
.dcsv-overlay.open{display:flex}
.dcsv-modal{background:#fff;border-radius:14px;padding:28px 30px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);position:relative}
.dcsv-modal h3{margin:0 0 4px;font-size:18px;font-weight:800;color:#1a1d23}
.dcsv-modal .dcsv-sub{font-size:13px;color:#6b7280;margin-bottom:16px}
.dcsv-tabs{display:flex;gap:8px;margin-bottom:16px}
.dcsv-tab{flex:1;padding:9px;border:2px solid #dde1e9;border-radius:8px;background:#f8f9fb;font-size:13px;font-weight:700;cursor:pointer;color:#374151;transition:all .15s}
.dcsv-tab.active{border-color:#1a56db;background:#1a56db;color:#fff}
.dcsv-fmt{background:#f1f5f9;border:1px solid #dde1e9;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#374151;line-height:1.8}
.dcsv-fmt strong{color:#1a1d23;display:block;margin-bottom:4px}
.dcsv-fmt code{font-family:monospace;background:#e2e8f0;padding:1px 5px;border-radius:4px;font-size:11px}
.dcsv-drop{border:2px dashed #dde1e9;border-radius:10px;padding:26px 20px;text-align:center;cursor:pointer;transition:all .15s;margin-bottom:12px}
.dcsv-drop:hover,.dcsv-drop.dragover{border-color:#1a56db;background:#f0f4ff}
.dcsv-drop input{display:none}
.dcsv-drop .dz-icon{font-size:32px;margin-bottom:6px}
.dcsv-drop .dz-txt{font-size:13px;color:#6b7280;font-weight:600}
.dcsv-drop .dz-hint{font-size:11px;color:#9ca3af;margin-top:3px}
.dcsv-btn{width:100%;padding:12px;background:linear-gradient(135deg,#1a56db,#1448b8);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer}
.dcsv-btn:disabled{background:#9ca3af;cursor:not-allowed}
.dcsv-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af}
.dcsv-close:hover{color:#c81e1e}
.dcsv-result{margin-top:10px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;display:none}
.dcsv-result.ok{background:#ecfdf5;color:#057a55;border:1px solid #6ee7b7}
.dcsv-result.err{background:#fef2f2;color:#c81e1e;border:1px solid #fca5a5}
</style>

<div class="dcsv-overlay" id="dcsv_overlay" onclick="if(event.target===this)closeDashCsv()">
  <div class="dcsv-modal">
    <button class="dcsv-close" onclick="closeDashCsv()">✕</button>
    <h3>📥 CSV Import</h3>
    <p class="dcsv-sub">Bulk-import Items or Clients from a CSV file.</p>
    <div class="dcsv-tabs">
      <button class="dcsv-tab active" id="dcsv_t_items"   onclick="switchDashCsv('items')">📦 Item List</button>
      <button class="dcsv-tab"        id="dcsv_t_clients" onclick="switchDashCsv('clients')">👥 Client List</button>
    </div>
    <div id="dcsv_fmt_items" class="dcsv-fmt">
      <strong>📄 File Name: <code>items_import.csv</code></strong>
      Required headers (row 1): <code>name</code> · <code>sku</code> · <code>category</code> · <code>unit</code> · <code>unit_price</code> · <code>quantity</code> · <code>reorder_level</code><br>
      Example: <code>Mouse Pad A240, MPAD, Accessories, pcs, 250, 4, 2</code>
    </div>
    <div id="dcsv_fmt_clients" class="dcsv-fmt" style="display:none">
      <strong>📄 File Name: <code>clients_import.csv</code></strong>
      Required headers (row 1): <code>name</code> · <code>phone</code> · <code>email</code> · <code>address</code> · <code>opening_balance</code><br>
      Example: <code>Rahim Uddin, 01711000000, rahim@email.com, Dhaka, 0</code>
    </div>
    <label class="dcsv-drop" id="dcsv_drop" for="dcsv_file"
           ondragover="event.preventDefault();this.classList.add('dragover')"
           ondragleave="this.classList.remove('dragover')"
           ondrop="dcsvDrop(event)">
      <div class="dz-icon">📂</div>
      <div class="dz-txt" id="dcsv_dz_txt">Click to browse or drag &amp; drop CSV</div>
      <div class="dz-hint">Only .csv files</div>
      <input type="file" id="dcsv_file" accept=".csv" onchange="dcsvFileSel(this)">
    </label>
    <button class="dcsv-btn" id="dcsv_upload_btn" onclick="dcsvUpload()" disabled>⬆ Upload &amp; Import</button>
    <div class="dcsv-result" id="dcsv_result"></div>
  </div>
</div>

<script>
var dcsvTab='items', dcsvFile=null;
function openDashCsvModal(tab){ dcsvFile=null; document.getElementById('dcsv_file').value=''; document.getElementById('dcsv_dz_txt').textContent='Click to browse or drag & drop CSV'; document.getElementById('dcsv_upload_btn').disabled=true; var r=document.getElementById('dcsv_result');r.style.display='none'; switchDashCsv(tab||'items'); document.getElementById('dcsv_overlay').classList.add('open'); }
function closeDashCsv(){ document.getElementById('dcsv_overlay').classList.remove('open'); }
function switchDashCsv(tab){ dcsvTab=tab; document.getElementById('dcsv_t_items').classList.toggle('active',tab==='items'); document.getElementById('dcsv_t_clients').classList.toggle('active',tab==='clients'); document.getElementById('dcsv_fmt_items').style.display=tab==='items'?'':'none'; document.getElementById('dcsv_fmt_clients').style.display=tab==='clients'?'':'none'; }
function dcsvFileSel(inp){ if(!inp.files.length)return; dcsvFile=inp.files[0]; document.getElementById('dcsv_dz_txt').textContent='✅ '+dcsvFile.name; document.getElementById('dcsv_upload_btn').disabled=false; }
function dcsvDrop(e){ e.preventDefault(); document.getElementById('dcsv_drop').classList.remove('dragover'); var f=e.dataTransfer.files[0]; if(!f||!f.name.endsWith('.csv')){ dcsvShowResult('err','⚠ Please drop a valid .csv file.'); return; } dcsvFile=f; document.getElementById('dcsv_dz_txt').textContent='✅ '+f.name; document.getElementById('dcsv_upload_btn').disabled=false; }
async function dcsvUpload(){ if(!dcsvFile){ dcsvShowResult('err','Select a CSV file first.'); return; } var btn=document.getElementById('dcsv_upload_btn'); btn.disabled=true; btn.textContent='⏳ Uploading…'; var fd=new FormData(); fd.append('csv_file',dcsvFile); fd.append('type',dcsvTab); try{ var r=await fetch('<?= APP_URL ?>/api-csv-import.php',{method:'POST',body:fd}); var data=await r.json(); if(data.success) dcsvShowResult('ok','✅ '+(data.message||'Imported '+( data.imported||0)+' rows.')); else dcsvShowResult('err','❌ '+(data.message||'Import failed.')); }catch(e){ dcsvShowResult('err','❌ Upload error. Ensure api-csv-import.php exists.'); } btn.disabled=false; btn.textContent='⬆ Upload & Import'; }
function dcsvShowResult(type,msg){ var r=document.getElementById('dcsv_result'); r.className='dcsv-result '+type; r.textContent=msg; r.style.display='block'; }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
