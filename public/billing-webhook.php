<?php
// billing-webhook.php — receives Stripe webhook events
// Set this URL in Stripe Dashboard → Developers → Webhooks
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/core.php';

$payload   = file_get_contents('php://input');
$sig       = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify signature
function stripe_verify(string $payload, string $sig, string $secret): bool {
    $parts = [];
    foreach (explode(',', $sig) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $ts   = $parts['t'][0] ?? 0;
    $expected = hash_hmac('sha256', $ts . '.' . $payload, $secret);
    foreach ($parts['v1'] ?? [] as $v) {
        if (hash_equals($expected, $v)) return true;
    }
    return false;
}

if (!stripe_verify($payload, $sig, STRIPE_WEBHOOK_SECRET)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$type  = $event['type'] ?? '';
$obj   = $event['data']['object'] ?? [];

// Helper: log event
function log_billing(int $tenant_id, string $type, string $stripe_id, string $payload): void {
    db()->prepare('INSERT INTO billing_events (tenant_id,event_type,stripe_id,payload) VALUES (?,?,?,?)')
       ->execute([$tenant_id, $type, $stripe_id, $payload]);
}

// Helper: provision new tenant on first payment
function provision_tenant(string $email, string $company, string $plan, string $stripe_cid, string $stripe_sid, string $period_end): void {
    $slug = strtolower(preg_replace('/[^a-z0-9]/', '-', $company)) . '-' . substr(md5($email), 0, 6);

    // Create tenant
    db()->prepare('INSERT IGNORE INTO tenants (name, slug, plan, is_active) VALUES (?,?,?,1)')
       ->execute([$company, $slug, $plan]);
    $tid = db()->lastInsertId();

    if (!$tid) {
        // Already exists — find it
        $r = db()->prepare('SELECT id FROM tenants WHERE slug=?');
        $r->execute([$slug]);
        $tid = $r->fetchColumn();
    }

    // Default branch
    db()->prepare('INSERT IGNORE INTO branches (tenant_id, name, address) VALUES (?,?,?)')
       ->execute([$tid, 'Main Branch', '']);
    $bid = db()->lastInsertId();

    // Admin user — random password
    $pass = bin2hex(random_bytes(6));
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    db()->prepare('INSERT IGNORE INTO users (tenant_id, name, email, password, role) VALUES (?,?,?,?,?)')
       ->execute([$tid, $company, $email, $hash, 'admin']);

    // Company settings
    db()->prepare('INSERT IGNORE INTO company_settings (tenant_id, company_name) VALUES (?,?)')
       ->execute([$tid, $company]);

    // Invoice sequence
    db()->prepare('INSERT IGNORE INTO invoice_sequences (tenant_id, branch_id, prefix, last_number) VALUES (?,?,?,0)')
       ->execute([$tid, $bid, 'INV']);

    // Default expense categories
    foreach (['Rent','Utilities','Salary','Supplies','Marketing','Other'] as $cat) {
        db()->prepare('INSERT IGNORE INTO expense_categories (tenant_id, name) VALUES (?,?)')->execute([$tid, $cat]);
    }

    // Subscription record
    db()->prepare('INSERT INTO subscriptions (tenant_id,stripe_customer_id,stripe_sub_id,plan,status,current_period_end)
                   VALUES (?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE stripe_customer_id=VALUES(stripe_customer_id),
                   stripe_sub_id=VALUES(stripe_sub_id),plan=VALUES(plan),status=VALUES(status),
                   current_period_end=VALUES(current_period_end)')
       ->execute([$tid, $stripe_cid, $stripe_sid, $plan, 'active', date('Y-m-d H:i:s', $period_end)]);

    // Send welcome email
    $login_url = APP_BASE_URL . '/pos/public/login.php';
    $subject = 'Welcome to ' . APP_NAME . ' — Your account is ready!';
    $body = "Hello,\n\nYour {$plan} account has been created!\n\n"
          . "Login URL: {$login_url}\n"
          . "Email: {$email}\n"
          . "Password: {$pass}\n\n"
          . "Please change your password after first login.\n\n"
          . "— " . APP_NAME . " Team";
    mail($email, $subject, $body, 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>');
}

// ── Handle events ─────────────────────────────────────────────
switch ($type) {

    case 'checkout.session.completed':
        $tid_meta = (int)($obj['metadata']['tenant_id'] ?? 0);
        $plan     = $obj['metadata']['plan'] ?? 'basic';
        $cid      = $obj['customer'] ?? '';
        $sid      = $obj['subscription'] ?? '';
        $email    = $obj['customer_details']['email'] ?? '';
        $company  = $obj['customer_details']['name'] ?? $email;

        if ($tid_meta) {
            // Existing user upgrading
            db()->prepare('INSERT INTO subscriptions (tenant_id,stripe_customer_id,stripe_sub_id,plan,status)
                           VALUES (?,?,?,?,?)
                           ON DUPLICATE KEY UPDATE stripe_customer_id=VALUES(stripe_customer_id),
                           stripe_sub_id=VALUES(stripe_sub_id),plan=VALUES(plan),status=VALUES(status)')
               ->execute([$tid_meta, $cid, $sid, $plan, 'active']);
            db()->prepare('UPDATE tenants SET plan=? WHERE id=?')->execute([$plan, $tid_meta]);
            log_billing($tid_meta, $type, $obj['id'], json_encode($obj));
        } elseif ($email) {
            // New customer from website — auto-provision
            provision_tenant($email, $company, $plan, $cid, $sid, time() + 30*86400);
        }
        break;

    case 'invoice.payment_succeeded':
        $cid = $obj['customer'] ?? '';
        $s = db()->prepare('SELECT tenant_id FROM subscriptions WHERE stripe_customer_id=?');
        $s->execute([$cid]); $tid_found = (int)$s->fetchColumn();
        if ($tid_found) {
            $period_end = $obj['lines']['data'][0]['period']['end'] ?? null;
            db()->prepare('UPDATE subscriptions SET status=?,current_period_end=? WHERE tenant_id=?')
               ->execute(['active', $period_end ? date('Y-m-d H:i:s', $period_end) : null, $tid_found]);
            db()->prepare('UPDATE tenants SET is_active=1 WHERE id=?')->execute([$tid_found]);
            log_billing($tid_found, $type, $obj['id'], json_encode($obj));
        }
        break;

    case 'invoice.payment_failed':
        $cid = $obj['customer'] ?? '';
        $s = db()->prepare('SELECT tenant_id FROM subscriptions WHERE stripe_customer_id=?');
        $s->execute([$cid]); $tid_found = (int)$s->fetchColumn();
        if ($tid_found) {
            db()->prepare('UPDATE subscriptions SET status=? WHERE tenant_id=?')->execute(['past_due', $tid_found]);
            log_billing($tid_found, $type, $obj['id'], json_encode($obj));
        }
        break;

    case 'customer.subscription.deleted':
        $cid = $obj['customer'] ?? '';
        $s = db()->prepare('SELECT tenant_id FROM subscriptions WHERE stripe_customer_id=?');
        $s->execute([$cid]); $tid_found = (int)$s->fetchColumn();
        if ($tid_found) {
            db()->prepare('UPDATE subscriptions SET status=? WHERE tenant_id=?')->execute(['cancelled', $tid_found]);
            db()->prepare('UPDATE tenants SET is_active=0 WHERE id=?')->execute([$tid_found]);
            log_billing($tid_found, $type, $obj['id'], json_encode($obj));
        }
        break;
}

http_response_code(200);
echo 'ok';
