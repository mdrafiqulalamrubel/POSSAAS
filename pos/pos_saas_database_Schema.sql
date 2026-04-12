-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 12:13 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos_saas`
--
CREATE DATABASE IF NOT EXISTS `pos_saas` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos_saas`;

-- --------------------------------------------------------

--
-- Table structure for table `billing_events`
--

CREATE TABLE `billing_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `stripe_id` varchar(100) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` VALUES(1, 1, 'Main Branch', '123 Main Street, City', '+1-555-0100', 1, '2026-04-09 19:52:28');
INSERT INTO `branches` VALUES(2, 1, 'Downtown Branch', '456 Downtown Ave, City', '+1-555-0200', 1, '2026-04-09 19:52:28');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(150) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `phone2` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `logo_path` varchar(300) DEFAULT NULL,
  `currency` varchar(10) DEFAULT '$',
  `currency_code` varchar(5) DEFAULT 'USD',
  `tax_label` varchar(30) DEFAULT 'VAT',
  `invoice_prefix` varchar(10) DEFAULT 'INV',
  `footer_note` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` VALUES(1, 1, 'Daffodil Company', '123 Main Street, City', '', '', '+1-555-0100', '', '', '', 'uploads/logos/logo_1_1775972746.jpg', 'BDT', 'USD', 'VAT', 'INV', '', '2026-04-12 11:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` VALUES(1, 1, 'MUHAMMAD RAFIQUL ALAM ALAM', 'sa@daffodil-bd.com', '01713493130', '', '2026-04-09 20:36:46');
INSERT INTO `customers` VALUES(2, 1, 'Walk in Customer', '', '', '', '2026-04-12 11:50:36');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `ref_no` varchar(40) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` VALUES(1, 1, 'Rent');
INSERT INTO `expense_categories` VALUES(2, 1, 'Utilities');
INSERT INTO `expense_categories` VALUES(3, 1, 'Salary');
INSERT INTO `expense_categories` VALUES(4, 1, 'Supplies');
INSERT INTO `expense_categories` VALUES(5, 1, 'Marketing');
INSERT INTO `expense_categories` VALUES(6, 1, 'Other');

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `invoice_no` varchar(40) NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_pct` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(14,2) DEFAULT 0.00,
  `discount` decimal(14,2) DEFAULT 0.00,
  `total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid` decimal(14,2) DEFAULT 0.00,
  `balance` decimal(14,2) GENERATED ALWAYS AS (`total` - `paid`) STORED,
  `status` enum('draft','unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `income`
--

INSERT INTO `income` VALUES(1, 1, 1, 'INV-00001', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-09', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 300.00, 'partial', '', 1, '2026-04-09 20:37:14');
INSERT INTO `income` VALUES(2, 1, 1, 'INV-00002', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, 'unpaid', '', 1, '2026-04-12 11:04:24');
INSERT INTO `income` VALUES(3, 1, 1, 'INV-00003', NULL, '', '2026-04-12', '0000-00-00', 550.00, 0.00, 0.00, 0.00, 550.00, 0.00, 'unpaid', '', 1, '2026-04-12 11:44:56');
INSERT INTO `income` VALUES(4, 1, 1, 'INV-00004', NULL, '', '2026-04-12', '0000-00-00', 1100.00, 0.00, 0.00, 0.00, 1100.00, 0.00, 'unpaid', '', 1, '2026-04-12 13:45:37');
INSERT INTO `income` VALUES(5, 1, 1, 'INV-00005', NULL, '', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, 'unpaid', '', 1, '2026-04-12 13:49:06');
INSERT INTO `income` VALUES(6, 1, 1, 'INV-00006', NULL, '', '2026-04-12', '0000-00-00', 2750.00, 0.00, 0.00, 0.00, 2750.00, 1000.00, 'partial', '', 1, '2026-04-12 13:58:22');
INSERT INTO `income` VALUES(7, 1, 1, 'INV-00007', NULL, '', '2026-04-12', '0000-00-00', 1000.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 'unpaid', '', 1, '2026-04-12 15:08:51');
INSERT INTO `income` VALUES(8, 1, 1, 'INV-00008', NULL, '', '2026-04-12', '0000-00-00', 50000.00, 0.00, 0.00, 0.00, 50000.00, 0.00, 'unpaid', '', 1, '2026-04-12 15:14:02');
INSERT INTO `income` VALUES(9, 1, 1, 'INV-00009', NULL, '', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 50.00, 450.00, 0.00, 'unpaid', '', 1, '2026-04-12 15:26:57');

-- --------------------------------------------------------

--
-- Table structure for table `income_items`
--

CREATE TABLE `income_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `income_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(10,3) DEFAULT 1.000,
  `unit_price` decimal(14,2) NOT NULL,
  `total` decimal(14,2) GENERATED ALWAYS AS (`qty` * `unit_price`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `income_items`
--

INSERT INTO `income_items` VALUES(2, 1, NULL, 'Mouse A4Teach', 1.000, 500.00);
INSERT INTO `income_items` VALUES(3, 2, NULL, 'Mouse A4Teach', 1.000, 500.00);
INSERT INTO `income_items` VALUES(8, 3, NULL, 'Mouse A4Teach', 1.000, 550.00);
INSERT INTO `income_items` VALUES(10, 4, NULL, 'Mouse A4Teach', 2.000, 550.00);
INSERT INTO `income_items` VALUES(12, 5, NULL, 'Keyboard A4Tech', 1.000, 500.00);
INSERT INTO `income_items` VALUES(13, 6, NULL, 'Mouse A4Teach', 5.000, 550.00);
INSERT INTO `income_items` VALUES(14, 7, NULL, 'Keyboard A4Tech', 1.000, 500.00);
INSERT INTO `income_items` VALUES(15, 7, NULL, 'Mouse A4Teach', 1.000, 500.00);
INSERT INTO `income_items` VALUES(16, 8, NULL, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00);
INSERT INTO `income_items` VALUES(17, 9, 2, 'Keyboard A4Tech', 1.000, 500.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_sequences`
--

CREATE TABLE `invoice_sequences` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(10) DEFAULT 'INV',
  `last_number` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_sequences`
--

INSERT INTO `invoice_sequences` VALUES(1, 1, 1, 'INV', 9);
INSERT INTO `invoice_sequences` VALUES(2, 1, 2, 'INV', 0);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `unit` varchar(30) DEFAULT 'pcs',
  `quantity` decimal(12,3) DEFAULT 0.000,
  `unit_price` decimal(14,2) DEFAULT 0.00,
  `reorder_level` decimal(12,3) DEFAULT 0.000,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` VALUES(1, 1, 1, 'Mouse A4Teach', 'B1', 'Accessories', 'pcs', 10.000, 550.00, 5.000, NULL, '', 1, 1, '2026-04-12 10:58:00', '2026-04-12 11:44:05');
INSERT INTO `items` VALUES(2, 1, 1, 'Keyboard A4Tech', 'A1', 'Accessories', 'pcs', 9.000, 500.00, 5.000, NULL, '', 1, 1, '2026-04-12 10:58:32', '2026-04-12 15:26:57');
INSERT INTO `items` VALUES(3, 1, 1, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', '', 'PC', 'pcs', 10.000, 50000.00, 2.000, NULL, '', 1, 1, '2026-04-12 15:12:49', '2026-04-12 15:13:30');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `stripe_customer_id` varchar(100) DEFAULT NULL,
  `stripe_sub_id` varchar(100) DEFAULT NULL,
  `plan` enum('trial','basic','pro') DEFAULT 'trial',
  `status` enum('active','past_due','cancelled','trialing') DEFAULT 'trialing',
  `current_period_end` datetime DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(60) NOT NULL,
  `plan` enum('trial','basic','pro') DEFAULT 'trial',
  `is_active` tinyint(1) DEFAULT 1,
  `trial_ends` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` VALUES(1, 'Demo Company', 'demo', 'pro', 1, NULL, '2026-04-09 19:52:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','manager','cashier') DEFAULT 'cashier',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` VALUES(1, 1, NULL, 'Super Admin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2026-04-09 19:52:28');
INSERT INTO `users` VALUES(2, 1, NULL, 'Super Admin', 'superadmin@platform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, '2026-04-12 12:06:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_events`
--
ALTER TABLE `billing_events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_invoice` (`tenant_id`,`invoice_no`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `income_items`
--
ALTER TABLE `income_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `income_id` (`income_id`),
  ADD KEY `idx_item` (`item_id`);

--
-- Indexes for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_seq` (`tenant_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_expiry` (`expiry_date`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email_tenant` (`tenant_id`,`email`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_events`
--
ALTER TABLE `billing_events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `income_items`
--
ALTER TABLE `income_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD CONSTRAINT `company_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD CONSTRAINT `expense_categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `income`
--
ALTER TABLE `income`
  ADD CONSTRAINT `income_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `income_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `income_ibfk_3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `income_items`
--
ALTER TABLE `income_items`
  ADD CONSTRAINT `income_items_ibfk_1` FOREIGN KEY (`income_id`) REFERENCES `income` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_sequences`
--
ALTER TABLE `invoice_sequences`
  ADD CONSTRAINT `invoice_sequences_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_sequences_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `items_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
