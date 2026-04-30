<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Items / Inventory';
$tid = tid(); $bid = brid();

// Filter
$search  = trim($_GET['q'] ?? '');
$cat     = trim($_GET['cat'] ?? '');
$expiry  = $_GET['expiry'] ?? '';

$where = 'WHERE i.tenant_id=? AND i.branch_id=? AND i.is_active=1';
$params = [$tid, $bid];

if ($search) { $where .= ' AND (i.name LIKE ? OR i.sku LIKE ? OR i.brand LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat)    { $where .= ' AND i.category=?'; $params[] = $cat; }
if ($expiry === 'expired')  { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date < CURDATE()'; }
if ($expiry === '7days')    { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)'; }
if ($expiry === '30days')   { $where .= ' AND i.expiry_date IS NOT NULL AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)'; }

$total = db()->prepare("SELECT COUNT(*) FROM items i $where");
$total->execute($params); $total = (int)$total->fetchColumn();
$pg = paginate($total, 20, (int)($_GET['page'] ?? 1));

$stmt = db()->prepare("SELECT * FROM items i $where ORDER BY i.name ASC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Categories for filter
$cats = db()->prepare('SELECT DISTINCT category FROM items WHERE tenant_id=? AND branch_id=? AND category IS NOT NULL AND category != "" ORDER BY category');
$cats->execute([$tid, $bid]);
$categories = $cats->fetchAll(PDO::FETCH_COLUMN);

// Expiry alerts counts
$exp_soon = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
$exp_soon->execute([$tid, $bid]); $exp_soon = (int)$exp_soon->fetchColumn();

$exp_gone = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 AND expiry_date IS NOT NULL AND expiry_date < CURDATE()');
$exp_gone->execute([$tid, $bid]); $exp_gone = (int)$exp_gone->fetchColumn();

ob_start();
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if ($exp_gone > 0): ?>
    <a href="?expiry=expired" class="alert alert-error" style="padding:8px 14px;border-radius:var(--radius);text-decoration:none;font-size:13px">
      ⚠ <?= $exp_gone ?> item(s) EXPIRED
    </a>
    <?php endif; ?>
    <?php if ($exp_soon > 0): ?>
    <a href="?expiry=30days" class="alert alert-warning" style="padding:8px 14px;border-radius:var(--radius);text-decoration:none;font-size:13px">
      ⏰ <?= $exp_soon ?> expiring within 30 days
    </a>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px">
    <button onclick="window.print()" class="btn btn-outline no-print">🖨 Print</button>
    <button onclick="openItemCsv()" class="btn btn-outline no-print" style="background:#0891b2;color:#fff;border-color:#0891b2">📥 CSV Import</button>
    <a href="<?= APP_URL ?>/item-add.php" class="btn btn-primary">+ Add Item</a>
  </div>
</div>

<!-- ITEMS CSV MODAL -->
<style>
.icsv-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center}
.icsv-overlay.open{display:flex}
.icsv-modal{background:#fff;border-radius:14px;padding:28px 30px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25);position:relative}
.icsv-modal h3{margin:0 0 4px;font-size:18px;font-weight:800;color:#1a1d23}
.icsv-modal .sub{font-size:13px;color:#6b7280;margin-bottom:14px}
.icsv-fmt{background:#f1f5f9;border:1px solid #dde1e9;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#374151;line-height:1.8}
.icsv-fmt strong{color:#1a1d23;display:block;margin-bottom:4px}
.icsv-fmt code{font-family:monospace;background:#e2e8f0;padding:1px 5px;border-radius:4px;font-size:11px}
.icsv-drop{border:2px dashed #dde1e9;border-radius:10px;padding:26px 20px;text-align:center;cursor:pointer;transition:all .15s;margin-bottom:12px}
.icsv-drop:hover,.icsv-drop.dragover{border-color:#1a56db;background:#f0f4ff}
.icsv-drop input{display:none}
.icsv-btn{width:100%;padding:12px;background:linear-gradient(135deg,#1a56db,#1448b8);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer}
.icsv-btn:disabled{background:#9ca3af;cursor:not-allowed}
.icsv-close{position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:#9ca3af}
.icsv-close:hover{color:#c81e1e}
.icsv-result{margin-top:10px;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;display:none}
.icsv-result.ok{background:#ecfdf5;color:#057a55;border:1px solid #6ee7b7}
.icsv-result.err{background:#fef2f2;color:#c81e1e;border:1px solid #fca5a5}
</style>
<div class="icsv-overlay" id="icsv_overlay" onclick="if(event.target===this)closeItemCsv()">
  <div class="icsv-modal">
    <button class="icsv-close" onclick="closeItemCsv()">✕</button>
    <h3>📥 Import Items via CSV</h3>
    <p class="sub">Bulk-add products to your inventory from a CSV file.</p>
    <div class="icsv-fmt">
      <strong>📄 File Name: <code>items_import.csv</code></strong>
      Required headers (row 1):<br>
      <code>name</code> · <code>sku</code> · <code>category</code> · <code>unit</code> · <code>unit_price</code> · <code>quantity</code> · <code>reorder_level</code><br><br>
      Example row:<br>
      <code>Mouse Pad A240, MPAD, Accessories, pcs, 250, 4, 2</code>
    </div>
    <label class="icsv-drop" id="icsv_drop" for="icsv_file"
           ondragover="event.preventDefault();this.classList.add('dragover')"
           ondragleave="this.classList.remove('dragover')"
           ondrop="icsvDrop(event)">
      <div style="font-size:32px;margin-bottom:6px">📂</div>
      <div style="font-size:13px;color:#6b7280;font-weight:600" id="icsv_dz_txt">Click to browse or drag &amp; drop CSV</div>
      <div style="font-size:11px;color:#9ca3af;margin-top:3px">Only .csv files</div>
      <input type="file" id="icsv_file" accept=".csv" onchange="icsvFileSel(this)">
    </label>
    <button class="icsv-btn" id="icsv_upload_btn" onclick="icsvUpload()" disabled>⬆ Upload &amp; Import</button>
    <div class="icsv-result" id="icsv_result"></div>
  </div>
</div>
<script>
var icsvFile=null;
function openItemCsv(){ icsvFile=null; document.getElementById('icsv_file').value=''; document.getElementById('icsv_dz_txt').textContent='Click to browse or drag & drop CSV'; document.getElementById('icsv_upload_btn').disabled=true; var r=document.getElementById('icsv_result');r.style.display='none'; document.getElementById('icsv_overlay').classList.add('open'); }
function closeItemCsv(){ document.getElementById('icsv_overlay').classList.remove('open'); }
function icsvFileSel(inp){ if(!inp.files.length)return; icsvFile=inp.files[0]; document.getElementById('icsv_dz_txt').textContent='✅ '+icsvFile.name; document.getElementById('icsv_upload_btn').disabled=false; }
function icsvDrop(e){ e.preventDefault(); document.getElementById('icsv_drop').classList.remove('dragover'); var f=e.dataTransfer.files[0]; if(!f||!f.name.endsWith('.csv')){ icsvShowResult('err','⚠ Drop a valid .csv file.'); return; } icsvFile=f; document.getElementById('icsv_dz_txt').textContent='✅ '+f.name; document.getElementById('icsv_upload_btn').disabled=false; }
async function icsvUpload(){ if(!icsvFile){ icsvShowResult('err','Select a CSV file first.'); return; } var btn=document.getElementById('icsv_upload_btn'); btn.disabled=true; btn.textContent='⏳ Uploading…'; var fd=new FormData(); fd.append('csv_file',icsvFile); fd.append('type','items'); try{ var r=await fetch('<?= APP_URL ?>/api-csv-import.php',{method:'POST',body:fd}); var data=await r.json(); if(data.success) icsvShowResult('ok','✅ '+(data.message||'Imported '+(data.imported||0)+' rows. Refreshing…')); else icsvShowResult('err','❌ '+(data.message||'Import failed.')); if(data.success) setTimeout(()=>location.reload(),1800); }catch(e){ icsvShowResult('err','❌ Upload error. Ensure api-csv-import.php exists.'); } btn.disabled=false; btn.textContent='⬆ Upload & Import'; }
function icsvShowResult(type,msg){ var r=document.getElementById('icsv_result'); r.className='icsv-result '+type; r.textContent=msg; r.style.display='block'; }
</script>

<!-- Filters -->
<div class="card" style="padding:16px;margin-bottom:16px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="min-width:200px;margin:0">
      <label>Search</label>
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Name or SKU…">
    </div>
    <div class="form-group" style="min-width:150px;margin:0">
      <label>Category</label>
      <select name="cat">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= h($c) ?>" <?= $c==$cat?'selected':'' ?>><?= h($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:150px;margin:0">
      <label>Expiry</label>
      <select name="expiry">
        <option value="">All</option>
        <option value="7days"  <?= $expiry=='7days' ?'selected':'' ?>>Expiring in 7 days</option>
        <option value="30days" <?= $expiry=='30days'?'selected':'' ?>>Expiring in 30 days</option>
        <option value="expired"<?= $expiry=='expired'?'selected':'' ?>>Already expired</option>
      </select>
    </div>
    <button type="submit" class="btn btn-outline">Filter</button>
    <a href="<?= APP_URL ?>/items.php" class="btn btn-outline">Clear</a>
  </form>
</div>

<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Image</th><th>Name</th><th>SKU</th><th>Category</th><th>Brand</th><th>Qty</th>
          <th>Unit Price</th><th>VAT</th><th>Expiry Date</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $it):
        $today = date('Y-m-d');
        $exp   = $it['expiry_date'];
        $exp_class = '';
        $exp_label = $exp ? fmt_date($exp) : '—';
        if ($exp) {
          $diff = (strtotime($exp) - strtotime($today)) / 86400;
          if ($diff < 0)  { $exp_class = 'badge-unpaid'; $exp_label = '⚠ ' . fmt_date($exp) . ' (EXPIRED)'; }
          elseif ($diff <= 7)  { $exp_class = 'badge-unpaid'; $exp_label = '⚠ ' . fmt_date($exp); }
          elseif ($diff <= 30) { $exp_class = 'badge-partial'; $exp_label = '⏰ ' . fmt_date($exp); }
        }
        $low_stock = $it['reorder_level'] > 0 && $it['quantity'] <= $it['reorder_level'];
      ?>
        <tr>
          <td><?php if(!empty($it['image_path'])): ?><img src="<?= APP_URL ?>/<?= h($it['image_path']) ?>" class="item-thumb"><?php else: ?><div class="item-thumb-placeholder">📦</div><?php endif; ?></td>
          <td><strong><?= h($it['name']) ?></strong>
            <?php if ($it['notes']): ?><br><small style="color:var(--c-muted)"><?= h(substr($it['notes'],0,60)) ?><?= strlen($it['notes'])>60?'…':'' ?></small><?php endif; ?>
          </td>
          <td><?= h($it['sku'] ?: '—') ?></td>
          <td><?= h($it['category'] ?: '—') ?></td>
          <td><?= h($it['brand'] ?: '—') ?></td>
          <td>
            <span style="<?= $low_stock?'color:var(--c-expense);font-weight:600':'' ?>">
              <?= h($it['quantity'] + 0) ?> <?= h($it['unit']) ?>
            </span>
            <?php if ($low_stock): ?><br><small style="color:var(--c-expense)">Low stock</small><?php endif; ?>
          </td>
          <td><?= money((float)$it['unit_price']) ?></td>
          <td>
            <?php $vp = (float)($it['vat_pct'] ?? 0); ?>
            <?php if ($vp > 0): ?>
              <span class="badge badge-pending"><?= $vp ?>%</span>
            <?php else: ?>
              <span class="badge badge-approved" title="VAT included in price">Incl.</span>
            <?php endif; ?>
          </td>
          <td><?php if ($exp && $exp_class): ?><span class="badge <?= $exp_class ?>"><?= $exp_label ?></span><?php else: ?><?= $exp_label ?><?php endif; ?></td>
          <td><span class="badge badge-<?= $it['is_active']?'paid':'cancelled' ?>"><?= $it['is_active']?'Active':'Inactive' ?></span></td>
          <td>
            <a href="<?= APP_URL ?>/item-add.php?id=<?= $it['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <a href="<?= APP_URL ?>/item-delete.php?id=<?= $it['id'] ?>" class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this item?')">Del</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="11" style="text-align:center;color:var(--c-muted);padding:30px">No items found. <a href="<?= APP_URL ?>/item-add.php">Add your first item →</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div style="padding:16px" class="pagination">
    <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <?php if ($i==$pg['page']): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($cat) ?>&expiry=<?= urlencode($expiry) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
