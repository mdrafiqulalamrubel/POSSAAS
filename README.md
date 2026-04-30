# SaaS POS System v2.0
Multi-Tenant · Multi-Branch · PHP 8 + MySQL · Stripe Billing

## What's New in v2
- Items / Inventory with expiry dates, notes, low-stock alerts
- Company Setup screen — name, address, phones, logo, currency, tax label
- Stripe Billing — automated subscriptions, auto-provisioning, webhooks
- Marketing Website — pricing page, features, FAQ, testimonials
- Auto Tenant Provisioning — new customer pays → account created automatically

---

## Quick Start (XAMPP Local)

1. Copy `pos` folder to `C:\xampp\htdocs\pos\`
2. Start Apache + MySQL in XAMPP
3. Go to http://localhost/phpmyadmin → New → `pos_saas` → utf8mb4_unicode_ci → Create
4. Click pos_saas → Import → select schema.sql → Go
5. Open http://localhost/pos/public/login.php
6. Login: admin@demo.com / password

config.php is already set for XAMPP defaults — no changes needed!

---

## New Pages
- items.php          — Item/inventory list with expiry alerts
- item-add.php       — Add/edit item with expiry date & notes
- company-setup.php  — Company name, address, logo, currency settings
- billing.php        — Subscription plan management
- billing-webhook.php— Stripe webhook (auto-provisions new tenants)
- website/index.php  — Public marketing website

---

## Stripe Setup
1. Create account at stripe.com
2. Copy API keys into config.php
3. Create Basic ($19/mo) and Pro ($49/mo) products, copy Price IDs into config.php
4. Add webhook: https://yourdomain.com/pos/public/billing-webhook.php
5. Events: checkout.session.completed, invoice.payment_succeeded, invoice.payment_failed, customer.subscription.deleted
6. Copy webhook signing secret into config.php

---

## Deploy to Cloud
```bash
# Ubuntu VPS
apt install apache2 mysql-server php8.2 php8.2-mysql php8.2-curl libapache2-mod-php8.2 -y
a2enmod rewrite
# Upload files to /var/www/html/pos/
# Import schema.sql
# Edit config.php with your domain and DB password
# Install SSL: certbot --apache -d yourdomain.com
```

## Default Login
Email: admin@demo.com
Password: password
# POS_RawPHPMySQL
