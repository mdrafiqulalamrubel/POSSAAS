<?php
/**
 * barcode-print.php
 * Print item barcodes on A4 paper.
 * URL: /public/barcode-print.php
 *
 * Uses Google Fonts Libre Barcode (Code128, Code39, EAN13).
 * No external barcode library needed — pure CSS font rendering.
 *
 * SQL (run once):
 * ALTER TABLE items ADD COLUMN IF NOT EXISTS barcode_value VARCHAR(120) NOT NULL DEFAULT '' AFTER sku;
 * ALTER TABLE items ADD COLUMN IF NOT EXISTS brand_name   VARCHAR(120) NOT NULL DEFAULT '' AFTER category;
 */
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$page_title = 'Barcode Print';
$tid = tid(); $bid = brid();

// Fetch all active items
$all_items = db()->prepare(
    'SELECT id, name, sku, barcode_value, brand_name, unit_price, category
     FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY name'
);
$all_items->execute([$tid, $bid]);
$all_items = $all_items->fetchAll();

// Selected items + quantities from POST
$selected_ids = [];
$label_counts = [];
$mode = 'setup'; // setup | preview | print

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_ids = array_map('intval', $_POST['item_ids'] ?? []);
    foreach ($selected_ids as $sid) {
        $label_counts[$sid] = max(1, min(200, (int)($_POST['qty_' . $sid] ?? 1)));
    }
    $mode = !empty($_POST['_print_now']) ? 'print' : 'preview';
}

// Quick-select single item via GET (from items list "Print Barcode" button)
if (!empty($_GET['item_id'])) {
    $quick_id = (int)$_GET['item_id'];
    $selected_ids = [$quick_id];
    $label_counts[$quick_id] = max(1, (int)($_GET['qty'] ?? 1));
    $mode = 'preview';
}

// Print settings
$cols       = max(1, (int)($_POST['cols']       ?? $_GET['cols']       ?? 4));
$font_style = $_POST['font_style'] ?? $_GET['font_style'] ?? 'code128text';
$label_size = $_POST['label_size'] ?? $_GET['label_size'] ?? 'medium';
$show_price = isset($_POST['show_price']) || isset($_GET['show_price']);
$show_name  = !isset($_POST['hide_name'])  && !isset($_GET['hide_name']);
$show_brand = isset($_POST['show_brand'])  || isset($_GET['show_brand']);

$size_map = [
    'small'  => ['font'=>'28px', 'name_size'=>'8px',  'price_size'=>'9px'],
    'medium' => ['font'=>'38px', 'name_size'=>'10px', 'price_size'=>'11px'],
    'large'  => ['font'=>'50px', 'name_size'=>'12px', 'price_size'=>'13px'],
];
$sz = $size_map[$label_size] ?? $size_map['medium'];

$font_families = [
    'code128text' => "'Libre Barcode 128 Text','Libre Barcode 128',monospace",
    'code128'     => "'Libre Barcode 128',monospace",
    'code39'      => "'Libre Barcode 39 Text',monospace",
    'code39ext'   => "'Libre Barcode 39 Extended Text',monospace",
    'ean'         => "'Libre Barcode EAN13 Text',monospace",
];
$font_family = $font_families[$font_style] ?? $font_families['code128text'];
$currency    = !empty($cs['currency'] ?? '') ? $cs['currency'] : '৳';

ob_start();
?>

<!-- Barcode fonts from Google -->
<link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&family=Libre+Barcode+128+Text&family=Libre+Barcode+39&family=Libre+Barcode+39+Text&family=Libre+Barcode+39+Extended+Text&family=Libre+Barcode+EAN13+Text&display=swap" rel="stylesheet">

<form method="post" id="bcForm">

<!-- Settings panel -->
<div class="card no-print" style="margin-bottom:14px">
  <div class="card-title" style="margin-bottom:12px">⚙ Print Settings</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="min-width:130px">
      <label>Columns per Row</label>
      <select name="cols">
        <?php foreach([2,3,4,5,6] as $c): ?>
          <option value="<?= $c ?>" <?= $cols==$c?'selected':'' ?>><?= $c ?> cols</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:180px">
      <label>Barcode Font</label>
      <select name="font_style">
        <option value="code128text" <?= $font_style==='code128text'?'selected':'' ?>>Code 128 + Text</option>
        <option value="code128"     <?= $font_style==='code128'?'selected':'' ?>>Code 128 (bars only)</option>
        <option value="code39"      <?= $font_style==='code39'?'selected':'' ?>>Code 39 + Text</option>
        <option value="code39ext"   <?= $font_style==='code39ext'?'selected':'' ?>>Code 39 Extended</option>
        <option value="ean"         <?= $font_style==='ean'?'selected':'' ?>>EAN-13</option>
      </select>
    </div>
    <div class="form-group" style="min-width:120px">
      <label>Label Size</label>
      <select name="label_size">
        <option value="small"  <?= $label_size==='small'?'selected':'' ?>>Small</option>
        <option value="medium" <?= $label_size==='medium'?'selected':'' ?>>Medium</option>
        <option value="large"  <?= $label_size==='large'?'selected':'' ?>>Large</option>
      </select>
    </div>
    <div class="form-group">
      <label>Options</label>
      <div style="display:flex;gap:14px;margin-top:6px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:5px;font-size:13px;text-transform:none;letter-spacing:0;cursor:pointer">
          <input type="checkbox" name="show_price" value="1" <?= $show_price?'checked':'' ?> style="width:auto;padding:0"> Show Price
        </label>
        <label style="display:flex;align-items:center;gap:5px;font-size:13px;text-transform:none;letter-spacing:0;cursor:pointer">
          <input type="checkbox" name="hide_name" value="1" <?= !$show_name?'checked':'' ?> style="width:auto;padding:0"> Hide Name
        </label>
        <label style="display:flex;align-items:center;gap:5px;font-size:13px;text-transform:none;letter-spacing:0;cursor:pointer">
          <input type="checkbox" name="show_brand" value="1" <?= $show_brand?'checked':'' ?> style="width:auto;padding:0"> Show Brand
        </label>
      </div>
    </div>
  </div>
</div>

<!-- Item selector -->
<div class="card no-print" style="margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px">
    <div class="card-title" style="margin-bottom:0">📦 Select Items to Print</div>
    <div style="display:flex;gap:8px">
      <input type="text" id="bcSearch" placeholder="🔍 Search…" oninput="bcFilter(this.value)"
             style="padding:7px 10px;border:1.5px solid #dde1e9;border-radius:8px;font-size:13px;width:200px">
      <button type="button" class="btn btn-outline btn-sm" onclick="bcSelAll(true)">☑ All</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="bcSelAll(false)">☐ None</button>
    </div>
  </div>
  <div class="table-wrap" style="max-height:320px;overflow-y:auto">
    <table>
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="chkAll" onchange="bcSelAll(this.checked)" style="width:auto"></th>
          <th>Item Name</th>
          <th>SKU / Barcode Value</th>
          <th>Brand</th>
          <th>Price</th>
          <th style="width:90px">Label Qty</th>
        </tr>
      </thead>
      <tbody id="bcTbody">
        <?php foreach ($all_items as $item):
          $bv = !empty($item['barcode_value']) ? $item['barcode_value'] : $item['sku'];
          $checked = in_array($item['id'], $selected_ids) ? 'checked' : '';
        ?>
        <tr data-name="<?= strtolower(h($item['name'].' '.$item['sku'])) ?>">
          <td><input type="checkbox" name="item_ids[]" value="<?= $item['id'] ?>" class="bc-chk" <?= $checked ?> style="width:auto"></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= h($item['name']) ?></div>
            <?php if (!empty($item['category'])): ?><div style="font-size:11px;color:#6b7280"><?= h($item['category']) ?></div><?php endif; ?>
          </td>
          <td style="font-size:12px;font-family:monospace">
            <div><?= h($item['sku']) ?></div>
            <?php if (!empty($item['barcode_value']) && $item['barcode_value'] !== $item['sku']): ?>
              <div style="color:#1a56db"><?= h($item['barcode_value']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px"><?= h($item['brand_name'] ?? '') ?></td>
          <td style="font-size:13px;font-weight:600"><?= $currency ?><?= number_format((float)($item['unit_price']??0),2) ?></td>
          <td><input type="number" name="qty_<?= $item['id'] ?>" value="<?= $label_counts[$item['id']] ?? 1 ?>"
                     min="1" max="200" style="width:65px;text-align:center;padding:4px 6px;border:1.5px solid #dde1e9;border-radius:6px;font-size:13px"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Action buttons -->
<div style="display:flex;gap:10px;margin-bottom:20px" class="no-print">
  <button type="submit" class="btn btn-primary">👁 Preview Labels</button>
  <button type="button" class="btn btn-success" onclick="printNow()">🖨 Print Now</button>
  <?php if (!empty($selected_ids)): ?>
  <span style="align-self:center;font-size:13px;color:#6b7280"><?= count($selected_ids) ?> item(s) selected</span>
  <?php endif; ?>
</div>

</form>

<!-- Label preview / print area -->
<?php if (!empty($selected_ids)): ?>
<div class="card" style="padding:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px" class="no-print">
    <div style="font-weight:700;font-size:15px">
      Label Preview —
      <?php $total_labels = 0; foreach($selected_ids as $sid) $total_labels += ($label_counts[$sid]??1); ?>
      <?= $total_labels ?> label<?= $total_labels!=1?'s':'' ?>
    </div>
    <button class="btn btn-success" onclick="window.print()">🖨 Print</button>
  </div>
  <div class="barcode-preview-grid" style="grid-template-columns:repeat(<?= $cols ?>, 1fr)">
    <?php
    // Build lookup by ID
    $item_lookup = [];
    foreach ($all_items as $item) $item_lookup[$item['id']] = $item;

    foreach ($selected_ids as $sid):
      $item = $item_lookup[$sid] ?? null;
      if (!$item) continue;
      $bv    = !empty($item['barcode_value']) ? $item['barcode_value'] : $item['sku'];
      $count = $label_counts[$sid] ?? 1;
      for ($i = 0; $i < $count; $i++):
    ?>
    <div class="bc-label-box">
      <?php if ($show_name): ?>
        <div class="bc-product-name"><?= h(mb_strimwidth($item['name'], 0, 30, '…')) ?></div>
      <?php endif; ?>
      <?php if ($show_brand && !empty($item['brand_name'])): ?>
        <div style="font-size:9px;color:#555;font-weight:600"><?= h($item['brand_name']) ?></div>
      <?php endif; ?>
      <div class="bc-barcode-text" style="font-family:<?= $font_family ?>;font-size:<?= $sz['font'] ?>"><?= h($bv) ?></div>
      <div class="bc-human-text"><?= h($bv) ?></div>
      <?php if ($show_price): ?>
        <div class="bc-price"><?= $currency ?><?= number_format((float)($item['unit_price']??0),2) ?></div>
      <?php endif; ?>
    </div>
    <?php endfor; endforeach; ?>
  </div>
</div>
<?php else: ?>
<div class="card" style="text-align:center;padding:50px;color:#9ca3af">
  <div style="font-size:40px;margin-bottom:12px">▌▌</div>
  <div style="font-weight:700;margin-bottom:6px">Select items above, then click Preview or Print</div>
  <div style="font-size:13px">You can also use the <strong>Print Barcode</strong> button next to any item in the Items list.</div>
</div>
<?php endif; ?>

<script>
function bcFilter(q){
  q=q.toLowerCase();
  document.querySelectorAll('#bcTbody tr').forEach(function(r){ r.style.display=r.dataset.name.includes(q)?'':'none'; });
}
function bcSelAll(v){
  document.querySelectorAll('.bc-chk').forEach(function(c){ c.checked=v; });
  document.getElementById('chkAll').checked=v;
}
function printNow(){
  var f=document.getElementById('bcForm');
  var inp=document.createElement('input'); inp.type='hidden'; inp.name='_print_now'; inp.value='1';
  f.appendChild(inp);
  f.submit();
  setTimeout(function(){ window.print(); },900);
}
// Auto-print mode
<?php if ($mode==='print'): ?>
window.addEventListener('load',function(){ setTimeout(function(){ window.print(); },600); });
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
