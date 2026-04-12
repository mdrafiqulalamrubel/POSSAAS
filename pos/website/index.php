<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SaaS POS — Cloud POS for Modern Businesses</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --primary:#4f46e5;--primary-d:#4338ca;--text:#1a1d23;--muted:#6b7280;
  --bg:#f4f5f7;--surface:#fff;--border:#e2e4e9;--radius:10px;
  --income:#059669;--warn:#d97706;
}
body{font:15px/1.6 'Inter',system-ui,sans-serif;color:var(--text);background:#fff}
a{color:inherit;text-decoration:none}
.container{max-width:1100px;margin:0 auto;padding:0 24px}

/* NAV */
nav{background:#fff;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:64px}
.nav-logo{font-size:20px;font-weight:800;color:var(--primary)}
.nav-links{display:flex;align-items:center;gap:24px;font-size:14px}
.nav-links a:hover{color:var(--primary)}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:var(--radius);font-size:14px;font-weight:500;cursor:pointer;border:none;transition:background .15s,opacity .15s}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-d)}
.btn-outline{background:#fff;color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{background:var(--bg)}
.btn-lg{padding:13px 28px;font-size:16px;border-radius:12px}

/* HERO */
.hero{padding:90px 0 70px;text-align:center;background:linear-gradient(135deg,#eef2ff 0%,#f5f3ff 100%)}
.hero-badge{display:inline-flex;align-items:center;gap:6px;background:#ede9fe;color:var(--primary);padding:5px 14px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:20px}
.hero h1{font-size:clamp(34px,5vw,58px);font-weight:800;line-height:1.15;margin-bottom:20px;color:#111}
.hero h1 span{color:var(--primary)}
.hero p{font-size:18px;color:var(--muted);max-width:580px;margin:0 auto 36px}
.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.hero-stats{display:flex;gap:40px;justify-content:center;margin-top:50px;flex-wrap:wrap}
.hero-stat{text-align:center}
.hero-stat strong{display:block;font-size:28px;font-weight:800;color:var(--primary)}
.hero-stat span{font-size:13px;color:var(--muted)}

/* FEATURES */
.section{padding:80px 0}
.section-tag{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--primary);margin-bottom:10px}
.section-title{font-size:clamp(26px,3.5vw,40px);font-weight:800;margin-bottom:14px}
.section-sub{font-size:17px;color:var(--muted);max-width:560px}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:48px}
.feature-card{background:var(--bg);border-radius:14px;padding:28px;border:1px solid var(--border)}
.feature-icon{font-size:32px;margin-bottom:14px}
.feature-card h3{font-size:17px;font-weight:700;margin-bottom:8px}
.feature-card p{font-size:14px;color:var(--muted);line-height:1.65}

/* PRICING */
.pricing-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:48px;max-width:860px;margin-left:auto;margin-right:auto}
.plan-card{background:#fff;border:1.5px solid var(--border);border-radius:16px;padding:32px;position:relative}
.plan-card.featured{border-color:var(--primary);box-shadow:0 0 0 4px #eef2ff}
.plan-badge{position:absolute;top:-13px;left:50%;transform:translateX(-50%);background:var(--primary);color:#fff;padding:4px 16px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.plan-name{font-size:18px;font-weight:700;margin-bottom:6px}
.plan-price{font-size:42px;font-weight:800;color:var(--primary);margin:10px 0}
.plan-price span{font-size:16px;font-weight:400;color:var(--muted)}
.plan-features{list-style:none;margin:20px 0 24px}
.plan-features li{padding:7px 0;font-size:14px;border-bottom:1px solid var(--border)}
.plan-features li:last-child{border:none}
.plan-features li::before{content:'✓ ';color:var(--income);font-weight:700}

/* TESTIMONIAL */
.testimonials{background:var(--bg);padding:80px 0}
.testimonials-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:48px}
.testi-card{background:#fff;border-radius:14px;padding:24px;border:1px solid var(--border)}
.testi-stars{color:#f59e0b;font-size:16px;margin-bottom:12px}
.testi-text{font-size:14px;line-height:1.7;color:var(--muted);margin-bottom:16px}
.testi-author{font-weight:700;font-size:13px}
.testi-company{font-size:12px;color:var(--muted)}

/* FAQ */
.faq-list{max-width:720px;margin:40px auto 0}
.faq-item{border-bottom:1px solid var(--border);padding:20px 0}
.faq-q{font-weight:700;font-size:16px;margin-bottom:8px;cursor:pointer}
.faq-a{font-size:14px;color:var(--muted);line-height:1.7}

/* CTA */
.cta-section{background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;padding:80px 0;text-align:center}
.cta-section h2{font-size:clamp(26px,3.5vw,40px);font-weight:800;margin-bottom:14px}
.cta-section p{font-size:17px;opacity:.85;max-width:500px;margin:0 auto 32px}
.btn-white{background:#fff;color:var(--primary);font-weight:700}
.btn-white:hover{opacity:.92}

/* FOOTER */
footer{background:#111;color:#9ca3af;padding:50px 0 24px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px}
.footer-brand{font-size:20px;font-weight:800;color:#fff;margin-bottom:12px}
.footer-tagline{font-size:13px;line-height:1.6}
.footer-col h4{font-size:13px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.08em;margin-bottom:14px}
.footer-col a{display:block;font-size:13px;margin-bottom:8px;color:#9ca3af}
.footer-col a:hover{color:#fff}
.footer-bottom{border-top:1px solid #1f2937;padding-top:20px;font-size:12px;text-align:center}

@media(max-width:768px){
  .nav-links{display:none}
  .footer-grid{grid-template-columns:1fr 1fr}
  .hero{padding:60px 0 50px}
}
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <div class="container nav-inner">
    <div class="nav-logo">⬡ SaaS POS</div>
    <div class="nav-links">
      <a href="#features">Features</a>
      <a href="#pricing">Pricing</a>
      <a href="#faq">FAQ</a>
      <a href="pos/public/login.php" class="btn btn-outline" style="padding:7px 16px">Login</a>
      <a href="#pricing" class="btn btn-primary" style="padding:7px 16px">Start Free Trial</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="hero-badge">🚀 No credit card required · 14-day free trial</div>
    <h1>The Smart POS for<br><span>Growing Businesses</span></h1>
    <p>Manage invoices, expenses, inventory and reports — all in one place. Multi-branch, multi-user, fully cloud-based.</p>
    <div class="hero-btns">
      <a href="#pricing" class="btn btn-primary btn-lg">Start Free Trial →</a>
      <a href="pos/public/login.php" class="btn btn-outline btn-lg">Live Demo</a>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><strong>500+</strong><span>Businesses</span></div>
      <div class="hero-stat"><strong>14 days</strong><span>Free trial</span></div>
      <div class="hero-stat"><strong>99.9%</strong><span>Uptime</span></div>
      <div class="hero-stat"><strong>24/7</strong><span>Support</span></div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="section" id="features">
  <div class="container">
    <div class="section-tag">Features</div>
    <div class="section-title">Everything your business needs</div>
    <p class="section-sub">From invoicing to inventory, reports to multi-branch management — all in one powerful platform.</p>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-icon">🧾</div>
        <h3>Invoicing & Billing</h3>
        <p>Create professional invoices with tax, discounts, and payment tracking. Print or save as PDF instantly.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📦</div>
        <h3>Item & Inventory Management</h3>
        <p>Track stock levels, set reorder alerts, monitor expiry dates, and get low-stock notifications automatically.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">💸</div>
        <h3>Expense Tracking</h3>
        <p>Categorize expenses, track suppliers, manage approval workflows and keep your finances organised.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3>Profit & Loss Reports</h3>
        <p>Real-time P&L reports, branch-by-branch breakdowns, and expense category analysis in one dashboard.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">🏪</div>
        <h3>Multi-Branch Support</h3>
        <p>Run unlimited branches from one account. Switch between branches with a single click.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Role-Based Access</h3>
        <p>Admin, Manager, and Cashier roles. Control exactly what each user can see and do.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">👥</div>
        <h3>Customer Management</h3>
        <p>Maintain a full customer database with contact details, purchase history and outstanding balances.</p>
      </div>
      <div class="feature-card">
        <div class="feature-icon">☁️</div>
        <h3>100% Cloud-Based</h3>
        <p>Access from anywhere on any device. No software to install, no backups to worry about.</p>
      </div>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="section" id="pricing" style="background:var(--bg)">
  <div class="container" style="text-align:center">
    <div class="section-tag">Pricing</div>
    <div class="section-title">Simple, transparent pricing</div>
    <p class="section-sub" style="margin:0 auto">Start with a 14-day free trial. No credit card required. Cancel anytime.</p>
    <div class="pricing-grid">

      <div class="plan-card">
        <div class="plan-name">Basic</div>
        <div class="plan-price">$19<span>/month</span></div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:0">Perfect for small businesses</p>
        <ul class="plan-features">
          <li>1 Branch</li>
          <li>Up to 5 Users</li>
          <li>Invoicing & Expenses</li>
          <li>Inventory & Items</li>
          <li>Reports & Dashboard</li>
          <li>Email Support</li>
        </ul>
        <a href="pos/public/billing-checkout.php?plan=basic" class="btn btn-outline" style="width:100%;justify-content:center">Start Free Trial</a>
      </div>

      <div class="plan-card featured">
        <div class="plan-badge">⭐ Most Popular</div>
        <div class="plan-name">Pro</div>
        <div class="plan-price">$49<span>/month</span></div>
        <p style="font-size:13px;color:var(--muted);margin-bottom:0">For growing businesses</p>
        <ul class="plan-features">
          <li>Unlimited Branches</li>
          <li>Unlimited Users</li>
          <li>All Basic features</li>
          <li>Priority Support</li>
          <li>Advanced Reports</li>
          <li>API Access</li>
        </ul>
        <a href="pos/public/billing-checkout.php?plan=pro" class="btn btn-primary" style="width:100%;justify-content:center">Start Free Trial</a>
      </div>

    </div>
    <p style="margin-top:20px;font-size:13px;color:var(--muted)">All plans include a 14-day free trial. No credit card required to start.</p>
  </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonials" id="testimonials">
  <div class="container" style="text-align:center">
    <div class="section-tag">Testimonials</div>
    <div class="section-title">Loved by businesses everywhere</div>
    <div class="testimonials-grid">
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-text">"SaaS POS transformed how we manage our 3 branches. The expiry date tracking alone saved us thousands in wasted inventory."</p>
        <div class="testi-author">Sarah M.</div>
        <div class="testi-company">Pharmacy Owner</div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-text">"Setup took less than 10 minutes. My staff picked it up instantly. The P&L reports finally give me a clear picture of my business."</p>
        <div class="testi-author">Ahmed K.</div>
        <div class="testi-company">Restaurant Owner</div>
      </div>
      <div class="testi-card">
        <div class="testi-stars">★★★★★</div>
        <p class="testi-text">"We switched from a $200/month solution to this and honestly it does more. The invoice printing is beautiful and professional."</p>
        <div class="testi-author">Lisa T.</div>
        <div class="testi-company">Retail Store Manager</div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section" id="faq">
  <div class="container" style="text-align:center">
    <div class="section-tag">FAQ</div>
    <div class="section-title">Frequently asked questions</div>
    <div class="faq-list" style="text-align:left">
      <div class="faq-item">
        <div class="faq-q">Do I need a credit card to start the free trial?</div>
        <div class="faq-a">No! You can start your 14-day free trial without any payment details. We'll only ask for payment if you decide to continue.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Can I use this on my phone or tablet?</div>
        <div class="faq-a">Yes. SaaS POS is fully responsive and works great on phones, tablets, and computers.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Can I manage multiple branches?</div>
        <div class="faq-a">Yes! The Pro plan supports unlimited branches. The Basic plan supports 1 branch.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">How does the expiry date tracking work?</div>
        <div class="faq-a">When you add items to inventory, you can set an expiry date. The system automatically alerts you when items are expiring within 7 or 30 days, or are already expired.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Can I cancel anytime?</div>
        <div class="faq-a">Absolutely. Cancel anytime from your billing settings. No questions asked, no long-term contracts.</div>
      </div>
      <div class="faq-item">
        <div class="faq-q">Is my data secure?</div>
        <div class="faq-a">Yes. All data is encrypted in transit (HTTPS), passwords are bcrypt-hashed, and each business's data is completely isolated from others.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="container">
    <h2>Ready to grow your business?</h2>
    <p>Join 500+ businesses using SaaS POS. Start your free 14-day trial today.</p>
    <a href="#pricing" class="btn btn-white btn-lg">Get Started Free →</a>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">⬡ SaaS POS</div>
        <p class="footer-tagline">The smart, cloud-based POS system for modern businesses. Manage invoices, inventory, expenses and reports from anywhere.</p>
      </div>
      <div class="footer-col">
        <h4>Product</h4>
        <a href="#features">Features</a>
        <a href="#pricing">Pricing</a>
        <a href="pos/public/login.php">Login</a>
        <a href="#pricing">Free Trial</a>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <a href="#faq">FAQ</a>
        <a href="mailto:support@yourdomain.com">Email Support</a>
        <a href="#">Documentation</a>
      </div>
      <div class="footer-col">
        <h4>Legal</h4>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Cookie Policy</a>
      </div>
    </div>
    <div class="footer-bottom">
      © <?= date('Y') ?> SaaS POS. All rights reserved.
    </div>
  </div>
</footer>

</body>
</html>
