<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('superadmin');
$page_title = 'Super Admin — Company Management';

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create new company (tenant)
    if ($action === 'create_company') {
        $name  = trim($_POST['company_name'] ?? '');
        $slug  = strtolower(preg_replace('/[^a-z0-9]+/i','-', $name));
        $slug  = trim($slug, '-');
        $plan  = $_POST['plan'] ?? 'trial';
        $bname = trim($_POST['branch_name'] ?? 'Main Branch') ?: 'Main Branch';
        $aname = trim($_POST['admin_name']  ?? '');
        $amail = trim($_POST['admin_email'] ?? '');
        $apass = $_POST['admin_password']   ?? '';

        if (!$name || !$aname || !$amail || strlen($apass) < 6) {
            flash('error','Company name, admin name, email and password (min 6) are required.');
        } else {
            // Check slug unique
            $chk = db()->prepare('SELECT id FROM tenants WHERE slug=?');
            $chk->execute([$slug]);
            if ($chk->fetch()) $slug .= '-' . time();

            try {
                db()->beginTransaction();
                // Insert tenant
                db()->prepare('INSERT INTO tenants (name,slug,plan) VALUES (?,?,?)')->execute([$name,$slug,$plan]);
                $tid = (int)db()->lastInsertId();
                // Insert main branch
                db()->prepare('INSERT INTO branches (tenant_id,name) VALUES (?,?)')->execute([$tid,$bname]);
                $bid = (int)db()->lastInsertId();
                // Insert default company settings
                db()->prepare('INSERT INTO company_settings (tenant_id,company_name,currency,currency_code,tax_label,invoice_prefix)
                               VALUES (?,?,?,?,?,?)')->execute([$tid,$name,'$','USD','VAT','INV']);
                // Insert admin user
                db()->prepare('INSERT INTO users (tenant_id,branch_id,name,email,password,role) VALUES (?,NULL,?,?,?,?)')
                    ->execute([$tid,$aname,$amail,password_hash($apass,PASSWORD_BCRYPT),'admin']);
                // Insert invoice sequence
                db()->prepare('INSERT INTO invoice_sequences (tenant_id,branch_id,prefix,last_number) VALUES (?,?,?,0)')
                    ->execute([$tid,$bid,'INV']);
                db()->commit();
                flash('success',"Company '$name' created. Admin: $amail / $apass");
            } catch (\Exception $e) {
                db()->rollBack();
                flash('error','Error: ' . $e->getMessage());
            }
        }
        redirect('superadmin.php');
    }

    // Toggle company active
    if ($action === 'toggle_company') {
        $cid = (int)($_POST['company_id'] ?? 0);
        $cur = db()->prepare('SELECT is_active FROM tenants WHERE id=?');
        $cur->execute([$cid]); $cur = (int)$cur->fetchColumn();
        db()->prepare('UPDATE tenants SET is_active=? WHERE id=?')->execute([$cur ? 0 : 1, $cid]);
        flash('success','Company status updated.');
        redirect('superadmin.php');
    }

    // Update company plan
    if ($action === 'update_plan') {
        $cid  = (int)($_POST['company_id'] ?? 0);
        $plan = $_POST['plan'] ?? 'trial';
        db()->prepare('UPDATE tenants SET plan=? WHERE id=?')->execute([$plan, $cid]);
        flash('success','Plan updated.');
        redirect('superadmin.php');
    }

    // Reset admin password for a company
    if ($action === 'reset_password') {
        $uid2 = (int)($_POST['user_id']  ?? 0);
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 6) { flash('error','Password must be at least 6 characters.'); }
        else {
            db()->prepare('UPDATE users SET password=? WHERE id=?')
                ->execute([password_hash($pass, PASSWORD_BCRYPT), $uid2]);
            flash('success','Password reset successfully.');
        }
        redirect('superadmin.php');
    }
}

// Load all companies with stats
$companies = db()->query("
    SELECT t.*,
        cs.company_name, cs.currency, cs.logo_path,
        (SELECT COUNT(*) FROM users u WHERE u.tenant_id=t.id AND u.is_active=1) user_count,
        (SELECT COUNT(*) FROM branches b WHERE b.tenant_id=t.id AND b.is_active=1) branch_count,
        (SELECT COUNT(*) FROM income i WHERE i.tenant_id=t.id) invoice_count,
        (SELECT COALESCE(SUM(total),0) FROM income i WHERE i.tenant_id=t.id) total_revenue,
        (SELECT u.id FROM users u WHERE u.tenant_id=t.id AND u.role='admin' LIMIT 1) admin_id,
        (SELECT u.name FROM users u WHERE u.tenant_id=t.id AND u.role='admin' LIMIT 1) admin_name,
        (SELECT u.email FROM users u WHERE u.tenant_id=t.id AND u.role='admin' LIMIT 1) admin_email
    FROM tenants t
    LEFT JOIN company_settings cs ON cs.tenant_id=t.id
    ORDER BY t.id ASC
")->fetchAll();

ob_start();
?>
<style>
.company-card { background:var(--c-card);border:1px solid var(--c-border);border-radius:var(--radius);padding:18px 20px;margin-bottom:14px;display:grid;grid-template-columns:1fr auto;gap:12px;align-items:start; }
.company-card.inactive { opacity:.6;border-style:dashed; }
.company-title { font-size:16px;font-weight:700;margin-bottom:4px; }
.company-meta  { font-size:12px;color:var(--c-muted);margin-bottom:8px; }
.company-stats { display:flex;gap:16px;flex-wrap:wrap;font-size:13px; }
.stat-pill { background:var(--c-bg);padding:3px 10px;border-radius:20px;border:1px solid var(--c-border); }
.plan-badge { display:inline-block;padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase; }
.plan-pro   { background:#4f46e5;color:#fff; }
.plan-basic { background:#0ea5e9;color:#fff; }
.plan-trial { background:#f59e0b;color:#fff; }
</style>

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start">

<!-- Company List -->
<div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <h2 style="margin:0;font-size:18px">All Companies (<?= count($companies) ?>)</h2>
  </div>

  <?php foreach ($companies as $co): ?>
  <div class="company-card <?= $co['is_active'] ? '' : 'inactive' ?>">
    <div>
      <div class="company-title">
        <?= h($co['company_name'] ?: $co['name']) ?>
        <span class="plan-badge plan-<?= $co['plan'] ?>"><?= $co['plan'] ?></span>
        <?php if (!$co['is_active']): ?><span style="font-size:12px;color:var(--c-expense);margin-left:6px">● Suspended</span><?php endif; ?>
      </div>
      <div class="company-meta">
        Slug: <code><?= h($co['slug']) ?></code> &nbsp;·&nbsp;
        Admin: <strong><?= h($co['admin_name'] ?? '—') ?></strong> (<?= h($co['admin_email'] ?? '—') ?>)
        &nbsp;·&nbsp; Created: <?= date('d M Y', strtotime($co['created_at'])) ?>
      </div>
      <div class="company-stats">
        <span class="stat-pill">👥 <?= $co['user_count'] ?> Users</span>
        <span class="stat-pill">🏬 <?= $co['branch_count'] ?> Branches</span>
        <span class="stat-pill">📄 <?= $co['invoice_count'] ?> Invoices</span>
        <span class="stat-pill income">💰 <?= ($co['currency']??'$') . number_format($co['total_revenue'],0) ?> Revenue</span>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:8px;min-width:180px">
      <!-- Toggle active -->
      <form method="post">
        <input type="hidden" name="action" value="toggle_company">
        <input type="hidden" name="company_id" value="<?= $co['id'] ?>">
        <button class="btn <?= $co['is_active'] ? 'btn-danger' : 'btn-primary' ?> btn-sm" style="width:100%"
                onclick="return confirm('<?= $co['is_active'] ? 'Suspend' : 'Activate' ?> this company?')">
          <?= $co['is_active'] ? '🚫 Suspend' : '✅ Activate' ?>
        </button>
      </form>

      <!-- Change Plan -->
      <form method="post" style="display:flex;gap:4px">
        <input type="hidden" name="action" value="update_plan">
        <input type="hidden" name="company_id" value="<?= $co['id'] ?>">
        <select name="plan" style="flex:1;font-size:12px;padding:4px">
          <?php foreach (['trial','basic','pro'] as $pl): ?>
            <option value="<?= $pl ?>" <?= $co['plan']===$pl?'selected':'' ?>><?= ucfirst($pl) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm">Set</button>
      </form>

      <!-- Reset Admin Password -->
      <?php if ($co['admin_id']): ?>
      <form method="post" style="display:flex;gap:4px">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" value="<?= $co['admin_id'] ?>">
        <input type="password" name="new_password" placeholder="New password" style="flex:1;font-size:12px;padding:4px" minlength="6">
        <button class="btn btn-outline btn-sm">🔑</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Create New Company -->
<div class="card" style="position:sticky;top:20px">
  <div class="card-title">➕ Create New Company</div>
  <form method="post">
    <input type="hidden" name="action" value="create_company">
    <div class="form-group" style="margin-bottom:10px">
      <label>Company Name *</label>
      <input type="text" name="company_name" required placeholder="Acme Corp">
    </div>
    <div class="form-group" style="margin-bottom:10px">
      <label>Plan</label>
      <select name="plan">
        <option value="trial">Trial</option>
        <option value="basic">Basic</option>
        <option value="pro">Pro</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:10px">
      <label>First Branch Name</label>
      <input type="text" name="branch_name" placeholder="Main Branch" value="Main Branch">
    </div>
    <div style="font-size:12px;font-weight:600;color:var(--c-muted);text-transform:uppercase;letter-spacing:.06em;margin:14px 0 8px;border-top:1px solid var(--c-border);padding-top:12px">Admin Account</div>
    <div class="form-group" style="margin-bottom:10px">
      <label>Admin Name *</label>
      <input type="text" name="admin_name" required placeholder="John Smith">
    </div>
    <div class="form-group" style="margin-bottom:10px">
      <label>Admin Email *</label>
      <input type="email" name="admin_email" required placeholder="admin@company.com">
    </div>
    <div class="form-group" style="margin-bottom:16px">
      <label>Admin Password * (min 6)</label>
      <input type="text" name="admin_password" required minlength="6" placeholder="Set a secure password">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">🏢 Create Company</button>
  </form>
</div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
