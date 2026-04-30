<?php
/**
 * activity-logs.php  — Fixed version
 *
 * FIXES:
 *  1. user_id not recording → log_activity() now reads from $_SESSION correctly
 *  2. Double-execute bug removed (was running COUNT query twice / incorrectly)
 *  3. Added per-page selector
 *  4. Shows "No logs yet" with helpful tip when table is empty
 *
 * SQL (run once if not yet done):
 * CREATE TABLE IF NOT EXISTS activity_logs (
 *   id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   tenant_id   INT NOT NULL,
 *   branch_id   INT NOT NULL DEFAULT 0,
 *   user_id     INT NOT NULL DEFAULT 0,
 *   user_name   VARCHAR(120) NOT NULL DEFAULT '',
 *   user_role   VARCHAR(40)  NOT NULL DEFAULT '',
 *   action      VARCHAR(40)  NOT NULL DEFAULT 'other',
 *   module      VARCHAR(60)  NOT NULL DEFAULT '',
 *   description TEXT,
 *   meta        TEXT,
 *   ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
 *   created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   INDEX idx_tenant  (tenant_id),
 *   INDEX idx_created (created_at),
 *   INDEX idx_user    (user_id),
 *   INDEX idx_action  (action)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$page_title = 'Activity Logs';
$tid = tid();

// ── Pagination ────────────────────────────────────────────
$per_page = max(10, min(200, (int)($_GET['per_page'] ?? 50)));
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── Filters ───────────────────────────────────────────────
$filter_action = trim($_GET['action']  ?? '');
$filter_user   = (int)($_GET['user_id'] ?? 0);
$filter_module = trim($_GET['module']  ?? '');
$filter_from   = $_GET['from'] ?? date('Y-m-01');
$filter_to     = $_GET['to']   ?? date('Y-m-d');
$filter_search = trim($_GET['q'] ?? '');

// ── Build WHERE ───────────────────────────────────────────
$where  = ['l.tenant_id = ?'];
$params = [$tid];

if ($filter_from && $filter_to) {
    $where[]  = 'DATE(l.created_at) BETWEEN ? AND ?';
    $params[] = $filter_from;
    $params[] = $filter_to;
}
if ($filter_action !== '') { $where[] = 'l.action = ?';   $params[] = $filter_action; }
if ($filter_user > 0)      { $where[] = 'l.user_id = ?';  $params[] = $filter_user; }
if ($filter_module !== '')  { $where[] = 'l.module = ?';   $params[] = $filter_module; }
if ($filter_search !== '')  {
    $where[]  = '(l.description LIKE ? OR l.user_name LIKE ? OR l.meta LIKE ?)';
    $like     = '%' . $filter_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereStr = implode(' AND ', $where);

// ── COUNT (fixed — single prepared statement) ─────────────
$cnt_stmt = db()->prepare("SELECT COUNT(*) FROM activity_logs l WHERE $whereStr");
$cnt_stmt->execute($params);
$total_rows  = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// ── Fetch rows ────────────────────────────────────────────
$rows_stmt = db()->prepare(
    "SELECT l.* FROM activity_logs l WHERE $whereStr ORDER BY l.created_at DESC LIMIT $per_page OFFSET $offset"
);
$rows_stmt->execute($params);
$logs = $rows_stmt->fetchAll();

// ── Sidebar filter data ───────────────────────────────────
$users_stmt = db()->prepare(
    'SELECT DISTINCT user_id, user_name FROM activity_logs WHERE tenant_id=? AND user_id > 0 ORDER BY user_name'
);
$users_stmt->execute([$tid]);
$all_users = $users_stmt->fetchAll();

$mod_stmt = db()->prepare(
    "SELECT DISTINCT module FROM activity_logs WHERE tenant_id=? AND module != '' ORDER BY module"
);
$mod_stmt->execute([$tid]);
$all_modules = $mod_stmt->fetchAll(PDO::FETCH_COLUMN);

// ── Action map ────────────────────────────────────────────
$action_map = [
    'create'  => ['label'=>'Create',  'class'=>'log-create'],
    'update'  => ['label'=>'Update',  'class'=>'log-update'],
    'delete'  => ['label'=>'Delete',  'class'=>'log-delete'],
    'login'   => ['label'=>'Login',   'class'=>'log-login'],
    'logout'  => ['label'=>'Logout',  'class'=>'log-logout'],
    'payment' => ['label'=>'Payment', 'class'=>'log-payment'],
    'export'  => ['label'=>'Export',  'class'=>'log-export'],
    'other'   => ['label'=>'Other',   'class'=>'log-other'],
];

// ── CSV Export ────────────────────────────────────────────
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    $exp = db()->prepare("SELECT l.* FROM activity_logs l WHERE $whereStr ORDER BY l.created_at DESC");
    $exp->execute($params);
    $exp_rows = $exp->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['ID','Date','Time','User','User ID','Role','IP','Action','Module','Description','Meta']);
    foreach ($exp_rows as $r) {
        fputcsv($out, [
            $r['id'],
            date('d/m/Y', strtotime($r['created_at'])),
            date('H:i:s', strtotime($r['created_at'])),
            $r['user_name'], $r['user_id'], $r['user_role'],
            $r['ip_address'], $r['action'], $r['module'],
            $r['description'], $r['meta']
        ]);
    }
    fclose($out); exit;
}

ob_start();
?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-size:18px;font-weight:800;margin:0">📝 Activity Logs</h2>
    <div style="font-size:13px;color:#6b7280;margin-top:2px">
      <?= number_format($total_rows) ?> record<?= $total_rows!=1?'s':'' ?> found
      <?php if ($total_rows === 0 && empty($filter_action) && empty($filter_user) && empty($filter_search)): ?>
        — <span style="color:#d97706">ℹ️ Tip: Call <code>log_activity()</code> from your PHP actions to populate this log.</span>
      <?php endif; ?>
    </div>
  </div>
  <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv','page'=>1])) ?>" class="btn btn-outline btn-sm no-print">⬇ Export CSV</a>
</div>

<!-- Filters -->
<form method="get" class="card no-print" style="padding:16px;margin-bottom:16px">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div class="form-group" style="min-width:120px">
      <label>From</label>
      <input type="date" name="from" value="<?= h($filter_from) ?>">
    </div>
    <div class="form-group" style="min-width:120px">
      <label>To</label>
      <input type="date" name="to" value="<?= h($filter_to) ?>">
    </div>
    <div class="form-group" style="min-width:130px">
      <label>Action</label>
      <select name="action">
        <option value="">All Actions</option>
        <?php foreach ($action_map as $k=>$v): ?>
          <option value="<?= h($k) ?>" <?= $filter_action===$k?'selected':'' ?>><?= h($v['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:150px">
      <label>User</label>
      <select name="user_id">
        <option value="">All Users</option>
        <?php foreach ($all_users as $u): ?>
          <option value="<?= (int)$u['user_id'] ?>" <?= $filter_user==(int)$u['user_id']?'selected':'' ?>>
            <?= h($u['user_name']) ?> (ID: <?= (int)$u['user_id'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:130px">
      <label>Module</label>
      <select name="module">
        <option value="">All Modules</option>
        <?php foreach ($all_modules as $m): ?>
          <option value="<?= h($m) ?>" <?= $filter_module===$m?'selected':'' ?>><?= h($m) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="min-width:180px">
      <label>Search (keyword)</label>
      <input type="text" name="q" value="<?= h($filter_search) ?>" placeholder="user, description…">
    </div>
    <div class="form-group" style="min-width:80px">
      <label>Per page</label>
      <select name="per_page">
        <?php foreach([25,50,100,200] as $pp): ?>
          <option value="<?= $pp ?>" <?= $per_page==$pp?'selected':'' ?>><?= $pp ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">🔍 Filter</button>
    <a href="activity-logs.php" class="btn btn-outline">✕ Reset</a>
  </div>
</form>

<!-- Table -->
<div class="card" style="padding:0">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:50px">#</th>
          <th style="width:140px">Date / Time</th>
          <th style="width:160px">User (ID)</th>
          <th style="width:90px">Action</th>
          <th style="width:120px">Module</th>
          <th>Description</th>
          <th style="width:110px">IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:48px;color:#9ca3af">
            <div style="font-size:32px;margin-bottom:10px">📋</div>
            No log entries found for the selected filters.
            <?php if ($total_rows === 0): ?>
            <div style="margin-top:10px;font-size:13px">
              To start logging, add <code style="background:#f3f4f6;padding:2px 6px;border-radius:4px">log_activity('create','Invoice','...')</code>
              calls in your PHP files after save / delete actions.
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php else:
          foreach ($logs as $log):
            $am = $action_map[$log['action']] ?? $action_map['other'];
        ?>
        <tr>
          <td style="font-size:11px;color:#9ca3af"><?= (int)$log['id'] ?></td>
          <td style="white-space:nowrap;font-size:12px">
            <div style="font-weight:600"><?= date('d M Y', strtotime($log['created_at'])) ?></div>
            <div style="color:#6b7280"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
          </td>
          <td>
            <div style="font-weight:700;font-size:13px"><?= h($log['user_name'] ?: '—') ?></div>
            <div style="font-size:11px;color:#6b7280">
              <?= h(ucfirst($log['user_role'] ?: '')) ?>
              <?php if ($log['user_id'] > 0): ?>
                · UID: <?= (int)$log['user_id'] ?>
              <?php endif; ?>
            </div>
          </td>
          <td><span class="log-action-badge <?= $am['class'] ?>"><?= $am['label'] ?></span></td>
          <td style="font-size:13px;font-weight:600;color:#374151"><?= h($log['module'] ?: '—') ?></td>
          <td>
            <div class="log-meta" title="<?= h($log['description']) ?>"><?= h($log['description'] ?: '—') ?></div>
            <?php if (!empty($log['meta'])): ?>
              <div style="font-size:11px;color:#9ca3af;margin-top:1px"><?= h(mb_strimwidth($log['meta'],0,100,'…')) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:#6b7280;font-family:monospace"><?= h($log['ip_address'] ?: '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="pagination no-print">
  <?php
  $qs = array_merge($_GET, ['per_page'=>$per_page]);
  if ($page > 1): ?>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>1])) ?>">«</a>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$page-1])) ?>">‹ Prev</a>
  <?php endif;
  $start = max(1, $page-3); $end = min($total_pages, $page+3);
  for ($i=$start; $i<=$end; $i++): ?>
    <?php if ($i==$page): ?>
      <span class="current"><?= $i ?></span>
    <?php else: ?>
      <a href="?<?= http_build_query(array_merge($qs,['page'=>$i])) ?>"><?= $i ?></a>
    <?php endif;
  endfor;
  if ($page < $total_pages): ?>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$page+1])) ?>">Next ›</a>
    <a href="?<?= http_build_query(array_merge($qs,['page'=>$total_pages])) ?>">»</a>
  <?php endif; ?>
  <span style="color:#6b7280;font-size:13px;margin-left:6px">Page <?= $page ?> of <?= $total_pages ?> · <?= number_format($total_rows) ?> records</span>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
