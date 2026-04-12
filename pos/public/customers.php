<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('manager');
$page_title = 'Customers';
$tid = tid();

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = $_POST;
    $id = (int)($d['id'] ?? 0);
    if ($id) {
        db()->prepare('UPDATE customers SET name=?,email=?,phone=?,address=? WHERE id=? AND tenant_id=?')
            ->execute([$d['name'],$d['email']??'',$d['phone']??'',$d['address']??'',$id,$tid]);
        flash('success','Customer updated.');
    } else {
        db()->prepare('INSERT INTO customers (tenant_id,name,email,phone,address) VALUES (?,?,?,?,?)')
            ->execute([$tid,$d['name'],$d['email']??'',$d['phone']??'',$d['address']??'']);
        flash('success','Customer added.');
    }
    redirect('customers.php');
}

// Delete
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    db()->prepare('DELETE FROM customers WHERE id=? AND tenant_id=?')->execute([$did,$tid]);
    flash('success','Customer deleted.');
    redirect('customers.php');
}

// Edit load
$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM customers WHERE id=? AND tenant_id=?');
    $s->execute([(int)$_GET['edit'],$tid]); $edit = $s->fetch();
}

$search = trim($_GET['q'] ?? '');
$where  = 'tenant_id=?'; $params = [$tid];
if ($search) { $where .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
               $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$rows = db()->prepare("SELECT * FROM customers WHERE $where ORDER BY name");
$rows->execute($params); $customers = $rows->fetchAll();

ob_start();
?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px">
  <!-- Form -->
  <div class="card">
    <div class="card-title"><?= $edit ? 'Edit Customer' : 'Add Customer' ?></div>
    <form method="post">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:12px">
        <label>Name *</label>
        <input type="text" name="name" required value="<?= h($edit['name']??'') ?>">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($edit['email']??'') ?>">
      </div>
      <div class="form-group" style="margin-bottom:12px">
        <label>Phone</label>
        <input type="text" name="phone" value="<?= h($edit['phone']??'') ?>">
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label>Address</label>
        <textarea name="address" rows="2"><?= h($edit['address']??'') ?></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary">💾 Save</button>
        <?php if ($edit): ?><a href="<?= APP_URL ?>/customers.php" class="btn btn-outline">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="card">
    <div class="card-title">
      Customers (<?= count($customers) ?>)
      <form method="get" style="display:inline-flex;gap:8px;float:right">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search..." style="padding:5px 10px;font-size:13px">
        <button class="btn btn-outline btn-sm">Search</button>
      </form>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td><?= h($c['name']) ?></td>
            <td><?= h($c['email']) ?></td>
            <td><?= h($c['phone']) ?></td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm"
                 onclick="return confirm('Delete this customer?')">Del</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?><tr><td colspan="4" style="text-align:center;color:var(--c-muted)">No customers found</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
