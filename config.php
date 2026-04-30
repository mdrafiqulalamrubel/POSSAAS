<?php
// ============================================================
//  config.php  — edit these before deploying
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'posdemob_pos_saas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'Daffodil POS(SaaS)');
define('APP_URL',     'http://localhost/pos/public');
define('APP_VERSION', '2.0.0');

// Session
define('SESSION_NAME',    'pos_sess');
define('SESSION_TIMEOUT', 7200);

// Invoice defaults
define('INVOICE_PREFIX', 'INV');
define('TAX_LABEL',      'VAT');
define('CURRENCY',       '$');
define('CURRENCY_CODE',  'USD');

// Superadmin
define('SUPERADMIN_EMAIL', 'superadmin@platform.com');

// ── Stripe ────────────────────────────────────────────────────
// Get keys from: https://dashboard.stripe.com/apikeys
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_SECRET_KEY',      'sk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_WEBHOOK_SECRET',  'whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');

// Create Price IDs in Stripe Dashboard → Products
define('STRIPE_PRICE_BASIC', 'price_REPLACE_BASIC_PRICE_ID');
define('STRIPE_PRICE_PRO',   'price_REPLACE_PRO_PRICE_ID');

// Plan display prices (must match Stripe)
define('PLAN_BASIC_PRICE', 19);
define('PLAN_PRO_PRICE',   49);
define('TRIAL_DAYS',       14);

// App base URL (no trailing slash)
define('APP_BASE_URL', 'http://localhost');

// Mail
define('MAIL_FROM',      'noreply@yourdomain.com');
define('MAIL_FROM_NAME', APP_NAME);
