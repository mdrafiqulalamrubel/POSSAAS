<?php
require_once __DIR__ . '/../src/core.php';
$user = require_auth('manager');
$page_title = 'Expense Categories';
$tid = tid();

// ── Save / Update ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d   = $_POST;
    $id  = (int)($d['id'] ?? 0);
    $name = trim($d['name'] ?? '');
    $desc = trim($d['description'] ?? '');

    if (!$name) {
        flash('error', 'Category name is required.');
        redirect('expense-categories.php');
    }

    if ($id) {
        db()->prepare('UPDATE expense_categories SET name=?, description=? WHERE id=? AND tenant_id=?')
            ->execute([$name, $desc, $id, $tid]);
        flash('success', 'Category updated.');
    } else {
        db()->prepare('INSERT INTO expense_categories (tenant_id, name, description) VALUES (?, ?, ?)')
            ->execute([$tid, $name, $desc]);
        flash('success', 'Category added.');
    }
    redirect('expense-categories.php');
}

// ── Delete ────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    // Check if category is in use
    $inuse = db()->prepare('SELECT COUNT(*) FROM expenses WHERE tenant_id=? AND category_id=?');
    $inuse->execute([$tid, $did]);
    if ((int)$inuse->fetchColumn() > 0) {
        flash('error', 'Cannot delete — this category has expenses linked to it.');
    } else {
        db()->prepare('DELETE FROM expense_categories WHERE id=? AND tenant_id=?')
            ->execute([$did, $tid]);
        flash('success', 'Category deleted.');
    }
    redirect('expense-categories.php');
}

// ── Edit load ─────────────────────────────────────────────────
$edit = null;
if (isset($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM expense_categories WHERE id=? AND tenant_id=?');
    $s->execute([(int)$_GET['edit'], $tid]);
    $edit = $s->fetch();
}

// ── List with expense count ───────────────────────────────────
// FIX: expenses table uses category_id (FK) not e.category (text column)
// We LEFT JOIN on category_id so we count only expenses linked to each category
$cats = db()->prepare("
    SELECT ec.*,
           COALESCE(SUM(e.amount), 0) AS total_spent,
           COUNT(e.id)               AS expense_count
    FROM expense_categories ec
    LEFT JOIN expenses e
           ON e.category_id = ec.id
          AND e.tenant_id   = ec.tenant_id
          AND e.status      != 'cancelled'
    WHERE ec.tenant_id = ?
    GROUP BY ec.id
    ORDER BY ec.name ASC
");
$cats->execute([$tid]);
$categories = $cats->fetchAll();

ob_start();
?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px">

  <!-- Form -->
  <div class="card">
    <div class="card-title"><?= $edit ? 'Edit Category' : 'Add Expense Category' ?></div>
    <form method="post">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
      <div class="form-group" style="margin-bottom:12px">
        <label>Category Name *</label>
        <input type="text" name="name" required value="<?= h($edit['name'] ?? '') ?>" placeholder="e.g. Rent, Utilities, Salary…">
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Optional description"><?= h($edit['description'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-primary">💾 Save</button>
        <?php if ($edit): ?><a href="<?= APP_URL ?>/expense-categories.php" class="btn btn-outline">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="card">
    <div class="card-title">Expense Categories (<?= count($categories) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Category Name</th>
            <th>Description</th>
            <th style="text-align:right">Total Spent</th>
            <th style="text-align:center"># Expenses</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><strong><?= h($c['name']) ?></strong></td>
            <td style="color:var(--c-muted);font-size:13px"><?= h($c['description'] ?: '—') ?></td>
            <td style="text-align:right;font-weight:700"><?= money((float)$c['total_spent']) ?></td>
            <td style="text-align:center">
              <span class="badge badge-paid"><?= $c['expense_count'] ?></span>
            </td>
            <td style="white-space:nowrap">
              <a href="?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
              <?php if ((int)$c['expense_count'] === 0): ?>
              <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm"
                 onclick="return confirm('Delete this category?')">Del</a>
              <?php else: ?>
              <span class="btn btn-outline btn-sm" style="opacity:.45;cursor:not-allowed" title="In use — cannot delete">Del</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$categories): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--c-muted);padding:30px">
            No categories yet. Add your first one →
          </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
