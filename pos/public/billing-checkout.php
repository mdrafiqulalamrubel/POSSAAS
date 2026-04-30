<?php
// billing-checkout.php — redirects to Stripe Checkout
require_once __DIR__ . '/../src/core.php';
$user = require_auth('admin');
$tid  = tid();

$plan = $_POST['plan'] ?? '';
$price_id = $plan === 'pro' ? STRIPE_PRICE_PRO : STRIPE_PRICE_BASIC;

// Build Stripe API call (no SDK needed — raw HTTP)
$data = http_build_query([
    'mode'                                   => 'subscription',
    'line_items[0][price]'                   => $price_id,
    'line_items[0][quantity]'                => 1,
    'success_url'                            => APP_BASE_URL . '/pos/public/billing-success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'                             => APP_BASE_URL . '/pos/public/billing.php',
    'metadata[tenant_id]'                    => $tid,
    'metadata[plan]'                         => $plan,
    'subscription_data[metadata][tenant_id]' => $tid,
    'subscription_data[metadata][plan]'      => $plan,
    'subscription_data[trial_period_days]'   => TRIAL_DAYS,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
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

flash('error', 'Could not connect to Stripe. Check your API keys in config.php. Error: ' . ($resp['error']['message'] ?? 'Unknown'));
redirect('billing.php');
