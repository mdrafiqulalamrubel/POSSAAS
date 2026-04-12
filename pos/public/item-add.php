<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('cashier');
$tid = tid(); $bid = brid();

$id   = (int)($_GET['id'] ?? 0);
$item = null;
if ($id) {
    $s = db()->prepare('SELECT * FROM items WHERE id=? AND tenant_id=?');
    $s->execute([$id, $tid]);
    $item = $s->fetch();
    if (!$item) { flash('error','Item not found'); redirect('items.php'); }
}
$page_title = $item ? 'Edit Item' : 'Add Item';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $sku     = trim($_POST['sku'] ?? '');
    $cat     = trim($_POST['category'] ?? '');
    $unit    = trim($_POST['unit'] ?? 'pcs');
    $qty     = (float)($_POST['quantity'] ?? 0);
    $price   = (float)($_POST['unit_price'] ?? 0);
    $reorder = (float)($_POST['reorder_level'] ?? 0);
    $expiry  = trim($_POST['expiry_date'] ?? '') ?: null;
    $notes   = trim($_POST['notes'] ?? '');
    $active  = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) { $err = 'Item name is required.'; }
    else {
        if ($item) {
            db()->prepare('UPDATE items SET name=?,sku=?,category=?,unit=?,quantity=?,unit_price=?,
                           reorder_level=?,expiry_date=?,notes=?,is_active=?,updated_at=NOW()
                           WHERE id=? AND tenant_id=?')
               ->execute([$name,$sku,$cat,$unit,$qty,$price,$reorder,$expiry,$notes,$active,$id,$tid]);
            flash('success','Item updated.');
        } else {
            db()->prepare('INSERT INTO items (tenant_id,branch_id,name,sku,category,unit,quantity,
                           unit_price,reorder_level,expiry_date,notes,is_active,created_by)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
               ->execute([$tid,$bid,$name,$sku,$cat,$unit,$qty,$price,$reorder,$expiry,$notes,1,uid()]);
            flash('success','Item added.');
        }
        redirect('items.php');
    }
}

ob_start();
?>
<div style="max-width:760px">
<div class="card">
  <div class="card-title"><?= $page_title ?></div>
  <?php if (!empty($err)): ?><div class="alert alert-error"><?= h($err) ?></div><?php endif; ?>

  <form method="post">
    <div class="form-grid">
      <div class="form-group full">
        <label>Item Name *</label>
        <input type="text" name="name" value="<?= h($item['name'] ?? '') ?>" required placeholder="e.g. Paracetamol 500mg">
      </div>
      <div class="form-group">
        <label>SKU / Barcode</label>
        <input type="text" name="sku" value="<?= h($item['sku'] ?? '') ?>" placeholder="Optional">
      </div>
      <div class="form-group">
        <label>Category</label>
        <input type="text" name="category" value="<?= h($item['category'] ?? '') ?>" placeholder="e.g. Medicine, Food…">
      </div>
      <div class="form-group">
        <label>Unit</label>
        <select name="unit">
          <?php foreach (['pcs','kg','g','litre','ml','box','dozen','pack','bottle','bag','roll'] as $u): ?>
            <option value="<?= $u ?>" <?= ($item['unit']??'pcs')==$u?'selected':'' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Quantity in Stock</label>
        <input type="number" name="quantity" value="<?= h($item['quantity'] ?? 0) ?>" min="0" step="0.001">
      </div>
      <div class="form-group">
        <label>Unit Price</label>
        <input type="number" name="unit_price" value="<?= h($item['unit_price'] ?? 0) ?>" min="0" step="0.01">
      </div>
      <div class="form-group">
        <label>Reorder Level (alert when stock ≤)</label>
        <input type="number" name="reorder_level" value="<?= h($item['reorder_level'] ?? 0) ?>" min="0" step="0.001">
      </div>
      <div class="form-group">
        <label>Expiry Date</label>
        <input type="date" name="expiry_date" value="<?= h($item['expiry_date'] ?? '') ?>">
      </div>
      <div class="form-group full">
        <label>Notes</label>
        <textarea name="notes" placeholder="Storage instructions, warnings, additional info…"><?= h($item['notes'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label>Status</label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:6px;text-transform:none;font-size:14px">
          <input type="checkbox" name="is_active" <?= (!isset($item) || $item['is_active']) ? 'checked' : '' ?>>
          Active
        </label>
      </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px">
      <button type="submit" class="btn btn-primary">💾 Save Item</button>
      <a href="<?= APP_URL ?>/items.php" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
