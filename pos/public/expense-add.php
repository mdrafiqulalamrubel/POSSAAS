<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$tid = tid(); $bid = brid();
$edit_id = (int)($_GET['id'] ?? 0);
$page_title = $edit_id ? 'Edit Expense' : 'Add Expense';

$exp = null;
if ($edit_id) {
    $s = db()->prepare('SELECT * FROM expenses WHERE id=? AND tenant_id=? AND branch_id=?');
    $s->execute([$edit_id,$tid,$bid]); $exp = $s->fetch();
    if (!$exp) { flash('error','Expense not found'); redirect('expenses.php'); }
}

$cats = db()->prepare('SELECT * FROM expense_categories WHERE tenant_id=? ORDER BY name');
$cats->execute([$tid]); $categories = $cats->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $d = $_POST;
    if ($edit_id) {
        db()->prepare('UPDATE expenses SET category_id=?,supplier=?,description=?,date=?,amount=?,status=?,notes=?
                       WHERE id=? AND tenant_id=?')
            ->execute([($d['category_id']??'')?:null,$d['supplier']??'',$d['description']??'',
                       $d['date'],$d['amount'],$d['status']??'pending',$d['notes']??'',$edit_id,$tid]);
        flash('success','Expense updated.');
    } else {
        db()->prepare('INSERT INTO expenses (tenant_id,branch_id,category_id,supplier,description,date,amount,status,notes,created_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$tid,$bid,($d['category_id']??'')?:null,$d['supplier']??'',$d['description']??'',
                       $d['date'],$d['amount'],$d['status']??'pending',$d['notes']??'',uid()]);
        flash('success','Expense added.');
    }
    redirect('expenses.php');
}

ob_start();
?>
<form method="post">
<div class="card">
  <div class="form-grid">
    <div class="form-group">
      <label>Date</label>
      <input type="date" name="date" required value="<?= h($exp['date']??date('Y-m-d')) ?>">
    </div>
    <div class="form-group">
      <label>Amount (<?= CURRENCY ?>)</label>
      <input type="number" step="0.01" name="amount" required value="<?= h($exp['amount']??'') ?>" placeholder="0.00">
    </div>
    <div class="form-group">
      <label>Category</label>
      <select name="category_id">
        <option value="">— Select —</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($exp['category_id']??'')==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Supplier / Payee</label>
      <input type="text" name="supplier" value="<?= h($exp['supplier']??'') ?>" placeholder="Vendor name">
    </div>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <?php foreach (['pending','approved','paid','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= ($exp['status']??'pending')==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group full">
      <label>Description</label>
      <textarea name="description" rows="3" placeholder="Expense details..."><?= h($exp['description']??'') ?></textarea>
    </div>
    <div class="form-group full">
      <label>Notes</label>
      <textarea name="notes" rows="2"><?= h($exp['notes']??'') ?></textarea>
    </div>
  </div>
</div>
<div style="display:flex;gap:10px">
  <button type="submit" class="btn btn-primary">💾 Save</button>
  <a href="<?= APP_URL ?>/expenses.php" class="btn btn-outline">Cancel</a>
</div>
</form>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
