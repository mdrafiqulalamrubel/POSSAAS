<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Users';
$tid = tid();

$branches_list = get_branches();

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d   = $_POST;
    $id  = (int)($d['id'] ?? 0);
    $bid = ($d['branch_id'] ?? '') ?: null;
    if ($id) {
        $sql = 'UPDATE users SET name=?,email=?,role=?,branch_id=?,is_active=? WHERE id=? AND tenant_id=?';
        $p   = [$d['name'],$d['email'],$d['role'],$bid,(int)($d['is_active']??1),$id,$tid];
        if (!empty($d['password'])) {
            $sql = 'UPDATE users SET name=?,email=?,role=?,branch_id=?,is_active=?,password=? WHERE id=? AND tenant_id=?';
            $p   = [$d['name'],$d['email'],$d['role'],$bid,(int)($d['is_active']??1),password_hash($d['password'],PASSWORD_BCRYPT),$id,$tid];
        }
        db()->prepare($sql)->execute($p);
        log_activity('update','Users','User updated: '.($d['name']??''),'ID:'.$id);
        flash('success','User updated.');
    } else {
        db()->prepare('INSERT INTO users (tenant_id,branch_id,name,email,password,role) VALUES (?,?,?,?,?,?)')
            ->execute([$tid,$bid,$d['name'],$d['email'],password_hash($d['password'],PASSWORD_BCRYPT),$d['role']]);
        log_activity('create','Users','User created: '.($d['name']??''),'Role:'.($d['role']??''));
        flash('success','User created.');
    }
    redirect('users.php');
}

if (isset($_GET['delete']) && (int)$_GET['delete'] !== uid()) {
    db()->prepare('DELETE FROM users WHERE id=? AND tenant_id=?')->execute([(int)$_GET['delete'],$tid]);
    log_activity('delete','Users','User deleted','ID:'.$did);
    flash('success','User deleted.'); redirect('users.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM users WHERE id=? AND tenant_id=?');
    $s->execute([(int)$_GET['edit'],$tid]); $edit = $s->fetch();
}

$rows = db()->prepare('SELECT u.*,b.name branch_name FROM users u
  LEFT JOIN branches b ON b.id=u.branch_id WHERE u.tenant_id=? ORDER BY u.name');
$rows->execute([$tid]); $users = $rows->fetchAll();

ob_start();
?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px">
  <div class="card">
    <div class="card-title"><?= $edit ? 'Edit User' : 'Add User' ?></div>
    <form method="post">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:12px"><label>Name *</label>
        <input type="text" name="name" required value="<?= h($edit['name']??'') ?>"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Email *</label>
        <input type="email" name="email" required value="<?= h($edit['email']??'') ?>"></div>
      <div class="form-group" style="margin-bottom:12px">
        <label>Password <?= $edit ? '(leave blank to keep)' : '*' ?></label>
        <input type="password" name="password" <?= $edit?'':'required' ?> placeholder="Min 6 chars" minlength="6"></div>
      <div class="form-group" style="margin-bottom:12px"><label>Role</label>
        <select name="role">
          <?php foreach (['cashier','manager','admin'] as $r): ?>
            <option value="<?= $r ?>" <?= ($edit['role']??'cashier')==$r?'selected':'' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group" style="margin-bottom:12px"><label>Branch (leave blank = all)</label>
        <select name="branch_id">
          <option value="">— All branches —</option>
          <?php foreach ($branches_list as $b): ?>
            <option value="<?= $b['id'] ?>" <?= ($edit['branch_id']??'')==$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="form-group" style="margin-bottom:16px"><label>Active</label>
        <select name="is_active">
          <option value="1" <?= ($edit['is_active']??1)==1?'selected':'' ?>>Yes</option>
          <option value="0" <?= ($edit['is_active']??1)==0?'selected':'' ?>>No</option>
        </select></div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary">💾 Save</button>
        <?php if ($edit): ?><a href="<?= APP_URL ?>/users.php" class="btn btn-outline">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-title">Users (<?= count($users) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Active</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h($u['name']) ?></td>
            <td><?= h($u['email']) ?></td>
            <td><span class="badge badge-<?= $u['role']==='admin'?'paid':($u['role']==='manager'?'partial':'draft') ?>"><?= ucfirst($u['role']) ?></span></td>
            <td><?= h($u['branch_name'] ?? 'All') ?></td>
            <td><?= $u['is_active'] ? '✓' : '—' ?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <?php if ($u['id'] != uid()): ?>
                <a href="?delete=<?= $u['id'] ?>" class="btn btn-danger btn-sm"
                   onclick="return confirm('Delete user?')">Del</a>
              <?php endif; ?>
            </td>
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
