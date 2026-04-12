-- ============================================================
--  SaaS POS System — Full Schema (v2)
--  Includes: Items, Company Settings, Stripe Billing
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Tenants ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenants (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  plan        ENUM('trial','basic','pro') DEFAULT 'trial',
  is_active   TINYINT(1) DEFAULT 1,
  trial_ends  DATE,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Company Settings (per tenant) ────────────────────────────
CREATE TABLE IF NOT EXISTS company_settings (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL UNIQUE,
  company_name    VARCHAR(150) NOT NULL DEFAULT '',
  address         TEXT,
  city            VARCHAR(100),
  country         VARCHAR(100),
  phone           VARCHAR(50),
  phone2          VARCHAR(50),
  email           VARCHAR(150),
  website         VARCHAR(200),
  logo_path       VARCHAR(300),
  currency        VARCHAR(10)  DEFAULT '$',
  currency_code   VARCHAR(5)   DEFAULT 'USD',
  tax_label       VARCHAR(30)  DEFAULT 'VAT',
  invoice_prefix  VARCHAR(10)  DEFAULT 'INV',
  footer_note     TEXT,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Branches ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS branches (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  address     TEXT,
  phone       VARCHAR(30),
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── Users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('superadmin','admin','manager','cashier') DEFAULT 'cashier',
  is_active   TINYINT(1) DEFAULT 1,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email_tenant (tenant_id, email),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── Customers ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS customers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(150),
  phone       VARCHAR(30),
  address     TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── Items (inventory / product list) ─────────────────────────
CREATE TABLE IF NOT EXISTS items (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  branch_id       INT UNSIGNED NOT NULL,
  name            VARCHAR(200) NOT NULL,
  sku             VARCHAR(80),
  category        VARCHAR(80),
  unit            VARCHAR(30)  DEFAULT 'pcs',
  quantity        DECIMAL(12,3) DEFAULT 0,
  unit_price      DECIMAL(14,2) DEFAULT 0,
  reorder_level   DECIMAL(12,3) DEFAULT 0,
  expiry_date     DATE,
  notes           TEXT,
  is_active       TINYINT(1) DEFAULT 1,
  created_by      INT UNSIGNED,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  INDEX idx_tenant_branch (tenant_id, branch_id),
  INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB;

-- ── Income transactions ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS income (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  branch_id       INT UNSIGNED NOT NULL,
  invoice_no      VARCHAR(40) NOT NULL,
  customer_id     INT UNSIGNED,
  customer_name   VARCHAR(120),
  date            DATE NOT NULL,
  due_date        DATE,
  subtotal        DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax_pct         DECIMAL(5,2) DEFAULT 0,
  tax_amount      DECIMAL(14,2) DEFAULT 0,
  discount        DECIMAL(14,2) DEFAULT 0,
  total           DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid            DECIMAL(14,2) DEFAULT 0,
  balance         DECIMAL(14,2) GENERATED ALWAYS AS (total - paid) STORED,
  status          ENUM('draft','unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
  notes           TEXT,
  created_by      INT UNSIGNED,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_invoice (tenant_id, invoice_no),
  FOREIGN KEY (tenant_id)   REFERENCES tenants(id)   ON DELETE CASCADE,
  FOREIGN KEY (branch_id)   REFERENCES branches(id)  ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  INDEX idx_tenant_branch (tenant_id, branch_id),
  INDEX idx_date (date)
) ENGINE=InnoDB;

-- ── Income line items ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS income_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  income_id   INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  qty         DECIMAL(10,3) DEFAULT 1,
  unit_price  DECIMAL(14,2) NOT NULL,
  total       DECIMAL(14,2) GENERATED ALWAYS AS (qty * unit_price) STORED,
  FOREIGN KEY (income_id) REFERENCES income(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Expense categories ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS expense_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(80) NOT NULL,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB;

-- ── Expenses ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id       INT UNSIGNED NOT NULL,
  branch_id       INT UNSIGNED NOT NULL,
  ref_no          VARCHAR(40),
  category_id     INT UNSIGNED,
  supplier        VARCHAR(120),
  description     TEXT,
  date            DATE NOT NULL,
  amount          DECIMAL(14,2) NOT NULL,
  status          ENUM('pending','approved','paid','cancelled') DEFAULT 'pending',
  notes           TEXT,
  created_by      INT UNSIGNED,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id)    REFERENCES tenants(id)            ON DELETE CASCADE,
  FOREIGN KEY (branch_id)    REFERENCES branches(id)           ON DELETE CASCADE,
  FOREIGN KEY (category_id)  REFERENCES expense_categories(id) ON DELETE SET NULL,
  INDEX idx_tenant_branch (tenant_id, branch_id),
  INDEX idx_date (date)
) ENGINE=InnoDB;

-- ── Invoice sequences ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS invoice_sequences (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED NOT NULL,
  branch_id   INT UNSIGNED NOT NULL,
  prefix      VARCHAR(10) DEFAULT 'INV',
  last_number INT UNSIGNED DEFAULT 0,
  UNIQUE KEY uniq_seq (tenant_id, branch_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Billing / Subscriptions (Stripe) ─────────────────────────
CREATE TABLE IF NOT EXISTS subscriptions (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id            INT UNSIGNED NOT NULL UNIQUE,
  stripe_customer_id   VARCHAR(100),
  stripe_sub_id        VARCHAR(100),
  plan                 ENUM('trial','basic','pro') DEFAULT 'trial',
  status               ENUM('active','past_due','cancelled','trialing') DEFAULT 'trialing',
  current_period_end   DATETIME,
  cancel_at_period_end TINYINT(1) DEFAULT 0,
  created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Billing events log ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS billing_events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id   INT UNSIGNED,
  event_type  VARCHAR(100),
  stripe_id   VARCHAR(100),
  payload     TEXT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Seed data ────────────────────────────────────────────────
INSERT IGNORE INTO tenants (id, name, slug, plan) VALUES
  (1, 'Demo Company', 'demo', 'pro');

INSERT IGNORE INTO branches (id, tenant_id, name, address, phone) VALUES
  (1, 1, 'Main Branch', '123 Main Street, City', '+1-555-0100'),
  (2, 1, 'Downtown Branch', '456 Downtown Ave, City', '+1-555-0200');

-- Password: password  (bcrypt)
INSERT IGNORE INTO users (tenant_id, branch_id, name, email, password, role) VALUES
  (1, NULL, 'Super Admin', 'admin@demo.com',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

INSERT IGNORE INTO company_settings (tenant_id, company_name, address, phone, currency, tax_label) VALUES
  (1, 'Demo Company', '123 Main Street, City', '+1-555-0100', '$', 'VAT');

INSERT IGNORE INTO expense_categories (tenant_id, name) VALUES
  (1,'Rent'),(1,'Utilities'),(1,'Salary'),(1,'Supplies'),(1,'Marketing'),(1,'Other');

INSERT IGNORE INTO invoice_sequences (tenant_id, branch_id, prefix, last_number) VALUES
  (1,1,'INV',0),(1,2,'INV',0);
