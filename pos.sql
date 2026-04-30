-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 19, 2026 at 09:04 AM
-- Server version: 5.7.44-48
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `posdemob_pos_saas`
--
CREATE DATABASE IF NOT EXISTS `posdemob_pos_saas` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `posdemob_pos_saas`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `user_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_role` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `module` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_ci,
  `meta` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `tenant_id`, `branch_id`, `user_id`, `user_name`, `user_role`, `action`, `module`, `description`, `meta`, `ip_address`, `created_at`) VALUES
(1, 1, 1, 1, 'Super Admin', 'admin', 'login', 'Auth', 'User logged in', 'admin@demo.com', '203.190.8.147', '2026-04-19 07:19:30'),
(2, 1, 1, 1, 'Super Admin', 'admin', 'logout', 'Auth', 'User logged out', '', '203.190.8.147', '2026-04-19 07:20:27'),
(3, 1, 1, 1, 'Super Admin', 'admin', 'login', 'Auth', 'User logged in', 'admin@demo.com', '203.190.8.147', '2026-04-19 07:20:30'),
(4, 1, 3, 1, 'Super Admin', 'admin', 'logout', 'Auth', 'User logged out', '', '203.190.8.147', '2026-04-19 07:20:49'),
(5, 1, 1, 1, 'Super Admin', 'admin', 'login', 'Auth', 'User logged in', 'admin@demo.com', '203.190.8.147', '2026-04-19 07:21:02'),
(6, 1, 1, 1, 'Super Admin', 'admin', 'logout', 'Auth', 'User logged out', '', '203.190.8.147', '2026-04-19 07:21:27'),
(7, 1, 1, 1, 'Super Admin', 'admin', 'login', 'Auth', 'User logged in', 'admin@demo.com', '203.190.8.147', '2026-04-19 07:29:03'),
(8, 1, 1, 1, 'Super Admin', 'admin', 'logout', 'Auth', 'User logged out', '', '203.190.8.147', '2026-04-19 08:03:20');

-- --------------------------------------------------------

--
-- Table structure for table `billing_events`
--

CREATE TABLE `billing_events` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `branch_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `tenant_id`, `name`, `address`, `phone`, `is_active`, `branch_password`, `created_at`) VALUES
(1, 1, 'Main Branch', 'Dhanmondi, Dhaka, Bangladesh ', '01782382140', 1, NULL, '2026-04-09 19:52:28'),
(2, 1, 'Town hall Branch, Mdpur', 'Mohammedpur, Dhaka, Bangladesh ', '', 1, NULL, '2026-04-09 19:52:28'),
(3, 1, 'Dhanmondi Branch', NULL, NULL, 1, '$2y$10$qDo2n89aF.lbZi27AoqlK.jAAe5wzVUi4AO.waQRirnVYBh/rqIEy', '2026-04-16 03:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '$',
  `currency_code` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `tax_label` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'VAT',
  `invoice_prefix` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'INV',
  `footer_note` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `loyalty_earn_amount` decimal(10,2) NOT NULL DEFAULT '100.00',
  `loyalty_earn_points` int(11) NOT NULL DEFAULT '1',
  `loyalty_redeem_value` decimal(10,4) NOT NULL DEFAULT '1.0000',
  `loyalty_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `loyalty_min_redeem` int(11) NOT NULL DEFAULT '10',
  `loyalty_expiry_days` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `tenant_id`, `company_name`, `address`, `city`, `country`, `phone`, `phone2`, `email`, `website`, `logo_path`, `currency`, `currency_code`, `tax_label`, `invoice_prefix`, `footer_note`, `updated_at`, `loyalty_earn_amount`, `loyalty_earn_points`, `loyalty_redeem_value`, `loyalty_enabled`, `loyalty_min_redeem`, `loyalty_expiry_days`) VALUES
(1, 1, 'Daffodil Software Limited', 'Dhaka, BD', 'Dhaka', 'Bangladesh', '+8801713493130', '', 'sa@daffodil-bd.com', '', 'uploads/logos/logo_1_1776018677.jpg', '৳', 'BDT', 'VAT', 'INV', 'Copyright Daffodil Software Plc', '2026-04-12 11:32:08', 100.00, 1, 1.0000, 1, 10, 0);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `loyalty_points` int(11) NOT NULL DEFAULT '0',
  `membership_tier_id` int(11) DEFAULT NULL,
  `membership_password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `loyalty_points`, `membership_tier_id`, `membership_password`, `created_at`) VALUES
(6, 1, 'MUHAMMAD RAFIQUL ALAM ALAM', 'sa@daffodil-bd.com', '01713493130', '64/3, Lake Circus, Kalabagan\r\nDhaka', 0, NULL, '', '2026-04-14 23:56:09'),
(7, 1, 'Reaz Ahmed ', '', '', '', 0, NULL, '', '2026-04-14 23:57:05'),
(8, 1, 'Walk-in Customer', '', '', NULL, 0, NULL, '', '2026-04-14 23:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `ref_no` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` enum('pending','approved','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `tenant_id`, `branch_id`, `ref_no`, `category_id`, `supplier`, `description`, `date`, `amount`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 2, NULL, 5, 'Mr. Alam', '', '2026-04-13', 1000.00, 'paid', '', 1, '2026-04-13 02:00:08');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `tenant_id`, `name`, `description`) VALUES
(0, 1, 'Rent', NULL),
(1, 1, 'Rent', NULL),
(2, 1, 'Utilities', NULL),
(3, 1, 'Salary', NULL),
(4, 1, 'Supplies', NULL),
(5, 1, 'Marketing', NULL),
(6, 1, 'Other', NULL),
(7, 1, 'Conveyance', '');

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `invoice_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_pct` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(14,2) DEFAULT '0.00',
  `discount` decimal(14,2) DEFAULT '0.00',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid` decimal(14,2) DEFAULT '0.00',
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `balance` decimal(14,2) GENERATED ALWAYS AS ((`total` - `paid`)) STORED,
  `status` enum('draft','unpaid','partial','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `income`
--

INSERT INTO `income` (`id`, `tenant_id`, `branch_id`, `invoice_no`, `customer_id`, `customer_name`, `date`, `due_date`, `subtotal`, `tax_pct`, `tax_amount`, `discount`, `total`, `paid`, `payment_method`, `status`, `notes`, `created_by`, `created_at`) VALUES
(0, 1, 1, 'INV-00011', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 500.00, 'cash', 'paid', '', 1, '2026-04-12 06:14:03'),
(1, 1, 1, 'INV-00001', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-09', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 300.00, 'cash', 'partial', '', 1, '2026-04-09 20:37:14'),
(2, 1, 1, 'INV-00002', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 11:04:24'),
(3, 1, 1, 'INV-00003', NULL, '', '2026-04-12', '0000-00-00', 550.00, 0.00, 0.00, 0.00, 550.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 11:44:56'),
(4, 1, 1, 'INV-00004', NULL, '', '2026-04-12', '0000-00-00', 1100.00, 0.00, 0.00, 0.00, 1100.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 13:45:37'),
(5, 1, 1, 'INV-00005', NULL, '', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 0.00, 500.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 13:49:06'),
(6, 1, 1, 'INV-00006', NULL, '', '2026-04-12', '0000-00-00', 2750.00, 0.00, 0.00, 0.00, 2750.00, 1000.00, 'cash', 'partial', '', 1, '2026-04-12 13:58:22'),
(7, 1, 1, 'INV-00007', NULL, '', '2026-04-12', '0000-00-00', 1000.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 15:08:51'),
(8, 1, 1, 'INV-00008', NULL, '', '2026-04-12', NULL, 50000.00, 0.00, 0.00, 0.00, 50000.00, 50000.00, 'cash', 'paid', '', 1, '2026-04-12 15:14:02'),
(9, 1, 1, 'INV-00009', NULL, '', '2026-04-12', '0000-00-00', 500.00, 0.00, 0.00, 50.00, 450.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 15:26:57'),
(10, 1, 1, 'INV-00010', NULL, '', '2026-04-12', '0000-00-00', 3850.00, 0.00, 0.00, 0.00, 3850.00, 0.00, 'cash', 'unpaid', '', 1, '2026-04-12 16:28:26'),
(11, 1, 1, 'INV-00012', 3, 'Md. Ismat Toha', '2026-04-12', NULL, 500.00, 0.00, 0.00, 0.00, 500.00, 500.00, 'cash', 'paid', '', 1, '2026-04-12 07:26:08'),
(12, 1, 2, 'INV-00013', 4, 'Md. Ismat Toha', '2026-04-12', NULL, 255.00, 0.00, 0.00, 5.00, 250.00, 250.00, 'cash', 'paid', '', 1, '2026-04-12 09:59:22'),
(13, 1, 2, 'INV-00014', 1, 'MUHAMMAD RAFIQUL ALAM ALAM', '2026-04-12', NULL, 160.00, 0.00, 0.00, 0.00, 160.00, 160.00, 'cash', 'paid', '', 0, '2026-04-12 10:51:20'),
(14, 1, 1, 'INV-00015', 5, 'Walk-in Customer', '2026-04-13', NULL, 51300.00, 0.00, 0.00, 0.00, 51300.00, 51300.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-13 05:57:56'),
(15, 1, 1, 'INV-00016', 5, 'Walk-in Customer', '2026-04-13', NULL, 750.00, 0.00, 0.00, 0.00, 750.00, 750.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-13 06:02:38'),
(16, 1, 1, 'INV-00017', 5, 'Walk-in Customer', '2026-04-13', NULL, 51300.00, 0.00, 0.00, 0.00, 51300.00, 51300.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-13 06:14:52'),
(17, 1, 1, 'INV-00018', 5, 'Walk-in Customer', '2026-04-13', NULL, 52950.00, 0.00, 0.00, 0.00, 52950.00, 52950.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-13 06:24:02'),
(18, 1, 1, 'INV-00019', 5, 'Walk-in Customer', '2026-04-13', NULL, 1500.00, 0.00, 0.00, 0.00, 1500.00, 1500.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-13 11:07:43'),
(19, 1, 2, 'INV-00020', 5, 'Walk-in Customer', '2026-04-14', NULL, 250.00, 0.00, 0.00, 0.00, 250.00, 250.00, 'cash', 'paid', 'POS Sale · Payment: nagad', 1, '2026-04-14 06:48:02'),
(20, 1, 1, 'INV-00021', 5, 'Walk-in Customer', '2026-04-15', NULL, 250.00, 0.00, 0.00, 0.00, 250.00, 250.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-14 21:48:50'),
(21, 1, 1, 'INV-00022', 5, 'Walk-in Customer', '2026-04-15', NULL, 250.00, 0.00, 0.00, 0.00, 250.00, 250.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-14 22:43:37'),
(22, 1, 1, 'INV-00023', 6, 'MUHAMMAD RAFIQUL ALAM ALAM · 01713493130', '2026-04-15', NULL, 1100.00, 0.00, 0.00, 0.00, 1100.00, 1100.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-14 23:58:27'),
(23, 1, 1, 'INV-00024', 8, 'Walk-in Customer', '2026-04-15', NULL, 550.00, 0.00, 0.00, 0.00, 550.00, 550.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-14 23:58:50'),
(24, 1, 1, 'INV-00025', 8, 'Walk-in Customer', '2026-04-16', NULL, 30.00, 0.00, 0.00, 0.00, 30.00, 30.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-15 23:16:18'),
(25, 1, 1, 'INV-00026', 8, 'Walk-in Customer', '2026-04-16', NULL, 550.00, 0.00, 0.00, 0.00, 550.00, 550.00, 'cash', 'paid', 'POS Sale · Payment: cash', 1, '2026-04-15 23:17:06'),
(26, 1, 1, 'INV-00027', 8, 'Walk-in Customer', '2026-04-16', NULL, 550.00, 0.00, 0.00, 0.00, 550.00, 550.00, 'cash', 'paid', 'POS Sale · Payment: upay', 1, '2026-04-15 23:17:57'),
(27, 1, 1, 'INV-00028', 8, 'Walk-in Customer', '2026-04-16', NULL, 114.00, 0.00, 0.00, 0.00, 114.00, 114.00, 'cash', 'paid', 'POS Sale · Payment: cash', 0, '2026-04-15 23:40:21'),
(28, 1, 1, 'INV-00029', 8, 'Walk-in Customer', '2026-04-16', '0000-00-00', 550.00, 0.00, 0.00, 50.00, 500.00, 500.00, 'cash', 'paid', 'POS Sale · Payment: card', 1, '2026-04-15 23:50:50'),
(29, 1, 1, 'INV-00030', NULL, '', '2026-04-19', '0000-00-00', 0.00, 2.00, 0.00, 0.00, 0.00, 0.00, 'cash', 'unpaid', '', 0, '2026-04-19 04:24:39'),
(30, 1, 1, 'INV-00031', 8, 'Walk-in Customer', '2026-04-19', NULL, 5560.00, 0.00, 0.00, 0.00, 5560.00, 5560.00, 'cash', 'paid', 'POS Sale', 1, '2026-04-19 07:13:25');

-- --------------------------------------------------------

--
-- Table structure for table `income_items`
--

CREATE TABLE `income_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `income_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(10,3) DEFAULT '1.000',
  `unit_price` decimal(14,2) NOT NULL,
  `total` decimal(14,2) GENERATED ALWAYS AS ((`qty` * `unit_price`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `income_items`
--

INSERT INTO `income_items` (`id`, `income_id`, `item_id`, `description`, `qty`, `unit_price`) VALUES
(1, 0, 2, 'Keyboard A4Tech', 1.000, 500.00),
(2, 1, NULL, 'Mouse A4Teach', 1.000, 500.00),
(3, 2, NULL, 'Mouse A4Teach', 1.000, 500.00),
(8, 3, NULL, 'Mouse A4Teach', 1.000, 550.00),
(10, 4, NULL, 'Mouse A4Teach', 2.000, 550.00),
(12, 5, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(13, 6, NULL, 'Mouse A4Teach', 5.000, 550.00),
(14, 7, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(15, 7, NULL, 'Mouse A4Teach', 1.000, 500.00),
(17, 9, 2, 'Keyboard A4Tech', 1.000, 500.00),
(25, 10, 1, 'Mouse A4Teach', 4.000, 550.00),
(26, 10, 1, 'Mouse A4Teach', 3.000, 550.00),
(31, 11, 2, 'Keyboard A4Tech', 1.000, 500.00),
(32, 8, NULL, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00),
(34, 13, 5, 'Dal 1kg', 1.000, 160.00),
(35, 12, 4, 'Chal Minicat', 3.000, 85.00),
(36, 14, NULL, 'Mouse Pad A240', 1.000, 250.00),
(37, 14, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(38, 14, NULL, 'Mouse A4Teach', 1.000, 550.00),
(39, 14, NULL, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00),
(40, 15, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(41, 15, NULL, 'Mouse Pad A240', 1.000, 250.00),
(42, 16, NULL, 'Mouse Pad A240', 1.000, 250.00),
(43, 16, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(44, 16, NULL, 'Mouse A4Teach', 1.000, 550.00),
(45, 16, NULL, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00),
(46, 17, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(47, 17, NULL, 'Mouse A4Teach', 4.000, 550.00),
(48, 17, NULL, 'Mouse Pad A240', 1.000, 250.00),
(49, 17, NULL, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00),
(50, 18, NULL, 'Mouse Pad A240', 4.000, 250.00),
(51, 18, NULL, 'Keyboard A4Tech', 1.000, 500.00),
(52, 19, NULL, 'Mouse Pad A240', 1.000, 250.00),
(53, 20, NULL, 'Mouse Pad A240', 1.000, 250.00),
(54, 21, NULL, 'Mouse Pad A240', 1.000, 250.00),
(55, 22, NULL, 'Mouse A4Teach', 2.000, 550.00),
(56, 23, NULL, 'Mouse A4Teach', 1.000, 550.00),
(57, 24, NULL, 'Wireless Router 56rt89', 3.000, 10.00),
(58, 25, NULL, 'Mouse A4Teach', 1.000, 550.00),
(59, 26, NULL, 'Mouse A4Teach', 1.000, 550.00),
(60, 27, NULL, 'Egg', 12.000, 8.25),
(61, 27, NULL, 'Anargy Biscuit', 1.000, 15.00),
(63, 28, NULL, 'Mouse A4Teach', 1.000, 550.00),
(65, 30, NULL, 'Mouse A4Teach', 1.000, 550.00),
(66, 30, NULL, 'Wireless Router 56rt', 1.000, 5000.00),
(67, 30, NULL, 'Wireless Router 56rt89', 1.000, 10.00);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_sequences`
--

CREATE TABLE `invoice_sequences` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_number` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoice_sequences`
--

INSERT INTO `invoice_sequences` (`id`, `tenant_id`, `branch_id`, `prefix`, `last_number`) VALUES
(1, 1, 1, 'INV', 31),
(2, 1, 2, 'INV', 20);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode_value` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'pcs',
  `quantity` decimal(12,3) DEFAULT '0.000',
  `unit_price` decimal(14,2) DEFAULT '0.00',
  `reorder_level` decimal(12,3) DEFAULT '0.000',
  `expiry_date` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `tenant_id`, `branch_id`, `name`, `sku`, `barcode_value`, `category`, `brand`, `brand_name`, `unit`, `quantity`, `unit_price`, `reorder_level`, `expiry_date`, `notes`, `image_path`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Mouse A4Teach', 'B1', '', 'Accessories', NULL, '', 'pcs', 4.000, 550.00, 5.000, NULL, '', 'uploads/items/item_1_1776085015.jpg', 0, 1, '2026-04-12 10:58:00', '2026-04-14 23:47:43'),
(2, 1, 1, 'Keyboard A4Tech', 'A1', '', 'Accessories', NULL, '', 'pcs', 8.000, 500.00, 5.000, NULL, '', 'uploads/items/item_1_1776085025.png', 0, 1, '2026-04-12 10:58:32', '2026-04-14 23:47:41'),
(3, 1, 1, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', '', '', 'PC', NULL, '', 'pcs', 11.000, 50000.00, 2.000, NULL, '', 'uploads/items/item_1_1776084990.png', 0, 1, '2026-04-12 15:12:49', '2026-04-14 23:47:45'),
(4, 1, 2, 'Chal Minicat', 'CMC', '', 'Food', NULL, '', 'kg', 109.000, 85.00, 10.000, '2026-04-30', '', NULL, 1, 1, '2026-04-12 09:58:33', '2026-04-12 10:52:24'),
(5, 1, 2, 'Dal 1kg', 'DAL1', '', 'Food', NULL, '', 'kg', 14.000, 160.00, 10.000, NULL, '', NULL, 1, 1, '2026-04-12 10:12:26', '2026-04-12 10:51:39'),
(6, 1, 1, 'Mouse Pad A240', 'MPAD', '', '', NULL, '', 'pcs', 4.000, 250.00, 0.000, NULL, '', 'uploads/items/item_1_1776085004.jpg', 0, 1, '2026-04-12 21:42:00', '2026-04-14 23:47:47'),
(7, 1, 2, 'Mouse Pad A240', 'MPAD', '', '', NULL, '', 'pcs', 4.000, 250.00, 0.000, NULL, NULL, NULL, 1, 1, '2026-04-13 01:57:48', '2026-04-13 01:57:58'),
(8, 1, 1, 'Mouse A4Teach', '', '', '', NULL, '', 'pcs', 10.000, 550.00, 5.000, '2026-05-30', '', 'uploads/items/item_1_1776235951.webp', 1, 1, '2026-04-14 23:52:32', '2026-04-14 23:52:32'),
(9, 1, 1, 'Keyboard A4 Tech', 'KEY10', '', 'PC', NULL, '', 'pcs', 0.000, 750.00, 0.000, NULL, '', 'uploads/items/item_1_1776236534.jpg', 1, 1, '2026-04-15 00:01:50', '2026-04-15 00:02:15'),
(10, 1, 1, 'Wireless Router', 'ROUTER123456', '', 'Accessories', NULL, 'Netis', 'pcs', 0.000, 0.00, 0.000, NULL, '', 'uploads/items/item_1_1776319797.jpg', 1, 1, '2026-04-15 23:09:57', '2026-04-15 23:09:57'),
(11, 1, 1, 'Wireless Router 56rt', 'ROUTER123456', '', 'Accessories', NULL, 'Netis', 'pcs', 150.000, 5000.00, 5.000, NULL, '', 'uploads/items/item_1_1776319873.jpg', 1, 1, '2026-04-15 23:11:13', '2026-04-15 23:11:13'),
(12, 1, 1, 'Wireless Router 56rt89', 'ROUTER123456', '', 'Accessories', NULL, 'Netis', 'pcs', 500.000, 10.00, 5.000, NULL, '', 'uploads/items/item_1_1776319943.png', 1, 1, '2026-04-15 23:12:22', '2026-04-15 23:12:22'),
(13, 1, 1, 'Anargy Biscuit', '1245645257857', '', 'Biscuit', NULL, 'Anargy', 'pcs', 100.000, 15.00, 80.000, '2026-05-29', '', NULL, 1, 0, '2026-04-15 23:36:18', '2026-04-15 23:36:18'),
(14, 1, 1, 'Egg', '1245235478521', '', 'Food', NULL, 'EGG', 'pcs', 1000.000, 8.25, 800.000, '2026-04-30', '', NULL, 1, 0, '2026-04-15 23:37:48', '2026-04-15 23:37:48'),
(15, 1, 1, 'Meril', 'squ', '', 'skincare', '', 'Square', 'pcs', 10.000, 20.00, 10.000, '2027-04-19', '', NULL, 1, 0, '2026-04-19 04:28:08', '2026-04-19 05:05:15');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

CREATE TABLE `loyalty_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `type` enum('earn','redeem') COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int(11) NOT NULL DEFAULT '0',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_tiers`
--

CREATE TABLE `membership_tiers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_points` int(11) NOT NULL DEFAULT '0',
  `discount_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `color` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `ref_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_pct` decimal(5,2) DEFAULT '0.00',
  `tax_amount` decimal(14,2) DEFAULT '0.00',
  `discount` decimal(14,2) DEFAULT '0.00',
  `total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid` decimal(14,2) DEFAULT '0.00',
  `balance` decimal(14,2) GENERATED ALWAYS AS ((`total` - `paid`)) STORED,
  `status` enum('draft','received','partial','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'received',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `tenant_id`, `branch_id`, `ref_no`, `supplier_id`, `supplier_name`, `date`, `subtotal`, `tax_pct`, `tax_amount`, `discount`, `total`, `paid`, `status`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 1, 'PO-00001', 1, 'Tasty Treet Dhanmondi', '2026-04-12', 51050.00, 0.00, 0.00, 0.00, 51050.00, 5000.00, 'received', 'Thanks ', 1, '2026-04-12 16:26:31'),
(2, 1, 1, 'PUR-00013', 0, 'Green Garden', '2026-04-13', 1000.00, 0.00, 0.00, 0.00, 1000.00, 1000.00, 'received', '', 1, '2026-04-12 21:42:04'),
(3, 1, 2, 'PUR-00015', 0, 'Green Garden', '2026-04-13', 1000.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 'received', '', 1, '2026-04-13 01:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `purchase_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` decimal(10,3) DEFAULT '1.000',
  `unit_price` decimal(14,2) NOT NULL,
  `total` decimal(14,2) GENERATED ALWAYS AS ((`qty` * `unit_price`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `item_id`, `description`, `qty`, `unit_price`) VALUES
(1, 1, 1, 'Mouse A4Teach', 1.000, 550.00),
(2, 1, 3, 'PC - 7007- Intell 16th Gen RAM128 HDD 2TB', 1.000, 50000.00),
(3, 1, 2, 'Keyboard A4Tech', 1.000, 500.00),
(4, 2, 6, 'Mouse Pad A240', 4.000, 250.00),
(5, 3, 7, 'Mouse Pad A240', 4.000, 250.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `return_no` varchar(50) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(150) DEFAULT NULL,
  `date` date NOT NULL,
  `total` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(10,3) DEFAULT '1.000',
  `unit_price` decimal(12,2) DEFAULT '0.00'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_sequences`
--

CREATE TABLE `purchase_sequences` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED NOT NULL,
  `prefix` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'PO',
  `last_number` int(10) UNSIGNED DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `purchase_sequences`
--

INSERT INTO `purchase_sequences` (`id`, `tenant_id`, `branch_id`, `prefix`, `last_number`) VALUES
(1, 1, 1, 'PO', 1),
(2, 1, 2, 'PO', 0);

-- --------------------------------------------------------

--
-- Table structure for table `sales_returns`
--

CREATE TABLE `sales_returns` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `income_id` int(11) DEFAULT NULL,
  `return_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `date` date NOT NULL,
  `total` decimal(12,2) DEFAULT '0.00',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sales_return_items`
--

CREATE TABLE `sales_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(10,3) DEFAULT '1.000',
  `unit_price` decimal(12,2) DEFAULT '0.00'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfers`
--

CREATE TABLE `stock_transfers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `from_branch_id` int(11) NOT NULL,
  `to_branch_id` int(11) NOT NULL,
  `transfer_no` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `notes` text,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `stock_transfer_items`
--

CREATE TABLE `stock_transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `qty` decimal(10,3) DEFAULT '1.000'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `stripe_customer_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_sub_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan` enum('trial','basic','pro') COLLATE utf8mb4_unicode_ci DEFAULT 'trial',
  `status` enum('active','past_due','cancelled','trialing') COLLATE utf8mb4_unicode_ci DEFAULT 'trialing',
  `current_period_end` datetime DEFAULT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `tenant_id`, `name`, `phone`, `email`, `address`, `is_active`, `created_at`) VALUES
(1, 1, 'Tasty Treet Dhanmondi', NULL, NULL, NULL, 1, '2026-04-12 16:26:31'),
(0, 1, 'Any', NULL, NULL, NULL, 1, '2026-04-12 10:06:02'),
(0, 1, 'Any', NULL, NULL, NULL, 1, '2026-04-12 10:06:16'),
(0, 1, 'Green Garden', NULL, NULL, NULL, 1, '2026-04-12 21:42:04'),
(0, 1, 'Green Garden', NULL, NULL, NULL, 1, '2026-04-13 01:57:58');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plan` enum('trial','basic','pro') COLLATE utf8mb4_unicode_ci DEFAULT 'trial',
  `is_active` tinyint(1) DEFAULT '1',
  `trial_ends` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `slug`, `plan`, `is_active`, `trial_ends`, `created_at`) VALUES
(1, 'Demo Company', 'demo', 'pro', 1, NULL, '2026-04-09 19:52:28'),
(1, 'Demo Company', 'demo', 'pro', 1, NULL, '2026-04-12 06:10:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('superadmin','admin','manager','cashier') COLLATE utf8mb4_unicode_ci DEFAULT 'cashier',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `branch_id`, `name`, `email`, `password`, `role`, `is_active`, `created_at`) VALUES
(1, 1, NULL, 'Super Admin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, '2026-04-09 19:52:28'),
(2, 1, NULL, 'Super Admin', 'superadmin@platform.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, '2026-04-12 12:06:05'),
(0, 1, 2, 'Rubel', 'sa@daffodil-bd.com', '$2y$10$u68wZrMoNN2g/641YHcp6.QWpg6JrvCdp5XRLTV9Mh8bsFY8f8Jku', 'cashier', 1, '2026-04-12 10:49:18'),
(0, 1, 1, 'demo', 'demo@demo.com', '$2y$10$rIzhzO71JB09tu7pcl51cOPRMjwy7lDgSmSTrGmeNVhZS.lgfve72', 'cashier', 1, '2026-04-15 21:20:54'),
(0, 1, NULL, 'Admin', 'admin@demo.com', '$2y$10$5cZUVIgAQlh8OyY03vB4F.Yy0KjDqXkPrNydfh3H57hmbx7xQggHW', 'admin', 1, '2026-04-15 21:25:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_module` (`module`);

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
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_payment_method` (`payment_method`);

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
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `membership_tiers`
--
ALTER TABLE `membership_tiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ref` (`tenant_id`,`ref_no`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `idx_item` (`item_id`);

--
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`,`branch_id`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

--
-- Indexes for table `purchase_sequences`
--
ALTER TABLE `purchase_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_seq` (`tenant_id`,`branch_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `sales_returns`
--
ALTER TABLE `sales_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`,`branch_id`),
  ADD KEY `income_id` (`income_id`);

--
-- Indexes for table `sales_return_items`
--
ALTER TABLE `sales_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

--
-- Indexes for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transfer_id` (`transfer_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `income_items`
--
ALTER TABLE `income_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_tiers`
--
ALTER TABLE `membership_tiers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_returns`
--
ALTER TABLE `sales_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_return_items`
--
ALTER TABLE `sales_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfers`
--
ALTER TABLE `stock_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_transfer_items`
--
ALTER TABLE `stock_transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
