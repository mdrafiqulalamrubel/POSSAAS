<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Branches';
$tid = tid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d  = $_POST;
    $id = (int)($d['id'] ?? 0);
    if ($id) {
        db()->prepare('UPDATE branches SET name=?,address=?,phone=?,is_active=? WHERE id=? AND tenant_id=?')
            ->execute([$d['name'],$d['address']??'',$d['phone']??'',(int)($d['is_active']??1),$id,$tid]);
        flash('success','Branch updated.');
    } else {
        db()->prepare('INSERT INTO branches (tenant_id,name,address,phone) VALUES (?,?,?,?)')
            ->execute([$tid,$d['name'],$d['address']??'',$d['phone']??'']);
        $new_bid = db()->lastInsertId();
        // create invoice sequence for new branch
        db()->prepare('INSERT IGNORE INTO invoice_sequences (tenant_id,branch_id,prefix,last_number) VALUES (?,?,?,0)')
            ->execute([$tid,$new_bid,INVOICE_PREFIX]);
        flash('success','Branch added.');
    }
    redirect('branches.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM branches WHERE id=? AND tenant_id=?');
    $s->execute([(int)$_GET['edit'],$tid]); $edit = $s->fetch();
}

$rows = db()->prepare('SELECT b.*,
    (SELECT COUNT(*) FROM income i WHERE i.branch_id=b.id) inv_count,
    (SELECT COUNT(*) FROM expenses e WHERE e.branch_id=b.id) exp_count
  FROM branches b WHERE b.tenant_id=? ORDER BY b.name');
$rows->execute([$tid]); $branches = $rows->fetchAll();

ob_start();
?>
<div style="display:grid;grid-template-columns:320px 1fr;gap:20px">
  <div class="card">
    <div class="card-title"><?= $edit ? 'Edit Branch' : 'Add Branch' ?></div>
    <form method="post">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:12px"><label>Branch Name *</label>
        <input type="text" name="name" required value="<?= h($edit['name']??'') ?>"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Phone</label>
        <input type="text" name="phone" value="<?= h($edit['phone']??'') ?>"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Address</label>
        <textarea name="address" rows="3"><?= h($edit['address']??'') ?></textarea></div>
      <?php if ($edit): ?>
      <div class="form-group" style="margin-bottom:16px"><label>Active</label>
        <select name="is_active">
          <option value="1" <?= ($edit['is_active']??1)?'selected':'' ?>>Yes</option>
          <option value="0" <?= ($edit['is_active']??1)?'':'selected' ?>>No</option>
        </select></div>
      <?php endif; ?>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary">💾 Save</button>
        <?php if ($edit): ?><a href="<?= APP_URL ?>/branches.php" class="btn btn-outline">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Branches (<?= count($branches) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Address</th><th>Invoices</th><th>Expenses</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($branches as $b): ?>
          <tr>
            <td><strong><?= h($b['name']) ?></strong></td>
            <td><?= h($b['phone']) ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($b['address']) ?></td>
            <td><?= number_format($b['inv_count']) ?></td>
            <td><?= number_format($b['exp_count']) ?></td>
            <td><span class="badge <?= $b['is_active']?'badge-paid':'badge-cancelled' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span></td>
            <td><a href="?edit=<?= $b['id'] ?>" class="btn btn-outline btn-sm">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
