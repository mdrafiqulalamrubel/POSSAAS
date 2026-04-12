<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Admin CMS';
$tid = tid();

$tab = $_GET['tab'] ?? 'overview';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Change admin password
    if ($action === 'change_password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $con = $_POST['confirm_password'] ?? '';
        $row = db()->prepare('SELECT password FROM users WHERE id=?');
        $row->execute([uid()]); $row = $row->fetch();
        if (!password_verify($cur, $row['password'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $con) {
            flash('error', 'Passwords do not match.');
        } else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($new, PASSWORD_BCRYPT), uid()]);
            flash('success', 'Password changed successfully.');
        }
        redirect('admin-cms.php?tab=security');
    }

    // Create user
    if ($action === 'create_user') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = $_POST['role'] ?? 'cashier';
        $bid   = (int)($_POST['branch_id'] ?? 0) ?: null;
        if (!$name || !$email || strlen($pass) < 6) {
            flash('error', 'Name, email and password (min 6 chars) required.');
        } else {
            try {
                db()->prepare('INSERT INTO users (tenant_id,branch_id,name,email,password,role) VALUES (?,?,?,?,?,?)')
                    ->execute([$tid, $bid, $name, $email, password_hash($pass, PASSWORD_BCRYPT), $role]);
                flash('success', "User '$name' created.");
            } catch (\Exception $e) {
                flash('error', 'Email already exists for this tenant.');
            }
        }
        redirect('admin-cms.php?tab=users');
    }

    // Toggle user active
    if ($action === 'toggle_user') {
        $uid2 = (int)($_POST['user_id'] ?? 0);
        if ($uid2 !== uid()) {
            $cur_stmt = db()->prepare('SELECT is_active FROM users WHERE id=? AND tenant_id=?');
            $cur_stmt->execute([$uid2, $tid]);
            $cur_val = (int)$cur_stmt->fetchColumn();
            db()->prepare('UPDATE users SET is_active=? WHERE id=? AND tenant_id=?')
                ->execute([$cur_val ? 0 : 1, $uid2, $tid]);
            flash('success', 'User status updated.');
        }
        redirect('admin-cms.php?tab=users');
    }

    // Save reorder levels
    if ($action === 'save_reorder') {
        $ids    = $_POST['item_id'] ?? [];
        $levels = $_POST['reorder_level'] ?? [];
        $stmt   = db()->prepare('UPDATE items SET reorder_level=? WHERE id=? AND tenant_id=?');
        $count  = 0;
        foreach ($ids as $i => $iid) {
            $level = (float)($levels[$i] ?? 0);
            $stmt->execute([$level, (int)$iid, $tid]);
            $count++;
        }
        flash('success', "Reorder levels saved for $count items.");
        redirect('admin-cms.php?tab=reorder');
    }
}

// ── Shared data ───────────────────────────────────────────────
$branches   = get_branches();
$bid_active = brid();

// Always initialise these to avoid undefined-variable errors
// on tabs that don't populate them (fix for blank-page bug)
$stats         = ['users'=>0,'items'=>0,'unpaid_inv'=>0,'low_stock'=>0];
$all_users     = [];
$reorder_items = [];
$bid_filter    = $bid_active;

// ── Overview: use separate queries — PDO unnamed ? placeholders
//    cannot be repeated across subqueries reliably ─────────────
if ($tab === 'overview') {
    $q1 = db()->prepare('SELECT COUNT(*) FROM users WHERE tenant_id=? AND is_active=1');
    $q1->execute([$tid]);

    $q2 = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND is_active=1');
    $q2->execute([$tid]);

    $q3 = db()->prepare("SELECT COUNT(*) FROM income WHERE tenant_id=? AND status='unpaid'");
    $q3->execute([$tid]);

    $q4 = db()->prepare('SELECT COUNT(*) FROM items WHERE tenant_id=? AND reorder_level>0 AND quantity<=reorder_level AND is_active=1');
    $q4->execute([$tid]);

    $stats = [
        'users'      => (int)$q1->fetchColumn(),
        'items'      => (int)$q2->fetchColumn(),
        'unpaid_inv' => (int)$q3->fetchColumn(),
        'low_stock'  => (int)$q4->fetchColumn(),
    ];
}

if ($tab === 'users') {
    $u_stmt = db()->prepare(
        'SELECT u.*, b.name branch_name
         FROM users u
         LEFT JOIN branches b ON b.id=u.branch_id
         WHERE u.tenant_id=?
         ORDER BY u.role DESC, u.name'
    );
    $u_stmt->execute([$tid]);
    $all_users = $u_stmt->fetchAll();
}

if ($tab === 'reorder') {
    $bid_filter = (int)($_GET['bid'] ?? $bid_active);
    $r_stmt = db()->prepare(
        'SELECT * FROM items
         WHERE tenant_id=? AND branch_id=? AND is_active=1
         ORDER BY category, name'
    );
    $r_stmt->execute([$tid, $bid_filter]);
    $reorder_items = $r_stmt->fetchAll();
}

ob_start();
?>
<!-- Tab Nav -->
<div style="display:flex;gap:4px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach ([
    'overview' => '📊 Overview',
    'users'    => '👥 Users',
    'reorder'  => '📦 Reorder Levels',
    'security' => '🔐 Security',
  ] as $t => $label): ?>
    <a href="?tab=<?= $t ?>" class="btn <?= $tab===$t ? 'btn-primary' : 'btn-outline' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- Overview -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-label">Active Users</div><div class="stat-value"><?= $stats['users'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Inventory Items</div><div class="stat-value"><?= $stats['items'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Unpaid Invoices</div><div class="stat-value" style="color:var(--c-warn)"><?= $stats['unpaid_inv'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Low / Out of Stock</div><div class="stat-value expense"><?= $stats['low_stock'] ?></div></div>
</div>
<div class="card" style="margin-top:20px">
  <div class="card-title">Quick Links</div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;padding-top:4px">
    <a href="<?= APP_URL ?>/company-setup.php"                   class="btn btn-outline">🏢 Company Setup</a>
    <a href="<?= APP_URL ?>/inventory-report.php"                class="btn btn-outline">📋 Inventory Report</a>
    <a href="<?= APP_URL ?>/inventory-report.php?filter=reorder" class="btn btn-outline">⚠ Low Stock Report</a>
    <a href="<?= APP_URL ?>/users.php"                           class="btn btn-outline">👤 Manage Users</a>
    <a href="<?= APP_URL ?>/branches.php"                        class="btn btn-outline">🏬 Branches</a>
    <a href="<?= APP_URL ?>/report.php"                          class="btn btn-outline">📈 Financial Report</a>
    <a href="?tab=reorder"                                       class="btn btn-outline">📦 Reorder Levels</a>
  </div>
</div>

<?php elseif ($tab === 'users'): ?>
<!-- Users Management -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <div class="card" style="padding:0">
    <div style="padding:16px 20px;border-bottom:1px solid var(--c-border)"><strong>All Users</strong></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($all_users as $u): ?>
          <tr>
            <td>
              <strong><?= h($u['name']) ?></strong>
              <?= $u['id']==uid() ? ' <span style="font-size:11px;color:var(--c-muted)">(you)</span>' : '' ?>
            </td>
            <td style="font-size:13px"><?= h($u['email']) ?></td>
            <td><span class="badge badge-<?= in_array($u['role'],['admin','superadmin'])?'paid':'partial' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td style="font-size:13px"><?= h($u['branch_name'] ?: 'All') ?></td>
            <td><span class="badge badge-<?= $u['is_active']?'paid':'cancelled' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <?php if ($u['id'] !== uid()): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-outline btn-sm" onclick="return confirm('Toggle this user?')"><?= $u['is_active']?'Disable':'Enable' ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$all_users): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--c-muted);padding:20px">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Create User -->
  <div class="card">
    <div class="card-title">➕ Create User</div>
    <form method="post">
      <input type="hidden" name="action" value="create_user">
      <div class="form-group"><label>Full Name *</label><input type="text" name="name" required placeholder="John Smith"></div>
      <div class="form-group"><label>Email *</label><input type="email" name="email" required placeholder="john@company.com"></div>
      <div class="form-group"><label>Password * (min 6)</label><input type="password" name="password" required minlength="6"></div>
      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="cashier">Cashier</option>
          <option value="manager">Manager</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label>Branch (leave blank = all)</label>
        <select name="branch_id">
          <option value="">— All Branches —</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= h($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:6px">Create User</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'reorder'): ?>
<!-- Reorder Levels -->
<div class="card" style="margin-bottom:16px;padding:14px">
  <form method="get" style="display:flex;gap:10px;align-items:flex-end">
    <input type="hidden" name="tab" value="reorder">
    <div class="form-group" style="margin:0">
      <label>Branch</label>
      <select name="bid">
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $b['id']==$bid_filter?'selected':'' ?>><?= h($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-outline">Load</button>
  </form>
</div>

<form method="post">
  <input type="hidden" name="action" value="save_reorder">
  <div class="card" style="padding:0">
    <div style="padding:14px 18px;border-bottom:1px solid var(--c-border);display:flex;justify-content:space-between;align-items:center">
      <strong>Set Reorder Levels</strong>
      <button type="submit" class="btn btn-primary btn-sm">💾 Save All</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Item</th><th>Category</th>
            <th style="text-align:right">Current Stock</th>
            <th style="text-align:right;width:160px">Reorder Level</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reorder_items as $it):
          $low = $it['reorder_level'] > 0 && $it['quantity'] <= $it['reorder_level'];
          $out = $it['quantity'] <= 0;
        ?>
          <tr>
            <td>
              <input type="hidden" name="item_id[]" value="<?= $it['id'] ?>">
              <strong><?= h($it['name']) ?></strong>
              <?php if ($it['sku']): ?><br><small style="font-family:monospace;color:var(--c-muted)"><?= h($it['sku']) ?></small><?php endif; ?>
            </td>
            <td><?= h($it['category'] ?: '—') ?></td>
            <td style="text-align:right;color:<?= $out?'var(--c-expense)':($low?'var(--c-warn)':'inherit') ?>;font-weight:<?= ($low||$out)?'600':'400' ?>">
              <?= rtrim(rtrim(number_format($it['quantity'],3,'.',','),'0'),'.') ?> <?= h($it['unit']) ?>
            </td>
            <td style="text-align:right">
              <input type="number" name="reorder_level[]"
                     value="<?= h($it['reorder_level'] + 0) ?>"
                     step="0.01" min="0" style="width:120px;text-align:right"
                     placeholder="0 = off">
            </td>
            <td>
              <?php if ($out): ?><span class="badge badge-unpaid">Out of Stock</span>
              <?php elseif ($low): ?><span class="badge badge-partial">⚠ Low</span>
              <?php else: ?><span class="badge badge-paid">OK</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$reorder_items): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:24px">No items found for this branch.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="padding:14px 18px;border-top:1px solid var(--c-border)">
      <button type="submit" class="btn btn-primary">💾 Save Reorder Levels</button>
      <small style="color:var(--c-muted);margin-left:12px">Set to 0 to disable reorder alert for that item.</small>
    </div>
  </div>
</form>

<?php elseif ($tab === 'security'): ?>
<!-- Security / Password Change -->
<div style="max-width:480px">
  <div class="card">
    <div class="card-title">🔐 Change Your Password</div>
    <form method="post">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label>Current Password</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-group">
        <label>New Password (min 6)</label>
        <input type="password" name="new_password" required minlength="6">
      </div>
      <div class="form-group">
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required minlength="6">
      </div>
      <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
  </div>

  <div class="card" style="margin-top:16px">
    <div class="card-title">ℹ Admin Account Info</div>
    <table style="font-size:13px;width:100%">
      <tr><td style="padding:6px 0;color:var(--c-muted)">Name</td><td><?= h($user['name']) ?></td></tr>
      <tr><td style="padding:6px 0;color:var(--c-muted)">Email</td><td><?= h($user['email']) ?></td></tr>
      <tr><td style="padding:6px 0;color:var(--c-muted)">Role</td><td><?= ucfirst($user['role']) ?></td></tr>
      <tr><td style="padding:6px 0;color:var(--c-muted)">Tenant</td><td><?= h($user['tenant_name']) ?></td></tr>
    </table>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
