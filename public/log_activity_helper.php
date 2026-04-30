<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  log_activity() helper — paste into src/core.php
 * ═══════════════════════════════════════════════════════════════
 *
 *  FIX: Previous version used auth_silent() which sometimes
 *  returned an empty array. This version reads the session
 *  directly and falls back gracefully.
 *
 *  USAGE (call after any important action):
 *    log_activity('create',  'Invoice',  'Created invoice POS-001 for Rahim');
 *    log_activity('update',  'Item',     'Updated Keyboard A4Tech price to ৳500');
 *    log_activity('delete',  'Customer', 'Deleted customer: Karim Uddin (ID:42)');
 *    log_activity('login',   'Auth',     'User logged in from 192.168.1.10');
 *    log_activity('logout',  'Auth',     'User signed out');
 *    log_activity('payment', 'Invoice',  'Payment ৳500 received for POS-001 via bKash');
 *    log_activity('export',  'Report',   'Exported Sales Report CSV');
 *
 *  IMPORTANT — also call log_activity on login/logout:
 *    In your login.php after session_regenerate_id():
 *      log_activity('login', 'Auth', 'Logged in');
 *    In your logout.php before session_destroy():
 *      log_activity('logout', 'Auth', 'Logged out');
 *
 * ═══════════════════════════════════════════════════════════════
 *
 *  SQL (run once):
 *  ───────────────
 *  CREATE TABLE IF NOT EXISTS activity_logs (
 *    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *    tenant_id   INT NOT NULL,
 *    branch_id   INT NOT NULL DEFAULT 0,
 *    user_id     INT NOT NULL DEFAULT 0,
 *    user_name   VARCHAR(120) NOT NULL DEFAULT '',
 *    user_role   VARCHAR(40)  NOT NULL DEFAULT '',
 *    action      VARCHAR(40)  NOT NULL DEFAULT 'other',
 *    module      VARCHAR(60)  NOT NULL DEFAULT '',
 *    description TEXT,
 *    meta        TEXT,
 *    ip_address  VARCHAR(45)  NOT NULL DEFAULT '',
 *    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *    INDEX idx_tenant  (tenant_id),
 *    INDEX idx_created (created_at),
 *    INDEX idx_user    (user_id),
 *    INDEX idx_action  (action)
 *  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *  -- Loyalty columns on company_settings:
 *  ALTER TABLE company_settings
 *    ADD COLUMN IF NOT EXISTS loyalty_earn_amount  DECIMAL(10,2) NOT NULL DEFAULT 100.00,
 *    ADD COLUMN IF NOT EXISTS loyalty_earn_points  INT NOT NULL DEFAULT 1,
 *    ADD COLUMN IF NOT EXISTS loyalty_redeem_value DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
 *    ADD COLUMN IF NOT EXISTS loyalty_enabled      TINYINT(1) NOT NULL DEFAULT 1,
 *    ADD COLUMN IF NOT EXISTS loyalty_min_redeem   INT NOT NULL DEFAULT 10,
 *    ADD COLUMN IF NOT EXISTS loyalty_expiry_days  INT NOT NULL DEFAULT 0;
 *
 *  -- Payment method on income table (for Sales Report):
 *  ALTER TABLE income
 *    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(30) NOT NULL DEFAULT 'cash'
 *    AFTER paid;
 */

// ── Paste this into src/core.php ─────────────────────────────────

if (!function_exists('log_activity')) {
    /**
     * Record a user activity to the activity_logs table.
     *
     * @param string $action      One of: create|update|delete|login|logout|payment|export|other
     * @param string $module      e.g. 'Invoice', 'Item', 'Customer', 'Auth'
     * @param string $description Human-readable description of what happened
     * @param string $meta        Optional extra data (JSON string, IDs, old/new values, etc.)
     */
    function log_activity(string $action, string $module, string $description, string $meta = ''): void
    {
        try {
            // ── Read session safely ──────────────────────────────
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            // Your core.php stores user in $_SESSION['user'] — adjust key if different
            $sess_user = $_SESSION['user'] ?? $_SESSION['pos_user'] ?? [];

            $user_id   = (int)  ($sess_user['id']   ?? 0);
            $user_name = (string)($sess_user['name'] ?? 'System');
            $user_role = (string)($sess_user['role'] ?? '');

            // ── Get tenant / branch ──────────────────────────────
            $tid = function_exists('tid')  ? (int)tid()  : 0;
            $bid = function_exists('brid') ? (int)brid() : 0;

            // ── Get IP address ────────────────────────────────────
            $ip = '';
            foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $ip = trim(explode(',', $_SERVER[$key])[0]);
                    break;
                }
            }
            $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';

            // ── Insert ────────────────────────────────────────────
            $stmt = db()->prepare(
                'INSERT INTO activity_logs
                 (tenant_id, branch_id, user_id, user_name, user_role,
                  action, module, description, meta, ip_address)
                 VALUES (?,?,?,?,?, ?,?,?,?,?)'
            );
            $stmt->execute([
                $tid, $bid, $user_id, $user_name, $user_role,
                $action, $module, $description, $meta, $ip
            ]);

        } catch (\Throwable $e) {
            // Logging must NEVER break the app
            error_log('[log_activity] ' . $e->getMessage());
        }
    }
}

// ── Also add: auto-log points when a sale is completed ───────────
// In your POS checkout / invoice save logic, after saving to DB:
//
// $inv_total = ...; // total amount of the sale
// $cust_id   = ...; // customer ID (0 for walk-in)
// if ($cust_id > 0) {
//     $cs_row = db()->prepare('SELECT * FROM company_settings WHERE tenant_id=?');
//     $cs_row->execute([tid()]);
//     $cs = $cs_row->fetch() ?: [];
//     if (!empty($cs['loyalty_enabled'])) {
//         $earn_amount = (float)($cs['loyalty_earn_amount'] ?? 100);
//         $earn_points = (int)  ($cs['loyalty_earn_points'] ?? 1);
//         $pts = (int)floor($inv_total / $earn_amount) * $earn_points;
//         if ($pts > 0) {
//             db()->prepare('UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id=? AND tenant_id=?')
//                 ->execute([$pts, $cust_id, tid()]);
//             db()->prepare('INSERT INTO loyalty_transactions (tenant_id,customer_id,invoice_id,type,points,note) VALUES (?,?,?,?,?,?)')
//                 ->execute([tid(), $cust_id, $invoice_id, 'earn', $pts, "Auto: sale ৳$inv_total"]);
//             _upgrade_tier($cust_id, tid()); // auto-upgrade membership tier
//         }
//     }
// }
// log_activity('create', 'Invoice', "Invoice #$inv_no · ৳$inv_total · $pm_label", "customer_id=$cust_id");
