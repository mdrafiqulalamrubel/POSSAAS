<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$tid = tid(); $bid = brid();

// ── Migrations (safe to run every time) ──────────────────────
try { db()->exec("ALTER TABLE items ADD COLUMN vat_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER unit_price"); } catch (PDOException $e) {}
try { db()->exec("ALTER TABLE items ADD COLUMN brand VARCHAR(100) NULL AFTER category"); } catch (PDOException $e) {}
try { db()->exec("ALTER TABLE items ADD COLUMN image_path VARCHAR(255) NULL"); } catch (PDOException $e) {}

// Load currency symbol for display
$cs_cur = db()->prepare('SELECT currency FROM company_settings WHERE tenant_id=? LIMIT 1');
$cs_cur->execute([$tid]);
$cs_cur_row = $cs_cur->fetch();
$currency_sym = !empty($cs_cur_row['currency']) ? $cs_cur_row['currency'] : CURRENCY;

$id   = (int)($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $s = db()->prepare('SELECT * FROM items WHERE id=? AND tenant_id=?');
    $s->execute([$id, $tid]);
    $item = $s->fetch();
    if (!$item) { flash('error','Item not found'); redirect('items.php'); }
}
$page_title = $item ? 'Edit Item' : 'Add Item';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']          ?? '');
    $sku     = trim($_POST['sku']           ?? '');
    $cat     = trim($_POST['category']      ?? '');
    $brand   = trim($_POST['brand']         ?? '');
    $unit    = trim($_POST['unit']          ?? 'pcs');
    $qty     = (float)($_POST['quantity']   ?? 0);
    $price   = (float)($_POST['unit_price'] ?? 0);
    $vat_pct = (float)($_POST['vat_pct']    ?? 0);
    if ($vat_pct < 0)   $vat_pct = 0;
    if ($vat_pct > 100) $vat_pct = 100;
    $reorder = (float)($_POST['reorder_level'] ?? 0);
    $expiry  = trim($_POST['expiry_date'] ?? '') ?: null;
    $notes   = trim($_POST['notes']       ?? '');
    $active  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) {
        $err = 'Item name is required.';
    } else {
        // ── Image upload ──────────────────────────────────────
        $image_path = $item['image_path'] ?? null;

        // Remove image if checkbox ticked
        if (isset($_POST['remove_image']) && $image_path) {
            $full = __DIR__ . '/' . $image_path;
            if (file_exists($full)) @unlink($full);
            $image_path = null;
        }

        // Upload new image
        if (!empty($_FILES['item_image']['name']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $file    = $_FILES['item_image'];
            $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $ext_map = ['image/jpeg'=>'jpg','image/jpg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];

            if (!in_array($file['type'], $allowed)) {
                $err = 'Only JPG, PNG, GIF, WEBP images are allowed.';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $err = 'Image must be under 3 MB.';
            } else {
                $dir   = __DIR__ . '/uploads/items/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $fname = 'item_' . $tid . '_' . time() . '.' . ($ext_map[$file['type']] ?? $ext);
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                    // Remove old image
                    if ($image_path && file_exists(__DIR__ . '/' . $image_path)) {
                        @unlink(__DIR__ . '/' . $image_path);
                    }
                    $image_path = 'uploads/items/' . $fname;
                } else {
                    $err = 'Image upload failed — check folder permissions on uploads/items/';
                }
            }
        }

        if (empty($err)) {
            if ($item) {
                db()->prepare('UPDATE items SET name=?,sku=?,category=?,brand=?,unit=?,quantity=?,
                               unit_price=?,vat_pct=?,reorder_level=?,expiry_date=?,notes=?,
                               is_active=?,image_path=?,updated_at=NOW()
                               WHERE id=? AND tenant_id=?')
                   ->execute([$name,$sku,$cat,$brand,$unit,$qty,
                              $price,$vat_pct,$reorder,$expiry,$notes,
                              $active,$image_path,$id,$tid]);
                log_activity('update','Inventory','Item updated: '.$name,'ID:'.$id.' SKU:'.$sku);
                flash('success','Item updated.');
            } else {
                db()->prepare('INSERT INTO items
                               (tenant_id,branch_id,name,sku,category,brand,unit,quantity,
                                unit_price,vat_pct,reorder_level,expiry_date,notes,is_active,image_path,created_by)
                               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                   ->execute([$tid,$bid,$name,$sku,$cat,$brand,$unit,$qty,
                              $price,$vat_pct,$reorder,$expiry,$notes,1,$image_path,uid()]);
                log_activity('create','Inventory','New item added: '.$name,'SKU:'.$sku.' Price:'.$price);
                flash('success','Item added.');
            }
            redirect('items.php');
        }
    }
}

ob_start();
?>
<div style="max-width:860px">
<div class="card">
  <div class="card-title"><?= $page_title ?></div>
  <?php if (!empty($err)): ?>
  <div class="alert alert-error" style="margin-bottom:16px"><?= h($err) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <div class="form-grid">

      <!-- Row 1 -->
      <div class="form-group full">
        <label>Item Name *</label>
        <input type="text" name="name" value="<?= h($item['name'] ?? '') ?>" required
               placeholder="e.g. Keyboard A4Tech">
      </div>

      <!-- Row 2 -->
      <div class="form-group">
        <label>SKU / Barcode</label>
        <input type="text" name="sku" value="<?= h($item['sku'] ?? '') ?>" placeholder="A1 / scan code">
      </div>
      <div class="form-group">
        <label>Category</label>
        <input type="text" name="category" value="<?= h($item['category'] ?? '') ?>" placeholder="e.g. Electronics, Food">
      </div>
      <div class="form-group">
        <label>Brand</label>
        <input type="text" name="brand" value="<?= h($item['brand'] ?? '') ?>" placeholder="e.g. Samsung, A4Tech">
      </div>

      <!-- Row 3 -->
      <div class="form-group">
        <label>Unit</label>
        <select name="unit">
          <?php foreach (['pcs','kg','g','litre','ml','box','dozen','pack','bottle','bag','roll'] as $u): ?>
            <option value="<?= $u ?>" <?= ($item['unit']??'pcs')===$u?'selected':'' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity in Stock</label>
        <input type="number" name="quantity" value="<?= h($item['quantity'] ?? 0) ?>" min="0" step="0.001">
      </div>
      <div class="form-group">
        <label>Reorder Level (alert when ≤)</label>
        <input type="number" name="reorder_level" value="<?= h($item['reorder_level'] ?? 0) ?>" min="0" step="0.001">
      </div>

      <!-- Row 4 — Price + VAT side by side -->
      <div class="form-group">
        <label>Unit Price (<?= $currency_sym ?? CURRENCY ?>)</label>
        <input type="number" name="unit_price" id="f_unit_price"
               value="<?= h($item['unit_price'] ?? 0) ?>" min="0" step="0.01"
               oninput="updateVatHint()">
      </div>

      <div class="form-group">
        <label>
          VAT %
          <small style="text-transform:none;font-weight:400;color:var(--c-muted)">&nbsp;(0 = VAT Included)</small>
        </label>
        <input type="number" name="vat_pct" id="f_vat_pct"
               value="<?= h(isset($item['vat_pct']) ? $item['vat_pct'] + 0 : 0) ?>"
               min="0" max="100" step="0.01" placeholder="0"
               oninput="updateVatHint()">
        <div id="vat_hint" style="margin-top:5px;font-size:12px;padding:6px 10px;border-radius:6px;font-weight:600"></div>
      </div>

      <!-- Row 5 -->
      <div class="form-group">
        <label>Expiry Date</label>
        <input type="date" name="expiry_date" value="<?= h($item['expiry_date'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;text-transform:none;font-size:14px">
          <input type="checkbox" name="is_active" <?= (!isset($item) || $item['is_active']) ? 'checked' : '' ?>>
          Active (visible in POS &amp; inventory)
        </label>
      </div>

      <!-- Notes -->
      <div class="form-group full">
        <label>Notes / Description</label>
        <textarea name="notes" rows="3" placeholder="Storage instructions, warnings, extra info…"><?= h($item['notes'] ?? '') ?></textarea>
      </div>

      <!-- ── Product Image ─────────────────────────────────── -->
      <div class="form-group full">
        <label>Product Image
          <small style="text-transform:none;font-weight:400;color:var(--c-muted)">&nbsp;JPG, PNG, GIF, WEBP — max 3 MB</small>
        </label>

        <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">

          <!-- Upload zone -->
          <div class="img-upload-wrap" id="img_zone"
               onclick="document.getElementById('item_image').click()"
               style="width:200px;min-height:160px;flex-shrink:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;cursor:pointer">
            <?php if (!empty($item['image_path'])): ?>
              <img id="img_preview"
                   src="<?= APP_URL ?>/<?= h($item['image_path']) ?>"
                   style="max-width:180px;max-height:150px;object-fit:contain;border-radius:6px;border:1px solid var(--c-border)">
              <span id="img_placeholder" style="display:none;text-align:center;color:var(--c-muted)">
                <span style="font-size:32px">📷</span><br>Click to upload image
              </span>
            <?php else: ?>
              <img id="img_preview" style="display:none;max-width:180px;max-height:150px;object-fit:contain;border-radius:6px;border:1px solid var(--c-border)">
              <span id="img_placeholder" style="text-align:center;color:var(--c-muted)">
                <span style="font-size:32px">📷</span><br>Click to upload image
              </span>
            <?php endif; ?>
            <input type="file" id="item_image" name="item_image" accept="image/*"
                   style="display:none" onchange="previewImage(this)">
          </div>

          <!-- Right side info -->
          <div style="flex:1;min-width:160px">
            <?php if (!empty($item['image_path'])): ?>
            <div style="font-size:13px;color:var(--c-income);margin-bottom:10px">✅ Current image saved</div>
            <label style="display:flex;align-items:center;gap:8px;text-transform:none;font-size:13px;font-weight:400;cursor:pointer;color:var(--c-expense)">
              <input type="checkbox" name="remove_image" value="1"
                     onchange="handleRemoveCheck(this)">
              🗑 Remove current image
            </label>
            <?php else: ?>
            <div style="font-size:13px;color:var(--c-muted)">No image yet — click the box to upload.</div>
            <?php endif; ?>
            <div style="margin-top:12px;font-size:12px;color:var(--c-muted);line-height:1.6">
              • Accepted: JPG, PNG, GIF, WEBP<br>
              • Max size: 3 MB<br>
              • Shown in POS grid &amp; item list
            </div>
            <div id="img_file_name" style="margin-top:8px;font-size:12px;color:var(--c-primary);font-weight:600;display:none"></div>
          </div>
        </div>
      </div>

    </div><!-- /form-grid -->

    <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
      <button type="submit" class="btn btn-primary">💾 Save Item</button>
      <a href="<?= APP_URL ?>/items.php" class="btn btn-outline">✕ Cancel</a>
    </div>
  </form>
</div>
</div>

<script>
// ── VAT Hint ────────────────────────────────────────────────
function updateVatHint() {
  var priceEl  = document.getElementById('f_unit_price');
  var vatEl    = document.getElementById('f_vat_pct');
  var hintEl   = document.getElementById('vat_hint');
  var price    = parseFloat(priceEl.value) || 0;
  var vat      = parseFloat(vatEl.value)   || 0;

  if (vat === 0) {
    hintEl.style.background = '#d1fae5';
    hintEl.style.color      = '#065f46';
    hintEl.textContent      = '✅ VAT Included — displayed price is the final customer price';
  } else {
    var vatAmt  = price * vat / 100;
    var total   = price + vatAmt;
    var cur     = '<?= addslashes(CURRENCY) ?>';
    hintEl.style.background = '#fef3c7';
    hintEl.style.color      = '#92400e';
    hintEl.textContent      = '⚠ ' + vat + '% VAT = ' + cur + vatAmt.toFixed(2)
                            + '  →  Customer pays ' + cur + total.toFixed(2) + ' per unit';
  }
}

// ── Image preview ───────────────────────────────────────────
function previewImage(input) {
  if (!input.files || !input.files[0]) return;
  var file = input.files[0];
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById('img_preview');
    var ph  = document.getElementById('img_placeholder');
    img.src = e.target.result;
    img.style.display = 'block';
    if (ph) ph.style.display = 'none';
    var fn = document.getElementById('img_file_name');
    if (fn) { fn.textContent = '📎 ' + file.name + ' (' + (file.size/1024).toFixed(0) + ' KB)'; fn.style.display='block'; }
    // Uncheck remove if user selects a new file
    var rem = document.querySelector('[name=remove_image]');
    if (rem) rem.checked = false;
  };
  reader.readAsDataURL(file);
}

function handleRemoveCheck(checkbox) {
  if (checkbox.checked) {
    var img = document.getElementById('img_preview');
    var ph  = document.getElementById('img_placeholder');
    if (img) { img.style.display = 'none'; }
    if (ph)  { ph.style.display  = 'flex'; ph.style.flexDirection = 'column'; }
    // Clear file input
    document.getElementById('item_image').value = '';
    var fn = document.getElementById('img_file_name');
    if (fn) fn.style.display = 'none';
  }
}

// ── Image zone drag-over highlight ──────────────────────────
var zone = document.getElementById('img_zone');
if (zone) {
  zone.addEventListener('dragover',  function(e){ e.preventDefault(); zone.style.borderColor='var(--c-primary)'; });
  zone.addEventListener('dragleave', function()  { zone.style.borderColor=''; });
  zone.addEventListener('drop', function(e) {
    e.preventDefault(); zone.style.borderColor='';
    var dt = e.dataTransfer;
    if (dt && dt.files.length) {
      document.getElementById('item_image').files = dt.files;
      previewImage(document.getElementById('item_image'));
    }
  });
}

// Run hint on load
updateVatHint();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
