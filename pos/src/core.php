<?php
// ============================================================
//  src/core.php  — DB · Auth · Helpers
// ============================================================

require_once __DIR__ . '/../config.php';

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// ── PDO singleton ─────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Auth ──────────────────────────────────────────────────────
function auth(): array|null {
    return $_SESSION['pos_user'] ?? null;
}

function require_auth(string $min_role = 'cashier'): array {
    $user = auth();
    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    $levels = ['cashier'=>1,'manager'=>2,'admin'=>3,'superadmin'=>4];
    if (($levels[$user['role']] ?? 0) < ($levels[$min_role] ?? 0)) {
        http_response_code(403);
        die(render_error('Access denied.'));
    }
    // session timeout
    if (isset($_SESSION['pos_last_active']) && time() - $_SESSION['pos_last_active'] > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['pos_last_active'] = time();
    return $user;
}

function login(string $email, string $password): bool {
    $row = db()->prepare('SELECT u.*, t.name tenant_name, t.is_active t_active
                          FROM users u JOIN tenants t ON t.id=u.tenant_id
                          WHERE u.email=? AND u.is_active=1 LIMIT 1');
    $row->execute([$email]);
    $user = $row->fetch();
    if (!$user || !$user['t_active']) return false;
    if (!password_verify($password, $user['password'])) return false;
    $_SESSION['pos_user'] = [
        'id'          => $user['id'],
        'tenant_id'   => $user['tenant_id'],
        'tenant_name' => $user['tenant_name'],
        'branch_id'   => $user['branch_id'],
        'name'        => $user['name'],
        'email'       => $user['email'],
        'role'        => $user['role'],
    ];
    $_SESSION['pos_last_active'] = time();
    if ($user['branch_id']) {
        $_SESSION['active_branch'] = $user['branch_id'];
    } else {
        $b = db()->prepare('SELECT id FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY id LIMIT 1');
        $b->execute([$user['tenant_id']]);
        $_SESSION['active_branch'] = $b->fetchColumn();
    }
    return true;
}

function logout(): void {
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ── Tenant / branch scope ────────────────────────────────────
function tid(): int  { return (int)auth()['tenant_id']; }
function uid(): int  { return (int)auth()['id']; }
function brid(): int { return (int)($_SESSION['active_branch'] ?? 0); }

function get_branches(): array {
    $u = auth();
    if ($u['branch_id']) {
        $s = db()->prepare('SELECT * FROM branches WHERE id=? AND tenant_id=? AND is_active=1');
        $s->execute([$u['branch_id'], $u['tenant_id']]);
    } else {
        $s = db()->prepare('SELECT * FROM branches WHERE tenant_id=? AND is_active=1 ORDER BY name');
        $s->execute([$u['tenant_id']]);
    }
    return $s->fetchAll();
}

function get_branch(int $id): array|false {
    $s = db()->prepare('SELECT * FROM branches WHERE id=? AND tenant_id=?');
    $s->execute([$id, tid()]);
    return $s->fetch();
}

// ── Currency from company_settings (cached per request) ───────
function tenant_currency(): string {
    static $sym = null;
    if ($sym !== null) return $sym;
    try {
        $s = db()->prepare('SELECT currency FROM company_settings WHERE tenant_id=? LIMIT 1');
        $s->execute([tid()]);
        $row = $s->fetch();
        $sym = (!empty($row['currency'])) ? $row['currency'] : CURRENCY;
    } catch (\Throwable $e) {
        $sym = CURRENCY;
    }
    return $sym;
}

// ── Invoice numbering ─────────────────────────────────────────
function next_invoice_no(int $branch_id): string {
    $tid = tid();
    $cs = db()->prepare('SELECT invoice_prefix FROM company_settings WHERE tenant_id=? LIMIT 1');
    $cs->execute([$tid]);
    $cs_row = $cs->fetch();
    $prefix = (!empty($cs_row['invoice_prefix']))
        ? strtoupper(trim($cs_row['invoice_prefix']))
        : INVOICE_PREFIX;

    db()->prepare(
        'INSERT INTO invoice_sequences (tenant_id, branch_id, prefix, last_number)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
             prefix      = VALUES(prefix),
             last_number = LAST_INSERT_ID(last_number + 1)'
    )->execute([$tid, $branch_id, $prefix]);

    $new_num = (int) db()->lastInsertId();

    if ($new_num < 1) {
        $r = db()->prepare('SELECT last_number FROM invoice_sequences WHERE tenant_id=? AND branch_id=?');
        $r->execute([$tid, $branch_id]);
        $new_num = (int) $r->fetchColumn();
    }

    $attempt = 0;
    do {
        $candidate = $prefix . '-' . str_pad($new_num, 5, '0', STR_PAD_LEFT);
        $chk = db()->prepare('SELECT 1 FROM income WHERE tenant_id=? AND invoice_no=? LIMIT 1');
        $chk->execute([$tid, $candidate]);
        if (!$chk->fetchColumn()) break;
        $new_num++;
        db()->prepare('UPDATE invoice_sequences SET last_number=?, prefix=? WHERE tenant_id=? AND branch_id=?')
            ->execute([$new_num, $prefix, $tid, $branch_id]);
        $attempt++;
    } while ($attempt < 100);

    return $candidate;
}

// ── HTML helpers ──────────────────────────────────────────────
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// money() now reads currency from company_settings DB so ৳/BDT works everywhere
function money(float $v): string { return tenant_currency() . number_format($v, 2); }
function fmt_date(string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg];
}
function get_flash(): array|null {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function render_error(string $msg): string {
    return '<div style="padding:2rem;font-family:sans-serif;color:#b00">' . h($msg) . '</div>';
}

function redirect(string $path): void {
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

// ── Pagination helper ─────────────────────────────────────────
function paginate(int $total, int $per_page, int $page): array {
    $pages = max(1, (int)ceil($total / $per_page));
    $page  = max(1, min($page, $pages));
    return ['total'=>$total,'per_page'=>$per_page,'page'=>$page,'pages'=>$pages,
            'offset'=>($page-1)*$per_page];
}
