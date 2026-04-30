<?php
// billing-portal.php — redirect to Stripe customer portal
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$tid  = tid();

$stmt = db()->prepare('SELECT stripe_customer_id FROM subscriptions WHERE tenant_id=?');
$stmt->execute([$tid]);
$cid = $stmt->fetchColumn();

if (!$cid) {
    flash('error', 'No active subscription found.');
    redirect('billing.php');
}

$data = http_build_query([
    'customer'   => $cid,
    'return_url' => APP_BASE_URL . '/pos/public/billing.php',
]);

$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$resp = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!empty($resp['url'])) {
    header('Location: ' . $resp['url']);
    exit;
}

flash('error', 'Could not open billing portal. ' . ($resp['error']['message'] ?? ''));
redirect('billing.php');
