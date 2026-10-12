-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 09:34 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saleshub`
--

-- --------------------------------------------------------

--
-- Table structure for table `commissary_cashflow`
--

CREATE TABLE `commissary_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_cashflow_balance`
--

CREATE TABLE `commissary_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_categories`
--

CREATE TABLE `commissary_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commissary_categories`
--

INSERT INTO `commissary_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(19, 'Commissary', 'MEDICINE', 0, '2026-07-14 07:08:33');

-- --------------------------------------------------------

--
-- Table structure for table `commissary_cf_vat_selection`
--

CREATE TABLE `commissary_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commissary_cf_vat_selection`
--

INSERT INTO `commissary_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'Commissary', 2026, 7, 0.00, 0, 'Commissary', '2026-07-13 02:52:31');

-- --------------------------------------------------------

--
-- Table structure for table `commissary_cogs`
--

CREATE TABLE `commissary_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_dinein_rows`
--

CREATE TABLE `commissary_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_disbursement`
--

CREATE TABLE `commissary_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_expenses`
--

CREATE TABLE `commissary_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `commissary_expenses`
--

INSERT INTO `commissary_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `selected_for_cf`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-10', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 123.00, 0.00, 0.00, 0.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 984.00, 0, 'Commissary', '2026-04-10 02:05:32', '2026-04-10 02:05:32'),
(2, '2026-05-09', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 200.00, 900.00, 900.00, 900.00, 2900.00, 0, 'Commissary', '2026-05-09 01:50:07', '2026-05-09 01:50:07');

-- --------------------------------------------------------

--
-- Table structure for table `commissary_income_statement`
--

CREATE TABLE `commissary_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Commissary',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_month_end_inv`
--

CREATE TABLE `commissary_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_pdc`
--

CREATE TABLE `commissary_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_pl_revenue`
--

CREATE TABLE `commissary_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_purchases`
--

CREATE TABLE `commissary_purchases` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) DEFAULT 'Commissary',
  `entry_date` date NOT NULL,
  `date_col` date DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `or_cr_no` varchar(100) DEFAULT NULL,
  `amount_w_vat` decimal(15,2) DEFAULT 0.00,
  `total_vat_exclusive` decimal(15,2) DEFAULT 0.00,
  `input_taxes` decimal(15,2) DEFAULT 0.00,
  `non_vat_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount_vat_ex` decimal(15,2) DEFAULT 0.00,
  `purchases` decimal(15,2) DEFAULT 0.00,
  `staff_meal` decimal(15,2) DEFAULT 0.00,
  `fare` decimal(15,2) DEFAULT 0.00,
  `drinking_water` decimal(15,2) DEFAULT 0.00,
  `other_supplies` decimal(15,2) DEFAULT 0.00,
  `delivery_fee` decimal(15,2) DEFAULT 0.00,
  `kitchen_equipment` decimal(15,2) DEFAULT 0.00,
  `pest_control` decimal(15,2) DEFAULT 0.00,
  `office_supplies` decimal(15,2) DEFAULT 0.00,
  `bio_augmentation` decimal(15,2) DEFAULT 0.00,
  `misc` decimal(15,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(15,2) DEFAULT 0.00,
  `internet_communication` decimal(15,2) DEFAULT 0.00,
  `fuel_oil` decimal(15,2) DEFAULT 0.00,
  `electricity` decimal(15,2) DEFAULT 0.00,
  `bill_water` decimal(15,2) DEFAULT 0.00,
  `representation_expense` decimal(15,2) DEFAULT 0.00,
  `salary` decimal(15,2) DEFAULT 0.00,
  `sss_hdmf_ph_cont` decimal(15,2) DEFAULT 0.00,
  `taxes_licenses` decimal(15,2) DEFAULT 0.00,
  `solane` decimal(15,2) DEFAULT 0.00,
  `mnikki` decimal(15,2) DEFAULT 0.00,
  `office_equipment` decimal(15,2) DEFAULT 0.00,
  `insurance` decimal(15,2) DEFAULT 0.00,
  `commission` decimal(15,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_reconcile`
--

CREATE TABLE `commissary_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_sales_detail_rows`
--

CREATE TABLE `commissary_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_sales_report`
--

CREATE TABLE `commissary_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Commissary',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissary_supplier`
--

CREATE TABLE `commissary_supplier` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) DEFAULT 'Commissary',
  `supplier_name` varchar(255) DEFAULT NULL,
  `tin` varchar(50) DEFAULT NULL,
  `vat_status` varchar(20) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `terms` varchar(100) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `entry_date` date DEFAULT NULL,
  `date_col` date DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `or_cr_no` varchar(100) DEFAULT NULL,
  `amount_w_vat` decimal(15,2) DEFAULT 0.00,
  `total_vat_exclusive` decimal(15,2) DEFAULT 0.00,
  `input_taxes` decimal(15,2) DEFAULT 0.00,
  `non_vat_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount_vat_ex` decimal(15,2) DEFAULT 0.00,
  `purchases` decimal(15,2) DEFAULT 0.00,
  `staff_meal` decimal(15,2) DEFAULT 0.00,
  `fare` decimal(15,2) DEFAULT 0.00,
  `drinking_water` decimal(15,2) DEFAULT 0.00,
  `other_supplies` decimal(15,2) DEFAULT 0.00,
  `delivery_fee` decimal(15,2) DEFAULT 0.00,
  `kitchen_equipment` decimal(15,2) DEFAULT 0.00,
  `pest_control` decimal(15,2) DEFAULT 0.00,
  `office_supplies` decimal(15,2) DEFAULT 0.00,
  `bio_augmentation` decimal(15,2) DEFAULT 0.00,
  `misc` decimal(15,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(15,2) DEFAULT 0.00,
  `internet_communication` decimal(15,2) DEFAULT 0.00,
  `fuel_oil` decimal(15,2) DEFAULT 0.00,
  `electricity` decimal(15,2) DEFAULT 0.00,
  `bill_water` decimal(15,2) DEFAULT 0.00,
  `representation_expense` decimal(15,2) DEFAULT 0.00,
  `salary` decimal(15,2) DEFAULT 0.00,
  `sss_hdmf_ph_cont` decimal(15,2) DEFAULT 0.00,
  `taxes_licenses` decimal(15,2) DEFAULT 0.00,
  `solane` decimal(15,2) DEFAULT 0.00,
  `mnikki` decimal(15,2) DEFAULT 0.00,
  `office_equipment` decimal(15,2) DEFAULT 0.00,
  `insurance` decimal(15,2) DEFAULT 0.00,
  `commission` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_reports`
--

CREATE TABLE `daily_reports` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) DEFAULT 'Recovery',
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sales_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gulay_commissary` decimal(12,2) NOT NULL DEFAULT 0.00,
  `direct_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kitchen_manpower` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_manpower` decimal(12,2) NOT NULL DEFAULT 0.00,
  `overtime` decimal(12,2) NOT NULL DEFAULT 0.00,
  `undertime` decimal(12,2) NOT NULL DEFAULT 0.00,
  `extra_expenses` longtext DEFAULT NULL COMMENT 'JSON array of {label, amount, type} objects',
  `quota_target` decimal(12,2) NOT NULL DEFAULT 9000.00,
  `cogs_threshold` decimal(5,2) NOT NULL DEFAULT 30.00,
  `mp_threshold` decimal(5,2) NOT NULL DEFAULT 35.00,
  `np_threshold` decimal(5,2) NOT NULL DEFAULT 20.00,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_reports`
--

INSERT INTO `daily_reports` (`id`, `report_date`, `store_name`, `gross_sales`, `sales_discount`, `gulay_commissary`, `direct_purchases`, `other_expenses`, `kitchen_manpower`, `frontline_manpower`, `overtime`, `undertime`, `extra_expenses`, `quota_target`, `cogs_threshold`, `mp_threshold`, `np_threshold`, `created_by`, `created_at`, `updated_at`) VALUES
(9, '2026-07-15', 'Pub Express', 85810.05, 2416.29, 26532.32, 1953.39, 283.75, 1650.00, 1650.00, 171.86, 0.00, '[]', 50000.00, 45.00, 6.00, 15.00, 'Pub Express', '2026-07-15 07:55:06', '2026-07-15 07:55:06');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_acc_titles`
--

CREATE TABLE `demiclab_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_acc_titles`
--

INSERT INTO `demiclab_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-21 01:42:15'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-21 01:42:15'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-21 01:42:15'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-21 01:42:15'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-21 01:42:15'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-21 01:42:15'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-21 01:42:15'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-21 01:42:15'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-21 01:42:15'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-21 01:42:15'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-21 01:42:15'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-21 01:42:15'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-21 01:42:15'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-21 01:42:15'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-21 01:42:15'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-21 01:42:15'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-21 01:42:15'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-21 01:42:15'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-21 01:42:15'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-21 01:42:15'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-21 01:42:15'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-21 01:42:15'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-21 01:42:15'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-21 01:42:15'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-21 01:42:15'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-21 01:42:15'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-21 01:42:15'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-21 01:42:15'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-21 01:42:15'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-21 01:42:15'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-21 01:42:15'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-21 01:42:15'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-21 01:42:15'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-21 01:42:15'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-21 01:42:15'),
(36, 'Water', 'expenses', 35, 'DemicLab-Main', '2026-07-21 01:45:57');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_bank_statement`
--

CREATE TABLE `demiclab_bank_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `report_date` date NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `roi_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_change` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_bank_statement_locks`
--

CREATE TABLE `demiclab_bank_statement_locks` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL,
  `report_date` date NOT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_bank_statement_rows`
--

CREATE TABLE `demiclab_bank_statement_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_cashflow`
--

CREATE TABLE `demiclab_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_cashflow_balance`
--

CREATE TABLE `demiclab_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_cashflow_withdrawal_rows`
--

CREATE TABLE `demiclab_cashflow_withdrawal_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_categories`
--

CREATE TABLE `demiclab_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_categories`
--

INSERT INTO `demiclab_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(9, 'DemicLab-Main', 'CONSUMABLES', 0, '2026-07-14 09:09:40'),
(10, 'DemicLab-Main', 'ANME', 1, '2026-07-31 09:56:46');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_categories_meta`
--

CREATE TABLE `demiclab_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_categories_meta`
--

INSERT INTO `demiclab_categories_meta` (`store_name`, `seeded`) VALUES
('DemicLab-Main', 1);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_cogs`
--

CREATE TABLE `demiclab_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_cogs`
--

INSERT INTO `demiclab_cogs` (`id`, `cogs_date`, `cogs_year`, `cogs_month`, `store_name`, `beg`, `purc`, `end_inv`, `cos`, `mktg_cost`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-05-01', 2026, 5, 'DemicLab-Main', 490244.47, 301865.20, 503407.08, 288702.59, 0.00, 'DemicLab-Main', '2026-07-31 07:16:19', '2026-07-31 07:16:19'),
(2, '2026-06-01', 2026, 6, 'DemicLab-Main', 503407.08, 0.00, 0.00, 503407.08, 0.00, 'DemicLab-Main', '2026-07-31 07:17:35', '2026-07-31 07:17:35');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_dinein_rows`
--

CREATE TABLE `demiclab_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_dinein_rows`
--

INSERT INTO `demiclab_dinein_rows` (`id`, `store_name`, `report_date`, `cash`, `palawan_pay`, `card_swipe_qr`, `unpaid_credit_name`, `unpaid_credit_amount`, `discount`, `bank_transfer_cheque`, `cancelled_transactions`, `sort_order`) VALUES
(1, 'DemicLab-Main', '2026-07-27', 1.00, 1.00, 1.00, '', 1.00, 1.00, 1.00, 1.00, 0),
(2, 'DemicLab-Main', '2026-07-28', 1.00, 1.00, 1.00, '', 0.00, 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_disbursement`
--

CREATE TABLE `demiclab_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_disbursement`
--

INSERT INTO `demiclab_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-07-28', '659-654-597-000', 'BRTL FOOD CORP', 'VAT', 'QUEZON CITY METRO MANILA', '12', 'Salaries and Wages', 1223.00, 131.04, 1091.96, '', 'DemicLab-Main', '2026-07-28 09:37:27', '2026-07-28 09:37:27');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_expenses`
--

CREATE TABLE `demiclab_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_expenses`
--

INSERT INTO `demiclab_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-10', '0', '0', '0', 'afs', 'df', 'asfas', '0', 137.76, 14.76, 123.00, 0.00, 123.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 'DemicLab', '2026-04-10 02:13:12', '2026-04-10 02:30:07'),
(2, '2026-05-09', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 122.00, 555.00, 555.00, 1355.00, 'DemicLab', '2026-05-09 01:51:29', '2026-05-09 01:51:29');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_income_statement`
--

CREATE TABLE `demiclab_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'DemicLab',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `cogs_capital` decimal(14,2) NOT NULL DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cash_advances` decimal(14,2) NOT NULL DEFAULT 0.00,
  `management_fee` decimal(14,2) NOT NULL DEFAULT 0.00,
  `bio_aug` decimal(14,2) NOT NULL DEFAULT 0.00,
  `roi_incorporator` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amortization` decimal(14,2) NOT NULL DEFAULT 0.00,
  `receivables_hmo` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credited_hmo` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credited_depreciation` decimal(14,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_income_statement`
--

INSERT INTO `demiclab_income_statement` (`id`, `store_name`, `stmt_date`, `stmt_year`, `stmt_month`, `stmt_day`, `stmt_label`, `net_sales`, `sales_discount`, `cost_of_sales`, `cogs_capital`, `other_income_royalty`, `equipment_supplies`, `depreciation_expense`, `transportation_fuel`, `lpg`, `rent`, `water_electricity`, `drinking_water`, `pest_control_bio`, `common_area_charges`, `exhaust_cleaning`, `salaries`, `office_equipment_supplies`, `philhealth_sss`, `medical_supplies`, `agency_fees`, `bank_fees`, `staff_meal`, `representation_benefits`, `professional_fees`, `communication`, `freight_storage`, `repairs_maintenance`, `sponsorship_marketing`, `taxes_licenses`, `system_development`, `construction_progress`, `insurance`, `admin_shares`, `miscellaneous_expense`, `vat_payment`, `saved_by`, `created_at`, `updated_at`, `cash_advances`, `management_fee`, `bio_aug`, `roi_incorporator`, `amortization`, `receivables_hmo`, `credited_hmo`, `credited_depreciation`) VALUES
(1, 'DemicLab-Main', '2026-07-31', 2026, 7, 31, '', 3942138.00, 264180.40, 258946.70, 250267.30, 0.00, 17700.00, 53333.33, 18132.75, 0.00, 123488.00, 40126.47, 0.00, 0.00, 0.00, 0.00, 481436.86, 22111.25, 26215.00, 0.00, 484344.50, 0.00, 0.00, 79115.50, 0.00, 4552.23, 3908.40, 5810.00, 42000.00, 28715.50, 0.00, 0.00, 0.00, 0.00, 24417.00, 0.00, 'DemicLab-Main', '2026-07-31 07:13:23', '2026-07-31 07:13:23', 0.00, 30000.00, 400.00, 0.00, 309453.89, 2381662.00, 455152.50, 53333.33);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_acc_titles`
--

CREATE TABLE `demiclab_jaro_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_acc_titles`
--

INSERT INTO `demiclab_jaro_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-21 02:41:40'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-21 02:41:40'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-21 02:41:40'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-21 02:41:40'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-21 02:41:40'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-21 02:41:40'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-21 02:41:40'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-21 02:41:40'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-21 02:41:40'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-21 02:41:40'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-21 02:41:40'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-21 02:41:40'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-21 02:41:40'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-21 02:41:40'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-21 02:41:40'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-21 02:41:40'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-21 02:41:40'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-21 02:41:40'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-21 02:41:40'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-21 02:41:40'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-21 02:41:40'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-21 02:41:40'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-21 02:41:40'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-21 02:41:40'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-21 02:41:40'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-21 02:41:40'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-21 02:41:40'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-21 02:41:40'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-21 02:41:40'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-21 02:41:40'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-21 02:41:40'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-21 02:41:40'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-21 02:41:40'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-21 02:41:40'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-21 02:41:40'),
(36, 'Water', 'expenses', 35, 'DemicLab-Jaro', '2026-07-21 02:42:14');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_cashflow`
--

CREATE TABLE `demiclab_jaro_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_cashflow_balance`
--

CREATE TABLE `demiclab_jaro_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_categories`
--

CREATE TABLE `demiclab_jaro_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_categories`
--

INSERT INTO `demiclab_jaro_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(10, 'DemicLab-Jaro', 'CONSUMABLES', 0, '2026-07-15 01:30:24'),
(11, 'DemicLab-Jaro', 'MICROPARA', 1, '2026-07-15 01:37:26'),
(12, 'DemicLab-Jaro', 'HEMA', 2, '2026-07-15 01:38:52'),
(13, 'DemicLab-Jaro', 'CHEMISTRY', 3, '2026-07-15 01:46:22'),
(14, 'DemicLab-Jaro', 'SEROLOGY', 4, '2026-07-15 01:52:26'),
(15, 'DemicLab-Jaro', 'SEROLOGY - RAPID KITS', 5, '2026-07-15 01:52:39');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_categories_meta`
--

CREATE TABLE `demiclab_jaro_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_categories_meta`
--

INSERT INTO `demiclab_jaro_categories_meta` (`store_name`, `seeded`) VALUES
('DemicLab-Jaro', 1);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_cf_vat_selection`
--

CREATE TABLE `demiclab_jaro_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_cf_vat_selection`
--

INSERT INTO `demiclab_jaro_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'DemicLab-Jaro', 2026, 7, 0.00, 0, 'DemicLab-Jaro', '2026-07-13 02:56:38');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_cogs`
--

CREATE TABLE `demiclab_jaro_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_dinein_rows`
--

CREATE TABLE `demiclab_jaro_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_disbursement`
--

CREATE TABLE `demiclab_jaro_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_disbursement`
--

INSERT INTO `demiclab_jaro_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(2, '2026-07-21', '000-345-273-053', 'ROBINSON\'S INCORPORATED', 'VAT', 'LOPEZ ST., SAN VICENTE, ILOILO CITY', '', 'Water', 123.00, 13.18, 109.82, '', 'DemicLab-Jaro', '2026-07-21 02:48:03', '2026-07-21 02:48:03');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_expenses`
--

CREATE TABLE `demiclab_jaro_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) DEFAULT 0.00,
  `commission_fees` decimal(12,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) DEFAULT 0.00,
  `bank_fees` decimal(12,2) DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) DEFAULT 0.00,
  `ca` decimal(12,2) DEFAULT 0.00,
  `withdrawal` decimal(12,2) DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_income_statement`
--

CREATE TABLE `demiclab_jaro_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'DemicLab-Jaro',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_month_end_inv`
--

CREATE TABLE `demiclab_jaro_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `quantity` varchar(50) NOT NULL DEFAULT '',
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price_per_piece` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `start_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `price_per_piece_start` decimal(12,2) NOT NULL DEFAULT 0.00,
  `consumed` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `remarks` varchar(255) DEFAULT NULL,
  `additional` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_jaro_month_end_inv`
--

INSERT INTO `demiclab_jaro_month_end_inv` (`id`, `inv_date`, `inv_year`, `inv_month`, `store_name`, `category`, `sort_order`, `item_desc`, `unit`, `supplier_cost`, `end_inv_num`, `total_amount`, `saved_by`, `created_at`, `updated_at`, `quantity`, `unit_price`, `price_per_piece`, `start_inv_num`, `price_per_piece_start`, `consumed`, `remarks`, `additional`) VALUES
(2, '2026-07-31', 2026, 7, 'DemicLab-Jaro', 'CONSUMABLES', 0, '3CC SYRINGE', 'PCS', 0.00, 0.0000, 0.00, 'DemicLab-Jaro', '2026-07-15 01:30:58', '2026-07-15 01:30:58', '100pcs/box', 220.00, 2.2000, 500.0000, 1100.00, 500.0000, '', ''),
(3, '2026-07-31', 2026, 7, 'DemicLab-Jaro', 'MICROPARA', 0, '4 PARA REAGENT STRIP', 'BOX', 0.00, 4.0000, 1.68, 'DemicLab-Jaro', '2026-07-15 01:38:30', '2026-07-15 01:55:39', '100strips/box', 208.00, 0.4200, 4.0000, 1.68, 0.0000, '', ''),
(4, '2026-07-31', 2026, 7, 'DemicLab-Jaro', 'CHEMISTRY', 0, 'CALIBRATOR', 'BOTTLE', 0.00, 2.0000, 0.00, 'DemicLab-Jaro', '2026-07-15 01:47:08', '2026-07-15 01:47:08', '1 bottle', 250.00, 0.0000, 2.0000, 0.00, 0.0000, '', '');

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_pdc`
--

CREATE TABLE `demiclab_jaro_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_pl_revenue`
--

CREATE TABLE `demiclab_jaro_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_reconcile`
--

CREATE TABLE `demiclab_jaro_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_report_entries`
--

CREATE TABLE `demiclab_jaro_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'DemicLab-Jaro',
  `cash` decimal(12,2) DEFAULT 0.00,
  `hmo` decimal(12,2) DEFAULT 0.00,
  `charge_to_company` decimal(12,2) DEFAULT 0.00,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `disc_30` decimal(12,2) DEFAULT 0.00,
  `disc_scpwd` decimal(12,2) DEFAULT 0.00,
  `disc_15` decimal(12,2) DEFAULT 0.00,
  `disc_10` decimal(12,2) DEFAULT 0.00,
  `disc_5` decimal(12,2) DEFAULT 0.00,
  `total_discounts` decimal(12,2) DEFAULT 0.00,
  `pos_reading` decimal(12,2) DEFAULT 0.00,
  `gross_sales` decimal(12,2) DEFAULT 0.00,
  `total_after_discounts` decimal(12,2) DEFAULT 0.00,
  `late_payment` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_sales_detail_rows`
--

CREATE TABLE `demiclab_jaro_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_jaro_sales_report`
--

CREATE TABLE `demiclab_jaro_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Jaro',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_month_end_inv`
--

CREATE TABLE `demiclab_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_pdc`
--

CREATE TABLE `demiclab_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_pl_revenue`
--

CREATE TABLE `demiclab_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_reconcile`
--

CREATE TABLE `demiclab_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_report_entries`
--

CREATE TABLE `demiclab_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'DemicLab',
  `cash` decimal(12,2) DEFAULT 0.00,
  `hmo` decimal(12,2) DEFAULT 0.00,
  `charge_to_company` decimal(12,2) DEFAULT 0.00,
  `debit` decimal(12,2) DEFAULT 0.00,
  `credit` decimal(12,2) DEFAULT 0.00,
  `disc_30` decimal(12,2) DEFAULT 0.00,
  `disc_scpwd` decimal(12,2) DEFAULT 0.00,
  `disc_15` decimal(12,2) DEFAULT 0.00,
  `disc_10` decimal(12,2) DEFAULT 0.00,
  `disc_5` decimal(12,2) DEFAULT 0.00,
  `total_discounts` decimal(12,2) DEFAULT 0.00,
  `pos_reading` decimal(12,2) DEFAULT 0.00,
  `gross_sales` decimal(12,2) DEFAULT 0.00,
  `total_after_discounts` decimal(12,2) DEFAULT 0.00,
  `late_payment` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `short_over` decimal(12,2) DEFAULT 0.00,
  `tips` decimal(12,2) DEFAULT 0.00,
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00,
  `cancelled_transaction` decimal(12,2) DEFAULT 0.00,
  `unpaid_staff` decimal(12,2) DEFAULT 0.00,
  `unpaid_mam` decimal(12,2) DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00,
  `advance_payment` decimal(12,2) DEFAULT 0.00,
  `grab` decimal(12,2) DEFAULT 0.00,
  `gcash` decimal(12,2) DEFAULT 0.00,
  `gc_sold` decimal(12,2) DEFAULT 0.00,
  `gc_sponsorship` decimal(12,2) DEFAULT 0.00,
  `bank_transfer` decimal(12,2) DEFAULT 0.00,
  `discounted` decimal(12,2) DEFAULT 0.00,
  `personal` decimal(12,2) DEFAULT 0.00,
  `cash_advance` decimal(12,2) DEFAULT 0.00,
  `payroll` decimal(12,2) DEFAULT 0.00,
  `commi_fund` decimal(12,2) DEFAULT 0.00,
  `service_charge_pos` decimal(12,2) DEFAULT 0.00,
  `cancelled_sc` decimal(12,2) DEFAULT 0.00,
  `service_charge_depo` decimal(12,2) DEFAULT 0.00,
  `pcf` decimal(12,2) DEFAULT 0.00,
  `other_expenses` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `total_swipe` decimal(12,2) DEFAULT 0.00,
  `other_deposits` decimal(12,2) DEFAULT 0.00,
  `lechonan_sales` decimal(12,2) DEFAULT 0.00,
  `discount_30` decimal(12,2) DEFAULT 0.00,
  `discount_scpwd_20` decimal(12,2) DEFAULT 0.00,
  `discount_15` decimal(12,2) DEFAULT 0.00,
  `discount_10` decimal(12,2) DEFAULT 0.00,
  `discount_5` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_report_entries`
--

INSERT INTO `demiclab_report_entries` (`id`, `report_date`, `store_name`, `cash`, `hmo`, `charge_to_company`, `debit`, `credit`, `disc_30`, `disc_scpwd`, `disc_15`, `disc_10`, `disc_5`, `total_discounts`, `pos_reading`, `gross_sales`, `total_after_discounts`, `late_payment`, `remarks`, `saved_by`, `created_at`, `updated_at`, `short_over`, `tips`, `sales_of_day_swipe`, `cancelled_transaction`, `unpaid_staff`, `unpaid_mam`, `marketing_pull_out`, `advance_payment`, `grab`, `gcash`, `gc_sold`, `gc_sponsorship`, `bank_transfer`, `discounted`, `personal`, `cash_advance`, `payroll`, `commi_fund`, `service_charge_pos`, `cancelled_sc`, `service_charge_depo`, `pcf`, `other_expenses`, `total_deductions`, `total_swipe`, `other_deposits`, `lechonan_sales`, `discount_30`, `discount_scpwd_20`, `discount_15`, `discount_10`, `discount_5`) VALUES
(1, '2026-04-01', 'DemicLab', 8020.50, 62225.00, 123.00, 123.00, 1590.00, 0.00, 870.00, 0.00, 530.00, 0.00, 227.00, 0.00, 72308.50, 1173.00, 60.57, '', 'DemicLab', '2026-04-05 15:10:31', '2026-04-05 15:23:20', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(6, '2026-07-01', 'DemicLab-Main', 8020.50, 62225.00, 123.00, 123.00, 1590.00, 0.00, 0.00, 0.00, 0.00, 0.00, 227.00, 72081.50, 72308.50, 1173.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:09', '2026-07-30 06:58:23', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 870.00, 0.00, 530.00, 0.00),
(8, '2026-07-02', 'DemicLab-Main', 53653.50, 141520.00, 1100.00, 6260.00, 5150.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2730.50, 207683.50, 210414.00, 12229.50, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:11', '2026-07-31 05:55:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 12345.00, 0.00, 2615.00, 0.00),
(9, '2026-07-03', 'DemicLab-Main', 42432.50, 134790.00, 2000.00, 2235.00, 7670.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3945.50, 189127.50, 193073.00, 17479.50, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:11', '2026-07-31 05:56:52', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 18030.00, 0.00, 3395.00, 0.00),
(10, '2026-07-04', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:11', '2026-07-27 06:06:11', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(11, '2026-07-05', 'DemicLab-Main', 3466341.50, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3466341.50, 3466341.50, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-31 06:52:00', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(12, '2026-07-06', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(13, '2026-07-07', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(14, '2026-07-08', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(15, '2026-07-09', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(16, '2026-07-10', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(17, '2026-07-11', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(18, '2026-07-12', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(19, '2026-07-13', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(20, '2026-07-14', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:12', '2026-07-27 06:06:12', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(21, '2026-07-15', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(22, '2026-07-16', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(23, '2026-07-17', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(24, '2026-07-18', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(25, '2026-07-19', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(26, '2026-07-20', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(27, '2026-07-21', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(28, '2026-07-22', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(29, '2026-07-23', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(30, '2026-07-24', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:13', '2026-07-27 06:06:13', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(31, '2026-07-25', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(32, '2026-07-26', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(33, '2026-07-27', 'DemicLab-Main', 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 1.00, 0.00, 1.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-30 06:58:55', 18.00, 0.00, 0.00, 1.00, 1.00, 0.00, 1.00, 0.00, 1.00, 1.00, 0.00, 0.00, 1.00, 1.00, 0.00, 0.00, 0.00, 0.00, 1.00, 0.00, 1.00, 1.00, 0.00, 7.00, 2.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(34, '2026-07-28', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(35, '2026-07-29', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(36, '2026-07-30', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(37, '2026-07-31', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'DemicLab-Main', '2026-07-27 06:06:14', '2026-07-27 06:06:14', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(107, '2026-08-01', 'DemicLab-Main', 100.00, 100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 55.00, 300.00, 355.00, 245.00, 0.00, '', 'DemicLab-Main', '2026-08-04 01:51:36', '2026-08-04 01:51:36', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 100.00, 0.00, 0.00, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_sales_detail_rows`
--

CREATE TABLE `demiclab_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_sales_detail_rows`
--

INSERT INTO `demiclab_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(1, 'DemicLab-Main', '2026-07-27', 'marketing_pullout', '', 1.00, 0),
(2, 'DemicLab-Main', '2026-07-27', 'grab', '', 1.00, 0),
(3, 'DemicLab-Main', '2026-07-27', 'expenses', '', 1.00, 0),
(4, 'DemicLab-Main', '2026-07-27', 'late_payment', '', 1.00, 0),
(5, 'DemicLab-Main', '2026-07-27', 'advance_payment', '', 1.00, 0),
(6, 'DemicLab-Main', '2026-07-27', 'gc_sponsorship', '', 1.00, 0),
(7, 'DemicLab-Main', '2026-07-27', 'gc_sold', '', 1.00, 0),
(8, 'DemicLab-Main', '2026-07-28', 'marketing_pullout', '', 0.00, 0),
(9, 'DemicLab-Main', '2026-07-28', 'grab', '', 0.00, 0),
(10, 'DemicLab-Main', '2026-07-28', 'expenses', '', 0.00, 0),
(11, 'DemicLab-Main', '2026-07-28', 'late_payment', '', 0.00, 0),
(12, 'DemicLab-Main', '2026-07-28', 'advance_payment', '', 0.00, 0),
(13, 'DemicLab-Main', '2026-07-28', 'gc_sponsorship', '', 0.00, 0),
(14, 'DemicLab-Main', '2026-07-28', 'gc_sold', '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `demiclab_sales_report`
--

CREATE TABLE `demiclab_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'DemicLab-Main',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demiclab_sales_report`
--

INSERT INTO `demiclab_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'DemicLab-Main', '2026-07-27', 0.00, 1.00, 1.00, 2.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, 1.00, 0.00, 11.00, -7.00, 18.00, 'DemicLab-Main', '2026-07-27 05:55:23', '2026-07-27 05:55:23'),
(2, 'DemicLab-Main', '2026-07-28', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, -1.00, 2.00, 'DemicLab-Main', '2026-07-28 09:40:17', '2026-07-28 09:40:17');

-- --------------------------------------------------------

--
-- Table structure for table `demic_balance_sheet`
--

CREATE TABLE `demic_balance_sheet` (
  `id` int(10) UNSIGNED NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `stmt_date` date NOT NULL,
  `stmt_year` smallint(6) NOT NULL,
  `stmt_month` tinyint(4) NOT NULL,
  `stmt_day` tinyint(4) NOT NULL,
  `operation_bank_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cash_for_depo` decimal(14,2) NOT NULL DEFAULT 0.00,
  `revolving_fund` decimal(14,2) NOT NULL DEFAULT 0.00,
  `hmo_ar` decimal(14,2) NOT NULL DEFAULT 0.00,
  `staff_cash_advances` decimal(14,2) NOT NULL DEFAULT 0.00,
  `ending_inventory` decimal(14,2) NOT NULL DEFAULT 0.00,
  `for_releasing_payment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `payables` decimal(14,2) NOT NULL DEFAULT 0.00,
  `depreciation` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `demic_balance_sheet`
--

INSERT INTO `demic_balance_sheet` (`id`, `store_name`, `display_name`, `stmt_date`, `stmt_year`, `stmt_month`, `stmt_day`, `operation_bank_balance`, `cash_for_depo`, `revolving_fund`, `hmo_ar`, `staff_cash_advances`, `ending_inventory`, `for_releasing_payment`, `payables`, `depreciation`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'branch_ms8ngvg70pkz7w', 'DEMICLAB-MAIN', '2026-07-31', 2026, 7, 31, 3324580.74, 0.00, 15000.00, 1442018.00, 0.00, 262297.55, 0.00, 274439.00, 53333.33, 'DemicLab-Main', '2026-07-31 08:00:53', '2026-07-31 08:07:07'),
(2, 'DemicLab-Main', 'DemicLab-Main', '2026-07-31', 2026, 7, 31, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 08:00:55', '2026-07-31 08:00:55'),
(4, 'branch_ms8ny8s1ygps77', 'New Branch 1', '2026-07-31', 2026, 7, 31, 1954648.99, 0.00, 15000.00, 1089417.00, 0.00, 307414.11, 0.00, 269856.00, 53333.33, 'DemicLab-Main', '2026-07-31 08:14:34', '2026-07-31 08:14:34');

-- --------------------------------------------------------

--
-- Table structure for table `demic_daily_cashflow`
--

CREATE TABLE `demic_daily_cashflow` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Demic Lab',
  `hmo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charge_to_company` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dr_cr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `labs` decimal(12,2) NOT NULL DEFAULT 0.00,
  `xray_ecg_utz_2decho` decimal(12,2) NOT NULL DEFAULT 0.00,
  `packages` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sc_pwd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payables_consumables` decimal(12,2) NOT NULL DEFAULT 0.00,
  `consumables` decimal(12,2) NOT NULL DEFAULT 0.00,
  `janitorial` decimal(12,2) NOT NULL DEFAULT 0.00,
  `office` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_daily_fixed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_holiday` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_reliever` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_xray` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_clinic_doctor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_ob_doctor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_ecg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_2d_echo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_ultrasound` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_ob_ultrasound` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_ortho` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_cardio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_patho_it` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_im` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_pedia` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_daily_cashflow`
--

INSERT INTO `demic_daily_cashflow` (`id`, `entry_date`, `store_name`, `hmo`, `charge_to_company`, `dr_cr`, `labs`, `xray_ecg_utz_2decho`, `packages`, `sc_pwd`, `payables_consumables`, `consumables`, `janitorial`, `office`, `frontline_daily_fixed`, `frontline_holiday`, `frontline_reliever`, `pf_xray`, `pf_clinic_doctor`, `pf_ob_doctor`, `pf_ecg`, `pf_2d_echo`, `pf_ultrasound`, `pf_ob_ultrasound`, `pf_ortho`, `pf_cardio`, `pf_patho_it`, `pf_im`, `pf_pedia`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(2, '2026-07-02', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(3, '2026-07-03', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(4, '2026-07-04', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(5, '2026-07-05', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(6, '2026-07-06', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(7, '2026-07-07', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(8, '2026-07-08', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(9, '2026-07-09', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(10, '2026-07-10', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(11, '2026-07-11', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(12, '2026-07-12', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(13, '2026-07-13', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(14, '2026-07-14', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(15, '2026-07-15', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(16, '2026-07-16', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(17, '2026-07-17', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(18, '2026-07-18', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(19, '2026-07-19', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(20, '2026-07-20', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(21, '2026-07-21', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(22, '2026-07-22', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:34'),
(23, '2026-07-23', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(24, '2026-07-24', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(25, '2026-07-25', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(26, '2026-07-26', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(27, '2026-07-27', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(28, '2026-07-28', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(29, '2026-07-29', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(30, '2026-07-30', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(31, '2026-07-31', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-07-31 05:19:35'),
(32, '2026-08-01', 'DemicLab-Main', 30781.00, 23150.00, 550.00, 19899.00, 2630.00, 1600.00, 6652.00, 16292.97, 0.00, 0.00, 0.00, 14290.01, 0.00, 0.00, 0.00, 9350.00, 0.00, 270.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1346.15, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(33, '2026-08-02', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(34, '2026-08-03', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(35, '2026-08-04', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(36, '2026-08-05', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(37, '2026-08-06', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(38, '2026-08-07', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(39, '2026-08-08', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(40, '2026-08-09', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:27'),
(41, '2026-08-10', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(42, '2026-08-11', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(43, '2026-08-12', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(44, '2026-08-13', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(45, '2026-08-14', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(46, '2026-08-15', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(47, '2026-08-16', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(48, '2026-08-17', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(49, '2026-08-18', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:28'),
(50, '2026-08-19', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(51, '2026-08-20', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(52, '2026-08-21', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(53, '2026-08-22', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(54, '2026-08-23', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(55, '2026-08-24', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(56, '2026-08-25', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(57, '2026-08-26', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(58, '2026-08-27', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(59, '2026-08-28', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(60, '2026-08-29', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(61, '2026-08-30', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29'),
(62, '2026-08-31', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'DemicLab-Main', '2026-08-04 03:32:29');

-- --------------------------------------------------------

--
-- Table structure for table `demic_daily_reports`
--

CREATE TABLE `demic_daily_reports` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Demic Lab',
  `sales_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sales_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_medical_staff` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hmo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charge_to_company` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dr_cr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hmo_withholding_pct` decimal(5,2) NOT NULL DEFAULT 2.00,
  `quota_target` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payables_threshold` decimal(5,2) NOT NULL DEFAULT 0.00,
  `mp_threshold` decimal(5,2) NOT NULL DEFAULT 0.00,
  `payables_expenses` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hmo_approved` decimal(10,2) NOT NULL DEFAULT 0.00,
  `patients_served` decimal(10,2) NOT NULL DEFAULT 0.00,
  `test_xray` decimal(10,2) NOT NULL DEFAULT 0.00,
  `test_laboratory` decimal(10,2) NOT NULL DEFAULT 0.00,
  `test_consultation` decimal(10,2) NOT NULL DEFAULT 0.00,
  `test_ultrasound` decimal(10,2) NOT NULL DEFAULT 0.00,
  `test_2d_echo` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_daily_reports`
--

INSERT INTO `demic_daily_reports` (`id`, `report_date`, `store_name`, `sales_revenue`, `sales_discount`, `frontline_medical_staff`, `pf_fee`, `cash`, `hmo`, `charge_to_company`, `dr_cr`, `hmo_withholding_pct`, `quota_target`, `payables_threshold`, `mp_threshold`, `payables_expenses`, `created_by`, `updated_at`, `hmo_approved`, `patients_served`, `test_xray`, `test_laboratory`, `test_consultation`, `test_ultrasound`, `test_2d_echo`) VALUES
(10, '2026-07-31', 'DemicLab-Main', 207683.50, 2750.00, 20696.39, 22011.15, 53653.50, 141520.00, 1100.00, 11410.00, 2.00, 50000.00, 45.00, 10.00, '[{\"label\":\"Payable 1\",\"amount\":16292.97}]', 'DemicLab-Main', '2026-07-31 04:25:28', 60.00, 82.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(11, '2026-07-01', 'DemicLab-Main', 129482.00, 1539.00, 20146.39, 22611.75, 62976.00, 49929.00, 1452.00, 15125.00, 2.00, 50000.00, 45.00, 10.00, '[{\"label\":\"Payable 1\",\"amount\":16292.97}]', 'DemicLab-Main', '2026-07-31 04:23:37', 33.00, 115.00, 0.00, 0.00, 0.00, 0.00, 0.00),
(13, '2026-07-03', 'DemicLab-Main', 189127.50, 3945.50, 20346.39, 13311.15, 42432.50, 134790.00, 2000.00, 9905.00, 2.00, 50000.00, 45.00, 10.00, '[{\"label\":\"Payable 1\",\"amount\":16292.97}]', 'DemicLab-Main', '2026-07-31 04:33:42', 74.00, 60.00, 0.00, 0.00, 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `demic_discounts`
--

CREATE TABLE `demic_discounts` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Demic Lab',
  `test_name` varchar(200) NOT NULL DEFAULT '',
  `disc_pct` decimal(5,2) NOT NULL DEFAULT 0.00,
  `txn` int(11) NOT NULL DEFAULT 0,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_discounts`
--

INSERT INTO `demic_discounts` (`id`, `entry_date`, `store_name`, `test_name`, `disc_pct`, `txn`, `price`, `amount`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-14', 'DemicLab-Main', 'CBC ONLY', 15.00, 10, 280.00, 420.00, 0, 'DemicLab-Main', '2026-07-14 08:41:32');

-- --------------------------------------------------------

--
-- Table structure for table `demic_purchases`
--

CREATE TABLE `demic_purchases` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `supplier_name` varchar(255) DEFAULT '',
  `description` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `or_cr_no` varchar(100) DEFAULT '',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `materials_purchases` decimal(12,2) DEFAULT 0.00,
  `amortization_expense` decimal(12,2) DEFAULT 0.00,
  `wtax_rent` decimal(12,2) DEFAULT 0.00,
  `service_fee` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `employees_benefit` decimal(12,2) DEFAULT 0.00,
  `advertisement` decimal(12,2) DEFAULT 0.00,
  `bio` decimal(12,2) DEFAULT 0.00,
  `janitorial_cleaning` decimal(12,2) DEFAULT 0.00,
  `freight_charge` decimal(12,2) DEFAULT 0.00,
  `fare_travel` decimal(12,2) DEFAULT 0.00,
  `fuel_oil` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `electricity` decimal(12,2) DEFAULT 0.00,
  `representation_expense` decimal(12,2) DEFAULT 0.00,
  `salary` decimal(12,2) DEFAULT 0.00,
  `professional_fee` decimal(12,2) DEFAULT 0.00,
  `management_fee` decimal(12,2) DEFAULT 0.00,
  `cash_advances` decimal(12,2) DEFAULT 0.00,
  `misc` decimal(12,2) DEFAULT 0.00,
  `other_equipment` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_purchases`
--

INSERT INTO `demic_purchases` (`id`, `expense_date`, `supplier_name`, `description`, `address`, `or_cr_no`, `total_amount`, `materials_purchases`, `amortization_expense`, `wtax_rent`, `service_fee`, `rent`, `office_supplies`, `employees_benefit`, `advertisement`, `bio`, `janitorial_cleaning`, `freight_charge`, `fare_travel`, `fuel_oil`, `repairs_maintenance`, `communication`, `electricity`, `representation_expense`, `salary`, `professional_fee`, `management_fee`, `cash_advances`, `misc`, `other_equipment`, `saved_by`, `created_at`, `updated_at`) VALUES
(10, '2026-08-03', 'DAYNETO SEAFOOD GRILL & RESTAURANT', '123', 'JARO, ILOILO CITY', '0', 2300.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 'DemicLab-Main', '2026-08-03 04:16:59', '2026-08-03 04:16:59'),
(11, '2026-06-17', 'ROBINSONS INCORPORATED', '0', 'LEDESMA ST., ILOILO CITY', '0', 2640.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 20.00, 2200.00, 20.00, 20.00, 20.00, 20.00, 'DemicLab-Main', '2026-08-03 04:19:18', '2026-08-03 04:19:18'),
(12, '2026-07-16', 'CEBU OVEN MAGIC CORPORATION', '0', 'MAYON STREET STA TERESITA QUEZON CITY', '0', 230.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 'DemicLab-Main', '2026-08-03 04:19:20', '2026-08-03 04:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `demic_sales_distribution`
--

CREATE TABLE `demic_sales_distribution` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Demic Lab',
  `old_client` decimal(12,2) NOT NULL DEFAULT 0.00,
  `walk_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `company_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charge_to_company` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hmo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `home_service` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demic_sales_report_summary`
--

CREATE TABLE `demic_sales_report_summary` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Demic Lab',
  `sales_revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hmo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `charge_to_company` decimal(12,2) NOT NULL DEFAULT 0.00,
  `dr_cr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payables` decimal(12,2) NOT NULL DEFAULT 0.00,
  `frontline_staff` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hmo_ewt` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sc_pwd` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_sales_report_summary`
--

INSERT INTO `demic_sales_report_summary` (`id`, `entry_date`, `store_name`, `sales_revenue`, `cash`, `hmo`, `charge_to_company`, `dr_cr`, `short_over`, `payables`, `frontline_staff`, `pf_fee`, `hmo_ewt`, `sc_pwd`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-31', 'DemicLab-Main', 185003.50, 48133.50, 133660.00, 0.00, 3210.00, 0.00, 16292.97, 23792.54, 14281.15, 2673.20, 4262.50, 0, 'DemicLab-Main', '2026-07-31 04:21:52'),
(2, '2026-07-01', 'DemicLab-Main', 129482.00, 62976.00, 49929.00, 1452.00, 15125.00, 0.00, 16292.97, 20146.39, 22611.75, 998.58, 1539.00, 0, 'DemicLab-Main', '2026-07-31 04:32:22'),
(33, '2026-07-03', 'DemicLab-Main', 189127.50, 42432.50, 134790.00, 2000.00, 9905.00, 0.00, 16292.97, 20346.39, 13311.15, 2695.80, 3945.50, 0, 'DemicLab-Main', '2026-07-31 04:36:28');

-- --------------------------------------------------------

--
-- Table structure for table `demic_tests`
--

CREATE TABLE `demic_tests` (
  `id` int(11) NOT NULL,
  `test_name` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `demic_tests`
--

INSERT INTO `demic_tests` (`id`, `test_name`, `created_at`) VALUES
(1, 'CBC ONLY', '2026-07-31 03:14:28'),
(2, 'CBC W/ PLATELET COUNT', '2026-07-31 03:14:28'),
(3, 'CBC PLATELET W/ SAVE SLIDE', '2026-07-31 03:14:28'),
(4, 'ESR', '2026-07-31 03:14:28'),
(5, 'BLOOD TYPING (ABO & RH TYPING)', '2026-07-31 03:14:28'),
(6, 'PROTIME (INR)', '2026-07-31 03:14:28'),
(7, 'APTT', '2026-07-31 03:14:28'),
(8, 'CTBT', '2026-07-31 03:14:28'),
(9, 'CLOTTING TIME', '2026-07-31 03:14:28'),
(10, 'BLEEDING TIME', '2026-07-31 03:14:28'),
(11, 'CRP (QUALI)', '2026-07-31 03:14:28'),
(12, 'CRP (QUANTI)', '2026-07-31 03:14:28'),
(13, 'CA-1-99', '2026-07-31 03:14:28'),
(14, 'PERIPHERAL BLOOD SMEAR', '2026-07-31 03:14:28'),
(15, 'RHEUMATOID FACTOR', '2026-07-31 03:14:28'),
(16, 'URINALYSIS 4-PARAMETER', '2026-07-31 03:14:28'),
(17, 'URINALYSIS 11-PARAMETER', '2026-07-31 03:14:28'),
(18, 'FECALYSIS', '2026-07-31 03:14:28'),
(19, 'FECLYSIS (CONCENTRATION)', '2026-07-31 03:14:28'),
(20, 'ANTIGEN H-PYLORI', '2026-07-31 03:14:28'),
(21, 'FOBT (FECAL OCCULT BLOOD TEST)', '2026-07-31 03:14:28'),
(22, 'FBS (FASTING BLOOD SUGAR)', '2026-07-31 03:14:28'),
(23, 'RBS (RANDOM BLOOD SUGAR)', '2026-07-31 03:14:28'),
(24, 'OGTT w/ GLUCOSE LOAD', '2026-07-31 03:14:28'),
(25, 'HPBG', '2026-07-31 03:14:28'),
(26, 'HbA1C', '2026-07-31 03:14:28'),
(27, 'LIPID PROFILE', '2026-07-31 03:14:28'),
(28, 'CHOLESTEROL ONLY', '2026-07-31 03:14:28'),
(29, 'TRIGLYCERIDES ONLY', '2026-07-31 03:14:28'),
(30, 'HDL ONLY', '2026-07-31 03:14:28'),
(31, 'CREATININE', '2026-07-31 03:14:28'),
(32, 'CRETININE WITH EGFR', '2026-07-31 03:14:28'),
(33, 'BLOOD URIC ACID (BUA)', '2026-07-31 03:14:28'),
(34, 'BUN (BLOOD UREA NITROGEN)', '2026-07-31 03:14:28'),
(35, 'SGPT', '2026-07-31 03:14:28'),
(36, 'SGOT', '2026-07-31 03:14:28'),
(37, 'SODIUM', '2026-07-31 03:14:28'),
(38, 'POTASSIUM', '2026-07-31 03:14:28'),
(39, 'TOTAL CALCIUM', '2026-07-31 03:14:28'),
(40, 'IONIZED CALCIUM', '2026-07-31 03:14:28'),
(41, 'CHLORIDE', '2026-07-31 03:14:28'),
(42, 'MAGNESIUM', '2026-07-31 03:14:28'),
(43, 'PHOSPHORUS', '2026-07-31 03:14:28'),
(44, 'ALP (ALKALINE PHOSPHATASE)', '2026-07-31 03:14:28'),
(45, 'AMYLASE', '2026-07-31 03:14:28'),
(46, 'PSA (TOTAL)', '2026-07-31 03:14:28'),
(47, 'PSA (FREE)', '2026-07-31 03:14:28'),
(48, 'GGT', '2026-07-31 03:14:28'),
(49, 'RERRITIN TEST', '2026-07-31 03:14:28'),
(50, 'CEA (CARCINOEMBRYONIC Ag)', '2026-07-31 03:14:28'),
(51, 'AFP (ALPHA- FETOPROTEIN)', '2026-07-31 03:14:28'),
(52, 'TPAG (TOTAL PROTEIN, ALBUMIN/GLOBULIN)', '2026-07-31 03:14:28'),
(53, 'BILIRUBIN TOTAL', '2026-07-31 03:14:28'),
(54, 'TOTAL PROTEIN', '2026-07-31 03:14:28'),
(55, 'ALBUMIN', '2026-07-31 03:14:28'),
(56, 'TSH TEST', '2026-07-31 03:14:28'),
(57, 'T3 TEST', '2026-07-31 03:14:28'),
(58, 'T4 TEST', '2026-07-31 03:14:28'),
(59, 'FT3 TEST', '2026-07-31 03:14:28'),
(60, 'FT4 TEST', '2026-07-31 03:14:28'),
(61, 'DENGUE NS1 Ag ONLY', '2026-07-31 03:14:28'),
(62, 'DENGUE IgM/IgG', '2026-07-31 03:14:28'),
(63, 'Anti HAV IgG/IgM (QUALITATIVE) Hepa A', '2026-07-31 03:14:28'),
(64, 'HBsAg (hepa B)', '2026-07-31 03:14:28'),
(65, 'HBsAg (hepa B) QUANTITATIVE', '2026-07-31 03:14:28'),
(66, 'HEPATITIS B CORE IGM', '2026-07-31 03:14:28'),
(67, 'ANTI HBC TOTAL', '2026-07-31 03:14:28'),
(68, 'Anti HBs', '2026-07-31 03:14:28'),
(69, 'ANTI HVC', '2026-07-31 03:14:28'),
(70, 'ANTI HBs QUANTITATIVE', '2026-07-31 03:14:28'),
(71, 'ANTI HBs QUALITATIVE', '2026-07-31 03:14:28'),
(72, 'H. PYLORI TEST (QUALITATIVE)', '2026-07-31 03:14:28'),
(73, 'GRAM STAIN', '2026-07-31 03:14:28'),
(74, 'VIT. D3 ASSAY', '2026-07-31 03:14:28'),
(75, 'PREGNANCY TEST (hCg)', '2026-07-31 03:14:28'),
(76, 'SYPHILIS TEST (ANTI-TP)', '2026-07-31 03:14:28'),
(77, 'SERUM PROLACTIN', '2026-07-31 03:14:28'),
(78, 'SERUM CORTISOL', '2026-07-31 03:14:28'),
(79, 'RUBELLA IGG', '2026-07-31 03:14:28'),
(80, 'ASO TITER (ANTISTREPTOLYSIN 0)', '2026-07-31 03:14:28'),
(81, 'SALMONELLA IgG/IgM (TYPHIDOT)', '2026-07-31 03:14:28'),
(82, 'INTACT PTH', '2026-07-31 03:14:28'),
(83, 'DRUG TEST', '2026-07-31 03:14:28'),
(84, 'CHEST PA/AP', '2026-07-31 03:14:28'),
(85, 'CHEST PA/AP (PEDIA)', '2026-07-31 03:14:28'),
(86, 'CHEST LATERAL R/L -ADULT', '2026-07-31 03:14:28'),
(87, 'CHEST PAL/APL RANDL PEDIA', '2026-07-31 03:14:28'),
(88, 'CHEST PAL/APL (ADULT)', '2026-07-31 03:14:28'),
(89, 'CHEST PAL/APL (PEDIA)', '2026-07-31 03:14:28'),
(90, 'CALCANEUS LATERAL R/L', '2026-07-31 03:14:28'),
(91, 'CHEST BUCKY APL R/L', '2026-07-31 03:14:28'),
(92, 'CHEST OBLIQUE', '2026-07-31 03:14:28'),
(93, 'APICOLORDOTIC VIEW', '2026-07-31 03:14:28'),
(94, 'LUMBOSACRAL APL R/L', '2026-07-31 03:14:28'),
(95, 'LUMBOSACRAL AP/PA', '2026-07-31 03:14:28'),
(96, 'THORACOLUMBAR APL R/L', '2026-07-31 03:14:28'),
(97, 'THORACOLUMBOSACRAL SPINE APL UPRIGHT', '2026-07-31 03:14:28'),
(98, 'CERVICOTHORASIC APL R/L', '2026-07-31 03:14:28'),
(99, 'THORACIC APL R/L', '2026-07-31 03:14:28'),
(100, 'HIP/PELVIS AP', '2026-07-31 03:14:28'),
(101, 'WRIST APL R&L', '2026-07-31 03:14:28'),
(102, 'FEMUR', '2026-07-31 03:14:28'),
(103, 'SHOULDER APL R/L', '2026-07-31 03:14:28'),
(104, 'HAND APO R/L', '2026-07-31 03:14:28'),
(105, 'HAND APL R/L', '2026-07-31 03:14:28'),
(106, 'FINGER AND THUMB APL R/L', '2026-07-31 03:14:28'),
(107, 'HIP/PELVIS APO', '2026-07-31 03:14:28'),
(108, 'ELBOW APL RAND L', '2026-07-31 03:14:28'),
(109, 'CERVICAL OBLIQUE R/L', '2026-07-31 03:14:28'),
(110, 'ANKLE APL R/L', '2026-07-31 03:14:28'),
(111, 'ANKLE AP R/L', '2026-07-31 03:14:28'),
(112, 'FOOT APO R/L', '2026-07-31 03:14:28'),
(113, 'FOOT OBLIQUE R/L', '2026-07-31 03:14:28'),
(114, 'KNEE APL R/L', '2026-07-31 03:14:28'),
(115, 'KNEE APL R & L', '2026-07-31 03:14:28'),
(116, 'KNEE AP RAND L', '2026-07-31 03:14:28'),
(117, 'LEG APL R/L', '2026-07-31 03:14:28'),
(118, 'FEMUR/THIGH APL R/L', '2026-07-31 03:14:28'),
(119, 'NASAL DONE SOFT TISSUE', '2026-07-31 03:14:28'),
(120, 'PARANASAL SINUSES (CALDWELLS,Lateral)', '2026-07-31 03:14:28'),
(121, 'PARANASAL SINUS WATER VIEWS', '2026-07-31 03:14:28'),
(122, 'PARANASAL SINUS (WATER,CALDWELLS,LATERAL)', '2026-07-31 03:14:28'),
(123, 'ECG WITH READING', '2026-07-31 03:14:28'),
(124, 'ECG WITH OUT READING', '2026-07-31 03:14:28'),
(125, '2D ECHO HMO', '2026-07-31 03:14:28'),
(126, '2D ECHO', '2026-07-31 03:14:28'),
(127, 'WHOLE ABD UTZ', '2026-07-31 03:14:28'),
(128, 'WHOLE ABD HMO', '2026-07-31 03:14:28'),
(129, 'UPPER ABDOMEN', '2026-07-31 03:14:28'),
(130, 'LOWER ABDOMEN', '2026-07-31 03:14:28'),
(131, 'HBT UTZ', '2026-07-31 03:14:28'),
(132, 'KUB UTZ', '2026-07-31 03:14:28'),
(133, 'LIVER UTZ', '2026-07-31 03:14:28'),
(134, 'KUBP UTZ', '2026-07-31 03:14:28'),
(135, 'UTZ PELVIS TRANSABDOMINAL', '2026-07-31 03:14:28'),
(136, 'UTZ FETAL EVALUATION', '2026-07-31 03:14:28'),
(137, 'BREAST ULTRASOUND', '2026-07-31 03:14:28'),
(138, 'THYROID ULTRASOUND', '2026-07-31 03:14:28'),
(139, 'SPECIMEN CUP', '2026-07-31 03:14:28'),
(140, 'X-RAY FILM (PHOTOPAPER)', '2026-07-31 03:14:28'),
(141, 'CONSULTATION FEE', '2026-07-31 03:14:28'),
(142, 'INCIDENTAL FINGS', '2026-07-31 03:14:28'),
(143, 'COMPANY CONSULTATION', '2026-07-31 03:14:28'),
(144, 'CONSULTATION FEE HMO', '2026-07-31 03:14:28'),
(145, 'FF UP W/ CERTIFICATE', '2026-07-31 03:14:28'),
(146, 'MEDICAL CERT/FIT TO WORK', '2026-07-31 03:14:28'),
(147, 'HOME SERVICE MABUHAII MANDURRIAO', '2026-07-31 03:14:28'),
(148, 'HOME SERVICE FEE', '2026-07-31 03:14:28'),
(149, 'HOME SERVICE FEE (OUT OF TOWN)', '2026-07-31 03:14:28'),
(150, 'HEALTH CARD PACKAGE', '2026-07-31 03:14:28'),
(151, 'HEALTH CARD W/ DT', '2026-07-31 03:14:28'),
(152, 'HOME SERVICE WITH CONSULTATION', '2026-07-31 03:14:28'),
(153, 'COMPANY HEALTH CARD PACKAGE (hmo)', '2026-07-31 03:14:28'),
(154, 'APE PACKAGE (BASIC 5)', '2026-07-31 03:14:28'),
(155, 'SEATTLE\'S BEST PACKAGE', '2026-07-31 03:14:28'),
(156, 'EXECUTIVE PACKAGE', '2026-07-31 03:14:28'),
(157, 'EXECUTIVE PACKAGE 1', '2026-07-31 03:14:28'),
(158, 'BASIC CHEM 1', '2026-07-31 03:14:28'),
(159, 'BASIC CHEM 2 PACKAGE', '2026-07-31 03:14:28'),
(160, 'BASIC 5 PACKEGE + HEPA SCREENING', '2026-07-31 03:14:28'),
(161, 'BASIC 5 PACKAGE + DRUG TEST', '2026-07-31 03:14:28'),
(162, 'BASIC 5 PACKAGE + DRUG TEST + PT', '2026-07-31 03:14:28'),
(163, 'BASIC 5 + PREGNANCY TEST', '2026-07-31 03:14:28'),
(164, 'CITI HARDWARE PACKAGE FEMALE', '2026-07-31 03:14:28'),
(165, 'CITI HARDWARE PACKAGE MALE', '2026-07-31 03:14:28'),
(166, 'SOLID GOLD FOOD INC./ MCDONALDS A', '2026-07-31 03:14:28'),
(167, 'SOLID GOLD FOOD INC./ MCDONALDS B', '2026-07-31 03:14:28'),
(168, 'ANDOKS PACKAGE A', '2026-07-31 03:14:28'),
(169, 'ANDOKS PACKAGE B', '2026-07-31 03:14:28'),
(170, 'CEBU GENERAL', '2026-07-31 03:14:28'),
(171, 'BLUE COLLAR', '2026-07-31 03:14:28'),
(172, 'BLUE COLLAR B', '2026-07-31 03:14:28'),
(173, 'BLUE COLLAR HEALTHCARD', '2026-07-31 03:14:29'),
(174, 'BFP', '2026-07-31 03:14:29'),
(175, 'GO GROUP', '2026-07-31 03:14:29'),
(176, 'GO GROUP MANAGERIAL', '2026-07-31 03:14:29'),
(177, 'MANAGERIAL MALE', '2026-07-31 03:14:29'),
(178, 'XTEND OPS MALE', '2026-07-31 03:14:29'),
(179, 'XTEND OPS FEMALE', '2026-07-31 03:14:29'),
(180, 'SAN AGUSTIN FACULTY', '2026-07-31 03:14:29'),
(181, 'SAN AGUSTIN PACKAGE A', '2026-07-31 03:14:29'),
(182, 'SAN AGUSTIN PACKAGE B', '2026-07-31 03:14:29'),
(183, 'WVSU- LAW', '2026-07-31 03:14:29'),
(184, 'SCHIERMAN MANPOWER', '2026-07-31 03:14:29'),
(185, 'Dep Ed Package', '2026-07-31 03:14:29'),
(186, 'ZURI HOTEL', '2026-07-31 03:14:29'),
(187, 'LIFE LINE', '2026-07-31 03:14:29'),
(188, 'CNT AGENCY', '2026-07-31 03:14:29'),
(189, 'XOOM MOBILE', '2026-07-31 03:14:29'),
(190, 'J&T ILOILO', '2026-07-31 03:14:29'),
(191, 'J & T ILOILO', '2026-07-31 03:14:29'),
(192, 'DAVAO MANPOWER PACKAGE 4', '2026-07-31 03:14:29'),
(193, 'STAR BOARD PACKAGE A', '2026-07-31 03:14:29'),
(194, 'STAR BOARD PACKAGE B', '2026-07-31 03:14:29'),
(195, 'S&R ILOILO', '2026-07-31 03:14:29'),
(196, 'STAR BOARD PACKAGE', '2026-07-31 03:14:29'),
(197, 'La Filipina (Uy Gongco)', '2026-07-31 03:14:29'),
(198, 'MANDAUE FOAM ILOILO', '2026-07-31 03:14:29'),
(199, 'PARAMOUNT HUMAN RESOURCES', '2026-07-31 03:14:29'),
(200, 'COMMONWEALTH APE', '2026-07-31 03:14:29'),
(201, 'COMMONWEALTH PAPSMEAR', '2026-07-31 03:14:29'),
(202, 'PAPSMEAR', '2026-07-31 03:14:29'),
(203, 'JOLLY MANAGEMENT SOLUTION INC.', '2026-07-31 03:14:29'),
(204, 'WALK IN', '2026-07-31 03:14:29'),
(205, 'SAM\'S 21/ DISTRICT 21 HOLTE', '2026-07-31 03:14:29'),
(206, 'ICK MANPOWER SRE', '2026-07-31 03:14:29'),
(207, 'TESDA PACKAGE', '2026-07-31 03:14:29'),
(208, 'RONIN', '2026-07-31 03:14:29'),
(209, 'MCR', '2026-07-31 03:14:29'),
(210, 'KTKM', '2026-07-31 03:14:29'),
(211, 'BAGUIO INT\'L AGENCY', '2026-07-31 03:14:29'),
(212, 'RE PRINT LAB RESULT', '2026-07-31 03:14:29'),
(213, 'RE PRINT X-RAY RESULT', '2026-07-31 03:14:29'),
(214, 'CHARGE TO COMPANY', '2026-07-31 03:14:29'),
(215, 'COVID RAPID Ag TEST', '2026-07-31 03:14:29');

-- --------------------------------------------------------

--
-- Table structure for table `dois_acc_titles`
--

CREATE TABLE `dois_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_acc_titles`
--

INSERT INTO `dois_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-20 10:02:31'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-20 10:02:31'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-20 10:02:31'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-20 10:02:31'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-20 10:02:31'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-20 10:02:31'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-20 10:02:31'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-20 10:02:31'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-20 10:02:31'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-20 10:02:31'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-20 10:02:31'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-20 10:02:31'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-20 10:02:31'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-20 10:02:31'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-20 10:02:31'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-20 10:02:31'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-20 10:02:31'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-20 10:02:31'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-20 10:02:31'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-20 10:02:31'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-20 10:02:31'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-20 10:02:31'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-20 10:02:31'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-20 10:02:31'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-20 10:02:31'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-20 10:02:31'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-20 10:02:31'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-20 10:02:31'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-20 10:02:31'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-20 10:02:31'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-20 10:02:31'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-20 10:02:31'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-20 10:02:31'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-20 10:02:31'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-20 10:02:31'),
(37, 'Drinking Water', 'expenses', 35, 'Dois', '2026-07-22 08:16:52');

-- --------------------------------------------------------

--
-- Table structure for table `dois_cashflow`
--

CREATE TABLE `dois_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_cashflow_balance`
--

CREATE TABLE `dois_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_categories`
--

CREATE TABLE `dois_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_categories`
--

INSERT INTO `dois_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(27, 'Dois', 'MEAT', 0, '2026-07-23 09:48:36'),
(28, 'Dois', 'SEAFOOD', 1, '2026-07-23 09:48:48');

-- --------------------------------------------------------

--
-- Table structure for table `dois_categories_meta`
--

CREATE TABLE `dois_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_categories_meta`
--

INSERT INTO `dois_categories_meta` (`store_name`, `seeded`) VALUES
('Dois', 1);

-- --------------------------------------------------------

--
-- Table structure for table `dois_cf_vat_selection`
--

CREATE TABLE `dois_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_cf_vat_selection`
--

INSERT INTO `dois_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'Dois', 2026, 7, 0.00, 0, 'Dois', '2026-07-09 07:50:51');

-- --------------------------------------------------------

--
-- Table structure for table `dois_cogs`
--

CREATE TABLE `dois_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_cogs`
--

INSERT INTO `dois_cogs` (`id`, `cogs_date`, `cogs_year`, `cogs_month`, `store_name`, `beg`, `purc`, `end_inv`, `cos`, `mktg_cost`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-05-01', 2026, 5, 'Dois', 800000.00, 800000.00, 800000.00, 800000.00, 800000.00, 'Dois', '2026-05-09 02:08:48', '2026-05-09 02:08:48'),
(2, '2026-06-01', 2026, 6, 'Dois', 1000.00, 1000.00, 1000.00, 1000.00, 0.00, 'Dois', '2026-06-29 10:24:05', '2026-06-29 10:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `dois_dinein_rows`
--

CREATE TABLE `dois_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_dinein_rows`
--

INSERT INTO `dois_dinein_rows` (`id`, `store_name`, `report_date`, `cash`, `palawan_pay`, `card_swipe_qr`, `unpaid_credit_name`, `unpaid_credit_amount`, `discount`, `bank_transfer_cheque`, `cancelled_transactions`, `sort_order`) VALUES
(43, 'Dois', '2026-07-22', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0),
(44, 'Dois', '2026-07-22', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 1),
(48, 'Dois', '2026-07-23', 123.00, 123.00, 1123.00, '', 123.00, 123.00, 123.00, 0.00, 0),
(53, 'Dois', '2026-07-08', 31692.24, 0.00, 24314.59, '', 0.00, 1599.80, 0.00, 0.00, 0),
(57, 'Dois', '2026-07-19', 37429.88, 0.00, 17333.39, '', 0.00, 316.20, 0.00, 0.00, 0),
(58, 'Dois', '2026-07-19', 0.00, 0.00, 0.00, '', 0.00, 1956.17, 0.00, 0.00, 1),
(61, 'Dois', '2026-07-27', 120.00, 120.00, 120.00, '', 120.00, 120.00, 120.00, 0.00, 0),
(62, 'Dois', '2026-07-27', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `dois_disbursement`
--

CREATE TABLE `dois_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_disbursement`
--

INSERT INTO `dois_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(3, '2026-07-20', '123-749-931-000', 'D\'TWIN STARS INDUSTRIAL SALES AND SERVICES', 'VAT', '#117 AVANCENA STREET, BRGY. SOUTH FUNDIDOR,MOLO, ILOILO CITY', '', 'Salaries and Wages', 200.00, 21.43, 178.57, '', 'Dois', '2026-07-20 10:10:14', '2026-07-22 01:09:37'),
(4, '2026-07-22', '', 'Aqua', 'NV', '', '', 'Drinking Water', 1000.00, 0.00, 1000.00, '', 'Dois', '2026-07-22 08:17:44', '2026-07-22 08:17:44');

-- --------------------------------------------------------

--
-- Table structure for table `dois_expenses`
--

CREATE TABLE `dois_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_expenses`
--

INSERT INTO `dois_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `pcf_expenses`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `selected_for_cf`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-01', '123', '123', 'as', 'as', 'dasda', 'asd', 'asdad', 123.00, 123.00, 123.00, 123.00, 246.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Dois', '2026-04-01 23:56:09', '2026-04-01 23:56:09'),
(2, '2026-06-18', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1000.00, 1000.00, 1000.00, 1000.00, 1000.00, 1000.00, 0.00, 10000.00, 0.00, 16000.00, 0, 'Dois', '2026-06-18 01:49:35', '2026-06-18 01:49:46'),
(3, '2026-06-29', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 32.00, 0, 'Dois', '2026-06-29 10:18:08', '2026-06-29 10:18:32'),
(9, '2026-07-19', 'AUTO-SR', '', '', '', 'PCF/Expenses & Other Expenses (auto from Sales Report)', '', '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 16234.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 16234.00, 0, 'Dois', '2026-07-27 07:30:09', '2026-07-27 07:30:09'),
(10, '2026-07-27', 'AUTO-SR', '', '', '', 'PCF/Expenses & Other Expenses (auto from Sales Report)', '', '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 120.00, 120.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 240.00, 0, 'Dois', '2026-07-27 08:15:39', '2026-07-27 08:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `dois_income_statement`
--

CREATE TABLE `dois_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Dois',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_month_end_inv`
--

CREATE TABLE `dois_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_month_end_inv`
--

INSERT INTO `dois_month_end_inv` (`id`, `inv_date`, `inv_year`, `inv_month`, `store_name`, `category`, `sort_order`, `item_desc`, `unit`, `supplier_cost`, `end_inv_num`, `total_amount`, `saved_by`, `created_at`, `updated_at`) VALUES
(7, '2026-07-31', 2026, 7, 'Dois', 'MEAT', 0, 'Pata', 'PCS', 200.00, 50.0000, 10000.00, 'Dois', '2026-07-23 09:51:24', '2026-07-23 09:51:24');

-- --------------------------------------------------------

--
-- Table structure for table `dois_pdc`
--

CREATE TABLE `dois_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_pl_revenue`
--

CREATE TABLE `dois_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_reconcile`
--

CREATE TABLE `dois_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dois_report_entries`
--

CREATE TABLE `dois_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Dois',
  `pos_reading` decimal(12,2) DEFAULT 0.00,
  `cash` decimal(12,2) DEFAULT 0.00,
  `short_over` decimal(12,2) DEFAULT 0.00,
  `tips` decimal(12,2) DEFAULT 0.00,
  `gross_sales` decimal(12,2) DEFAULT 0.00,
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00,
  `cancelled_transaction` decimal(12,2) DEFAULT 0.00,
  `unpaid_staff` decimal(12,2) DEFAULT 0.00,
  `unpaid_mam` decimal(12,2) DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00,
  `late_payment` decimal(12,2) DEFAULT 0.00,
  `advance_payment` decimal(12,2) DEFAULT 0.00,
  `grab` decimal(12,2) DEFAULT 0.00,
  `gcash` decimal(12,2) DEFAULT 0.00,
  `gc_sold` decimal(12,2) DEFAULT 0.00,
  `gc_sponsorship` decimal(12,2) DEFAULT 0.00,
  `bank_transfer` decimal(12,2) DEFAULT 0.00,
  `discounted` decimal(12,2) DEFAULT 0.00,
  `personal` decimal(12,2) DEFAULT 0.00,
  `cash_advance` decimal(12,2) DEFAULT 0.00,
  `payroll` decimal(12,2) DEFAULT 0.00,
  `commi_fund` decimal(12,2) DEFAULT 0.00,
  `service_charge_pos` decimal(12,2) DEFAULT 0.00,
  `cancelled_sc` decimal(12,2) DEFAULT 0.00,
  `service_charge_depo` decimal(12,2) DEFAULT 0.00,
  `pcf` decimal(12,2) DEFAULT 0.00,
  `other_expenses` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `total_swipe` decimal(12,2) DEFAULT 0.00,
  `other_deposits` decimal(12,2) DEFAULT 0.00,
  `lechonan_sales` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `marketing_inputable` decimal(12,2) DEFAULT 0.00,
  `vat_exemption` decimal(12,2) DEFAULT 0.00,
  `net_gross_sales` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_report_entries`
--

INSERT INTO `dois_report_entries` (`id`, `report_date`, `store_name`, `pos_reading`, `cash`, `short_over`, `tips`, `gross_sales`, `sales_of_day_swipe`, `cancelled_transaction`, `unpaid_staff`, `unpaid_mam`, `marketing_pull_out`, `late_payment`, `advance_payment`, `grab`, `gcash`, `gc_sold`, `gc_sponsorship`, `bank_transfer`, `discounted`, `personal`, `cash_advance`, `payroll`, `commi_fund`, `service_charge_pos`, `cancelled_sc`, `service_charge_depo`, `pcf`, `other_expenses`, `total_deductions`, `total_swipe`, `other_deposits`, `lechonan_sales`, `remarks`, `saved_by`, `created_at`, `updated_at`, `marketing_inputable`, `vat_exemption`, `net_gross_sales`) VALUES
(10, '2026-07-19', 'Dois', 64436.29, 37429.88, 537.70, 0.00, 64436.29, 17333.39, 0.00, 1814.23, 0.00, 500.00, 0.00, 0.00, 8770.00, 0.00, 0.00, 0.00, 0.00, 2272.37, 0.00, 0.00, 550.00, 0.00, 3183.58, 0.00, 3183.58, 16234.00, 0.00, 47473.99, 0.00, 0.00, 0.00, '', 'Dois', '2026-07-22 10:18:19', '2026-07-27 07:40:59', 0.00, 1173.70, 65109.99),
(32, '2026-07-23', 'Dois', 2353.00, 123.00, 1097.00, 0.00, 2353.00, 1123.00, 0.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 550.00, 0.00, 550.00, 0.00, 123.00, 0.00, 123.00, 550.00, 550.00, 3430.00, 0.00, 0.00, 0.00, '', 'Dois', '2026-07-23 03:50:30', '2026-07-23 03:50:30', 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `dois_sales_detail_rows`
--

CREATE TABLE `dois_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_sales_detail_rows`
--

INSERT INTO `dois_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(190, 'Dois', '2026-07-22', 'deposit', '', 0.00, 0),
(191, 'Dois', '2026-07-22', 'late_payment', '', 0.00, 0),
(192, 'Dois', '2026-07-22', 'paid_med', '', 0.00, 0),
(193, 'Dois', '2026-07-22', 'advance_paid', '', 0.00, 0),
(194, 'Dois', '2026-07-22', 'gc_sold', '', 0.00, 0),
(195, 'Dois', '2026-07-22', 'gc_availed', '', 0.00, 0),
(196, 'Dois', '2026-07-17', 'deposit', '', 0.00, 0),
(197, 'Dois', '2026-07-17', 'late_payment', '', 0.00, 0),
(198, 'Dois', '2026-07-17', 'paid_med', '', 0.00, 0),
(199, 'Dois', '2026-07-17', 'advance_paid', '', 0.00, 0),
(200, 'Dois', '2026-07-17', 'marketing_pullout', '', 0.00, 0),
(201, 'Dois', '2026-07-17', 'gc_sold', '', 0.00, 0),
(202, 'Dois', '2026-07-17', 'gc_availed', '', 0.00, 0),
(203, 'Dois', '2026-07-20', 'late_payment', '', 0.00, 0),
(204, 'Dois', '2026-07-20', 'paid_med', '', 0.00, 0),
(205, 'Dois', '2026-07-20', 'advance_paid', '', 0.00, 0),
(206, 'Dois', '2026-07-20', 'marketing_pullout', '', 0.00, 0),
(207, 'Dois', '2026-07-20', 'gc_sold', '', 0.00, 0),
(208, 'Dois', '2026-07-20', 'gc_availed', '', 0.00, 0),
(236, 'Dois', '2026-07-23', 'grab', '', 123.00, 0),
(237, 'Dois', '2026-07-23', 'deposit', '', 123.00, 0),
(238, 'Dois', '2026-07-23', 'late_payment', '', 123.00, 0),
(239, 'Dois', '2026-07-23', 'unpaid_med', '', 123.00, 0),
(240, 'Dois', '2026-07-23', 'paid_med', '', 123.00, 0),
(241, 'Dois', '2026-07-23', 'advance_paid', '', 123.00, 0),
(242, 'Dois', '2026-07-23', 'marketing_pullout', '', 123.00, 0),
(243, 'Dois', '2026-07-23', 'gc_sold', '', 123.00, 0),
(244, 'Dois', '2026-07-23', 'gc_availed', '', 123.00, 0),
(281, 'Dois', '2026-07-08', 'grab', '', 10188.00, 0),
(282, 'Dois', '2026-07-08', 'deposit', '', 1090.43, 0),
(283, 'Dois', '2026-07-08', 'late_payment', '', 0.00, 0),
(284, 'Dois', '2026-07-08', 'unpaid_med', '', 385.20, 0),
(285, 'Dois', '2026-07-08', 'paid_med', '', 0.00, 0),
(286, 'Dois', '2026-07-08', 'advance_paid', '', 0.00, 0),
(287, 'Dois', '2026-07-08', 'marketing_pullout', '', 0.00, 0),
(288, 'Dois', '2026-07-08', 'gc_sold', '', 0.00, 0),
(289, 'Dois', '2026-07-08', 'gc_availed', '', 0.00, 0),
(317, 'Dois', '2026-07-19', 'grab', '10 OS', 8770.00, 0),
(318, 'Dois', '2026-07-19', 'deposit', '', 0.00, 0),
(319, 'Dois', '2026-07-19', 'late_payment', '', 0.00, 0),
(320, 'Dois', '2026-07-19', 'unpaid_med', 'Shan', 439.20, 0),
(321, 'Dois', '2026-07-19', 'unpaid_med', 'SHucks', 883.63, 1),
(322, 'Dois', '2026-07-19', 'unpaid_med', 'Lyka', 108.00, 2),
(323, 'Dois', '2026-07-19', 'unpaid_med', 'Demic', 383.40, 3),
(324, 'Dois', '2026-07-19', 'paid_med', '', 0.00, 0),
(325, 'Dois', '2026-07-19', 'advance_paid', '', 0.00, 0),
(326, 'Dois', '2026-07-19', 'marketing_pullout', '', 500.00, 0),
(327, 'Dois', '2026-07-19', 'gc_sold', '', 0.00, 0),
(328, 'Dois', '2026-07-19', 'gc_availed', '', 0.00, 0),
(340, 'Dois', '2026-07-27', 'grab', '', 0.00, 0),
(341, 'Dois', '2026-07-27', 'deposit', '', 120.00, 0),
(342, 'Dois', '2026-07-27', 'late_payment', '', 120.00, 0),
(343, 'Dois', '2026-07-27', 'unpaid_med', '', 120.00, 0),
(344, 'Dois', '2026-07-27', 'paid_med', '', 120.00, 0),
(345, 'Dois', '2026-07-27', 'advance_paid', '', 120120.00, 0),
(346, 'Dois', '2026-07-27', 'marketing_pullout', '', 120.00, 0),
(347, 'Dois', '2026-07-27', 'marketing_pullout', '', 0.00, 1),
(348, 'Dois', '2026-07-27', 'marketing_pullout', '', 0.00, 2),
(349, 'Dois', '2026-07-27', 'gc_sold', '', 120.00, 0),
(350, 'Dois', '2026-07-27', 'gc_availed', '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `dois_sales_report`
--

CREATE TABLE `dois_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Dois',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cashier_name` varchar(150) DEFAULT NULL,
  `paid_med` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gc_sold` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gc_availed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `personal_withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dois_sales_report`
--

INSERT INTO `dois_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`, `cashier_name`, `paid_med`, `advance_paid`, `gc_sold`, `gc_availed`, `personal_withdrawal`) VALUES
(1, 'Dois', '2026-07-17', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Dois', '2026-07-17 01:32:38', '2026-07-23 02:51:38', '', 0.00, 0.00, 0.00, 0.00, 0.00),
(10, 'Dois', '2026-07-20', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Dois', '2026-07-17 06:13:35', '2026-07-23 02:58:25', '', 0.00, 0.00, 0.00, 0.00, 0.00),
(12, 'Dois', '2026-07-22', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Dois', '2026-07-22 01:06:48', '2026-07-22 10:29:06', '', 0.00, 0.00, 0.00, 0.00, 0.00),
(14, 'Dois', '2026-07-19', 64436.29, 3183.58, 64436.29, 0.00, 0.00, 0.00, 17333.39, 1814.23, 8770.00, 0.00, 550.00, 500.00, 2272.37, 0.00, 16234.00, 0.00, 17500.00, 16962.30, 537.70, 'Dois', '2026-07-22 07:03:35', '2026-07-27 07:30:09', 'Blesss', 0.00, 0.00, 0.00, 0.00, 0.00),
(22, 'Dois', '2026-07-23', 2353.00, 123.00, 2353.00, 0.00, 123.00, 123.00, 1123.00, 123.00, 123.00, 123.00, 550.00, 123.00, 123.00, 123.00, 550.00, 550.00, 20.00, -1077.00, 1097.00, 'Dois', '2026-07-23 03:05:58', '2026-07-23 03:33:32', 'Bless', 123.00, 123.00, 123.00, 123.00, 550.00),
(26, 'Dois', '2026-07-08', 64875.91, 3303.92, 64875.91, 0.00, 1090.43, 0.00, 24314.59, 385.20, 10188.00, 0.00, 0.00, 0.00, 1599.80, 0.00, 13188.75, 0.00, 15200.00, 15199.57, 0.43, 'Dois', '2026-07-23 09:05:45', '2026-07-23 09:34:44', '', 0.00, 0.00, 0.00, 0.00, 0.00),
(31, 'Dois', '2026-07-27', 121320.00, 0.00, 121320.00, 360.00, 120.00, 120.00, 120.00, 120.00, 0.00, 120.00, 0.00, 120.00, 120.00, 120.00, 120.00, 120.00, 0.00, 0.00, 0.00, 'Dois', '2026-07-27 07:17:46', '2026-07-27 08:16:28', 'Shantily', 120.00, 120120.00, 120.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `expense_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `expense_date`, `notes`, `created_by`, `created_at`) VALUES
(1, 'Salaries & Wages', 'December payroll', 48500.00, '2024-12-31', NULL, 1, '2026-03-26 04:29:59'),
(2, 'Marketing & Ads', 'Google Ads Q4', 8200.00, '2024-12-20', NULL, 1, '2026-03-26 04:29:59'),
(3, 'Software & Tools', 'AWS monthly invoice', 3100.00, '2024-12-01', NULL, 1, '2026-03-26 04:29:59'),
(4, 'Operations', 'Office supplies', 650.00, '2024-12-15', NULL, 1, '2026-03-26 04:29:59'),
(5, 'Logistics & Ship', 'Courier fees Dec', 1800.00, '2024-12-18', NULL, 1, '2026-03-26 04:29:59'),
(6, 'Salaries & Wages', 'November payroll', 48500.00, '2024-11-30', NULL, 1, '2026-03-26 04:29:59'),
(7, 'Marketing & Ads', 'LinkedIn Ads Nov', 5400.00, '2024-11-25', NULL, 1, '2026-03-26 04:29:59'),
(8, 'Office & Utilities', 'Electricity & internet', 1200.00, '2024-11-05', NULL, 1, '2026-03-26 04:29:59'),
(9, 'Miscellaneous', 'Team lunch & events', 900.00, '2024-11-20', NULL, 1, '2026-03-26 04:29:59'),
(10, 'Software & Tools', 'Figma & Notion licenses', 480.00, '2024-11-01', NULL, 1, '2026-03-26 04:29:59');

-- --------------------------------------------------------

--
-- Table structure for table `h_acc_titles`
--

CREATE TABLE `h_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_acc_titles`
--

INSERT INTO `h_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-21 03:23:36'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-21 03:23:36'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-21 03:23:36'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-21 03:23:36'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-21 03:23:36'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-21 03:23:36'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-21 03:23:36'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-21 03:23:36'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-21 03:23:36'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-21 03:23:36'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-21 03:23:36'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-21 03:23:36'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-21 03:23:36'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-21 03:23:36'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-21 03:23:36'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-21 03:23:36'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-21 03:23:36'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-21 03:23:36'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-21 03:23:36'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-21 03:23:36'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-21 03:23:36'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-21 03:23:36'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-21 03:23:36'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-21 03:23:36'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-21 03:23:36'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-21 03:23:36'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-21 03:23:36'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-21 03:23:36'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-21 03:23:36'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-21 03:23:36'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-21 03:23:36'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-21 03:23:36'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-21 03:23:36'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-21 03:23:36'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-21 03:23:36');

-- --------------------------------------------------------

--
-- Table structure for table `h_bank_statement`
--

CREATE TABLE `h_bank_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `roi_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_change` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_bank_statement`
--

INSERT INTO `h_bank_statement` (`id`, `store_name`, `report_date`, `opening_balance`, `roi_pull_out`, `closing_balance`, `net_change`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'H', '2026-07-30', 296716.89, 630000.00, 752161.93, 455445.04, '', 'H', '2026-07-30 01:37:51', '2026-07-30 01:37:51');

-- --------------------------------------------------------

--
-- Table structure for table `h_bank_statement_locks`
--

CREATE TABLE `h_bank_statement_locks` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL,
  `report_date` date NOT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `h_bank_statement_rows`
--

CREATE TABLE `h_bank_statement_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_bank_statement_rows`
--

INSERT INTO `h_bank_statement_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(19, 'H', '2026-07-30', 'cash_in_bank', 'Ending Balance', 734978.42, 0),
(20, 'H', '2026-07-30', 'cash_in_bank', 'Deposit in Transit', 70044.77, 1),
(21, 'H', '2026-07-30', 'cash_in_bank', 'Pettycash Store', 5000.00, 2),
(22, 'H', '2026-07-30', 'cash_in_bank', 'Pettycash Carwash', 3000.00, 3),
(23, 'H', '2026-07-30', 'accounts_receivable', 'CORPORATE/MANAGEMENT PAYABLES', 95672.58, 0),
(24, 'H', '2026-07-30', 'accounts_receivable', 'STAFF CASH ADVANCE', 91260.68, 1),
(25, 'H', '2026-07-30', 'accounts_receivable', 'EMPLOYEE PAYABLES', 7971.28, 2),
(26, 'H', '2026-07-30', 'accounts_receivable', 'ADV SALARY SHARE FOR JUNE', 15000.00, 3),
(27, 'H', '2026-07-30', 'accounts_receivable', 'ADVANCE TO COMMI', 83955.41, 4),
(28, 'H', '2026-07-30', 'outstanding_checks', '', 11574.00, 0),
(29, 'H', '2026-07-30', 'outstanding_checks', '', 98142.67, 1),
(30, 'H', '2026-07-30', 'outstanding_checks', '', 9000.00, 2),
(31, 'H', '2026-07-30', 'outstanding_checks', '', 9300.78, 3),
(32, 'H', '2026-07-30', 'outstanding_checks', '', 14798.50, 4),
(33, 'H', '2026-07-30', 'outstanding_checks', '', 163729.04, 5),
(34, 'H', '2026-07-30', 'outstanding_checks', '', 2500.00, 6),
(35, 'H', '2026-07-30', 'outstanding_checks', '', 42360.03, 7),
(36, 'H', '2026-07-30', 'outstanding_checks', '', 3316.19, 8);

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_cash_rows`
--

CREATE TABLE `h_carwash_cash_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `report_date` date NOT NULL,
  `qty` decimal(12,2) DEFAULT 0.00,
  `denomination` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_cash_rows`
--

INSERT INTO `h_carwash_cash_rows` (`id`, `store_name`, `report_date`, `qty`, `denomination`, `sort_order`) VALUES
(106, 'HEROCARWASH', '2026-07-30', 3.00, 1000.00, 0),
(107, 'HEROCARWASH', '2026-07-30', 0.00, 500.00, 1),
(108, 'HEROCARWASH', '2026-07-30', 0.00, 200.00, 2),
(109, 'HEROCARWASH', '2026-07-30', 4.00, 100.00, 3),
(110, 'HEROCARWASH', '2026-07-30', 0.00, 50.00, 4),
(111, 'HEROCARWASH', '2026-07-30', 0.00, 20.00, 5),
(112, 'HEROCARWASH', '2026-07-30', 0.00, 10.00, 6),
(113, 'HEROCARWASH', '2026-07-30', 0.00, 5.00, 7),
(114, 'HEROCARWASH', '2026-07-30', 0.00, 0.50, 8),
(115, 'HEROCARWASH', '2026-07-30', 0.00, 0.10, 9),
(116, 'HEROCARWASH', '2026-07-30', 0.00, 0.05, 10),
(117, 'HEROCARWASH', '2026-07-30', 0.00, 0.00, 11),
(118, 'HEROCARWASH', '2026-07-30', 0.00, 0.00, 12),
(119, 'HEROCARWASH', '2026-07-30', 0.00, 0.00, 13),
(120, 'HEROCARWASH', '2026-07-30', 0.00, 0.00, 14),
(136, 'HEROCARWASH', '2026-07-29', 0.00, 1000.00, 0),
(137, 'HEROCARWASH', '2026-07-29', 0.00, 500.00, 1),
(138, 'HEROCARWASH', '2026-07-29', 0.00, 200.00, 2),
(139, 'HEROCARWASH', '2026-07-29', 0.00, 100.00, 3),
(140, 'HEROCARWASH', '2026-07-29', 0.00, 50.00, 4),
(141, 'HEROCARWASH', '2026-07-29', 0.00, 20.00, 5),
(142, 'HEROCARWASH', '2026-07-29', 0.00, 10.00, 6),
(143, 'HEROCARWASH', '2026-07-29', 0.00, 5.00, 7),
(144, 'HEROCARWASH', '2026-07-29', 0.00, 0.50, 8),
(145, 'HEROCARWASH', '2026-07-29', 0.00, 0.10, 9),
(146, 'HEROCARWASH', '2026-07-29', 0.00, 0.05, 10),
(147, 'HEROCARWASH', '2026-07-29', 0.00, 0.00, 11),
(148, 'HEROCARWASH', '2026-07-29', 0.00, 0.00, 12),
(149, 'HEROCARWASH', '2026-07-29', 0.00, 0.00, 13),
(150, 'HEROCARWASH', '2026-07-29', 0.00, 0.00, 14);

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_detail_rows`
--

CREATE TABLE `h_carwash_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `report_date` date NOT NULL,
  `section` varchar(20) NOT NULL,
  `particular` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_detail_rows`
--

INSERT INTO `h_carwash_detail_rows` (`id`, `store_name`, `report_date`, `section`, `particular`, `amount`, `sort_order`) VALUES
(17, 'HEROCARWASH', '2026-07-29', 'expenses', '', 0.00, 0),
(18, 'HEROCARWASH', '2026-07-29', 'unpaids', '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_income_statement`
--

CREATE TABLE `h_carwash_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'HEROCARWASH',
  `stmt_date` date NOT NULL,
  `stmt_year` int(11) DEFAULT NULL,
  `stmt_month` int(11) DEFAULT NULL,
  `stmt_day` int(11) DEFAULT NULL,
  `stmt_label` varchar(150) DEFAULT NULL,
  `net_sales` decimal(12,2) DEFAULT 0.00,
  `sales_discount` decimal(12,2) DEFAULT 0.00,
  `cost_of_sales` decimal(12,2) DEFAULT 0.00,
  `other_income_royalty` decimal(12,2) DEFAULT 0.00,
  `equipment_supplies` decimal(12,2) DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) DEFAULT 0.00,
  `transportation_fuel` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `water_electricity` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio` decimal(12,2) DEFAULT 0.00,
  `common_area_charges` decimal(12,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(12,2) DEFAULT 0.00,
  `philhealth_sss` decimal(12,2) DEFAULT 0.00,
  `medical_supplies` decimal(12,2) DEFAULT 0.00,
  `agency_fees` decimal(12,2) DEFAULT 0.00,
  `bank_fees` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `representation_benefits` decimal(12,2) DEFAULT 0.00,
  `professional_fees` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `freight_storage` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(12,2) DEFAULT 0.00,
  `taxes_licenses` decimal(12,2) DEFAULT 0.00,
  `system_development` decimal(12,2) DEFAULT 0.00,
  `construction_progress` decimal(12,2) DEFAULT 0.00,
  `insurance` decimal(12,2) DEFAULT 0.00,
  `admin_shares` decimal(12,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(12,2) DEFAULT 0.00,
  `vat_payment` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_marketing_rows`
--

CREATE TABLE `h_carwash_marketing_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `report_date` date NOT NULL,
  `particular` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `staff` varchar(100) DEFAULT NULL,
  `commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_marketing_rows`
--

INSERT INTO `h_carwash_marketing_rows` (`id`, `store_name`, `report_date`, `particular`, `amount`, `staff`, `commission`, `sort_order`) VALUES
(20, 'HEROCARWASH', '2026-07-30', '', 250.00, '', 75.00, 0),
(21, 'HEROCARWASH', '2026-07-30', '', 250.00, '', 75.00, 1),
(22, 'HEROCARWASH', '2026-07-30', '', 250.00, '', 87.50, 2),
(24, 'HEROCARWASH', '2026-07-29', '', 0.00, '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_report`
--

CREATE TABLE `h_carwash_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `report_date` date NOT NULL,
  `opening_cashier` varchar(150) DEFAULT NULL,
  `closing_cashier` varchar(150) DEFAULT NULL,
  `sold_gc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qr_palawan_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `card_payments` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `staff_cf` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pos_reading` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaids` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discounts` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_report`
--

INSERT INTO `h_carwash_report` (`id`, `store_name`, `report_date`, `opening_cashier`, `closing_cashier`, `sold_gc`, `qr_palawan_pay`, `card_payments`, `gross_sales`, `staff_cf`, `pos_reading`, `expenses`, `unpaids`, `discounts`, `marketing_expense`, `net_cash`, `coh`, `short_over`, `total_commission`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'HEROCARWASH', '2026-07-30', 'Bless', 'bless 2', 0.00, 350.00, 450.00, 3900.00, 1050.00, 4950.00, 0.00, 0.00, 0.00, 750.00, 3400.00, 3400.00, 0.00, 1287.50, '', 'H', '2026-07-29 02:55:27', '2026-07-29 06:07:35'),
(9, 'HEROCARWASH', '2026-07-29', 'Bless', 'bless 1', 0.00, 0.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, -100.00, 0.00, 100.00, 0.00, '', 'H', '2026-07-29 06:08:01', '2026-07-29 08:22:08');

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_services`
--

CREATE TABLE `h_carwash_services` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `category` varchar(100) NOT NULL DEFAULT 'ADD-ONS / OTHERS',
  `name` varchar(150) NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_services`
--

INSERT INTO `h_carwash_services` (`id`, `store_name`, `category`, `name`, `price`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'HEROCARWASH', '4 WHEELS', 'BASIC SMALL 4 WHEELS', 350.00, 0, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(2, 'HEROCARWASH', '4 WHEELS', 'BASIC MEDIUM 4 WHEELS', 400.00, 1, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(3, 'HEROCARWASH', '4 WHEELS', 'BASIC LARGE 4 WHEELS', 450.00, 2, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(4, 'HEROCARWASH', '4 WHEELS', 'BASIC XL 4 WHEELS', 500.00, 3, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(5, 'HEROCARWASH', '4 WHEELS', 'ADVANCED SMALL 4 WHEELS', 400.00, 4, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(6, 'HEROCARWASH', '4 WHEELS', 'ADVANCED MEDIUM 4 WHEELS', 450.00, 5, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(7, 'HEROCARWASH', '4 WHEELS', 'ADVANCE LARGE 4 WHEELS', 500.00, 6, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(8, 'HEROCARWASH', '4 WHEELS', 'ADVANCE XL 4 WHEELS', 550.00, 7, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(9, 'HEROCARWASH', '4 WHEELS', 'PREMIUM SMALL 4 WHEELS', 500.00, 8, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(10, 'HEROCARWASH', '4 WHEELS', 'PREMIUM MEDIUM 4 WHEELS', 550.00, 9, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(11, 'HEROCARWASH', '4 WHEELS', 'PREMIUM LARGE 4 WHEELS', 600.00, 10, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(12, 'HEROCARWASH', '4 WHEELS', 'PREMIUM XL 4 WHEELS', 700.00, 11, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(13, 'HEROCARWASH', 'MOTORCYCLE', 'BASIC REGULAR MOTORCYCLE', 150.00, 12, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(14, 'HEROCARWASH', 'MOTORCYCLE', 'ADVANCE REGULAR MOTORCYCLE', 250.00, 13, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(15, 'HEROCARWASH', 'MOTORCYCLE', 'ADVANCE BIG BIKE MOTORCYCLE', 380.00, 14, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(16, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'ARMOR ALL', 200.00, 15, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(17, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'BACK TO ZERO', 500.00, 16, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(18, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'ENGINE WASH', 400.00, 17, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(19, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'ASPHALT REMOVAL', 400.00, 18, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(20, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'QUICK WASH', 250.00, 19, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(21, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'VACUUM SMALL', 100.00, 20, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37'),
(22, 'HEROCARWASH', 'ADD-ONS / OTHERS', 'DEFAULT', 0.00, 21, 1, '2026-07-29 02:44:37', '2026-07-29 02:44:37');

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_summary_entries`
--

CREATE TABLE `h_carwash_summary_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'HEROCARWASH',
  `pos_reading` decimal(12,2) DEFAULT 0.00,
  `staff_cf` decimal(12,2) DEFAULT 0.00,
  `gross_sales` decimal(12,2) DEFAULT 0.00,
  `sold_gc` decimal(12,2) DEFAULT 0.00,
  `qr_palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_payments` decimal(12,2) DEFAULT 0.00,
  `coh` decimal(12,2) DEFAULT 0.00,
  `expenses` decimal(12,2) DEFAULT 0.00,
  `unpaids` decimal(12,2) DEFAULT 0.00,
  `discounts` decimal(12,2) DEFAULT 0.00,
  `marketing_expense` decimal(12,2) DEFAULT 0.00,
  `net_cash` decimal(12,2) DEFAULT 0.00,
  `short_over` decimal(12,2) DEFAULT 0.00,
  `total_commission` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gross_sales_excl_mktg` decimal(12,2) DEFAULT 0.00,
  `store_gross_excl_mktg` decimal(12,2) DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) DEFAULT 0.00,
  `cash_for_depo` decimal(12,2) DEFAULT 0.00,
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00,
  `deposit_swipe` decimal(12,2) DEFAULT 0.00,
  `late_payment` decimal(12,2) DEFAULT 0.00,
  `cancelled_transaction` decimal(12,2) DEFAULT 0.00,
  `unpaid` decimal(12,2) DEFAULT 0.00,
  `paid` decimal(12,2) DEFAULT 0.00,
  `advance_payment` decimal(12,2) DEFAULT 0.00,
  `grab` decimal(12,2) DEFAULT 0.00,
  `bank_trans` decimal(12,2) DEFAULT 0.00,
  `gc_sponsor_marketing` decimal(12,2) DEFAULT 0.00,
  `gc_sold` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00,
  `personal` decimal(12,2) DEFAULT 0.00,
  `other_expenses` decimal(12,2) DEFAULT 0.00,
  `sc_for_depo` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `total_swipe` decimal(12,2) DEFAULT 0.00,
  `cash_deposit` decimal(12,2) DEFAULT 0.00,
  `other_sales` decimal(12,2) DEFAULT 0.00,
  `remarks2` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_summary_entries`
--

INSERT INTO `h_carwash_summary_entries` (`id`, `report_date`, `store_name`, `pos_reading`, `staff_cf`, `gross_sales`, `sold_gc`, `qr_palawan_pay`, `card_payments`, `coh`, `expenses`, `unpaids`, `discounts`, `marketing_expense`, `net_cash`, `short_over`, `total_commission`, `remarks`, `saved_by`, `created_at`, `updated_at`, `gross_sales_excl_mktg`, `store_gross_excl_mktg`, `z_reading_gross`, `cash_for_depo`, `sales_of_day_swipe`, `deposit_swipe`, `late_payment`, `cancelled_transaction`, `unpaid`, `paid`, `advance_payment`, `grab`, `bank_trans`, `gc_sponsor_marketing`, `gc_sold`, `discount`, `marketing_pull_out`, `personal`, `other_expenses`, `sc_for_depo`, `total_deductions`, `total_swipe`, `cash_deposit`, `other_sales`, `remarks2`) VALUES
(1, '2026-07-30', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 05:44:21', '2026-07-29 06:08:26', 3900.00, 3150.00, 4950.00, 3400.00, 450.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 750.00, 0.00, 0.00, 0.00, 450.00, 450.00, 0.00, 0.00, ''),
(3, '2026-07-29', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 0.00, '', 'H', '2026-07-29 06:08:01', '2026-07-29 06:08:29', 0.00, 0.00, 0.00, -100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 100.00, 0.00, 0.00, ''),
(6, '2026-07-01', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:41', '2026-07-29 06:10:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(7, '2026-07-02', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:41', '2026-07-29 06:10:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(8, '2026-07-03', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:41', '2026-07-29 06:10:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(9, '2026-07-04', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:41', '2026-07-29 06:10:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(10, '2026-07-05', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:41', '2026-07-29 06:10:41', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(11, '2026-07-06', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(12, '2026-07-07', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(13, '2026-07-08', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(14, '2026-07-09', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(15, '2026-07-10', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(16, '2026-07-11', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:42', '2026-07-29 06:10:42', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(17, '2026-07-12', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(18, '2026-07-13', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(19, '2026-07-14', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(20, '2026-07-15', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(21, '2026-07-16', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(22, '2026-07-17', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:43', '2026-07-29 06:10:43', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(23, '2026-07-18', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(24, '2026-07-19', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(25, '2026-07-20', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(26, '2026-07-21', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(27, '2026-07-22', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(28, '2026-07-23', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(29, '2026-07-24', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:44', '2026-07-29 06:10:44', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(30, '2026-07-25', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:45', '2026-07-29 06:10:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(31, '2026-07-26', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:45', '2026-07-29 06:10:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(32, '2026-07-27', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:45', '2026-07-29 06:10:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(33, '2026-07-28', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:45', '2026-07-29 06:10:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(36, '2026-07-31', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'H', '2026-07-29 06:10:45', '2026-07-29 06:10:45', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `h_carwash_transactions`
--

CREATE TABLE `h_carwash_transactions` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'HEROCARWASH',
  `report_date` date NOT NULL,
  `plate_no` varchar(30) DEFAULT NULL,
  `service` varchar(100) DEFAULT NULL,
  `staff` varchar(100) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `mop` varchar(30) DEFAULT NULL,
  `remarks` varchar(200) DEFAULT NULL,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_carwash_transactions`
--

INSERT INTO `h_carwash_transactions` (`id`, `store_name`, `report_date`, `plate_no`, `service`, `staff`, `price`, `discount`, `commission`, `net_sales`, `mop`, `remarks`, `sort_order`) VALUES
(78, 'HEROCARWASH', '2026-07-30', 'FAT 2995', 'ADVANCED SMALL 4 WHEELS', '', 400.00, 0.00, 100.00, 300.00, 'CASH', '', 0),
(79, 'HEROCARWASH', '2026-07-30', 'F1Z407', 'BASIC SMALL 4 WHEELS', '', 350.00, 0.00, 87.50, 262.50, 'CASH', '', 1),
(80, 'HEROCARWASH', '2026-07-30', '', 'ADVANCE LARGE 4 WHEELS', '', 500.00, 0.00, 125.00, 375.00, 'CASH', '', 2),
(81, 'HEROCARWASH', '2026-07-30', '', 'ENGINE WASH', '', 400.00, 0.00, 100.00, 300.00, 'CASH', '', 3),
(82, 'HEROCARWASH', '2026-07-30', '', 'QUICK WASH', '', 250.00, 0.00, 62.50, 187.50, 'CASH', '', 4),
(83, 'HEROCARWASH', '2026-07-30', '', 'BASIC SMALL 4 WHEELS', '', 350.00, 0.00, 87.50, 262.50, 'CASH', '', 5),
(84, 'HEROCARWASH', '2026-07-30', '', 'PREMIUM MEDIUM 4 WHEELS', '', 550.00, 0.00, 137.50, 412.50, 'CASH', '', 6),
(85, 'HEROCARWASH', '2026-07-30', '', 'PREMIUM LARGE 4 WHEELS', '', 600.00, 0.00, 150.00, 450.00, 'CASH', '', 7),
(86, 'HEROCARWASH', '2026-07-30', '', 'ADVANCED MEDIUM 4 WHEELS', '', 450.00, 0.00, 112.50, 337.50, 'CASH', '', 8),
(87, 'HEROCARWASH', '2026-07-30', '', 'QUICK WASH', '', 250.00, 0.00, 62.50, 187.50, 'CASH', '', 9),
(88, 'HEROCARWASH', '2026-07-30', '', 'VACUUM SMALL', '', 100.00, 0.00, 25.00, 75.00, 'CASH', '', 10);

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow`
--

CREATE TABLE `h_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pull_out_roi` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cf_operations` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cf_financing` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_increase_cash` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cashflow`
--

INSERT INTO `h_cashflow` (`id`, `cf_date`, `cf_year`, `cf_month`, `store_name`, `cash_beg`, `sales`, `inv_purchases`, `expenses`, `pdc_loan`, `withdrawals`, `net_cash_flow`, `cash_end`, `saved_by`, `created_at`, `updated_at`, `pull_out_roi`, `depreciation`, `net_cf_operations`, `net_cf_financing`, `net_increase_cash`) VALUES
(1, '2026-07-31', 2026, 7, 'H', 296716.89, 3160049.95, 1293439.10, 1595410.95, 0.00, 0.00, 0.00, 753102.58, 'H', '2026-07-29 06:32:58', '2026-07-30 05:51:56', 0.00, 185185.79, 271199.90, 185185.79, 456385.69);

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow_balance`
--

CREATE TABLE `h_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow_recon`
--

CREATE TABLE `h_cashflow_recon` (
  `id` int(11) NOT NULL,
  `recon_date` date NOT NULL,
  `recon_year` int(4) NOT NULL,
  `recon_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_of_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operating_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `administrative_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `extra_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sales_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `add_back_depreciation` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cogs_income_statement` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_ending_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `petty_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `beginning_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cashflow_recon`
--

INSERT INTO `h_cashflow_recon` (`id`, `recon_date`, `recon_year`, `recon_month`, `store_name`, `sales`, `cost_of_sales`, `operating_expenses`, `administrative_expenses`, `extra_expenses`, `sales_discount`, `add_back_depreciation`, `total_purchases`, `cogs_income_statement`, `bank_ending_balance`, `deposit_in_transit`, `petty_cash`, `beginning_balance`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-07-31', 2026, 7, 'H', 1663735.11, 736975.68, 422779.42, 132751.34, 112133.95, 46369.93, 36192.24, 728644.04, 736975.68, 1190264.26, 0.00, 5000.00, 372275.62, 'H', '2026-07-30 03:08:12', '2026-07-30 04:02:45');

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow_recon_payable_rows`
--

CREATE TABLE `h_cashflow_recon_payable_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `recon_year` int(4) NOT NULL,
  `recon_month` tinyint(2) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cashflow_recon_payable_rows`
--

INSERT INTO `h_cashflow_recon_payable_rows` (`id`, `store_name`, `recon_year`, `recon_month`, `item_name`, `amount`, `sort_order`) VALUES
(10, 'H', 2026, 7, '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow_recon_receivable_rows`
--

CREATE TABLE `h_cashflow_recon_receivable_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `recon_year` int(4) NOT NULL,
  `recon_month` tinyint(2) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cashflow_recon_receivable_rows`
--

INSERT INTO `h_cashflow_recon_receivable_rows` (`id`, `store_name`, `recon_year`, `recon_month`, `item_name`, `amount`, `sort_order`) VALUES
(33, 'H', 2026, 7, 'CORPORATE/MANAGEMENT PAYABLES', 48994.50, 0),
(34, 'H', 2026, 7, 'STAFF CASH ADVANCE', 39429.34, 1),
(35, 'H', 2026, 7, 'EMPLOYEE PAYABLES', 5089.77, 2),
(36, 'H', 2026, 7, '', 0.00, 3);

-- --------------------------------------------------------

--
-- Table structure for table `h_cashflow_withdrawal_rows`
--

CREATE TABLE `h_cashflow_withdrawal_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cashflow_withdrawal_rows`
--

INSERT INTO `h_cashflow_withdrawal_rows` (`id`, `store_name`, `cf_year`, `cf_month`, `item_name`, `amount`, `sort_order`) VALUES
(13, 'H', 2026, 7, '', 0.00, 0),
(14, 'H', 2026, 7, '', 0.00, 1),
(15, 'H', 2026, 7, '', 0.00, 2),
(16, 'H', 2026, 7, '', 0.00, 3),
(17, 'H', 2026, 7, '', 0.00, 4);

-- --------------------------------------------------------

--
-- Table structure for table `h_categories`
--

CREATE TABLE `h_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_categories`
--

INSERT INTO `h_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(1, 'H', 'LIQUORS/BEVERAGES', 0, '2026-07-22 03:48:14'),
(2, 'H', 'DRY GOODS', 1, '2026-07-22 03:48:14'),
(3, 'H', 'BAR STOCKS', 2, '2026-07-22 03:48:14'),
(4, 'H', 'MEAT/WET PRODUCTS', 3, '2026-07-22 03:48:14'),
(5, 'H', 'VEGETABLES', 4, '2026-07-22 03:48:14'),
(6, 'H', 'PASTA', 5, '2026-07-22 03:48:14'),
(7, 'H', 'CONDIMENTS', 6, '2026-07-22 03:48:14'),
(8, 'H', 'BREAD', 7, '2026-07-22 03:48:14'),
(9, 'H', 'FISH', 8, '2026-07-29 06:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `h_categories_meta`
--

CREATE TABLE `h_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_categories_meta`
--

INSERT INTO `h_categories_meta` (`store_name`, `seeded`) VALUES
('H', 1);

-- --------------------------------------------------------

--
-- Table structure for table `h_cf_vat_selection`
--

CREATE TABLE `h_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cf_vat_selection`
--

INSERT INTO `h_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'H', 2026, 7, 74.65, 3, 'H', '2026-07-30 07:54:20');

-- --------------------------------------------------------

--
-- Table structure for table `h_check_report`
--

CREATE TABLE `h_check_report` (
  `id` int(11) NOT NULL,
  `check_date` date DEFAULT NULL,
  `cr_year` int(4) NOT NULL,
  `cr_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `vendor` varchar(200) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_released` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL,
  `sort_order` int(4) DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_check_report`
--

INSERT INTO `h_check_report` (`id`, `check_date`, `cr_year`, `cr_month`, `store_name`, `vendor`, `amount`, `is_released`, `remarks`, `sort_order`, `saved_by`, `created_at`, `updated_at`) VALUES
(5, '2026-07-30', 2026, 7, 'H', '', 60164.35, 0, '', 9999, 'H', '2026-07-30 05:35:02', '2026-07-30 05:35:02'),
(6, '2026-07-30', 2026, 7, 'H', '', 13728.80, 0, '', 9999, 'H', '2026-07-30 05:35:02', '2026-07-30 05:35:02'),
(7, '2026-07-30', 2026, 7, 'H', '', 23898.96, 0, '', 9999, 'H', '2026-07-30 05:35:03', '2026-07-30 05:35:03'),
(8, '2026-07-30', 2026, 7, 'H', '', 12966.00, 0, '', 9999, 'H', '2026-07-30 05:35:03', '2026-07-30 05:35:03'),
(9, '2026-07-30', 2026, 7, 'H', '', 10096.62, 0, '', 9999, 'H', '2026-07-30 05:35:04', '2026-07-30 05:35:04'),
(10, '2026-07-30', 2026, 7, 'H', '', 10028.00, 0, '', 9999, 'H', '2026-07-30 05:35:06', '2026-07-30 05:35:06'),
(11, '2026-07-30', 2026, 7, 'H', '', 98335.07, 0, '', 9999, 'H', '2026-07-30 05:35:07', '2026-07-30 05:35:07'),
(12, '2026-07-30', 2026, 7, 'H', '', 105031.96, 0, '', 9999, 'H', '2026-07-30 05:35:08', '2026-07-30 05:35:08'),
(13, '2026-07-30', 2026, 7, 'H', '', 18455.00, 0, '', 9999, 'H', '2026-07-30 05:35:09', '2026-07-30 05:35:09'),
(14, '2026-07-30', 2026, 7, 'H', '', 3429.67, 0, '', 9999, 'H', '2026-07-30 05:35:10', '2026-07-30 05:35:10'),
(15, '2026-07-30', 2026, 7, 'H', '', 6467.00, 0, '', 9999, 'H', '2026-07-30 05:35:13', '2026-07-30 05:35:13'),
(16, '2026-07-30', 2026, 7, 'H', '', 67110.40, 0, '', 9999, 'H', '2026-07-30 05:35:14', '2026-07-30 05:35:14'),
(17, '2026-07-30', 2026, 7, 'H', '', 67110.40, 0, '', 9999, 'H', '2026-07-30 05:35:15', '2026-07-30 05:35:15'),
(18, '2026-07-30', 2026, 7, 'H', '', 6720.00, 1, '', 9999, 'H', '2026-07-30 05:35:15', '2026-07-30 05:35:50'),
(19, '2026-07-30', 2026, 7, 'H', '', 824.00, 1, '', 9999, 'H', '2026-07-30 05:35:16', '2026-07-30 05:35:51'),
(20, '2026-07-30', 2026, 7, 'H', '', 1620.53, 1, '', 9999, 'H', '2026-07-30 05:35:16', '2026-07-30 05:36:03'),
(21, '2026-07-30', 2026, 7, 'H', '', 5162.00, 1, '', 9999, 'H', '2026-07-30 05:35:17', '2026-07-30 05:36:22'),
(22, '2026-07-30', 2026, 7, 'H', '', 16729.15, 0, '', 9999, 'H', '2026-07-30 05:35:19', '2026-07-30 05:35:19'),
(23, '2026-07-30', 2026, 7, 'H', '', 4224.86, 0, '', 9999, 'H', '2026-07-30 05:35:19', '2026-07-30 05:35:19'),
(24, '2026-07-30', 2026, 7, 'H', '', 9223.00, 0, '', 9999, 'H', '2026-07-30 05:35:20', '2026-07-30 05:35:20');

-- --------------------------------------------------------

--
-- Table structure for table `h_check_report_summary`
--

CREATE TABLE `h_check_report_summary` (
  `id` int(11) NOT NULL,
  `cr_year` int(4) NOT NULL,
  `cr_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `bank_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_check_report_summary`
--

INSERT INTO `h_check_report_summary` (`id`, `cr_year`, `cr_month`, `store_name`, `bank_balance`, `saved_by`, `updated_at`) VALUES
(1, 2026, 7, 'H', 917220.08, 'H', '2026-07-30 05:08:41');

-- --------------------------------------------------------

--
-- Table structure for table `h_cogs`
--

CREATE TABLE `h_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_cogs`
--

INSERT INTO `h_cogs` (`id`, `cogs_date`, `cogs_year`, `cogs_month`, `store_name`, `beg`, `purc`, `end_inv`, `cos`, `mktg_cost`, `saved_by`, `created_at`, `updated_at`) VALUES
(2, '2026-05-31', 2026, 5, 'H', 210008.11, 1259799.56, 269020.99, 1184250.30, 16536.38, 'H', '2026-07-30 04:16:01', '2026-07-30 04:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `h_dinein_rows`
--

CREATE TABLE `h_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_dinein_rows`
--

INSERT INTO `h_dinein_rows` (`id`, `store_name`, `report_date`, `cash`, `palawan_pay`, `card_swipe_qr`, `unpaid_credit_name`, `unpaid_credit_amount`, `discount`, `bank_transfer_cheque`, `cancelled_transactions`, `sort_order`) VALUES
(24, 'H', '2026-07-29', 250.00, 250.00, 250.00, '', 250.00, 250.00, 250.00, 250.00, 0),
(35, 'H', '2026-07-01', 31458.20, 0.00, 47212.88, '', 0.00, 989.60, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_disbursement`
--

CREATE TABLE `h_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `h_expenses`
--

CREATE TABLE `h_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_expenses`
--

INSERT INTO `h_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `selected_for_cf`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-08', '123.00', '123456789010', 'Example Company', 'Iloilo city', '1231.00', '212312.00', '12345', 200.00, 21.43, 178.57, 200.00, 378.57, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 2200.00, 0, 'H', '2026-04-08 06:02:25', '2026-04-22 05:37:40'),
(2, '2026-05-06', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 123.00, 123.00, 44.00, 44.00, 44.00, 501.00, 0, 'H', '2026-05-06 04:40:51', '2026-05-08 01:07:11'),
(3, '2026-07-29', '0', '000-144-976-00023', 'SUPERVALUE INC.', 'MANDURRIAO, ILOILO CITY', 'AJAX CLEANER LAVANDER FRESH', '0', '0', 164.75, 17.65, 147.10, 0.00, 147.10, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 147.10, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 147.10, 0, 'H', '2026-07-29 08:40:08', '2026-07-29 08:40:08'),
(4, '2026-07-29', '0', '0', 'ANNIE VIA LENCIOCO', 'ILOILO CITY', 'TRAINING SALARY', '0', '0', 0.00, 0.00, 0.00, 4280.00, 4280.00, 0.00, 4280.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 4280.00, 0, 'H', '2026-07-29 08:40:10', '2026-07-29 08:40:10'),
(5, '2026-07-29', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 0.00, 0.00, 0.00, 0.00, 123.00, 1, 'H', '2026-07-29 08:50:22', '2026-07-30 07:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `h_extra_sales_rows`
--

CREATE TABLE `h_extra_sales_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_qr` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_extra_sales_rows`
--

INSERT INTO `h_extra_sales_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount_card`, `amount_cash`, `amount_qr`, `sort_order`) VALUES
(2, 'H', '2026-07-29', 'carwash_sales', '', 1.00, 1.00, 1.00, 0),
(18, 'H', '2026-07-29', 'advance_deposit', '', 250.00, 250.00, 0.00, 0),
(19, 'H', '2026-07-29', 'bar_sales', '', 250.00, 250.00, 0.00, 0),
(40, 'H', '2026-07-01', 'advance_deposit', '', 0.00, 0.00, 0.00, 0),
(41, 'H', '2026-07-01', 'bar_sales', '', 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_income_statement`
--

CREATE TABLE `h_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'H',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_income_statement`
--

INSERT INTO `h_income_statement` (`id`, `store_name`, `stmt_date`, `stmt_year`, `stmt_month`, `stmt_day`, `stmt_label`, `net_sales`, `sales_discount`, `cost_of_sales`, `other_income_royalty`, `equipment_supplies`, `depreciation_expense`, `transportation_fuel`, `lpg`, `rent`, `water_electricity`, `drinking_water`, `pest_control_bio`, `common_area_charges`, `exhaust_cleaning`, `salaries`, `office_equipment_supplies`, `philhealth_sss`, `medical_supplies`, `agency_fees`, `bank_fees`, `staff_meal`, `representation_benefits`, `professional_fees`, `communication`, `freight_storage`, `repairs_maintenance`, `sponsorship_marketing`, `taxes_licenses`, `system_development`, `construction_progress`, `insurance`, `admin_shares`, `miscellaneous_expense`, `vat_payment`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'H', '2026-04-08', 2026, 4, 8, '', 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'H', '2026-04-08 06:07:33', '2026-04-08 06:07:33');

-- --------------------------------------------------------

--
-- Table structure for table `h_month_end_inv`
--

CREATE TABLE `h_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_month_end_inv`
--

INSERT INTO `h_month_end_inv` (`id`, `inv_date`, `inv_year`, `inv_month`, `store_name`, `category`, `sort_order`, `item_desc`, `unit`, `supplier_cost`, `end_inv_num`, `total_amount`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-06-30', 2026, 6, 'H', 'MEAT/WET PRODUCTS', 0, 'TAPA BITS', 'PACKS', 75.00, 43.0000, 3225.00, 'H', '2026-06-16 00:09:24', '2026-06-16 00:09:24'),
(2, '2026-06-30', 2026, 6, 'H', 'MEAT/WET PRODUCTS', 1, 'CHORIZO HUBAD', 'GRAMS', 50.00, 10.5000, 525.00, 'H', '2026-06-16 00:09:43', '2026-06-16 00:09:43'),
(3, '2026-06-30', 2026, 6, 'H', 'MEAT/WET PRODUCTS', 2, 'HONEY GLAZED BACON + 2packs portion', 'KG', 605.00, 3.3300, 2014.65, 'H', '2026-06-16 00:10:03', '2026-06-16 00:10:03'),
(4, '2026-06-30', 2026, 6, 'H', 'DRY GOODS', 0, 'RED CUPS', 'PCS', 5.83, 0.0000, 0.00, 'H', '2026-06-17 02:11:39', '2026-06-17 02:11:39'),
(5, '2026-06-30', 2026, 6, 'H', 'DRY GOODS', 1, 'ACRYLIC CUPS', 'PCS', 5.83, 78.0000, 454.74, 'H', '2026-06-17 02:12:52', '2026-06-17 02:12:52'),
(6, '2026-06-30', 2026, 6, 'H', 'DRY GOODS', 2, 'BROWN PAPER CUPS', 'PCS', 9.90, 30.0000, 297.00, 'H', '2026-06-17 02:12:53', '2026-06-17 02:12:53'),
(7, '2026-06-30', 2026, 6, 'H', 'DRY GOODS', 3, 'H PLASTIC CUPS 12OZ', 'PCS', 7.92, 271.0000, 2146.32, 'H', '2026-06-17 02:12:54', '2026-06-17 02:12:54'),
(8, '2026-06-30', 2026, 6, 'H', 'BAR STOCKS', 0, 'THAI TEA(CHATRA MUE) 400G', 'PACKS', 500.00, 1.0475, 523.75, 'H', '2026-06-17 02:14:19', '2026-06-17 02:14:19'),
(9, '2026-06-30', 2026, 6, 'H', 'BAR STOCKS', 1, 'JASMINE GREEN TEA 1KG', 'PACKS', 1000.00, 2.0940, 2094.00, 'H', '2026-06-17 02:14:20', '2026-06-17 02:14:20'),
(10, '2026-06-30', 2026, 6, 'H', 'BAR STOCKS', 2, 'GREEN TEA (MCCOY TEAS) 20s', 'BOX', 115.00, 4.6500, 534.75, 'H', '2026-06-17 02:14:21', '2026-06-17 02:14:21'),
(11, '2026-06-30', 2026, 6, 'H', 'VEGETABLES', 0, 'CUCUMBER', 'KILO', 120.00, 1.1000, 132.00, 'H', '2026-06-17 02:17:15', '2026-06-17 02:17:15'),
(12, '2026-06-30', 2026, 6, 'H', 'VEGETABLES', 1, 'WHITE ONION (KG)', 'KILO', 130.00, 0.1000, 13.00, 'H', '2026-06-17 02:17:16', '2026-06-17 02:17:16'),
(13, '2026-06-30', 2026, 6, 'H', 'VEGETABLES', 2, 'RED ONION (KG)', 'KILO', 120.00, 1.0400, 124.80, 'H', '2026-06-17 02:17:17', '2026-06-17 02:17:17'),
(14, '2026-06-30', 2026, 6, 'H', 'LIQUORS/BEVERAGES', 0, 'MARTINI ROSS 1L', 'BOTTLE', 1086.00, 2.0000, 2172.00, 'H', '2026-06-17 02:18:38', '2026-06-17 02:18:38'),
(15, '2026-06-30', 2026, 6, 'H', 'LIQUORS/BEVERAGES', 1, 'MARTINI EXTRA DRY 1L', 'BOTTLE', 999.00, 0.0000, 0.00, 'H', '2026-06-17 02:18:38', '2026-06-17 02:18:38'),
(16, '2026-06-30', 2026, 6, 'H', 'LIQUORS/BEVERAGES', 2, 'OLMECA TEQUILA', 'BOTTLE', 966.00, 2.0000, 1932.00, 'H', '2026-06-17 02:18:39', '2026-06-17 02:18:39'),
(17, '2026-06-30', 2026, 6, 'H', 'PASTA', 0, 'PAD THAI (200G) (RICE STICK NOODLES)', 'BOTTLE', 341.41, 8.0000, 2731.28, 'H', '2026-06-17 02:19:51', '2026-06-17 02:19:51'),
(18, '2026-06-30', 2026, 6, 'H', 'PASTA', 1, 'LAKSA (100G)', 'BOTTLE', 33.00, 5.0000, 165.00, 'H', '2026-06-17 02:19:53', '2026-06-17 02:19:53'),
(19, '2026-06-30', 2026, 6, 'H', 'PASTA', 2, 'PANCAKE', 'BOTTLE', 46.61, 8.0000, 372.88, 'H', '2026-06-17 02:19:53', '2026-06-17 02:19:53'),
(20, '2026-06-30', 2026, 6, 'H', 'CONDIMENTS', 0, 'HARDYS 750ML', 'BOTTLE', 445.00, 0.0000, 0.00, 'H', '2026-06-17 02:20:45', '2026-06-17 02:20:45'),
(21, '2026-06-30', 2026, 6, 'H', 'CONDIMENTS', 1, 'DONYA ELENA WHITE VINEGAR', 'BOTTLE', 192.50, 0.0000, 0.00, 'H', '2026-06-17 02:20:46', '2026-06-17 02:20:46'),
(22, '2026-06-30', 2026, 6, 'H', 'CONDIMENTS', 2, 'BLACK VINEGAR 640ML', 'BOTTLE', 118.50, 0.0000, 0.00, 'H', '2026-06-17 02:20:47', '2026-06-17 02:20:47'),
(23, '2026-06-30', 2026, 6, 'H', 'CONDIMENTS', 3, 'BLACK PEPPER', 'KG', 660.00, 0.2000, 132.00, 'H', '2026-06-17 02:21:14', '2026-06-17 02:21:14'),
(24, '2026-06-30', 2026, 6, 'H', 'BREAD', 0, 'BRIOCHE LOAF (180G)', 'PCS', 110.00, 1.6250, 178.75, 'H', '2026-06-17 02:22:32', '2026-06-17 02:22:32'),
(25, '2026-06-30', 2026, 6, 'H', 'BREAD', 1, 'HOTDOG BUNS', 'PCS', 12.50, 17.0000, 212.50, 'H', '2026-06-17 02:22:32', '2026-06-17 02:22:32'),
(26, '2026-06-30', 2026, 6, 'H', 'BREAD', 2, 'BREAKFAST SANDWICH', 'PCS', 12.00, 20.0000, 240.00, 'H', '2026-06-17 02:22:33', '2026-06-17 02:22:33'),
(27, '2026-06-30', 2026, 6, 'H', 'BREAD', 3, 'BURGER BUNS', 'PCS', 12.00, 12.0000, 144.00, 'H', '2026-06-17 02:22:34', '2026-06-17 02:22:34'),
(30, '2026-08-01', 2026, 8, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-22 03:53:56', '2026-07-22 03:53:56'),
(31, '2026-08-01', 2026, 8, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-22 03:53:56', '2026-07-22 03:53:56'),
(33, '2026-08-31', 2026, 8, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 1111.0000, 1111.00, 'H', '2026-07-29 06:23:29', '2026-07-29 06:23:29'),
(34, '2026-08-31', 2026, 8, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 123.0000, 15129.00, 'H', '2026-07-29 06:23:29', '2026-07-29 06:23:29'),
(35, '2026-08-31', 2026, 8, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 12.0000, 12.00, 'H', '2026-07-29 06:23:29', '2026-07-29 06:23:29'),
(37, '2026-09-30', 2026, 9, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:24:07', '2026-07-29 06:24:07'),
(38, '2026-09-30', 2026, 9, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:24:07', '2026-07-29 06:24:07'),
(46, '2026-10-31', 2026, 10, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:25:51', '2026-07-29 06:25:51'),
(47, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 1111.0000, 1111.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(48, '2026-11-30', 2026, 11, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 123.0000, 15129.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(49, '2026-11-30', 2026, 11, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 12.0000, 12.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(50, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 123.0000, 123.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(51, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 1, 'Test11', 'BOTTLE', 23.00, 123.0000, 2829.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(52, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 2, 'asdasd', 'BOTTLE', 123.00, 12312.0000, 1514376.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(53, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 4, 'asdadsasdads', 'BOTTLE', 22.00, 22.0000, 484.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(54, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 5, 'asdasdsa', 'BOTTLE', 22.00, 22.0000, 484.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(55, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 6, 'sadasssss', 'BOTTLE', 22.00, 22.0000, 484.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(56, '2026-11-30', 2026, 11, 'H', 'LIQUORS/BEVERAGES', 7, 'sdsdssssssss', 'BOTTLE', 22.00, 222.0000, 4884.00, 'H', '2026-07-29 06:26:53', '2026-07-29 06:26:53'),
(57, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(58, '2026-12-31', 2026, 12, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(59, '2026-12-31', 2026, 12, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(60, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(61, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 1, 'Test11', 'BOTTLE', 23.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(62, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 2, 'asdasd', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(63, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 4, 'asdadsasdads', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(64, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 5, 'asdasdsa', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(65, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 6, 'sadasssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(66, '2026-12-31', 2026, 12, 'H', 'LIQUORS/BEVERAGES', 7, 'sdsdssssssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:27:07', '2026-07-29 06:27:07'),
(67, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(68, '2026-05-31', 2026, 5, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(69, '2026-05-31', 2026, 5, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(70, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(71, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 1, 'Test11', 'BOTTLE', 23.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(72, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 2, 'asdasd', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(73, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 4, 'asdadsasdads', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(74, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 5, 'asdasdsa', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(75, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 6, 'sadasssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(76, '2026-05-31', 2026, 5, 'H', 'LIQUORS/BEVERAGES', 7, 'sdsdssssssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:28:44', '2026-07-29 06:28:44'),
(77, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(78, '2026-04-30', 2026, 4, 'H', 'DRY GOODS', 0, 'test2', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(79, '2026-04-30', 2026, 4, 'H', 'FISH', 0, 'test', 'PACKS', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(80, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(81, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 1, 'Test11', 'BOTTLE', 23.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(82, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 2, 'asdasd', 'BOTTLE', 123.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(83, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 4, 'asdadsasdads', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(84, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 5, 'asdasdsa', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(85, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 6, 'sadasssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16'),
(86, '2026-04-30', 2026, 4, 'H', 'LIQUORS/BEVERAGES', 7, 'sdsdssssssss', 'BOTTLE', 22.00, 0.0000, 0.00, 'H', '2026-07-29 06:29:16', '2026-07-29 06:29:16');

-- --------------------------------------------------------

--
-- Table structure for table `h_pdc`
--

CREATE TABLE `h_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_pdc`
--

INSERT INTO `h_pdc` (`id`, `date_issued`, `amount`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-05-07', 19000.00, 'H', '2026-05-07 02:44:04', '2026-05-07 02:44:04'),
(2, '2026-05-14', 122000.00, 'H', '2026-05-07 02:44:12', '2026-05-07 02:44:12');

-- --------------------------------------------------------

--
-- Table structure for table `h_pl_revenue`
--

CREATE TABLE `h_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `h_reconcile`
--

CREATE TABLE `h_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_reconcile`
--

INSERT INTO `h_reconcile` (`id`, `store_name`, `rec_year`, `rec_month`, `ending_balance_bank`, `deposits_in_transit`, `outstanding_checks`, `bank_credits`, `bank_charges`, `saved_by`, `created_at`, `updated_at`, `ending_balance_books`, `adjusted_bank_balance`, `adjusted_book_balance`) VALUES
(1, 'H', 2026, 7, 813023.19, 293859.95, 0.00, 0.00, 0.00, 'H', '2026-07-29 06:31:18', '2026-07-29 06:31:18', 0.00, 1106883.14, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `h_report_entries`
--

CREATE TABLE `h_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'H',
  `gross_sales_excl_mktg` decimal(12,2) DEFAULT 0.00 COMMENT 'Gross Sales Excl Marketing',
  `store_gross` decimal(12,2) DEFAULT 0.00 COMMENT 'Store Gross Sales (Excl SC/Mktg)',
  `z_reading_gross` decimal(12,2) DEFAULT 0.00 COMMENT 'Z Reading Gross (Incl SC/Mktg)',
  `cash_for_depo` decimal(12,2) DEFAULT 0.00 COMMENT 'Cash for Deposit',
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00 COMMENT 'Sales of the Day (Swipe)',
  `deposit_swipe` decimal(12,2) DEFAULT 0.00 COMMENT 'Deposit Swipe',
  `late_payment` decimal(12,2) DEFAULT 0.00 COMMENT 'Late Payment',
  `cancelled_transaction` decimal(12,2) DEFAULT 0.00 COMMENT 'Cancelled Transaction',
  `unpaid` decimal(12,2) DEFAULT 0.00 COMMENT 'Unpaid',
  `paid` decimal(12,2) DEFAULT 0.00 COMMENT 'Paid',
  `advance_payment` decimal(12,2) DEFAULT 0.00 COMMENT 'Advance Payment',
  `grab` decimal(12,2) DEFAULT 0.00 COMMENT 'Grab',
  `bank_trans` decimal(12,2) DEFAULT 0.00 COMMENT 'Bank Transfer',
  `gc_sponsor_marketing` decimal(12,2) DEFAULT 0.00 COMMENT 'GC Sponsor / Marketing',
  `gc_sold` decimal(12,2) DEFAULT 0.00 COMMENT 'GC Sold',
  `discount` decimal(12,2) DEFAULT 0.00 COMMENT 'Discount',
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00 COMMENT 'Marketing Pull Out',
  `personal` decimal(12,2) DEFAULT 0.00 COMMENT 'Personal',
  `expenses` decimal(12,2) DEFAULT 0.00 COMMENT 'Expenses',
  `other_expenses` decimal(12,2) DEFAULT 0.00 COMMENT 'Other Expenses',
  `sc_for_depo` decimal(12,2) DEFAULT 0.00 COMMENT 'SC for Depo',
  `total_deductions` decimal(12,2) DEFAULT 0.00 COMMENT 'Total Deductions (auto)',
  `short_over` decimal(12,2) DEFAULT 0.00 COMMENT 'Short / Over (auto)',
  `total_swipe` decimal(12,2) DEFAULT 0.00 COMMENT 'Total Swipe (user input)',
  `cash_deposit` decimal(12,2) DEFAULT 0.00 COMMENT 'Cash Deposit',
  `other_sales` decimal(12,2) DEFAULT 0.00 COMMENT 'Other Sales',
  `remarks` text DEFAULT NULL,
  `remarks2` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='H Breakfast to Bar — one flat row per day';

--
-- Dumping data for table `h_report_entries`
--

INSERT INTO `h_report_entries` (`id`, `report_date`, `store_name`, `gross_sales_excl_mktg`, `store_gross`, `z_reading_gross`, `cash_for_depo`, `sales_of_day_swipe`, `deposit_swipe`, `late_payment`, `cancelled_transaction`, `unpaid`, `paid`, `advance_payment`, `grab`, `bank_trans`, `gc_sponsor_marketing`, `gc_sold`, `discount`, `marketing_pull_out`, `personal`, `expenses`, `other_expenses`, `sc_for_depo`, `total_deductions`, `short_over`, `total_swipe`, `cash_deposit`, `other_sales`, `remarks`, `remarks2`, `saved_by`, `created_at`, `updated_at`) VALUES
(53, '2026-07-01', 'H', 88207.08, 85585.96, 88207.08, 18195.00, 47212.88, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 8436.00, 0.00, 0.00, 0.00, 989.60, 0.00, 0.00, 10173.75, 0.00, 3610.72, 66812.23, -578.73, 47212.88, 0.00, 0.00, '', '', 'H', '2026-07-28 05:16:09', '2026-07-29 07:26:08'),
(55, '2026-07-28', 'H', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', '', 'H', '2026-07-28 06:48:08', '2026-07-28 06:53:28'),
(57, '2026-07-29', 'H', 2000.00, 2000.00, 2250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 0.00, 0.00, 250.00, 250.00, 0.00, 250.00, 250.00, 250.00, 0.00, 250.00, 250.00, 250.00, 2000.00, 500.00, 750.00, 0.00, 0.00, '', '', 'H', '2026-07-29 05:01:32', '2026-07-29 05:01:32');

-- --------------------------------------------------------

--
-- Table structure for table `h_sales_detail_rows`
--

CREATE TABLE `h_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_sales_detail_rows`
--

INSERT INTO `h_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(151, 'H', '2026-07-29', 'marketing_pullout', '', 250.00, 0),
(152, 'H', '2026-07-29', 'grab', '', 250.00, 0),
(153, 'H', '2026-07-29', 'expenses', '', 250.00, 0),
(154, 'H', '2026-07-29', 'late_payment', '', 250.00, 0),
(155, 'H', '2026-07-29', 'advance_payment', '', 250.00, 0),
(156, 'H', '2026-07-29', 'gc_sponsorship', '', 250.00, 0),
(157, 'H', '2026-07-29', 'gc_sold', '', 250.00, 0),
(408, 'H', '2026-07-01', 'marketing_pullout', '', 0.00, 0),
(409, 'H', '2026-07-01', 'grab', '', 796.00, 0),
(410, 'H', '2026-07-01', 'grab', '', 974.00, 1),
(411, 'H', '2026-07-01', 'grab', '', 308.00, 2),
(412, 'H', '2026-07-01', 'grab', '', 2846.00, 3),
(413, 'H', '2026-07-01', 'grab', '', 308.00, 4),
(414, 'H', '2026-07-01', 'grab', '', 616.00, 5),
(415, 'H', '2026-07-01', 'grab', '', 974.00, 6),
(416, 'H', '2026-07-01', 'grab', '', 1026.00, 7),
(417, 'H', '2026-07-01', 'grab', '', 588.00, 8),
(418, 'H', '2026-07-01', 'expenses', '', 600.00, 0),
(419, 'H', '2026-07-01', 'expenses', '', 164.75, 1),
(420, 'H', '2026-07-01', 'expenses', '', 478.00, 2),
(421, 'H', '2026-07-01', 'expenses', '', 4280.00, 3),
(422, 'H', '2026-07-01', 'expenses', '', 69.00, 4),
(423, 'H', '2026-07-01', 'expenses', '', 1500.00, 5),
(424, 'H', '2026-07-01', 'expenses', '', 100.00, 6),
(425, 'H', '2026-07-01', 'expenses', '', 1420.00, 7),
(426, 'H', '2026-07-01', 'expenses', '', 67.00, 8),
(427, 'H', '2026-07-01', 'expenses', '', 895.00, 9),
(428, 'H', '2026-07-01', 'expenses', '', 600.00, 10),
(429, 'H', '2026-07-01', 'late_payment', '', 0.00, 0),
(430, 'H', '2026-07-01', 'advance_payment', '', 0.00, 0),
(431, 'H', '2026-07-01', 'gc_sponsorship', '', 0.00, 0),
(432, 'H', '2026-07-01', 'gc_sold', '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `h_sales_report`
--

CREATE TABLE `h_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'H',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cashier_name` varchar(150) DEFAULT NULL,
  `carwash_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bar_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `opening_cashier` varchar(150) DEFAULT NULL,
  `closing_cashier` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `h_sales_report`
--

INSERT INTO `h_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`, `cashier_name`, `carwash_sales`, `bar_sales`, `opening_cashier`, `closing_cashier`) VALUES
(16, 'H', '2026-07-28', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'H', '2026-07-28 06:56:15', '2026-07-28 07:42:11', '', 0.00, 0.00, NULL, NULL),
(19, 'H', '2026-07-29', 2000.00, 250.00, 2250.00, 750.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, 250.00, -250.00, 500.00, 'H', '2026-07-29 00:47:31', '2026-07-29 01:45:43', 'Bless', 0.00, 500.00, '', ''),
(30, 'H', '2026-07-01', 84485.96, 3610.72, 87107.08, 47212.88, 0.00, 0.00, 47212.88, 0.00, 8436.00, 0.00, 0.00, 0.00, 989.60, 0.00, 10173.75, 0.00, 18195.00, 17673.73, 521.27, 'H', '2026-07-29 05:49:03', '2026-07-29 08:18:17', NULL, 0.00, 0.00, 'Bless', 'Bless');

-- --------------------------------------------------------

--
-- Table structure for table `payables_cash_snapshot`
--

CREATE TABLE `payables_cash_snapshot` (
  `id` int(11) NOT NULL,
  `as_of_date` date DEFAULT NULL,
  `secbank_balance` decimal(14,2) DEFAULT 0.00,
  `aub_balance` decimal(14,2) DEFAULT 0.00,
  `receivable_hmo` decimal(14,2) DEFAULT 0.00,
  `receivable_tenants` decimal(14,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payables_cash_snapshot`
--

INSERT INTO `payables_cash_snapshot` (`id`, `as_of_date`, `secbank_balance`, `aub_balance`, `receivable_hmo`, `receivable_tenants`, `updated_at`) VALUES
(1, '2026-08-03', 588850.10, 709729.83, 5951719.21, 0.00, '2026-08-03 04:44:10');

-- --------------------------------------------------------

--
-- Table structure for table `payables_ledger`
--

CREATE TABLE `payables_ledger` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `item_name` varchar(255) DEFAULT '',
  `monthly_payment` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `remaining_bal` decimal(14,2) DEFAULT 0.00,
  `terms` varchar(255) DEFAULT '',
  `status` varchar(30) DEFAULT 'NOT YET PAID',
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payables_ledger`
--

INSERT INTO `payables_ledger` (`id`, `entry_date`, `item_name`, `monthly_payment`, `paid_amount`, `remaining_bal`, `terms`, `status`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-08-03', 'BANK LOAN (INCLUSIVE INTEREST)', 216388.89, 216388.89, 4544166.66, '20 MONTHS', 'PAID', '', 'DemicLab-Main', '2026-08-03 04:48:32', '2026-08-03 04:48:32'),
(2, '2026-08-03', 'RENT', 92100.06, 92100.06, 0.00, '', 'PAID', '', 'DemicLab-Main', '2026-08-03 04:49:19', '2026-08-03 04:49:19');

-- --------------------------------------------------------

--
-- Table structure for table `payables_pdc`
--

CREATE TABLE `payables_pdc` (
  `id` int(11) NOT NULL,
  `obligation_type` varchar(50) DEFAULT '',
  `due_date` date DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(14,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'NOT PAID',
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payables_pdc`
--

INSERT INTO `payables_pdc` (`id`, `obligation_type`, `due_date`, `amount`, `balance`, `status`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'RENTAL', '2026-08-04', 60000.00, 0.00, 'PAID', '', 'DemicLab-Main', '2026-08-04 03:59:15', '2026-08-04 03:59:15'),
(2, 'RENTAL', '2026-08-04', 32100.00, 0.00, 'PAID', '', 'DemicLab-Main', '2026-08-04 03:59:27', '2026-08-04 03:59:39'),
(3, 'UTZ MACHINE', '2026-08-04', 66665.00, 0.00, 'PAID', '', 'DemicLab-Main', '2026-08-04 04:02:39', '2026-08-04 04:02:39'),
(4, 'BANK LOAN', NULL, 216388.89, 0.00, 'PAID', '', 'DemicLab-Main', '2026-08-04 04:02:49', '2026-08-04 04:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `payables_pdc_sections`
--

CREATE TABLE `payables_pdc_sections` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payables_pdc_sections`
--

INSERT INTO `payables_pdc_sections` (`id`, `name`, `sort_order`, `created_at`) VALUES
(1, 'RENTAL', 0, '2026-08-04 03:56:02'),
(2, 'UTZ MACHINE', 1, '2026-08-04 03:56:02'),
(3, 'BANK LOAN', 2, '2026-08-04 03:56:02'),
(4, 'INCORPORATOR 1', 3, '2026-08-04 03:56:02'),
(5, 'INCORPORATOR 2', 4, '2026-08-04 03:56:02'),
(6, 'SGD SEND-OUTS', 5, '2026-08-04 03:56:02');

-- --------------------------------------------------------

--
-- Table structure for table `payables_releasing`
--

CREATE TABLE `payables_releasing` (
  `id` int(11) NOT NULL,
  `release_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT '',
  `amount` decimal(12,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'pending',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payables_releasing`
--

INSERT INTO `payables_releasing` (`id`, `release_date`, `description`, `amount`, `status`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-08-03', 'CYBERLINK COMPU SALES CORP.', 19350.00, 'pending', 'DemicLab-Main', '2026-08-03 04:43:11', '2026-08-03 04:43:11'),
(2, '2026-08-03', 'PITCH STRATEGIES & CONSULTANCY INC.', 24000.00, 'pending', 'DemicLab-Main', '2026-08-03 04:43:12', '2026-08-03 06:43:28'),
(3, '2026-08-03', 'NEO DX SOLUTIONS & DISTRIBUTIONS', 24552.00, 'pending', 'DemicLab-Main', '2026-08-03 04:43:12', '2026-08-03 04:43:12'),
(4, '2026-08-03', 'MARIE DOMINIQUE G. PERFECTO', 80500.00, 'pending', 'DemicLab-Main', '2026-08-03 04:43:12', '2026-08-03 04:43:12'),
(5, '2026-08-03', 'INCORPORATOR 1 NGO (CAPEX)', 355555.55, 'pending', 'DemicLab-Main', '2026-08-03 04:43:13', '2026-08-03 04:43:13'),
(6, '2026-08-03', 'RV MARKETING (3TON AC)  1 of 2', 40750.00, 'pending', 'DemicLab-Main', '2026-08-03 04:43:14', '2026-08-03 04:43:14');

-- --------------------------------------------------------

--
-- Table structure for table `payables_summary`
--

CREATE TABLE `payables_summary` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) DEFAULT '',
  `amount` decimal(12,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT '',
  `rental` decimal(12,2) DEFAULT 0.00,
  `utz` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_cogs_monitoring`
--

CREATE TABLE `pub_cogs_monitoring` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `sm_kitchen_copy` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Billing to Commi (COGS)',
  `rhea_copy` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Billing to Commi (COGS) — reference/reconciliation only',
  `rhea_copy_parcel` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Reference/reconciliation only',
  `gulay` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Store Expenses',
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(COGS)',
  `bottled_water_sodas` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(COGS) — Water ₱9 / Soda ₱36 per unit',
  `transpo_df_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
  `staff_meal_expenses` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '(NON COGS)',
  `office_supplies` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
  `dining_supplies` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Frontline Expenses (NON COGS)',
  `refund_misc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_sales_per_day` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_mp` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Manpower Cost',
  `cogs_threshold_pct` decimal(5,2) NOT NULL DEFAULT 45.00,
  `mp_threshold_pct` decimal(5,2) NOT NULL DEFAULT 7.00,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_cogs_monitoring`
--

INSERT INTO `pub_cogs_monitoring` (`id`, `entry_date`, `store_name`, `sm_kitchen_copy`, `rhea_copy`, `rhea_copy_parcel`, `gulay`, `other_expenses`, `bottled_water_sodas`, `transpo_df_expenses`, `staff_meal_expenses`, `office_supplies`, `dining_supplies`, `refund_misc`, `total_sales_per_day`, `total_mp`, `cogs_threshold_pct`, `mp_threshold_pct`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-28', 'Pub Express', 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123123123.00, 1232323.00, 45.00, 7.00, 0, 'Pub Express', '2026-07-28 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_acc_titles`
--

CREATE TABLE `pub_express_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_acc_titles`
--

INSERT INTO `pub_express_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-21 03:45:28'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-21 03:45:28'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-21 03:45:28'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-21 03:45:28'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-21 03:45:28'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-21 03:45:28'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-21 03:45:28'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-21 03:45:28'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-21 03:45:28'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-21 03:45:28'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-21 03:45:28'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-21 03:45:28'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-21 03:45:28'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-21 03:45:28'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-21 03:45:28'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-21 03:45:28'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-21 03:45:28'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-21 03:45:28'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-21 03:45:28'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-21 03:45:28'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-21 03:45:28'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-21 03:45:28'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-21 03:45:28'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-21 03:45:28'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-21 03:45:28'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-21 03:45:28'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-21 03:45:28'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-21 03:45:28'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-21 03:45:28'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-21 03:45:28'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-21 03:45:28'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-21 03:45:28'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-21 03:45:28'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-21 03:45:28'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-21 03:45:28'),
(36, 'Water', 'expenses', 35, 'Pub Express', '2026-07-21 03:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_cashflow`
--

CREATE TABLE `pub_express_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_cashflow_balance`
--

CREATE TABLE `pub_express_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_cf_vat_selection`
--

CREATE TABLE `pub_express_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_cf_vat_selection`
--

INSERT INTO `pub_express_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'Pub Express', 2026, 7, 0.00, 0, 'Pub Express', '2026-07-07 07:26:26');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_cogs`
--

CREATE TABLE `pub_express_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_dinein_rows`
--

CREATE TABLE `pub_express_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_disbursement`
--

CREATE TABLE `pub_express_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_disbursement`
--

INSERT INTO `pub_express_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(2, '2026-07-21', '432-281-006-133', 'CEBU OVEN MAGIC CORPORATION', 'VAT', 'MAYON STREET STA TERESITA QUEZON CITY', '', 'Water', 1233.00, 132.11, 1100.89, '', 'Pub Express', '2026-07-21 03:46:16', '2026-07-21 03:46:16');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_expenses`
--

CREATE TABLE `pub_express_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_expenses`
--

INSERT INTO `pub_express_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `selected_for_cf`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-10', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 123.00, 123.00, 123.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 492.00, 0, 'Pub Express', '2026-04-10 02:09:38', '2026-04-10 02:09:38'),
(2, '2026-05-06', '0', '0', '0', '0', '0', '0', '0', 2000.00, 214.29, 1785.71, 0.00, 1785.71, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 123.00, 123.00, 123.00, 123.00, 0.00, 0.00, 0.00, 0.00, 0.00, 212.00, 123.00, 222.00, 2223.00, 4444.00, 44.00, 7760.00, 0, 'Pub Express', '2026-05-06 03:16:51', '2026-05-08 01:07:53');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_inv_breakdown`
--

CREATE TABLE `pub_express_inv_breakdown` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `group_name` varchar(200) NOT NULL DEFAULT '',
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `per_serving` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `op` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `sales_op` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `sales_grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `bd_op` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `bd_grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_sold_out` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `converted_dozen` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_out` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_inv_breakdown`
--

INSERT INTO `pub_express_inv_breakdown` (`id`, `inv_date`, `store_name`, `group_name`, `item_name`, `sort_order`, `per_serving`, `op`, `grab`, `sales_op`, `sales_grab`, `bd_op`, `bd_grab`, `total_sold_out`, `converted_dozen`, `total_out`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-31', 'Pub Express', 'GARLIC CHICKEN', 'ALA CART', 1, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-14 04:12:32'),
(2, '2026-07-31', 'Pub Express', 'GARLIC CHICKEN', 'BAKA', 2, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-15 07:41:55'),
(3, '2026-07-31', 'Pub Express', 'GARLIC CHICKEN', 'SPOILAGE', 3, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-14 04:12:32'),
(4, '2026-07-31', 'Pub Express', 'PORK BBQ', 'ALAKART 1PC', 6, 1.0000, 50.0000, 0.0000, 150.0000, 0.0000, 3.0000, 0.0000, 3.0000, 3.0000, 3.0000, 'Pub Express', '2026-07-15 07:45:10'),
(5, '2026-07-31', 'Pub Express', 'PORK BBQ', 'ALAKART 3PC', 7, 3.0000, 150.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-15 07:45:11'),
(6, '2026-07-31', 'Pub Express', 'PORK BBQ', 'MAIN CORUSE 3PC', 8, 3.0000, 0.0000, 0.0000, 0.0000, 0.0000, 17.0000, 2.0000, 19.0000, 6.3333, 57.0000, 'Pub Express', '2026-07-15 07:45:12'),
(7, '2026-07-31', 'Pub Express', 'PORK BBQ', 'SPOILAGE', 9, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 7.0000, 0.0000, 7.0000, 7.0000, 7.0000, 'Pub Express', '2026-07-15 07:45:13'),
(8, '2026-08-01', 'Pub Express', 'GARLIC CHICKEN', 'ALA CART', 1, 2.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(9, '2026-08-01', 'Pub Express', 'GARLIC CHICKEN', 'BAKA', 2, 2.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(10, '2026-08-01', 'Pub Express', 'GARLIC CHICKEN', 'SPOILAGE', 3, 2.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(11, '2026-08-01', 'Pub Express', 'PORK BBQ', 'ALAKART 1PC', 6, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(12, '2026-08-01', 'Pub Express', 'PORK BBQ', 'ALAKART 3PC', 7, 3.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(13, '2026-08-01', 'Pub Express', 'PORK BBQ', 'MAIN CORUSE 3PC', 8, 3.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(14, '2026-08-01', 'Pub Express', 'PORK BBQ', 'SPOILAGE', 9, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(15, '2026-07-02', 'Pub Express', 'GARLIC CHICKEN', 'ALA CART', 1, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-28 04:21:16'),
(16, '2026-07-02', 'Pub Express', 'GARLIC CHICKEN', 'BAKA', 2, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-28 04:21:16'),
(17, '2026-07-02', 'Pub Express', 'GARLIC CHICKEN', 'SPOILAGE', 3, 2.0000, 2.0000, 2.0000, 4.0000, 4.0000, 2.0000, 2.0000, 4.0000, 2.0000, 8.0000, 'Pub Express', '2026-07-28 04:21:16'),
(18, '2026-07-02', 'Pub Express', 'PORK BBQ', 'ALAKART 1PC', 6, 1.0000, 50.0000, 0.0000, 150.0000, 0.0000, 3.0000, 0.0000, 3.0000, 3.0000, 3.0000, 'Pub Express', '2026-07-28 04:21:16'),
(19, '2026-07-02', 'Pub Express', 'PORK BBQ', 'ALAKART 3PC', 7, 3.0000, 150.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:21:16'),
(20, '2026-07-02', 'Pub Express', 'PORK BBQ', 'MAIN CORUSE 3PC', 8, 3.0000, 0.0000, 0.0000, 0.0000, 0.0000, 17.0000, 2.0000, 19.0000, 6.3333, 57.0000, 'Pub Express', '2026-07-28 04:21:16'),
(21, '2026-07-02', 'Pub Express', 'PORK BBQ', 'SPOILAGE', 9, 1.0000, 0.0000, 0.0000, 0.0000, 0.0000, 7.0000, 0.0000, 7.0000, 7.0000, 7.0000, 'Pub Express', '2026-07-28 04:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_inv_main`
--

CREATE TABLE `pub_express_inv_main` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `op` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `bb` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `del` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_sold_op` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_sold_grab` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_out` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `ending` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `op_sale` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_ending` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `variances` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_inv_main`
--

INSERT INTO `pub_express_inv_main` (`id`, `inv_date`, `store_name`, `item_name`, `sort_order`, `op`, `grab`, `bb`, `del`, `qty_sold_op`, `qty_sold_grab`, `total_out`, `ending`, `op_sale`, `grab_sales`, `actual_ending`, `variances`, `saved_by`, `updated_at`) VALUES
(8, '2026-07-31', 'Pub Express', 'WHOLE RIBS', 0, 349.0000, 349.0000, 13.0000, 5.0000, 7.0000, 1.0000, 8.0000, 708.0000, 2443.00, 349.00, 7.0000, -701.0000, 'Pub Express', '2026-07-14 06:03:53'),
(9, '2026-07-31', 'Pub Express', 'HALF RIBS', 1, 249.0000, 249.0000, 23.0000, 20.0000, 28.0000, 1.0000, 29.0000, 512.0000, 6972.00, 249.00, 10.0000, -502.0000, 'Pub Express', '2026-07-15 07:47:16'),
(10, '2026-08-01', 'Pub Express', 'WHOLE RIBS', 0, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.00, 0.00, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(11, '2026-08-01', 'Pub Express', 'HALF RIBS', 1, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.00, 0.00, 0.0000, 0.0000, 'Pub Express', '2026-07-28 04:18:49'),
(12, '2026-07-02', 'Pub Express', 'WHOLE RIBS', 0, 349.0000, 349.0000, 13.0000, 5.0000, 7.0000, 1.0000, 8.0000, 708.0000, 2443.00, 349.00, 7.0000, -701.0000, 'Pub Express', '2026-07-28 04:21:16'),
(13, '2026-07-02', 'Pub Express', 'HALF RIBS', 1, 249.0000, 249.0000, 23.0000, 20.0000, 28.0000, 1.0000, 29.0000, 512.0000, 6972.00, 249.00, 10.0000, -502.0000, 'Pub Express', '2026-07-28 04:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_inv_summary`
--

CREATE TABLE `pub_express_inv_summary` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_inv_summary`
--

INSERT INTO `pub_express_inv_summary` (`id`, `inv_date`, `store_name`, `discount`, `saved_by`, `updated_at`) VALUES
(1, '2026-07-31', 'Pub Express', 1482.01, 'Pub Express', '2026-07-14 06:04:05'),
(4, '2026-07-02', 'Pub Express', 1482.01, 'Pub Express', '2026-07-28 04:21:16');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_month_end_inv`
--

CREATE TABLE `pub_express_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_month_end_inv`
--

INSERT INTO `pub_express_month_end_inv` (`id`, `inv_date`, `inv_year`, `inv_month`, `store_name`, `category`, `sort_order`, `item_desc`, `unit`, `supplier_cost`, `end_inv_num`, `total_amount`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-06-30', 2026, 6, 'Pub Express', 'CONDIMENTS', 0, 'BLACK PEPPER', 'KG', 660.00, 0.2000, 132.00, 'Pub Express', '2026-06-16 00:11:25', '2026-06-16 00:11:25'),
(2, '2026-06-30', 2026, 6, 'Pub Express', 'CONDIMENTS', 1, 'SLICE JALAPENOS 335G', 'BOTTLE', 189.00, 0.2500, 47.25, 'Pub Express', '2026-06-16 00:11:43', '2026-06-16 00:11:43'),
(3, '2026-07-31', 2026, 7, 'Pub Express', 'LIQUORS/BEVERAGES', 0, 'test', 'BOTTLE', 1.00, 1000.0000, 1000.00, 'Pub Express', '2026-07-01 09:58:35', '2026-07-01 09:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_pdc`
--

CREATE TABLE `pub_express_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_pl_revenue`
--

CREATE TABLE `pub_express_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_reconcile`
--

CREATE TABLE `pub_express_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_sales_detail_rows`
--

CREATE TABLE `pub_express_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_sales_detail_rows`
--

INSERT INTO `pub_express_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(36, 'Pub Express', '2026-07-15', 'denomination', '1000', 43.00, 0),
(37, 'Pub Express', '2026-07-15', 'denomination', '500', 32.00, 1),
(38, 'Pub Express', '2026-07-15', 'denomination', '5', 9.00, 2),
(39, 'Pub Express', '2026-07-15', 'denomination', '1', 15.00, 3),
(40, 'Pub Express', '2026-07-15', 'marketing_pullout', '', 0.00, 0),
(41, 'Pub Express', '2026-07-15', 'unpaids', '', 0.00, 0),
(42, 'Pub Express', '2026-07-15', 'expenses', '', 312.68, 0),
(43, 'Pub Express', '2026-07-15', 'expenses', '', 283.75, 1),
(44, 'Pub Express', '2026-07-15', 'expenses', '', 1415.71, 2),
(45, 'Pub Express', '2026-07-15', 'expenses', '', 400.00, 3),
(46, 'Pub Express', '2026-07-15', 'expenses', '', 180.00, 4),
(47, 'Pub Express', '2026-07-15', 'expenses', '', 225.00, 5);

-- --------------------------------------------------------

--
-- Table structure for table `pub_express_sales_report`
--

CREATE TABLE `pub_express_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Pub Express',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `undeclared` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pub_express_sales_report`
--

INSERT INTO `pub_express_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `undeclared`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'Pub Express', '2026-07-15', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2817.14, 0.00, 59060.00, -2817.14, 61877.14, 'Pub Express', '2026-07-15 07:00:54', '2026-07-15 08:02:51');

-- --------------------------------------------------------

--
-- Table structure for table `pub_income_statement`
--

CREATE TABLE `pub_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Pub Express',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pub_report_entries`
--

CREATE TABLE `pub_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL COMMENT 'A — Date of report',
  `store_name` varchar(100) NOT NULL DEFAULT 'Pub Express',
  `collected_pos` decimal(12,2) DEFAULT 0.00 COMMENT 'B — Collected (POS)',
  `discount` decimal(12,2) DEFAULT 0.00 COMMENT 'C — Discount',
  `total_collected` decimal(12,2) DEFAULT 0.00 COMMENT 'D — Total Collected = B + C',
  `uncollected_grab` decimal(12,2) DEFAULT 0.00 COMMENT 'E — Uncollected (Manual OS Grab)',
  `gross_sales` decimal(12,2) DEFAULT 0.00 COMMENT 'F — Gross Sales = D + E',
  `total_cash_deposit` decimal(12,2) DEFAULT 0.00 COMMENT 'G — Total Cash / Deposit',
  `gcash` decimal(12,2) DEFAULT 0.00 COMMENT 'H — GCash',
  `swiper` decimal(12,2) DEFAULT 0.00 COMMENT 'I — Swiper',
  `payroll_ca` decimal(12,2) DEFAULT 0.00 COMMENT 'J — Payroll / CA',
  `marketing` decimal(12,2) DEFAULT 0.00 COMMENT 'K — Marketing',
  `unpaid_mam_nikki` decimal(12,2) DEFAULT 0.00 COMMENT 'L — Unpaid Ma''am Nikki',
  `unpaids` decimal(12,2) DEFAULT 0.00 COMMENT 'M — Unpaids',
  `direct_purchases` decimal(12,2) DEFAULT 0.00 COMMENT 'N — Direct Purchases',
  `pcf` decimal(12,2) DEFAULT 0.00 COMMENT 'O — PCF',
  `grab` decimal(12,2) DEFAULT 0.00 COMMENT 'P — Grab',
  `personal_mam_nikki` decimal(12,2) DEFAULT 0.00 COMMENT 'Q — Personal Ma''am Nikki',
  `short_over` decimal(12,2) DEFAULT 0.00 COMMENT 'R — (Short)/Over = G − (J+K+L+M+N+O+P+Q)',
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Pub Express daily summary entries — mirrors PUB SM Excel sheet';

--
-- Dumping data for table `pub_report_entries`
--

INSERT INTO `pub_report_entries` (`id`, `report_date`, `store_name`, `collected_pos`, `discount`, `total_collected`, `uncollected_grab`, `gross_sales`, `total_cash_deposit`, `gcash`, `swiper`, `payroll_ca`, `marketing`, `unpaid_mam_nikki`, `unpaids`, `direct_purchases`, `pcf`, `grab`, `personal_mam_nikki`, `short_over`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-03-01', 'Pub Express', 14881.91, 210.08, 15091.99, 5689.00, 20780.99, 15112.00, 123.00, 3322.00, 123.00, 123.00, 123.00, 123.00, 1548.78, 90.00, 589.00, 123.00, 828.87, '', 'Pub Express', '2026-03-31 01:20:12', '2026-03-31 01:20:12'),
(2, '2026-04-01', 'Pub Express', 0.00, 123.00, 123.00, 123.00, 246.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, -123.00, '', 'Pub Express', '2026-04-14 01:37:48', '2026-04-14 01:37:48');

-- --------------------------------------------------------

--
-- Table structure for table `receivables_ledger`
--

CREATE TABLE `receivables_ledger` (
  `id` int(11) NOT NULL,
  `client_name` varchar(255) DEFAULT '',
  `billing_date` date DEFAULT NULL,
  `soa_number` varchar(100) DEFAULT '',
  `soa_sent_date` date DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT 0.00,
  `check_no` varchar(60) DEFAULT '',
  `check_amount` decimal(14,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `taxes_withheld` decimal(14,2) DEFAULT 0.00,
  `less_1118` decimal(14,2) DEFAULT 0.00,
  `tax` decimal(14,2) DEFAULT 0.00,
  `paid_amount` decimal(14,2) DEFAULT 0.00,
  `remaining_balance` decimal(14,2) DEFAULT 0.00,
  `aging` varchar(60) DEFAULT '',
  `surcharge` decimal(14,2) DEFAULT 0.00,
  `rebate` decimal(14,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receivables_ledger`
--

INSERT INTO `receivables_ledger` (`id`, `client_name`, `billing_date`, `soa_number`, `soa_sent_date`, `amount`, `check_no`, `check_amount`, `payment_date`, `taxes_withheld`, `less_1118`, `tax`, `paid_amount`, `remaining_balance`, `aging`, `surcharge`, `rebate`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'CARITAS', '2026-08-03', '', NULL, 4850.00, '', 0.00, '2026-08-03', 4753.00, 0.00, 0.00, 200.00, 0.00, '', 0.00, 0.00, '', 'DemicLab-Main', '2026-08-03 06:11:17', '2026-08-03 06:11:17'),
(2, 'COCOLIFE', '2023-05-05', '', NULL, 3110.00, '', 0.00, '2026-08-04', 3047.80, 0.00, 0.00, 3047.80, 0.00, '', 0.00, 0.00, '', 'DemicLab-Main', '2026-08-03 06:12:44', '2026-08-03 06:13:24');

-- --------------------------------------------------------

--
-- Table structure for table `receivables_monitoring`
--

CREATE TABLE `receivables_monitoring` (
  `id` int(11) NOT NULL,
  `hmo_name` varchar(255) DEFAULT '',
  `entry_date` date DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT 0.00,
  `remaining_balance` decimal(14,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receivables_monitoring`
--

INSERT INTO `receivables_monitoring` (`id`, `hmo_name`, `entry_date`, `amount`, `remaining_balance`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'CARITAS', '2026-08-03', 10000.00, 500.00, '', 'DemicLab-Main', '2026-08-03 06:10:06', '2026-08-03 06:10:06'),
(2, 'COCOLIFE', '2026-08-03', 2000.00, 500.00, '', 'DemicLab-Main', '2026-08-03 06:10:18', '2026-08-03 06:10:18');

-- --------------------------------------------------------

--
-- Table structure for table `receivables_others`
--

CREATE TABLE `receivables_others` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT '',
  `billing_date` date DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT 0.00,
  `payment_date` date DEFAULT NULL,
  `taxes_withheld` decimal(14,2) DEFAULT 0.00,
  `paid_amount` decimal(14,2) DEFAULT 0.00,
  `remaining_balance` decimal(14,2) DEFAULT 0.00,
  `aging` varchar(60) DEFAULT '',
  `surcharge` decimal(14,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receivables_pastdue`
--

CREATE TABLE `receivables_pastdue` (
  `id` int(11) NOT NULL,
  `hmo_name` varchar(255) DEFAULT '',
  `entry_date` date DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT 'NOT PAID',
  `remarks` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `receivables_soatracker`
--

CREATE TABLE `receivables_soatracker` (
  `id` int(11) NOT NULL,
  `soa_number` varchar(50) DEFAULT '',
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `receivables_soatracker`
--

INSERT INTO `receivables_soatracker` (`id`, `soa_number`, `used`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'SN-C00001', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(2, 'SN-C00002', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(3, 'SN-C00003', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(4, 'SN-C00004', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(5, 'SN-C00005', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(6, 'SN-C00006', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(7, 'SN-C00007', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(8, 'SN-C00008', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(9, 'SN-C00009', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(10, 'SN-C00010', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(11, 'SN-C00011', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(12, 'SN-C00012', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(13, 'SN-C00013', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(14, 'SN-C00014', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(15, 'SN-C00015', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(16, 'SN-C00016', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(17, 'SN-C00017', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(18, 'SN-C00018', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(19, 'SN-C00019', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(20, 'SN-C00020', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(21, 'SN-C00021', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(22, 'SN-C00022', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(23, 'SN-C00023', 1, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(24, 'SN-C00024', 0, 'DemicLab-Main', '2026-08-04 04:25:04', '2026-08-04 05:54:27'),
(25, 'SN-C00025', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(26, 'SN-C00026', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(27, 'SN-C00027', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(28, 'SN-C00028', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(29, 'SN-C00029', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(30, 'SN-C00030', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(31, 'SN-C00031', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(32, 'SN-C00032', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(33, 'SN-C00033', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(34, 'SN-C00034', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(35, 'SN-C00035', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(36, 'SN-C00036', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(37, 'SN-C00037', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(38, 'SN-C00038', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(39, 'SN-C00039', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(40, 'SN-C00040', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(41, 'SN-C00041', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(42, 'SN-C00042', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(43, 'SN-C00043', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(44, 'SN-C00044', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(45, 'SN-C00045', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(46, 'SN-C00046', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(47, 'SN-C00047', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(48, 'SN-C00048', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(49, 'SN-C00049', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(50, 'SN-C00050', 0, NULL, '2026-08-04 04:25:04', '2026-08-04 04:25:04'),
(51, 'SN-C00051', 0, 'DemicLab-Main', '2026-08-04 04:25:17', '2026-08-04 04:25:17'),
(52, 'SN-C00052', 0, 'DemicLab-Main', '2026-08-04 04:25:23', '2026-08-04 04:25:23'),
(53, 'SN-C00053', 0, 'DemicLab-Main', '2026-08-04 05:54:18', '2026-08-04 05:54:18');

-- --------------------------------------------------------

--
-- Table structure for table `receivables_summary`
--

CREATE TABLE `receivables_summary` (
  `id` int(11) NOT NULL,
  `hmo_name` varchar(255) DEFAULT '',
  `amount` decimal(14,2) DEFAULT 0.00,
  `ewt_tax` decimal(14,2) DEFAULT 0.00,
  `receivables_after_ew` decimal(14,2) DEFAULT 0.00,
  `surcharge` decimal(14,2) DEFAULT 0.00,
  `payment` decimal(14,2) DEFAULT 0.00,
  `balance` decimal(14,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT '',
  `sort_order` int(11) DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_acc_titles`
--

CREATE TABLE `recovery_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_acc_titles`
--

INSERT INTO `recovery_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-20 07:38:23'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-20 07:38:23'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-20 07:38:23'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-20 07:38:23'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-20 07:38:23'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-20 07:38:23'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-20 07:38:24'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-20 07:38:24'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-20 07:38:24'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-20 07:38:24'),
(11, 'Spa Supplies', 'expenses', 10, 'Recovery', '2026-07-20 07:38:24'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-20 07:38:24'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-20 07:38:24'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-20 07:38:24'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-20 07:38:24'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-20 07:38:24'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-20 07:38:24'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-20 07:38:24'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-20 07:38:24'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-20 07:38:24'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-20 07:38:24'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-20 07:38:24'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-20 07:38:24'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-20 07:38:24'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-20 07:38:24'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-20 07:38:24'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-20 07:38:24'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-20 07:38:24'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-20 07:38:24'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-20 07:38:24'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-20 07:38:24'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-20 07:38:24'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-20 07:38:24'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-20 07:38:24'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-20 07:38:24'),
(37, 'Water', 'expenses', 35, 'Recovery', '2026-07-20 08:14:14');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_cashflow`
--

CREATE TABLE `recovery_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_cashflow_balance`
--

CREATE TABLE `recovery_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_cashflow_balance`
--

INSERT INTO `recovery_cashflow_balance` (`id`, `store_name`, `txn_date`, `description`, `cash_in`, `cash_out`, `entry_year`, `entry_month`, `created_by`, `created_at`) VALUES
(2, 'Recovery', '2026-07-20', 'tEST', 1000.00, 0.00, 2026, 7, 'Recovery', '2026-07-20 07:08:33'),
(3, 'Recovery', NULL, 'tEST', 0.00, 500.00, 2026, 7, 'Recovery', '2026-07-20 07:08:41'),
(4, 'Recovery', '2026-07-22', 'TEST', 1000.00, 0.00, 2026, 7, 'Recovery', '2026-07-20 07:09:02');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_cash_breakdown`
--

CREATE TABLE `recovery_cash_breakdown` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `denomination` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty` int(6) NOT NULL DEFAULT 0,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_cash_breakdown`
--

INSERT INTO `recovery_cash_breakdown` (`id`, `store_name`, `report_date`, `denomination`, `qty`, `sort_order`) VALUES
(34, 'Recovery', '2026-07-10', 1000.00, 0, 0),
(35, 'Recovery', '2026-07-10', 500.00, 0, 1),
(36, 'Recovery', '2026-07-10', 200.00, 0, 2),
(37, 'Recovery', '2026-07-10', 100.00, 0, 3),
(38, 'Recovery', '2026-07-10', 50.00, 0, 4),
(39, 'Recovery', '2026-07-10', 20.00, 0, 5),
(40, 'Recovery', '2026-07-10', 10.00, 0, 6),
(41, 'Recovery', '2026-07-10', 5.00, 0, 7),
(42, 'Recovery', '2026-07-10', 1.00, 0, 8),
(43, 'Recovery', '2026-07-10', 0.50, 0, 9),
(44, 'Recovery', '2026-07-10', 0.10, 0, 10),
(45, 'Recovery', '2026-07-10', 0.05, 0, 11),
(46, 'Recovery', '2026-07-10', 1000.00, 0, 12),
(47, 'Recovery', '2026-07-10', 500.00, 0, 13),
(48, 'Recovery', '2026-07-10', 200.00, 0, 14),
(49, 'Recovery', '2026-07-10', 100.00, 0, 15),
(50, 'Recovery', '2026-07-10', 50.00, 0, 16),
(51, 'Recovery', '2026-07-10', 20.00, 0, 17),
(52, 'Recovery', '2026-07-10', 10.00, 0, 18),
(53, 'Recovery', '2026-07-10', 5.00, 0, 19),
(54, 'Recovery', '2026-07-10', 1.00, 0, 20),
(55, 'Recovery', '2026-07-17', 1000.00, 1, 0),
(56, 'Recovery', '2026-07-17', 500.00, 1, 1),
(57, 'Recovery', '2026-07-17', 200.00, 0, 2),
(58, 'Recovery', '2026-07-17', 100.00, 0, 3),
(59, 'Recovery', '2026-07-17', 50.00, 0, 4),
(60, 'Recovery', '2026-07-17', 20.00, 0, 5),
(61, 'Recovery', '2026-07-17', 10.00, 5, 6),
(62, 'Recovery', '2026-07-17', 5.00, 4, 7),
(63, 'Recovery', '2026-07-17', 1.00, 1, 8),
(64, 'Recovery', '2026-07-17', 0.50, 0, 9),
(65, 'Recovery', '2026-07-17', 0.10, 0, 10),
(66, 'Recovery', '2026-07-17', 0.05, 0, 11),
(67, 'Recovery', '2026-07-18', 1000.00, 0, 0),
(68, 'Recovery', '2026-07-18', 500.00, 0, 1),
(69, 'Recovery', '2026-07-18', 200.00, 0, 2),
(70, 'Recovery', '2026-07-18', 100.00, 0, 3),
(71, 'Recovery', '2026-07-18', 50.00, 0, 4),
(72, 'Recovery', '2026-07-18', 20.00, 0, 5),
(73, 'Recovery', '2026-07-18', 10.00, 0, 6),
(74, 'Recovery', '2026-07-18', 5.00, 0, 7),
(75, 'Recovery', '2026-07-18', 1.00, 0, 8),
(76, 'Recovery', '2026-07-18', 0.50, 0, 9),
(77, 'Recovery', '2026-07-18', 0.10, 0, 10),
(78, 'Recovery', '2026-07-18', 0.05, 0, 11),
(385, 'Recovery', '2026-07-20', 1000.00, 0, 0),
(386, 'Recovery', '2026-07-20', 500.00, 0, 1),
(387, 'Recovery', '2026-07-20', 200.00, 0, 2),
(388, 'Recovery', '2026-07-20', 100.00, 0, 3),
(389, 'Recovery', '2026-07-20', 50.00, 0, 4),
(390, 'Recovery', '2026-07-20', 20.00, 0, 5),
(391, 'Recovery', '2026-07-20', 10.00, 0, 6),
(392, 'Recovery', '2026-07-20', 5.00, 0, 7),
(393, 'Recovery', '2026-07-20', 1.00, 0, 8),
(394, 'Recovery', '2026-07-20', 0.50, 0, 9),
(395, 'Recovery', '2026-07-20', 0.10, 0, 10),
(396, 'Recovery', '2026-07-20', 0.05, 0, 11),
(397, 'Recovery', '2026-07-20', 1000.00, 0, 12),
(398, 'Recovery', '2026-07-20', 500.00, 1, 13),
(399, 'Recovery', '2026-07-20', 200.00, 0, 14),
(400, 'Recovery', '2026-07-20', 100.00, 2, 15),
(401, 'Recovery', '2026-07-20', 50.00, 1, 16),
(402, 'Recovery', '2026-07-20', 20.00, 0, 17),
(403, 'Recovery', '2026-07-20', 10.00, 0, 18),
(404, 'Recovery', '2026-07-20', 5.00, 0, 19),
(405, 'Recovery', '2026-07-20', 1.00, 3, 20),
(418, 'Recovery', '2026-07-21', 1000.00, 1, 0),
(419, 'Recovery', '2026-07-21', 500.00, 1, 1),
(420, 'Recovery', '2026-07-21', 200.00, 0, 2),
(421, 'Recovery', '2026-07-21', 100.00, 0, 3),
(422, 'Recovery', '2026-07-21', 50.00, 0, 4),
(423, 'Recovery', '2026-07-21', 20.00, 0, 5),
(424, 'Recovery', '2026-07-21', 10.00, 5, 6),
(425, 'Recovery', '2026-07-21', 5.00, 4, 7),
(426, 'Recovery', '2026-07-21', 1.00, 1, 8),
(427, 'Recovery', '2026-07-21', 0.50, 0, 9),
(428, 'Recovery', '2026-07-21', 0.10, 0, 10),
(429, 'Recovery', '2026-07-21', 0.05, 0, 11);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_categories`
--

CREATE TABLE `recovery_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_categories`
--

INSERT INTO `recovery_categories` (`id`, `store_name`, `name`, `sort_order`, `created_at`) VALUES
(11, 'Recovery', 'LASH', 0, '2026-07-17 09:15:32');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_categories_meta`
--

CREATE TABLE `recovery_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_categories_meta`
--

INSERT INTO `recovery_categories_meta` (`store_name`, `seeded`) VALUES
('Recovery', 1);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_cf_vat_selection`
--

CREATE TABLE `recovery_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_cf_vat_selection`
--

INSERT INTO `recovery_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'Recovery', 2026, 7, 0.00, 0, 'Recovery', '2026-07-09 08:20:49');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_cogs`
--

CREATE TABLE `recovery_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_commission_fees`
--

CREATE TABLE `recovery_commission_fees` (
  `id` int(11) NOT NULL,
  `service` varchar(200) NOT NULL DEFAULT '',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fix_cf` decimal(10,2) NOT NULL DEFAULT 0.00,
  `at_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_commission_fees`
--

INSERT INTO `recovery_commission_fees` (`id`, `service`, `price`, `fix_cf`, `at_cost`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, 'Brow Shaping (Wax or Thread)', 199.00, 30.00, 0.00, 0, 'Recovery', '2026-07-23 07:48:27'),
(2, 'Hotel or Home Service Fee', 300.00, 30.00, 0.00, 1, 'Recovery', '2026-07-23 07:48:27'),
(3, 'Lash Removal', 250.00, 30.00, 0.00, 2, 'Recovery', '2026-07-23 07:48:27'),
(4, 'Gel Removal', 200.00, 30.00, 0.00, 3, 'Recovery', '2026-07-23 07:48:28'),
(5, 'Softgel Removal', 300.00, 30.00, 0.00, 4, 'Recovery', '2026-07-23 07:48:28'),
(6, 'Lash Removal', 300.00, 30.00, 0.00, 5, 'Recovery', '2026-07-23 07:48:28'),
(7, 'Nail Extension Simple', 999.00, 100.00, 52.72, 6, 'Recovery', '2026-07-20 05:24:56'),
(8, 'Basic Manicure', 239.00, 50.00, 15.47, 7, 'Recovery', '2026-07-20 05:24:55'),
(9, 'Scalp Srub', 479.00, 50.00, 14.38, 8, 'Recovery', '2026-07-20 06:43:07'),
(10, 'Nails Extenson Removal', 300.00, 30.00, 44.75, 9, 'Recovery', '2026-07-20 06:43:40'),
(11, 'Reg Foot Spa', 399.00, 50.00, 9.27, 11, 'Recovery', '2026-07-23 07:48:28'),
(12, 'Foot Spa + Foot Scrub', 499.00, 50.00, 11.35, 13, 'Recovery', '2026-07-23 07:48:28'),
(13, 'Gel Manicure', 399.00, 50.00, 55.41, 10, 'Recovery', '2026-07-23 07:48:28'),
(14, 'Lash Lift', 490.00, 50.00, 31.90, 12, 'Recovery', '2026-07-23 07:48:28'),
(15, 'Brow Tinting', 249.00, 50.00, 11.19, 14, 'Recovery', '2026-07-20 06:46:36'),
(16, 'Brow Lamination', 599.00, 50.00, 9.29, 15, 'Recovery', '2026-07-20 06:48:36'),
(17, 'Brow Shaping + Tint Package', 399.00, 50.00, 39.19, 16, 'Recovery', '2026-07-20 06:49:25'),
(18, 'Milk + Honey Scrub', 599.00, 100.00, 38.84, 17, 'Recovery', '2026-07-20 06:49:45'),
(19, 'BASIC FACIAL', 499.00, 50.00, 21.58, 18, 'Recovery', '2026-07-20 06:50:58'),
(20, 'Foot massage', 250.00, 50.00, 14.79, 19, 'Recovery', '2026-07-20 06:51:20'),
(21, 'Foot Mask', 350.00, 50.00, 0.00, 20, 'Recovery', '2026-07-20 06:52:01'),
(22, 'Nail Extension Simple', 999.00, 100.00, 52.72, 21, 'Recovery', '2026-07-20 06:52:22'),
(23, 'Nail Extension PD', 1500.00, 100.00, 0.00, 22, 'Recovery', '2026-07-20 06:52:38'),
(24, 'Foot Spa + Foot Scrub+ Foot Massage', 649.00, 100.00, 14.79, 23, 'Recovery', '2026-07-20 06:53:02'),
(25, 'Classic Lash Extensions', 699.00, 100.00, 113.41, 24, 'Recovery', '2026-07-20 06:54:52'),
(26, 'Cat Eye/Wispy Lash Ext', 799.00, 100.00, 131.98, 25, 'Recovery', '2026-07-20 06:55:32'),
(27, 'Semi Glam', 899.00, 100.00, 131.41, 26, 'Recovery', '2026-07-21 10:31:58'),
(28, 'Full Glam', 999.00, 100.00, 100.17, 27, 'Recovery', '2026-07-21 10:32:46'),
(29, 'Brow Lamination + Tint Package', 749.00, 100.00, 20.48, 28, 'Recovery', '2026-07-21 10:33:24'),
(30, 'Express Head Spa (30 mins)', 999.00, 100.00, 44.58, 29, 'Recovery', '2026-07-21 10:33:56'),
(31, 'Luxury Scalp + Basic Facial', 1499.00, 100.00, 216.21, 30, 'Recovery', '2026-07-21 10:34:37'),
(32, 'Swedish Massage', 699.00, 100.00, 19.25, 31, 'Recovery', '2026-07-21 10:35:03'),
(33, 'Thai Massage', 799.00, 100.00, 19.25, 32, 'Recovery', '2026-07-21 10:35:38'),
(34, 'Combination Massage', 699.00, 100.00, 19.25, 33, 'Recovery', '2026-07-21 10:36:13'),
(35, 'UNDERARM', 359.00, 50.00, 62.80, 34, 'Recovery', '2026-07-21 10:36:43'),
(36, 'CHEST', 359.00, 50.00, 123.00, 35, 'Recovery', '2026-07-21 10:37:09'),
(37, 'FULL ARM', 779.00, 100.00, 196.33, 36, 'Recovery', '2026-07-22 06:38:21'),
(38, 'HALF ARM', 399.00, 100.00, 98.17, 37, 'Recovery', '2026-07-22 06:38:49'),
(39, 'FULL LEG', 1079.00, 100.00, 196.33, 38, 'Recovery', '2026-07-22 06:39:16'),
(40, 'BIKINI', 839.00, 100.00, 117.80, 39, 'Recovery', '2026-07-22 06:39:51'),
(41, 'CHIN', 359.00, 100.00, 39.27, 40, 'Recovery', '2026-07-22 06:40:21'),
(42, 'Hydrafacial', 999.00, 100.00, 58.61, 41, 'Recovery', '2026-07-22 06:40:53'),
(43, 'SHIATSU MASSAGE', 949.00, 100.00, 19.92, 42, 'Recovery', '2026-07-22 06:41:21');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_dinein_rows`
--

CREATE TABLE `recovery_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_dinein_rows`
--

INSERT INTO `recovery_dinein_rows` (`id`, `store_name`, `report_date`, `cash`, `palawan_pay`, `card_swipe_qr`, `unpaid_credit_name`, `unpaid_credit_amount`, `discount`, `bank_transfer_cheque`, `cancelled_transactions`, `sort_order`) VALUES
(1, 'Recovery', '2026-07-07', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_disbursement`
--

CREATE TABLE `recovery_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_disbursement`
--

INSERT INTO `recovery_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-07-20', '000-405-340-218', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', 'Office Supplies', 649.00, 69.54, 579.46, '', 'Recovery', '2026-07-20 07:20:35', '2026-07-20 07:20:35'),
(2, '2026-07-20', '123-749-931-000', 'D\'TWIN STARS INDUSTRIAL SALES AND SERVICES', 'VAT', '#117 AVANCENA STREET, BRGY. SOUTH FUNDIDOR,MOLO, ILOILO CITY', '', 'Spa Supplies', 123.00, 13.18, 109.82, '', 'Recovery', '2026-07-20 07:52:28', '2026-07-20 07:52:28'),
(3, '2026-07-20', '123-749-931-000', 'D\'TWIN STARS INDUSTRIAL SALES AND SERVICES', 'VAT', '#117 AVANCENA STREET, BRGY. SOUTH FUNDIDOR,MOLO, ILOILO CITY', '', 'Water', 300.00, 32.14, 267.86, '', 'Recovery', '2026-07-20 08:14:37', '2026-07-20 08:14:37');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_expenses`
--

CREATE TABLE `recovery_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) NOT NULL DEFAULT 0.00,
  `commission_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_fees` decimal(12,2) NOT NULL DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ca` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_expenses`
--

INSERT INTO `recovery_expenses` (`id`, `expense_date`, `voucher_no`, `tin`, `company_name`, `address`, `particulars`, `document_type`, `document_no`, `amount_w_vat`, `vat`, `amount_wo_vat`, `non_vat`, `total_amount`, `purchases`, `salaries`, `rent`, `medicine`, `lpg`, `repairs_maintenance`, `fuel_trans`, `communication`, `transportation`, `light`, `drinking_water`, `water`, `sss_phic_hdmf`, `taxes_licences`, `office_supplies`, `kitchen_supplies`, `bio_pest_control`, `representation`, `miscellaneous`, `sir_budoy_nikki`, `staff_meal`, `pest_control_bio_aug`, `commission_fees`, `exhaust_cleaning`, `bank_fees`, `admin_salary_shares`, `marketing`, `sales_discounts`, `pdc`, `ca`, `withdrawal`, `depreciation_expense`, `row_total`, `selected_for_cf`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-04-10', '0', '0', '0', '0', '0', '0', '0', 123.00, 13.18, 109.82, 0.00, 109.82, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 'Recovery', '2026-04-10 02:31:03', '2026-04-10 02:31:03'),
(3, '2026-05-09', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2000.00, 2900.00, 299.00, 299.00, 5498.00, 0, 'Recovery', '2026-05-09 01:50:58', '2026-05-09 01:50:58'),
(4, '2026-07-20', '0', '0', '0', '0', '0', '0', '0', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1000.00, 0.00, 10000.00, 0.00, 11000.00, 0, 'Recovery', '2026-07-20 07:10:42', '2026-07-20 07:11:07');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_gc_sold`
--

CREATE TABLE `recovery_gc_sold` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `gc_type` enum('service','paid') NOT NULL DEFAULT 'service',
  `series` varchar(50) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `voucher` varchar(50) DEFAULT NULL,
  `qty` int(6) DEFAULT 0,
  `amount` decimal(12,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_gc_sold`
--

INSERT INTO `recovery_gc_sold` (`id`, `store_name`, `report_date`, `gc_type`, `series`, `name`, `voucher`, `qty`, `amount`, `remarks`, `sort_order`) VALUES
(5, 'Recovery', '2026-07-10', 'service', '', '', '', 0, 1.00, '', 0),
(6, 'Recovery', '2026-07-10', 'paid', '', '', '', 0, 1.00, '', 0),
(7, 'Recovery', '2026-07-17', 'service', '', '', '', 0, 0.00, '', 0),
(8, 'Recovery', '2026-07-17', 'paid', '', '', '', 0, 0.00, '', 0),
(9, 'Recovery', '2026-07-18', 'service', '', '', '', 0, 0.00, '', 0),
(10, 'Recovery', '2026-07-18', 'paid', '', '', '', 0, 0.00, '', 0),
(41, 'Recovery', '2026-07-20', 'service', '', '', '', 1, 20.00, '', 0),
(42, 'Recovery', '2026-07-20', 'paid', '', '', '', 1, 20.00, '', 0),
(45, 'Recovery', '2026-07-21', 'service', '', '', '', 0, 0.00, '', 0),
(46, 'Recovery', '2026-07-21', 'paid', '', '', '', 0, 0.00, '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_income_statement`
--

CREATE TABLE `recovery_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Recovery',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_mktg_services`
--

CREATE TABLE `recovery_mktg_services` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `time_start` time DEFAULT NULL,
  `time_end` time DEFAULT NULL,
  `slip_no` varchar(30) DEFAULT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `service` varchar(200) DEFAULT NULL,
  `stylist` varchar(100) DEFAULT NULL,
  `at_cost` decimal(12,2) DEFAULT 0.00,
  `commission_fee` decimal(12,2) DEFAULT 0.00,
  `total_mktg_exp` decimal(12,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_mktg_services`
--

INSERT INTO `recovery_mktg_services` (`id`, `store_name`, `report_date`, `time_start`, `time_end`, `slip_no`, `client_name`, `service`, `stylist`, `at_cost`, `commission_fee`, `total_mktg_exp`, `remarks`, `sort_order`) VALUES
(3, 'Recovery', '2026-07-10', NULL, NULL, NULL, NULL, NULL, NULL, 1.00, 1.00, 2.00, NULL, 0),
(4, 'Recovery', '2026-07-17', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0),
(5, 'Recovery', '2026-07-18', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0),
(29, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Nail Extension Simple', NULL, 52.72, 100.00, 152.72, NULL, 0),
(30, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Basic Manicure', NULL, 15.47, 50.00, 65.47, NULL, 1),
(32, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_month_end_inv`
--

CREATE TABLE `recovery_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_pdc`
--

CREATE TABLE `recovery_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_pl_revenue`
--

CREATE TABLE `recovery_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recovery_product_sold`
--

CREATE TABLE `recovery_product_sold` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `particular` varchar(150) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT 0.00,
  `price` decimal(12,2) DEFAULT 0.00,
  `amount` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_product_sold`
--

INSERT INTO `recovery_product_sold` (`id`, `store_name`, `report_date`, `particular`, `qty`, `price`, `amount`, `sort_order`) VALUES
(3, 'Recovery', '2026-07-10', '', 1.00, 1.00, 1.00, 0),
(4, 'Recovery', '2026-07-17', '', 0.00, 0.00, 0.00, 0),
(5, 'Recovery', '2026-07-18', '', 0.00, 0.00, 0.00, 0),
(21, 'Recovery', '2026-07-20', '', 1.00, 12.00, 12.00, 0),
(23, 'Recovery', '2026-07-21', '', 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_reconcile`
--

CREATE TABLE `recovery_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_reconcile`
--

INSERT INTO `recovery_reconcile` (`id`, `store_name`, `rec_year`, `rec_month`, `ending_balance_bank`, `deposits_in_transit`, `outstanding_checks`, `bank_credits`, `bank_charges`, `saved_by`, `created_at`, `updated_at`, `ending_balance_books`, `adjusted_bank_balance`, `adjusted_book_balance`) VALUES
(1, 'Recovery', 2026, 7, 224832.86, 5914.15, 0.00, 1000.00, 1000.00, 'Recovery', '2026-07-20 07:17:27', '2026-07-20 07:17:27', 1500.00, 230747.01, 1500.00);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_report_entries`
--

CREATE TABLE `recovery_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Recovery',
  `pos_reading` decimal(12,2) DEFAULT 0.00,
  `cash_for_depo` decimal(12,2) DEFAULT 0.00,
  `short_over` decimal(12,2) DEFAULT 0.00,
  `gross_sales_excl_mktg` decimal(12,2) DEFAULT 0.00,
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00,
  `unpaid_staff` decimal(12,2) DEFAULT 0.00,
  `unpaid_corporate` decimal(12,2) DEFAULT 0.00,
  `unpaid_mam_nikki` decimal(12,2) DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00,
  `redeemed_gc_voucher` decimal(12,2) DEFAULT 0.00,
  `sold_product` decimal(12,2) DEFAULT 0.00,
  `bpi_bank` decimal(12,2) DEFAULT 0.00,
  `gcash` decimal(12,2) DEFAULT 0.00,
  `gc_sold` decimal(12,2) DEFAULT 0.00,
  `gc_sponsorship` decimal(12,2) DEFAULT 0.00,
  `bank_transfer` decimal(12,2) DEFAULT 0.00,
  `discounted_snr_pwd` decimal(12,2) DEFAULT 0.00,
  `regular_staff_disc` decimal(12,2) DEFAULT 0.00,
  `personal` decimal(12,2) DEFAULT 0.00,
  `cash_advance` decimal(12,2) DEFAULT 0.00,
  `payroll` decimal(12,2) DEFAULT 0.00,
  `commission_fee_staff` decimal(12,2) DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) DEFAULT 0.00,
  `other_expenses` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `acctg_short_over` decimal(12,2) DEFAULT 0.00,
  `total_swipe` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_report_entries`
--

INSERT INTO `recovery_report_entries` (`id`, `report_date`, `store_name`, `pos_reading`, `cash_for_depo`, `short_over`, `gross_sales_excl_mktg`, `sales_of_day_swipe`, `unpaid_staff`, `unpaid_corporate`, `unpaid_mam_nikki`, `marketing_pull_out`, `redeemed_gc_voucher`, `sold_product`, `bpi_bank`, `gcash`, `gc_sold`, `gc_sponsorship`, `bank_transfer`, `discounted_snr_pwd`, `regular_staff_disc`, `personal`, `cash_advance`, `payroll`, `commission_fee_staff`, `pcf_expenses`, `other_expenses`, `total_deductions`, `acctg_short_over`, `total_swipe`, `remarks`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-03-01', 'Recovery', 6911.00, 2300.00, 5730.95, 6788.00, 1898.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 123.00, 1252.95, 2789.00, 123.00, 8030.95, 5730.95, 2267.00, '', 'Recovery', '2026-03-31 05:27:44', '2026-03-31 06:00:21'),
(5, '2026-07-01', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:32:20', '2026-07-13 02:53:36'),
(7, '2026-07-02', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:17', '2026-07-09 08:33:17'),
(8, '2026-07-03', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:17', '2026-07-09 08:33:17'),
(9, '2026-07-04', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:17', '2026-07-09 08:33:17'),
(10, '2026-07-05', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-09 08:33:18'),
(11, '2026-07-06', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-09 08:33:18'),
(12, '2026-07-07', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-09 08:33:18'),
(13, '2026-07-08', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-09 08:33:18'),
(14, '2026-07-09', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-09 08:33:18'),
(15, '2026-07-10', 'Recovery', 2.00, 0.00, 2.00, 1.00, 1.00, 0.00, 1.00, 0.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3.00, 1.00, 0.00, 8.00, 6.00, 0.00, '', 'Recovery', '2026-07-09 08:33:18', '2026-07-23 07:25:21'),
(16, '2026-07-11', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:19', '2026-07-09 08:33:19'),
(17, '2026-07-12', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-09 08:33:19', '2026-07-09 08:33:19'),
(31, '2026-07-13', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(32, '2026-07-14', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(33, '2026-07-15', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(34, '2026-07-16', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(35, '2026-07-17', 'Recovery', 7720.00, 1571.00, 6149.00, 7720.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, -6149.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(36, '2026-07-18', 'Recovery', 1328.00, 0.00, 1328.00, 1328.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, -1328.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(37, '2026-07-19', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(38, '2026-07-20', 'Recovery', 8693.00, 753.00, 7940.00, 8624.81, 8177.50, 0.00, 2072.50, 0.00, 68.19, 0.00, 12.00, 0.00, 0.00, 20.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1272.91, 554.00, 0.00, 10904.19, 2964.19, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(39, '2026-07-21', 'Recovery', 6251.00, 1571.00, 4680.00, 6251.00, 3764.60, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 509.40, 0.00, 0.00, 0.00, 0.00, 1317.70, 406.00, 0.00, 4680.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(40, '2026-07-22', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:22', '2026-07-23 07:25:22'),
(41, '2026-07-23', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(42, '2026-07-24', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(43, '2026-07-25', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(44, '2026-07-26', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(45, '2026-07-27', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(46, '2026-07-28', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(47, '2026-07-29', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(48, '2026-07-30', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23'),
(49, '2026-07-31', 'Recovery', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '', 'Recovery', '2026-07-23 07:25:23', '2026-07-23 07:25:23');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_sales_detail_rows`
--

CREATE TABLE `recovery_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0,
  `mop` varchar(150) DEFAULT NULL,
  `remarks` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_sales_detail_rows`
--

INSERT INTO `recovery_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`, `mop`, `remarks`) VALUES
(1, 'Recovery', '2026-07-07', 'marketing_pullout', '', 0.00, 0, NULL, NULL),
(2, 'Recovery', '2026-07-07', 'grab', '', 0.00, 0, NULL, NULL),
(3, 'Recovery', '2026-07-07', 'expenses', '', 0.00, 0, NULL, NULL),
(4, 'Recovery', '2026-07-07', 'late_payment', '', 0.00, 0, NULL, NULL),
(5, 'Recovery', '2026-07-07', 'advance_payment', '', 0.00, 0, NULL, NULL),
(6, 'Recovery', '2026-07-07', 'gc_sponsorship', '', 0.00, 0, NULL, NULL),
(7, 'Recovery', '2026-07-07', 'gc_sold', '', 0.00, 0, NULL, NULL),
(14, 'Recovery', '2026-07-10', 'down_payment', '', 1.00, 0, '', ''),
(15, 'Recovery', '2026-07-10', 'unpaids_corp', '', 1.00, 0, NULL, NULL),
(16, 'Recovery', '2026-07-10', 'expenses', '', 1.00, 0, NULL, NULL),
(17, 'Recovery', '2026-07-17', 'down_payment', '', 0.00, 0, '', ''),
(18, 'Recovery', '2026-07-17', 'unpaids_corp', '', 0.00, 0, NULL, NULL),
(19, 'Recovery', '2026-07-17', 'expenses', '', 0.00, 0, NULL, NULL),
(20, 'Recovery', '2026-07-18', 'down_payment', '', 0.00, 0, '', ''),
(21, 'Recovery', '2026-07-18', 'unpaids_corp', '', 0.00, 0, NULL, NULL),
(22, 'Recovery', '2026-07-18', 'expenses', '', 0.00, 0, NULL, NULL),
(84, 'Recovery', '2026-07-20', 'down_payment', '', 0.00, 0, '', ''),
(85, 'Recovery', '2026-07-20', 'unpaids_corp', '', 374.50, 0, NULL, NULL),
(86, 'Recovery', '2026-07-20', 'unpaids_corp', '', 849.00, 1, NULL, NULL),
(87, 'Recovery', '2026-07-20', 'unpaids_corp', '', 849.00, 2, NULL, NULL),
(88, 'Recovery', '2026-07-20', 'expenses', 'GRAB (Face Mask Aloe & Del. Fee)', 304.00, 0, NULL, NULL),
(89, 'Recovery', '2026-07-20', 'expenses', 'Sharmine (Lash Glue & Spoolie)', 250.00, 1, NULL, NULL),
(93, 'Recovery', '2026-07-21', 'down_payment', '', 0.00, 0, '', ''),
(94, 'Recovery', '2026-07-21', 'unpaids_corp', '', 0.00, 0, NULL, NULL),
(95, 'Recovery', '2026-07-21', 'expenses', '', 406.00, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_sales_report`
--

CREATE TABLE `recovery_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `staff_cf` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sold_gc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pos_reading` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discounts` decimal(12,2) NOT NULL DEFAULT 0.00,
  `celeb_discounts` decimal(12,2) NOT NULL DEFAULT 0.00,
  `redeemed_gc` decimal(12,2) NOT NULL DEFAULT 0.00,
  `swiper` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_dp` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaids` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_payment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_expense` decimal(12,2) NOT NULL DEFAULT 0.00,
  `product_sold` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `opening_cashier` varchar(100) DEFAULT NULL,
  `closing_cashier` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_sales_report`
--

INSERT INTO `recovery_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`, `staff_cf`, `sold_gc`, `pos_reading`, `discounts`, `celeb_discounts`, `redeemed_gc`, `swiper`, `gcash_sales`, `maya_sales`, `maya_dp`, `unpaids`, `advance_payment`, `expenses`, `marketing_expense`, `product_sold`, `net_cash`, `opening_cashier`, `closing_cashier`) VALUES
(1, 'Recovery', '2026-07-07', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Recovery', '2026-07-07 03:51:17', '2026-07-07 03:51:17', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, NULL),
(2, 'Recovery', '2026-07-10', 6.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 6.00, 'Recovery', '2026-07-10 05:55:44', '2026-07-10 07:46:08', 3.00, 1.00, 2.00, 0.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, -6.00, NULL, NULL),
(5, 'Recovery', '2026-07-17', 7720.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1571.00, 0.00, -6149.00, 'Recovery', '2026-07-17 06:56:31', '2026-07-17 06:56:31', 0.00, 0.00, 7720.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 7720.00, NULL, NULL),
(6, 'Recovery', '2026-07-18', 1328.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, -1328.00, 'Recovery', '2026-07-17 09:06:05', '2026-07-17 09:06:05', 0.00, 0.00, 1328.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1328.00, NULL, NULL),
(7, 'Recovery', '2026-07-20', 8713.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 753.00, 0.00, 2944.19, 'Recovery', '2026-07-20 03:44:44', '2026-07-20 06:41:39', 1272.91, 20.00, 8693.00, 0.00, 0.00, 0.00, 8177.50, 0.00, 0.00, 0.00, 2072.50, 0.00, 554.00, 68.19, 12.00, -2191.19, NULL, NULL),
(23, 'Recovery', '2026-07-21', 6251.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1571.00, 0.00, 0.00, 'Recovery', '2026-07-20 07:02:08', '2026-07-20 07:06:42', 1317.70, 0.00, 6251.00, 509.40, 0.00, 0.00, 3764.60, 0.00, 0.00, 0.00, 0.00, 0.00, 406.00, 0.00, 0.00, 1571.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_sales_services`
--

CREATE TABLE `recovery_sales_services` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Recovery',
  `report_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `slip_no` varchar(30) DEFAULT NULL,
  `client_name` varchar(150) DEFAULT NULL,
  `service` varchar(200) DEFAULT NULL,
  `stylist` varchar(100) DEFAULT NULL,
  `regular_price` decimal(12,2) DEFAULT 0.00,
  `promo_price` decimal(12,2) DEFAULT 0.00,
  `comm_rate` varchar(10) DEFAULT '' COMMENT 'Selected commission tier: 30, 20, 15, or disc50',
  `celeb_promo_10` decimal(12,2) DEFAULT 0.00,
  `disc_20_pwd_snr` decimal(12,2) DEFAULT 0.00,
  `comm_30` decimal(12,2) DEFAULT 0.00,
  `comm_20` decimal(12,2) DEFAULT 0.00,
  `comm_15` decimal(12,2) DEFAULT 0.00,
  `disc_50_staff` decimal(12,2) DEFAULT 0.00,
  `net_sales` decimal(12,2) DEFAULT 0.00,
  `mode_of_payment` varchar(50) DEFAULT NULL,
  `advance_payment` decimal(12,2) DEFAULT 0.00,
  `mop` varchar(50) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_sales_services`
--

INSERT INTO `recovery_sales_services` (`id`, `store_name`, `report_date`, `time_in`, `time_out`, `slip_no`, `client_name`, `service`, `stylist`, `regular_price`, `promo_price`, `comm_rate`, `celeb_promo_10`, `disc_20_pwd_snr`, `comm_30`, `comm_20`, `comm_15`, `disc_50_staff`, `net_sales`, `mode_of_payment`, `advance_payment`, `mop`, `remarks`, `sort_order`) VALUES
(1, 'Recovery', '2026-07-07', '11:43:00', '02:47:00', '123', 'test', 'id', NULL, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0),
(2, 'Recovery', '2026-07-07', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 1),
(5, 'Recovery', '2026-07-10', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', 1.00, 1.00, 1.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 0),
(6, 'Recovery', '2026-07-17', NULL, NULL, '1943-2026', 'MANDEEB', 'Swedish Massage', NULL, 599.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 599.00, NULL, 0.00, NULL, NULL, 0),
(7, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'ALAINE PAREJA', NULL, NULL, 599.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 599.00, NULL, 0.00, NULL, NULL, 1),
(8, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'LET DELGADO', NULL, NULL, 599.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 599.00, NULL, 0.00, NULL, NULL, 2),
(9, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'SHEKINAH HEPILOS', NULL, NULL, 599.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 599.00, NULL, 0.00, NULL, NULL, 3),
(10, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'IRENE SULLESTE', NULL, NULL, 2199.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 2199.00, NULL, 0.00, NULL, NULL, 4),
(11, 'Recovery', '2026-07-17', NULL, NULL, NULL, NULL, NULL, NULL, 849.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 849.00, NULL, 0.00, NULL, NULL, 5),
(12, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'MA. MUEDITA ALCALA', NULL, NULL, 999.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 999.00, NULL, 0.00, NULL, NULL, 6),
(13, 'Recovery', '2026-07-17', NULL, NULL, NULL, 'BABY DE GUZMAN', NULL, NULL, 269.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 269.00, NULL, 0.00, NULL, NULL, 7),
(14, 'Recovery', '2026-07-17', NULL, NULL, NULL, NULL, NULL, NULL, 359.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 359.00, NULL, 0.00, NULL, NULL, 8),
(15, 'Recovery', '2026-07-17', NULL, NULL, NULL, NULL, NULL, NULL, 399.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 399.00, NULL, 0.00, NULL, NULL, 9),
(16, 'Recovery', '2026-07-17', NULL, NULL, NULL, NULL, NULL, NULL, 250.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 250.00, NULL, 0.00, NULL, NULL, 10),
(17, 'Recovery', '2026-07-18', NULL, NULL, NULL, NULL, 'Scalp Scrub', NULL, 479.00, 399.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 399.00, NULL, 0.00, NULL, NULL, 0),
(18, 'Recovery', '2026-07-18', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 699.00, NULL, 0.00, NULL, NULL, 1),
(19, 'Recovery', '2026-07-18', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, NULL, 0.00, NULL, NULL, 2),
(185, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Promo Gel Pedicure', NULL, 499.00, 499.00, 'disc50', 0.00, 0.00, 0.00, 0.00, 0.00, 249.50, 499.00, NULL, 0.00, NULL, NULL, 0),
(186, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Combination Massage', NULL, 849.00, 699.00, '20', 0.00, 0.00, 0.00, 139.80, 0.00, 0.00, 709.20, NULL, 0.00, NULL, NULL, 1),
(187, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '30', 0.00, 0.00, 209.70, 0.00, 0.00, 0.00, 639.30, NULL, 0.00, NULL, NULL, 2),
(188, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '30', 0.00, 0.00, 209.70, 0.00, 0.00, 0.00, 639.30, NULL, 0.00, NULL, NULL, 3),
(189, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Combination Massage', NULL, 849.00, 699.00, '20', 0.00, 0.00, 0.00, 139.80, 0.00, 0.00, 709.20, NULL, 0.00, NULL, NULL, 4),
(190, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Package 1 (45mins Express Headspa + 1hr Body Massage)', NULL, 2139.00, 1069.50, '15', 0.00, 0.00, 0.00, 0.00, 160.43, 0.00, 1978.58, NULL, 0.00, NULL, NULL, 5),
(191, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Package 1 (45mins Express Headspa + 1hr Body Massage)', NULL, 2139.00, 1069.50, '15', 0.00, 0.00, 0.00, 0.00, 160.43, 0.00, 1978.58, NULL, 0.00, NULL, NULL, 6),
(192, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Promo Basic Manicure', NULL, 239.00, 239.00, '30', 0.00, 0.00, 71.70, 0.00, 0.00, 0.00, 167.30, NULL, 0.00, NULL, NULL, 7),
(193, 'Recovery', '2026-07-20', NULL, NULL, NULL, NULL, 'Nail Cleaning', NULL, 269.00, 209.00, '15', 0.00, 0.00, 0.00, 0.00, 31.35, 0.00, 237.65, NULL, 0.00, NULL, NULL, 8),
(222, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '30', 0.00, 0.00, 209.70, 0.00, 0.00, 0.00, 639.30, NULL, 0.00, NULL, NULL, 0),
(223, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Scalp Scrub', NULL, 479.00, 399.00, '30', 0.00, 0.00, 119.70, 0.00, 0.00, 0.00, 359.30, NULL, 0.00, NULL, NULL, 1),
(224, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Foot Spa + Foot Scrub', NULL, 649.00, 449.00, '20', 0.00, 0.00, 0.00, 89.80, 0.00, 0.00, 559.20, NULL, 0.00, NULL, NULL, 2),
(225, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Promo Gel Manicure', NULL, 399.00, 399.00, '30', 0.00, 0.00, 119.70, 0.00, 0.00, 0.00, 279.30, NULL, 0.00, NULL, NULL, 3),
(226, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Face Lifting', NULL, 479.00, 399.00, '20', 0.00, 0.00, 0.00, 79.80, 0.00, 0.00, 399.20, NULL, 0.00, NULL, NULL, 4),
(227, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Combination Massage', NULL, 849.00, 699.00, '20', 0.00, 0.00, 0.00, 139.80, 0.00, 0.00, 709.20, NULL, 0.00, NULL, NULL, 5),
(228, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '20', 0.00, 169.80, 0.00, 139.80, 0.00, 0.00, 539.40, NULL, 0.00, NULL, NULL, 6),
(229, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '30', 0.00, 169.80, 209.70, 0.00, 0.00, 0.00, 469.50, NULL, 0.00, NULL, NULL, 7),
(230, 'Recovery', '2026-07-21', NULL, NULL, NULL, NULL, 'Swedish Massage', NULL, 849.00, 699.00, '30', 0.00, 169.80, 209.70, 0.00, 0.00, 0.00, 469.50, NULL, 0.00, NULL, NULL, 8);

-- --------------------------------------------------------

--
-- Table structure for table `recovery_services_pricelist`
--

CREATE TABLE `recovery_services_pricelist` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL DEFAULT '',
  `regular` decimal(10,2) NOT NULL DEFAULT 0.00,
  `promo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_promo` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_services_pricelist`
--

INSERT INTO `recovery_services_pricelist` (`id`, `name`, `regular`, `promo`, `is_promo`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, 'Nail Cleaning', 269.00, 209.00, 1, 0, 'Recovery', '2026-07-17 08:31:50'),
(2, 'Basic Manicure', 319.00, 239.00, 1, 1, 'Recovery', '2026-07-17 08:31:50'),
(3, 'Basic Pedicure', 389.00, 289.00, 1, 2, 'Recovery', '2026-07-17 08:31:51'),
(4, 'Gel Manicure', 499.00, 399.00, 1, 3, 'Recovery', '2026-07-17 08:31:52'),
(5, 'Gel Pedicure', 659.00, 499.00, 1, 4, 'Recovery', '2026-07-17 08:31:52'),
(6, 'Gel Removal', 200.00, 200.00, 1, 5, 'Recovery', '2026-07-17 08:31:53'),
(7, 'Nail Extension Simple', 1399.00, 999.00, 1, 6, 'Recovery', '2026-07-17 08:31:54'),
(8, 'Nail Extension Pd', 2200.00, 1500.00, 1, 7, 'Recovery', '2026-07-17 08:31:55'),
(9, 'Promo Nail Cleaning', 209.00, 209.00, 1, 8, 'Recovery', '2026-07-17 08:31:56'),
(10, 'Promo Basic Manicure', 239.00, 239.00, 1, 9, 'Recovery', '2026-07-17 08:31:56'),
(11, 'Promo Basic Pedicure', 289.00, 289.00, 1, 10, 'Recovery', '2026-07-23 07:48:22'),
(12, 'Promo Gel Manicure', 399.00, 399.00, 1, 11, 'Recovery', '2026-07-23 07:48:22'),
(13, 'Promo Gel Pedicure', 499.00, 499.00, 1, 12, 'Recovery', '2026-07-23 07:48:23'),
(14, 'Swedish Massage', 849.00, 699.00, 1, 13, 'Recovery', '2026-07-23 08:10:40'),
(15, 'Scalp Scrub', 479.00, 399.00, 1, 14, 'Recovery', '2026-07-23 07:35:29'),
(16, 'Promo Nail Extension Simple', 999.00, 999.00, 1, 15, 'Recovery', '2026-07-23 07:35:29'),
(17, 'Nails Extenson Removal', 300.00, 300.00, 1, 16, 'Recovery', '2026-07-23 07:35:30'),
(18, 'Reg Foot Spa', 599.00, 399.00, 1, 17, 'Recovery', '2026-07-23 07:35:31'),
(19, 'Foot Spa + Foot Scrub', 649.00, 449.00, 1, 18, 'Recovery', '2026-07-23 07:35:32'),
(20, 'Classic Lash Extensions', 899.00, 699.00, 1, 19, 'Recovery', '2026-07-23 08:10:40'),
(21, 'Cat Eye/Wispy Lash Ext', 999.00, 799.00, 1, 20, 'Recovery', '2026-07-23 07:35:46'),
(22, 'Semi Glam', 1199.00, 899.00, 1, 21, 'Recovery', '2026-07-23 07:35:45'),
(23, 'Full Glam', 1399.00, 999.00, 1, 22, 'Recovery', '2026-07-23 07:35:44'),
(24, 'Lash Removal', 300.00, 300.00, 1, 23, 'Recovery', '2026-07-23 07:35:43'),
(25, 'Last Lift with  Tint', 799.00, 590.00, 1, 24, 'Recovery', '2026-07-23 07:35:43'),
(26, 'Package 1 (45mins Express Headspa + 1hr Body Massage)', 2139.00, 1069.50, 1, 25, 'Recovery', '2026-07-23 07:35:42'),
(27, 'Combination Massage', 849.00, 699.00, 1, 26, 'Recovery', '2026-07-20 04:31:59'),
(28, 'Daytime Massage  Promo(10am -4pm)', 599.00, 599.00, 1, 27, 'Recovery', '2026-07-23 08:10:41'),
(29, 'Daytime Massage  Promo(10am -4pm) shiatsu', 699.00, 699.00, 1, 28, 'Recovery', '2026-07-23 08:10:41'),
(31, 'Chest Wax', 359.00, 299.00, 1, 31, 'Recovery', '2026-07-23 08:10:41'),
(32, 'Underarm Wax', 359.00, 299.00, 1, 30, 'Recovery', '2026-07-23 08:10:41'),
(33, 'Scalp Scrub', 479.00, 399.00, 1, 29, 'Recovery', '2026-07-23 08:10:41'),
(34, 'Full Arm Wax', 779.00, 649.00, 1, 32, 'Recovery', '2026-07-23 08:10:41'),
(35, 'Full Leg Wax', 1079.00, 899.00, 1, 34, 'Recovery', '2026-07-23 08:10:41'),
(36, 'Half Arm Wax', 399.00, 329.00, 1, 33, 'Recovery', '2026-07-23 08:10:41'),
(37, 'Bikini Wax', 839.00, 699.00, 1, 35, 'Recovery', '2026-07-23 08:10:41'),
(38, 'Add Ventosa', 300.00, 300.00, 1, 37, 'Recovery', '2026-07-23 08:10:41'),
(39, 'Chin Wax', 359.00, 299.00, 1, 36, 'Recovery', '2026-07-23 08:10:41'),
(40, 'Add Hotstone', 300.00, 300.00, 1, 38, 'Recovery', '2026-07-23 08:10:41'),
(41, 'Gc 1000', 1000.00, 1000.00, 1, 39, 'Recovery', '2026-07-23 08:10:41'),
(42, 'Package 1 (1hr body massage + 30 mins Ventosa)', 1099.00, 999.00, 1, 40, 'Recovery', '2026-07-23 08:10:41'),
(43, 'Package 2 ( 1hr body massage + 30mins Hotstone)', 1099.00, 999.00, 1, 41, 'Recovery', '2026-07-23 08:10:42'),
(44, 'Package 3 (1hr body massage + Body Scrub)', 1429.00, 1298.00, 1, 42, 'Recovery', '2026-07-23 08:10:42'),
(45, 'Swedish Massage 1.5hrs', 1199.00, 1048.00, 1, 43, 'Recovery', '2026-07-23 08:10:42'),
(46, 'Combination Massage 1.5hrs', 1199.00, 1048.00, 1, 44, 'Recovery', '2026-07-23 08:10:42'),
(47, 'Thai Massage 1.5hrs', 1199.00, 1048.00, 1, 45, 'Recovery', '2026-07-23 08:10:42'),
(48, 'Back Massage', 699.00, 699.00, 1, 46, 'Recovery', '2026-07-23 08:10:42'),
(49, 'Back Massage 30Mins', 350.00, 350.00, 1, 47, 'Recovery', '2026-07-23 08:10:42'),
(50, 'Deep Cleansing Facial', 749.00, 749.00, 1, 48, 'Recovery', '2026-07-23 08:10:42'),
(51, 'Hand Massage', 350.00, 350.00, 1, 49, 'Recovery', '2026-07-23 08:10:42'),
(52, '30Mins Massage', 350.00, 350.00, 1, 50, 'Recovery', '2026-07-23 08:10:42'),
(53, '15 Min. Headspa', 499.00, 499.00, 1, 51, 'Recovery', '2026-07-23 08:10:42'),
(54, 'Basic Facial', 499.00, 499.00, 1, 53, 'Recovery', '2026-07-23 08:10:42'),
(55, 'HydraFacial', 999.00, 999.00, 1, 52, 'Recovery', '2026-07-23 08:10:42'),
(56, 'Junior Head Spa', 499.00, 499.00, 1, 54, 'Recovery', '2026-07-23 08:10:42'),
(57, 'Foot Massage', 350.00, 350.00, 1, 55, 'Recovery', '2026-07-23 08:10:42'),
(58, 'Package 1 (Foot Spa  + Foot Scrub + Basic Manicure+ Basic Pedicure)', 1299.00, 977.00, 1, 56, 'Recovery', '2026-07-23 08:10:42'),
(59, 'Package 2 ( Foot Spa + Gel Mani +Gel Pedi)', 1669.00, 1297.00, 1, 57, 'Recovery', '2026-07-23 08:10:42'),
(60, 'Package 3 (Footspa + Foot Massage + Gel Pedi)', 1499.00, 1248.00, 1, 58, 'Recovery', '2026-07-23 08:10:42'),
(61, 'Package 1 (45mins Express Headspa + 1hr Body Massage)', 2139.00, 1069.50, 1, 59, 'Recovery', '2026-07-23 08:10:42'),
(62, 'Package 2 (45mins Express Headspa +  30mins foot massage )', 1709.00, 854.50, 1, 60, 'Recovery', '2026-07-23 08:10:42'),
(63, 'Package 3 (45mins Express Headspa +  Body Scrubi)', 1949.00, 1598.00, 1, 61, 'Recovery', '2026-07-23 08:10:42'),
(64, '30mins Express Headspa', 599.00, 599.00, 1, 62, 'Recovery', '2026-07-23 08:10:43'),
(65, 'SHIATSU MASSAGE', 949.00, 799.00, 1, 63, 'Recovery', '2026-07-23 08:10:43'),
(66, 'ADD ON 150  FOR GEL DESIGN', 150.00, 150.00, 1, 64, 'Recovery', '2026-07-23 08:10:43'),
(67, 'PROMO 25mins Express Headspa', 499.00, 499.00, 1, 66, 'Recovery', '2026-07-23 08:10:43'),
(68, 'Ear Candling', 250.00, 250.00, 1, 67, 'Recovery', '2026-07-23 08:10:43'),
(69, 'FOOT MASSAGE 1HR.', 700.00, 700.00, 1, 65, 'Recovery', '2026-07-23 08:10:43'),
(70, 'ULTIMATE RECOVERY', 3599.00, 0.00, 1, 68, 'Recovery', '2026-07-23 08:10:43'),
(72, 'HEAD TO TOE BLISS', 1799.00, 0.00, 1, 69, 'Recovery', '2026-07-23 08:10:43'),
(73, 'SCALP & FOOT RENEWAL', 1549.00, 0.00, 1, 70, 'Recovery', '2026-07-23 08:10:43'),
(74, 'HAND AND FOOT MASSAGE', 350.00, 350.00, 1, 72, 'Recovery', '2026-07-23 08:10:43'),
(75, 'HEAD MASSAGE', 350.00, 350.00, 1, 71, 'Recovery', '2026-07-23 08:10:43'),
(77, 'Promo Nail Extension Pd', 0.00, 0.00, 1, 73, 'Recovery', '2026-07-23 08:10:43'),
(78, 'Foot Spa + Foot Scrub+ Foot Massage', 0.00, 0.00, 1, 74, 'Recovery', '2026-07-23 08:10:43'),
(79, 'Foot Reflex', 0.00, 0.00, 1, 75, 'Recovery', '2026-07-23 08:10:43'),
(80, 'last lift with tint', 0.00, 0.00, 1, 76, 'Recovery', '2026-07-23 08:10:43'),
(81, 'PROMO Classic Lash Extensions', 0.00, 0.00, 1, 77, 'Recovery', '2026-07-23 08:10:43'),
(82, 'PROMO Cat Eye/Wispy Lash Ext', 0.00, 0.00, 1, 78, 'Recovery', '2026-07-23 08:10:43'),
(83, 'PROMO Semi Glam', 0.00, 0.00, 1, 79, 'Recovery', '2026-07-23 08:10:43'),
(84, 'PROMO Full Glam', 0.00, 0.00, 1, 80, 'Recovery', '2026-07-23 08:10:44'),
(85, 'PROMO Lash Lift', 0.00, 0.00, 1, 81, 'Recovery', '2026-07-23 08:10:44'),
(86, 'Lash Lift', 0.00, 0.00, 1, 82, 'Recovery', '2026-07-23 08:10:44'),
(87, 'Brow Shaping (Wax Or Tread)', 0.00, 0.00, 1, 83, 'Recovery', '2026-07-23 08:10:44'),
(88, 'Brow Tinting', 0.00, 0.00, 1, 84, 'Recovery', '2026-07-23 08:10:44'),
(89, 'Brow Lamination', 0.00, 0.00, 1, 85, 'Recovery', '2026-07-23 08:10:44'),
(90, 'Brow Shaping + Tint Package', 0.00, 0.00, 1, 86, 'Recovery', '2026-07-23 08:10:44'),
(91, 'Brow Lamination + Tint Package', 0.00, 0.00, 1, 87, 'Recovery', '2026-07-23 08:10:44'),
(92, 'Express Head Spa (30 Mins)', 0.00, 0.00, 1, 88, 'Recovery', '2026-07-23 08:10:44'),
(93, 'Luxury Scalp + Basic Facial', 0.00, 0.00, 1, 89, 'Recovery', '2026-07-23 08:10:44'),
(94, 'Milk + Honey Scrub', 0.00, 0.00, 1, 90, 'Recovery', '2026-07-23 08:10:44'),
(95, 'Coffee Detox Scrub', 0.00, 0.00, 1, 91, 'Recovery', '2026-07-23 08:10:44'),
(96, 'Thai Massage', 0.00, 0.00, 1, 92, 'Recovery', '2026-07-23 08:10:44'),
(97, 'Daytime Massage Promo(10am -4pm)', 0.00, 0.00, 1, 93, 'Recovery', '2026-07-23 08:10:44'),
(98, 'Daytime Massage Promo(10am -4pm) shiatsu', 0.00, 0.00, 1, 94, 'Recovery', '2026-07-23 08:10:44'),
(99, 'Hotel Or Home Service Fee', 0.00, 0.00, 1, 95, 'Recovery', '2026-07-23 08:10:44'),
(100, 'Face Lifting', 0.00, 0.00, 1, 96, 'Recovery', '2026-07-23 08:10:44'),
(101, 'Package 1 (1hr body massage + 30 mins ventosa)', 0.00, 0.00, 1, 97, 'Recovery', '2026-07-23 08:10:44'),
(102, 'Package 2 ( 1hr body massage + 30mins hotstone)', 0.00, 0.00, 1, 98, 'Recovery', '2026-07-23 08:10:44'),
(103, 'Thai Massage 1.5hrs', 0.00, 0.00, 1, 99, 'Recovery', '2026-07-23 08:10:44'),
(104, 'Hydrafacial', 0.00, 0.00, 1, 100, 'Recovery', '2026-07-23 08:10:44'),
(105, 'Junior Headspa', 0.00, 0.00, 1, 101, 'Recovery', '2026-07-23 08:10:44'),
(106, 'Package 1 (Footspa + Foot Scrub+basic mani+ basic pedi)', 0.00, 0.00, 1, 102, 'Recovery', '2026-07-23 08:10:44'),
(107, 'Package 2 ( Footspa + Gel Mani +Gel Pedi)', 0.00, 0.00, 1, 103, 'Recovery', '2026-07-23 08:10:44'),
(108, 'Package 2 (45mins Express Headspa + 30mins foot massage )', 0.00, 0.00, 1, 104, 'Recovery', '2026-07-23 08:10:44'),
(109, 'Package 3 (45mins Express Headspa + Body Scrubi)', 0.00, 0.00, 1, 105, 'Recovery', '2026-07-23 08:10:44'),
(110, 'ADD ON 150 FOR GEL DESIGN', 0.00, 0.00, 1, 106, 'Recovery', '2026-07-23 08:10:44'),
(111, 'PAMPER & POLISH', 0.00, 0.00, 1, 107, 'Recovery', '2026-07-23 08:10:44'),
(112, 'thai Massage 1.5hrs', 0.00, 0.00, 0, 109, 'system-backfill', '2026-07-23 08:10:44');

-- --------------------------------------------------------

--
-- Table structure for table `recovery_stylist_handles`
--

CREATE TABLE `recovery_stylist_handles` (
  `id` int(11) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `handles` text DEFAULT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recovery_stylist_handles`
--

INSERT INTO `recovery_stylist_handles` (`id`, `price`, `name`, `handles`, `sort_order`, `saved_by`, `updated_at`) VALUES
(1, 269.00, 'APRIL', '', 0, 'Recovery', '2026-07-23 07:48:27'),
(2, 319.00, 'JAZY', 'Headspa / Massage', 1, 'Recovery', '2026-07-23 07:48:27'),
(3, 389.00, 'SHANE', 'Lash / Manicure / Pedicure / Nails Extension / Footspa / Foot Scrub / Body Scrub', 2, 'Recovery', '2026-07-23 07:48:27'),
(4, 499.00, 'ANGEL', '', 3, 'Recovery', '2026-07-23 07:48:27'),
(5, 659.00, 'RONALENE', '', 4, 'Recovery', '2026-07-23 07:48:27'),
(6, 200.00, 'MILA', '', 5, 'Recovery', '2026-07-23 07:48:27'),
(7, 1399.00, 'ANDREA', '', 6, 'Recovery', '2026-07-23 07:48:27'),
(8, 2200.00, 'JANINE', '', 7, 'Recovery', '2026-07-23 07:48:27'),
(9, NULL, 'JOY', '', 8, 'Recovery', '2026-07-23 07:48:27'),
(10, NULL, 'CARMEN', '', 9, 'Recovery', '2026-07-23 07:48:27'),
(11, NULL, 'RUTH', '', 10, 'Recovery', '2026-07-23 07:48:27'),
(12, NULL, 'ANGIE', '', 11, 'Recovery', '2026-07-23 07:48:27');

-- --------------------------------------------------------

--
-- Table structure for table `report_locks`
--

CREATE TABLE `report_locks` (
  `store_name` varchar(100) NOT NULL,
  `report_date` date NOT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `locked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `client` varchar(150) NOT NULL,
  `product` varchar(150) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('paid','pending','overdue') DEFAULT 'pending',
  `sale_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_acc_titles`
--

CREATE TABLE `stella_acc_titles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `section` enum('assets','expenses','other') NOT NULL DEFAULT 'expenses',
  `sort_order` int(6) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_acc_titles`
--

INSERT INTO `stella_acc_titles` (`id`, `title`, `section`, `sort_order`, `saved_by`, `created_at`) VALUES
(1, 'Office Equipment', 'assets', 0, 'system-seed', '2026-07-21 04:32:14'),
(2, 'Other Equipment', 'assets', 1, 'system-seed', '2026-07-21 04:32:14'),
(3, 'Service Vehicle', 'assets', 2, 'system-seed', '2026-07-21 04:32:14'),
(4, 'Leasehold Improvement', 'assets', 3, 'system-seed', '2026-07-21 04:32:14'),
(5, 'Furniture and Fixtures', 'assets', 4, 'system-seed', '2026-07-21 04:32:14'),
(6, 'Investments', 'assets', 5, 'system-seed', '2026-07-21 04:32:14'),
(7, 'Accounts Payable', 'other', 6, 'system-seed', '2026-07-21 04:32:14'),
(8, 'EWT Payable', 'other', 7, 'system-seed', '2026-07-21 04:32:14'),
(9, 'Purchases - Non-Vat', 'expenses', 8, 'system-seed', '2026-07-21 04:32:14'),
(10, 'Purchases - Vatable', 'expenses', 9, 'system-seed', '2026-07-21 04:32:14'),
(11, 'Kitchen Supplies', 'expenses', 10, 'system-seed', '2026-07-21 04:32:14'),
(12, 'Solane', 'expenses', 11, 'system-seed', '2026-07-21 04:32:14'),
(13, 'Miscellaneous', 'expenses', 12, 'system-seed', '2026-07-21 04:32:14'),
(14, 'Rent', 'expenses', 13, 'system-seed', '2026-07-21 04:32:14'),
(15, 'CUSA', 'expenses', 14, 'system-seed', '2026-07-21 04:32:14'),
(16, 'Office Supplies', 'expenses', 15, 'system-seed', '2026-07-21 04:32:14'),
(17, 'Pest Control', 'expenses', 16, 'system-seed', '2026-07-21 04:32:14'),
(18, 'Advertisement', 'expenses', 17, 'system-seed', '2026-07-21 04:32:14'),
(19, 'Bio Augmentation', 'expenses', 18, 'system-seed', '2026-07-21 04:32:14'),
(20, 'Professional Fee', 'expenses', 19, 'system-seed', '2026-07-21 04:32:14'),
(21, 'Bookkeeping Fee', 'expenses', 20, 'system-seed', '2026-07-21 04:32:14'),
(22, 'Fare & Transportation', 'expenses', 21, 'system-seed', '2026-07-21 04:32:14'),
(23, 'Fuel & Oil', 'expenses', 22, 'system-seed', '2026-07-21 04:32:14'),
(24, 'Repairs and Maintenance', 'expenses', 23, 'system-seed', '2026-07-21 04:32:14'),
(25, 'Telephone, Light & Water', 'expenses', 24, 'system-seed', '2026-07-21 04:32:14'),
(26, 'Delivery Expense', 'expenses', 25, 'system-seed', '2026-07-21 04:32:14'),
(27, 'Salaries and Wages', 'expenses', 26, 'system-seed', '2026-07-21 04:32:14'),
(28, 'Representation Expense', 'expenses', 27, 'system-seed', '2026-07-21 04:32:14'),
(29, 'Meals', 'expenses', 28, 'system-seed', '2026-07-21 04:32:14'),
(30, 'Taxes and Licenses', 'expenses', 29, 'system-seed', '2026-07-21 04:32:14'),
(31, 'SSS, PHIC, HDMF Contribution', 'expenses', 30, 'system-seed', '2026-07-21 04:32:14'),
(32, 'Commission Expense', 'expenses', 31, 'system-seed', '2026-07-21 04:32:14'),
(33, 'M\'Nikki', 'expenses', 32, 'system-seed', '2026-07-21 04:32:14'),
(34, 'c/o Nikki', 'expenses', 33, 'system-seed', '2026-07-21 04:32:14'),
(35, 'Others', 'expenses', 34, 'system-seed', '2026-07-21 04:32:14');

-- --------------------------------------------------------

--
-- Table structure for table `stella_cashflow`
--

CREATE TABLE `stella_cashflow` (
  `id` int(11) NOT NULL,
  `cf_date` date NOT NULL,
  `cf_year` int(4) NOT NULL,
  `cf_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `cash_beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash at Beginning of Month',
  `sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inv_purchases` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pdc_loan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `withdrawals` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_cash_flow` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_end` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_cashflow`
--

INSERT INTO `stella_cashflow` (`id`, `cf_date`, `cf_year`, `cf_month`, `store_name`, `cash_beg`, `sales`, `inv_purchases`, `expenses`, `pdc_loan`, `withdrawals`, `net_cash_flow`, `cash_end`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-07-31', 2026, 7, 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Stella', '2026-07-03 11:19:18', '2026-07-13 01:57:16');

-- --------------------------------------------------------

--
-- Table structure for table `stella_cashflow_balance`
--

CREATE TABLE `stella_cashflow_balance` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `txn_date` date DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `cash_in` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `entry_year` int(4) NOT NULL,
  `entry_month` tinyint(2) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_categories`
--

CREATE TABLE `stella_categories` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `name` varchar(100) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_categories_meta`
--

CREATE TABLE `stella_categories_meta` (
  `store_name` varchar(50) NOT NULL,
  `seeded` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_categories_meta`
--

INSERT INTO `stella_categories_meta` (`store_name`, `seeded`) VALUES
('Stella', 1);

-- --------------------------------------------------------

--
-- Table structure for table `stella_cf_vat_selection`
--

CREATE TABLE `stella_cf_vat_selection` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `sel_year` int(4) NOT NULL,
  `sel_month` tinyint(2) NOT NULL,
  `vat_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `row_count` int(11) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_cf_vat_selection`
--

INSERT INTO `stella_cf_vat_selection` (`id`, `store_name`, `sel_year`, `sel_month`, `vat_total`, `row_count`, `saved_by`, `updated_at`) VALUES
(1, 'Stella', 2026, 7, 0.00, 0, 'Stella', '2026-07-28 04:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `stella_cogs`
--

CREATE TABLE `stella_cogs` (
  `id` int(11) NOT NULL,
  `cogs_date` date NOT NULL,
  `cogs_year` int(4) NOT NULL,
  `cogs_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `beg` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Beginning Inventory',
  `purc` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Purchases',
  `end_inv` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Inventory',
  `cos` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cost of Sales = BEG + PURC - END',
  `mktg_cost` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Marketing Cost',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_cogs`
--

INSERT INTO `stella_cogs` (`id`, `cogs_date`, `cogs_year`, `cogs_month`, `store_name`, `beg`, `purc`, `end_inv`, `cos`, `mktg_cost`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '2026-05-01', 2026, 5, 'Stella', 20000.00, 2000.00, 2000.00, 20000.00, 2000.00, 'Stella', '2026-05-09 02:07:09', '2026-05-09 02:07:09'),
(2, '2026-05-01', 2026, 5, 'Stella', 100000.00, 100000.00, 100000.00, 100000.00, 100000.00, 'Stella', '2026-05-09 02:07:25', '2026-05-09 02:07:25'),
(3, '2026-05-02', 2026, 5, 'Stella', 30000.00, 30000.00, 30000.00, 30000.00, 30000.00, 'Stella', '2026-05-09 02:08:08', '2026-05-09 02:08:08'),
(4, '2026-06-01', 2026, 6, 'Stella', 1000.00, 1000.00, 10000.00, -8000.00, 1000.00, 'Stella', '2026-06-29 10:03:15', '2026-06-29 10:03:15'),
(5, '2026-06-01', 2026, 6, 'Stella', 1.00, 1.00, 1.00, 1.00, 1.00, 'Stella', '2026-06-30 10:05:51', '2026-06-30 10:05:51');

-- --------------------------------------------------------

--
-- Table structure for table `stella_dinein_rows`
--

CREATE TABLE `stella_dinein_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `report_date` date NOT NULL,
  `cash` decimal(12,2) DEFAULT 0.00,
  `palawan_pay` decimal(12,2) DEFAULT 0.00,
  `card_swipe_qr` decimal(12,2) DEFAULT 0.00,
  `unpaid_credit_name` varchar(100) DEFAULT NULL,
  `unpaid_credit_amount` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) DEFAULT 0.00,
  `cancelled_transactions` decimal(12,2) DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0,
  `gift_card` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_dinein_rows`
--

INSERT INTO `stella_dinein_rows` (`id`, `store_name`, `report_date`, `cash`, `palawan_pay`, `card_swipe_qr`, `unpaid_credit_name`, `unpaid_credit_amount`, `discount`, `bank_transfer_cheque`, `cancelled_transactions`, `sort_order`, `gift_card`) VALUES
(4, 'Stella', '2026-07-06', 35370.71, 0.00, 24072.63, 'DEMIC LAB MANDURRIAO', 1321.43, 194.00, 0.00, 0.00, 0, 4500.00),
(5, 'Stella', '2026-07-06', 0.00, 0.00, 0.00, NULL, 0.00, 1012.62, 0.00, 0.00, 1, 0.00),
(6, 'Stella', '2026-07-06', 0.00, 0.00, 0.00, NULL, 0.00, 597.17, 0.00, 0.00, 2, 0.00),
(8, 'Stella', '2026-07-08', 1.00, 1.00, 1.00, '', 1.00, 1.00, 1.00, 1.00, 0, 1.00),
(14, 'Stella', '2026-07-13', 1.00, 1.00, 1.00, 'Test', 1.00, 1.00, 1.00, 1.00, 0, 1.00),
(33, 'Stella', '2026-07-17', 30773.72, 0.00, 20541.50, '', 650.00, 45.00, 0.00, 0.00, 0, 0.00),
(34, 'Stella', '2026-07-17', 0.00, 0.00, 0.00, '', 405.00, 1043.49, 0.00, 0.00, 1, 0.00),
(35, 'Stella', '2026-07-17', 0.00, 0.00, 0.00, '', 0.00, 108.93, 0.00, 0.00, 2, 0.00),
(42, 'Stella', '2026-07-16', 19861.19, 0.00, 48501.24, '', 1345.03, 2050.50, 2450.00, 0.00, 0, 0.00),
(43, 'Stella', '2026-07-16', 0.00, 0.00, 0.00, '', 1470.00, 971.40, 0.00, 0.00, 1, 0.00),
(47, 'Stella', '2026-07-19', 17394.88, 0.00, 38793.99, 'CHRISTINE TULOT', 261.00, 29.00, 5000.00, 0.00, 0, 0.00),
(48, 'Stella', '2026-07-19', 0.00, 0.00, 0.00, 'CHRISTINE TULOT', 365.63, 2143.50, 0.00, 0.00, 1, 0.00),
(49, 'Stella', '2026-07-19', 0.00, 0.00, 0.00, '', 0.00, 815.22, 0.00, 0.00, 2, 0.00),
(50, 'Stella', '2026-07-23', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 0, 0.00),
(51, 'Stella', '2026-07-18', 100.00, 100.00, 100.00, '', 0.00, 100.00, 0.00, 0.00, 0, 100.00),
(52, 'Stella', '2026-07-18', 0.00, 0.00, 0.00, '', 0.00, 0.00, 0.00, 0.00, 1, 0.00),
(53, 'Stella', '2026-07-15', 29672.07, 0.00, 82571.56, '', 0.00, 2745.85, 0.00, 0.00, 0, 1647.51),
(54, 'Stella', '2026-07-15', 0.00, 0.00, 0.00, '', 0.00, 1647.51, 0.00, 0.00, 1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stella_disbursement`
--

CREATE TABLE `stella_disbursement` (
  `id` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'VAT',
  `address` varchar(255) DEFAULT '',
  `invoice_no` varchar(100) DEFAULT '',
  `account_title` varchar(255) DEFAULT '',
  `gross` decimal(15,2) DEFAULT 0.00,
  `input_tax` decimal(15,2) DEFAULT 0.00,
  `net_of_vat` decimal(15,2) DEFAULT 0.00,
  `particular` varchar(255) DEFAULT '',
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_disbursement`
--

INSERT INTO `stella_disbursement` (`id`, `entry_date`, `tin`, `company_name`, `vat_status`, `address`, `invoice_no`, `account_title`, `gross`, `input_tax`, `net_of_vat`, `particular`, `saved_by`, `created_at`, `updated_at`) VALUES
(11, '2026-07-15', '923-415-165-002', 'GULAY KO VEGETABLE SUPPLY', 'NV', 'DELEON SUPER TERMINAL MARKET,ILOILO CITY', 'NA', 'Fare & Transportation', 390.00, 0.00, 390.00, '', 'Stella', '2026-07-15 06:13:41', '2026-07-15 06:15:28');

-- --------------------------------------------------------

--
-- Table structure for table `stella_expenses`
--

CREATE TABLE `stella_expenses` (
  `id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `voucher_no` varchar(100) DEFAULT '',
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `document_no` varchar(100) DEFAULT '',
  `amount_w_vat` decimal(12,2) DEFAULT 0.00,
  `vat` decimal(12,2) DEFAULT 0.00,
  `amount_wo_vat` decimal(12,2) DEFAULT 0.00,
  `non_vat` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `purchases` decimal(12,2) DEFAULT 0.00,
  `salaries` decimal(12,2) DEFAULT 0.00,
  `rent` decimal(12,2) DEFAULT 0.00,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `lpg` decimal(12,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(12,2) DEFAULT 0.00,
  `fuel_trans` decimal(12,2) DEFAULT 0.00,
  `communication` decimal(12,2) DEFAULT 0.00,
  `transportation` decimal(12,2) DEFAULT 0.00,
  `light` decimal(12,2) DEFAULT 0.00,
  `drinking_water` decimal(12,2) DEFAULT 0.00,
  `water` decimal(12,2) DEFAULT 0.00,
  `sss_phic_hdmf` decimal(12,2) DEFAULT 0.00,
  `taxes_licences` decimal(12,2) DEFAULT 0.00,
  `office_supplies` decimal(12,2) DEFAULT 0.00,
  `kitchen_supplies` decimal(12,2) DEFAULT 0.00,
  `bio_pest_control` decimal(12,2) DEFAULT 0.00,
  `representation` decimal(12,2) DEFAULT 0.00,
  `miscellaneous` decimal(12,2) DEFAULT 0.00,
  `sir_budoy_nikki` decimal(12,2) DEFAULT 0.00,
  `staff_meal` decimal(12,2) DEFAULT 0.00,
  `admin_salary_shares` decimal(12,2) DEFAULT 0.00,
  `bank_fees` decimal(12,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(12,2) DEFAULT 0.00,
  `commission_fees` decimal(12,2) DEFAULT 0.00,
  `pest_control_bio_aug` decimal(12,2) DEFAULT 0.00,
  `marketing` decimal(12,2) DEFAULT 0.00,
  `sales_discounts` decimal(12,2) DEFAULT 0.00,
  `depreciation_expense` decimal(12,2) DEFAULT 0.00,
  `withdrawal` decimal(12,2) DEFAULT 0.00,
  `ca` decimal(12,2) DEFAULT 0.00,
  `pdc` decimal(12,2) DEFAULT 0.00,
  `row_total` decimal(12,2) DEFAULT 0.00,
  `selected_for_cf` tinyint(1) NOT NULL DEFAULT 0,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_income_statement`
--

CREATE TABLE `stella_income_statement` (
  `id` int(11) NOT NULL,
  `store_name` varchar(100) NOT NULL DEFAULT 'Stella',
  `stmt_date` date NOT NULL COMMENT 'Exact statement date (YYYY-MM-DD)',
  `stmt_year` smallint(4) NOT NULL DEFAULT 0,
  `stmt_month` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_day` tinyint(2) NOT NULL DEFAULT 0,
  `stmt_label` varchar(255) DEFAULT '',
  `net_sales` decimal(14,2) DEFAULT 0.00,
  `sales_discount` decimal(14,2) DEFAULT 0.00,
  `cost_of_sales` decimal(14,2) DEFAULT 0.00,
  `other_income_royalty` decimal(14,2) DEFAULT 0.00,
  `equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `depreciation_expense` decimal(14,2) DEFAULT 0.00,
  `transportation_fuel` decimal(14,2) DEFAULT 0.00,
  `lpg` decimal(14,2) DEFAULT 0.00,
  `rent` decimal(14,2) DEFAULT 0.00,
  `water_electricity` decimal(14,2) DEFAULT 0.00,
  `drinking_water` decimal(14,2) DEFAULT 0.00,
  `pest_control_bio` decimal(14,2) DEFAULT 0.00,
  `common_area_charges` decimal(14,2) DEFAULT 0.00,
  `exhaust_cleaning` decimal(14,2) DEFAULT 0.00,
  `salaries` decimal(14,2) DEFAULT 0.00,
  `office_equipment_supplies` decimal(14,2) DEFAULT 0.00,
  `philhealth_sss` decimal(14,2) DEFAULT 0.00,
  `medical_supplies` decimal(14,2) DEFAULT 0.00,
  `agency_fees` decimal(14,2) DEFAULT 0.00,
  `bank_fees` decimal(14,2) DEFAULT 0.00,
  `staff_meal` decimal(14,2) DEFAULT 0.00,
  `representation_benefits` decimal(14,2) DEFAULT 0.00,
  `professional_fees` decimal(14,2) DEFAULT 0.00,
  `communication` decimal(14,2) DEFAULT 0.00,
  `freight_storage` decimal(14,2) DEFAULT 0.00,
  `repairs_maintenance` decimal(14,2) DEFAULT 0.00,
  `sponsorship_marketing` decimal(14,2) DEFAULT 0.00,
  `taxes_licenses` decimal(14,2) DEFAULT 0.00,
  `system_development` decimal(14,2) DEFAULT 0.00,
  `construction_progress` decimal(14,2) DEFAULT 0.00,
  `insurance` decimal(14,2) DEFAULT 0.00,
  `admin_shares` decimal(14,2) DEFAULT 0.00,
  `miscellaneous_expense` decimal(14,2) DEFAULT 0.00,
  `vat_payment` decimal(14,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_income_statement`
--

INSERT INTO `stella_income_statement` (`id`, `store_name`, `stmt_date`, `stmt_year`, `stmt_month`, `stmt_day`, `stmt_label`, `net_sales`, `sales_discount`, `cost_of_sales`, `other_income_royalty`, `equipment_supplies`, `depreciation_expense`, `transportation_fuel`, `lpg`, `rent`, `water_electricity`, `drinking_water`, `pest_control_bio`, `common_area_charges`, `exhaust_cleaning`, `salaries`, `office_equipment_supplies`, `philhealth_sss`, `medical_supplies`, `agency_fees`, `bank_fees`, `staff_meal`, `representation_benefits`, `professional_fees`, `communication`, `freight_storage`, `repairs_maintenance`, `sponsorship_marketing`, `taxes_licenses`, `system_development`, `construction_progress`, `insurance`, `admin_shares`, `miscellaneous_expense`, `vat_payment`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, 'Stella', '2026-04-08', 2026, 4, 8, '', 1266692.91, 16745.20, 488600.90, 0.00, 2579.73, 0.00, 5202.00, 18586.00, 67110.40, 33439.65, 3200.00, 0.00, 0.00, 0.00, 205575.39, 14485.70, 0.00, 0.00, 0.00, 26153.76, 2650.00, 29452.85, 0.00, 6720.00, 0.00, 7375.02, 36771.66, 126.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Stella', '2026-04-08 04:33:11', '2026-04-08 04:33:11');

-- --------------------------------------------------------

--
-- Table structure for table `stella_month_end_inv`
--

CREATE TABLE `stella_month_end_inv` (
  `id` int(11) NOT NULL,
  `inv_date` date NOT NULL,
  `inv_year` int(4) NOT NULL,
  `inv_month` tinyint(2) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `category` varchar(50) NOT NULL,
  `sort_order` int(4) NOT NULL DEFAULT 0,
  `item_desc` varchar(200) NOT NULL DEFAULT '',
  `unit` varchar(20) NOT NULL DEFAULT 'BOTTLE',
  `supplier_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `end_inv_num` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_month_end_inv`
--

INSERT INTO `stella_month_end_inv` (`id`, `inv_date`, `inv_year`, `inv_month`, `store_name`, `category`, `sort_order`, `item_desc`, `unit`, `supplier_cost`, `end_inv_num`, `total_amount`, `saved_by`, `created_at`, `updated_at`) VALUES
(9, '2026-07-31', 2026, 7, 'Commissary', 'LIQUORS/BEVERAGES', 0, 'test', 'BOTTLE', 122.00, 122.0000, 14884.00, 'Commissary', '2026-07-01 04:25:13', '2026-07-01 04:25:13'),
(10, '2026-07-31', 2026, 7, 'Commissary', 'DRY GOODS', 0, 'test1', 'BOTTLE', 122.00, 122.0000, 14884.00, 'Commissary', '2026-07-01 04:25:14', '2026-07-01 04:25:14'),
(12, '2026-07-31', 2026, 7, 'Dois', 'LIQUORS/BEVERAGES', 0, 'Test', 'BOTTLE', 1.00, 210008.1100, 210008.11, 'Dois', '2026-07-01 09:49:06', '2026-07-01 09:49:06'),
(13, '2026-07-31', 2026, 7, 'Dois', 'LIQUORS/BEVERAGES', 1, 'test1', 'BOTTLE', 1.00, 210008.1100, 210008.11, 'Dois', '2026-07-01 10:01:00', '2026-07-01 10:01:00');

-- --------------------------------------------------------

--
-- Table structure for table `stella_pdc`
--

CREATE TABLE `stella_pdc` (
  `id` int(11) NOT NULL,
  `date_issued` date NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_pl_revenue`
--

CREATE TABLE `stella_pl_revenue` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `month` int(2) NOT NULL,
  `rev_type` varchar(50) NOT NULL DEFAULT 'vatable',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stella_reconcile`
--

CREATE TABLE `stella_reconcile` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `rec_year` int(4) NOT NULL,
  `rec_month` tinyint(2) NOT NULL,
  `ending_balance_bank` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Ending Balance per Bank',
  `deposits_in_transit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_checks` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_credits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ending_balance_books` decimal(12,2) DEFAULT NULL,
  `adjusted_bank_balance` decimal(12,2) DEFAULT NULL,
  `adjusted_book_balance` decimal(12,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_reconcile`
--

INSERT INTO `stella_reconcile` (`id`, `store_name`, `rec_year`, `rec_month`, `ending_balance_bank`, `deposits_in_transit`, `outstanding_checks`, `bank_credits`, `bank_charges`, `saved_by`, `created_at`, `updated_at`, `ending_balance_books`, `adjusted_bank_balance`, `adjusted_book_balance`) VALUES
(1, 'Stella', 2026, 7, 0.00, 0.00, 0.00, 0.00, 0.00, 'Stella', '2026-07-07 01:26:37', '2026-07-13 03:12:47', 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stella_sales_detail_rows`
--

CREATE TABLE `stella_sales_detail_rows` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `report_date` date NOT NULL,
  `section` varchar(40) NOT NULL,
  `item_name` varchar(150) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_sales_detail_rows`
--

INSERT INTO `stella_sales_detail_rows` (`id`, `store_name`, `report_date`, `section`, `item_name`, `amount`, `sort_order`) VALUES
(99, 'Stella', '2026-07-13', 'marketing_pullout', 'Test', 1.00, 0),
(100, 'Stella', '2026-07-13', 'grab', 'Test', 1.00, 0),
(101, 'Stella', '2026-07-13', 'expenses', 'Test', 1.00, 0),
(102, 'Stella', '2026-07-13', 'late_payment', 'Test', 1.00, 0),
(103, 'Stella', '2026-07-13', 'advance_payment', 'Test', 1.00, 0),
(104, 'Stella', '2026-07-13', 'gc_sponsorship', 'Test', 1.00, 0),
(105, 'Stella', '2026-07-13', 'gc_sold', 'Test', 1.00, 0),
(106, 'Stella', '2026-07-13', 'paid_med', 'Test', 1.00, 0),
(107, 'Stella', '2026-07-13', 'deposit_card', 'Test', 1.00, 0),
(108, 'Stella', '2026-07-13', 'cash_out_interest', 'Test', 1.00, 0),
(239, 'Stella', '2026-07-17', 'marketing_pullout', '', 350.00, 0),
(240, 'Stella', '2026-07-17', 'marketing_pullout', '', 650.00, 1),
(241, 'Stella', '2026-07-17', 'grab', '', 7060.00, 0),
(242, 'Stella', '2026-07-17', 'expenses', '', 0.00, 0),
(243, 'Stella', '2026-07-17', 'late_payment', '', 0.00, 0),
(244, 'Stella', '2026-07-17', 'advance_payment', '', 365.63, 0),
(245, 'Stella', '2026-07-17', 'advance_payment', '', 1525.19, 1),
(246, 'Stella', '2026-07-17', 'advance_payment', '', 13425.00, 2),
(247, 'Stella', '2026-07-17', 'advance_payment', '', 6050.00, 3),
(248, 'Stella', '2026-07-17', 'advance_payment', '', 500.00, 4),
(249, 'Stella', '2026-07-17', 'advance_payment', '', 240.00, 5),
(250, 'Stella', '2026-07-17', 'gc_sponsorship', '', 0.00, 0),
(251, 'Stella', '2026-07-17', 'gc_sold', '', 0.00, 0),
(252, 'Stella', '2026-07-17', 'paid_med', '', 731.25, 0),
(253, 'Stella', '2026-07-17', 'paid_med', '', 1620.00, 1),
(254, 'Stella', '2026-07-17', 'paid_med', '', 110.00, 2),
(255, 'Stella', '2026-07-17', 'deposit_card', '', 9840.00, 0),
(256, 'Stella', '2026-07-17', 'deposit_card', '', 2018.75, 1),
(257, 'Stella', '2026-07-17', 'deposit_card', '', 1120.00, 2),
(258, 'Stella', '2026-07-17', 'deposit_card', '', 2130.00, 3),
(259, 'Stella', '2026-07-17', 'gift_cert_tips', '', 0.00, 0),
(260, 'Stella', '2026-07-17', 'cash_out_interest', '', 3953.00, 0),
(261, 'Stella', '2026-07-09', 'marketing_pullout', '', 0.00, 0),
(262, 'Stella', '2026-07-09', 'grab', '', 0.00, 0),
(263, 'Stella', '2026-07-09', 'expenses', '', 0.00, 0),
(264, 'Stella', '2026-07-09', 'late_payment', '', 0.00, 0),
(265, 'Stella', '2026-07-09', 'advance_payment', '', 0.00, 0),
(266, 'Stella', '2026-07-09', 'gc_sponsorship', '', 0.00, 0),
(267, 'Stella', '2026-07-09', 'gc_sold', '', 0.00, 0),
(268, 'Stella', '2026-07-09', 'paid_med', '', 0.00, 0),
(269, 'Stella', '2026-07-09', 'gift_cert_tips', '', 0.00, 0),
(303, 'Stella', '2026-07-16', 'marketing_pullout', '', 1300.00, 0),
(304, 'Stella', '2026-07-16', 'grab', '', 8145.00, 0),
(305, 'Stella', '2026-07-16', 'expenses', '', 0.00, 0),
(306, 'Stella', '2026-07-16', 'late_payment', '', 0.00, 0),
(307, 'Stella', '2026-07-16', 'advance_payment', '', 804.97, 0),
(308, 'Stella', '2026-07-16', 'advance_payment', '', 2450.00, 1),
(309, 'Stella', '2026-07-16', 'advance_payment', '', 690.00, 2),
(310, 'Stella', '2026-07-16', 'gc_sponsorship', '', 0.00, 0),
(311, 'Stella', '2026-07-16', 'gc_sold', '', 0.00, 0),
(312, 'Stella', '2026-07-16', 'paid_med', '', 0.00, 0),
(313, 'Stella', '2026-07-16', 'deposit_card', '', 0.00, 0),
(314, 'Stella', '2026-07-16', 'gift_cert_tips', '', 0.00, 0),
(315, 'Stella', '2026-07-16', 'cash_out_interest', '', 0.00, 0),
(334, 'Stella', '2026-07-19', 'marketing_pullout', '', 0.00, 0),
(335, 'Stella', '2026-07-19', 'grab', '8 OS', 5340.00, 0),
(336, 'Stella', '2026-07-19', 'expenses', '', 0.00, 0),
(337, 'Stella', '2026-07-19', 'late_payment', 'CHERISH (ZUELLIG)', 1739.33, 0),
(338, 'Stella', '2026-07-19', 'advance_payment', 'AEROL PFIZER', 2645.88, 0),
(339, 'Stella', '2026-07-19', 'advance_payment', 'DEPARTMENT OF AGRICULTURE PAID VIA BANK TRANSER', 1500.00, 1),
(340, 'Stella', '2026-07-19', 'advance_payment', 'YANIE (ZUELLIG)', 1546.07, 2),
(341, 'Stella', '2026-07-19', 'advance_payment', 'JADE (UNILAB)', 2710.00, 3),
(342, 'Stella', '2026-07-19', 'gc_sponsorship', '', 0.00, 0),
(343, 'Stella', '2026-07-19', 'gc_sold', '', 0.00, 0),
(344, 'Stella', '2026-07-19', 'paid_med', 'AEROL PFIZER', 206.01, 0),
(345, 'Stella', '2026-07-19', 'paid_med', 'CHERISH (ZUELLIG)', 1739.33, 1),
(346, 'Stella', '2026-07-19', 'paid_med', 'ANTHONY (ZUELLIG) SWIPE VIA MAYA', 1739.33, 2),
(347, 'Stella', '2026-07-19', 'paid_med', 'MARK (MERCK SHARP)', 510.00, 3),
(348, 'Stella', '2026-07-19', 'deposit_card', 'ANTHONY (ZUELLIG)', 1140.67, 0),
(349, 'Stella', '2026-07-19', 'deposit_card', 'MARK (MERCK SHARP)', 740.00, 1),
(350, 'Stella', '2026-07-19', 'gift_cert_tips', 'MARK MERKSHARK ', 3000.00, 0),
(351, 'Stella', '2026-07-19', 'cash_out_interest', '', 0.00, 0),
(352, 'Stella', '2026-07-23', 'marketing_pullout', '', 0.00, 0),
(353, 'Stella', '2026-07-23', 'grab', '', 0.00, 0),
(354, 'Stella', '2026-07-23', 'expenses', '', 0.00, 0),
(355, 'Stella', '2026-07-23', 'late_payment', '', 0.00, 0),
(356, 'Stella', '2026-07-23', 'advance_payment', '', 0.00, 0),
(357, 'Stella', '2026-07-23', 'gc_sponsorship', '', 0.00, 0),
(358, 'Stella', '2026-07-23', 'gc_sold', '', 0.00, 0),
(359, 'Stella', '2026-07-23', 'paid_med', '', 0.00, 0),
(360, 'Stella', '2026-07-23', 'deposit_card', '', 0.00, 0),
(361, 'Stella', '2026-07-23', 'gift_cert_tips', '', 0.00, 0),
(362, 'Stella', '2026-07-23', 'cash_out_interest', '', 0.00, 0),
(363, 'Stella', '2026-07-18', 'marketing_pullout', '', 0.00, 0),
(364, 'Stella', '2026-07-18', 'grab', '', 0.00, 0),
(365, 'Stella', '2026-07-18', 'expenses', '', 0.00, 0),
(366, 'Stella', '2026-07-18', 'late_payment', '', 0.00, 0),
(367, 'Stella', '2026-07-18', 'advance_payment', '', 0.00, 0),
(368, 'Stella', '2026-07-18', 'gc_sponsorship', '', 0.00, 0),
(369, 'Stella', '2026-07-18', 'gc_sold', '', 0.00, 0),
(370, 'Stella', '2026-07-18', 'paid_med', '', 0.00, 0),
(371, 'Stella', '2026-07-18', 'deposit_card', '', 0.00, 0),
(372, 'Stella', '2026-07-18', 'gift_cert_tips', '', 0.00, 0),
(373, 'Stella', '2026-07-18', 'cash_out_interest', '', 0.00, 0),
(374, 'Stella', '2026-07-15', 'marketing_pullout', '', 1050.00, 0),
(375, 'Stella', '2026-07-15', 'marketing_pullout', '', 650.00, 1),
(376, 'Stella', '2026-07-15', 'marketing_pullout', '', 500.00, 2),
(377, 'Stella', '2026-07-15', 'grab', '', 4280.00, 0),
(378, 'Stella', '2026-07-15', 'expenses', '', 0.00, 0),
(379, 'Stella', '2026-07-15', 'late_payment', '', 0.00, 0),
(380, 'Stella', '2026-07-15', 'advance_payment', '', 4000.00, 0),
(381, 'Stella', '2026-07-15', 'advance_payment', '', 2003.40, 1),
(382, 'Stella', '2026-07-15', 'advance_payment', '', 2800.00, 2),
(383, 'Stella', '2026-07-15', 'advance_payment', '', 365.63, 3),
(384, 'Stella', '2026-07-15', 'gc_sponsorship', '', 0.00, 0),
(385, 'Stella', '2026-07-15', 'gc_sold', '', 0.00, 0),
(386, 'Stella', '2026-07-15', 'paid_med', '', 0.00, 0),
(387, 'Stella', '2026-07-15', 'deposit_card', '', 0.00, 0),
(388, 'Stella', '2026-07-15', 'gift_cert_tips', '', 0.00, 0),
(389, 'Stella', '2026-07-15', 'cash_out_interest', '', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `stella_sales_report`
--

CREATE TABLE `stella_sales_report` (
  `id` int(11) NOT NULL,
  `store_name` varchar(50) NOT NULL DEFAULT 'Stella',
  `report_date` date NOT NULL,
  `gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `z_reading_gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_swipe_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_payment_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `maya_swipe` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unpaid_med_credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grab_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_transfer_cheque` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pcf_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `coh` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cash on Hand',
  `net_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `short_over` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `paid_med_card` decimal(12,2) NOT NULL DEFAULT 0.00,
  `advance_paid_appigo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash_out_interest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gift_cert_sold_tips` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pos_z_reading` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cashier_name` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stella_sales_report`
--

INSERT INTO `stella_sales_report` (`id`, `store_name`, `report_date`, `gross_sales`, `service_charge`, `z_reading_gross`, `total_swipe`, `deposit_swipe_card`, `late_payment_card`, `maya_swipe`, `unpaid_med_credit`, `grab_sales`, `gcash`, `gift_card`, `marketing_pull_out`, `discount`, `bank_transfer_cheque`, `pcf_expenses`, `other_expenses`, `coh`, `net_sales`, `short_over`, `saved_by`, `created_at`, `updated_at`, `paid_med_card`, `advance_paid_appigo`, `cash_out_interest`, `gift_cert_sold_tips`, `pos_z_reading`, `cashier_name`) VALUES
(9, 'Stella', '2026-07-13', 11.00, 1.00, 0.00, 4.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, -2.00, 3.00, 'Stella', '2026-07-13 03:33:22', '2026-07-13 03:34:03', 1.00, 1.00, 1.00, 1.00, 12.00, 'Test testing'),
(12, 'Stella', '2026-07-15', 126572.57, 5160.96, 0.00, 82571.56, 0.00, 0.00, 82571.56, 0.00, 4280.00, 0.00, 1647.51, 2200.00, 4393.36, 0.00, 15099.50, 0.00, 9413.00, 9411.61, 1.39, 'Stella', '2026-07-15 10:21:28', '2026-07-27 04:42:05', 0.00, 9169.03, 0.00, 0.00, 128772.57, ''),
(18, 'Stella', '2026-07-16', 86285.08, 2454.25, 0.00, 48501.24, 0.00, 0.00, 48501.24, 2815.03, 8145.00, 0.00, 0.00, 1300.00, 3021.90, 2450.00, 16220.20, 0.00, 19861.19, 1186.74, 18674.45, 'Stella', '2026-07-16 07:31:46', '2026-07-16 08:23:26', 0.00, 3944.97, 0.00, 0.00, 87585.08, ''),
(21, 'Stella', '2026-07-17', 86771.59, 2376.12, 0.00, 38111.50, 15108.75, 0.00, 20541.50, 1055.00, 7060.00, 0.00, 0.00, 1000.00, 1197.42, 0.00, 27053.59, 0.00, 0.00, 1344.01, -1344.01, 'Stella', '2026-07-16 08:35:01', '2026-07-16 08:35:01', 2461.25, 22105.82, 3953.00, 0.00, 87771.59, ''),
(23, 'Stella', '2026-07-09', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Stella', '2026-07-16 09:53:14', '2026-07-16 09:53:14', 0.00, 0.00, 0.00, 0.00, 0.00, ''),
(24, 'Stella', '2026-07-18', 500.00, 0.00, 0.00, 100.00, 0.00, 0.00, 100.00, 0.00, 0.00, 100.00, 100.00, 0.00, 100.00, 0.00, 0.00, 0.00, 100.00, 100.00, 0.00, 'Stella', '2026-07-16 10:38:14', '2026-07-23 08:27:24', 0.00, 0.00, 0.00, 0.00, 500.00, ''),
(28, 'Stella', '2026-07-19', 79754.79, 2985.05, 0.00, 49608.66, 4880.67, 1739.33, 38793.99, 626.63, 5340.00, 0.00, 0.00, 0.00, 2987.72, 5000.00, 19505.50, 0.00, 0.00, -5095.67, 5095.67, 'Stella', '2026-07-17 01:13:02', '2026-07-17 01:13:25', 4194.67, 8401.95, 0.00, 0.00, 79754.79, ''),
(30, 'Stella', '2026-07-23', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 'Stella', '2026-07-23 08:25:55', '2026-07-23 08:25:55', 0.00, 0.00, 0.00, 0.00, 0.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `summary_reports`
--

CREATE TABLE `summary_reports` (
  `id` int(11) NOT NULL,
  `report_month` date NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `total_gross_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_discounts` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_cogs` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_manpower` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_other_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_net_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `quota_target` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `summary_reports`
--

INSERT INTO `summary_reports` (`id`, `report_month`, `store_name`, `total_gross_sales`, `total_discounts`, `total_cogs`, `total_manpower`, `total_other_expenses`, `total_net_profit`, `quota_target`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(30, '2026-07-01', 'Dois', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'Dois', '2026-07-23 10:00:35', '2026-07-23 10:00:35'),
(31, '2026-07-01', 'Dois', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'Dois', '2026-07-23 10:01:12', '2026-07-23 10:01:12'),
(32, '2026-07-01', 'Dois', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'Dois', '2026-07-23 10:01:38', '2026-07-23 10:01:38'),
(33, '2026-07-01', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000.00, NULL, 'H', '2026-07-29 07:09:28', '2026-07-29 07:09:28'),
(34, '2026-07-01', 'HEROCARWASH', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 300000.00, NULL, 'H', '2026-07-29 07:09:34', '2026-07-29 07:09:34'),
(35, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'DemicLab-Main', '2026-07-30 06:58:33', '2026-07-30 06:58:33'),
(36, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'DemicLab-Main', '2026-07-30 06:58:38', '2026-07-30 06:58:38'),
(37, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'DemicLab-Main', '2026-07-30 06:58:40', '2026-07-30 06:58:40'),
(38, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3000000.00, NULL, 'DemicLab-Main', '2026-07-30 06:58:45', '2026-07-30 06:58:45'),
(39, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3500000.00, NULL, 'DemicLab-Main', '2026-07-31 05:57:22', '2026-07-31 05:57:22'),
(40, '2026-07-01', 'DemicLab-Main', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 3500000.00, NULL, 'DemicLab-Main', '2026-07-31 05:57:29', '2026-07-31 05:57:29');

-- --------------------------------------------------------

--
-- Table structure for table `summary_report_entries`
--

CREATE TABLE `summary_report_entries` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `store_name` varchar(100) NOT NULL,
  `tips_gift_cert` decimal(12,2) DEFAULT 0.00,
  `booky_fees_income` decimal(12,2) DEFAULT 0.00,
  `store_gross` decimal(12,2) DEFAULT 0.00,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `cash_for_depo` decimal(12,2) DEFAULT 0.00,
  `sales_of_day_swipe` decimal(12,2) DEFAULT 0.00,
  `deposit_swipe` decimal(12,2) DEFAULT 0.00,
  `late_payment` decimal(12,2) DEFAULT 0.00,
  `cancelled_transaction` decimal(12,2) DEFAULT 0.00,
  `unpaid` decimal(12,2) DEFAULT 0.00,
  `paid` decimal(12,2) DEFAULT 0.00,
  `advance_payment` decimal(12,2) DEFAULT 0.00,
  `grab` decimal(12,2) DEFAULT 0.00,
  `bank_trans` decimal(12,2) DEFAULT 0.00,
  `gcash` decimal(12,2) DEFAULT 0.00,
  `gc_sponsor_marketing` decimal(12,2) DEFAULT 0.00,
  `gc_sold` decimal(12,2) DEFAULT 0.00,
  `discount` decimal(12,2) DEFAULT 0.00,
  `marketing_pull_out` decimal(12,2) DEFAULT 0.00,
  `personal` decimal(12,2) DEFAULT 0.00,
  `pcf` decimal(12,2) DEFAULT 0.00,
  `other_expenses` decimal(12,2) DEFAULT 0.00,
  `sc_for_depo` decimal(12,2) DEFAULT 0.00,
  `total_deductions` decimal(12,2) DEFAULT 0.00,
  `short_over` decimal(12,2) DEFAULT 0.00,
  `total_swipe` decimal(12,2) DEFAULT 0.00,
  `cash_deposit` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `other_sales` decimal(12,2) DEFAULT 0.00,
  `remarks2` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `summary_report_entries`
--

INSERT INTO `summary_report_entries` (`id`, `report_date`, `store_name`, `tips_gift_cert`, `booky_fees_income`, `store_gross`, `total_sales`, `cash_for_depo`, `sales_of_day_swipe`, `deposit_swipe`, `late_payment`, `cancelled_transaction`, `unpaid`, `paid`, `advance_payment`, `grab`, `bank_trans`, `gcash`, `gc_sponsor_marketing`, `gc_sold`, `discount`, `marketing_pull_out`, `personal`, `pcf`, `other_expenses`, `sc_for_depo`, `total_deductions`, `short_over`, `total_swipe`, `cash_deposit`, `remarks`, `other_sales`, `remarks2`, `saved_by`, `created_at`, `updated_at`) VALUES
(121, '2026-07-01', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-13 05:24:06', '2026-07-13 05:26:48'),
(124, '2026-07-02', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:02', '2026-07-23 08:26:02'),
(125, '2026-07-03', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(126, '2026-07-04', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(127, '2026-07-05', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(128, '2026-07-06', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(129, '2026-07-07', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(130, '2026-07-08', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 1.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(131, '2026-07-09', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:03', '2026-07-23 08:26:03'),
(132, '2026-07-10', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(133, '2026-07-11', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(134, '2026-07-12', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(135, '2026-07-13', 'Stella', 1.00, 0.00, 10.00, 11.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, 0.00, 1.00, 1.00, 1.00, 12.00, 3.00, 4.00, 0.00, '0', 1.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(136, '2026-07-14', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(137, '2026-07-15', 'Stella', 0.00, 0.00, 124372.57, 124372.57, 9413.00, 24072.63, 0.00, 0.00, 0.00, 0.00, 0.00, 9169.03, 4280.00, 0.00, 0.00, 0.00, 1647.51, 4393.36, 2200.00, 0.00, 15099.50, 0.00, 5160.96, 60862.03, -56297.54, 24072.63, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(138, '2026-07-16', 'Stella', 0.00, 0.00, 86285.08, 86285.08, 19861.19, 48501.24, 0.00, 0.00, 0.00, 2815.03, 0.00, 3944.97, 8145.00, 2450.00, 0.00, 0.00, 0.00, 3021.90, 1300.00, 0.00, 16220.20, 0.00, 2454.25, 86398.34, 18674.45, 48501.24, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:04', '2026-07-23 08:26:04'),
(139, '2026-07-17', 'Stella', 0.00, 0.00, 86771.59, 86771.59, 0.00, 20541.50, 15108.75, 0.00, 0.00, 1055.00, 2461.25, 22105.82, 7060.00, 0.00, 0.00, 0.00, 0.00, 1197.42, 1000.00, 0.00, 27053.59, 0.00, 2376.12, 82474.58, -1344.01, 38111.50, 0.00, '0', 3953.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(140, '2026-07-18', 'Stella', 0.00, 0.00, 600.00, 600.00, 100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 100.00, 0.00, 100.00, 100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 400.00, -100.00, 100.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(141, '2026-07-19', 'Stella', 0.00, 0.00, 79754.79, 79754.79, 0.00, 38793.99, 4880.67, 1739.33, 0.00, 626.63, 4194.67, 8401.95, 5340.00, 5000.00, 0.00, 0.00, 0.00, 2987.72, 0.00, 0.00, 19505.50, 0.00, 2985.05, 84850.46, 5095.67, 49608.66, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(142, '2026-07-20', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(143, '2026-07-21', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(144, '2026-07-22', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(145, '2026-07-23', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(146, '2026-07-24', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(147, '2026-07-25', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(148, '2026-07-26', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(149, '2026-07-27', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:05', '2026-07-23 08:26:05'),
(150, '2026-07-28', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:06', '2026-07-23 08:26:06'),
(151, '2026-07-29', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:06', '2026-07-23 08:26:06'),
(152, '2026-07-30', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:06', '2026-07-23 08:26:06'),
(153, '2026-07-31', 'Stella', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '0', 0.00, '0', 'Stella', '2026-07-23 08:26:06', '2026-07-23 08:26:06');

-- --------------------------------------------------------

--
-- Stand-in structure for view `unified_daily_reports`
-- (See below for the actual view)
--
CREATE TABLE `unified_daily_reports` (
`id` decimal(10,0)
,`report_date` date
,`store_name` varchar(100)
,`gross_sales` decimal(12,2)
,`sales_discount` decimal(12,2)
,`gulay_commissary` decimal(12,2)
,`direct_purchases` decimal(12,2)
,`other_expenses` decimal(34,2)
,`kitchen_manpower` decimal(12,2)
,`frontline_manpower` decimal(12,2)
,`overtime` decimal(12,2)
,`undertime` decimal(12,2)
,`quota_target` decimal(12,2)
,`cogs_threshold` decimal(5,2)
,`mp_threshold` decimal(5,2)
,`np_threshold` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Admin User', 'admin@saleshub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-03-26 04:29:59'),
(2, 'Jane Manager', 'jane@saleshub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '2026-03-26 04:29:59');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_masterlist`
--

CREATE TABLE `vendor_masterlist` (
  `id` int(11) NOT NULL,
  `tin` varchar(100) NOT NULL DEFAULT '',
  `company_name` varchar(255) NOT NULL DEFAULT '',
  `vat_status` varchar(10) NOT NULL DEFAULT 'V',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `contact` varchar(150) DEFAULT '',
  `notes` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_masterlist_unified`
--

CREATE TABLE `vendor_masterlist_unified` (
  `id` int(11) NOT NULL,
  `tin` varchar(100) DEFAULT '',
  `company_name` varchar(255) DEFAULT '',
  `vat_status` varchar(10) DEFAULT 'V',
  `address` varchar(255) DEFAULT '',
  `particulars` varchar(255) DEFAULT '',
  `document_type` varchar(100) DEFAULT '',
  `contact` varchar(150) DEFAULT '',
  `notes` text DEFAULT NULL,
  `saved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_masterlist_unified`
--

INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(1, '006-126-000-00000', 'MESTIZO BY EMILION (TFM ALLIANCE, INC.)', 'VAT', 'GEN.LUNA ST., BRGY. INDAY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:58', '2026-07-13 03:04:58'),
(2, '027-732-961-000', 'SSG CONVINIENCE STORE', 'VAT', 'CORNERS GERONA AND LAKANDULA STS, POBLACION GUIMBAL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:58', '2026-07-13 03:04:58'),
(3, '712-202-849-001', '(IIAOIIAO) MCA 5 FOOD SERVICE', 'VAT', 'GROUND LEVEL NORTH POINT SM CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:58', '2026-07-13 03:04:58'),
(4, '234-247-904-001', '0330 GENERAL MERCHANDISE', 'VAT', 'MAGINHAWA ST., UP VILLAGE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(5, '476-865-499-000', '1688 HARDWARE INC.', 'VAT', '2ND FLOOR 1688 MALL CORNER LEDESMA & QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(6, '476-865-499-0000', '1688 HARDWARE, INC.', 'VAT', 'LEDESMA& QUEZON STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(7, '603-763-134-000', '1LOILO INC.', 'VAT', 'JAP BLDG.,JV JOCSON ST., DULONAN AREVALO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(8, '174-218-911-000', '2EL\'S TOY MART', 'VAT', 'FESTIVE WALK MALL, MEGAWORLD BLVD. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(9, '000-313-401-000', '2GO GROUP INC.', 'VAT', 'TAFT AVE., ERMITA MANILA', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(10, '008-933-798-010', '2RANP TRADING CORP.', 'VAT', 'SM CITY ILOILO, DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(11, '401-774-644-015', '365 DESIGNS RETAILING INC.', 'VAT', 'SM MALL OF ASIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(12, '145-338-357-000', '3E AUTO GLASS', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(13, '940-766-840-006', '3RD GEN GLORY\'S CAFÉ', 'VAT', 'SEN. BENIGNO AQUINO AVENUE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(14, '285-997-454-003', '3R\'S AND A INTERNET STATION', 'NV', 'CYBERZONE, SM CITY,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(15, '927-408-292-001', '40J COPY CENTER SERVICES', 'NV', 'E.LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(16, '616-573-607-000', '4A\'S PARTNERS CO.', 'VAT', 'LANDHEIGHTSVILLE SUBD., TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(17, '300-781-334-000', '4HG EGG CENTER', 'NV', 'BRGY. FLORES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(18, '168-256-796-001', '4TH OF JULY COPY CENTER&SERVICES', 'NV', 'JALANDONI ST., COR AURORA SUBD., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(19, '760-168-255-00000', '5 BOXES VENTURES CORP.', 'VAT', 'GOLDEN AC BLDG., E. LOPEZ ST., SAN VICENTE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(20, '760-168-255-000', '5BOXES VENTURES CORP.', 'VAT', 'E LOPEZ ST.,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(21, '741-386-726-000', '5L MMPJR TRADING AND MARKETING INC.', 'VAT', 'NO.43 PANSOL AVE., PANSOL3, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(22, '426-863-880-000', '5N PLAINFIELD MARKETING INC', 'VAT', 'LMT BUILDING DE LEON ST.,I.C.', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(23, '627-948-636-00001', '5S DISTRIBUTORS, INC', 'VAT', 'ZONE 1 TICUD LAPAZ 5000 CITY OF ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(24, '207-742-577-003', '6 ECHOES FOOD CART', 'VAT', 'FISHERMALL QUEZON AVENUE SANTA CRUZ QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(25, '007-127-951-000', '628 MERCHANDISING CORPORATION', 'VAT', 'ANGELES CITY, PAMPANGA', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(26, '722-177-444-000', '668 ONION AND GARLIC TRADING', 'NV', 'J.DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(27, '010-765-564-000', '7 ELEVEN (SEPTONZE CORPORATION)', 'VAT', 'FORT BONIFACIO, NICHOLS-MCKINLEY, TAGUIG', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(28, '000-390-189-1613', '7-ELEVEN', 'VAT', 'GAISANO ILOILO CITY CENTER, DIVERSION RD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(29, '000-390-189-1496', '7-ELEVEN', 'VAT', 'GAISANO ILOILO CITY CENTER, DIVERSION RD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(30, '140-539-955-000', '7-ELEVEN (IGLESIA, EDGAR GAYAS)', 'VAT', 'BANGGA BANTE ZARAGA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(31, '107-168-357-011', '7-ELEVEN (MARIANO M. MAGBANUA JR)', 'VAT', 'POBLACION BAROTAC VIEJO ILOILO PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(32, '000-390-189-02178', '7-ELEVEN (PHILIPPINE SEVEN CORPORATION)', 'VAT', 'MUNTINLUPA CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(33, '193-941-219-000', '8 VILLA BEACH HOUSE', 'VAT', '#8 VILLA BEACH STO. NINO SUR AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(34, '447-793-075-000', '89-S DITRIBUTION INC', 'VAT', 'LAPUZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(35, '009-836-926-002', 'A MANO RISTORANTE (AMACO INC.)', 'VAT', 'BONIFACIO GLOBAL CITY FORT BONIFACIO, TAGUIG CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(36, '171-171-508-000', 'A.C. PAINT CENTER', 'NV', 'BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(37, '117-322-545-000', 'A.FORMACION STORE', 'NV', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(38, '008-383-125-007', 'A2Z DRIVING ACADEMY INC.,', 'VAT', 'BRGY.HIBAO AN SUR MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(39, '000-299-299-218', 'ABACUS BOOK AND CARD CORP.', 'VAT', 'GROUND FLOOR A18 FESTIVE WALK MALL Q. ABETO-MIRASOL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(40, '000-299-299-047', 'ABACUS BOOK AND CARD CORP.', 'VAT', 'B. AQUINO AVE. , JARO WEST DIVERSION RD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(41, '000-299-299-182', 'ABACUS BOOK AND CARD CORP.', 'VAT', 'ROBINSON,E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(42, '000-299-299-044', 'ABACUS BOOK AND CARD CORP.', 'VAT', 'ROBINSONS PLACE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(43, '000-299-299-224', 'ABACUS BOOK AND CARD CORP.', 'VAT', 'BRGY. UNGKA II ROBINSONS PAVIA', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(44, '000-299-299-000', 'ABACUS BOOK AND CARD CORP. (NATIONAL BOOK STORE)', 'VAT', 'MANDALUYONG, NCR', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(45, '177-219-290-000', 'ABS DISTRIBUTOR', 'VAT', 'JEREOS ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(46, '006-460-948-000', 'ACCLAIM WEST INTERNET STATION', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(47, '200-035-311-000', 'ACE HARDWARE PHILIPPINES INC', 'VAT', 'SM MEGAMALL BLDG VARGAS MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(48, '200-035-311-023', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'SM DELGADO COR DELGADO-VALERIA STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(49, '000-035-311-023', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'VALERIA-DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(50, '200-036-311-023', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(51, '200-036-311-019', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(52, '200-035-811-019', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(53, '200-035-311-019', 'ACE HARDWARE PHILIPPINES, INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVE., MANDURRIO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(54, '450-151-844-000', 'ACOL BRIGHT IDEAS TRADING INC.', 'VAT', 'BURGOS ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(55, '010-077-521-000', 'ACROPOLIS FOOD AND BEVERAGE CORP.', 'VAT', 'ROBINSONS PLACE PUROK 5 SAN ANGEL, SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(56, '647-863-937-001', 'ADASTRIA PHILIPPINES INC.', 'VAT', 'MALL OF ASIA COMPLEX, BRGY. 76 7300, PASAY CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(57, '219-197-699-022', 'ADDIDAS LIFESTYLE IC.-ILOILO', 'VAT', 'LGF SM CITY ILOILO BENIGNO AQUINO AVE., MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(58, '177-216-837-004', 'ADELFA LEPURA BORRO', 'VAT', 'BENIGNO AQUINO AVE.DIVERSION,BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(59, '442-373-090-000', 'ADVERTISING MATERIALS SUPPLY,INC.', 'VAT', 'DOOR 3,TRIPLE ALLIANCE REALTY & SALES CORP.BLDG.,VALERIA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(60, '669-560-310-00006', 'AENOVA TECH OPC', 'VAT', '3/F SM CITY BOLILAO,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(61, '008-757-256-039', 'AEON FANTASY GROUP PHILIPPINES INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(62, '200-422-170-039', 'AEROPHONE ENTERPRISES & CO', 'VAT', 'ROBINSONS PLACE ILOILO, LEDESMA, COR, QUEZON STS BRGY ROXAS VILLAGE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(63, '125-291-690-000', 'AFTER 7 CARWASH SERVICES', 'NV', 'NARRA DRIVE, SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(64, '117-686-332-000', 'AGRI-MACHINERIES', 'VAT', 'J&B BLDG. 11 MABINI-LEDESMA STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(65, '006-125-756-000', 'AGUILLON VENTURES, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(66, '474-715-631-000', 'AI FASHION ACCESSORIES', 'VAT', 'LEDESMA ST., BRGY. KAUSWAGAN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(67, '256-988-514-000', 'AIM VALUE VENTURES', 'VAT', 'GEN. LUNA STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(68, '010-018-864-000', 'AIM VALUE VENTURES INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(69, '005-983-911-000', 'AIVEE\'S CLOTHES STATION, INC.', 'VAT', 'LEDESCO VILLAGE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(70, '009-816-479-000', 'AJ MANOKAN NG BAYAN INC.', 'VAT', 'ZONE 2 RIZAL ST., PALA-PALA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(71, '002-886-900-0001', 'AKAMON FOOD INC.', 'VAT', 'BUHANG TAFT NORTH MANDURRIAO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(72, '622-886-900-001', 'AKAMON FOOD INC.', 'VAT', 'BUHANG TAFT NORTH MANDURRIAO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(73, '022-886-900-0001', 'AKAMON FOOD INC.', 'VAT', 'BUHANG TAFT NORTH MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(74, '448-166-367-001', 'AKAMON RAMEN RESTAURANT', 'VAT', 'SM STRATA BENIGNO AQUINO AVE., MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(75, '448-166-367-000', 'AKAMON RAMEN RESTAURANT', 'VAT', 'SM CITY STRATA B.AQUINO AVE.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(76, '480-197-442-000', 'AKLAN ROASTED OUTLET, INC.', 'NV', 'BRGY. SAN PEDRO, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(77, '480-197-442-002', 'AKLAN ROASTED OUTLET, INC.', 'NV', 'BRGY. SAN RAFAEL DIVERSION RD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(78, '480-197-422-002', 'AKLAN ROASTED OUTLET, INC.', 'NV', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(79, '219-643-505-001', 'AL VINCENT S. JUANTA', 'VAT', 'CITY OF PASIG', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(80, '921-024-090-000', 'ALADDIN TRADE CENTER', 'VAT', '123 IZNART ST., BRGY. MAGSAYSAY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(81, '004-621-038-022', 'ALBERTO SHOE CORPORATION', 'VAT', 'SM CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(82, '930-475-449-000', 'ALDEGUER SUPERMARKET', 'VAT', 'ALDEGUER ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(83, '114-796-019-003', 'ALEMAR GRAPHIC ADS', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(84, '114-796-019-005', 'ALEMAR GRAPHIC ADS', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(85, '908-782-912-000', 'ALEX BIKE SHOP', 'VAT', 'ATRIA AEXTENSION, SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(86, '707-834-570-000', 'ALEX MOTORCYCLE PARTS SHOP', 'NV', 'SAN ROQUE CORNER BURGOS ST. POBLACION III ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(87, '008-720-052-051', 'ALFAMETRO MARKETING, INC.', 'VAT', 'STO. NINO VILLAGE TUNASAN', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(88, '942-461-376-00002', 'ALICIAS BATCHOYAN', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(89, '265-116-204-000', 'ALICIAS RESTAURANT', 'VAT', 'BRGY. DEMOCRACIA ST. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(90, '754-075-899-000', 'ALING LITA\'S KUSINA FOOD HAUS', 'NV', 'E LOPEZ ST.,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(91, '458-256-090-000', 'ALI\'Z FASHION ACCESSORIES SHOP', 'VAT', 'RL MONTINOLA LEDESMA ST. KAUSWAGAN CITY PROPER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(92, '000-000-000-001', 'ALL ABOUT BAKING', 'VAT', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(93, '206-841-531-001', 'ALL FOOD ASIA, INC', 'NV', 'ZONE 7 BRGY.SAMBAG, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(94, '206-841-531-00001', 'ALL FOOD ASIA, INC.', 'NV', 'MALI-AO PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(95, '008-218-094-004', 'ALL PLATINUM RETAILERS, INC.', 'VAT', 'LEVEL 4 CASUAL DINING NAIA TERMINAL', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(96, '230-342-140-00000', 'ALLCARD INC.', 'VAT', 'LOT 3 BLK 17 E. RODRIGUEZ JR. AVE. SUBD. BAGUMBAYAN, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(97, '009-491-731-016', 'ALLDAY MARTS INC.', 'VAT', 'POLO MAESTRA VITA, OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(98, '009-491-731-054', 'ALLDAY RETAIL CONCEPTS,INC.', 'VAT', 'VISTAMALL ILOILO BRGY.PULO MAESTRA VITA,OTON,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(99, '174-217-747-000', 'ALLEN MARKETING', 'VAT', 'TABUC-SUBA BAROTAC NUEVO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(100, '008-541-952-024', 'ALLHOME CORP.', 'VAT', 'VISTAMALL ILOILO PULO MAESTRA VITA OTON ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(101, '300-228-702-000', 'ALLHOME CORP.', 'VAT', 'VISTAMALL ILOILO PULO MAESTRA VITA OTON ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(102, '243-383-976-000', 'ALLHOME CORP.', 'VAT', 'VISTAMALL ILOILO PULO MAESTRA VITA OTON ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(103, '439-740-178-001', 'ALLWHEEL AUTOMOTIVE INC', 'VAT', 'MAC ARHUR DRIVE, TABUC SUBA,JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(104, '439-740-178-000', 'ALLWHEEL AUTOMOTIVE INC', 'VAT', 'CDO FOODSPHERE BLDG., A.S FORTUNA ST., BAKILID MANDAUE,CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(105, '177-165-658-000', 'ALMA MAY L. TAYO', 'VAT', 'VILLANUEVA BLDG.,J.M BASA ST.,ILOILO CITY 5000', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(106, '933-606-046-000', 'ALMOETE ELECT. FAN REPAIR SHOP', 'NV', 'CORNER ARROYO- LUNA STS., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(107, '244-300-587-000', 'ALPHABYTE COMPUTER PARTS & ACCESSORIES', 'NV', '#35 BURGOS ST. LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(108, '008-837-089-001', 'ALTA BRIZA RESORT, INC.', 'VAT', 'STATION 2 SITIO BOLABOG, BALABAG, BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(109, '702-656-517-000', 'ALVAREZ, GENELYN JUANICO', 'NV', 'POBLACION ALTAVAS, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(110, '005-252-483-018', 'AMAZING TOUCH INTERNATIONALINC', 'VAT', 'SM CITY ILOILO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(111, '008-312-611-002', 'AMPHORAMERCHANTS INC.', 'VAT', 'BRGY. BURAY OTON,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(112, '126-150-955-000', 'AMS MERCHANDISING', 'VAT', 'J.M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(113, '108-778-609-000', 'AN GROCERY MERCHANDISE', 'NV', 'HUSHES ST. MALASIN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(114, '745-271-054-000', 'ANALYN BALASA-PEREZ', 'NV', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(115, '007-665-741-001', 'ANCECAR SPECIALTY FOODS, INC.', 'VAT', 'SM MEGAMALL, VARGAS WACK WACK MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(116, '005-573-275-070', 'ANDOKS CORP', 'VAT', 'RS BLDG.,QUINTIN SALAS', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(117, '000-996-443-000', 'ANG KAMALIG RESTAURANT, INC.', 'VAT', 'ROBINSONS PLACE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(118, '102-268-051-000', 'ANGELINA\'S BAKESHOP', 'VAT', '146-A LOPEZ JAENA ST., LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(119, '102-268-051-001', 'ANGELINA\'S BAKESHOP (THEODORE M. VALDERRAMA)', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(120, '622-591-676-000', 'ANGELMED PHARMACY', 'VAT', 'SAN JOSE ST. POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(121, '136-786-191-000', 'ANGELO B. MOLETA-PROP', 'VAT', 'COR. DE LEON-QUEZON ST.ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(122, '927-412-142-001', 'ANGELS CROWN HARDWARE & CONSTRUCTION SUPPLY', 'VAT', 'COMMISSION CIVIL OUR LADY FATIMA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(123, '740-500-000-000', 'ANGELS PIZZA', 'VAT', 'LUNA ST.,VILLA ANITA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(124, '748-560-030-001', 'ANGELS PIZZA (J AND C VENTURES, OPC)', 'VAT', 'TAGBAC JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(125, '454-025-471-000', 'ANJOLEHN FOOD CORPORATION', 'VAT', '10 JALANDONI ST, BRGY. VILLA ANITA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(126, '740-330-992-000', 'ANN ANTS GEN MERCHANDISE CORP.', 'VAT', 'NO.2 M. DELEON ST PANSOL, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(127, '149-459-241-002', 'ANN CO PASTRIES', 'VAT', 'PASEO VERDE BLDG.,MANDALAGAN, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(128, '941-226-281-000', 'ANNE TONIO\'S GARDEN CAFÉ', 'VAT', 'CADAGMAYAN, SANTA BARBARA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(129, '939-436-252-000', 'ANNIE\'S AND JUANING\'S MANOKAN AND SEAFOODS', 'VAT', 'STO. NIÑO SUR, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(130, '703-678-786-000', 'ANTHONETTE TALABAHAN', 'NV', 'DONATO PISON AVE. BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(131, '744-525-995-00000', 'ANTONIO LUIS C. TENDENCIA', 'NV', 'BURGOS-MABINI PLAZA, LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(132, '490-674-685-0001', 'ANVIL ILOILO GAS STATION INC', 'VAT', 'FUENTES DE LEON ST., BRGY. SAN JOSE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(133, '490-674-685-001', 'ANVIL ILOILO GAS STATION INC', 'VAT', 'FUENTES DE LEON ST., BRGY. SAN JOSE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(134, '490-674-685-0000', 'ANVIL ILOILO GAS STATION INC.', 'VAT', 'NORTH BALUARTE MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(135, '490-674-685-00000', 'ANVIL ILOILO GAS STATION INC.', 'VAT', 'NORTH BALUARTE MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(136, '005-247-530-000', 'AP CARGO LOGISTIC NETWORK CORP.', 'VAT', '124-D DURIAN PARK, DOMESTIC ROAD, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(137, '005-247-530-004', 'AP CARGO LOGISTIC NETWORK CORP.', 'VAT', '124-D DURIAN PARK, DOMESTIC ROAD, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(138, '157-311-281-000', 'APOLLO AUTO SUPPLY', 'VAT', 'E LOPEZ ST.,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(139, '451-835-485-000', 'APOMAXX INCORPORATED', 'VAT', 'STO.NINO SUR, AREVALO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(140, '402-754-064-001', 'APOTAT\'S INCORPORATED', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(141, '924-541-132-000', 'APRONIANA GIFT SHOP', 'VAT', 'BACLAYON BOHOL', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(142, '110-498-090-000', 'APSTAR TRADER&PRINTER', 'VAT', 'MARYMART MALL BDG., VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(143, '102-264-209-004', 'AQUINO AVENUE PETRON GAS TO GO', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(144, '742-029-871-001', 'ARBUTOS FOOD CORPORATION', 'VAT', 'GAIANO CITY MALL LUNA ST. LAPAZ', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(145, '402-419-005-00001', 'ARC PETROLEUM SLES AND DISTRIBUTION, INC.', 'VAT', 'LAPUZ NORTE LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(146, '223-662-279-000', 'ARCHIPELAGO PHILIPPINE FERRIES CORPORATION', 'VAT', 'ALABANG, MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(147, '348-424-592-000', 'ARIANNE ATHENA YU PINEDA', 'NV', 'E.LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(148, '1631-727-790-000', 'ARISE DISTRIBUTORS INC.', 'VAT', 'SH CIRCUMFERENTIAL ROAD BUHANG JARO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(149, '007-157-779-062', 'ARMY NAVY BURGER, INC.', 'VAT', 'DIOSDADO MACAPAGAL AVE. PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(150, '119-992-832-000', 'AS NOVELTY SHOP & MERCHANDISE (ANDY U ONG)', 'VAT', 'J. DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(151, '010-030-100-002', 'ASCON PARCON CORP.', 'VAT', 'PARCON ST. FERNANDO PARCON WARD POB. POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(152, '675-772-613-00001', 'ASCONGOLD FOODS CORPORATION', 'VAT', 'RIZAL ST., POBLACION, BAROTAC VIEJO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(153, '632-278-211-002', 'ASCONPRIME FOODS CORPORATION', 'VAT', 'MAPA ST. ORTIZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(154, '632-278-211-001', 'ASCONPRIME FOODS CORPORATION', 'VAT', 'J.C ZULUETA ST. POBLACION SOUTH OTON, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(155, '769-062-640-000', 'ASHAN BAKERS', 'NV', 'BRGY.TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(156, '006-358-151-011', 'ASIA CONSUMER VALUE TRADING IC', 'VAT', 'GUNNON ST., POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(157, '126-152-615-000', 'ASIAN DRAGON ENTERPRISES-SM CITY BRANCH', 'VAT', 'SM CITY DIVERSION RD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(158, '259-985-052-000', 'ASIAN LUMBER AND HARDWARE INC.', 'VAT', '', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(159, '606-242-479-000', 'ASTERIA APOTHECARY FRAGRANCE TRADING (ASTERIA APOTHECARY OPC)', 'VAT', 'PASIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(160, '452-115-379-000', 'ASTS ANALYTICAL SOLUTIONS&TECHNICAL SERVICES ILOILO, INC.', 'NV', 'JALANDONI ESTATE, LAPUZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(161, '002-786-458-017', 'ATHLETES GYM & DIVE II INC.', 'VAT', 'LEVEL 1 ROBINSONS PLACE LEDESMA ST, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(162, '137-351-564-000', 'ATTY.EFRAIN V. BALDAGO', 'NV', 'ARGUELLES, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(163, '005-273-734-000', 'A-UNITY DEVELOPMENT CORPORATION', 'VAT', '2ND FLOOR RED PLAZA BLDG., JM BASA ST., MUELLE LONEY MONTES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(164, '403-419-527-00006', 'AUTO GLOBAL INC.', 'VAT', 'MAC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(165, '934-597-586-000', 'AUTO PARTS AND SUPPLY', 'VAT', 'TAGBAK JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(166, '323-902-243-000', 'AVENUES AUTO SUPPLY', 'VAT', 'ROXAS AVENUE, POBLACION VIII, ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(167, '000-249-683-000', 'AVESCOR MOTORS, INC.', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(168, '117-802-113-000', 'AVL AUTO SUPPLY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(169, '308-630-590-001', 'AYAGOLD RETAILERS, INC.', 'VAT', 'UP TOWN CENTER KATIPUNAN, UP CAMPUS, QC', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(170, '008-630-590-001', 'AYAGOLD RETAILERS, INC.', 'VAT', 'UP TOWN CENTER KATIPUNAN AVENUE UP CAMPUS QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(171, '008-426-069-001', 'AYALA LAND METRO NORTH, INC', 'VAT', 'KATIPUNAN AVE. BARANGAY UP CAMPUS. DILIMAN QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(172, '000-106-866-092', 'AYALA PROPERTY MANAGEMENT CORP.', 'VAT', 'BONIFACIO GLOBAL CITY, TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(173, '000-106-866-00034', 'AYALA PROPERTY MANAGEMENT CORPORATION', 'VAT', 'GLOBAL CITY FORT BONIFACIO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(174, '686-382-950-00002', 'AZALAN (MAGANA HOSPITALITY GROUP INC.)', 'VAT', 'SM CENTRAL MARKET RIZAL ST. NONOY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(175, '756-988-821-000', 'AZUL GASTRO LOUNGE CORP.', 'VAT', 'THE GRID ROTONDA SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(176, '410-544-649-000', 'BAC FIRE INDUSTRIAL SUPPLIES', 'VAT', 'DIVERSION ROAD BRGY. SAMBAG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(177, '137-151-236-002', 'BACALIAN STEVEN GUIDO', 'VAT', 'Q.ABETO ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(178, '037-151-236-002', 'BACALIAN STEVEN GUIDO', 'VAT', 'Q.ABETO ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(179, '603-226-721-0000', 'BAIWEI DRY GOODS STORE', 'NV', 'EL 98 ST., TAYTAY ZONE 11 JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(180, '464-868-875-002', 'BAKEFULLY EVER AFTER INC.', 'VAT', 'GF GT MALL PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(181, '000-328-014-018', 'BAKERS\' FAIT & FOODMART INC', 'VAT', 'COMMONWELATH AVENUE BRGY BATASAN HILLS QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(182, '000-856-105-389', 'BALIWAG LECHON MANOK INC', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(183, '000-856-105-00294', 'BALIWAG LECHON MANOK INC', 'VAT', 'CORNER RIZAL-SCOTT JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(184, '000-856-105-473', 'BALIWAG LECHON MANOK INC', 'VAT', 'FOODCOURT FESTIVE WALK MALL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(185, '000-856-105-478', 'BALIWAG LECHON MANOK INC', 'VAT', 'CORNER RIZAL-SCOTT JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(186, '000-856-105-287', 'BALIWAG LECHON MANOK INC', 'VAT', 'CORNER RIZAL-SCOTT JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(187, '000-856-105-377', 'BALIWAG LECHON MANOK INC.', 'VAT', 'ROOSEVELT AVE BRGY STA CRUZ QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(188, '004-864-818-000', 'BANCON MARKETING, INC.', 'VAT', 'PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(189, '009-490-714-00015', 'BANH MI KITCHEN SERVICES INC.', 'VAT', 'BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(190, '407-590-376-002', 'BARMART CONVENIENCE STORE', 'VAT', 'JALANDONI ST., BRGY. LOURDES, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(191, '005-818-624-004', 'BARRIO INASAL', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(192, '183-280-646-004', 'BARTELL DRUGSTORE', 'VAT', 'Q.ABETO ST., MAND. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(193, '302-777-298-001', 'BATTUTA BAR AND COCKTAIL LOUNGE (RAMON B. GASTON)', 'VAT', 'BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(194, '168-256-368-000', 'BAYLON PORK AN D BEEF', 'NV', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(195, '166-906-093-000', 'BC JAYME MECHANICAL AND ELECTRICAL ENGINEERING SERVICES', 'VAT', 'LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(196, '166-906-093', 'BC JAYME MECHANICAL AND ELECTRICAL ENGINEERING SERVICES', 'VAT', 'LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(197, '223-767-840-000', 'BEARING AND SEAL CENTER', 'VAT', 'VALERIA EXT., ST., BRGY. NONOY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(198, '914-787-812-000', 'BEARLAND PARADISE RESORT', 'VAT', 'BRGY. TAN PAEL, TIGBAUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(199, '171-883-883-000', 'BEATRIZ P. ARAPE-PROP.', 'VAT', 'LANGKA ST. ST.JOSEPH SUBD.BRGY BALABAG PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(200, '140-606-513-000', 'BEBOT AND MILA TALABAHAN', 'VAT', 'BRGY. BITOON, JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(201, '465-156-869-002', 'BELCRIS FOODS INC.', 'VAT', 'ZONE 2 DUNGON A., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(202, '010-412-114-001', 'BELONG PRIME PLUS INC', 'VAT', 'EPSILON CHI FITNESS CENTER, BALAGTAS ST., UNIVERSITY OF THE PHILIPPINES, DILIMAN, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(203, '000-379-609-013', 'BENBY ENTERPRISES,INC', 'VAT', 'IBA MEYCAUAYAN CITY, BULACAN', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(204, '000-379-609-00012', 'BENBY ENTERPRISES', 'VAT', 'MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(205, '000-379-609-012', 'BENBY ENTERPRISES', 'VAT', 'MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(206, '000-844-246-690', 'BENCH BOTIQUE (SUYEN CORPORATION)', 'VAT', 'E LOPEZ ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(207, '000-844-246-276', 'BENCH BOTIQUE (SUYEN CORPORATION)', 'VAT', 'TUNASAN, MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(208, '000-844-246-783', 'BENCH EXPRESS (SUYEN CORPORATION)', 'VAT', 'MAKATI CITTY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(209, '200-247-346-003', 'BENJIE M. BASTIAN', 'VAT', 'GAISANO CAPITAL ILOILO CENTRAL MALL MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(210, '204-067-186-000', 'BENSON ELECTRICAL SUPPLY', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(211, '005-439-033-001', 'BERLIN PHARMACY AND CO.', 'VAT', 'INFANTE-DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(212, '464-402-892-000', 'BERMOUN INC', 'VAT', 'ONATE DE LEON ST. COR. R. MAPA ST. DISTRICT OF MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(213, '192-001-747-000', 'BERNADITH TRADING', 'NV', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(214, '008-224-347-068', 'BESTBAKE INC', 'VAT', 'SM CITY MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(215, '008-224-347-065', 'BESTBAKE INC', 'VAT', 'ANNEX BLDG SM DELGADO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(216, '008-224-347-072', 'BESTBAKE INC', 'VAT', 'SM CITY MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(217, '008-224-347-119', 'BESTBAKE INC', 'VAT', 'SM CITY MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(218, '008-224-347-00119', 'BESTBAKE INC.', 'VAT', 'SAVEMORE BAROTAC VIEJO BRANCH', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(219, '008-224-347-071', 'BESTBAKE, INC', 'VAT', 'BRGY LIBERIAD JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(220, '008-224-347-064', 'BESTBAKE, INC.', 'VAT', 'GT TOWN CENTER UNGKA II PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(221, '008-224-347-069', 'BESTBAKE, INC.', 'VAT', 'GT TOWN CENTER UNGKA II PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(222, '168-254-043-000', 'BH CONSTRUCTION AND SUPPLY', 'NV', 'POBLACION, LEGANES,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(223, '923-422-085-000', 'BHEB\'S FRUITS & VEGETABLES DEALER', 'NV', 'ILOILO TERMINAL MARKET ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(224, '008-030-153-0000', 'BIG BAD WOLF RESTOBAR CO.', 'VAT', 'TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(225, '204-474-329-010', 'BIG KAHUNA INC.', 'VAT', 'SHOP G NETPARK BLDG 5TH AVENUE, BGC TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(226, '487-536-745-000', 'BIGBS CAFE AND RESTO', 'VAT', 'UNIT B10 2F FESTIVE WALK ILOILO BUSINESS PARK MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(227, '005-982-855-029', 'BISCOCHO HAUS CORP.', 'VAT', 'ILOILO AYALA TECHNOMAND, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(228, '134-355-474-002', 'BISTRO CARCOSA', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(229, '004-732-019-048', 'BISTRO ITALIANO CORP.', 'VAT', 'AYALA MALLS CAPITOL CENTRAL GATUSLAO ST., BRGY 8 BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(230, '009-732-770-004', 'BISTRONOMIA CORP.', 'VAT', 'TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(231, '207-762-151-001', 'BITOYS RESTAURANT (RAYMUND AUGUSTUS O. CONLU)', 'VAT', 'BAYBAY ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(232, '122-015-282-000', 'BL ENTERPRISES', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(233, '488-452-829-000', 'BLACK SCOOP CAFE ANTIQUE BRANCH', 'VAT', 'CABASAN BLDG. BRGY. 7 SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(234, '232-721-268-069', 'BLADE ASIA INC.', 'VAT', 'LGF SM CITY ILOILO, BENIGNO AQUINO AVR, BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(235, '232-721-268-077', 'BLADE ASIA INC.', 'VAT', 'SM CITY, BENIGNO AQUINO AVE, BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(236, '491-431-158-000', 'BLINDS INSTALLATION SERVICES', 'NV', 'DUNGON B JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(237, '007-472-143-001', 'BLING PHILIPPINE, INC.', 'VAT', 'NAIA TERMINAL 3 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(238, '427-338-174-000', 'BLITZ AUTO SUPPLY', 'VAT', '38 FUENTES-DELGADO STS., BRGY.SAN AGUSIN,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(239, '146-204-941-003', 'BLJ GASOLINE STATION', 'VAT', 'TABUC PONTEVEDRA CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(240, '928-941-458-001', 'BLOOMINGTAILS SHOP', 'VAT', '73 AVANCENA STREET BRGY NORTH FUNDIDOR MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:04:59', '2026-07-13 03:04:59'),
(241, '241-629-317-021', 'BLUED CLOTHING INC', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(242, '005-423-653-000', 'BLUES BROTHERS INCORPORATED', 'VAT', 'SAN ISIDRO CAINTA RIZAL', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(243, '626-697-916-000', 'BLUESTROM MIX CORP', 'VAT', 'ADB AVE. ORTIGAS CENTER SAN ANTONIO, PASIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(244, '136-415-645-000', 'BLUEWAVES LAUNDROMAT', 'NV', 'MC ARTHUR DRIVE,CUBAY, JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(245, '009-999-358-001', 'BMG DMALL CORPORATION', 'VAT', 'D\' MALL D\' BORACAY PHASE 4 BALABAG BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(246, '122-451-891-000', 'BML MARKETING', 'VAT', 'SAMBAG JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(247, '005-918-171-093', 'BONG BONG VILLIAN CORPORATION', 'VAT', 'UTC MALL ARANETA ST BRGY 36 BACOLOD CITY 6100', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(248, '005-918-171-105', 'BONG BONG VILLIAN CORPORATION', 'VAT', 'G/F FORT SAN PEDRO ROAD PAROLA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(249, '005-918-171-00080', 'BONG BONG VILLIAN CORPORATION', 'VAT', 'SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(250, '005-918-171-089', 'BONGBONG VILLAIN CORPORATION', 'VAT', 'RED PLAZA BLDG., JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(251, '005-918-171-080', 'BONGBONG VILLAN CORPORATION', 'VAT', 'MANAGEMENT CORP. BENIGNO AQUINO AVE. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(252, '005-918-171-001', 'BONGBONG VILLAN CORPORATION', 'VAT', 'GAISANO CITY MALL LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(253, '005-918-171-025', 'BONGBONG VILLAN CORPORATION', 'VAT', 'GAISANO CITY MALL LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(254, '005-918-171-017', 'BONGBONG VILLAN CORPORATION', 'VAT', 'GAISANO CITY MALL LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(255, '000-109-279-021', 'BOOKSALE CEBU INC.', 'VAT', 'WALTERMART NORTH EDSA BRGY VETERANS VILLA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(256, '758-440-124-000', 'BOOKXLATTE INC.', 'VAT', 'ILOILO BUSINESS PARK, BRGY. TAFT NORTH MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(257, '005-571-626-002', 'BORACAY ENTERTAINMENT RESOURCES INC.', 'VAT', 'BEACH FRONT, D MALL OF BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(258, '238-544-750-00000', 'BOSSINGS FRUIT AND VEGETABLES STORE', 'NV', 'TAFT NORTH MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(259, '007-872-494-034', 'BOTIKANG PINOY', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(260, '007-872-494-032', 'BOTIKANG PINOY', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(261, '005-478-257-398', 'BOUNTY AGRO VENTURES INC.', 'NV', 'BRGY. AVANCENA MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(262, '939-429-229-000', 'BOY AMADO RICE AND GRAIN STORE', 'NV', 'GUZMAN ST.,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(263, '102-264-233-001', 'BP 76 AUTOPARTS CENTER- MOLO BRANCH', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(264, '009-695-967-001', 'BRAAI MASTERS CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(265, '184-808-940-00010', 'BREAD BASKET BREAD & PASTRIES', 'VAT', 'G/F UNIT 5-6, OUTDOOR, DONATO P MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(266, '184-808-940-012', 'BREAD BASKET BREAD AND PASTRIES SHOP', 'NV', 'LOWER GROUND FLOOR, NORTH POINT, SM CITY ILOILO, B. AQUINO AVENUE, BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(267, '618--042-120-082', 'BREADWINNERS FOODS CORPORATION', 'VAT', 'SAN ISIDRO GALAS NCR, 1113 QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(268, '122-455-829-002', 'BREAKTHROUGH', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(269, '122-455-829-00002', 'BREAKTHROUGH RESTAURANT', 'VAT', 'SM CITY FOODCOURT B. AQUINO AVE. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(270, '639-607-051-002', 'BREAKTHROUGH RESTAURANT INC.', 'VAT', 'SANTO NIÑO NORTE, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(271, '639-607-051-001', 'BREAKTHROUGH RESTAURANT INC.', 'VAT', 'DANAO CITY PROPER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(272, '639-607-051-000', 'BREAKTHROUGH RESTAURANT INC.', 'VAT', 'SANTO NIÑO NORTE, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(273, '122-455-829-000', 'BREAKTROUGH RESTAURANT', 'VAT', 'SO. NINO AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(274, '009-266-822-000', 'BREW NATION PHILIPPINES CORPORATION', 'VAT', '5666 DON PEDRO ST. POBLACION, MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(275, '010-057-617-555', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'IZNART ST. DANAO CITY PROPER CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(276, '010-057-617-110', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(277, '010-057-617-00182', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(278, '010-057-617-057', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'MARYMART MALL BDG., VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(279, '010-057-617-00237', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(280, '010-057-617-040', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(281, '010-057-617-00254', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(282, '010-057-617-415', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GF UNIT C108 AND C110 GT PLAZA MALL MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(283, '010-057-617-00953', 'BRICOLAGE PHILIPPINES INC.', 'VAT', 'GUZMAN JESENA, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(284, '009-478-988-001', 'BRIGHTSIDE PROPERTIES AND RESORTS INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(285, '159-559-705-000', 'BRM SALES CENTER', 'VAT', 'MABINI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(286, '010-357-617-00237', 'BROCOLAGE PHILIPPINES INC.', 'VAT', '2ND FLR GAISANO CAPITAL CITY MALL LUNA ST.,LUNA LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(287, '457-136-259-000', 'BRONCO ENTERPRISES', 'NV', 'YULO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(288, '502-212-932-000', 'BROOKLYN POBLACION PH INC.,', 'VAT', 'KALAYAAN AVENUE, POBLACION 1210 CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(289, '659-654-597-000', 'BRTL FOOD CORP', 'VAT', 'QUEZON CITY METRO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(290, '003-675-871-039', 'BRUNO\'S SERVICES CORPORATION', 'VAT', 'LGF FOODCOURT SM CITY,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(291, '127-732-961-000', 'BSG CONVENIENCE STORE', 'VAT', 'COR.GERONA&LAKANDULA ST.,GUIMBAL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(292, '-', 'BT GRAINS AND AGRO CORPORATION', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(293, '719-020-964-008', 'BT GRAINS AND AGRO CORPORATION', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(294, '719-020-984-008', 'BT GRAINS AND CARGO CORPORATION', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(295, '137-821-202-000', 'BTJ MARKETING', 'VAT', 'J.DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(296, '437-636-210', 'BUBBLES LUMBER AND CONCRETE PRODUCTS', 'VAT', 'NEW LUCENA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(297, '932-437-379-000', 'BUK-AN\'S SIOMAI & FOOD CART', 'NV', 'BRGY. CAGBANG,OTON,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(298, '932-437-379-001', 'BUK-AN\'S SIOMAI & FOOD CART', 'NV', 'AVANCENA ST. NORTH FUNDIDOR MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(299, '238-383-045-039', 'BUKO NI FRUITAS,INC', 'VAT', 'SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(300, '245-353-189-001', 'BULL JACK TALABAHAN', 'VAT', 'BRGY. BITOON NEW SITE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(301, '005-918-171-0080', 'BUNGBONG VILLAN CORPORATION', 'VAT', 'BENIGNOAQUINO AVENUE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(302, '412-662-780-000', 'BUSINESS EXPERIENCE SERVICES AND TRANSITION', 'VAT', 'POBLACION ILAWOD, LAMBUNAO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(303, '004-507-339-005', 'BUSINESS PEOPLE, INC.', 'VAT', 'AGANAN, PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(304, '004-507-339-000', 'BUSINESS PEOPLE, INC.', 'VAT', 'TANZA GUA, ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(305, '004-867-949-000', 'BUTO\'T BALAT FASTFOOD AND COMPANY', 'VAT', 'SMITH CORNER BENEDICTO ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(306, '004-867-949-002', 'BUTO\'T BALAT FASTFOOD AND COMPANY', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(307, '004-867-949-004', 'BUTO\'T BALAT FASTFOOD AND COMPANY', 'VAT', 'FESTIVE WALK MALL ILOILO BUSINESS PARK', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(308, '614-616-130-000', 'BY THE BUCKET CORP.', 'VAT', 'GROUND FLOOR DOUBLEDRAGON PLAZA DD MERIDIAN PARK, BRGY 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(309, '744-192-658-000', 'BYRON\'S SMALLVILLE', 'VAT', 'THE VERTEX SMALLVILLE COMPLEX, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(310, '901-768-872-001', 'C AND T INASAL AND RESTAURANT', 'VAT', 'ATRIA, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(311, '901-768-872-000', 'C AND T INASAL AND RESTAURANT', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(312, '177-219-136', 'C SQUARE ENGINEERING CONSTRUCTION AND SUPPLY', 'VAT', 'BLK4 BRGY SINIKWAY, LAPUZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(313, '209-466-540-007', 'C2 RETAIL STORES, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(314, '601-227-074-553-000', 'CAFÉ A. ROMA', 'VAT', 'TIME SQUARE BENIGNO AQUINO DIVERSION RD., SAN RAFAEL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(315, '005-167-511-001', 'CAFÉ ILOILO PASTRY AND RESTAURANT', 'VAT', 'SM CITY BENIGNO AQUINO AVE., MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(316, '425-934-117-000', 'CAFÉ MARIA', 'VAT', 'ROBINSONS PLACE ILOILO COR. QUEZON DE LEON STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(317, '165-703-807-001', 'CAFÉ MEZZANTNE', 'VAT', 'NUEVA ST. BINONDO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(318, '462-057-203-001', 'CAKEFULLY YOURS, INC.', 'VAT', 'JEA2 BLDG. E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(319, '462-057-203-000', 'CAKEFULLY YOURS, INC.', 'VAT', 'JEA2 BLDG. E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(320, '001-627-540-046', 'CALIFORNIA CLOTHING INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(321, '442-986-449-000', 'CAMERON FOODHAUS (ROSVIE M. DIGNOS)', 'VAT', 'BUHANG TAFT NORTH, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(322, '771-118-568-000', 'CAMILO CAFE (CASCOLAN, JOSE JIRO TANALGO)', 'VAT', 'SANTA BARBARA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(323, '183-780-362-000', 'CAMPUESTOHAN HIGHLAND RESORT', 'VAT', 'SITIO CAMPUESTOHAN, CABATANGAN TALISAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(324, '007-944-679-009', 'CAMSUR GENERAL MDSE. INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(325, '203-301-457-0020', 'CANELLE FOOD CORPORATION', 'VAT', 'SM MALL OF ASIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(326, '008-888-007-001', 'CAPITOL CENTRAL HOTEL VENTURES, INC', 'VAT', 'LACSON ST.,CORNER NORTH CAPITOL ROAD, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(327, '007-571-298-003', 'CAPITOL COMMONS CORP.', 'VAT', 'GREENHILLS SHOPPING CENTER, GREENHILLS, SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(328, '750-845-773-000', 'CAPITOLYO, INC', 'VAT', '2/F CORONET BLDG. 879 COR IMPERIAL ST CUBAO Q. C.', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(329, '008-022-724-00009', 'CARGO PADALA EXPRESS FORWARDING SERVICES CORP.', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(330, '934-617-336-007', 'CARINA CHRISTINE C JUAN HONG', 'VAT', 'FESTIVE WALK PARADE LOLO BUSINESS PARK MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(331, '000-251-244-000', 'CARLO\'S BAKESHOP', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(332, '255-148-381-000', 'CARLSON DIGITAL PRINTS,INC.', 'VAT', 'JUANTONG BUILDING, IZNART STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(333, '003-887-284-000', 'CASA ILONGGA INCORPORATED', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(334, '008-286-542-001', 'CASA ILONGGA INCORPORATED', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(335, '200-282-826-012', 'CASAMIA FURNITURE CENTER INC', 'VAT', 'SM CITY ILOILO,BENIGNO AQUINO AVE., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(336, '424-394-809-000', 'CATALUNA PHARMACY', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(337, '211-317-623-000', 'CAUSEWAY SEAFOOD RESTAURANT', 'VAT', '24 TIMOG AVE., LAGING HANDA, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(338, '008-414-072-001', 'CDC SHOPPE, INC.', 'VAT', 'SOUTH LUZON TOLLWAY MAMPLASAN BINAN LAGUNA', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(339, '008-462-850-049', 'CD-R KING GEN. MERCHANDISE', 'VAT', 'ROBINSONS PLACE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(340, '008-134-031-083', 'CD-R KING GEN. MERCHANDISE', 'VAT', 'SM CITYILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(341, '000-948-229-000', 'CEBU AIR INC.', 'VAT', 'BASEMENT 2-R 01-02 ROBINSONS GALLERIA CEBU GEN. MAXILOM COR. S. OSMENA BLVD. TEJERO 6000 CEBU CITY CEBU PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(342, '000-948-229-00023', 'CEBU AIR, INC.', 'VAT', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(343, '009-373-084-000', 'CEBU CORDOVA LINK EXPRESSWAY CORPORATION', 'VAT', 'UNIT 1705 BPI CEBU CORPORATE CENTER ARCHBP REYES AVE. CORNER LUZON AVE. CEBU BUSINESS PARK, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(344, '261-481-387-000', 'CEBU INNOSOFT SOLUTIONS SERVICES INC.', 'VAT', 'CORNER V RAMA AVENUE AND DUTERTE ST. GUADALUPE CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(345, '432-281-006-133', 'CEBU OVEN MAGIC CORPORATION', 'VAT', 'MAYON STREET STA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(346, '933-000-900-016', 'CEBUANOS LECHON MANOK', 'NV', 'BRGY NORTH AVACENA,MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(347, '943-182-609-000', 'CECILE MINI MART', 'VAT', 'R.Y. LADRIDO ST. POTOTAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(348, '941-845-840-001', 'CELERES CONVENIENCE STORE', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(349, '930-829-422-004', 'CELLMAX CELLPHONE ACCESSORIES', 'NV', 'ROBINSONS PLACE E.LOPEZ ST. BRGY. SAN VICENTE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(350, '287-039-304-000', 'CELOY AUTO SUPPLY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(351, '211-581-351-000', 'CENTRAL MANILA FOOD CORP', 'VAT', 'NAIA CENTENNIAL BLDG NAIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(352, '901-786-358-000', 'CENTRAL TIN MART', 'VAT', 'RIZAL ST., CALINOG ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(353, '907-752-409-000', 'CENTRO FOOTWEAR', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(354, '010-315-200-00031', 'CHA TUK CHAK CORP.', 'VAT', 'ATRIA PARK SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(355, '009-812-763-001', 'CHAZANIA FOOD CORPORATION', 'VAT', 'SM CITY STA MESA AURORA BLVD DONA IMELDA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(356, '177-214-479-000', 'CHEMICAL INDUSTRIAL CORPORATION', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(357, '267-950-576-000', 'CHERRY MAE P. NODQUE', 'VAT', 'ILOILO TERMINAL MARKET ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(358, '005-981-177-001', 'CHIAMBROS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(359, '730-940-875-001', 'CHIB\'S RESTAURANT', 'NV', 'E LOPEZ ST.,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(360, '730-940-875-002', 'CHIB\'S RESTAURANT', 'NV', 'NORTH SAN JOSE MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(361, '928-927-822-007', 'CHIC N\' KERI OVEN ROASTED CHICKEN', 'VAT', 'TAGBAK, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(362, '110-518-768-001', 'CHICKEN HOUSE BACOLOD', 'VAT', 'COR. ARANETA - ALUNAN STS, BRGY. 39, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(363, '602-029-637-000', 'CHICKEN HUB ILOILO (ILOILO PRIME FOOD VENTURES CORP.)', 'VAT', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(364, '117-501-779-000', 'CHICKEN SARI-SARI', 'VAT', 'COM. CIVIL, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(365, '261-286-681-000', 'CHOCO-LATE DE BATIROL', 'VAT', 'JOHN HAY BAGUIO CITY BENGUET', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(366, '335-113-825-000', 'CHODAM KOREAN RESTAURANT', 'VAT', 'THE UPTOWN PLACE CONDOMINIUM, GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(367, '228-422-731-012', 'CHOICE GOURMET BANQUET INC.', 'VAT', 'MEGAWORLD AVENUE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(368, '228-422-731-002', 'CHOICE GOURMET BANQUET INC.', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(369, '609-277-057-867', 'CHOOKS TO GO', 'VAT', 'BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(370, '609-277-057-01138', 'CHOOKS TO GO INC', 'VAT', 'BANGGA BANTE ZARAGA', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(371, '615-993-151-003', 'CHOOSY PANDA VENTURES INC', 'VAT', 'SM CITY ILOILO DIVERSION ROAD MANDURRIAO DISTRICT ILOILO CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(372, '000-333-173-880', 'CHOWKING CITYMALL PAVIA (FREASH N\'FAMOUS FOODS INC.)', 'VAT', 'UNGKA II, PAVIA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(373, '478-526-608-000', 'CHRISMAYG RICE STORE', 'NV', 'HUEVANA ST RAILWAY LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(374, '478-526-608-00000', 'CHRISMAYG RICE STORE', 'NV', 'HUEVANA ST RAILWAY LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(375, '942-461-021-00007', 'CHRISTY L. ZALDARRIAGA-PROP.', 'VAT', 'E.LOPEZ ST. OUR LADY OF FATIMA JARO 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(376, '006-344-250-041', 'CHRONOTRON, INC.', 'VAT', '2ND FLR. ROBINSONS LAND CORP. COR. DE LEON-QUEZON STS, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(377, '635-827-197-000', 'CHUBBY-CRAB BORACAY SEAFOOD RESTAURANT INC.', 'VAT', 'BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(378, '213-046-918-048', 'CINCO CORPORATION', 'VAT', 'SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(379, '005-919-438-001', 'CITIHARDWARE BACOLOD, INC.', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(380, '005-919-438-00001', 'CITIHARDWARE BACOLOD, INC.', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(381, '005-919-438-006', 'CITIHARDWARE BACOLOD, INC.', 'VAT', 'BRGY. BUNTATALA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(382, '004-625-830-00000', 'CITRA METRO MANILATOLLWAYS CORP.', 'VAT', 'PARAÑAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(383, '004-625-830-000', 'CITRA METRO MANILATOLLWAYS CORP.', 'VAT', 'PARAÑAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(384, '006-129-955-000', 'CITY SQUARE TRADER', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(385, '006-129-955-001', 'CITY SQUARE TRADER', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(386, '009-389-694-00001', 'CITYBEE FOODS CORPORATION', 'VAT', 'MARTELINO ST., POBLACION KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(387, '748-736-902-0007', 'CJI FUELS CORP.', 'VAT', 'Q.ABETO ST., Q ABETO-MIRASOL MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(388, '748-736-902-008', 'CJI FUELS CORP.', 'VAT', 'LOT NO. 2320F-2 BRGY. TAGBAC JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(389, '009-914-382-000', 'CK TRUMART FOODS CORPORATION', 'VAT', 'GAISANO ILOILO CITY CENTER, DIVERSION RD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(390, '009-914-382-001', 'CK TRUMART FOODS CORPORATION', 'VAT', 'WEST AVE., BRGY. TAAL MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(391, '009-914-382-002', 'CK TRUMART FOODS CORPORATION', 'VAT', 'GAISANO ICC MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(392, '268-697-998-002', 'CLEAN GAZZ GASOLINE STATION', 'VAT', 'POBLACION ALTAVAS, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(393, '923-422-043-000', 'CLERIAHN STORE', 'NV', 'ILOILO TERMINAL MARKET, MABINI ST., FLORES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(394, '000-337-941-053', 'CLN BOTIQUE', 'VAT', 'SOCORRO NCR, SECOND DISTRICT QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(395, '000-337-941-00054', 'CLN BOTIQUE (CMG RETAIL INC)', 'VAT', 'NCR, PASIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(396, '102-264-505-000', 'CLS MARKETING', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(397, '906-716-949-000', 'CLUB 21 RESTOBAR', 'VAT', 'G.T. PISON AVENUE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(398, '004-247-791-016', 'CM & SONS FOOD PRODUCTS, INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(399, '004-247-791-00077', 'CM & SONS FOOD PRODUCTS, INC.', 'VAT', 'POBLACION MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(400, '004-247-791-050', 'CM AND SONS FOOD PRODUCTS', 'VAT', 'J.M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(401, '459-773-349-004', 'CMC 417 ENTERPRISES CORPORATION', 'VAT', '#15 LM TINSAY BLDG. DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(402, '000-337-541-548', 'CMC RETAIL INC.', 'VAT', 'PASIG CTY NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(403, '228-729-037-040', 'CMSTAR MANAGEMENT INC', 'VAT', 'VISTAMALL ILOILO BRGY. PULO MAESTRA VITA OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(404, '228-729-037-039', 'CMSTAR MANAGEMENT INC', 'VAT', 'VISTAMALL ILOILO, BRGY. PULO MAESTRA VITA, OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(405, '228-729-037-188', 'CMSTAR MANAGEMENT, INC.', 'VAT', 'VISTA MALL ILOILO, BRGY. PULO MAESTRA VITA OTON ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(406, '228-729-037-189', 'CMSTAR MANAGEMENT, INC.', 'VAT', 'VISTA MALL ILOILO, BRGY. PULO MAESTRA VITA OTON ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(407, '008-532-597-002', 'COCO FRESH TEA & JUICE', 'VAT', 'ROOSEVELT AVE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(408, '001-798-988-000', 'COCOTREST TEA & JUICE', 'VAT', 'QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(409, '006-231-269-00047', 'COFFEBREAK CAFÉ INTERNATIONAL', 'VAT', 'SM CITYSOUTHPOINT BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(410, '427-431-793', 'COFFEE BREAK', 'VAT', 'DIVERSION ROAD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(411, '203-872-880-034', 'COFFEE MASTER INC.,', 'VAT', 'NAIA 1 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(412, '228-729-037-077', 'COFFEE PROJECT BGC', 'VAT', 'VISTA HUB 21ST DRIVE FORT BONIFACIO,TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(413, '008-976-889-124', 'COFFEE TABLE INC.', 'VAT', '2/F MAIN MALL SM MALL OF ASIA SEASIDE BLVD BRGY 123 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(414, '006-231-269-011', 'COFFEEBREAK CAFÉ INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(415, '006-231-269-002', 'COFFEEBREAK CAFÉ INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(416, '006-231-269-003', 'COFFEEBREAK CAFÉ INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(417, '006-231-269-005', 'COFFEEBREAK CAFÉ INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(418, '006-231-269-00048', 'COFFEEBREAK CAFE INTL INCORPORATED', 'VAT', 'JIBAO-AN PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(419, '006-231-269-007', 'COFFEEBREAK CAFÉ INTL INCORPORATED', 'VAT', 'FESTIVE WALK ANNEX, MEGAWORLD', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(420, '006-231-269-047', 'COFFEEBREAK CAFE INTL. INC.', 'VAT', 'SM CITY SOUTHPOINT BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(421, '935-312-404-000', 'COLOMER BAKE SHOP', 'NV', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(422, '006-461-607-000', 'COLOURLAND, INC.', 'VAT', 'CORNER TEXAS- MABINI STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(423, '154-498-675-000', 'COMBAT MILITARY STORE&TAILORING', 'NV', 'ILOILO CENTRAL MARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(424, '000-249-730-0000', 'COMMONER INC.', 'VAT', 'J.M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(425, '100-699-925-000', 'COMMONWEALTH GLASSWARE STORE', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(426, '473-270-680-000', 'COMPUTRON BUSINESS CENTER', 'VAT', 'NO 35 QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(427, '005-981-177-004', 'CONCEPT COMPUTER CENTER- BRANCH III', 'VAT', 'ROBINSONS PLACE, ROXAS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(428, '005-981-177-000', 'CONCEPT COMPUTER CENTER- VALERIA', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(429, '008-499-870-00013', 'CONCETTI GLOBALI, INC.', 'VAT', 'SOCORRO CUBAO, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(430, '441-062-028-015', 'CONFLUENCE HEALTH & WELLMNESS PRODUCT', 'VAT', 'LOWER GROUND FLOOR SM CITY ILOILO BENIGNO AQUINO AVE BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(431, '441-062-028-000', 'CONFLUENCE HEALTH AND WELLNESS PRODUCTS', 'VAT', 'DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(432, '010-682-715-007', 'CONGRATS 2U INC.', 'VAT', 'ROBINSONS GALLERIA ORTIGAS AVENUE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(433, '239-058-557-003', 'CONLINS COFFEE WORLD INC', 'VAT', 'BONIFACIO GLOBAL CITY FORT BONIFACIO 1635, TAGUIG CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(434, '219-294-991-434', 'CONSOLIDATED GLOBAL IMPORTS, INC', 'VAT', 'BARANGAY MAYAMOT, ANTIPOLO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(435, '219-294-991-587', 'CONSOLIDATED GLOBAL IMPORTS, INC', 'VAT', 'ATRIA, DONATO PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(436, '008-043-737-00033', 'CONTEMPORAIN FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(437, '008-043-737-046', 'CONTEMPORAIN FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(438, '217-932-494-000', 'COPIADO COPY SERVICES', 'NV', 'M. H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(439, '933-864-790', 'COPIERONLINE ILOILO STATION', 'NV', 'DEL PILAR MOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(440, '935-312-841-004', 'COPYA ILONGGO SALES AND SEVICES', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(441, '127-945-430-000', 'CORNERSTONE ANIMAL HOSPITAL AND VETERINARY SUPPLY', 'VAT', 'BRGY.OUR LADY OF LOURDES, JAROP,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(442, '008-101-491-017', 'CO\'S COFFEE', 'VAT', 'MCIA PUSOK LAPU-LAPU CITY, CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(443, '008-981-182-00000', 'COSMIC CHAIN INC.', 'VAT', 'UNIT 1219-1220 SM CITY CITY OF PASIG', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(444, '633-371-715-001', 'COSMOLUXE INC.', 'VAT', 'JW DIOKNO BLVD., MALL OF ASIA COMPLEX BRGY 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(445, '007-916-186-011', 'COSTA BUENA', 'VAT', 'DUMANGAS ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(446, '153-359-459-000', 'COSTA BUENA', 'VAT', 'DUMANGAS ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(447, '290-699-231-000', 'COSTAR MARKETING', 'VAT', 'CAPREHOST BANATE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(448, '003-338-727-001', 'COSTMART INC', 'VAT', '168 PLAZA BLDG. IZNART ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:00', '2026-07-13 03:05:00'),
(449, '009-998-727-001', 'COSTMART INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(450, '000-064-175-002', 'COSTNER TRADING CORPORATION', 'VAT', 'MUELLE LONEY ST., BRGY. MUELLE LONEY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(451, '933-592-606-000', 'CPT MART', 'VAT', 'DULALIA BLDG. IZNART ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(452, '008-607-808-031', 'CREATESTAR INC', 'NV', 'SM CITY MAND, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(453, '204-067-186-00000', 'CRISTINA C. GOLEZ-PROP.', 'VAT', 'QUEZON ST,5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(454, '488-453-633-000', 'CRYSTAL PALACE HOUSE OF PASSION', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(455, '119-409-459-0002', 'CT MART GROCERY', 'VAT', 'CC ZALDIVAR ST., SJA', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(456, '445-533-461-000', 'CTR GRAPHICS AND SIGNS INC.', 'VAT', 'DIVERSION ROAD SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(457, '463-228-368-0000', 'CUCINAITALIA,INC', 'VAT', 'PASEO VERDE BLDG.,LACSON ST., BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(458, '009-135-048-00008', 'CUMULAS PREMIER DINING INC.', 'VAT', 'SM MALL OF ASIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(459, '269-795-414-000', 'CURVUS CAFÉ', 'VAT', 'SITIO BOOL, BRGY. SAPAO, DUMANGAS, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(460, '260-138-446-017', 'CYBERCLINIC GADGET REPAIR SHOP', 'VAT', '3F CYBERZONE SM CITY BOLILAO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(461, '606-412-298-000', 'CYBERLINK COMPU SALES CORPORATION', 'VAT', 'NO. 283 IZNART STREET DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(462, '917-783-451', 'CYBERLINK COMPUSALES', 'VAT', '273 IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(463, '917-783-451-000', 'CYBERLINK COMPUSALES', 'VAT', '273 IZNART ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(464, '260-532-686-000', 'CYRIL CHRISTOPHER P. BACALLAN', 'NV', '#1 BENEDICTO ST.,JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(465, '000-146-306-00904', 'D BLVD FESTIVE WALK (STORES SPECIALISTS, INC.)', 'VAT', 'MEGAWORLD BLVD MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(466, '948-087-809-000', 'DAD\'S BARBECUE PARK', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(467, '191-757-840-000', 'DAINTY HOUSE RESTAURANT', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(468, '142-917-825-000', 'DAISAN MULTI TRADERS', 'VAT', 'BENEDICTO ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(469, '431-010-172-0000', 'DAKASI', 'VAT', 'ILOILO AYALA TECHNOHUB MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(470, '748-940-479-00236', 'DALI EVERYDAY GROCERY (HARD DISCOUNT PHILIPPINES, INC.)', 'VAT', 'TUNASAN CITY MUNTINLUPA, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(471, '009-195-513-000', 'DANNIBEE FOODS CORP', 'VAT', 'MABINI COR D. MAAGMA ST., KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(472, '009-727-320-001', 'DARAMGUHANON EATERY INC.', 'VAT', 'Q. ABETO-MIRASOLMANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(473, '007-971-689-0007', 'DARJEELING TEA CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(474, '005-172-745-022', 'DAVAO CITIHARDWARE INC.', 'VAT', 'BENIGNO S. AQUINO JR. AVE. SAMBAG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(475, '456-383-990-000', 'DAYNETO SEAFOOD GRILL & RESTAURANT', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(476, '004-865-972-004', 'DD FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(477, '004-865-972-001', 'DD FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(478, '004-865-972-000', 'DD FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(479, '004-865-972-003', 'DD FOODS INC.', 'VAT', 'E-LOPEZ ST., SAN VICENTE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(480, '004-865-972-0003', 'DD FOODS INC.', 'VAT', 'E.LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(481, '003-377-306-000', 'DD ROAD HOUSE CO.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(482, '003-377-306-001', 'DD ROAD HOUSE CO.- LAPAZ', 'VAT', 'LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(483, '401-053-122-000', 'DDL AND SONS DEV.GROUP INC.', 'VAT', 'FUNDA-DALIPE SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(484, '009-296-480-000', 'DEAC FAMILY FOODS, INC.', 'VAT', 'QUEZON BLVD COR PATERNO ST. QUIAPO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(485, '009-521-038-009', 'DECATHLON PHILIPPINES INC.', 'VAT', 'SM CITY BENIGNO AQUINO AVE. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(486, '006-463-537-000', 'DECO\'S LAPAZ BATCHOY', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(487, '102-274-516-000', 'DELGADO SHELL SERVICE STATION', 'VAT', 'COR.DELGADO-JALANDONI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(488, '456-686-466-005', 'DELIDRAGONS FOOD CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(489, '488-320-171-000', 'DELISH FOOD PRODUCTS', 'NV', 'ALTA TIERRA VILLAGE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(490, '168-255-876-000', 'DELTA MERCHANDISING', 'VAT', 'THE ATRIUM, GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(491, '006-232-077-000', 'DELTA TRADING, INC', 'NV', 'BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(492, '117-317-677-000', 'DENILA MERCHANDISING', 'VAT', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(493, '165-591-721-000', 'DEOCAMPO\'S BARQUILLOS', 'VAT', 'STA. ISABEL ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(494, '274-751-386-001', 'DEPT. STORE AND SHOE EMPORIUM', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(495, '223-822-351-068', 'DERMASIA CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(496, '770-372-234-006', 'DESKBAKE CORP.', 'VAT', 'GAISANO CITY, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(497, '753-676-229-0025', 'DGNATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(498, '102-264-928-000', 'DIAMOND SHOPPING CENTER', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(499, '412-738-270-002', 'DIEZ NINJA CO.', 'VAT', 'ROBINSONS PLACE, LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(500, '917-779-973-002', 'DIGITIZERS 2000 COMPUTER SERVICES', 'NV', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(501, '168-257-760-002', 'DINAGYANG SIOPAO', 'VAT', 'FIGUERA ST., AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(502, '934-595-690-004', 'DINNO P. BEJAR-PROP', 'NV', 'ONATE ST. BRGY. ONATE DE LEON,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(503, '288-918-976-000', 'DIOVEN O. METRAN', 'VAT', 'BAGTIKAN ST.,SAN ANTONIO 1203, CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(504, '773-734-270-000', 'DIRTY WHITE PH INC.', 'NV', 'PLAZUELA DE ILOILO DIVERSION ROAD, MANDURRIAO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(505, '102-266-315-012', 'DISTRICT 21 RESTO KTV', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(506, '000-312-312-028', 'DISTRICT FIESTA (CESAR\'S FOODLAND, INC.)', 'VAT', 'LAPU LAPU CITY, CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(507, '221-144-927-000', 'DIVEROAD ROADHOUSE GRILL', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(508, '009-256-063-000', 'DIVERSIOB PETRON SERVICE STATION', 'VAT', 'SUPERMARKET', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(509, '432-008-505-000', 'DIVERSION 21 HOTEL', 'VAT', 'DIVERSION ROAD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(510, '102-265-848-0000', 'DIVERSION PETRON SERVICE STATION', 'VAT', 'DIVERSION ROAD , MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(511, '009-756-063-000', 'DIVERSION PETRON SERVICE STATION', 'VAT', 'BRGY.BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(512, '102-265-848-0001', 'DIVERSION PETRON SERVICE STATION', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(513, '182-258-329-000', 'DIZOR ENTERPRISES', 'VAT', 'QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(514, '421-614-001-000', 'DLRCONS INCORPORATED', 'VAT', 'BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(515, '934-584-971-002', 'DLS SUPERMART', 'VAT', 'IAC BLDG BRGY TAGBAK JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(516, '934-584-971-001', 'DLS SUPERMART POTOTAN', 'VAT', 'POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(517, '339-254-156-00001', 'DOANE WILSON C. LIM-PROP', 'VAT', 'EX 315,SM CITY ILOILO CYBERZONE DIVERSION ROAD,BENIGNO AQUINO AVE.,BOLILAO MANDURRIAO,5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(518, '468-222-246-000', 'DOCTOR TEXTUS GADGETS AND ACCESSORIES', 'VAT', '2ND FLOOR,OLD MARYMART,VALERIA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(519, '481-443-695-000', 'DOMING\'S TALABAHAN', 'VAT', 'KIRAYAN NORTE,MIAGAO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(520, '117-366-049-000', 'DONAROSE ENTERPRISE', 'NV', '76 LUNA SREET, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(521, '254-568-338-000', 'DONS CALAMAN-C JUICE DRINK', 'NV', 'BRGY. CALUMPANG MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(522, '447-986-700-009', 'DORONA TOP AND SHOP STORE INC.', 'VAT', 'IZMART-LEDESMA ST. MAGSAYSAY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(523, '619-305-543-000', 'DOUBLEWIN ENTERPRISE CORPORATION', 'VAT', 'TABUC SUBA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(524, '450-460-080-000', 'DOVA BRUNCH CAFE', 'VAT', 'JAVELLANA ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(525, '450-460-080-001', 'DOVA BRUNCH CAFÉ', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(526, '450-460-080-0001', 'DOVA BRUNCH CAFÉ', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(527, '286-251-070-000', 'DOVANSER INC', 'VAT', 'E.LOPEZ STREET, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(528, '937-199-238-000', 'DR. TEXTUS GADGETS AND ACCESSORIES STORE (CATHERINE J. TAN)', 'VAT', 'SM CITY BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(529, '005-819-977-000', 'DRAGON FARM PURIFIED DRINKING WATER CORPORATION', 'NV', 'LOT 1 BLK 13, LAKURAN LUCKY HOMES PH 2 JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(530, '003-987-300-010', 'DRANIX DISTRIBUTORS, INC.', 'VAT', 'LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(531, '239-075-644-001', 'DRIVE AND DYNE SPECIALIST, INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVE. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(532, '000-388-474-00596', 'DRUG - MALAY CATICLAN JETTY PORT', 'VAT', 'MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(533, '424-444-188-000', 'DRY GOODS TRADING', 'VAT', 'DUENAS ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(534, '123-749-931-000', 'D\'TWIN STARS INDUSTRIAL SALES AND SERVICES', 'VAT', '#117 AVANCENA STREET, BRGY. SOUTH FUNDIDOR,MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(535, '000-254-247-099', 'DU EK SAM INC', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(536, '000-254-247-018', 'DU-EK SAM, INC.', 'VAT', 'DOLMAX SUBD. BRGY. CAMALIG, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(537, '000-254-247-00018', 'DU-EK SAM, INC.', 'VAT', 'DOLMAX SUBD. BRGY. CAMALIG, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(538, '418-589-773-000', 'DUMALAG CONVINIENCE STORE', 'VAT', 'SAN MARTIN ST POBLACION DUMALAG, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(539, '001-004-583-018', 'DUNKIN\' DONUTS', 'VAT', 'SHOEMART DELGADO ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(540, '000-122-565-158', 'DUNKIN\' DONUTS', 'VAT', 'PAG-ASA NCR, SECOND DISTRICT QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(541, '000-122-565-241', 'DUNKIN\' DONUTS (GOLDEN DONUTS INC)', 'VAT', 'PASIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(542, '008-384-536-001', 'DV&S MEX FOODS CO.', 'VAT', 'B315 & B329 UPTOWN CENTER KATIPUNAN AVE.QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(543, '143-267-487-001', 'E AND L AUTO SUPPLY', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(544, '438-005-388-000', 'E AND S MEAT CORPORATION', 'V&NV', 'BRGY. SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(545, '007-069-897-005', 'E.P. CREDIT CORP.', 'VAT', 'L 98 STREET, BARANGAY DESAMPARADOS, JARO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(546, '006-509-389-000', 'E.W.J. VENTURES, INC.', 'VAT', 'PASIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(547, '258-674-057-002', 'EAGLE\'S HOTEL AND FUNCTION HALL', 'VAT', 'BANTAYAN ST., SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(548, '483-977-526-0001', 'EARL OF SANDWICH', 'VAT', 'EX. CT101, UPPER GROUND FLR. MALL', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(549, '483-977-526-001', 'EARL OF SANDWICH', 'VAT', 'UPPER GROUND FLR. MALL EXPANSION SM CITY B. AQUINO AVE., BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(550, '483-977-526-004', 'EARL OF SANDWICH', 'VAT', 'SM MALL OF ASIA JW DIOKNO BLVD BRGY 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(551, '009-629-474-003', 'EAST ASIA HOT POT INC.,', 'VAT', 'AYALA MALLS, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(552, '177-178-002-000', 'EASTMAN ELECTRICAL CENTER', 'VAT', 'LEDESMA , CORNER QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(553, '008-879-746-001', 'ECONORTH RESORT VENTURES, INC', 'VAT', 'BRGY. VILLA LIBERTAD, EL NIDO PALAWAN', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(554, '158-996-153-003', 'E-CONVENIENCE STORE', 'NV', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(555, '158-966-153-003', 'E-CONVENIENCE STORE', 'NV', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(556, '928-934-693-000', 'EDDIE-C TRADERS', 'VAT', 'TIMAWA COR. LOPEZ JAENA STS., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(557, '158-500-879-000', 'EDD\'S VEGE AND SPICES STORE', 'NV', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(558, '476-901-737-000', 'EGARD MARKETING, INC', 'VAT', 'LEDESMA ST., MAGSAYSAY,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(559, '001-568-141-005', 'EGGS, BEANS & GRAINS, INC.', 'VAT', 'PRE DEPARTURE AREA NORTHWING NAIA 3 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(560, '267-245-105-000', 'EK TRADING & MERCHADISING', 'VAT', 'LEDI SUPERMART BLDG.RIZAL ST ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(561, '281-836-124-0000', 'EL CORPORATION', 'VAT', 'BRGY. KAUSWAGAN,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(562, '006-803-024-014', 'EL FASHION N DESIGN CORP.', 'VAT', 'UNIT 5B BLDG. C THE OUTLET PUEBLO VERDE MACTAN ECONOMIC ZONE II BASAK LAPU-LAPU', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(563, '010-882-821-000', 'EL RINCON DE LOS SABORES (ART AND CULTURE AT THE TABLE INC.)', 'VAT', 'TAFT SOUTH, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(564, '006-231-476-000', 'ELECO-1 EMPLOYEE MULTI- PURPOSE COOPERATIVE', 'VAT', 'TIGBUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(565, '009-071-449-00000', 'ELECTRONIC TRANSFER AND ADVANCE PROCESSING, INC', 'VAT', '3734 BAUTISTA ST., BRGY. PALANAN MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(566, '438-004-597-000', 'ELI EVEREST CARGO SERVICES INC.', 'VAT', 'SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(567, '136-793-051-000', 'ELLEN DUMAYAS ABELITA', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(568, '472-369-289-000', 'ELMER LEE A. BRANA', 'NV', '113 LUNA ST., LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(569, '923-422-793-000', 'ELPA LEGASPI DRESSED CHICKEN', 'NV', 'SUPERMARKET, BRGY. FLORES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(570, '143-267-487-000', 'ELVIE ENTERPRISES', 'VAT', 'BURGOS ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(571, '179-485-710-000', 'ELYNNA\'S CAFE', 'VAT', 'COSTA DEL ESTRILLA FOOD PARK BITO-ON JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(572, '768-675-886-000', 'E-MAX HARDWARE', 'VAT', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(573, '141-170-055-000', 'EMERITA DELA CRUZ GALLINERO-PROP,', 'VAT', 'FUNDA DALIPE,SAN JOSE,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(574, '006-126-000-001', 'EMILION SPECIALTY RESTAURANT', 'VAT', 'GENERAL LUNA ST., BRGY. INDAY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(575, '658-117-392-00008', 'EMINENT SHOP AND GENERAL MERCHANDISE INC', 'VAT', 'SAN JOSE WARD POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(576, '008-511-592-001', 'EMPIRE 1010 TRADING CO.', 'NV', 'BRGY. TABUCAN MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(577, '005-443-368-000', 'EON REALTY AND DEVELOPMENT CORPORATION', 'VAT', 'JALANDONI ST., BRGY. HIPODROMO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(578, '007-865-477-00001', 'EPIC GLOBAL VENTURES CORP.', 'VAT', 'BALABAG, BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(579, '007-865-477-001', 'EPIC RESTAURANT', 'VAT', 'BALABAG BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(580, '007-189-834-023', 'EPICUREAN PARTNERS EXCHANGE INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(581, '225-570-714-00000', 'EQUILIBRIUM INTERTRADE CORPORATION', 'VAT', 'THE PLACE BLDG.,NATIONAL HIGHWAY BRGY.TUNASAN MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(582, '225-570-714-000', 'EQUILIBRIUM INTERTRADE CORPORATION', 'VAT', 'BRGY.TUNASAN MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(583, '008-686-273-002', 'EQUIPLUS CORPORATION', 'VAT', 'BRGY. SAMBAG, JARO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(584, '009-126-901-000', 'ERAWAN PHILIPPINES ( ASEANA ) INC.', 'VAT', 'ASEANA BUSINESS PARK, BACLARAN CITY OF PARANAQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(585, '765-210-773-001', 'ERNA L. BAYLON', 'VAT', 'FUNDA-DALIPE,SAN JOSE,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(586, '643-824-977-000', 'ERSAO', 'VAT', 'CITY TIME SQUARE, GAISANO ICC, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(587, '288-533-731-001', 'ERSI MEAT PRODUCTS', 'VAT', 'G/F BLDG. ANGELES ARCADE MABINI', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(588, '488-815-199-000', 'ERV PANAY SALES CORPORATION', 'VAT', 'GRRENFIELD SUBD., CUBAY, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(589, '232-155-815-000', 'ESCL ENTERPRISES', 'VAT', 'PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(590, '438-081-084-004', 'ESPANIA TAPAS AND W. FIREWOOD PIZZERIA', 'VAT', 'BAGUIO CITY, BENGUET', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(591, '117-320-222-000', 'ESPAÑOLA UPHOLSTERY', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(592, '610-662-726-00021', 'ESPRUTINGKLE CONVENIENCE STORES CORPORATION (7-ELEVEN)', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(593, '271-514-499-002', 'ESPRUTINGKLE FOOD CORPORATION', 'VAT', 'SUSANA BLDG.SAN JOSE DALIPE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(594, '271-514-499-004', 'ESPRUTINGKLE FOOD CORPORATION', 'VAT', 'HUERVANA ST., CORNER DIVINAGRACIA STREET, LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(595, '271-514-499-027', 'ESPRUTINGKLE FOOD CORPORATION', 'VAT', 'BRGY.ATABAY SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(596, '271-514-499-010', 'ESPRUTINGKLE FOOD CORPORATION', 'VAT', 'COLUMBA SENUOE BLDG.NATIONAL HIGHWAY COR.ROOSEVELT ST.,SANTA BARBARA', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(597, '009-793-103-000', 'ESPRUTINGKLE FOOD CORPORATION', 'VAT', 'SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(598, '199-840-484-001', 'ESPRUTINGKLE GAS AND SERVICE STATION (LESTER MARK E. YEE)', 'VAT', 'SALAZAR ST., SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(599, '009-817-084-000', 'ESPRUTINGKLE PIZZA PARLOR CORPORATION', 'VAT', 'ROBINSONS PLACE ANTIQUE SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(600, '008-596-949-010', 'EST. NAMNAM, INC.', 'VAT', 'AYALA MALLS MANILA BAY ASEAN AVE. COR. ASEANA CITY TAMBO, PARAÑAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(601, '434-960-288-000', 'EVE CONSUMERS CHANNEL, INC.', 'VAT', 'BRGY.SAN JOSE, SAN MIGUEL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(602, '002-925-972-000', 'EVER COMMONWELATH CENTER INC', 'VAT', 'DON MARIANO MARCOS AVE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(603, '126-150-818-002', 'EVER SUPREME MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(604, '483-954-419-000', 'EVERGOLD BUILDERS SALES CENTER INC.', 'VAT', '19 QUEZON ST., SAMPAGUITA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(605, '008-644-409-00017', 'EVOLVE TECH LIFESTYLE INC.,', 'VAT', 'UNIT A17A, GF FESTIVE WALK MALL, ILOILO BUSINESS PARK', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(606, '183-280-616-000', 'EVZ PHARMACY - MANDURRIAO', 'VAT', 'Q.ABETO ST.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(607, '183-280-646-002', 'EVZ PHARMACY 2', 'VAT', 'LAPAZ ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(608, '183-280-646-000', 'EVZ PHARMCY- MANDURRIAO', 'VAT', 'Q. ABETO ST., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(609, '183-280-646-001', 'EVZ PHARMCY- MISSION', 'VAT', 'MISSION ROAD, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(610, '010-747-995-000', 'EXCELSIOR VENTURES CORP.', 'VAT', 'DOOR #1 73RD LACSON ST.,MANDALAGAN BACOLOD CITY NEGROS OCCIDENTAL PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(611, '200-068-519-00034', 'EXECUTIVE OPTICAL, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(612, 'EWT', 'EXPANDED WITHHOLDING TAX (EWT)', 'N/A', 'BIR', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(613, '117-398-424-000', 'EXTRA BOWL FOOD HOUSE (HILARIO A. BORRO)', 'VAT', 'TAFT NORTH, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(614, '008-518-953-029', 'EYE SOCIETY INC.', 'VAT', 'SM CITY BENIGNO AQUINO AVE. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(615, '277-667-810-000', 'EZEKIEL FOOD PRODUCTS', 'VAT', 'ZONE 7, BRGY. SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(616, '277-677-810-000', 'EZEKIEL FOOD PRODUCTS', 'VAT', 'ZONE 7, BRGY. SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(617, '055-166-561-000', 'FABSON INC', 'VAT', 'LEDESMA ST.,BRGY. ED GANZON, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(618, '005-166-561-000', 'FABSON, INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(619, '174-239-631-013', 'FAITH FARMS', 'VAT', 'DOOR 1 LMT BLDG., FENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(620, '767-185-201-012', 'FAITH FARMS INC', 'VAT', 'DOOR 1 LMT BLDG., FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(621, '767-185-201-013', 'FAITH FARMS PORK SHOP', 'NV', 'LOPEZ-VILLALOBOS 2021 EL 98 ST. EL 98 JARO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(622, '767-185-201-011', 'FAITH FARMSPORK SHOP', 'NV', 'RIZAL ST., LAPAZA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(623, '265-028-662-000', 'FALARDO\'S GAS OLINE STATION', 'VAT', 'BRGY MALI-AO PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(624, '407-936-929-000', 'FALSIS RICE STORE', 'NV', 'DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(625, '230-393-680-136', 'FAMILY HEALTH & BEAUTY CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(626, '230-393-680-762', 'FAMILY HEALTH & BEAUTY CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(627, '230-393-680-478', 'FAMILY HEALTH AND BEAUTY CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(628, '230-393-680-404', 'FAMILY HEALTH AND BEAUTY CORP.', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(629, '230-393-680-209', 'FAMILY HEALTH AND BEAUTY CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(630, '230-393-680-058', 'FAMILY HEALTH AND BEAUTY CORP.- VICTORY', 'VAT', 'CALOOCAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(631, '230-393-680-569', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'MANDURRIAO ABETO MIRASOL TAFT SOUTH ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(632, '230-393-680-403', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'BARANGAY ZONE 3 (POB.) STA. BARBARA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(633, '230-393-680-568', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(634, '230-393-680-00906', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(635, '230-393-680-497', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(636, '230-393-680-906', 'FAMILYHEALTH & BEAUTY CORP.', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(637, '230-393-680-913', 'FAMILYHEALTH & NEAUTY CORP', 'VAT', 'ZARAGA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(638, '230-393-680-570', 'FAMILYHEALTH AND BEAUTY CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(639, '230-393-680-616', 'FAMILYHEALTH AND BEAUTY CORP.', 'VAT', '', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(640, '230-393-680-679', 'FAMILYHEALTH AND BEAUTY CORP.', 'VAT', 'Q ABETO STREET MANDURRIAO,ILOILO CIY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(641, '230-393-680-717', 'FAMILYHEALTH AND BEAUTY CORP.-PUREGOLD JARO', 'VAT', 'EL 98 ROAD CORNER CUARTERO JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(642, '230-393-680-109', 'FAMILYHEALTH AND BEAUTY CORP.-SM HYPERMARKET COM.', 'VAT', 'CIVILCOR OUR LADY OF FATIMAI LOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(643, '008-730-175-045', 'FAMOUS BELGIAN WAFFLES', 'VAT', 'METRO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(644, '426-178-296-000', 'FAMOUS FASHION LINE', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(645, '000-249-265-00001', 'FAR EASTERN HARDWARE & FURNITURE ENT., INC.', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(646, '000-249-265-001', 'FAR EASTERN HARDWARE & FURNITURE ENT., INC.', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(647, '923-412-725-001', 'FAR EASTERN HARDWARE AND FURNITURE ENT., INC.', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(648, '000-249-265-000', 'FAR EASTERN HARDWARE AND FURNITURE ENT., INC.', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(649, 'FA', 'FARE', 'NV', 'X', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(650, '154-269-875-0004', 'FARM TO TABLE RESTAURANT', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(651, '154-269-875-004', 'FARM TO TABLE RESTAURANT', 'VAT', 'BRGY. TAFT NORTH MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(652, '942-434-288-005', 'FARMACIA TOPAC', 'VAT', 'BRGY. SAN PEDRO, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(653, '008-204-649-074', 'FAST RETAILING PHILIPINES INC.', 'VAT', 'FESTIVE WALK MALL ANNEX A ILOILO CUSINESS PARK, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(654, '008-204-649-034', 'FAST RETAILING PHILIPPINES INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(655, '008-204-649-00074', 'FAST RETAILING PHILIPPINES INC.', 'VAT', 'FESTIVE WALK MALL ILOILO BUSINESS PARK', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(656, '008-204-649-008', 'FAST RETAILING PHILIPPINES INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(657, '008-204-649-068', 'FAST RETAILING PHILIPPINES INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(658, '412-622-286-000', 'FAT BUDDIES RESTO', 'VAT', 'MISSION EXTENSION,LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(659, '946-253-141-000', 'FAT N\' THIN RESTAURANT', 'VAT', '21 AVENUE BLDG.,BENIGNO AVENUE, SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(660, '005-164-213-00003', 'FAVORITE ITALIAN EATERY INC', 'VAT', 'SM CITY DIVERSION RD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(661, '440-521-663-016', 'FAVORITE ITALIAN EATERY INC.', 'VAT', 'ROBINSONS PLACE LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(662, '917-117-178-006', 'FBC SHELL STATION - VIII (FRANCIS JOEL P. DELA CRUZ)', 'VAT', 'OSMENA ST., MOHON AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(663, '917-117-178-001', 'FBC SHELL STATION VI', 'VAT', 'UNGKA II PAVIA,ILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(664, '005-441-421-00', 'FEAST GROUP INC', '0', 'Q ABETO ST., MAND.ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(665, '486-709-816-000', 'FEB. 23 CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(666, '275-540-614-000', 'FEDERAL EXPRESS PACIFIC,LLC', 'VAT', '11/FL,ZUELIG BUILDING,MAKATI AVE. COR. PASEO DE ROXAS,MAKATI CITY 1200 PHIL', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(667, '908-802-815-000', 'FEDMAN ENTERPRISE', 'VAT', 'ROBINSON\'S PLACE COR. MABINI-LEDESMA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(668, '009-593-138-000', 'FELI ILOILO INC.', 'VAT', 'UNIT 101 PARAGON PLAZA PISON AVENUE BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(669, '151-460-431-000', 'FELICIAS RETAIL&PASTRY SHOP', 'VAT', 'BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(670, '009-911-766-000', 'FESTIVE WALK CINEMAS', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(671, '604-879-756-000', 'FGD FOODIES INC.', 'VAT', 'GROUND LEVEL MT. CARMEL BLDG. DELGADO ST. GANZON ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(672, '604-879-756-001', 'FGD FOODIES INC.', 'VAT', 'GT TOWN CENTER UNGKA II PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(673, '005-341-312-104', 'FIGARO COFFEE SYSTEMS, INC.', 'VAT', 'WALTERMART NORTH EDSA VETERANS VILLAGE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(674, '133-162-738-008', 'FILBERT\'S 6 FOODS', 'VAT', 'FESTIVE WALK MALK-MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(675, '000-159-510-000', 'FIRST ASIA REALTY DEVELOPMENT CORP', 'VAT', 'WACK-WACK VILLAGE MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(676, '276-203-707-00018', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(677, '276-203-707-016', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'J.C. ZULUETA ST., OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(678, '000-312-799-004', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(679, '276-203-707-00025', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'BALASAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(680, '276-203-707-00030', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(681, '276-203-707-0030', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(682, '276-203-707-010', 'FIRST PACIFIC FORTUNE COM. CORP.', 'VAT', 'COR. GUANCO-RIZAL ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(683, '008-419-028-000', 'FISHER RETAIL INC.', 'VAT', '-', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(684, '000-000-000-000', 'FIVE STAR SEAFOODS RESTAURANT, INC', 'VAT', 'BOARDWALK AVE.,SMALLVILLE COMPLEX ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(685, '483-087-371-000', 'FIVE STAR SEAFOODS RESTAURANT, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(686, '136-788-230-001', 'FJH BACKRIBS RESTAURANT', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(687, '286-497-335-000', 'FLG INDUSTRIAL PRODUCTS MERCHANDISING', 'VAT', 'CIRCUMFERENTIAL ROAD, BRGY.PANDAC,PAVIA,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(688, '198-893-295-000', 'FLORABEL B. VILLANUEVA', 'NV', '69 JALANDONI DT.,JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(689, '602-667-327-000', 'FLOUR BAKERY AND BRUNCH CAFE OPC', 'VAT', 'DR. HENRY CHUSUEY INTERNATIONAL CENTER JALANDONI ST. SAN AGUSTIN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(690, '802-667-327-000', 'FLOUR BAKERY AND BRUNCH CAFÉ OPC', 'VAT', 'JALANDONI ST., COR AURORA SUBD., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(691, '602-667-327-00000', 'FLOUR BAKERY AND BRUNCH CAFÉ OPC', 'VAT', 'JALANDONI ST., SAN AGUSTIN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(692, '008-376-063-004', 'FLOUR INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(693, '258-147-821-001', 'FLOURISH PURIFIED WATER', 'NV', 'COR. COMM. CIVIL&MISSION RD. EXT. ST., LAPAZ, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(694, '502-032-187-000', 'FLOW\'S WATER STATION', 'VAT', 'LOT 1 BLK 1, GREEN VALLEY SUBD. BUHANG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(695, '008-767-585-00000', 'FOOD PANDA PHILIPPINES, INC', 'VAT', '29/F PACIFIC STAR BLDG. MAKATI AVE COR DEN GIL PUYAT AVE BEL-AIR NCR FOURTH DISTRICT CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(696, '272-433-650-001', 'FOODPHIL CORPORATION', 'VAT', 'CEBU CITY, CEBU PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(697, '757-014-760-000', 'FOODWORLD 888 INC.', 'VAT', 'DONATO PISON AVE.,SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(698, '007-081-686-072', 'FOOTWEAR SPECIALTY RETAILERS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(699, '005-028-764', 'FORBES TAXI', 'NV', 'VILLA LAS PALMAS JARO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(700, '200-202-804-00002', 'FOREST LAKE DEVELOPMENT INC', 'VAT', 'BRGY. CALAJUNAN,ONATE EXT., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(701, '007-736-279-00001', 'FOREVER 21', 'VAT', 'UNIT 215 250 A SM MEGAMALL BLDG A EDSA COR. J. VARGAS AVE WACK-WACK GREENHILLS,CITY OF MANDALUYONG NCR,SECOND DISTRICT PHILS. 1550', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(702, '007-736-279-00016', 'FOREVER 21', 'VAT', 'ASINAN PHASE 3 C5 EXT LA HUERTA, PARAÑAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(703, '007-736-279-013', 'FOREVER AGAPE AND GLORY, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(704, '117-311-414-000', 'FORT SAN PEDRO DRIVE IN', 'VAT', 'FORT SAN PEDRO, BRGY.VETERANS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(705, '768-812-580-006', 'FOUR RCCS ALTERNATE OPC.', 'VAT', '109B G/F SM CITY ILOILO BENIGNO AQUINO AVE. SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(706, '008-439-959-000', 'FOUR SEASON HOTPOT INC.,', 'VAT', 'BLDG. E SM MALL OF ASIA SEASIDE BRGY.76 NCR PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(707, '009-279-819-00001', 'FOURTIFY INC.,', 'VAT', '2/F NASSONS BLDG.,8467 WEST SERVICE ROAD, SUN VALLEY PARANEQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(708, '009-074-986-00010', 'FRANKIE\'S NY WINGS INC.', 'VAT', 'TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:01', '2026-07-13 03:05:01'),
(709, '003-460-168-174', 'FREEMONT FOODS CORP.', 'VAT', 'BOARDWALK AVE. COR. G. PISON AVENUE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(710, '003-460-168-064', 'FREEMONT FOODS CORP.', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(711, '003-460-168-036', 'FREEMONT FOODS CORP.', 'VAT', 'ROBINSONS PLACE , LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(712, '003-460-168-034', 'FREEMONT FOODS CORP.', 'VAT', 'COR. E. LOPEZ- JALANDONI STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(713, '003-460-168-210', 'FREEMONT FOODS CORP.', 'VAT', 'B. AQUINO AVE. , JARO WEST DIVERSION RD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(714, '003-460-168-046', 'FREEMONT FOODS CORP.', 'VAT', 'BOARDWALK AVE. COR. G. PISON AVENUE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(715, '003-460-168-030', 'FREEMONT FOODS CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(716, '003-460-168-237', 'FREEMONT FOODS CORP.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(717, '003-460-168-00216', 'FREEMONT FOODS CORPORATION', 'VAT', '001 LOWER LEVEL CITYMALL COR.PRES CORA', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(718, '003-460-168-00219', 'FREEMONT FOODS CORPORATION', 'VAT', 'FESTIVE MALL ABETO MIRASOL TAFT', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(719, '003-460-168-00035', 'FREEMONT FOODS CORPORATION', 'VAT', 'IZNART COR.LEDESMA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(720, '003-460-168-035', 'FREEMONT FOODS CORPORATION', 'VAT', 'B. AQUINO MANDURRIAO SAN RAFAEL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(721, '003-460-168-00034', 'FREEMONT FOODS CORPORATION', 'VAT', 'COR. E. LOPEZ JALANDONI ST. OUR LADY OF FATIMA JARO ILOILO CITY CAPITAL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(722, '003-460-168-00210', 'FREEMONT FOODS CORPORATION', 'VAT', 'B. AQUINO MANDURRIAO SAN RAFAEL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(723, '003-460-168-071', 'FREEMONT FOODS CORPORATION-PASSI CITY', 'VAT', 'F.PALMAREZ COR SAN JAUN ST., PASSI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(724, '008-026-182-017', 'FRESH HEALTHY JUICE BOOSTER, INC.', 'VAT', 'UP TOWN CENTER BRGY. UP CAMPUS KATIPUNAN AVE. DILIMAN QC', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(725, '000-333-173-740', 'FRESH N\' FAMOUS FOODS INC', 'VAT', 'GRD. FLR CITYMALL PAROLA FORT SAN PEDRO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(726, '000-333-173-687', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'CITYMALL TAGBAK ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(727, '000-333-173-855', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(728, '000-333-173-00750', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'ROBINSONS PLACE JARO,E. LOPEZ ST. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(729, '000-333-173-00076', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'CITYMALL TAGBAK ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(730, '000-333-173-00078', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'LEDESMA CORNER J DE LEON', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(731, '000-333-173-00855', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'LGF SM CITY MANDURRIAO BOLILAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(732, '000-333-173-00687', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'CITYMALL TAGBAK ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(733, '000-333-173-656', 'FRESH N\' FAMOUS FOODS INC.', 'VAT', 'CITYMALL TAGBAK ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(734, '000-333-173-179', 'FRESH N\' FAMOUS FOODS, INC.', 'VAT', 'SANTA CRUZ POB CEBU CITY CAPITAL CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(735, '000-333-173-750', 'FRESH N\'FAMOUS FOODS INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(736, '008-174-699-047', 'FRONTLAKE INC', 'VAT', 'FESTIVEWALL MALL, MEGAWORLD BLVD.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(737, '008-174-699-045', 'FRONTLAKE INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(738, '008-174-699-022', 'FRONTLAKE INC.', 'VAT', 'ROBINSONS PLACE ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(739, '008-174-699-048', 'FRONTLAKE, INC', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(740, '008-174-699-051', 'FRONTLAKE, INC.', 'VAT', 'CALTEX EAST BACOLOD, CIRCUMFERENTIAL ROAD CORNER BURGOS AVE., BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(741, '442-973-184-004', 'FRUITFULL SMOOTHIES AND JUICES CORPORATION', 'VAT', 'ROBINSONS PLACE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(742, '487-873-517-000', 'FU-DO PRIME CORPORATION', 'VAT', 'ROBINSONS PLACE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(743, '497-973-517-000', 'FU-DO PRIME CORPORATION', 'VAT', 'ROBINSONS PLACE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(744, '010-015-248', 'FULLWAY MEGAHOME BUILDERS SUPPLY, INC.', 'VAT', 'DIVERSION ROAD, BRGY. SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(745, '219-225-647-047', 'FULLY BOOKED - SM ILOILO', 'VAT', 'NORTHPOINT 2F SM CITY ILOILO, B. AQUINO AVENUE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(746, '612-451-062-00001', 'FUTUREFRESH INC.', 'VAT', 'THE POWERPLANT MALL ROCKWELL CENTER, MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(747, '005-176-347-000', 'FUTURELOGIC CORPORATION', 'VAT', 'BENAVIDEZ PEDRO CRUZ SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(748, '009-927-143-009', 'FV MARKETING CORPORATION', 'VAT', 'BRGY. SAN PEDRO, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(749, '430-744-269-000', 'G- LUCKY HOME BUILDERS', 'NV', 'BRGY. PARARA NORTE, TIGBAUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(750, '127-737-375-000', 'G.G. GASOLINE STATION', 'VAT', 'AVANCENA ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(751, '339-254-156-000', 'GADGET HEADZ', 'VAT', 'JALANDONI-DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(752, '339-254-156-001', 'GADGET HEADZ GADGETS AND ACCESSORIES', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(753, '276-203-707-030', 'GAISANO CAPITAL ICC.', 'VAT', 'SEN. BENIGNO JR., SAN RAFAEL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(754, '000-704-113-015', 'GAISANO GRAND ESTANCIA', 'VAT', 'ESTANCIA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(755, '004-272-277-006', 'GAISANO GRAND MALL OF ANTIQUE (DYNASTY MGMT. AND DEV\'T CORP.)', 'VAT', 'SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(756, '102-270-352-000', 'GALLINERO ENGINEERING WORKS', 'VAT', 'BRGY. CUARTERO- HIGHWAY, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(757, '476-385-875-000', 'GARAJE FOOD DISTRICT', 'NV', 'BRGY. TABUC SUBA, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(758, '216-917-612-000', 'GARDEN RESTAURANT', 'VAT', 'MA.CLARA AVE., AURORA SUBD., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(759, '305-538-426-003', 'GAY JOY B. DEOCAMPO', 'VAT', 'MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(760, '218-157-626-023', 'GAZZ UP, INC', 'VAT', 'CORNER LEDESMA-JALANDINI STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(761, '194-003-872-000', 'GDITHS TRADING', 'VAT', 'DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(762, '192-003-872-00001', 'GDITH\'S TRADING', 'VAT', 'LEDESMA-FUENTES ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(763, '192-003-872-000', 'GDITH\'S TRADING', 'VAT', 'DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(764, '192-002-872-002', 'GDITH\'S TRADING', 'VAT', 'BRGY.NORTH BALUARTE, MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(765, '119-990-129-073', 'GDR PETROLEUM STORAGE AND REFILLING STATION', 'VAT', 'BRGY. DEMOCRACIA, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(766, '119-990-129-0073', 'GDR PETROLEUM STORAGE&REFILLING STATION', 'VAT', 'BRGY. DEMOCRACIA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(767, '119-990-129', 'GDR TAXI', 'VAT', 'SUBD. GUZMAN ST., MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(768, '472-124-753-000', 'GEELANE ENTERPRISES', 'VAT', 'LAGING HANDA, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(769, '191-760-869-000', 'GEMS ELECTONICS', 'VAT', 'CRES BLDG.,VALERIA EXT.,ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(770, '927-414-441-000', 'GENERIKA DRUGSTORE- JARO MARKET', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(771, '907-737-609-002', 'GERALD C. TUPAZ', 'VAT', 'BRGY.POB.ILAYA, DUMARAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(772, '108-162-230-000', 'GER-B MARKETING', 'VAT', 'N. MAPA ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(773, '009-670-919-0001', 'GGC DINING CONCEPT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(774, '009-670-919-00006', 'GGC DINING CONCEPTS', 'VAT', 'FOOD HALL NO.4 SM FOOD HALL BRGY.BOLILAO MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(775, '931-267-038-000', 'GHB VEGETABLES STORE', 'NV', 'SUPERMARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(776, '142-903-761-000', 'GHS MARKETING', 'NV', 'LAPUZ LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(777, '006-070-024-007', 'GILIGANS ISLAND BAGUIO INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(778, '006-070-024-014', 'GILIGANS ISLAND BAGUIO INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(779, '133-162-730-000', 'GILIGAN\'S RESTAURANT', 'VAT', 'FESTIVE WALK MALL ILOILO BUSINESS PARK', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(780, '438-862-847-000', 'GINA\'S SEAFOOD RESTAURANT', 'VAT', 'SAN JUAN ST. BRGY. 10, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(781, '000-225-679-029', 'GINGERSNAPS MARKETING', 'VAT', 'SM CITY DIVERSION ROAD, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(782, '000-385-125-074', 'GIORDANO FTB, INC', 'VAT', '3RD FLOOR-SHOP 17 NEWPORT CITY, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(783, '000-385-125-079', 'GIORDANO FTB, INC', 'VAT', 'LGF FOODCOURT SM CITY,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(784, '010-459-763-010', 'GIZMOTECH CORPORATION', 'VAT', '3RD FLR. SM CITY DIVERSION ROAD BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(785, '902-331-672-000', 'GKM FOODS ENTERPRISE', 'NV', 'E-LOPEZ ST., SAN VICENTE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(786, '003-741-931-000', 'GLOBAL MISSION FOUNDATION INC', 'NV', 'CUBAO,QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(787, '005-164-288-014', 'GLOBAL VILLAGERS INTERNATIONAL, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(788, '005-164-288-00014', 'GLOBAL VILLAGERS INTERNATIONAL,INC.', 'VAT', 'UGF SM CITY BENIGNO AQUINO AVE MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(789, '006-770-877-001', 'GLOBALINX ASIA GENERAL MERCHANDISE AND SERVICES INC', 'VAT', 'BRGY. SAN JOSE, SAN MIGUEL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(790, '000-360-915-00203', 'GLOBE', 'VAT', 'LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(791, '000-360-916-00203', 'GLOBE', 'VAT', 'ROBINSONS PLACE , E. LOPEZ ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(792, '000-768-480-0055', 'GLOBE TELECOM INC.', 'VAT', 'SM CITY ILOILO, BOLILAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(793, '000-768-480-005', 'GLOBE TELECOM INC.', 'VAT', 'SM CITY BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(794, '000-768-480-00055', 'GLOBE TELECOM INC.-', 'VAT', 'SM AQUINO AVENUE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(795, '000-768-480-00111', 'GLOBE TELECOM INC.- SM DELGADO', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(796, '000-768-480-0111', 'GLOBE TELECOM INC.- SM DELGADO', 'VAT', 'SM DELGADO COR DELGADO-VALERIA STS., , ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(797, '000-360-916-203', 'GLOBE TELECOM, INC.', 'VAT', 'ROBINSONS PLACE , E. LOPEZ ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(798, '131-370-706-000', 'GO SHOP TRADING', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(799, '009-528-364-003', 'GOLDEN 8 FOLD GROUP INC.', 'VAT', 'GREENHILLS SHOPPING CENTER, GREENHILLS, SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(800, '000-068-427-1085', 'GOLDEN ABC INC', 'VAT', 'ROXAS VILLAGEM ILOILO CITY, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(801, '000-068-427-331', 'GOLDEN ABC, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(802, '000-068-427-01085', 'GOLDEN ABC, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(803, '000-068-427-552', 'GOLDEN ABC, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(804, '000-068-427-261', 'GOLDEN ABC, INC.', 'VAT', 'SM CITY ILOILO, B.S AQUINO AVE., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(805, '000-068-427-265', 'GOLDEN ABC,INC.', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(806, '000-068-427-236', 'GOLDEN ABC,INC.', 'VAT', 'UGF SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(807, '000-068-427-000', 'GOLDEN ABC,INC.', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(808, '000-068-427-522', 'GOLDEN ABC,INC.', 'VAT', 'UGF SM CITY ,BENIGNO AQUINO AVE.,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(809, '000-121-242-640', 'GOLDEN ARCHES DEVELOPMENT CORP.', 'VAT', 'KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(810, '000-121-242-040', 'GOLDEN ARCHES DEVELOPMENT CORP.', 'VAT', 'MCDONALD\'S MCCAFE EDSA PANAY BRANCH', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(811, '000-121-242-379', 'GOLDEN ARCHES DEVELOPMENT CORP.', 'VAT', 'BRGY BALABAG MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(812, '000-121-242-570', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'NV', 'BOHOL ST., AYALA BUSINESS PARK CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(813, '000-121-242-485', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'POBLACION, TOLEDO CITY, CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(814, '000-121-242-705', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'UN AVE COR DEL PILAR ST COR CORTADA ST 666 ZONE 072 ERMITA MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(815, '000-121-242-371', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(816, '000-121-242-043', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(817, '000-121-242-044', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(818, '000-121-242-087', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(819, '000-121-242-436', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(820, '000-121-242-808', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(821, '000-121-242-692', 'GOLDEN ARCHES DEVELOPMENT CORPORATION', 'VAT', 'ROXAS AVENUE KALIBO AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(822, '000-121-242-184', 'GOLDEN ARCHES DEVELOPMENT CORPORATION (MCDONALD\'S RESTAURANT)', 'VAT', 'MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(823, '449-676-885-001', 'GOLDEN BEVERAGE DISTRIBUTOR', 'VAT', 'GUSTILO, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(824, '297417012-006', 'GOLDEN COWRIE FRANCHISING INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(825, '297-417-023-006', 'GOLDEN COWRIE FRANCHISING INC.', 'VAT', 'LGF 017-18 SM CITY BENIGNO AQUINO AVE. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(826, '006-459-296-000', 'GOLDEN DELTA STEEL CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(827, '000-122-565-00138', 'GOLDEN DONUTS INC.- CALOOCAN', 'VAT', 'CALOOCAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(828, '217-372-852-000', 'GOLDEN JM FUEL TRADING', 'VAT', 'WEST TIMAWA, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(829, '630-222-091-001', 'GOLDEN PETROL DISTRIBUTION CORPORATION', 'VAT', 'ZONE 3, UNGKA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(830, '006-228-377-000', 'GOLDEN RAMEN CORPORATION', 'VAT', 'SM CITY ILOILO DIVERSION ROAD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(831, '145-783-009-000', 'GOLDEN VENTURES GASOLINE STATION', 'VAT', 'MAYOR FELIX GORRIETA AVENUE, BALABAG, PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(832, '008-966-761-002', 'GOLDENEATS INCORPORATED', 'VAT', 'SALITRAN II DASMARINAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(833, '271-514-499-017', 'GOLDILOCKS BAKESHOP, INC.', 'VAT', 'ROBINSONS PLACE ANTIQUE SAN RAFAEL', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(834, '009-720-295-002', 'GOMART CONVENIENCE STORE SMALLVILLE', 'VAT', 'DIVERSION RD. BRGY. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(835, '009-720-295-0001', 'GOMART- CONVENIENCE STORE-DIVERSION', 'VAT', 'DIVERSION ROAD, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(836, '946-328-771-001', 'GOOD CHOICE ELECT. AND APPLIANCE CENTER', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(837, '455-944-590-000', 'GOOD PARK NOODLE FACTORY', 'VAT', 'BRGY. CONSOLACION, SAN MIGUEL,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(838, '441-494-765-000', 'GOOD TASTE RESTAURANT', 'VAT', 'BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(839, '263-126-015-000', 'GOODNESS PHARMACY', 'NV', 'ZONE 14, CALAPARAN, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(840, '910-120-130-000', 'GOSON MARKETING', 'VAT', 'MABINI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(841, '005-983-241-000', 'GOTAN FOODS CORP.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(842, '452-037-009-000', 'G-PS FOOD CHOICES INC.', 'VAT', 'AVANCEÑA ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(843, '777-164-487-000', 'GR8PACK MERCHANDISING', 'VAT', 'TWO LORTON BLDG.,QUEZON ST.,KAUSWAGAN, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(844, '009-172-027-000', 'GRAB EXPRESS INC', 'VAT', '3/F POLAR CENTER BLDG., EDSA WACK WACK PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(845, '009-172-027-0000', 'GRABEXPRESS INC.', 'VAT', '3/F POLAR CENTER BLDG., EDSA WACK WACK PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(846, '104-148-728-000', 'GRACE M. JAVELOSA', 'VAT', 'PH 2 GRAN PLAINS SUBD.JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(847, '009-670-919-00001', 'GRACIA\'S LECHON BELLY', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(848, '758-892-813-000', 'GRACIASLOUINC', 'VAT', 'PASEO DE AKLAN,MABINI ST., KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(849, '006-227-778-006', 'GRAET FOODS CONCEPTS, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(850, '272-292-058-000', 'GRANDE BUILDINGS AND SUPPLY', 'VAT', 'CIVIL JAVELLANA JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(851, '008-176-821-033', 'GRANTLINE INC.', 'VAT', 'RODRIGUEZ SR. AVE. CORNERN RAMIREZ S QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(852, '010-074-223-000', 'GRANWILL FOOD MANAGEMENT CORPORATION', 'VAT', 'LASALETTE BLDG VALERIA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(853, '009-852-264-002', 'GREAT ALPHAWEST FOODS CORPORATION', 'VAT', 'WEST AVE. BRGY. TAAL, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(854, '009-852-264-000', 'GREAT ALPHAWEST FOODS CORPORATION', 'VAT', 'L1 CITY MALL PASSI CITY ILOILO-ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(855, '009-852-264-006', 'GREAT ALPHAWEST FOODS CORPORATION', 'VAT', 'SAN JOSE CAPITAL BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(856, '009-852-264-005', 'GREAT ALPHAWEST FOODS CORPORATION', 'VAT', 'POBLACION ILAWOD, CITY MALL PASSI, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(857, '006-227-778-027', 'GREAT FOODS CONCEPTS, INC', 'VAT', 'BRGY. SAN RAFEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(858, '446-952-003-000', 'GREAT HARVEST COMMODITIES PH,INC.', 'NV', 'BRGY. HINACTACAN,LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(859, '001-180-435-050', 'GREAT IMAGES SERVICES CORPORATION', 'VAT', 'SM CITY DIVERSION ROAD MANDURRIO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(860, '927-407-634-001', 'GREAT PEAK ENERGY GASOLINE STATION', 'VAT', 'COR. MANINI-LEDESMA STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(861, '927-407-634-002', 'GREAT PEAK ENERGY GASOLINE STATION', 'VAT', 'BURGOS-MAGDALO STS., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(862, '927-407-634-000', 'GREAT PEAK ENERGY GASOLINE STATION', 'VAT', 'CORNER MABINI-LEDESMA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(863, '009-852-264-004', 'GREATALPHAWEST FOODS CORP', 'VAT', 'BRGY. POBLACION ILAWON PASSI CITY, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(864, '006-227-778-034', 'GREATFOODS CONCEPTS INC.', 'VAT', 'SM SOUTHPOINT BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(865, '006-227-778-018', 'GREATFOODS CONCEPTS, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(866, '006-227-778-021', 'GREATFOODS CONCEPTS, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(867, '006-227-778-030', 'GREATFOODS CONCEPTS, INC.', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(868, '002-006-502-000', 'GREEN GARDEN, INC', 'VAT', '4TH FLR.TCT CENTRE, IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(869, '154-499-723-000', 'GREEN RIBBON MINIMART', 'VAT', 'GERONA ST.,GUIMBAL ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(870, '154-499-723', 'GREEN RIBBON MINIMART (EMILDA G. PALACIOS)', 'VAT', 'GUIMBAL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(871, '005-147-744-074', 'GREEN TEA INC.', 'VAT', 'BGC TAGUIG', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(872, '155-638-493-000', 'GREENPAK ENTERPRISES', 'VAT', '145-B CONGRESSIONAL AVE., BRGY. BAHAY TORO, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(873, '006-329-840-00019', 'GREENSTONE PHARMACEUTICAL, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(874, '000-329-888-008', 'GREYHOUND MARKETING CORPORATION', 'VAT', 'SM CITY BRGY. BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(875, '740-236-880-000', 'GRILLENIALS INC.', 'VAT', 'SM CITY ILOILO SEN. BENGNO AQUINO DRIVE MANDURRIAO,ILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(876, '775-401-498-000', 'GRILLER\'S OYSTER HOUSE-SMALLVILLE', 'VAT', 'SAN JOSE ST., BRGY. SAN RAFAEL, MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(877, '947-532-465-001', 'GRIP BEVERAGES MARKETING', 'VAT', 'BRGY.DEMOCRACIA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(878, '259-260-805-000', 'GRUMPY CAT RESTAURANT', 'NV', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(879, '937-199-238-004', 'GRUMPYCAT RESTAURANT', 'VAT', 'BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(880, '485-727-907-000', 'GRUPPO LA GUSTATION INCORPORATED', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(881, '235-045-267-004', 'GRYFFINHOUSE INC.', 'VAT', 'BLDG F. CABAHUG ST. KASAMBAGAN CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(882, '484-880-607-000', 'GT AND SON\'S FOOD VENTURES, INC.', 'VAT', 'HUERVANA ST.LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(883, '484-880-607-001', 'GT AND SON\'S FOOD VENTURES, INC.-TABUC SUBA', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(884, '204-073-057-000', 'GT FOOD PACKAGING SUPPLY', 'VAT', 'DOOR B. GARND ATLANTA BLDG., ORTIZ ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(885, '225-058-150-001', 'GT MIRANDA GASOLINE STATION', 'VAT', 'COR-FIGUERA-OSMENA STS STA FELOMINA ARVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(886, '919-270-385-000', 'GUIRJEN MOTORPARTS', 'NV', 'EL 98 ST., DESAMPARADOS JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(887, '923-415-165-002', 'GULAY KO VEGETABLE SUPPLY', 'NV', 'DELEON SUPER TERMINAL MARKET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(888, '008-675-559-021', 'H&M (H&M HENNES & MAURITZ INC)', 'VAT', 'UGF SM CITY, BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(889, '007-242-095-000', 'H AND M HENNES AND MAURITZ INC', 'VAT', 'UGF, SM CITY BENIGNO AQUINO AVENUE MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(890, '008-675-559-024', 'H AND M HENNES AND MAURITZ INC', 'VAT', 'UGF, SM CITY BENIGNO AQUINO AVENUE MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(891, '008-675-559-015', 'H AND M HENNES AND MAURITZ INC', 'VAT', 'SM MALL OF ASIA, BARANGAY 76, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(892, '941-261-553-000', 'H.F. ENTERPRISES', 'VAT', 'SIMON LEDESMA STREET, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(893, '010-700-908-00001', 'HAIDILAO PHILIPPINES RESTAURANT CORPORATION', 'VAT', 'SM MALL OF ASIA JW DIOKNO BLVD BRGY 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(894, '009-433-952-022', 'HAIRSALON WONDERS, INC', 'VAT', 'GREENHILLS MALL GREENHILLS SHOPPING CENTER, ORTIGAS AVE., GREENHILLS SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(895, '000-406-088-003', 'HAMMAD MANAGEMENT CORPORATION', 'VAT', 'SANTA CRUZ 1104 QUEZON CITY NCR, SECOND DISTRICT PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(896, '180-419-242-017', 'HANNAH\'S CAKE DECORS AND PARTY NEEDS', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(897, '008-658-631-276', 'HANSBURY, INC', 'VAT', 'SM CITY ILOILO DIVERSION ROAD MANDURRIAO DISTRICT ILOILO CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(898, '008-658-631-00276', 'HANSBURY,INC.', 'VAT', 'EX105-106 UPPER GROUND FLOOR,SM CITY ILOILO SENATOR BENIGNO AQUINO JR., AVENUE JARO WEST DIVERSION ROAD MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(899, '616-298-720-002', 'HANYEN SHOPPING CENTER', 'VAT', 'COR. LIBO-ON STS. RIZAL-TUGUISAN POB. GUIMBAL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(900, '145-635-090-000', 'HAP CHAN TRADING AND MANAGEMENT CORP.', 'VAT', 'ROBINSONS PLACE , LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(901, '613-611-339-001', 'HAPPY CAMPER RETAIL SOLUTIONS, INC.', 'VAT', 'SM CITY, BENIGNO AQUINO AVENUE, BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(902, '005-823-334-013', 'HAPPY FLASK SHOP', 'VAT', 'SM CITY GROUND MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(903, '009-057-807-016', 'HAPPY GO SHOPPING CENTER INC.', 'VAT', 'BALABAG BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(904, '474-31-272-000', 'HAPPY TIME HOUSEHOLD MDSG.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(905, '474-786-530-000', 'HARBOUR CITY DIMSUM HOUSE', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(906, '003-583-915-032', 'HARBOUR CITY DIMSUM HOUSE CO., INC', 'VAT', 'SM CITY BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(907, '003-583-915-00032', 'HARBOUR CITY DIMSUM HOUSE CO., INC', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(908, '010-015-896-000', 'HARMONY 158 FOODS CORPORATION', 'VAT', '1 ARNALDO BOULEVARD ST BAYBAY ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(909, '232-863-405-000', 'HARMONY BEAR FOOD MARKETING', 'VAT', 'ILOILO IZNART BRANCH DULALA BLDG. IZNART ST ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(910, '324-934-104-000', 'HAROMART GROCERY', 'VAT', 'TRADETOWN,DALIPE, SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(911, '006-521-821-018', 'HARVEST WHEAT BAKED FOODS, INC.', 'VAT', 'GRND. FLR. GAISANO CAPITAL, PASSI CITY, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(912, '006-521-821-008', 'HARVEST WHEAT BAKED FOODS, INC.', 'VAT', 'COR. DE LEON-QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(913, '006-521-821-032', 'HARVEST WHEAT BAKED FOODS, INC.', 'VAT', 'LEGASPI ST., MIAGAO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(914, '260-799-007-000', 'HARVY REX EGG DEALER', 'NV', 'LMT BUILDING FUENTES, STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(915, '749-668-361-000', 'HCE CORPORATION', 'VAT', 'ATRIA BRGY. SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(916, '410-385-584-000', 'HEALTHWAY QUALIMED HOSPITAL ILOILO (PANAY MEDICAL VENTURES, INC)', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(917, '607-084-508-000', 'HEALTHY KITCHEN CAFE OPC (MIKKA ELLA B. PERLAS)', 'VAT', 'TAFT SOUTH QUIRINO ABELO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(918, '004-715-242-046', 'HEALTHY OPTIONS CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(919, '004-513-283-000', 'HEAVEN\'S BARBEQUE STICK', 'VAT', 'REAL CONCEPT MEARKETING INC.', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(920, '000-312-799-006', 'HEVA MANAGEMENT & DECT. CORP', 'VAT', 'COR. GUANCO-RIZAL ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(921, '000-312-799-00004', 'HEVA MANAGEMENT & DEVELOPMENT CORP', 'VAT', 'GAISANO CAPITAL CITY-ILOILO LUNA STREER LUNA (LA PAZ), ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(922, '000-312-799-022', 'HEVA MANAGEMENT & DEVELOPMENT CORP.', 'VAT', 'BENIGNO AQUINO SAN RAFAEL', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(923, '000-312-799-014', 'HEVA MANAGEMENT & DEVELOPMENT CORP.', 'VAT', 'BENIGNO AQUINO SAN RAFAEL', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(924, '000-312-799-00014', 'HEVA MANAGEMENT & DEVELOPMENT CORP.', 'VAT', 'SIMEON AGUILAR, POB. ILAWOD, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(925, '000-312-799-00006', 'HEVA MANAGEMENT & DEVR. CORP.', 'VAT', 'GAISANO CAPITAL GUANCO COR. GUANCO - RIZAL ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(926, '102-266-315-002', 'HIGHWAY 21 HOTEL', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(927, '127-739-789-000', 'HK MOTOR PARTS SUPPLY', 'VAT', 'DOOR 7, STA., ISABEL, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(928, '001-966-859-044', 'HMR PHILIPPINES', 'VAT', 'INSIDE SM HYPERMARKET,COMMON CIVIL COR JALANDONI ST,JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(929, '008-377-070-004', 'HOKKAIDO RAMEN PHILIPPINES, INC.', 'VAT', 'SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(930, '611-110-308-000', 'HOLY CHILD PHARMACY', 'VAT', 'SAN JUAN MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(931, '925-796-879-000', 'HOME TOWN PRINTING PRESS', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(932, '236-524-890-002', 'HOME MART GROCERY EXPRESS', 'VAT', 'JALANDONI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(933, '771-725-811-000', 'HOMEMART GROCERY TRADING CORPORATION', 'NV', 'DGM BLDG. SOUTH AMBULONG, MANOC MANOC BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(934, '771-725-811-003', 'HOMEMART GROCERY TRADING CORPORATION', 'VAT', 'DGM BLDG. SOUTH AMBULONG, MANOC MANOC BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(935, '005-984-188-000', 'HOMEMASTER(ILOILO)SPECIALIST, INC.', 'VAT', 'BRGY. BOLILAO, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(936, '925-796-879-00000', 'HOMETOWN PRINTING PRESS', 'VAT', 'G.BLDG.M.V HECHANOVA,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(937, '161-990-151-000', 'HONEY WELL AUTO SUPPLY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(938, '009-044-447-001', 'HONEYTREE CORPORATION', 'VAT', 'TUAZON COR QUEZON AVE. QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(939, '269-049-671-001', 'HONGHONG XU', 'VAT', 'LEDESMA ST. BRGY. KAUSWAGAN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(940, '483-228-105-000', 'HOSTELERIA UNO INC', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(941, '268-806-593-000', 'HOTEL DEL RIO', 'VAT', 'M.H DEL PILAR ST., MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(942, '010-097-875-003', 'HOUSE OF MONKEYS', 'VAT', 'THE SHOPS AT ATRIA DON NONATO PISON AVE.', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(943, '447-755-280-002', 'HOWNOW CAFE', 'VAT', 'GROUND FLOOR AMIGO PLAZA MALL COR. IZNART-DELGADO DANAO 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(944, '762-237-591-000', 'HQ CURE CENTER CORP.', 'VAT', 'BRGY. SAN ISIDRO JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(945, '005-439-283-000', 'HUA LUN COMMERCIAL & COMPANY', 'VAT', 'JM BASA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(946, '009-023-856-000', 'HUMAN NATURE (BALAY NATURA,INC.)', 'VAT', '#21 G/F D JABEZ BLDG. GEN LUNA HIGHWAY ST', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(947, '184-808-940-00012', 'HYACINTH O. VITERBO - PROP.', 'VAT', 'LOWER GROUND FLOOR,NORTH POINT,SM CITY ILOILO,B. AQUINO AVENUE,BOLILAO,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(948, '208-035-563-0004', 'HYKAN\'S KAMAY KAINAN BAR AND GRILL INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(949, '208-035-563-004', 'HYMANS KAMAY KAINAN BAR AND GRILL INC.', 'VAT', 'SM DFOOD HALL SM CITY BENIGNO AQUINO AVE. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(950, '406-229-965-002', 'I BUY NOVELTY SHOP', 'NV', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(951, '406-229-965-000', 'I BUY NOVELTY SHOP', 'VAT', 'YULO DRIVE ST., BRGY. STA. FILOMENA, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(952, '759-995-190-00001', 'I HEART NY INC.', 'VAT', 'SM MALL OF ASIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(953, '759-995-190-001', 'I HEART NY INC.', 'VAT', 'SM MALL OF ASIA JW DIOKNO BLVD MALL OF ASIA COMPLEX BRGY. 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(954, '102-272-831-004', 'I.S MEDICINE CORNER', 'VAT', 'ICDC BLDG CITY MALL BRGY TAGBAK JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(955, '102-272-831-000', 'I.S. MEDICINE CORNER', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(956, '102-272-831-002', 'I.S. MEDICINE CORNER-ATRIUM BRANCH', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(957, '406-229-965-003', 'IBUY NOVELTY SHOP', 'NV', 'UNGKA 11 PAVIA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(958, '244-794-278-000', 'IDEALEE FOODS VENTURE INC.', 'VAT', 'TANDANG SORA AVE., QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(959, '010-027-397-000', 'IDEATECHS PACKAGING CORP.', 'VAT', 'ARANETA VILLAGE, POTRERO, DISTRICT 1, MALABON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(960, '009-560-864-001', 'IKANO PHILIPPINES, INC.', 'VAT', 'LOT NO. 7 MARINA WAY, MALL OF ASIA COMPLEX, PASAY CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(961, '008-351-400-024', 'IKITCHEN, INC.', 'VAT', 'BENIGNO AQUINO AVE. DIVERSION ROAD MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(962, '008-401-866-011', 'IKKORYU FUKUOKA RAMEN, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(963, '008-225-679-029', 'IL CONIGLIO BIANCO CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(964, '010-026-162-000', 'ILAWATBP', 'VAT', 'PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(965, '672-772-00001', 'ILO CANELLA FOOD CORPORATION', 'VAT', 'BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:02', '2026-07-13 03:05:02'),
(966, '009-439-327-00001', 'ILOFEST GRILL CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(967, '009-439-327-001', 'ILOFEST GRILL CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(968, '000-994-935-000', 'ILOILO 1 ELECTRIC COOPERATIVE, INC.', 'VAT', 'TIGBUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(969, '102-268-621-000', 'ILOILO 3 B AUTO PARTS', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(970, '939-423-432-000', 'ILOILO AUTO SUPPLY', 'VAT', 'COR. LEDESMA-VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(971, '916-479-538-000', 'ILOILO BALAY MANOKAN', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(972, '000-963-104', 'ILOILO BIG JS MART CORPORATION', 'VAT', '9 ALDUEGER STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(973, '000-963-104-000', 'ILOILO BIG J\'S MART CORPORATION', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(974, '000-963-104-003', 'ILOILO BIG J\'S MART CORPORATION', 'VAT', 'HOSKYN\'S COMPOUND,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(975, '004-230-525-000', 'ILOILO CAR CORPORATION', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(976, '102-264-627-000', 'ILOILO CENTRAL LUMBER', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(977, '771-479-041-000', 'ILOILO CENTRALLUMBER INV', 'VAT', '27 M. H. DEL PILAR ST., BRGY. TAAL MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(978, '492-815-416-000', 'ILOILO CG&A DISTRIBUTION INC', 'VAT', 'PUROK PITAO, BRGY. MAYA BALASAN, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(979, '000-249-579-000', 'ILOILO CITY HARDWARE, INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(980, '915-982-146-000', 'ILOILO COFFEE TRADING', 'NV', 'METRO MIDLAND ENT. BLDG., MCARTHUR AVENUE, TAGBAK,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(981, '102-275-010-000', 'ILOILO COLUMBIA HARDWARE', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(982, '009-708-958-001', 'ILOILO CUISINE INC.', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(983, '102-264-830-000', 'ILOILO DISCOUNT HOUSE', 'VAT', 'QUEZON ST. I.C.', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(984, '254-971-278-002', 'ILOILO DIVISORIA NOUVELTY SHOP', 'VAT', 'EL 98 ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(985, '254-971-278-000', 'ILOILO DIVISORIA NOUVELTY SHOP', 'VAT', 'RIZAL ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(986, '254-971-278-005', 'ILOILO DIVISORIA NOVELTY SHOP', 'VAT', 'ARROYO STREET, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(987, '000-994-854-000', 'ILOILO DOCTORS HOSPITAL INC', 'VAT', 'WEST AVENUE, MOLO TAAL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(988, '102-267-531-000', 'ILOILO EVERKON TRADE', 'NV', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(989, '000-803-752-005', 'ILOILO FISH PORT COMPLEX', 'VAT', 'TANZA BAYBAY,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(990, '623-789-688-000', 'ILOILO FRESH MARKET CORPORATION', 'VAT', 'QHP BUILDING JIBAO-AN PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(991, '102-274-767-003', 'ILOILO GASUL CENTER', 'VAT', 'BALANTANG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(992, '005-917-995-020', 'ILOILO GRACE PHARMACY', 'VAT', '28 RIZAL ST., LAGUDA LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(993, '005-917-995-009', 'ILOILO GRACE PHARMACY', 'VAT', 'ONATE ST.BRGY.ONATE DE LEON MANDURRAIO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(994, '005-917-995-023', 'ILOILO GRACE PHARMACY', 'VAT', 'ONATE ST.BRGY.ONATE DE LEON MANDURRAIO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(995, '005-917-995-001', 'ILOILO GRACE PHARMACY (SAMUEL DRUG CORPORATION)', 'VAT', 'ROBINSONS PLACE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(996, '005-917-995-026', 'ILOILO GRACE PHARMACY (SAMUEL DRUG CORPORATION)', 'VAT', 'ONATE ST.BRGY.ONATE DE LEON MANDURRAIO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(997, '005-981-482-002', 'ILOILO GRAND HOTEL CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(998, '000-465-623-004', 'ILOILO GRAND PALACE CUISINE', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(999, '005-981-482-001', 'ILOILO GRAND PALACE CUISINE', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1000, '000-249-870-000', 'ILOILO INTEGRATED ARRASTRE SERVICES CORP', 'VAT', 'JM BASA STREETS, CITY PROPER, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1001, '136-965-006-000', 'ILOILO IZEEM COMMERCIAL', 'VAT', 'MABINI STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1002, '004-863-429-001', 'ILOILO JAR CORPORATION', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1003, '131-395-845-000', 'ILOILO JIMMY\'S HARDWARE', 'VAT', 'DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1004, '005-981-360-000', 'ILOILO LIM\'S MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1005, '005-818-579-000', 'ILOILO MARISON INC.,', 'VAT', 'QUEZON ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1006, '004-863-604-000', 'ILOILO MIDTOWN HOTEL CORP.', 'VAT', 'YULO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1007, '261-050-407-001', 'ILOILO MILLENIUM REALTY AND DEV.CORP', 'VAT', 'BRGY. SAN RAFAEL MANDURRIAO , ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1008, '005-982-483-000', 'ILOILO NATIONWIDE HARDWARE, INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1009, '923-412-276-000', 'ILOILO NET AND TWINE MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1010, '000-250-783-000', 'ILOILO NEW MABUHAY HARDWARE & GLASSWARE CO.,INC', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1011, '000-249-805-000', 'ILOILO PAINT AND INDUSTRIAL SALES CORPORATION', 'VAT', 'COR. QUEZON GEN. LUNA STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1012, '005-820-809-000', 'ILOILO PECHO-PAK CHICKEN HOUSE COMPANY', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1013, '432-582-779-000', 'ILOILO PLATINUM SALES COMPANY', 'VAT', 'SAN PEDRO STREET, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1014, '439-725-339-000', 'ILOILO POPSODA CORP.', 'VAT', 'ROSMAN BUILDING BRGY.CALUBIHAN DIVERSION ROAD,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1015, '602-029-637-00000', 'ILOILO PRIME FOOD VENTURES CORP.', 'NV', '96 J DE LEON ST.,BRGY.KAUSWAGAN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1016, '484-236-428-000', 'ILOILO PRINTING PUBLISHING AND SERVICES INC.', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1017, '762-982-734-000', 'ILOILO PROSPEROUS FORTUNE FOODS CORP.', 'VAT', 'RED HOUSE TAIWAN SHABU-SHABU UNIT83 FESTIVE WALK ILOILO BUS PARK AIRPORT RD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1018, '764-982-734-000', 'ILOILO PROSPEROUS FORTUNE FOODS CORPORATION', 'VAT', 'FESTIVE WALK ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1019, '005-439-514-002', 'ILOILO SBF SPORTSCENTER', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1020, '427-900-700-000', 'ILOILO SHANGHAI BZR. INC.', 'VAT', '#78 IZNART STREET I.C.', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1021, '001-330-045-001', 'ILOILO SOCIETY COMMERCIAL', 'VAT', '156 RIZAL-ORTIZ ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1022, '001-330-450-000', 'ILOILO SOCIETY COMMERCIAL INC.', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1023, '001-330-450-001', 'ILOILO SOCIETY COMMERCIAL INC.', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1024, '191-997-537-000', 'ILOILO SOLID MARKETING', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1025, '000-844-307-000', 'ILOILO SUPERMARKET, INC.', 'VAT', 'ATRIUM MALL, GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1026, '003-844-307-000', 'ILOILO SUPERMART INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1027, '003-844-307-004', 'ILOILO SUPERMART INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1028, '106-722-253-000', 'ILOILO SURPLUS&AUTO SUPPLY', 'VAT', 'VALERIA EXT., ST., BRGY. NONOY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1029, '126-152-191-000', 'ILOILO TICO TRADING', 'VAT', 'LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1030, '177-210-676-000', 'ILOILO YCA TRADING', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1031, '177-209-703-000', 'ILONGGOS PAINT TRADE CENTER', 'VAT', 'RIZAL ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1032, '458-439-711-000', 'ILOOIL TABUC SUBA', 'VAT', 'MC ARTHUR DRIVE TABUC SUBA JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1033, '000-249-888-090', 'IMPERIAL APPLIANCE GALLERIA-DELGADO', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1034, '000-249-888-00028', 'IMPERIAL APPLIANCE PLAZA MEGA SHOWROOM', 'VAT', 'H.MONTINOLA COR. MUELLE LONEY ST., PRES.ROXAS, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1035, '491-562-668-0002', 'IMR FOOD VENTURES, INC', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1036, '491-562-668-0001', 'IMR FOOD VENTURES, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1037, '418-391-196-000', 'IN QIAN CAP CELLPHONE & ACCESSORIES', 'NV', '2ND FLR. THE ATRIUM, GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1038, 'ITR', 'INCOME TAX RETURN', 'N/A', 'BIR', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1039, '291-997-005-000', 'INDUSTRIAL SUPPLY AND SERVICES', 'VAT', 'BRGY. MAGSAYSAY, LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1040, '008-659-510-007', 'INNOVATIONS CORP.', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1041, '000-360-916-159', 'INNOVE COMMU NICATIONS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1042, '000-360-916-160', 'INNOVE COMMU NICATIONS INC.', 'VAT', 'GF, SM DELAGADO COR. VALERIA & DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1043, '000-360-916-00159', 'INNOVE COMMUNICATIONS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1044, '000-360-916-054', 'INNOVE COMMUNICATIONS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1045, '000-360-916-000', 'INNOVE COMMUNICATIONS INC.', 'VAT', 'GF, SM DELAGADO COR. VALERIA & DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1046, '007-920-095-178', 'INSPIRA PRIME INTERNATIONAL CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1047, '238-777-814-013', 'INTERNATIONAL SPECIALTY CONCEPTS, INC.', 'VAT', 'MALL OF ASIA, CORAL WAY COR JW DIOKNO BLVD, BRGY 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1048, '008-422-798-005', 'INTERNATIONAL SPECIALTY RTAILERS INC.', 'VAT', 'JW DIOKNO BLD BRGY. 76 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1049, '000-404-818-000', 'INTERNATIONAL TOYWORLD INC.', 'VAT', 'SM CITY BENIGNO AQUINO AVE., MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1050, '000-404-818-008', 'INTERNATIONAL TOYWORLD, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1051, '004-215-446-002', 'INTERWORLD FRIENDSHIPS, INC', 'VAT', 'MANGGAYAD BALABAG, BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1052, '007-934-259-001', 'INT\'L DINING CONCEPTS, INC.', 'VAT', 'UNIT A2 D MALL D BORACAY, BALABAG BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1053, '903-618-222-004', 'IPAPERMINTS SIGNS AND DIGITAL PRINTS', 'VAT', 'ROBINSONS PLACE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1054, '121-486-278-00000', 'IRENE S. ENRIQUEZ', 'VAT', 'POBLACON, GUIMBAL', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1055, '126-188-965-000', 'IRMAN A. ITA-AS', 'VAT', 'GUZMAN-JESENA STREET,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1056, '908-797-272-011', 'IS AREVALO PLAZA SUPERMARKET', 'VAT', 'QUEZON STREET, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1057, '907-797-272-011', 'IS AREVALO PLAZA SUPERMART', 'VAT', 'QUEZON ST, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1058, '102-272-831-005', 'IS MEDICINE CORNER', 'VAT', 'ICDC BLDG CITY MALL BRGY UNGKA 11 PAVIA', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1059, '102-272-831-001', 'IS MEDICINE CORNER- MOLO', 'VAT', 'LOCSIN-AVANCEÑA STS., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1060, '102-272-831-003', 'IS MEDICINE CORNER- VALERIA', 'VAT', 'VALERIA-DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1061, '296-475-668-002', 'IS TAGBAK TERMINAL SUPERMART', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1062, '296-245-668-002', 'IS TAGBAK TERMINAL SUPERMART', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1063, '296-475-658-000', 'IS TAGBAK TERMINAL SUPERMART', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1064, '296-245-668-000', 'IS TAGBAK TERMINAL SUPERMART', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1065, '296-475-668-000', 'IS TAGBAK TERMINAL SUPERMART', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1066, '635-608-137-000', 'IS TAGBAK TERMINAL SUPERMART INC.', 'VAT', 'ICDC BLDG., TAGBAK JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1067, '705-126-414-000', 'IS UNGKA II TERMINAL SUPERMART, INC.', 'VAT', 'UNGKA II PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1068, '465-491-001', 'ISHINE HOUSEHOLDWARES & PLASTICWARES', 'VAT', 'LIBO-ON ST., GUIMBAL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1069, '906-498-180-000', 'ISLAND CHICKEN INASAL', 'VAT', 'UNIT 112 PHASE 4 D\'MALL D\' BORACAY, BALABAG BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1070, '460-527-957-000', 'ISLAND HOUSEWARE PRODUCTS MERCHANDISING', 'VAT', 'PUBLIC MARKET BUGASONG ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1071, '008-093-108-000', 'ISPACE FURNISHING INC.', 'VAT', 'SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1072, '007-432-235-000', 'ISTILA COMPANHIA INC.', 'VAT', '157 MILAGROS ST., ERMITANO SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1073, '480-588-028-000', 'ISTORYA FOREST GARDEN INCORPORATED', 'VAT', 'POBLACION ILAYA, PANAY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1074, '126-188-965-005', 'ITA-AS IRMAN ANINO', 'VAT', 'DALAN GUZMAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1075, '935-313-391-000', 'IVAN\'S ENT.', 'NV', 'JALANDONI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1076, '238-657-525-003', 'J AND C PIZZA HAUS', 'VAT', 'E.LOPEZ ST.JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1077, '748-560-030-000', 'J AND C VENTURES,OPC', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1078, '440-093-050-000', 'J AND M AGRI MARKETING', 'NV', 'J.DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1079, '196-224-067-006', 'J AND S SABOR ILONGGO', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1080, '707-919-124-000', 'J&A HOUSEHOLD PRODUCTS TRADING', 'VAT', 'J.M BASA ST. YULO-ARROYO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1081, '238-657-525-000', 'J&C PIZZA HAUS', 'VAT', 'E.LOPEZ ST.JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1082, '935-732-610-004', 'J&S SABOR ILONGGO PASALUBONG CENTER', 'NV', 'FESTIVE WALK HALL Q. ABETO ST., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1083, '923-412-945-000', 'J. HOME BUILDERS SUPPLY', 'VAT', 'J. DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1084, '008-043-737-00047', 'J.CO DONUTS AND COFFEE (CONTEMPORAIN FOODS INC.)', 'VAT', 'SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1085, '008-043-737-00034', 'J.CO DONUTS AND COFFEE (CONTEMPORAIN FOODS INC.)', 'VAT', 'ROBINSONS PLACE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1086, '918-471-386-001', 'J.P. ALABASTRO FOOD SERVICES', 'VAT', 'DOMESTIC AIRPORT PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1087, '173-286-487-000', 'JACKIES FOOD TREATS', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1088, '005-278-351-000', 'JACSON\'S METALCRAFT INDUSTRIES, INC.', 'VAT', 'BALTAZAR SUBD., BRGY. BALDOZA, LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1089, '009-821-546-001', 'JAD FRIEND SULIT FOOD CORPORATION', 'VAT', '135 WEST AVE BRGY BUNGAD QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1090, '106-910-539-000', 'JAG AGRI-MACHINERIES', 'VAT', 'J&B BLDG. 11 MABINI STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1091, '149-107-904-000', 'JAGIB STORE', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1092, '008-448-927-003', 'JAGJIT FRIENDS TRADING, INC.', 'NV', '#10 JALANDONI- COMMISSION STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1093, '271-395-425-000', 'JAMES MEAT STORE', 'NV', 'ILOILO TERMINAL MARKET, FLORES 5000 ILOILO CITY (CAPITAL) ILOILO, PHIL.', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1094, '136-755-387-000', 'JAN BEE MARKETING', 'VAT', 'BRGY. TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1095, '234-634-259-127', 'JAPAN HOME CENTRE', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1096, '763-664-658-00004', 'JAP-JAP JO DINER, INC', 'NV', 'BRGY.BANUYAO, LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1097, '004-227-254-00003', 'JARA FOODS INC.,', 'VAT', 'FOOD HALL NO.4 SM FOOD HALL BRGY.BOLILAO MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1098, '006-227-254-003', 'JARA FOODS, INC.', 'VAT', 'FOOD HALL #2 ANNEX EXT SM CITY, BRGY. BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1099, '008-818-550-000', 'JARDELEZA SEA GARDEN RESORTS, INC', 'VAT', 'BRGY. CAMANGAY, LEGANES, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1100, '147-704-115-001', 'JARDIN RESTAURANT', 'VAT', 'BOARDWALK DON MARIANO PISON AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1101, '005-983-468-000', 'JARO ARCHDIOCESAN SOCIAL ACTION CENTER, INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1102, '005-983-468-00003', 'JARO ARCHDIOCESAN SOCIAL ACTION CENTER, INC.', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1103, '183-777-606-000', 'JARO BALAY MANOKAN', 'VAT', '292-PHIL-AM COMPOUND, COMMISSION CIVIL ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1104, '274-820-463-000', 'JARO GOLDEN HOME MARKETING', 'VAT', 'BRGY. TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1105, '000-250-858-0000', 'JARO GRAND PETRON SERVICE STATION, INC.', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1106, '000-250-858-000', 'JARO GRAND PETRON SERVICE STATION, INC.', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1107, '102-264-144-000', 'JARO SUPERMART', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1108, '168-255-245-000', 'JASBERT ENTERPRISES', 'VAT', 'BRGY. PALI BENEDICTO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1109, '167-085-823-000', 'JASPER\'S TAPSILOG ATBP. RESTO', 'VAT', 'BALABAG, BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1110, '275-765-944-000', 'JAT DECALS ARTS & ACCESSORIES', 'NV', 'DIVERSION ROAD BRGY. SAMBAG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1111, '257-765-944-000', 'JAT DECALS ARTS AND ACCESSORIES', 'NV', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1112, '160-080-620-000', 'JAXQUELINE Y. EALA PROP', 'VAT', 'GORRICETA AVE.,BALABAG,PAVIA 5001,PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1113, '424-434-111-00000', 'JAYSON L. ZALDARRIAGA', 'VAT', 'BUHANG TAFT NORTH, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1114, '272-292-058-00000', 'JAYVEE LORENZO G. FALCIS', 'VAT', 'COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1115, '010-352-134-013', 'JBPX FOODS INC.', 'VAT', 'PANDA EXPRESS GREENHILLS B5 PARKING G/F PROMENADE SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1116, '921-036-413-000', 'JC TELEMART', 'NV', 'GEN. LUNA ST. 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1117, '921-036-413-00000', 'JC TELEMART', 'NV', 'GEN. LUNA ST. 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1118, '231-175-211-004', 'JC WELLNESS CENTER', 'VAT', 'PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1119, '776-493-132-000', 'JD BAKERY CAFÉ', 'VAT', 'JV JOCSON ST, SAN JOSE AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1120, '412-738-270-000', 'JD BAKERY CAFE (DIEZ NINJA CO.)', 'VAT', 'ROBINSONS PLACE, LEDESMA STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1121, '005-439-354-001', 'JD BAKERY CAFE (FOODS R\'US, INC.)', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1122, '005-439-354-000', 'JD BAKERY CAFÉ 2-FOOD R\'US INC.', 'VAT', 'WEST AVE. MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1123, '429-831-238-000', 'JDL NACHOS FACTORY', 'NV', 'BLK. 26 LOT 21 JOSEFINA ST. ERORECO MANDALAGAN BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1124, '914-785-167-000', 'JEANETTE RENA DY.', 'VAT', 'G/F GAISANO CITY LUNA ST, LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1125, '006-326-450-004', 'JEARY TOP METAL PRODUCTS CORPORATION', 'VAT', 'COR. RIZAL QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1126, '422-181-244-006', 'JEDAH NABARTE ABARTE', 'VAT', 'NUNEZ ST., MAMBUSAO, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1127, '921-840-339-003', 'JEJU RAMYUN FOOD PARK', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1128, '229-485-027-007', 'JEMN COPIERONLINE COPIER STATION AND SERVICES', 'VAT', 'MH DEL PILAR ST, MOLO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1129, '603-226-721-000', 'JENNY ESPANOL PENASALES', 'NV', 'EL 98 ST. TAYTAY ZONE II JARO ILOILO CITY CAPITAL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1130, '286-037-812-000', 'JENNY TALABAHAN', 'NV', 'BOLILAO MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1131, '436-025-896-002', 'JERI DAVE COPY CENTER', 'NV', 'MISSION ROAD, BRGY. MONTINOLA, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1132, '102-273-306-000', 'JERRY H. CHUA', 'VAT', '116-A ST.,ELIZABETH CENTRE, VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1133, '210-148-664-00093', 'JETTI', 'VAT', 'MC ARTHUR DRIVE TABUC SUBA JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1134, '103-759-904-007', 'JEWELRY CENTER', 'VAT', 'SM CITY ILOILO, MENIGNO AQUINO AVE., MANDURRIAO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1135, '298-905-978-002', 'JGM DISTRIBUTOR, INC', 'NV', 'BRGY. SAN PEDRO MOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1136, '298-905-978-000', 'JGM DISTRIBUTOR, INC.', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1137, '298-905-978-003', 'JGM DISTRIBUTOR, INC.', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1138, '676-111-587-000', 'JGT FOOD VENTURES, INC.', 'VAT', 'GUSTILO ST. POBLACION LEGANES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1139, '010-268-633-000', 'JIGA MAGS AND TIRES', 'VAT', 'VILLALOBOS ST.,COR.DIVERAION BRGY.CUARTERO JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1140, '142-466-149-000', 'JIM P. VELEZ', 'VAT', 'RED SQUARE BLDG.,SMALLVILLE COMPLEX', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1141, '102-269-702-000', 'JIMMY C. GAW TE - PROP', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1142, '215-610-972-016', 'JING OÑAS SURPLUS IN THE CITY-BRANCH', 'VAT', 'GROUND FLOOR MARYMART MALL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1143, '745-965-316-002', 'JK FOOD AND RESTAURANT OPC', 'VAT', '2ND FLOOR UNIT FESTIVE WALK MALL ILOILO BUSINESS PARK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1144, '495-746-377-000', 'JKF HOUSEWARE TRADING', 'VAT', 'E. LOPEZ ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1145, '168-263-146-000', 'JMB PETRON GASOLINE STATION', 'VAT', 'JALAUD NORTE, ZARRAGA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1146, '107-168-357-005', 'JML CONVENIENCE STORE', 'VAT', 'SIMEON AGUILAR ST., POB.ILAWOD, PASSI ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(1147, '904-489-109-003', 'JMU M ARKETING', 'VAT', 'BRGY. LAPUZ NORTE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1148, '010-784-061-001', 'JMU MARKETING INCORPORATED PACKAGING COMPANY', 'VAT', 'GT AGRO WAREHOUSE GUZMAN STREET, GUZMAN-JESENA MANDURRIAO 5000 CITY OF ILOILO PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1149, '908-8074-279', 'JN GOTERA CONCRETE PRODUCT', 'VAT', 'AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1150, '908-807-279-000', 'JN GOTERA CONCRETE PRODUCT', 'VAT', 'J.V. JOCSON ST., AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1151, '102-265-442-000', 'JOCSON CONSTRUCTION SUPPLY', 'VAT', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1152, '422-589-842-000', 'JOHN COSMETICS SHOP (CUA LEO ALEJOS)', 'VAT', 'ALDEGUER ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1153, '168-256-594-000', 'JOLIN CONSTRUCTION SUPPLY', 'VAT', 'SIMON LEDESMA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1154, '000-388-771-173', 'JOLLIBEE FOODS CORP', 'VAT', 'QUEZON BLD. STA TERESITA MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1155, '007-660-207-0001', 'JOLLIBEE LANDMARK FESTIVAL (PROGRESSO FASTFOOD INC.)', 'VAT', 'GF FOOD HALL LANDMARK FILINVEST ALABANG', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1156, '002-727-551-0000', 'JOLLIBEE SM JARO ILOILO', 'VAT', 'SM BLDG. COR.EL 98 LIBERTAD ST.JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1157, '001-568-100-000', 'JOLLIBEST FASTFOOD CORP.', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1158, '133-699-185-001', 'JONAH\'S FRUIT SHAKE AND SNACK BAR (MILBA P. GADORES)', 'VAT', 'BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1159, '229-348-010-000', 'JOR-DEL FARMACIA', 'VAT', 'PUBLIC MARKET BAROTAC VIEJO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1160, '262-389-774-000', 'JORQUE PHARMACY', 'VAT', 'POBLACION BANATE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1161, '916-479-538-003', 'JO\'S CHICKEN INATO', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1162, '260-138-446-008', 'JOSE NATHAN F. GUSTILO', 'VAT', '3RD FLOOR CYBERZONE, SM CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1163, '183-775-010-000', 'JOSELENE G. MARQUEZ', 'VAT', 'LOT 4 BRGY. FATIMA JARO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1164, '731-682-273-000', 'JOSHAINE CORPORATION', 'VAT', 'JACILDO BLDG.,COR.RODRIGUEZ-TAFT ST., SANTA BARBARA', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1165, '771-669-696-000', 'JOSMEF PHARMA INC.,', 'VAT', 'JAVELLANA E.LOPEZ ST.,JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1166, '443-704-813-000', 'JOYRICH VEGETABLES DEALER', 'NV', 'ILOILO TERMINAL MARKET, DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1167, '644-450-378-001', 'JPP HOUSEHOLD PRODUCTS TRADING', 'NV', 'L61 TERESA MAGBANUA ST SAN JOSE WARD POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1168, '008-248-400-017', 'JPV EMISSION TESTING CORP.', 'VAT', 'BRGY. QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1169, '010-253-211-000', 'JRK GOODS TRADING INC', 'VAT', 'STA MESA HEIGHTS STA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1170, '939-433-650-000', 'JSU AUTO SUPPLY', 'VAT', 'SIMON LEDESMA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1171, '005-519-158-007', 'JT INTERNATIONAL (PHILIPPINES) INC.', 'VAT', 'BRGY LOBOC LAPUZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1172, '468-116-332-003', 'JTC GOLDEN FOOD VENTURES CORP.', 'VAT', 'MARYMART CENTER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1173, '468-116-332-00004', 'JTC GOLDEN FOOD VENTURES CORP.', 'VAT', 'SM CITY ILOILO BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1174, '468-116-332-001', 'JTC GOLDEN FOOD VENTURES CORPORATION', 'VAT', 'COR. LOCSIN ST. & SAN PEDRO ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1175, '468-166-332-001', 'JTC GOLDEN FOOD VENTURES CORPORATION', 'VAT', 'CORNER LOCSIN & SAN PEDRO ST. MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1176, '934-617-336-001', 'JUAN HONG CARINA CHRISTINE CHU', 'VAT', 'COR. DE LEON-QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1177, '103-959-530-001', 'JUANTONG, JULIAN L.', 'VAT', 'HORMILLOSA BLDG IZNART ST, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1178, '007-357-430-002', 'JUDPHILAN FOODS CORPORATION', 'NV', 'ILOILO FISH PORT COMPLEX, TANZA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1179, '122-452-582-00000', 'JUDSON FUNCION GEQUILLANA', 'VAT', 'PLAZUELA DE ILOILO DIVERSION ROAD, MANDURRIAO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1180, '009-663-596-035', 'JUMBOLITO\'S FOOD PRODUCTS INC.', 'VAT', 'LUNA STREET, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1181, '311-568-447-000', 'JVRAC FOOD SERVICE', 'NV', 'VERBANA ST., SAN ISIDRO, CAINTA, RIZAL', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1182, '153-876-529-003', 'JVS AUDIO SYSTEM', 'VAT', 'BRGY. SAN AGUSTIN, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1183, '010-074-168-000', 'JWK FOOD CO.', 'VAT', 'FESTIVE WALK PROMENADE MEGAWORLD BLVD BUHANG TAFT NORTH MANDURRIAO, ILOLO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1184, '415-194-385-000', 'K SPICE CAFÉ', 'VAT', 'BUHANG TAFT NORTH, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1185, '009-244-078-000', 'K1 HEALTH SCIENCE CORP.', 'VAT', '3/F BURGOS PARK BLDG FORBESTOWN RD, BONIFACIO GLOBAL CITY FORT BONIFACIO, TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1186, '009-853-232-000', 'KAANYAG CANTEEN INC.', 'VAT', 'BRGY.TAGBAK JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1187, '200-251-128-189', 'KALIN INCORPORATED', 'VAT', '2ND FLR, GAISANO CITY LUNA ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1188, '200-251-128-422', 'KALIN INCORPORATED', 'VAT', '2ND FLR, GAISANO CITY LUNA ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1189, '200-251-128-436', 'KALIN INCORPORATED', 'VAT', '2ND FLR, GAISANO CITY LUNA ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1190, '200-251-128-188', 'KALIN INCORPORATED', 'VAT', 'MARYMART CENTER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1191, '208-035-963-0004', 'KAMAY KINAN', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1192, '444-633-475-000', 'KANENG PORK AND BEEF STORE', 'NV', 'ILOILO TERMINAL MARKET, RIZAL ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1193, '009-279-559-001', 'KANTO FREESTYLE BREAKFAST', 'VAT', 'GF AVIDA TOWER CITYFLEX FORT BONIFACIO BGC TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1194, '428-252-707-001', 'KAP ISING PANCIT MOLO', 'NV', 'BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1195, '654-241-012-000', 'KAP ISING\'S PANCIT', 'VAT', 'SM CITY NORTH WING BENIGNO AQUINO AVE. BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1196, '246-969-491-035', 'KAREILA MGNT. CORP.', 'VAT', 'DON NONATO PISON AVE., BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1197, '246-969-491-00035', 'KAREILA MGNT. CORP.', 'VAT', 'DON NONATO PISON AVE., BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1198, '126-180-168-001', 'KASING TEXTILE', 'NV', '4-1-3 J.M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1199, '762-601-583-000', 'KATE & 4 J SARI-SARI STORE', 'NV', 'JARO BIG MARKET,EL 98 ST., BRGY. DESAMPARADOS, JARO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:03', '2026-07-13 03:05:03'),
(1200, '942-461-021-001', 'KATSILONGGA ARROZ CALDO', 'NV', 'NORTH SAN JOSE MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1201, '942-461-021-005', 'KATSILONGGA ARROZ CALDO', 'NV', 'NORTH SAN JOSE MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1202, '942-461-021-000', 'KATSILONGGA ARROZ CALDO', 'VAT', 'NO. 28 MISSION ROAD JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1203, '288-613-669-001', 'KAYE ANN G. ACOSA', 'NV', 'FARMERS BAZAAR PLAZUELA DOS, SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1204, '009-727-748-000', 'KCA PRIME FOOD VENTURES INC', 'VAT', 'QUEZON CAVE. BRGY STA TERESITA Q.C', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1205, '009-737-748-000', 'KCA PRIME FOOD VENTURES INC', 'VAT', 'QUEZON AVE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1206, '120-838-081-000', 'KENIKA ENTERPRISES', 'VAT', 'PAMPANGA', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1207, '043-302-178-002', 'KENLEX MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1208, '643-302-173-002', 'KENLEX MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1209, '007-109-034-023', 'KENNY ROGERS ROASTERS', 'VAT', 'SM CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1210, '002-925-850-178', 'KENRICH INTERNATIONAL DISTRIBUTOR CORP.', 'VAT', 'SPACE NO.EX211 2ND FLOOR SM CITY ILOILO BENIGNO AQUINO AVENUE MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1211, '008-096-136-000', 'KERDAR E NIK INC.', 'VAT', 'SPACE 276 L1 ROBINSONS PLACE MLA ALFRESCO MIDTOWN WING M ADRIATICO COR PEDRO GIL ERMITA MLA', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1212, '352-371-902-000', 'KEVIN LANCE P. GAW TE', 'VAT', '14 J DE LEON ST.,MAGSAYSAY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1213, '706-047-487-000', 'KF HOUSEWARE PRODUCTS ENTERPRISE', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1214, '779-456-830-000', 'KFC ANTIQUE', 'VAT', 'GR.FLOOR ST.NICHOLAS COMMERCIAL SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1215, '283-975-531-002', 'KH GLASS&ALUMINUM SERVICES', 'NV', 'UNGKA II PAVIA,ILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1216, '466-468-474-005', 'KH HOY PANGA DEALER CORPORATION', 'VAT', 'BALABAG BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1217, '274-659-809-000', 'KIANE\'S GASOLINE STATION', 'VAT', 'GUZMAN ST.,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1218, '274-659-809-001', 'KIANE\'S GASOLINE STATION', 'VAT', 'BRGY. LOPEZ JAENA NORTE, LA PAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1219, '945-755-013-000', 'KIDDO\'S DROP PURIFIED WATER REFILLING STATION', 'NV', 'BRGY.BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1220, '942-808-079-000', 'KIMBERLY DAWN A. NABALAN - PROP.', 'VAT', 'DALIPE,SAN JOSE,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1221, '009-515-586-002', 'KIMSTAURANT INC.', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1222, '267-342-243-000', 'KING ICE INCORPORATED', 'VAT', 'BRGY. CAGBANG, OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1223, '449-113-946-000', 'KING ZONE TOYS TRADING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1224, '102-285-237-0000', 'KING/S DELIGHT', 'VAT', 'IZNART ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1225, '102-285-237-000', 'KINGS DELIGHT', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1226, '736-448-579-000', 'KJ HOUSEWARE PRODUCTS MKTG.', 'VAT', 'ONATE STREET,BRGY. ONATE DE LEON , MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1227, '008-188-059-031', 'KNOXPORT INC.', 'VAT', '11TH AVE. BONIFACIO GLOBAL CITY, TAGUIG', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1228, '484-136-787-002', 'KOGI &^VEGI FOODS CORPORATION', 'VAT', '2ND FLOOR COURT THE ATRIUM MALL GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1229, '484-136-787-000', 'KOGI AND VEGI FOODS CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1230, '484-136-787-001', 'KOGI AND VEGI FOODS CORPORATION', 'VAT', '2ND FLOOR COURT THE ATRIUM MALL GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1231, '434-351-367-023', 'KOGI- Q', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1232, '004-660-226-011', 'KOLIN PHILIPPINES INTERNATIONAL INC', 'VAT', 'DOOR D\' APPLIANCE ARCADE , BRGY SOUTH FUNDIDOR MOLO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1233, '308-037-918-00000', 'KOLLABO KITCHEN KOREAN, CHINESE, JAPANESE CUISINE', 'VAT', 'SM CITY FOOD COURT B. AQUINO AVENUE MANDURRIO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1234, '273-249-788-000', 'K-ONE MARKETING (JOHNNY WONG)', 'VAT', 'PLARIDEL STREET, ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1235, '459-653-457-00004', 'KONGKEE RESTAURANT (MAKANI, JAY MARK LEGASPI)', 'VAT', 'GUANGCO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1236, '608-596-566-000', 'KOOMI PHILIPPINES SM ILOILO INC.,', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1237, '009-565-117-00000', 'KOREA DAILY TRADING', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1238, '009-565-117-000', 'KOREADAILY TRADING INTERNATIONAL CORP', 'VAT', 'MEGAWORLD BOULEVARD, MANDURRIAO, ILOILO CIY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1239, '748-805-288-000', 'KP PACKAGING AND FOOD MIX,OPC', 'VAT', 'DOOR 8 HVG WAREHOUSE DUNGON A, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1240, '753-226-737-000', 'KRANE TRANSPORT SERVICE INC.', 'VAT', 'BLK 17 LOT 5 LEDESCO VILLAGE LAPAZ', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1241, '474-321-476-000', 'KRISANDER RETAIL AND MGT. 2 INC.', 'VAT', 'TIGBUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1242, '492-890-853-000', 'KRISANDER RETAIL AND MGT. 3 INC.', 'VAT', 'COMM. CIVIL COR. JALANDONI STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1243, '263-454-925-00004', 'KRISTINA JOY P. DUAT - PROP.', 'NV', '3RD FLOOR CYBERZONE SM CITY,BOLILAO,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1244, '010-174-322-001', 'KSC INCORPORATED', 'VAT', 'UPPER GROUND FLOOR EX 122-123, BENIGNO AQUINO AVE., PALE BENEDICTO RIZAL, MANDURRAIO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1245, '008-586-211-002', 'K-STAR CUISINE INC.', 'VAT', 'UP TOWN CENTER AYALA MALL BRGY. PANSOL KATIPUNAN AVENUE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1246, '734-432-277-001', 'KTE FOOD CORPORATION', 'VAT', 'SM CITY SOUTHPOINT MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1247, '440-897-787-001', 'KUY\'S LECHON RESTAURANT', 'VAT', 'JYSQUARE ANNEX GORORDO AVENUE CORNER SANSON ROAD', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1248, '440-645-560-000', 'L AND N REAL COFFEE CORP.', 'VAT', 'SEA WORLD, BALABAG, BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1249, '434-276-748-002', 'L&M FOOTWEAR STORE', 'VAT', 'ALDEGUER ST. MAGSAYSAY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1250, '934-585-663-000', 'LABLEE\'S PARTY NEEDS', 'VAT', 'LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1251, '000-146-306-00810', 'LACOSTE NUSTAR (STORES SPECIALISTS, INC.)', 'VAT', 'KAWIT ISLAND SOUTH ROAD PROPERTIES', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1252, '005-820-581-000', 'LAD MARKETING CO.', 'VAT', 'IZNART ST. DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1253, '000-249-185-005', 'LADY PHARMACY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1254, '000-249-105-000', 'LADY PHARMACY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1255, '009-149-884-000', 'LANDMARK DEPARTMENT STORE', 'VAT', 'ALABANG, MUNTINLUPA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1256, '258-216-276-0001', 'LAPAZ BAKESHOPINC', 'VAT', 'LAPAZILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1257, '102-264-209-000', 'LAPAZ PETRON SERVICE STATION', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1258, '948-085-629-000', 'LASORTECH ENTERPRISES', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1259, '000-782-140-000958', 'LBC EXPRESS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1260, '000-782-140-00942', 'LBC EXPRESS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1261, '000-782-140-00957', 'LBC EXPRESS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1262, '000-782-140-01335', 'LBC EXPRESS, INC.', 'VAT', 'E. LOPEZ ST., SAN VICENTE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1263, '000-782-140-00943', 'LBC EXPRESS, INC.- NELLY\'S GARDEN', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1264, '000-782-140-958', 'LBC EXPRESS,INC', 'VAT', 'SM CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1265, '000-782-140-951', 'LBS EXPRESS INC', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1266, '000-782-140-952', 'LBS EXPRESS INC', 'VAT', 'E.LOPEZ SAN VICENTE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1267, '416-473-576-000', 'LC AND SB STORE', 'NV', '25-A DIVINAGRACIA STREET,LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1268, '606-353-330-000', 'LC3 MULTI RESOURCES, INC.', 'VAT', 'LC BLDG. RIZAL ST. POBLACION BAROTAC VIEJO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1269, '225-590-988-001', 'LEARNING AND PERFORMANCE PARTNERS INCORPORATED', 'VAT', 'G/F ROBINSONS PLACE LEDESMA QUEZON ST.,', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1270, '475-922-726-005', 'LECHONHAUS FOOD CORPORATION', 'VAT', 'JBI A VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1271, '475-922-726-002', 'LECHONHAUS FOOD CORPORATION', 'VAT', 'LOWER G/L SM CITY ILOILO BENIGO AQUINO AVE.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1272, '938-052-126-000', 'LEDESMA HOUSE GASOLINE REFILLLING STATION', 'VAT', 'PISON AVENUE BRGY. TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1273, '000-249-185-001', 'LEDI SUPERMART', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1274, '000-249-185-000', 'LEDI SUPERTMART', 'VAT', 'JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1275, '005-375-553-123', 'LEISURE AND ALLIED IND., PHILS. INC.', 'VAT', 'TIMEZONE-ROBINSONS ILOILO, LEDESMA ST. ROZAS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1276, '005-375-553-00124', 'LEISURE AND ALLIED IND., PHILS., INC.', 'VAT', 'ROBINSONS PLACE, LEDESMA ST., ROXAS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1277, '005-375-553-00123', 'LEISURE AND ALLIED IND., PHILS., INC.', 'VAT', 'ROBINSONS PLACE, LEDESMA ST., ROXAS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1278, '404-761-122-000', 'LEMUEL ADDISON D. ONG', 'VAT', 'LEDESCO VILLAGE, BRGY. CUBAY, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1279, '367-375-046-000', 'LEN\'Z PARTY NEEDS SHOP', 'VAT', 'RL MONTINOLA LEDESMA ST. KAUSWAGAN CITY PROPER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1280, '230-191-029-070', 'LET\'S FACE IT SALON', 'VAT', 'SM CITY MANDURRIAO ILOILO BRANCH', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1281, '401-868-879-00025', 'LEYLAM (LI AVRASYA, INC.)', 'VAT', 'GAISANO CITY LUNA, LAPAZ, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1282, '401-868-879-050', 'LI AVRASYA INC.', 'VAT', 'ROBINSONS PLACE MALL', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1283, '401-868-879-005', 'LI AVRASYA, INC.', 'VAT', 'G/F ILOILO SUPERMART MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1284, '009-572-463-000', 'LICANDAS COMMERCIAL', 'VAT', 'DALIPE SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1285, '623-608-620-001', 'LICANDA\'S OPC', 'VAT', 'SAN JOSE DE BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1286, '009-866-740-004', 'LIFEFOODS INCORPORATED', 'VAT', '2ND FLOOR FOOD HALL SM CITY MANDURRIAO , ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1287, '009-866-740-001', 'LIFEFOODS INCORPORATED', 'VAT', '2ND FLOOR FOOD HALL SM CITY MANDURRIAO , ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1288, '009-866-740-002', 'LIFEGOODS INCORPORATED', 'VAT', 'SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1289, '123-456-789-000', 'LIGHT OF GLORY TAXI SERVICES', 'NV', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1290, '477-417-981', 'LIGHT SPEED TAXI', 'NV', '#N/A', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1291, '444-829-882-000', 'LILIROSE ENTERPRISES', 'VAT', 'M. H. DEL PILAR ST., BRGY. TAAL, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1292, '316-845-889-000', 'LING BUSINESS VENTURES', 'VAT', 'CITY TIME SQUARE BRGY.SUBANGDAKU', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1293, '460-376-355-000', 'LIQUOR TOWN 5001 LIQUOR SHOP', 'NV', 'F.GORRICETA AVENUE.,BALABAG PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1294, '004-677-361-001', 'LITTLE VIN VIN FOOD CORP.', 'VAT', 'TERMINAL 2 NAIA PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1295, '424-421-325-000', 'LIVE COMFORT FOOD RESTAURANT', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1296, '326-734-847-000', 'LIVE LIFE PURIFIED WATER REFILLING STATION', 'NV', '78 SATA CRUZ, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1297, '648-326-258-000', 'LJB GREAT FOOD & BEVERAGE MANAGEMENT CORPORATION', 'VAT', '22 GENERAL LUNA STREET, SAN AGUSTIN, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1298, '638-882-752-001', 'LK1979 CORPORATION', 'VAT', 'SM CITY, BENIGNO AQUINO AVE., DIVERSION RD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1299, '491-678-768-000', 'LLP OLDTIMER INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1300, '177-177-920-001', 'LMV NATURAL STONE AND STEEL', 'VAT', '#8 BENIGNO AVENUE, SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1301, '201-871-637-012', 'LONGWIN ILOILO', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1302, '201-871-637-008', 'LONGWIN JARO', 'VAT', 'EL 98 ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1303, '603-579-720-035', 'LOOB PHILIPPINES INC.', 'VAT', 'SUN MALL ESPANA BLVD. MAYON ST. SANTA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1304, '129-405-705-00000', 'LOUIE C. EBRADA', 'NV', 'GUZMAN ST., HIBAO AN SUR, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1305, '166-477-099-000', 'LOUIE\'S EMISSION TESTING CENTER', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1306, '317-982-860-000', 'LOUIS BREAD & PASTRIES', 'NV', '21 AVE NUE BENIGNO AQUINO AVENUE MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1307, '000-427-388-0008', 'LOURDES-C AND SONS REALTY AND DEVELOPMENT CORPORATION', 'VAT', 'BRGY.BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1308, '920-936-954-001', 'LOVE & KISSES', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1309, '604-740-651-000', 'LOVETRENDS SHOPPING CENTER', 'VAT', 'LEGANES ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1310, '002-155-732-000', 'LOWTEMP CORPORATION', 'VAT', 'ANTIPOLO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1311, '904-590-071-000', 'LOYCE & LINLEY\'S FOOD KIOSK (MONA LOUISE L. AGUILAR )', 'VAT', 'NORTH WING NAIA TERMINAL 2 BRGY. 183, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1312, '255-430-394-000', 'LPG DISTRIBUTION CENTER', 'VAT', '201 JOCSON ST., SAN JOSE, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1313, '010-122-120-002', 'LRF ENTERPRISES INC', 'VAT', 'ZULUETA DRIVE TUPAZ CORNER ST BRGY BAROTAC VIEJO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1314, '008-222-274-0000', 'LUCKY STARGAZER CORP.', 'VAT', 'CONNECTICUT ST., MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1315, '177-210-505-000', 'LUMBER AND HARDWARE', 'VAT', 'SIMON LEDESMA STREET, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1316, '008-729-566-000', 'LUNA J FILIPINO FOOD CORPORATION', 'VAT', 'QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1317, '803-552-358-000', 'M& K SWEET BITES CHOCOLATE STORE', 'VAT', 'GT MALL ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1318, '603-552-358-000', 'M&K SWEET BITES', 'VAT', 'GT MALL,MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1319, '493-649-351-000', 'M.A LIM HOUSEHOLD PRODUCT TRADING', 'VAT', 'JALANDONI ST., POBLACION ILAWOD ZARRAGA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1320, '618-003-661-000', 'MA. ISABEL D. FERRARIZ', 'VAT', 'CALAHUNAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1321, '136-801-626-000', 'MA. ROSARIO H. VILLA', 'NV', 'GROUND LEVEL THE ATRIUM MALL GEN.LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1322, '102-271-902-000', 'MA.CRISTINA S. BORROMEO - PROP.', 'VAT', 'JAMES RESTAURANT BLDG. IZNART ST.,5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1323, '134-560-342-000', 'MAARNES FIVE PRODUCTS', 'NV', 'CENTRAL MARKET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1324, '738-542-103-002', 'MAGALONA SB ALLIANCE INC', 'VAT', 'ROBINSONS PLACE MABINI STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1325, '102-264-209-005', 'MAGAPET GAS STATION', 'VAT', 'BRGY. TABUCAN-AIRPORT MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1326, '738-542-103-004', 'MAGLONA SB ALLIANCE INC.', 'VAT', 'G/F ROBINSONS PLACE MALL JARO E. LOPEZ ST.,', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1327, '484-773-637-002', 'MAGNAV CORPORATION', 'VAT', 'ZONE 3 RBGY. JIBAO -AN PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1328, '484-773-637-000', 'MAGNAV CORPORATION', 'VAT', 'ZONE 3 BRGY. HIBA-AON PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1329, '485-873-096-000', 'MAGNIFICENTS RESTO GRILL CORPORATION', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1330, '134-560-665-000', 'MAGS PIPE BENDING AND STEEL WORKS', 'VAT', 'RIZAL ESTANZUELA ST., CIY PROPER,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1331, '000-057-799-000', 'MAJOR SHOPPING MANAGEMENT CORP', 'VAT', 'EDSA COR. WACK WACK MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1332, '102-270-337-000', 'MAKINAUGALINGON PRINTER AND BOOKBINDER', 'VAT', '251 LOPEZ JAENA ST., BALUARTE, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1333, '009-891-661-001', 'MANAYANAYA EATERY INC.', 'VAT', 'GAISANO CAPITAL PASSI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1334, '763-936-361-001', 'MANDARIN NEST RESORT (ISLA PACIFICO REALTY DEVELOPMENT INCORPORATED)', 'VAT', 'BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1335, '006-126-603-001', 'MANDARIN RESORT DEVELOPMENT CORPORATION', 'VAT', 'MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1336, '000-069-873-012', 'MANDAUE FOAM INDUSTRIES INC.', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1337, '000-069-873-006', 'MANDAUE FOAM INDUSTRIES INC.-GEN. LUNA', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1338, '102-264-209-0002', 'MANDURRIAO PETRON GAS STATION', 'VAT', 'Q. ABETO ST., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1339, '102-264-209-002', 'MANDURRIAO PETRON GAS STATION', 'VAT', 'Q.ABETO STR. MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1340, '201-001-062-040', 'MANDURRIAO STAR INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVENUE DIVERSION ROAD, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1341, '201-001-062-00052', 'MANDURRIAO STAR INC.', 'VAT', 'FESTIVE MARKET,FESTIVE WALK MALL,MEGAWORLS BOULEVARD,ILOILO,BUSINESS PARK MANDURRIAO ABETO MIRASOL TAFT,SOUTH ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1342, '201-001-062-000', 'MANDURRIAO STAR, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1343, '201-001-062-006', 'MANDURRIAO STAR, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1344, '201-001-062-041', 'MANDURRIAO STAR, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1345, '201-001-062-013', 'MANDURRIAO STAR, INC.', 'VAT', 'JALANDONI ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1346, '102-264-434-000', 'MANDURRIAO SUPERMART', 'VAT', 'Q. ABETO ST., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1347, '254-487-295-093', 'MANG INASAL PHIL.INC.', 'VAT', 'GF ROBINSON\'S PLACE OSMANA,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1348, '254-487-295-026', 'MANG INASAL PHILS. INC.', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1349, '254-487-295-035', 'MANG INASAL PHILS. INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1350, '254-487-295-114', 'MANG INASAL PHILS. INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1351, '286-718-890-000', 'MANGO TREE RESTAURANT AND CATERING SERVICE', 'VAT', 'BRGY. GUZMAN-JESENA, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1352, '205-175-945-001', 'MANILA SHOPPING MECCA CORP.', 'VAT', 'DELGADO CORNER VALERIA AND DELGADO ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1353, '006-460-893-018', 'MANNA FOODS & BEVERAGES PHIL', 'VAT', 'LG/L SM CITY CEBU NORTH RECLAMATION ARE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1354, '443-792-245-001', 'MANONG ISKO FOOD CORPORATION', 'VAT', 'POBLACION ANILAO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1355, '007-424-197-000', 'MAPLETREEINCORPORATED', 'VAT', 'BRGY. 289 ZONE 027 DIST. III BINONDO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1356, '759-409-971-000', 'MARA AGRIPOULTRY MARKETING CORPORATION', 'NV', 'BRGY. SAN VICENTE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1357, '180-351-900-003', 'MARARISON CAR SPA', 'VAT', 'TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1358, '422-782-720-001', 'MARARISON CAR SPA', 'VAT', 'TABUCAN MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1359, '422-782-720-000', 'MARARISON SEAFOOD HOUSE', 'VAT', 'TABUCAN MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1360, '180-351-900-002', 'MARARISON SEAFOOD PLACE', 'VAT', 'TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1361, '003-844-364-002', 'MARAVILLA ENTERPRISES INC', 'VAT', '135-B JOSERIZAL STREET, LAPUZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1362, '540-003-844-364', 'MARAVILLA ENTERPRISES INC', 'VAT', '135-B JOSERIZAL STREET, LAPUZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1363, '003-844-364-003', 'MARAVILLA ENTERPRISES, INC.', 'VAT', '135-B JOSE RIZAL ST., LAPUZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1364, 'MARC', 'MARC JORGE ALTURA', 'NV', 'X', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1365, '141-240-918-000', 'MARCLLUS UY REBADULLA-PROP.', 'VAT', 'TFJ BLDG., 88 COMMISSION CIVIL ST., JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1366, '201-284-794-000', 'MARIA CRISTINA MARGARITA V. PARAS', 'VAT', '5666 DON PEDRO ST., POBLACION 1210 CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1367, '146-647-912-000', 'MARIA ELIZABETH PILAR L. ATIENZA', 'VAT', 'PLARIDEL ST. SAN JOSE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1368, '281-464-294-006', 'MARIA LIZ PHARMACY', 'VAT', 'CAMALIG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1369, '123-752-161-000', 'MARIDEL\'S DESSERTS AND PASTRIES', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1370, '715-576-334-000', 'MARIGOLD NATIVE PRODUCTS STORE', 'NV', 'ILOILO CENTRAL MARKET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1371, 'MARK', 'MARK EDUARD T. COLON', 'NV', 'X', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1372, '216-682-854-029', 'MARY GRACE FOODS INC.', 'VAT', 'UNIT 17 LEVEL 4 NAIA TERMINAL 3, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1373, '216-682-854-044', 'MARY GRACE FOODS INC.', 'VAT', 'UNIT 17 LEVEL 4 NAIA TERMINAL 3, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1374, '127-715-781-000', 'MARY MILAGROS ALELICAY HECHANOVA', 'NV', '114-B ST. ELIZABETH CENTR, VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1375, '432-299-280-000', 'MAS NOVELTY SHOP', 'VAT', 'J.M. BASA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1376, '009-522-100-008', 'MASTER SHOPPERS VENUE, INC.', 'VAT', 'SM CITY ROXAS ARNALDO BLVD. BRGY. BAYBAY ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1377, '191-750-164-000', 'MATAPAHA STORE', 'NV', 'LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1378, '191-756-164-000', 'MATAPAHA STORE', 'NV', 'LAPAZ PUBLIC MARKET,LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1379, '414-762-507-000', 'MATYFRAN\'S MEAT SHOP', 'NV', '6TH ST.,LAWAAN VILLAGE BALANTANG JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1380, '000-487-637-000', 'MAXICARE HEALTHCARE CORPORATION', 'VAT', 'MAXICARE TOWER 203 SALCEDO ST.,BRGY.SAN LORENZO LEGASPI VILLAGE MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1381, '004-249-165-000', 'MAXXIMILER DISTRIBUTORS, INC.', 'VAT', 'FLC BLDG., LAPUZ NORTE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1382, '004-249-165-00000', 'MAXXIMILER DISTRIBUTORS, INC.', 'VAT', 'FLC BLDG., LAPUZ NORTE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1383, '400-624-217-000', 'MAY A. ZAPANTA', 'VAT', 'NEW FUS BLDG. SAN ROQUE EXT., ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1384, '403-746-248-000', 'MAYCEE CONSTRUCTION SUPPLY', 'VAT', 'COMM. CIVIL ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1385, '289-954-661-00000', 'MBM ILOILO ASSET MANAGEMENT SERVICES INC.,', 'VAT', 'IMC BONIFACIO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1386, '424-421-325-001', 'MBP SALADSAND WRAPS', 'VAT', 'EXPANSION SM CITYMANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1387, '241-498-356-012', 'MC WILSON CORPORATION', 'VAT', 'SAN LORENZO NCR, FOURTH DISTRICT, CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1388, '226-422-731-012', 'MC-DONALDS-ILOILO BUSINESS PARK', 'VAT', 'Q.ABETO ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1389, '007-889-218-002', 'MCDREYSON CORPORATION', 'VAT', 'SM CITY ILOILO BOLILAO MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1390, '455-237-632-000', 'MD EXCELLENCE ILOILO INC.', 'VAT', 'ATRIA, DONATO PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1391, '937-199-439-000', 'MEDIPLUS PHARMACY', 'VAT', 'ST. ELIZABETH CENTER VALERIA ST., IC', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1392, '007-811-693-022', 'MEGA GOLDTOWN PAN, INC.', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1393, '007-811-693-0019', 'MEGA GOLDTOWN PAN, INC.', 'VAT', 'IZNART COR DELGADO,ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1394, '135-352-702-002', 'MEGABIT TECHNOLOGIES -ILOILO', 'VAT', 'GAISANO CAPITAL, LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1395, '102-264-209-0005', 'MEGAPET GAS STATION', 'VAT', 'TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1396, '009-393-327-000', 'MEGASTAR CONCEPT CORP.', 'VAT', 'MAIN ROAD BALABAG MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1397, '000-477-103-015', 'MEGAWORLD CORPORATION', 'VAT', 'TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1398, '007-424-197-123', 'MEMOXPRESS', 'VAT', 'SM CITY ILOILO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1399, '213-339-571-000', 'MERCEDES ILOILO DRUG HOUSE', 'VAT', 'Q.ABETO ST., MAND. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1400, '000-388-474-00328', 'MERCURY DRUG', 'VAT', 'MH DEL PILAR ST, MOLO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1401, '000-388-474-00332', 'MERCURY DRUG', 'VAT', 'MH DEL PILAR ST, MOLO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1402, '000-388-474-333', 'MERCURY DRUG', 'VAT', 'MERCURY DRUG-MAKATI CITY VALERO 2 CARPARK G/F VALERO 2 CARPARK VLDG. MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1403, '000-388-474-806', 'MERCURY DRUG', 'VAT', 'CORNER ROOSEVELT AVENUE BRGY STA', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1404, '000-388-474-790', 'MERCURY DRUG', 'VAT', 'QUEZON CITY VIZASAY AVENUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1405, '000-388-474-00472', 'MERCURY DRUG', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1406, '000-388-474-725', 'MERCURY DRUG', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1407, '000-388-474-305', 'MERCURY DRUG', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1408, '000-388-474-00020', 'MERCURY DRUG - AYALA CENTER CEBU CITY', 'VAT', 'AYALA CENTER ARCH REYES AVE CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1409, '000-388-474-656', 'MERCURY DRUG - ILOILO GUIMBAL RIZAL', 'VAT', 'RIZAL ST. BRGY RIZAL, GUIMBAL ILO-ILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1410, '000-388-474-535', 'MERCURY DRUG - ILOILO JARO TAGBAK', 'VAT', 'TAGBAK JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1411, '000-388-474-752', 'MERCURY DRUG - ILOILO OTON', 'VAT', 'OTON, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1412, '000-388-474-00923', 'MERCURY DRUG - ILOILO PAVIA GT TOWN', 'VAT', 'GT TOWN CENTER FERNANDO LOPEZ AVE. UNGKA PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1413, '000-388-474-00712', 'MERCURY DRUG - ILOILO SARA', 'VAT', 'POBLACION ILAWOD SARA', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1414, '000-388-474-01003', 'MERCURY DRUG - LA PAZ BRANCH', 'VAT', 'BRGY.RAILWAY LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1415, '000-388-474-986', 'MERCURY DRUG - MAKATI BEL-AIR', 'VAT', 'MAKATI AVE. BEL-AIR MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1416, '000-388-474-00667', 'MERCURY DRUG - MKTI LEGASPI VLG SALCEDO', 'VAT', 'MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1417, '000-388-474-846', 'MERCURY DRUG - QC UP TOWN CENTER', 'VAT', 'UP TOWN CENTER KATIPUNAN, UP CAMPUS, QC', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1418, '000-388-474-1035', 'MERCURY DRUG- AKLAN MALAY BORACAY', 'VAT', 'SINAGPA BALABAG MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1419, '000-388-474-00727', 'MERCURY DRUG- ANTIQUE', 'VAT', 'NIETES ST., BRGY. FUNDA DALIPE, SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1420, '000-388-474-410', 'MERCURY DRUG- ARANETA CTR GATEWAY MALL ARANETA CENTER', 'VAT', 'ARANETA CENTER CUBAO QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1421, '000-388-474-538', 'MERCURY DRUG- BALASAN', 'VAT', 'BALASAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1422, '000-388-474-00656', 'MERCURY DRUG- GUIMBAL RIZAL', 'VAT', 'RIZAL ST.,GUIMBAL ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1423, '000-388-474-328', 'MERCURY DRUG- ILOILO MOLO', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1424, '000-388-474-00814', 'MERCURY DRUG- ILOILO AREVALO', 'VAT', 'BRGY. QUEZON, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1425, '000-388-474-472', 'MERCURY DRUG- ILOILO CITY GEN. LUNA QUEZON', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1426, '000-388-474-924', 'MERCURY DRUG- ILOILO FESTIVAL WALK MALL', 'VAT', 'FESTIVE WALK MALL ANNEX A ILOILO CUSINESS PARK, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1427, '000-388-474-350', 'MERCURY DRUG- ILOILO IZNART', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1428, '000-388-474-332', 'MERCURY DRUG- ILOILO JARO', 'VAT', 'LA CASTILLA EL 98 STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1429, '000-388-474-439', 'MERCURY DRUG- JARO E. LOPEZ', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:04', '2026-07-13 03:05:04'),
(1430, '000-388-474-408', 'MERCURY DRUG- SAN JOSE ANTIQUE FORNIER', 'VAT', 'COR. TOBIAS FORNIER & BANTAYAN STS., SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1431, '000-388-474-00166', 'MERCURY DRUG- SAN PEDRO MABINI', 'VAT', 'SAN PEDRO, LAGUNA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(1432, '000-388-474-653', 'MERCURY DRUG- SJ CITY G\'HILLS AVE.', 'VAT', 'SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1433, '000-388-474-818', 'MERCURY DRUG- THE SHOPS ATRIA', 'VAT', 'DONATO P. AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1434, '000-388-474-00818', 'MERCURY DRUG- THE SHOPS ATRIA', 'VAT', 'DONATO P. AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1435, '000-388-474-00815', 'MERCURY DRUG-ANTIQUE CULASI CENTRO', 'VAT', 'COR.JAVIER ST.,BRGY.CENTRO POB.', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1436, '000-388-474-00725', 'MERCURY DRUG-ILOILO CITY MANDURRIAO PA', 'VAT', 'BENEDICTO ST., BRGY. PALI MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1437, '000-388-474-00924', 'MERCURY DRUG-ILOILO FESTIVE WALK', 'VAT', 'FESTIVE WALK MALK-MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1438, '000-388-474-00350', 'MERCURY DRUG-ILOILO IZNART', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1439, '000-388-474-00439', 'MERCURY DRUG-ILOILO JARO E. LOPEZ', 'VAT', 'E.LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1440, '000-380-474-00332', 'MERCURY DRUG-ILOILO JARO E. LOPEZ', 'VAT', 'LA CASTILLA EL 98 STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1441, '000-388-474-00535', 'MERCURY DRUG-ILOILO JARO TAGBAK', 'VAT', 'TAGBAK JAROILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1442, '000-388-474-00388', 'MERCURY DRUG-ILOILO MABINI', 'VAT', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1443, '000-388-474-00975', 'MERCURY DRUG-ILOILO MIAG AO', 'VAT', 'VILLA LUISA 2 BLDG.,ZULUETA AVENUE', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1444, '000-388-474-00331', 'MERCURY DRUG-ILOILO PASSI CITY', 'VAT', 'PALMARES ST., PASSI CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1445, '000-388-474-00960', 'MERCURY DRUG-ILOILO POOTAN POBLACON', 'VAT', 'BRGY. POBLACION POTOTAN,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1446, '000-388-474-00333', 'MERCURY DRUG-ILOILO VALERIA', 'VAT', 'VALERIA COR, DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1447, '000-388-474-00672', 'MERCURY DRUG-PLAZA LIBERTAD', 'VAT', 'PLAZA LIBERTAD, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1448, '000-388-474-672', 'MERCURY DRUG-PLAZA LIBERTAD', 'VAT', 'PLAZA LIBERTAD, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1449, '006-324-896-089', 'MERIDIEN BUSINESS LEADER, INC.', 'VAT', 'SM STORE DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1450, '006-324-896-019', 'MERIDIEN BUSINESS LEADERS, INC.', 'VAT', 'SM AURA PREMIER C5 ROAD COR 26TH ST BONIFACIO CITY TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1451, '006-324-896-057', 'MERIDIEN BUSINESS LEADERS, INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVENUE, BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1452, '281-768-124-012', 'MERRYMART CONSUMER CORP.', 'VAT', 'G/F CITYMALL PAROLA FORT SAN PEDRO DRIVE CONCEPTION-MONTES ILOILO CITY CAPITAL ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1453, '281-768-124-000', 'MERRYMART CONSUMER CORP.', 'VAT', 'ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1454, '281-768-124-005', 'MERRYMART CONSUMER CORP.', 'VAT', 'PLARIDEL STREET, ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1455, '281-768-124-001', 'MERRYMART CONSUMER CORP.', 'VAT', 'BURGOS ST., ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1456, '281-768-124-006', 'MERRYMART CONSUMER CORP.', 'VAT', 'BURGOS ST., ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1457, '281-768-124-002', 'MERRYMART CONSUMER CORP.', 'VAT', 'BURGOS ST., ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1458, '281-768-124-008', 'MERRYMART CONSUMER CORP.,', 'NV', 'BENIGNO AQUINO AVE.,BRGY SAN RAFAEL MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1459, '010-148-200-021', 'MERRYMART GROCERY CENERS INC', 'VAT', 'QUINTIN SALAS JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1460, '010-148-200-022', 'MERRYMART GROCERY CENERS INC', 'VAT', '100 J DELEON COR QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1461, '010-148-200-032', 'MERRYMART GROCERY CENTERS INC', 'VAT', 'BENIGNO AQUINO AVE., DIVERDION RD., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1462, '010-148-200-001', 'MERRYMART GROCERY CENTERS INC.', 'VAT', 'DD-MERIDIAN PARK BAY AREA EDSA EXT COR MACAPAGAL AVE BRGY 076 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1463, '004-247-791-034', 'MERZCI BREAD AND PASTRIES (CM AND SONS FOOD PRODUCTS INC.)', 'VAT', 'MREDCO RECLAMATION AREA BRGY. 10, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1464, '004-247-791-002', 'MERZCI PASALUBONG CENTER', 'VAT', 'DOOR 15 A&B UTC MALL ARANETA ST. BACOLOD', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1465, '004-247-791-073', 'MERZCI PASALUBONG CENTER', 'VAT', 'BLUMENTRITT BRGY 363 CITY OF MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1466, '006-126-000-000', 'MESTIZO BY EMILION (TFM ALLIANCE, INC.)', 'VAT', 'COR. RIZAL-HUERVANA STS., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1467, '007-895-538-009', 'METOLIUS VALLEY INC', 'VAT', 'CEES BLDG PAKIAD 5020 OTON ILOILO PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1468, '007-895-538-00009', 'METOLIUS VALLEY INC', 'VAT', 'CEES BLDG PAKIAD 5020 OTON ILOILO PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1469, '009-867-724-000', 'METRO CHERRY COMMERCIAL AND SUPPLY INC.', 'VAT', '57N.PEREZ ST., PANSOL,QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1470, '000-995-756-000', 'METRO ILOILO WATER DISTRICT', 'NV', 'BONIFACIO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1471, 'MIWD', 'METRO ILOILO WATER DISTRICT', 'NV', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1472, '000-995-756-00000', 'METRO ILOILO WATER DISTRICT', 'NV', 'BONIFACIO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1473, '009-992-006-004', 'METRO KITCHEN INNOVATIONS INC.', 'VAT', 'SAN LORENZO, MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1474, '240-798-564-000', 'METRO MAIN STAR ASIA CORP', 'VAT', 'SM MALL OF ASIA, JW DIOKNO BLVD. CBP 1A', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1475, '240-798-564-103', 'METRO MAIN STAR ASIA CORP', 'VAT', 'SM CITY BAGUIO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1476, '205-172-945-000', 'METRO MANILA SHOPPING MECCA CORP', 'VAT', 'BRGY. 659, ERMITA NCR, MANILA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1477, '205-172-945-00063', 'METRO MANILA SHOPPING MECCA CORP.', 'VAT', 'AX 103 SM DELGADO COR VALERIA DELGADO STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1478, '205-172-945-001', 'METRO MANILA SHOPPING MECCA CORP.', 'VAT', 'SM DELGADO COR DELGADO-VALERIA STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1479, '206-172-945-00073', 'METRO MANILA SHOPPING MECCA CORP.', 'VAT', 'SM DELGADO CORNER VALERIA AND DELGADO DANAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1480, '205-172-945-00001', 'METRO MANILA SHOPPING MECCA CORP.', 'VAT', 'AX 103 SM DELGADO COR VALERIA DELGADO STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1481, '206-172-545-00001', 'METRO MANILA SHOPPING MECCA CORP.', 'VAT', 'SM DELGADO CORNER VALERIA AND DELGADO STS,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1482, '004-864-826-000', 'METRO PACIFIC CONSTRUCTION SUPPLY, INC.', 'VAT', 'MABINI STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1483, '226-527-915-045', 'METRO RETAIL STORES GROUP, INC.', 'VAT', 'ATRIA, DONATO PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1484, '000-477-863-00616', 'METROBANK METROPOLITAN BANK & TRUST COMPANY', 'NV', 'ILOILO JM BASA BRANCH', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1485, '226-527-915-031', 'METRO-CEBU', 'VAT', 'CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1486, '010-253-818-000', 'METRO-PACIFIC ILOILO WATER,INC.', 'VAT', 'BONIFACIO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1487, '004-247-791-049', 'MFRCT PSAI HRANG CENTER', 'VAT', 'DFI GADO ST. BRGY FD GANZON ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1488, '010-268-633-003', 'MGA . 414 CORPORATION', 'VAT', 'BRGY. M.V. HECHANOVA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1489, '603-690-510-000', 'MGJG INC.', 'VAT', '139 RIZAL ESTANZUELA,CITY PROPER,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1490, '928-927-032-000', 'MIGUEL J. CORDOVA', 'VAT', 'RED SQUARE BLDG.,SMALLVILLE COMPLEX', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1491, '456-290-104-000', 'MIKAELLA ANTONETTE B. CANAMAN -PROP', 'NV', '2ND FLOOR GTBI BLDG.,WVSU CAMPUS,LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1492, '451-293-427-004', 'MIL GASOLINE STATIONS', 'VAT', 'BRGY. DULONAN AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1493, '424-702-805-000', 'MILAGROS JARDELEZA FOOD PROCESSING', 'VAT', '299 JALANDONI ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1494, '007-781-739-002', 'MINDAVE FOOD MERCHANTS INC', 'VAT', 'GERARDO TUAZON, SAMPALOK MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1495, '009-767-383-035', 'MINI DEPATO CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1496, '009-767-383-007', 'MINI DEPATO CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1497, '009-767-383-036', 'MINISO (MINI DEPATO CORP)', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1498, '009-767-363-000', 'MINISO (MINI DEPATO CORP)', 'VAT', 'PASAY CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1499, '009-767-383-00122', 'MINISO (MINI DEPATO CORP)', 'VAT', 'SM ILOILO CENTRAL MARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1500, '009-767-383-029', 'MINISO DEPATO CORP', 'VAT', 'ROBINSONS PLACE PADRE PAURA M ADRIATICO ST COR PEDRO GIL ST BRGY 669 ZONE 72 ERMITA MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1501, '009-274-483-024', 'MINISO PHILIPPINES INC.', 'VAT', '2/F SM CITY ILOILO, BENIGNO AQUINO JR. AVENUE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1502, '468-324-347-006', 'MINISTOP', 'VAT', 'MAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1503, '007-094-175-000', 'MIRADOR JESUIT VILLA RETREAT HOUSE, INC', 'NV', 'BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1504, '428-655-932-000', 'MIRRIAM T. GIERZA', 'VAT', 'GONSALEZ ST., LIBO-ON, GUIMBAL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1505, 'MIS', 'MISCELLANEOUS', 'NV', 'XX', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1506, '177-941-891-001', 'MITZI GRAINS CENTER', 'NV', 'DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1507, '414-880-871-000', 'MJ STONE WORLD BUILDERS CORPORATION', 'VAT', 'BRGY. GUIHAMAN, LEGANES, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1508, '451-295-427-004', 'MJL GASOLINE STATIONS', 'VAT', 'BRGY. DULONAN AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1509, '917-776-385-001', 'MM NATIVE PRODUCTS', 'NV', 'ILOILO CENTRAL MKT. IZNART ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1510, '917-776-365-001', 'MM NATIVE PRODUCTS', 'NV', 'ILOILO CENTRAL MKT. IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1511, '161-730-626-000', 'MMN STORE', 'NV', 'ONATE STREET, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1512, '906-501-193-002', 'MO2 RESTOBAR', 'VAT', 'SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1513, '102-264-209-0001', 'MOLO PETRON SERVICE CENTER', 'VAT', 'M. H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1514, '102-264-209-001', 'MOLO PETRON SERVICE STATION', 'VAT', 'M. H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1515, '494-363-172-000', 'MOLO PLAZA SUPERMART, INC.', 'VAT', 'ANTIGUA-AVANCEÑA STS., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1516, '279-878-896-000', 'MOMMY LINDA STORE', 'NV', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1517, '010-126-623-000', 'MONGOL TRADING CORP.', 'NV', '2A ORCHARD BLDG.,LOPEZ JAENA ST., LOPEZ JAENA SUR,LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1518, '601-884-597-000', 'MONKEY GROUND COFFEE SHOP', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1519, '492-894-548-000', 'MONKEY GROUNDS COFFEE SHOP', 'VAT', 'BRGY. SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1520, '601-884-597-00000', 'MONKEY GROUNDS COFFEE SHOP', 'VAT', 'BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1521, '000-935-433-000', 'MONTENEGRO SHIPPING LINES , INC.', 'VAT', 'BATANGAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1522, '459-814-512-000', 'MONTIBRO INTERNATIONAL TRADING INC.', 'VAT', 'LEGASPI ST., STO NINO, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1523, '005-820-364-000', 'MOOST BRAND , INC.', 'VAT', '5 VALERIA ST., 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1524, '005-820-364-001', 'MOOSTBRAND HOME DEPOT', 'VAT', 'BUHANG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1525, '007-106-367-000', 'MORE ELECTRIC AND POWER CORPORATION', 'VAT', '2ND FLOOR GST CORPORATE CENTER, QUEZON ST, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1526, '010-290-569-013', 'MOTOBRAND CORPORATION', 'VAT', 'LG 0030 SM CITY B AQUINO AVE, MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1527, '005-984-717-000', 'MOUNT CARMEL SECURITY AGENCY, INC.', 'VAT', 'FIGUEROA ST., AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1528, '004-474-989-000', 'MOVEIN AND PICK BAKESHOP AND RESTAURA NT, INC.', 'VAT', 'BANAWE ST., LOURDES 1 Q.C.', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1529, '456-793-161-004', 'MQHPT ENTERPRISES CORP.', 'VAT', 'SM CITY ILOILO BENIGNO AQUINO AVENUE DIVERSION ROAD,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1530, '010-057-617-00760', 'MR DIY (BRICOLAGE PHILIPPINES INC.)', 'VAT', 'POBLACION BAROTAC VIEJO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1531, '010-057-617-00559', 'MR DIY (BRICOLAGE PHILIPPINES INC.)', 'VAT', 'POBLACION ZARRAGA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1532, '010-057-617-00548', 'MR DIY (BRICOLAGE PHILIPPINES INC.)', 'VAT', 'POBLACION MARKET, SARA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1533, '010-057-617-254', 'MR. DIY (BRICOLAGE PHILIPPINES INC.)', 'VAT', 'ARGUELLES , JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1534, '008-554-415-004', 'MR. KIMBOB', 'VAT', 'LUNETA HILL BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1535, '008-554-415-00038', 'MR. KIMBOB BIBIMBOB INC', 'VAT', 'BRGY. BOLILAO, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1536, '008-554-415-012', 'MR. KIMBOB BIBIMBOB INC', 'VAT', 'EDSA CO NORTH AVE BAGONG PAG-ASA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1537, '005-693-898-117', 'MR.QUICKIE CORP.SERVICES', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1538, '603-690-510-00000', 'MSJG INC.', 'VAT', '139 RIZAL ESTANZUELA , CITY PROPER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1539, '009-547-333-006', 'MUJI PHILIPPINES CORP.', 'VAT', 'L1 JW DIOKNO BLVD. BRGY. 076 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1540, '009-547-333-00007', 'MUJI PHILIPPINES CORP.', 'VAT', 'MAKATI CITY, METRO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1541, '947-239-005-000', 'MULL\'S VULCANIZING & 2ND HAND TIRES', 'NV', 'INFANTE AVENUE,MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1542, '200-987-487-000', 'MULTI-M FOOD CORPORATION', 'VAT', 'LAOAG CITY, ILOCOS NORTE', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1543, '010-057-586-000', 'MULTIPLIERS CORP.', 'VAT', 'GREENFIELD SQUARE,BENIGNO AQUINO AVENUE SAN RAFAEL,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1544, '007-766-249-027', 'MUMUSO (GREATSPAN INC.)', 'VAT', 'SAN LORENZO, MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1545, '444-679-380-000', 'N.S. NATIVE PRODUCTS', 'NV', 'NO. 49 IZNART STREET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1546, '921-847-368-001', 'NARITA ELECTRICAL &AUTO PARTS', 'VAT', 'DELGADO STS. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1547, '000-325-972-003', 'NATIONAL BOOK STORE, INC.', 'VAT', 'ROBINSON PLACE, ERMITA, MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1548, '000-325-972-020', 'NATIONAL BOOK STORE, INC.', 'VAT', 'SM CITY COMPLEX NORTH AVE., STO. CRISTO 1, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1549, '008-980-827-002', 'NEGROS GOURMET FOOD SOCIETY INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1550, '286-090-403-000', 'NEL KAT-AL MARKETING', 'NV', 'SIMON LEDESMA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1551, '758-675-386-000', 'NELITA L. FELOMINO-PROP.', 'VAT', 'CPK BLDG. MABINI STREET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1552, '720-385-374-0000', 'NENE J SEAFOODS', 'NV', 'BRGY. 1, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1553, '705-170-529-000', 'NERIZZA R. CANILLO PRO.', 'VAT', 'VALERIA EXT..,VALERIA ST. MAGSAYSAY,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1554, '923-415-253-003', 'NET EXPRESS INTERNET HUB', 'NV', 'M.H. DEL PILAR ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1555, '008-738-689-003', 'NET FUEL STATION CORPORATION', 'VAT', 'SAN NICOLAS OTON,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1556, '437-951-614-001', 'NETONG\'S BATCHOY', 'VAT', 'ATRIA BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1557, '219-621-613-082', 'NETOPIA INTERNET CAFÉ', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1558, '102-268-077-000', 'NEW A-BROS MARKETING', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1559, '005-820-155-000', 'NEW BUANTONG TRADE CENTER & COMPANY', 'VAT', '42-1-1 QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1560, '941-251-032-000', 'NEW ERNING\'S MANOKAN AND SEAFOOD', 'NV', 'BRGY. STO. NINO SUR, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1561, '276-027-984-00000', 'NEW ERNING\'S MANOKAN&SEAFOODS RESTAURANT', 'NV', 'SANTO NINO SUR, AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1562, '425-968-487-001', 'NEW GENESIS MERCHANDISING', 'VAT', 'J.C. ZULUETA ST., OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1563, '261-769-017-0002', 'NEW GINZU MERCHANDISING , INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1564, '261-769-017-0003', 'NEW GINZU MERCHANDISING INC.', 'VAT', 'R.Y LADRIDO ST. POTOTAN ILOILO 5008', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1565, '261-769-017-0000', 'NEW GINZU MERCHANDISING, INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1566, '261-769-017-000', 'NEW GINZU MERCHANDISING, INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1567, '102-268-420-000', 'NEW GOODWILL MARKETING', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1568, '493-812-180', 'NEW ILOILO GQ ENTERPRISES INC', 'VAT', 'DELGADO ST ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1569, '493-812-180-000', 'NEW ILOILO GQ ENTERPRISES INC.', 'VAT', 'VALERIA-DELGADO STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1570, '254-793-017-000', 'NEW ILOILO MANHATTAN COMMERCIAL', 'NV', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1571, '436-271-920-0000', 'NEW IMAGE NOVELTY SHOP', 'NV', 'ARROYO, RAILWAY LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1572, '260-977-929-000', 'NEW LIFE BATTLE STATION INTERNET CAFÉ', 'NV', 'MARYMART MALL, VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1573, '908-805-533-000', 'NEW MERCURY MERCHANDISING', 'NV', '100 IZNART STREET,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1574, '143-263790', 'NEW MULTI PAINT CENTER', 'VAT', 'QUEZON ST ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1575, '143-263-790-000', 'NEW MULTI PAINT CENTER', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1576, '191-756-574-000', 'NEW PANAY GLASS ALUMINUM & MIRROR', 'VAT', '#311 MAC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1577, '916-475-470-000', 'NEW VGM STORE', 'NV', 'MISSION ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1578, '006-459-446-000', 'NEW WASHINGTON TRADING CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1579, '436-271-920-000', 'NEWIMAGE NOVELTY SHOP', 'VAT', 'ARROYO RAILWAY LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1580, '438-081-084-002', 'NEWTOWN PLAZA HOEL CORP', 'VAT', 'NAVY BASE RD ST JOSEPH BRGY BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1581, '102-273-766-001', 'NH MOTOR SALES', 'VAT', 'MAC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1582, '270-663-274-000', 'NICHE HOMEWARE', 'VAT', 'J.DE LEON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1583, '258-555-051-002', 'NICOLAS GATILAO', 'VAT', 'GERONA ST., GUIMBAL,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1584, '272-996-242-000', 'NICOLETTE BAKERY + CAFÉ', 'VAT', 'DIVERSION ROAD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1585, '194-054-890-000', 'NICOMAX NATIVE PRODUCT', 'NV', 'IZNART STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1586, '716-803-937-000', 'NINA FOOD PRODUCTS TRADING', 'NV', 'MISSION EXTENSION RD. BRGY SAN NICOLAS LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1587, '102-265-058-000', 'NIPPON ENGINEERING WORKS', 'VAT', 'COR.MABINI-LEDESMA STS., CITY PROPER ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1588, '004-984-946-000', 'NLEX CORPORATION', 'VAT', 'CALOOCAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1589, '318-385-528-000', 'NOBLEZA CELLPHONE ACCESSORIES', 'NV', 'Q.ABETO ST., ABETOMIRASOL TAFT SOUTH MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1590, '008-547-213-005', 'NODDLERAMA GROUP INC.', 'VAT', 'ROBINSON\'S PLACE MANILA PEDRO GIL ST. COR M ADRIATICO & FAURA STS. BRGY 669 ZONE 072 ERMITA MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1591, '764-771-831-001', 'NOMNOMNOM INC.,', 'NV', 'G/F ATRIUM MALL BLDG. GEN LUNA ST., BRGY.DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1592, '292-330-407-002', 'NONKI FOODS CEBU INC', 'VAT', 'GRAND XING IMPERIAL HOTEL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1593, '292-330-407-000', 'NONKI FOODS CEBU INC.', 'VAT', 'IMPERIAL HOTEL, TOWER II, MUELLE LONEY ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1594, '008-988-478-00002', 'NORISUSHI INC.', 'VAT', 'MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1595, '004-630-100-001', 'NORTH PARK NOODLE HOUSE INC.', 'VAT', 'MAKATI AVE. BEL-AIR MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1596, '135-539-305-001', 'NORTH POINT GAS STATION', 'VAT', 'LOPEZ JAENA ST., BRGY. ARGUELLES JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1597, '916-483-728-000', 'NORTH-EAST ELECTRICAL SUPPLY', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1598, '102-264-338-000', 'NORTHERN ILOILO LUMBER AND HARDWARE', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1599, '004-461-256-00071', 'NORTHERN LUZON DRUG CORPORATION', 'VAT', 'BRGY.VILLA LIBERTAD, EL NIDO PALAWAN', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1600, '006-265-571-000', 'NORTHWEST BACOLOD TOURS & TRAVELS, INC.', 'VAT', 'KILAYCO BLDG., RIZAL-MABINI STS., BRGY. 24, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1601, '009-568-376-006', 'NOVALEAR COFFEE CONCEPTS INC', 'VAT', 'ONE BONIFACIO HIGH ST. 5TH AVE COR BONIFACIO TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1602, '009-568-378-008', 'NOVATEUR COFFEE CONCEPTS INC.', 'VAT', 'ONE BONIFACIO HIGH STREET 5TH AVE., COR 28TH ST. BONIFACIO GLOBAL CITY TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1603, '152-132-684-000', 'NOVELTY SHOP (ALFONSO BERGANTE REDONDA)', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1604, '177-213-557-0007', 'NULADAS TRADING', 'VAT', 'TIGBUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1605, '177-213-557-007', 'NULADAS TRADING VII', 'VAT', 'TEJERO COR. TUPAS STREET, TIGBAUAN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1606, '116-074-408-000', 'NUTS BERRY GARDEN', 'VAT', 'HDA. STA. MARIA BO. MATAB-ANG TALISAY CITY NEG OCC.', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1607, '009-144-650-000', 'NUVOPIAZZA VENTURES INC.', 'VAT', '9745 KAMAGONG ST., SAN ANTONIO 1203, CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1608, '007-251-393-011', 'OAK FOREST RETAIL STORES INC', 'VAT', 'ROBINSONS PLACE ILOILO QUEZON ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1609, '927-417-477', 'OASIS RICE OUTLET', 'VAT', 'LOPEZ JAENA ST, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1610, '002-006-528-006', 'OCEAN CITY', 'VAT', 'ATRIUM', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1611, '002-006-528-000', 'OCEAN CITY SEAFOOD & RESTAURANT, INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1612, '002-006-528-00000', 'OCEAN CITY SEAFOOD & RESTAURANT, INC.', 'VAT', 'GEN. LUNA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1613, '002-006-528-00009', 'OCEAN CITY SEAFOOD & RESTAURANT, INC.', 'VAT', 'LOPEZ ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1614, '002-006-528-008', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1615, '002-006-528-001', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1616, '002-006-528-009', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1617, '002-006-528-007', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1618, '002-006-528-011', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1619, '002-006-528-002', 'OCEAN CITY SEAFOOD AND RESTAURANT, INC.', 'VAT', 'ROBINSONS PLACE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1620, '000-258-937-004', 'OCEAN FAST FERRIES INC.', 'VAT', 'INSULAR WHARF TAGBILARAN CITY BOHOL', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1621, '419-970-214-000', 'OCEAN QUEST INTERNET CAFÉ', 'NV', 'POBLACION, SOUTH OTON,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1622, '009-837-787-001', 'OCTAPRIME INFINITY FOODS CORP.', 'VAT', 'GAISANO CITY, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1623, '009-837-787-000', 'OCTAPRIME INFINITY FOODS CORP.', 'VAT', 'GAISANO CITY, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1624, '009-837-787-002', 'OCTAPRIME INFINITY FOODS CORP.', 'VAT', 'GAISANO CITY, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1625, '424-865-640-000', 'OGANE RESTAURANT', 'VAT', 'QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1626, '102-269-358-000', 'OLIOLL HARDWARE AND CONSTRUCTION SUPPLY', 'NV', 'IZNART COR. MAGSAYSAY ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1627, '454-252-993-000', 'OLIVEN FLORENCIO AND CO.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1628, '490-586-724-000', 'OLIVIAS KITCHEN AND ISLAND BREW BAI', 'VAT', 'SAN MIGUEL, JORDAN GUIMARAS', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1629, '278-522-233-004', 'ONE MI CORP', 'VAT', 'FORTUNE CENTER QUEZON AVE. BANAWE ST. QC', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1630, '445-655-020-000', 'ONECLICKTECH CORP.', 'VAT', 'UNIT F7 BLDG.COR SCT BORROMEO SOUTH TRIANGLE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1631, '441-564-817-001', 'ONESHOP ILOILO CO.', 'VAT', 'E. LOPEZ STREET, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1632, '602-715-402-000', 'ONLYFOODSPH CORPORATION', 'VAT', 'UNIT A VINE BUILDING DON PEDRO CORNER ALFONSO STREET POBLACION MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1633, '220-403-613-004', 'OPTIMIZED CUSTOMER SOLUTIONS INC', 'VAT', 'BRGY.CALAJUNAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1634, '220-403-613-00004', 'OPTIMIZED CUSTOMER SOLUTIONS INC', 'VAT', 'BRGY.CALAJUNAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1635, '220-403-613-000', 'OPTIMIZED CUSTOMER SOLUTIONS, INC.', 'VAT', 'MAC ARTHUR HIGHWAY, SAGUIN CITY OF SAN FERNANDO, PAMPANGA', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1636, '453-952-744-000', 'OPULENZA CORPORATION', 'VAT', '#35 RIZAL ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1637, '009-105-766-000', 'ORANGE ISLAND INCORPORATED', 'VAT', 'SM CITY DIVERSION RD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1638, '005-982-855-000', 'ORIG BISCOCHO HAUS CORP.', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1639, '005-982-855-038', 'ORIGINAL BISCOCHO HAUS CORP.', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1640, '005-982-855-023', 'ORIGINAL BISCOCHO HAUS CORP.', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1641, '005-982-855-004', 'ORIGINAL BISCOCHO HAUS CORP.', 'VAT', 'FESTIVE WALK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1642, '005-982-855-019', 'ORIGINAL BISCOCHO HAUS CORPORATION', 'VAT', 'MALI-AO PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1643, '005-982-855-021', 'ORIGINAL BISCOCHO ORIG. BISCOCHO HAUS CORP.', 'VAT', 'MALIAO PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1644, '187-122-932-002', 'ORIGINAL LAPAZ BATCHOY', 'VAT', 'SM CITY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1645, '102-264-619-000', 'OTANT RAZAR', 'VAT', 'BASA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1646, '629-316-434-000', 'P&J FOODS CORPORATION', 'VAT', 'Q. ABETO STREET MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1647, '431-579-437-000', 'P. L. A. R. K. CORP.', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1648, '010-140-307-001', 'P. LUNCH BAY AREA INC.', 'VAT', 'AYALA MALLS MANILA BAY DIOSDADO MACAPAGAL BOULEVARD BRGY. TAMBO ENTERTAINMENT CITY PARAÑAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1649, '906-723-533-000', 'P.L. ALEJADO GLASS AND ALUMINUM', 'VAT', '422 E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1650, '143-263-790-001', 'PA A PAINT MERCHANDISING', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1651, '008-517-258-003', 'PA LANANG FOOD VENTURES, INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVENUE, BRGY. BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1652, '008-235-541-001', 'PA NORTH EDSA FOOD VENTURES INC.', 'VAT', 'GREENHILLS EAST MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1653, '456-448-927-004', 'PACIFIC SEVEN FOOD SERVICES INC.', 'VAT', 'AYALA TECHNOHUB BLDG. BRGY. SANRAFAEL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1654, '456-448-927-000', 'PACIFIC SEVEN FOOD SERVICES INC.', 'VAT', 'AYALA TECHNOHUB BLDG. BRGY. SANRAFAEL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1655, '000-258-476-00042', 'PACIFICA AGRIVET SUPPLIES INC.', 'VAT', 'VALERIA ST., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1656, '000-258-476-000-236', 'PACIFICA AGRIVET SUPPLIES, INC', 'NV', 'SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1657, '238-941-466-000', 'PACIFICA-BANDINI CORP.', 'VAT', '5012 P. BURGOS ST., POBLACION,1210 CITY OF PASIG', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1658, '227-128-679-008', 'PAGES HOLDINGS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1659, '444-497-137-000', 'PAINT TRADE CENTER', 'VAT', '#85,RIZAL ST.,RIMA-RIZAL 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1660, '476-849-545-000', 'PAINTS & HARDWARE SUPPLY,INC.', 'VAT', 'NIETES AVE.,FUNDA-DALIPE,SAN JOSE,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1661, '010-333-970-000', 'PALAWAN PAY (PPS-PEPP FINANCIAL SERVICES CORPORATION)', 'VAT', 'PUERTO PRINCESA CITY, PALAWAN', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1662, '258-046-851-000', 'PALMARES DRIED FISH', 'NV', 'ILOILO CENTARL MARKET, GUANCO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1663, '000-263-296-004', 'PAMBATO CARGO FORWARDER, INC', 'VAT', 'COR. SAGASA, STO, ROSARIO STS. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1664, '203-120-687-184', 'PAN DE MANILA FOOD CO INC.', 'VAT', 'UNIT RETAIL 1 BPO BLDG C ILOILO BUSINESS PARK AIRPORT ROAD MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1665, '203-120-687-0140', 'PAN DE MANILA FOOD CO INC.', 'VAT', 'L1-112 ROBINSONS PLACE ILOILO QUEZON-DE LEON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1666, '296-040-170-002', 'PANABOR RESTAURANT', 'VAT', 'GROUND FLR LEVE A1 FESTIVE WALK MALL ABETO MIRASOL TAFT SOUTH QUIRINO ABETO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1667, '257-842-866-000', 'PANAY AGRO CENTER CORPORATION', 'VAT', 'MABINI-DE LEON STS ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1668, '005-573-275-006', 'PANAY ANDOKS CORPORATION', 'VAT', 'ROXAS AVENUE, KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1670, '005-573-275-008', 'PANAY ANDOKS CORPORATION', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1671, '005-573-275-042', 'PANAY ANDOKS CORPORATION', 'VAT', 'LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1672, '005-573-275-136', 'PANAY ANDOKS CORPORATION', 'VAT', 'LUNA ST. SAN NICOLAS LAPAS ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1673, '005-573-275-100', 'PANAY ANDOKS CORPORATION', 'VAT', 'ROXAS AVENUE, KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1674, '005-573-275-00008', 'PANAY ANDOK\'S CORPORATION', 'VAT', '90 COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1675, '005-573-275-012', 'PANAY ANDOK\'S CORPORATION', 'VAT', 'LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1676, '005-573-275-014', 'PANAY ANDOK\'S CORPORATION', 'VAT', '90 COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1677, '005-573-275-00136', 'PANAY ANDOK\'S CORPORATION', 'VAT', '90 COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1678, '005-573-275-118', 'PANAY ANDOK\'S CORPORATION', 'VAT', '90 COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1679, '005-573-275-00102', 'PANAY ANDOK\'S CORPORATION', 'VAT', 'ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1680, '005-573-275-00134', 'PANAY ANDOK\'S CORPORATION', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1681, '001-002-833-000', 'PANAY ELECTRIC COMPANY, INC.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1682, '410-365-584-000', 'PANAY MEDICAL VENTURES, INC.', 'VAT', 'ATRIA, DONATO PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1683, '149-444-604-002', 'PANAY PETROLEUM TRADING', 'VAT', 'MABINI STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1684, '008-548-560-002', 'PANAY REFRIGERATION&AIRCONDITIONING SUPPLIES', 'VAT', 'E.LOPEZ STREET, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1685, '000-059-364-00011', 'PAN-EURASIA SALES MARKETING COPORATION', 'VAT', 'SAN ISIDRO 1930, ANGONO RIZAL', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1686, '004-863-478-000', 'PANORAMA PRINTING, INC.', 'VAT', 'COR. SIMON LEDESMA-LOPEZ JAENA STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1687, '009-603-272-000', 'PAPERMINTS, INC.', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:05', '2026-07-13 03:05:05'),
(1688, '938-037-288-002', 'PAREKOY RESTO BAR', 'VAT', 'BRGY.SO OC AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1689, '212-836-941-002', 'PASAY CENTENNIAL REST. INC.', 'VAT', 'NAIA CENTENNIAL BLDG. PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1690, '442-480-438-000', 'PAUL CYCLE PARTS', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1691, '446-237-640-000', 'PCSC GASLINE CENTER', 'VAT', 'TIMAWA COR. LOPEZ JAENA STS., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1692, '006-262-887-005', 'PD 888 AUTO CARE', 'VAT', 'BRGY.BANAGO CITY, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1693, '123-720-611', 'PEACE GASOLINE STATION', 'VAT', 'NEW LUCENA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1694, '906-501-193-00013', 'PECHO PAK CHICKEN HOUSE', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1695, '259-944-088-001', 'PEDRO BULALOHAN', 'VAT', 'TAFT NORTH DIVERSION ROAD, BRGY. BAKHAW, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1696, '772-268-705-000', 'PEDRO BULALOHAN BAR AND RESTAURANT INC.', 'VAT', 'PASEO DE ARCANGELES,DIVERSION ROAD,BOLILAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1697, '102-274-767-000', 'PELAMER PETRON SERVICE CENTER', 'VAT', 'RIZAL ST., LAPAZ', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1698, '000-068-427-218', 'PENSHOPPE (GOLDEN ABC, INC.)', 'VAT', 'SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1699, '000-068-427-774', 'PENSHOPPE ACCESSORIES', 'VAT', 'UNIT 1012 SM CITY BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1700, '000-168-541-072', 'PEPSI- COLA PRODUCTS PHILIPPINES, INC.', 'VAT', 'BRGY. SAN JOSE, SAN MIGUEL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1702, '0314-116-00311', 'PEPSI-COLA PRODUCTS PHILLIPINES INC', 'VAT', 'BRGY.SAN JOSE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1703, '009-244-060-000', 'PEPTARSUS CORP.', 'VAT', '72 C ESTEBAN ABAD ST.,QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1704, '005-009-744-176', 'PERF RESTAURANT INC', 'VAT', 'UPPER GROUND FLOOR SM CITY ILOILO BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1705, '005-009-744-013', 'PERF RESTAURANTS INC BURGER KING', 'VAT', 'SANTA TERESITA QUEZON CITY NCR, SECOND DISTRICT', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1706, '005-009-744-00176', 'PERF RESTAURANTS INC BURGER KING', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1707, '005-009-744-102', 'PERF RESTAURANTS INC.', 'VAT', 'BOLILAO MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1708, '005-009-744-026', 'PERF RESTAURANTS, INC.', 'VAT', 'BALAGTAS, BULACAN', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1709, '181-014-934-000', 'PEROLS MART', 'VAT', 'JAVELLANA EXT SAN PEDRO JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1710, '005-975-643-00006', 'PEST SCIENCE CORPORATION', 'VAT', 'PHASE 2 HACIENDA FE SUBD., SAMBAG ZARRAGA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1711, '05-975-643-00006', 'PEST SCIENCE CORPORATION', 'VAT', 'SAMBAG, ZARRAGA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1712, '005-975-643-006', 'PEST SCIENCE CORPORATION', 'VAT', 'PHASE 2 HACIENDA FE SUBD, SAMBAG ZARRAGA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1713, '415-735-994-000', 'PESTIFIED CORPORATION', 'VAT', 'DR 1 CHENG WAREHOUSE LEDESMA ST., BRGY. LIBERATION, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1714, '006-127-040-000', 'PETROL TRADERS CORPORATION', 'VAT', 'COR. J.M. BASA-ORTIZ STS., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1715, '496-241-859-001', 'PETROROMA INC.', 'VAT', 'Mc ARTHUR HIGHWAY, GRAN PLAINS SUBD. M.V. HECHANOVA, JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1716, '496-241-859-0000', 'PETROROMA INC.', 'VAT', 'COR. GEN. GRAN PLAINS SUBD. MC ARTHUR HIGHWAY M.V. HECHANOVA JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1717, '010-133-971-276', 'PH GLOBAL JET EXPRESS INC', 'VAT', 'JARDELEZA BLDG. COMM CIVIL ST., BRGY.LUNA JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(1718, '000-597-645-015', 'PHILIPPINE AIRLINES INC.,', 'VAT', 'BENIGNO AQUINO AVE.,BRGY SAN RAFAEL MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1719, '000-597-645-000', 'PHILIPPINE AIRLINES, INC.', 'VAT', 'PNB FINANCIAL CENTER, PRES. DIOSDADO MACAPAGAL AVE., CCP COMPLEX, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1720, '008-422-714-049', 'PHILIPPINE FAMILY MART CVS, INC', 'VAT', 'G/F BUTTERFLY GARDEN NEWPORT CIRCLE PASAY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1721, '000-665-693-000', 'PHILIPPINE GLOBAL COMMUNICATIONS, INC.', 'VAT', 'PASEO DE ROXAS MAKATI CITY NCR PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1722, '000-805-150-000', 'PHILIPPINE MEDICAL ASSOCIATION INC.', 'VAT', 'NORTH AVENUE,PAG-ASA,QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1723, '000-352-232-00092', 'PHILIPPINE PORT AUTHORITY', 'VAT', 'BRGY. CULASI, ROXAS, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1724, '000-352-232-00090', 'PHILIPPINE PORT AUTHORITY', 'VAT', 'FORT SAN PEDRO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1725, '000-352-232-090', 'PHILIPPINE PORTS AUTHORITY', 'VAT', 'FORT SAN PEDRO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1726, '000-352-232-00091', 'PHILIPPINE PORTS AUTHORITY', 'VAT', '', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1727, '000-352-232-00010', 'PHILIPPINE PORTS AUTHORITY', 'VAT', 'BANAGO, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1728, '000-746-621-1155', 'PHILIPPINE POSTAL CORPORATION', 'VAT', 'BONIFACIO DRIVE PRESIDENT ROXAS ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1729, '000-746-621-01155', 'PHILIPPINE POSTAL CORPORATION', 'VAT', 'BONIFACIO DRIVE PRESIDENT ROXAS ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1730, '000-390-189-02283', 'PHILIPPINE SEBEN CORP.', 'VAT', 'OLD LOPEZ JAENA ST.,BRGY UNGKA I,JARO,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1731, '000-390-189-02429', 'PHILIPPINE SEVEN CORP.', 'VAT', 'ONATE DE LEON ST. COR. R. MAPA ST. DISTRICT OF MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1732, '000-390-189-1574', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1733, '000-390-189-1505', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'POBLACION TAKAS, CUARTERO, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1734, '000-390-189-860', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1735, '000-390-189-1619', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'SUPERMARKET', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1736, '000-390-189-1944', 'PHILIPPINE SEVEN CORPORATION', 'VAT', '92 PLAZA RIZAL ST., JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1737, '000-390-189-1168', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'RIZAL ST., COR. OSMENA ST., POBLACION, CALINOG, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1738, '000-390-189-1502', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'SAMPAGUITA ST., BO OBRERO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1739, '000-390-189-1266', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1740, '000-390-189-02032', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1741, '000-390-189-256', 'PHILIPPINE SEVEN CORPORATION', 'NV', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1742, '000-390-189-1530', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'LOT 3210, BRGY. BAKHAW, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1743, '000-390-189-1997', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PHASE 3, ALTA TIERRA VILLAGE, BRGY. QUINTIN SALAS, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1744, '000-390-189-1841', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1745, '000-390-189-1764', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'BRGY.TAGBAC, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1746, '000-390-189-02590', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1747, '000-390-189-01761', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1748, '000-390-189-02255', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1749, '000-390-189-1515', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1750, '000-390-189-2284', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1751, '000-390-189-851', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1752, '000-390-189-2203', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'LOPEZ JAENA ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1753, '000-390-189-2645', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'MANOC MANOC MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1754, '000-390-189-2278', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'CITY MALL BORAKAY MALAY AKLAN PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1755, '000-390-189-1108', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'GOV. FULLON ST. SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1756, '000-390-189-2095', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1757, '000-390-189-2000', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1758, '000-390-189-1522', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1759, '000-390-189-1695', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1760, '000-390-189-01108', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'GOV. FULLON ST., SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1761, '000-390-189-01517', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'UP TOWN CENTER KATIPUNAN AVENUE UP CAMPUS QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1762, '000-390-189-1760', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'NATIONAL HIGHWAY PAVIA ILOILO PHILIPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1763, '000-390-189-02284', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1764, '000-390-189-02222', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PASEO PARKVIEW TOWER 142 VALERO COR SEDENO ST. SALCEDO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1765, '000-390-189-03187', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'VILLA AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1766, '000-390-189-02730', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'LOPEZ JAENA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1767, '000-390-189-02000', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'BURGOS STREET, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1768, '000-390-189-02046', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'PUTATAN, MUNTINLUPA, PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1769, '000-390-189-02177', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'LEGASPI ST., ROXAS CITY CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1770, '000-390-189-1059', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'POBLACION BANATE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1771, '000-390-189-000', 'PHILIPPINE SEVEN CORPORATION- SIGMA', 'VAT', 'POBLACION SUR, NATIONAL ROAD, SIGMA, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1772, '000-597-645-043', 'PHILIPPINES AIRLINES, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1773, '000-338-830-082', 'PHOTOLINE ENTERPRISES CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1774, '000-338-830-036', 'PHOTOLINE ENTERPRISES CORP.', 'VAT', 'MABINI STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1775, '000-338-830-002', 'PHOTOLINE ENTERPRISES CORPORATION', 'VAT', 'LVL 1 ROBINSONS PLACE ERMITA M. ADRIATICO COR. PEDRO GILAND FAURA ST.', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1776, '947-224-994-000', 'PICH CYBER INTERNET CAFÉ', 'NV', 'COMMISSION CIVIL ST., JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1777, '365-687-848-000', 'PICK UP HOUSEHOLD GOODS TRADING', 'VAT', 'IZNART ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1778, '933-605-220-001', 'PICK UP SHOP TRADING (LOLITA SUN TAN)', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1779, '000-315-620-030', 'PICTURE CITY INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE.MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1780, '000-315-620-106', 'PICTURE CITY INTERNATIONAL INC.', 'VAT', 'BENIGNO AQUINO AVE.MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1781, '000-315-620-029', 'PICTURE CITY INTERNATIONAL, INC.', 'VAT', 'ROBINSONS\'S PLACE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1782, '923-409-542-000', 'PIDO VEGETABLE DEALER', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1783, '005-582-774-00012', 'PILIPINO CABLE CORPORATION', 'VAT', 'ABS-CBN BROADCAST COMPLEX, LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1784, '102-268-662-002', 'PIONEER UPHOLSTERY SUPPLY-BRANCH', 'VAT', 'E.LOPEZ STREET, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1785, '927-408-091-000', 'PIROTE COPY CENTER AND COMPUTER TYPING AND PRINTING', 'NV', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1786, '429-209-472-000', 'PITSTOP FOODS UNI CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1787, '492-209-472-000', 'PITSTOP FOODS UNI CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1788, '200-741-954-612', 'PIZZA HUT-PPI HOLDINGS INC', 'VAT', 'FESTIVE WALK MALK-MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1789, '477-450-440-000', 'PIZZA, PIZZA INC', 'VAT', 'BOARDWALK DIVERSION ROAD MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1790, '434-181-790-001', 'PJ MEATSHOP', 'NV', 'ILOILO TERMINAL MARKET, BRGY. FLORES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1791, '204-248-466-398', 'PLANET SPORTS INC.', 'VAT', 'GROUND FLOOR FESTIVE WALK MALL MEGAWORLD BLVD MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1792, '006-230-953-000', 'PLATINUM CORPORATION', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1793, '414-520-850-004', 'PLAY TELECOM CENTER', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1794, '425-009-224-000', 'PLAZA JARO DELICACIES', 'VAT', 'DEMOCRACIA ST.,JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1795, '006-909-073-100', 'PLDT INC.', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1796, '000-488-793-000', 'PLDT INC.', 'VAT', 'RAMON COJUANGCO BLDG, MAKATI AVE., MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1797, '010-026-709-00037', 'PLK PHILIPPINES INC.', 'VAT', 'G/F MARKET MARKET MALL MCKINLEY PARKWAY BONIFACIO GLOBAL CITY, TAGUIG', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1798, '010-026-709-00043', 'PLK PHILIPPINES INC.', 'VAT', 'LOT 2 BLOCK 139 QUEZON AVE. CORNER TIMOG AVE., SOUTH TRIANGLE, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1799, '914-793-528-000', 'P-N PETRON GASOLINE STATION', 'VAT', 'UNGKA II PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1800, '009-263-851-006', 'PODEROSO FOOD HOUSE CO. LTD.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1801, '487-713-788-000', 'PONYONG AND DIDING VEGETABLES STORE', 'NV', 'ILOILO TERMINAL MARKET, MABINI ST., BRGY. FLORES,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1802, '107-123-236-001', 'POTOTAN FUEL PITSTOP', 'VAT', 'RUMBANG POTOTAN,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1803, '932-985-212-000', 'POULTRICH DRESSED CHICKEN', 'VAT', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1804, '004-451-692-000', 'POWER MAC CENTER, INC.', 'VAT', '4/F CYBERZONE, SM MEGAMALL, BLDG. B EDSA, WACK-WACK GREENHILLS, MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1805, '200-740-954-645', 'PPI HOLDINGS INC', 'VAT', 'GF UNIT 1084SM CITY ILOILO,BENIGNO AQUINO AVE. MANDURRIAO, ILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1806, '200-741-954-645', 'PPI HOLDING\'S INC.,', 'VAT', 'GF UNIT 1084SM CITY ILOILO,BENIGNO AQUINO AVE. MANDURRIAO, ILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1807, '200-741-954-00137', 'PPI HOLDINGS, INC.', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1808, '200-741-954-845', 'PPI HOLDINGS, INC.', 'VAT', 'DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1809, '489-443-867-000', 'PRANCETECH INDUSTRIAL SUPPLY AND SERVICES', 'VAT', 'PANDAC PAVIA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1810, '201-888-636-000', 'PRESTIGE HOTELS & RESORTS INC', 'VAT', 'MEGAWORLD AVENUE ILOILO BUSINESS PARK, ILOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1811, '008-122-488-010', 'PRESTINE PACIFIC FOOD MANAGEMENT CORP.', 'VAT', 'G/F SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1812, '008-122-488-009', 'PRESTINE PACIFIC FOOD MANAGEMENT CORP.', 'VAT', 'B.AQUINO AVE.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1813, '424-829-297-000', 'PRETTYPIX DESIGNS AND PRINTS', 'NV', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1814, '932-444-676-000', 'PRINCE BAKER', 'VAT', 'MEGAWORLD AVENUE ILOILO BUSINESS PARK, ILOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1815, '005-983-899-00081', 'PRINCE BAKER', 'VAT', 'MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1816, '005-983-899-081', 'PRINCE BAKER', 'VAT', 'MEGAWORLD BLVD. MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1817, '006-125-985-007', 'PRINCE WORLD CORPORATION', 'VAT', 'BRGY. Q ABETO ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1818, '445-348-464-002', 'PRINCEPAN CORP.', 'VAT', 'POBLACION POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1819, '445-348-464-003', 'PRINCEPAN CORPORATION', 'VAT', 'LIBOT CALINOGILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1820, '445-348-646-002', 'PRINCEPAN CORPORATION', 'VAT', 'LIBOT CALINOGILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1821, '000-340-906-076', 'PRINCESS MARCELLA ACCESSORIES, INC.', 'VAT', 'B. AQUINO AVENUE MANDURRIAO BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1822, '134-362-819-003', 'PRINCESS QUENNIE NATIVE PRODUCTS', 'VAT', 'ILOILO CENTRAL MARKET', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1823, '008-134-031-073', 'PRINCESS SAPPHIRE CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1824, '006-125-985-001', 'PRINCEWORLD CORPORATION', 'VAT', 'CR. LOCSIN AVANCEÑA ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1825, '006-125-985-027', 'PRINCEWORLD CORPORATION', 'VAT', 'JALANDNI ST., BRGY. POBLACION, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1826, '006-125-985-005', 'PRINCEWORLD CORPORATION', 'VAT', 'WEST TIMAWA MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1827, '006-125-985-032', 'PRINCEWORLD CORPORATION', 'VAT', 'CR. LOCSIN AVANCEÑA ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1828, '777-215-363-000', 'PROJECT HEALTHYKITCHEN OPC', 'NV', '2ND FLOOR SM CITY FOODHALL,BENIGNO AQUINO AVENUE, BRGY BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1829, '909-568-803-000', 'PROJECT INK DESIGN PRINTING SERVICES', 'NV', 'BARANGAY BUENAFLOR-EMBARCADERO, DUMANGAS,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1830, '005-287-369-040', 'PROMARK INDUSTRIES INC.', 'VAT', 'SM CITY ILOILO MANDURRIAO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1831, '010-131-564-000', 'PROP. HAPPY BAKE DAY INC.', 'VAT', 'GT MALL, UNGKA PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1832, '183-775-574-000', 'PROP.LAMBERT DIVINA GRACIA', 'VAT', 'VALERIA ST.ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1833, '412-622-286-003', 'PUB EXPRESS', 'VAT', 'SM CITY, MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1834, '412-622-286-002', 'PUB EXPRESS RESTO', 'VAT', 'MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1835, '201-277-095-029', 'PUREGOLD PRICE CLUB INC', 'VAT', 'FELIX AVE SAN ISIDRO CAINTA RIZAL', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1836, '201-277-095-125', 'PUREGOLD PRICE CLUB INC', 'VAT', 'OLIVAREZ PLAZA, AGUINALDO HIGHWAY TAGAYTAY CITY CAVITE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1837, '201-277-095-044', 'PUREGOLD PRICE CLUB INC.', 'VAT', 'TANDANG SORA CORNER QUIRINO 2-A NCR SECOND DISTRICT QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1838, '201-277-095-316', 'PUREGOLD PRICE CLUB, INC', 'VAT', 'CUARTERO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1839, '201-277-095-00361', 'PUREGOLD PRICE CLUB, INC.', 'VAT', 'MUNTINLUPA CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1840, '201-277-095-00056', 'PUREGOLD PRICE CLUB, INC.', 'VAT', 'GEN LUNA ST., BRGY. TUKTUKAN, TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1841, '201-277-095-00012', 'PUREGOLD PRICE CLUB, INC.', 'VAT', 'NCR, SECOND DISTRICT QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1842, '201-277-095-393', 'PUREGOLD PRICE CLUB,INC.', 'VAT', 'DALAN EL 98 ROAD NEAR CORNER C CUARTERO ILOILO CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1843, '201-277-095-00312', 'PUREGOLD PRICE CLUB,INC.', 'VAT', 'DALAN EL 98 ROAD NEAR CORNER C CUARTERO ILOILO CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1844, '416-091-320-002', 'PUREWOOD HOME BUILDERS, INC.', 'VAT', 'BRGY. SOUTH FUNDIDOR, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1845, '416-091-320-000', 'PUREWOOD HOME BUILDERS,INC', 'VAT', 'GUZMAN-JESENA STREET,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1846, '010-088-010-003', 'PURPLE HAZE PRINTING PRESS', 'VAT', 'BRGY. SOUTH BALUARTE, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1847, '008-006-530-002', 'PURPLE PADTHAI RESTAURANT CO.', 'VAT', 'BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1848, '005-865-365-006', 'QOUTED CAFE', 'VAT', 'OTEK CITY OF BAGUIO BENGUET', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1849, '009-756-063-001', 'QUALITY PETRO MATES , INC.', 'VAT', 'DONATO P. AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1850, '907-752-976-000', 'QUEZON PAINT\'S & CONSTRUCTION SUPPLY', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1851, '117-012-639-000', 'QUICK FILL GAS STATION', 'VAT', 'SAMBAG JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1852, '005-982-291-452', 'QUIX CART', 'VAT', 'POBLACION, LEGANES,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1853, '000-060-348-024', 'QUOROM INTERNATIONAL INC.,', 'VAT', 'LOWER G/L SM CITY ILOILO BENIGO AQUINO AVE.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1854, '000-008-348-024', 'QUORUM INT\'L INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1855, '921-033-138-000', 'R AND L AIRCON REPAIR SHOP', 'NV', 'BRGY.BUHANG TAFT NORTH MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1856, '769-308-239-000', 'R&A DIGITAL PRINTING SERVICES', 'NV', 'ROOM 219, 2ND FLOOR CALLE REAL BUSINESS CENTER, JM BASA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1857, '933-867-648-001', 'RACT FOOD SERVICE RESTAURANT', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1858, '933-867-968-001', 'RACT FOOD SERVICE RESTAURANT', 'VAT', 'FESTIVE WALK MALL ILOILO BUSINESS PARK ABETO MIRASOL TAFT SOUTH MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1859, '003-058-789-139', 'RADISSON ILOILO', 'VAT', 'BUHANG MANDURRIAO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1860, '311-362-711-000', 'RAINCEL PIZZA PARLOR', 'NV', '1688 CORNER QUEZON LEDESMA ST 5000 ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1861, '203-711-172-009', 'RAKSO AIR TRAVEL & TOURS INC.', 'VAT', 'CITY TIME SQUARE,ILOILO CENTER,SEN.BENIGNO S. AQUINO AVE.', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1862, '191-741-340-000', 'RAMBOY\'S RESTAURANT', 'VAT', 'BULWANG NUMANCIA, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1863, '191-741-340-016', 'RAMBOY\'S LECHON AND RESTAURANT', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1864, '191-741-340-010', 'RAMBOY\'S LECHON AND RESTAURANT', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1865, '191-741-340-008', 'RAMBOY\'S LECHON AND RESTAURANT', 'VAT', 'R.BWALK SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1866, '455-627-544-000', 'RAMBOY\'S LECHON AND RESTAURANT', 'VAT', 'RIVERSIDE BOARDWALK BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1867, '102-267-306-000', 'RAN-RAN STORE', 'NV', 'ILOILO CENTRAL MARKET', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1868, '117-302-094-000', 'RB PETROLEUM ENTERPRISES', 'VAT', 'TAFT NORTH MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1869, '239-174-923-246', 'RDF FEED, LIVESTOCK & FOODS, INC.', 'VAT', 'BRGY STA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1870, '005-570-207-000', 'RED COCONUT BEACH HOTEL CORPORATION', 'VAT', 'BALABAG, BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1871, '270-307-970-000', 'RED DOOR RESTAURANT', 'VAT', 'DIVERSION ROAD, CUARTERO JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1872, '629-446-442-000', 'RED HEN CHINESE RESTAURANT', 'VAT', 'ROBINSONS MALL, SAN ANGEL, SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1873, '003-294-772-00217', 'RED RIBBON BAKESHOP INC', 'VAT', 'ROBINSONS PLACE ILOILO, LEDESMA COR.MABINI ST.', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1874, '003-294-772-044', 'RED RIBBON BAKESHOP MINC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1875, '426-026-292-001', 'REGINALD NICO D. MILLANES', 'VAT', '23RD LACSON ST., BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1876, '010-375-092-026', 'RELYT SOLUTIONS INC.', 'VAT', 'SM CITY ILOILO BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1877, '600-476-257-001', 'RESTAURANTE DE POBLACION INC.', 'VAT', 'NO ADDRESS', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1878, '102-266-315', 'RESTRO 21 CAFE', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1879, '009-584-209-000', 'REVLIS\' 7 FOOD CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1880, '935-737-060-000', 'REY CHARLES B. LIM', 'NV', 'EL 98 ST.,TAYTAY ZONE 11 JR, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1881, '117-336-414-000', 'REYNALDO B. LASQUITE', 'NV', 'MABINI SUPERMARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1882, '284-167-055-000', 'RHC PCOMPUTER PARTS', 'NV', 'GRAN PLAINS SUBD., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1883, '284-167-055-00', 'RHC PCOMPUTER PARTS', 'NV', 'ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1884, '008-171-210-083', 'RHD DAISO-SAIZEN, INC', 'VAT', 'LEVEL 2 RP-PAVIA F.EUGENIO ST., UNGKA 11 PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1885, '008-171-210-00085', 'RHD DAISO-SAIZEN, INC', 'VAT', 'LEVEL 2 RP-PAVIA F.EUGENIO ST., UNGKA 11 PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1886, '008-171-210-056', 'RHD DAISO-SAIZEN, INC.', 'VAT', '2ND FLOOR C-276-B U.P TOWN CENTER KATIPUNAN AVE. QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1887, '452-109-484-013', 'RHI BUILDERS AND CONTRACTORS DEPOT CORP.', 'VAT', 'BRGY. MABINI LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1888, '452-109-484-011', 'RHI BUILDERS AND CONTRACTORS DEPOT CORP.-ROBINSONS BUILDERS ANTIQUE', 'VAT', 'REP.E.NIETESST.,FUNDADALIPESANJOSE DE BUENAVISTA,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1889, '452-109-484-014', 'RHI BUILDERS AND CONTRACTORS DEPOT CORP.-ROBINSONS BUILDERS TANZA', 'VAT', 'LOPEZ JAENA CORNER WEST AVENUE TANZA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1890, '482-862-212-011', 'RIBSHACK GRILL CORPORATION', 'VAT', 'ROBINSONS PLACE ILOILO DE LEON & QUEZON STS. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1891, '482-862-212-00019', 'RIBSHACK GRILL CORPORATION', 'VAT', 'FESTIVE WALK MALL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1892, '482-862-212-00035', 'RIBSHACK GRILL CORPORATION', 'VAT', 'SAN VICENTE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1893, '482-862-212-00011', 'RIBSHACK GRILL CORPORATION', 'VAT', 'ROBINSONS PLACE CITY PROPER, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1894, '201-888-636-002', 'RICHMONDE HOTEL ILOILO', 'VAT', 'MEGAWORLD AVENUE ILOILO BUSINESS PARK, ILOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1895, '281-726-184-010', 'RICHOIL FUEL ENTERPRISE', 'VAT', 'COASTAL ROAD HINACTACAN LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1896, '281-726-184-007', 'RICHOIL FUEL ENTERPRISES', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1897, '281-726-184-008', 'RICHOIL FUEL ENTERPRISES', 'VAT', 'BRGY.BUHANG TAFT NORTH MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1898, '281-726-184-000', 'RICHOIL FUEL ENTERPRISES', 'VAT', 'BRGY.NORTH AVANCENA MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1899, '281-726-184-002', 'RICHOIL FUEL ENTERPRISES', 'VAT', 'STA. ISABEL COR.LOPEZ JAENA STS. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1900, '281-726-184-001', 'RICHOIL FUEL ENTERPRISES- SAN RAFAEL', 'VAT', 'DIVERSION RD. BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1901, '000-996-428-000', 'RICHPORT DIESEL CORPORATION', 'VAT', '24 HIPODROMO ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1902, '921-034-873-060', 'RIENA\'S CUISINE', 'VAT', 'ROBINSONS PLACE , LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1903, '102-275-123-000', 'RIGHTSTOP UPHOLSTERY SUPPLY', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1904, '938-466-706-001', 'RJ ZERRUDO CONVENIENCE STORE', 'VAT', 'E.LOPEZ ST., BRGY. MONTINOLA,JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1905, '945-770-069-000', 'RMJ LUMBER, HARDWARE AND MARKETING', 'NV', '221 UNIT 2, MANFORT BLDG. M. H. DELPILAR ST., MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1906, '219-777-018-015', 'ROBERTO SOLOMON VIRAY', 'VAT', 'ROXAS AVE., BRGY. VIII, ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1907, '126-181-337-000', 'ROBERTOS ILOILO SIOPAO FOOD HOUSE', 'VAT', 'JM BASA ST. MARIA CLARA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1908, '000-996-451-000', 'ROBETRTO\'S HOUSE, INC.', 'VAT', '#61 J. M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1909, '207-540-656-095', 'ROBINSONS APPLIANCE', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1910, '205-728-757-351', 'ROBINSONS CONVENIENCE STORES INC', 'VAT', 'ILOILO DE LEON COR. QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1911, '007-079-853-059', 'ROBINSONS DAISO DIVERSIFIED CORP.', 'VAT', 'ROBINSON\'S PLACE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1912, '007-079-853-019', 'ROBINSONS DAISO DIVERSIFIED CORP.', 'VAT', 'ROBINSONS PLACE JARO,E. LOPEZ ST. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1913, '007-079-853-00079', 'ROBINSONS DAISO DIVERSIFIED CORP.', 'VAT', 'LEVEL 2 ROBINSONS PLACE PAVIA, UNGKA 11, PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1914, '007-079-853-042', 'ROBINSONS DAISO DIVERSIFIEDCORP.', 'VAT', 'MAYBATO SAN JOSE DE BUENAVISTA, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1915, '000-405-340-252', 'ROBINSONS DEPARTMENT STORE-PAVIA', 'VAT', 'RDS, VP. F LOPEZ AVE., RP PAVIA, UNGKA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1916, '003-888-229-132', 'ROBINSONS HANDYMAN INC.', 'VAT', 'JARO SAN VICENTE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1917, '003-888-229-085', 'ROBINSONS HANDYMAN INC.', 'VAT', 'MH DEL PILAR ST, MOLO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1918, '003-888-229-033', 'ROBINSON\'S HANDYMAN INC.', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1919, '003-888-229-037', 'ROBINSON\'S HANDYMAN INC.', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1920, '003-888-229-201', 'ROBINSON\'S HANDYMAN INC.', 'VAT', 'VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1921, '003-888-229-034', 'ROBINSON\'S HANDYMAN INC.- ROBINSON', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1922, '000-345-273-024', 'ROBINSONS INCORPORATED', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1923, '000-345-273-053', 'ROBINSON\'S INCORPORATED', 'VAT', 'LOPEZ ST., SAN VICENTE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1924, '000-361-376-0000', 'ROBINSONS LAND CORPORATION', 'VAT', 'ORTIGAS AVE., QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1925, '000-405-340-018', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1926, '000-405-340-177', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1927, '000-405-340-395', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'VALERIA EXT., ST., BRGY. NONOY ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1928, '000-405-340-218', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1929, '000-405-340-412', 'ROBINSONS SUPERMARKET CORPORATION', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1930, '000-405-340-086', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1931, '000-405-340-151', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1932, '000-405-340-00151', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1933, '000-405-340-124', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1934, '000-405-340-202', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1935, '000-405-340-163', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:06', '2026-07-13 03:05:06'),
(1936, '000-405-340-871', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'GT PLAZA MALL, M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1937, '000-405-340-020', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'ROBINSONS GALLERIA ORTIGAS AVENUE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1938, '000-405-340-000', 'ROBINSON\'S SUPERMARKET CORPORATION', 'VAT', 'BAGUMBAYAN, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1939, '000-405-340-245', 'ROBINSONS SUPERMARKET CORPORATION-JARO', 'VAT', 'E.LOPEZ ST., JARO SAN VICENTE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1940, '000-405-340-025', 'ROBINSONS SUPERMARKET CORPORATION-JARO', 'VAT', 'E-LOPEZ ST., JARO SAN VICENTE , ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1941, '000-405-340-414', 'ROBNSONS SUPERMARKET CORPORATION', 'VAT', 'THE MARKETPLACE FESTIVE WALK BUHANG TAFT NORTH ILOLO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1942, '003-051-268-00000', 'ROGERS TRADING INC', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1943, '003-051-268-000', 'ROGER\'S TRADING INC', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1944, '003-051-268-001', 'ROGER\'S TRADING, INC.', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1945, '008-810-127-009', 'ROKA FOODS CORP.', 'VAT', 'CATICLAN MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1946, '222-755-533-003', 'ROLLWAY REALTY DEVELOPMENT CORPORATION', 'VAT', 'PINAUNGON BALABAG, BORACAY, MALAY, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1947, 'ROMA', 'ROMAGEL TAJONERA', 'NV', 'X', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1948, '610-242-119-000', 'ROMGIN CORPORATION', 'VAT', 'RGM BLDS. ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1949, '933-867-648-000', 'RONI S. TAN - PROP.', 'VAT', '109 LEDESMA ST. SAN JOSE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1950, '007-432-979-0006', 'ROSA FIORE HOUSE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1951, '000-251-244-004', 'ROSALIE S. TREÑAS CORP.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1952, '000-310-457-077', 'ROSE PHARMACY INC', 'VAT', 'ILOILO SUPERMART MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1953, '000-310-457-316', 'ROSE PHARMACY INC', 'VAT', 'ILOILO SUPERMART MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1954, '000-310-457-181', 'ROSE PHARMACY INC.', 'VAT', 'E. LOPEZ JAVELLANA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1955, '000-310-457-450', 'ROSE PHARMACY INC.', 'VAT', 'E. LOPEZ JAVELLANA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1956, '000-310-457-410', 'ROSE PHARMACY INC.', 'VAT', 'E. LOPEZ JAVELLANA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1957, '000-310-457-104', 'ROSE PHARMACY, INC.', 'VAT', 'E. LOPEZ & JAVELLANA STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1958, '000-310-457-065', 'ROSE PHARMACY, INC.', 'VAT', '100 COMMISSION CIVIL ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1959, '000-310-457-186', 'ROSE PHARMACY, INC.', 'VAT', 'PLAZUELA DE ILOILO, AQUINO AVENUE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1960, '000-310-457-066', 'ROSE PHARMACY, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1961, '000-310-457-105', 'ROSE PHARMACY, INC.', 'VAT', 'E. LOPEZ & JAVELLANA STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1962, '000-310-457-064', 'ROSE PHARMACY, INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1963, '000-310-457-362', 'ROSE PHARMACY, INC.', 'VAT', 'E. LOPEZ & JAVELLANA STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1964, '000-310-457-317', 'ROSE PHARMACY, INCORPORATED', 'VAT', 'LOPEZ JAENA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1965, '000-310-457-187', 'ROSE PHARMACY, INCORPORATED', 'VAT', 'AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1966, '183-043-929-000', 'ROSEL FISH MEAT AND VEGETABLE', 'NV', 'JIBAO-AN PAVIA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1967, '007-937-565-005', 'ROXACO- VANGUARD HOTEL', 'VAT', 'QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1968, '934-474-444-000', 'ROXAS CITY SOLID MERCHANDISING', 'VAT', '1031 ROXAS AVE. POBLACION IX, ROXAS CITY, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1969, '187-122-932-000', 'ROY AND POPOY\'S BATCHOY FOOD HOUSE', 'NV', 'LEVEL1 SHOPHOUSE, ROBINSONS PLACE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1970, '004-230-793-000', 'ROYAL ILOILO KING CHOW, INC.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1971, '004-217-965-000', 'ROYAL SPRING CORPORATION', 'VAT', 'N. ROLDAN ST., KALIBO, AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1972, '213-618-819-002', 'ROYAL-ASTA FOOD SERVICES CORPORATION', 'VAT', 'COMM. AVE. CORN. LUZON TANDANG SORA, QC', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1973, '290-595-110-000', 'RPJ MULTI- VENTURES INC.', 'VAT', 'PASSI CITY, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1974, '940-753-635-000', 'RRJA COCO LUMBER', 'NV', 'DULONAN, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1975, '271-197-602-000', 'RRR PERSONALIZED TOUCH OF ART AND PHOTO PRINTING', 'NV', 'SM CITY, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1976, '010-089-011-000', 'RSB VENTURES INC.', 'VAT', 'TIMOG AVENUE COR. SGT.ESGUERRA BRGY.SOUTH TRIANGLE QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1977, '005-215-077-325', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1978, '005-215-077-259', 'RUSTAN COFFEE CORP.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1979, '005-215-077-026', 'RUSTAN COFFEE CORP.', 'VAT', 'SEN. BENIGNO AQUINO JR. AVENUE, MANDURRIAO DISTRICT, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1980, '005-215-077-101', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1981, '005-215-077-196', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1982, '005-215-077-174', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1983, '005-215-077-382', 'RUSTAN COFFEE CORP.', 'VAT', 'MEGAWORLD AVENUE ILOILO BUSINESS PARK, ILOLO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1984, '005-215-077-169', 'RUSTAN COFFEE CORP.', 'VAT', '18TH ST.,CORNER LACSON ST.,BRGY.4, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1985, '005-215-077-070', 'RUSTAN COFFEE CORP.', 'VAT', '419 B, SM MEGAMALL BRIDGEWAY, ORTIGAS CENTER', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1986, '005-215-077-127', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1987, '005-215-077-459', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1988, '005-215-077-060', 'RUSTAN COFFEE CORP.', 'VAT', 'STARBUCKS BLDG SILANG CROSSING WEST TAGAYTAY CITY CAVITE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1989, '005-215-077-506', 'RUSTAN COFFEE CORP.', 'VAT', 'ONE TECHNO PLACE, MEGAWORLD BOULEVARD, ILOILO BUSINESS PARK, MANDURRIAO DISTRICT, BRGY. BUHANG TAFT, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1990, '005-215-077-318', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1991, '005-215-077-440', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1992, '005-215-077-239', 'RUSTAN COFFEE CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1993, '005-215-077-281', 'RUSTAN COFFEE CORP.', 'VAT', 'UNIVERSITY OF STO TOMAS LEON MA GUERRERO DRIVE COR QUEZON DRIVE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1994, '005-215-077-204', 'RUSTAN COFFEE CORP.', 'VAT', 'THE SOUTHPOINT ALFRESCO BENIGNO AQUINO AVENUE MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1995, '005-215-077-576', 'RUSTAN COFFEE CORP.', 'VAT', 'DONATO PISON AVENUE AYALA ATRIA PARK DISTRICT, SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1996, '201-160-401', 'RUSTAN SUPERCENTERS INC.', 'VAT', 'FORT BONIFACIO TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1997, '201-160-401-118', 'RUSTAN SUPERCENTERS INC.', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1998, '201-160-401-114', 'RUSTAN SUPERCENTERS INC.', 'NV', 'FORT BONIFACIO TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(1999, '201-160-401-112', 'RUSTAN SUPERCENTERS, INC', 'VAT', 'FESTIVE WALK ANNEX, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(2000, '120-187-312-000', 'RV MARKETING', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2001, '120-187-312-007', 'RV MARKETING', 'VAT', 'QUEZON ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2002, '909-571-846-000', 'RVH CONSTRUCTION SUPPLY', 'VAT', 'MAGBANUA BLDG LANDHEIGHTS SUBD., BALABAGO, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2003, '601-756-229-001', 'RVP SEAFOODS INC.,', 'VAT', 'ATRIA ILOILO, SAN RAFAEL MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2004, '302-814-976-007', 'RX QUEEN PHARMACY', 'VAT', 'JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2005, '008-958-747-020', 'RYOAKI FOODS INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2006, '009-316-981-018', 'S & R PIZZA INC', 'VAT', 'PUREGOLD JARO EL 98 ROAD CORNER CUARTERO JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2007, '601-158-243-000', 'S & T HOUSEWARE TRADING', 'VAT', 'IZNART ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2008, '480-840-687-000', 'S AND F FOOD PACKAGING AND SUPPLIES PACKAGING', 'NV', 'JAVELLANA BUILDING LUNA ST., BRGY. BANTUD LAPAZ, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2009, '009-316-981-034', 'S AND R PIZZA, INC.', 'VAT', 'BRGY. SAMBAG, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2010, '009-316-981-045', 'S&R PIZZA,INC.', 'VAT', 'JARO, E LOPEZ STS., CORNER M. JAIME ST SAN VICENTE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2011, '303-119-949-001', 'SACREDCAMP CONVENIENCE STORE', 'VAT', 'DIVERSION ROAD COR.JALANDONI ST. MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2012, '209-161-308-030', 'SALON DE ROSE, INC', 'VAT', 'DONATO PISON AVE. BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2013, '907-745-491-000', 'SALT GASTRO RESTAURANT', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2014, '921-752-716-000', 'SAMANTHA\'S STEAMBOAT FOOD STATION', 'NV', 'LGF FOODCOURT SM CITY,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2015, '005-917-995-010', 'SAMUEL DRUG CORPORATION', 'VAT', '12 LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2016, '005-917-995-014', 'SAMUEL DRUG CORPORATION', 'VAT', 'LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2017, '005-917-995-008', 'SAMUEL DRUG CORPORATION', 'VAT', 'LEDESMA STREET ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2018, '005-917-995-012', 'SAMUEL DRUG CORPORATION', 'VAT', 'Q.ABETO ST., MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2019, '005-917-995-021', 'SAMUEL DRUG CORPORATION', 'VAT', '12 LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2020, '005-917-995-015', 'SAMUEL DRUG CORPORATION', 'VAT', 'GAISANO ILOILOLUNA ST. LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2021, '005-917-995-027', 'SAMUEL DRUG CORPORATION', 'VAT', '12 LOPEZ JAENA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2022, '005-917-995-025', 'SAMUEL DRUG CORPORATION', 'VAT', 'QUEZON STREET AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2023, '005-917-995-007', 'SAMUEL DRUG CORPORATION-DELGADO', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2024, '169-400-990-003', 'SAMURAI TALABAHAN', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2025, '005-167-193-000', 'SAN ANTONIO MARKETING INC', 'VAT', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2026, '005-164-584-000', 'SAN FRANCISCO AUTO PARTS INC', 'VAT', 'LEDESMA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2027, '006-807-251-039', 'SAN MIGUEL BREWERY INC.', 'VAT', 'BRGY.CONCEPCION, MUELLE LONEY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2028, '000-275-554-087', 'SAN MIGUEL FOODS, INC.', 'VAT', 'SMFI MELILIZA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2029, '000-275-554-000', 'SAN MIGUEL FOODS, INC.', 'VAT', 'SMFI MELILIZA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2030, '005-985-114-001', 'SAN PAOLO ICE PLANT AND COLD STORAGE, INC.', 'VAT', '#39 RIZAL STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2031, '005-985-114-000', 'SAN PAOLO ICE PLANT AND COLD STORAGE, INC.', 'VAT', '#39 RIZAL STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2032, '207-961-175-00014', 'SANFORD MARKETING CORPORATION', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2033, '207-961-175-00015', 'SANFORD MARKETING CORPORATION', 'VAT', 'LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2034, '207-961-175-015', 'SANFORD MARKETING CORPORATION', 'VAT', 'CORNER L98, LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2035, '207-961-174-00015', 'SANFORD MARKETING CORPORATION', 'VAT', 'LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2036, '207-961-175-00157', 'SANFORD MARKETING CORPORATION', 'VAT', 'PAROLA PORT SAN PEDRO ROAD CONCEPCION', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2037, '207-961-175-00210', 'SANFORD MARKETING CORPORATION', 'VAT', 'SM STRATA BENIGNO AQUINO AVENUE MANDURRIAO BOLILAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2038, '207-961-175-00207', 'SANFORD MARKETING CORPORATION', 'VAT', 'SAVEMORE MARKET GT TOWN CENTER UNGKA II', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2039, '207-961-175-229', 'SANFORD MARKETING CORPORATION', 'VAT', 'CORNER L98, LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2040, '207-961-175-00229', 'SANFORD MARKETING CORPORATION', 'VAT', 'CORNER L98, LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2041, '207-961-175-249', 'SANFORD MARKETING CORPORATION', 'VAT', 'CORNER L98, LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2042, '207-961-175-231', 'SANFORD MARKETING CORPORATION', 'VAT', 'SAVEMORE MARKET, SUVIL TOWN CENTER, INANGAYAN SANTA BARBARA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2043, '207-961-175-170', 'SANFORD MARKETING CORPORATION', 'VAT', 'SAVEMORE MARKET CITYMALL TALUBANGI, CITY OF KABANKALAN NEGROS OCCIDENTAL', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2044, '207-981-175-00014', 'SANFORD MARKETING CORPORATION- QUINTIN SALAS', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2045, '207-961-175-00206', 'SANFORD MARKETING CORPORATION(FESTIVE MARKET)', 'VAT', 'TAFT NORTH DIVERSION ROAD, BRGY. BAKHAW, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2046, '207-961-175-00011', 'SANFORD MARKTING CORPORATION', 'VAT', 'JARO TOWN SQUARE CORNR TACAS RD. QUINTIN SALAS, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2047, '207-961-175-00223', 'SANFORD MARKTING CORPORATION', 'VAT', 'SAVEMORE MARKET LIBOT CALINOG ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2048, '004-640-600-003', 'SANITARY CARE PRODUCTS ASIA, INC.', 'VAT', 'LAPUZ NORTE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2049, '004-640-600-00003', 'SANITARY CARE PRODUCTS ASIA, INC.', 'VAT', 'LAPUZ NORTE, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2050, '774-702-631-000', 'SANTOLAN GAS STATION INC.', 'VAT', '532 ORTIGAS AVE. COR SANTOLAN ST GREENHILLS SAN JUAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2051, '005-443-054-00003', 'SANTOS PETROLEUM CORPORATION', 'VAT', 'JIBOLO, JANIUAY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2052, '005-983-899-00082', 'SARI-SARI BREAD NETWORK INT\'L CORP.', 'VAT', 'LGF SM CITY ILOILO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2053, '007-030-904-001', 'SATURN TEMOCHI, INC.', 'VAT', 'CALOOCAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2054, '933-592-606-002', 'SAU LING C. TIU', 'VAT', 'SEMION AGUILAR ST., PASSI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2055, '006-727-988-081', 'SAVORY FASTFOOD INC.', 'VAT', '2ND FLOOR SM CITY ILOILO BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2056, '006-727-988-068', 'SAVORY FASTFOOD, INC.', 'VAT', 'ROBINSONS\'S PLACE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2057, '101-890-038-000', 'SB VARIETY STORE', 'NV', '161 FUENTES ST, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2058, '005-823-334-012', 'SBF RETAIL, INC.', 'VAT', '2/F NORTH POINT SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2059, '208-926-430-052', 'SCDI CALTEX MANDURRIAO', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2060, '009-669-413-00111', 'SCOTTLAND FOOD GROUP', 'VAT', '102 VALERI ST. SALCEDO VILLAGE BEL-AIR MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2061, '001-419-241-027', 'SCOTTLAND INC.', 'VAT', 'SM MANILA, MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2062, '008-324-406-028', 'SEADWELLER CORP', 'VAT', 'E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2063, '008-324-406-048', 'SEADWELLER CORP.', 'VAT', 'F.LOPEZ AVENUE BRGY. UNGKA 11 ROBINSON', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2064, '008-324-406-029', 'SEADWELLER CORP.', 'VAT', 'E.LOPEZ ST. ROBINSONS JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2065, '808-324-406-043', 'SEADWLLERCORP.', 'VAT', 'LEVEL1 ROBINSONS PLACE PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2066, '203-872-880-044', 'SEATTLE\'S BEST COFFEE', 'VAT', 'COFFEE MASTERS INC NO 3 Q-PAVILLION UST', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2067, '454-922-193-00003', 'SEB AND MEL GROUP INC.', '', 'UNGKA 1 PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2068, '606-232-564-001', 'SECRET TABLE CO. INC.', 'VAT', '4TH FLR., SM AURA, 26TH ST COR MCKINLEY PARKWAY BGC TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2069, '454-922-193-002', 'SEN & MEL GROUP INC.', 'VAT', 'LOPEZ JAENA ST. TIMAWA TANZA,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2070, '454-922-193-005', 'SEN+MEL GROUP INC.', 'VAT', 'REPRESENTATIVE NIETES ST. FUNDA-DALIPE,SAN JOSE(CAPITAL)SAN JOSE DE BUENAVISTA,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2071, '102-266-348-000', 'SENOR HARDWARE', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2072, '936-435-606-007', 'SENTAK MARKETING', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2073, '936-435-606-000', 'SENTAK MARKETING', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2074, '769-418-420-001', 'SEOUL MATE HOME KITCHEN CO. LTD.', 'VAT', 'G/F A-37-2B FESTIVE WALK PARADE ILOILO BUSINESS PARK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2075, '936-803-249-000', 'SERLED ELECTRICAL SHOP', 'VAT', 'DEMOCRACIA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2076, '102-264-799', 'SEVEN ELEVEN TRADING', 'VAT', 'JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2077, '450-038-238-000', 'SEVEN FISHES', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2078, '474-049-756-000', 'SEVEN SEVEN CONSUMER GOODS TRADING (JOSEPH JOSHUA U. TEE)', 'VAT', '4-1-1 J.M BASA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2079, '424-702-805-002', 'SEVENTY N COFFEE PLUS DRINKERY RESTAURANT', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2080, '483-993-221-000', 'SG FOOD CONCEPTS, INC.', 'VAT', 'DIVERSION ROAD CORNER TAFT ST., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2081, '486-993-221-000', 'SG FOOD CONCEPTS, INC.', 'VAT', 'DIVERSION ROAD BRGY. SAMBAG JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2082, '120-013-780-000', 'SGP GAS STATION', 'VAT', 'FUNDA-DALIPE,SAN JOSE,ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2083, '008-311-880-062', 'SHAKE SHACK (GOOD EATS SPECIALIST INC.)', 'VAT', 'GREENHILLS MALL ANNEX, SAN JUAN, METRO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2084, '000-163-396-00247', 'SHAKEYS PIZZA ASIA CENTURES INC.', 'VAT', 'NO.1 G/F MUNCHEN BLDG.6488,CALLE EL 98 BRGY.CUARTERO,JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2085, '000-163-396-00063', 'SHAKEY\'S PIZZA ASIA VENTURES INC.', 'VAT', 'BAGONG PAG ASA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2086, '000-312-274-001', 'SHAMROCK BAKERY', 'VAT', 'FUENTE OSMEÑA CAPITOL SITE, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2087, '283-385-816-006', 'SHAN FOOD PRODUCTS TRADING', 'VAT', 'SM CITY BENIGNO AQUINO AVE BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2088, '427-900-700-001', 'SHANGHAI BZR. INC', 'VAT', 'BASA STREET ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2089, '477-961-383-001', 'SHIRITORI VENTURES INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2090, '000-428-068-001', 'SHOGUN MGT & DEV\'T CORP.', 'VAT', 'SAN ROQUE EXT. ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2091, '292-784-403-00000', 'SHOP AND MATCH FASHION ACCESSORIES', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2092, '292-784-403-000', 'SHOP AND MATCH FASHION ACCESSORIES', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2093, '292-704-403-00000', 'SHOP AND MATCH FASHION ACCESSORIES', 'VAT', 'MONTINOLA BLDG.,LEDESMA ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2094, '417-385-533-000', 'SHOPBY NOVELTY SHOP', 'NV', 'CAPRICHO POBLACION BANATE ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2095, '007-978-428-026', 'SHOWROOM7 INC.', 'VAT', 'SM CITY ILOILO BENIGNO AVE. BOLILAO MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2096, '737-152-397-000', 'SIAM THAI CUSINE', 'VAT', 'GREENFIELD SQUARE,BENIGNO AQUINO AVENUE SAN RAFAEL,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2097, '467-970-898-000', 'SIETE PICADOS SEAFOODS RESTAURANT', 'NV', 'COASTAL ROAD BANTOD FABRICA, DUMANGAS, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2098, '008-384-536-00001', 'SILANTRO FIL-MEX CANTINA', 'VAT', 'KATIPUNAN AVENUE, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2099, '000-360-191-056', 'SILICON VALLEY COMPUTER GROUP PHIL., INC', 'VAT', 'SM CITY BRGY.BOLILAO B AQUINO AVE. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2100, '639-487-778-000', 'SILVERPRIME FOOD CORP.', 'VAT', 'GUINOBATAN LEGANES ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2101, '487-917-492-000', 'SIMPLE & WHOLE (MEIJIN GLOBALWORKS CORP.)', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2102, '491-746-146-000', 'SINGLE-SEED BAKERY SUPPLIES TRADING', 'VAT', 'TABUC SUBA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2103, '643-470-791-001', 'SIP N\' SPATTER CAFE INC.', 'VAT', 'STA. TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2104, '928-954-907-000', 'SIS ELECTRICAL SHOP', 'NV', 'ZONE 2 NORTH BALUARTE MOLO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2105, '914-794-394-000', 'SIXTO AGRI PRODUCTS', 'NV', 'J.DE LEON STREET, BRGY. FLORES ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2106, '219-225-647-008', 'SKETCH BOOKS, INC', 'VAT', 'BONIFACIO GLOBAL CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2107, '009-682-202-000', 'SKINETICS', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2108, '452-918-211-000', 'SKINSCIENCE, INC', 'VAT', 'PISON AVENUE BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2109, '722-723-661-000', 'SKV STAINLESS & ALUMINUM TRADING', 'VAT', 'MABINI ST.,BRGY.LIBERATION,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2110, '004-247-791-066', 'SM AND SONS FOOD PRODUCTS INC', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2111, '008-027-819-011', 'SM ESGUERRA HOTELS & RESTAURANTS INC.', 'VAT', 'MAHARLIKA EAST 4120 TAGAYTAY CITY, CAVITE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2112, '209-609-185-022', 'SM HYPERMARKET (SUPER SHOPPING MARKET, INC.)', 'VAT', 'JALANDONI ST., JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2113, '209-609-185-00010', 'SM HYPERMARKET MUNTINLUPA (SUPER SHOPPING MARKET, INC.)', 'VAT', 'MUNTINLUPA CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2114, '213-545-858-004', 'SM MART, INC.', 'VAT', 'SM CITY NORTH AVE., PAGASA 1, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2115, '003-058-789-00156', 'SM PRIME HOLDINGS, INC', 'VAT', 'MOA SQUARE MARINA WAY MALL OF ASIA ARENA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2116, '003-058-780-027', 'SM PRIME HOLDINGS, INC', 'VAT', 'MOA SQUARE MARINA WAY MALL OF ASIA ARENA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2117, '003-058-789-00027', 'SM PRIME HOLDINGS, INC.', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2118, '003-058-789-156', 'SM PRIME HOLDINGS, INC.', 'VAT', 'MOA SQUARE MARINA WAY MALL OF ASIA COMPLEX BRGY 76 ZONE 10 PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2119, '205-172-946-001', 'SM STORE', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2120, '003-058-789-00034', 'SM STORYLAND FUN PARK', 'VAT', 'SM CITY ILOILO BENIGNO AQUINO AVENUE MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2121, '000-144-976-033', 'SM SUPERMARKET', 'VAT', 'BACOLOD CITY CAPITAL NEGROS OCCIDENTAL', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2122, '102-266-315-006', 'SMALLVILLE 21 HOTEL', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2123, '903-552-358-000', 'SMR CHOCOLATE STORE', 'NV', 'SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2124, '000-144-976-017', 'SMSUPERMARKET', 'VAT', 'SM CITY LUNETA BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2125, '462-863-880-000', 'SN PLAINFIELD MKTG. INC', 'VAT', 'LMT BLDG. DE LEON ST ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2126, '005-983-468-00000', 'SOCIAL ACTION CENTER, INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2127, '274-356-961-000', 'SOLID 3 ENTERPRISES', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2128, '006-227-498-001', 'SOLID GOLD FOOD INC.', 'VAT', 'MCDONALD\'S MARYMART ILOILO G/F MARY MART MALL VALERIA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2129, '006-227-498-012', 'SOLID GOLD FOOD, INC.', 'VAT', 'E. LOPEZ COR. SAN JOSE ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2130, '006-227-498-002', 'SOLID GOLD FOOD, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2131, '006-227-498-016', 'SOLID GOLD FOOD, INC.', 'VAT', 'LOPEZ AVENUE UNGKA 11 PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2132, '006-227-498-015', 'SOLID GOLD FOOD, INC.- PISON', 'VAT', 'PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2133, '006-227-498-014', 'SOLID GOLD FOOD, INC.- ROB', 'VAT', 'ROBINSONS PLACE ILOILO, ROXAS VILLAGE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2134, '006-227-498-008', 'SOLID GOLD FOOD,INC', 'VAT', 'ATRIUM, GENERAL LUNA BONIFACIO DRIVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2135, '006-227-490-015', 'SOLID GOLD FOOD,INC', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2136, '487-548-008-001', 'SOLID GOLD FOOD,INC', 'VAT', 'GEN.FULLON COR.ZALDIVAR ST.,SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2137, '934-474-444-002', 'SOLID MOTORCYCLE', 'VAT', 'DELGADO STREETS LIBERATION RD. BRGY. SAN AGUSTIN,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2138, '934-474-444-004', 'SOLID MOTORCYCLE PARTS', 'VAT', 'M.V HECHANOVA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2139, '005-277-498-000', 'SOLIDGOLD MULTI RESOURCES CORP.', 'VAT', 'JALANDONI ST., BRGY. LOURDES, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2140, '000-146-548-030', 'SOLIDSERVICE ELECTRONICS CORPORATION', 'VAT', 'COR. BRGY. SAN RAFAEL RD., SEN. B. AQUINO AVE., DIVERSION ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2141, '008-632-317-001', 'SOLINA BEACH AND NATURE RESORT', 'VAT', 'GUINTICGAN CARLES, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2142, '008-165-438-000', 'SOLUTIONS EXPERTS AND ENABLERS INC', 'VAT', 'BRGY DAMAYANG LAGI, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2143, '177-211-790-000', 'SOLYMAR FAMILY BEACH RESORT', 'NV', 'NAMOCON, TIGBAUAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2144, '007-067-428-005', 'SONAK SPORTS SPECIALISTS, INC.', 'VAT', 'UNIT 1 TWO PARKADE BONIFACIO HIGH STREET CENTRAL BONIFACIO GLOBAL CITY, FORT BONIFACIO, TAGUIG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2145, '008-854-370-001', 'SONGON FOOD CORPORATION', 'VAT', 'MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2146, '942-478-940-00000', 'SOT DDU KUNG', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2147, '010-257-384-000', 'SOTOGRANDE ILOILO HOTEL INC.', 'VAT', 'GREENFIELD SQUARE, SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2148, '482-420-930-000', 'SOUF INFINITY CORPORATION', 'VAT', 'UNIT 2 GLOBAL CENTER BUSINESS PARK, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2149, '207-247-094-000', 'SOUTH LUZON TOLLWAY CORPORATION', 'VAT', 'SOUTH LUZON BARANGAY MAPAGONG SITIO LATIAN, CALAMBA LAGUNA', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2150, '010-243-944-008', 'SOUTH PETRO, MARKETING CORP.', 'VAT', 'BRGY.SAN RAFAEL, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2151, '010-243-944-005', 'SOUTH PETROL MARKETING CORP.', 'VAT', 'SAN MARCOS HIGHWA, BRGY. SAN PEDRO , MOLO ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2152, '228-037-432-263', 'SOUTH STAR DRUG , INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2153, '228-037-432-570', 'SOUTH STAR DRUG , INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2154, '228-037-432-448', 'SOUTH STAR DRUG , INC.', 'VAT', 'E.LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2155, '228-037-432-300', 'SOUTH STAR DRUG , INC.', 'VAT', 'M.H. DEL PILAR ST., MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2156, '228-037-432-00671', 'SOUTH STAR DRUG , INC.', 'VAT', 'BRGY. ATABAY, SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2157, '228-037-432-252', 'SOUTH STAR DRUG , INC.', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2158, '008-909-992-012', 'SOUTHEASTASIA RETAIL INC.', 'VAT', 'U.P TOWN CENTER, KATIPUNAN AVENUE, BRGY. UP CAMPUS, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2159, '906-501-193-00011', 'SP DIMSUM SEAFOOD RESTAURANT', 'VAT', 'BOARDWALK AVENUE,SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2160, '651-023-959-002', 'SP FOODS CORPORATION', 'VAT', 'TABUC SUBA JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2161, '775-407-493-000', 'SPA RIVIERA PRIME,OPC', 'VAT', 'S/F SM CITY SOUTHPOINT BENIGNO AQUINO AVE.,BOLILAO,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2162, '102-274-516-004', 'SPC RKS GASOLINE STATION', 'VAT', 'HUERVANA STREET, LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2163, '214-371-976-035', 'SPECSTACY CONCEPTS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2164, '436-075-933-000', 'SPEEDLANE GASOLINE STATION', 'VAT', 'COASTAL ROAD, BRGY. CAMANGAY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2165, '010-502-862-008', 'SPH FOODS CORPORATION', 'VAT', 'QUEZON AVE', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2166, '201-167-510-006', 'SPICEBIRD GRILL', 'VAT', 'D\'MALL D\' BORACAY BALABAG 5608 BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2167, '917-778-439-000', 'SPINTASTIC WATER REFILLING STATION & WATER SUPPLIES', 'VAT', 'LOPEZ JAENA ST. LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2168, '010-074-098-001', 'SPL FOOD VENTURES CORP.', 'VAT', 'BRGY. CUARTERO, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2169, '219-197-688-022', 'SPORTS CENRAL (MANILA)NC', 'VAT', 'SM CITY BENIGNO AQUINO AVE., MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2170, '219-197-688-052', 'SPORTS CENRAL (MANILA)NC', 'VAT', 'SM CITY ILOILO BENIGNO AQUINO AVENUE DIVERSION ROAD,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2171, '908-803-219-000', 'SPRINGPALACE DIMSUM HOUSE', 'VAT', 'QUINTIN SALAS, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2172, '005-403-713-000', 'SRN FAST SEACRAFTS INC.', 'VAT', 'G/F AMIL\'S TOWER, PILAR ST., ZAMBOANGA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2173, '763-342-972-00000', 'SS KOREAN FOOD VENTUREINC', 'VAT', 'BRGY 4 BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2174, '009-922-253-026', 'SSP- MACTAN CEBU CORP.', 'VAT', 'ARRIVAL TERMINAL 1 MACTAN CEBU INTERNATIONAL AIRPORT PUSOK LAPU LAPU', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2175, '411-133-546-001', 'ST PETER\'S PHARMACY', 'VAT', 'JALANDONI ESTATE ,LAPUZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2176, '005-818-626-004', 'ST. ANDREW FOOD CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2177, '005-818-626-000', 'ST. ANDREW FOOD CORPORATION- MOLO', 'VAT', 'AURORA SUBD. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2178, '271-763-565-001', 'ST. FRANCIS FAMILY MART', 'VAT', '27 TULLAHAN ST STA QUITERIA CALOOCAN CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2179, '000-086-204-022', 'STAR APPLIANCE CENTER, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2180, '000-086-204-00022', 'STAR APPLIANCE CENTER, INC.', 'VAT', 'SM CITY ILOILO BENIGNO AQUINO AVE MANDURRIAO BOLILAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2181, '000-008-204-00022', 'STAR APPLIANCE CENTER, INC.', 'VAT', 'SM CITY ILOILO, BENIGNO AQUINO AVENUE, BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2182, '605393015-00317', 'STARBREAKER CORP', 'VAT', 'STRATA BOLILAO MANDURRIAO CITY OF ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2183, '605-393-015-101', 'STARBREAKER CORP.', 'VAT', 'ROTONDA PLAZUELA DOS SEN. BENIGNO AQUINO AVENUE SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2184, '605-393-015-097', 'STARBREAKER CORP.', 'VAT', 'TANDANG SORA CORNER QUIRINO 2-A NCR SECOND DISTRICT QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2185, '605-393-015-336', 'STARBREAKER CORP.', 'VAT', 'PACENCIA PARKING ATRIA PARK DISTRICT SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:07', '2026-07-13 03:05:07'),
(2186, '005-215-077-251', 'STARBUCKS COFFEE', 'VAT', 'ALABAMA ST., QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2187, '005-215-077-397', 'STARBUCKS COFFEE', 'VAT', 'BRGY STO CRISTO , QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2188, '005-215-077-417', 'STARBUCKS COFFEE', 'VAT', 'STA. TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2189, '005-215-077-108', 'STARBUCKS COFFEE (RUSTAN COFFEE CORP.)', 'VAT', 'CUBAO QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2190, '005-215-077-078', 'STARBUCKS COFFEE (RUSTAN COFFEE CORP.)', 'VAT', 'SHELL SLEX, CALABUSO BIÑAN, LAGUNA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2191, '421-522-918-004', 'STARHOME MART', 'VAT', 'GUSTILO ST., POBLACION ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2192, '004-453-807-006', 'STARLITE FERRIES, INC.', 'VAT', 'DANGAY ROXAS, ORIENTAL MINDORO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2193, '004-453-807-003', 'STARLITE FERRIES, INCORPORATED', 'VAT', 'SAN ANTONIO, CALAPAN CITY, ORIENTAL MINDORO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2194, '282-763-771-001', 'STARTEXT CELLPHONE MARKETING', 'NV', 'GAISANO CAPITAL CITY, LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2195, '002-176-999-008', 'STATIONERY (SUPPLIES STATION, INC.', 'VAT', 'NCR, PARANAQUE CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2196, '471-253-072-000', 'STERIDA INC.', 'VAT', 'DONATO P. AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2197, '000-146-306-524', 'STORES SPECIALISTS, INC.', 'VAT', 'MIDLAND BUENDIA BLDG 403 SEN. GIL PUYAT AVE. MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2198, '000-146-306-727', 'STORES SPECIALISTS, INC.-AYALA CEBU', 'VAT', 'AYALA CENTER, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2199, '123-737-441-000', 'STREET SMART AUTO SUPPLY', 'VAT', 'SIMON LEDESMA STREET, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2200, '009-233-638-001', 'STROMBOOT FOOD SERVICES INC.', 'VAT', 'QUIRINO TALIPAPA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2201, '127-765-618', 'STRYP TAXI', '0', 'LEDESCO CITY HOMES LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2202, '000-309-496-007', 'SUAREZ BROTHERS METAL ARTS, INC', 'VAT', '2ND LEVEL, GAISANO CAPITAL ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2203, '149-819-462-001', 'SUES CAKE GALLERY', 'VAT', 'J DE LEON- OSMEÑA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2204, '149-819-462-004', 'SUE\'S CAKE GALLERY', 'VAT', 'ROBINSONS PLACE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2205, '225-682-089-183', 'SUGARY MOMENTO BAKESHOP, INC.', 'VAT', 'G/F SM DELGADO COR. VALERIA - DELGADO STS. DANAO CITY PROPER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2206, '225-682-089-172', 'SUGARY MOMENTO BAKESHOP, INC.', 'VAT', 'SAN JOSE WARD POTOTAN ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2207, '001-004-583-038', 'SUMMA FOODS CORP', 'VAT', 'Q ABETO STREET MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2208, '001-004-583-017', 'SUMMA FOODS CORP', 'VAT', 'I.C SHELL GASOLINE STATION TAGBAC JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2209, '001-004-583-004', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2210, '001-004-583-00029', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2211, '001-004-583-029', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2212, '001-004-583-0019', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2213, '001-004-583-011', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2214, '001-004-583-002', 'SUMMA FOODS CORP.', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2215, '001-004-583-00001', 'SUMMA FOODS CORPORATION', 'VAT', 'SEN B. AQUINO AVE., BRGY. BOLILAO MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2216, '001-004-583-037', 'SUMMA FOODS CORPORATION', 'VAT', 'LGF FOODCOURT SM CITY,MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2217, '001-004-583-039', 'SUMMA FOODS CORPORATION', 'VAT', 'G/F UNIT A & B FESTIVE WALK MALL ILOILO BUSINESS PARK ARPORT ROAD MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2218, '001-004-583-040', 'SUMMA FOODS CORPORATION', 'VAT', 'SAN JOSE AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2219, '001-004-583-00030', 'SUMMA FOODS CORPORATION-JARO', 'VAT', 'ROBINSON\'S PLACE E.LOPEZ ST.,SAN VICENTE JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2220, '006-130-456-003', 'SUMMERLIN CENTER FOOD CORP.', 'VAT', 'G/F MARYMART CENTER ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2221, '006-130-456-002', 'SUMMERLIN CTR. FOOD CORP.', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2222, '007-169-897-005', 'SUMMIT BIKES', 'VAT', 'LUNA ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2223, '183-292-089-000', 'SUNBURTS BALAY TABLEA', 'NV', 'RIZAL ILAWOD ST., BRGY. ZONE 1 , CABATUAN,ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2224, '294-270-609-000', 'SUNMART MARKETING', 'VAT', 'J.M. BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2225, '008-619-900-049', 'SUNNIES BY CHARLIE INC.', 'VAT', 'UGF SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2226, '008-619-00155', 'SUNNIES SPECS (SUNNIES INC.)', 'VAT', 'GREENHILLS MALL ANNEX, SAN JUAN, METRO MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2227, '102-274-486-000', 'SUNNY A. TAN', 'NV', 'DULONAN AREVALO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2228, '000-070-851-007', 'SUNPRIDE FOODS, INCORPORATED', 'VAT', '#57 RIZAL ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2229, '000-070-851-00007', 'SUNPRIDE FOODS, INCORPORATED', 'VAT', '#57 RIZAL ST., LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2230, '009-932-738-012', 'SUPER 50 CORPORATION', 'VAT', 'LEVEL 2 ROBINSONS PLACE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2231, '005-623-368-007', 'SUPER EAST ASIA ENTERPRISES INC', 'VAT', 'SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2232, '653-339-594-000', 'SUPER SAVER HARDWARE INC.', 'VAT', 'DOUBLEWIN BLDG. TABUC SUBA JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2233, '209-609-185-00022', 'SUPER SHOPPING MARKET , INC. (SM HYPERMARKET)', 'VAT', 'COMM. CIVIL COR. JALANDONI STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2234, '209-609-185-0002', 'SUPER SHOPPING MARKET INC.', 'VAT', 'SUPERMARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2235, '209-609-185-042', 'SUPER SHOPPING MARKET INC.', 'VAT', 'SUPERMARKET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2236, '291-533-630-001', 'SUPER VALLEY AGRI TRADING', 'VAT', 'ANGELES ARCADE MABINI ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2237, '171-910-034', 'SUPER VALUE PHARMACY', 'VAT', 'CK PLAZA, HUERVANA ST, LAPZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2238, '009-932-738-001', 'SUPER50 CORPORATION', 'VAT', 'ROBINONS PLACE PAVIA,UNIT L2 16-18 ROBINSONS PLACE PAVIA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2239, '009-932-738-008', 'SUPER50 CORPORATION', 'VAT', 'ROBINSONS PLACE ILOILO, LEDESMA COR.MABINI ST.', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2240, '008-789-606-000', 'SUPERHOUSE SOLUTIONS CAFÉ CORP.', 'VAT', 'TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2241, '008-789-606-002', 'SUPERHOUSE SOLUTIONS CAFÉ CORP.', 'VAT', '3F SM CITY MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2242, '', 'SUPERMARKET', 'NV', 'ILOILO CITY', '', '', '', '', 'Recovery', '2026-07-13 03:05:08', '2026-07-20 07:29:23'),
(2243, '010-695-095-001', 'SUPERSTORE MERCHANDISING CORP.', 'VAT', 'IZNART MAGSAYSAY 5000 ILOILO CITY ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2244, '000-144-976-00023', 'SUPERVALUE INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2245, '000-144-976-00024', 'SUPERVALUE INC.', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2246, '000-144-976-024', 'SUPERVALUE INC.', 'VAT', 'COR. VALERIA DELGADO STS., CITY PROPER, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2247, '000-144-967-00023', 'SUPERVALUE INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2248, '000-144-976-000', 'SUPERVALUE INC.', 'NV', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2249, '000-144-976-0024', 'SUPERVALUE INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2250, '000-144-976-00001', 'SUPERVALUE INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2251, '000-144-976-023', 'SUPERVALUE INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2252, '000-144-976-00064', 'SUPERVALUE, INC. (SUPERMARKET AURA PREMIER)', 'VAT', 'FORT BONIFACIO GLOBAL CITY, TAGUIG', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2253, '010-097-875-002', 'SUREBIZ CORPORATION', 'VAT', '#58 BRGY.RIZAL LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2254, '010-097-875-00002', 'SUREBIZ MARKETING', 'VAT', '#58 RIZAL, LA PAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2255, '000-382-653-00022', 'SURPLUS MARKETING CORP.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2256, '000-382-663-026', 'SURPLUS MARKETING CORP.', 'VAT', 'COR.VALERIA AND DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2257, '000-382-663-000', 'SURPLUS MARKETING CORP.', 'VAT', 'QUEZON CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2258, '000-382-653-000', 'SURPLUS MARKETING CORP.', 'VAT', 'SM NORTH EDSA NORTH AVENUE COR EDSA SANTO CRISTO, QUEZON CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2259, '000-382-653-022', 'SURPLUS MARKETING CORPORATION', 'VAT', 'B AQUINO AVE., MANDURRIAO, ILOIILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2260, '000-382-653-020', 'SURPLUS MARKETING CORPORATION', 'VAT', 'BAGUIO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2261, '191-626-825-000', 'SUSAN J. AMPATIN', 'VAT', 'MANOC MANOC MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2262, '000-844-246-307', 'SUYEN CORPORATION', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2263, '000-844-246-00734', 'SUYEN CORPORATION', 'VAT', 'FESTIVE WALK MALL ILOILO BUSINESS PARK', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2264, '000-844-246-311', 'SUYEN CORPORATION', 'VAT', '3RD FLOOR 1 & 5A NEWPORT PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2265, '000-844-246-249', 'SUYEN CORPORATION', 'VAT', 'BONIFACIO GLOBAL CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2266, '000-844-246-000', 'SUYEN CORPORATION', 'VAT', 'SAN JOSE DE BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2267, '000-844-246-206', 'SUYEN CORPORATION', 'VAT', 'SAN JOSE DE BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2268, '000-844-246-723', 'SUYEN CORPORATION', 'VAT', 'SAN JOSE DE BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2269, '000-844-246-607', 'SUYEN CORPORATION', 'VAT', 'SAN JOSE DE BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2270, '000-844-246-247', 'SUYEN CORPORATION', 'VAT', 'SM MALL OF ASIA, BAY BLVD., PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2271, '000-844-246-875', 'SUYEN CORPORATION', 'VAT', 'B. AQUINO AVENUE, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2272, '921-857-396-000', 'SWWA PLASTIC CENTER', 'VAT', '178 VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2273, '426-082-852-000', 'SYL HERMANOS FOOD SERVICES, INC.', 'VAT', 'ROSCOM BLDG., MUELLE LONEY STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2274, '005-470-257-397', 'SYNERMAXX CORPORATION', 'VAT', 'MC ARTHUR DRIVE TABUC SUBA JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2275, '767-531-515-002', 'SYSTEMIC FARM CHEMICALS INC.', 'VAT', 'BUHANG JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2276, '000-286-989-000', 'SYSU INTERNATIONAL INC', 'VAT', '145 PANAY AVENUE, SOUTH TRIANGLE, QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2277, '004-865-333-001', 'T.J. FOODS, INC.', 'VAT', 'ROBINSONS PLACE LEDESMA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2278, '102-265-187-000', 'T.TAM DRY GOODS STORE', 'VAT', 'ALDEGUER ST.,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2279, '938-069-210-000', 'TABLE MATTERS CATERING SERVICES', 'NV', '#5 MIGUEL ST AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2280, '938-069-210-00000', 'TABLE MATTERS CATERING SERVICES', 'NV', '#5 MIGUEL ST AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2281, '734-716-709-000', 'TAGALANI INCORPORATED', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2282, '292-849-334-00000', 'TAGBAK PIONEERS IN.', 'VAT', 'MC ARTHUR DRIVE,TAGBAK, JARO, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2283, '292-849-334-000', 'TAGBAK PIONEERS INC', 'VAT', 'MAC ARTHUR DRIVE, TAGBAK, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2284, '712-378-004-000', 'TAMBAYAN NI DODOY FOOD HAUZ', 'NV', 'BRGY. BOLILAO, MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2285, '757-767-250-00001', 'TAMBUKIKO INC.', 'VAT', 'ATRIUM MALL BLDG GEN. LUNA ST BRGY DANAO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2286, '119-490-251-003', 'TANALGO GAS STATION', 'VAT', 'BOLONG OESTA STA. BARBARA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(2287, '005-164-729', 'TATA TRANS. CORP.', 'NV', 'JALANDONI JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2288, '117-401-658-000', 'TATOY\'S', 'VAT', 'STO.NINO SUR, AREVALO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2289, '938-058-866-000', 'TAY LAY TEXTILE', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2290, '008-247-343-005', 'TEAM RUSSO PHILIPPINES INC (MACBETH)', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2291, '000-963-120-000', 'TEAM SALES AND PROMOTIONS, INC.', 'VAT', 'HIBAO-AN NORTE, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2292, '008-521-046-0018', 'TEAMAKERS CORPORATION', 'VAT', 'BUSINESS PARK MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2293, '008-521-046-0003', 'TEAMAKERS CORPORATION', 'VAT', 'SUN MALL ESPANA BLVD. MAYON ST. SANTA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2294, '008-521-046-003', 'TEAMAKERS CORPORATION', 'VAT', 'SUN MALL ESPANA BLVD. MAYON ST. SANTA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2295, '008-521-046-012', 'TEAMAKERS CORPORATION', 'VAT', 'CT 201 2/F SM CITY BACOLOD RECLAMATION', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2296, '000-963-120-002', 'TEAMS SALES AND PROMOTIONS INC', 'VAT', 'BRGY. BALDOZA LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2297, '431-810-192-000', 'TEASTARS FOODS CORP.', 'VAT', 'UGF SM CITY BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2298, '431-810-192-00000', 'TEASTARS FOODS CORPORATION', 'VAT', 'ILOILO AYALA TECHNOHUB MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2299, '009-012-975-006', 'TECH SYNERGY PHONES AND ACCESSORIES INC.', 'VAT', 'BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2300, '416-257-770-002', 'TECHNOCARE GADGETS REPIR AND ACCESSORIES', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2301, '281-836-124-000', 'TECHOME CORPORATION', 'VAT', '1688 MALL, COR. QUEZON-LEDESMA STREET BRGY. KAUSWAGAN, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2302, '168-244-995-000', 'TED, JOHN & LEMUEL\'S GENERAL MERCHANDISING', 'VAT', 'TAFT ST. POBLACION DUMALAG CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2303, '177-216-837-006', 'TED\'S OLD TIMER LAPAZ BATCHOY', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2304, '184-859-160-000', 'TEE UNO SHIIRT SUPPLY&PRINTING', 'NV', 'LAGUDA SUBD.LA PAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2305, '278-824-828-000', 'TEMP TECHNOLOGIES KITCHEN EQUIPMENT REPAIR AND MAINTENANCE SERVICES', 'NV', 'BRGY.CALUBIHAN, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2306, '009-793-103-006', 'TERIYAKI BOY AND SIZZLIN STEAK', 'VAT', 'ROBINSONS PLASCE ANTIQUE, CITY OF ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2307, '000-060-997-000', 'TESCO SERVICES INCORPORATED', 'VAT', 'BRGY.SAMPALOC, MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2308, '923-417-489-000', 'TEXT TERMINAL ENTERPRISE', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2309, '923-417-489-001', 'TEXT TERMINAL ENTERPRISE', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2310, '460-903-593-000', 'THE ASSEMBLAGE POINT RESORT AND CONVENTION HUB, INC.', 'VAT', 'LAWIGAN, SAN JUAQUIN, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2311, '455-369-022-004', 'THE BAG AND SHOE KOBBLER CORPORATION', 'VAT', 'EX.201 SECOND LEVEL SM CITY ILOILO BRGY.,MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2312, '480-855-986-000', 'THE CAPITOL PLACE ILOILO, INC.', 'VAT', 'GEN. LUNA STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2313, '735-921-239-000', 'THE CAVE SUPERCLUB&MUSIC HALL', 'VAT', 'CITY TIME SQUARE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2314, '000-249-730-000', 'THE COMMONER INC', 'VAT', 'J.M. BASA ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2315, '005-694-346-079', 'THE DIY SHOP CORP.', 'VAT', 'BRGY. UNGKA II PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2316, '005-694-346-078', 'THE DIY SHOP CORP.', 'VAT', 'ILOILO FESTIVE WALK MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2317, '008-388-234-021', 'THE LOOP GADGET ZONE', 'VAT', 'SENATOR BENIGNO AQUINO JR. AVE. DIVERSION ROAD BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2318, '226-527-915-046', 'THE METRO GAISANO TAGAYTAY', 'VAT', 'EMILIO AGUINALDO HIGHWAY SILANG JUNCTION NORTH TAGAYTAY CITY CAVITE PHIL', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2319, '009-856-653-000', 'THE PINNACLE SUITES GRP.INC', 'VAT', 'ATABAY, SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2320, '245-603-497-067', 'THE REAL AMERICAN DOUGHNUT COMAPNY INC.', 'VAT', 'UGF SM CITY ILOILO, BENIGNO AQUINO AVE. SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2321, '245-603-492-067', 'THE REAL AMERICAN DOUGHNUT COMPANY INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2322, '245-603-492-128', 'THE REAL AMERICAN DOUGHNUT COMPANY, INC.', 'VAT', 'SM CITY MANDURRIAO BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2323, '213-545-858-000', 'THE SM STORE ( HYPERMARKET )', 'NV', 'COMMISSION CIVIL CORNER JALANDONI STREET, JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2324, '458-947-629-000', 'THE SUMMERHOUSE RESTAURANT AND BAKESHOP', 'VAT', 'UNIT B2 FESTIVE WALK MALL, ILOILO BUSINESS PARK, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2325, '201-167-510-009', 'THE SUNNY SIDE', 'VAT', 'BORACAY SANDS HOTEL, STATION 3 BEACH FRONT MANOC2 BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2326, '609-691-686-00016', 'THE WORLD\'S FINEST LIQOUR INC.', 'VAT', 'CASA EMPERADPR FESTIVE WALK ILOILO BUSINESS PARK BUHANG TAFT NORTH MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2327, '291-403-216-000', 'THEA ELYZA A. UY', 'VAT', 'SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2328, '615-110-723-000', 'THEO\'S FOOD GURU CORPORATION', 'VAT', 'SM CITY DIVERSION ROAD BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2329, '321-669-291-002', 'THERESA\'S BAKESHOP', 'VAT', '#41 JAYME ST., OUR LADY PF FATIMA JARO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2330, '277-426-448-041', 'THIRSTY JUICES AND SHAKES CORP.', 'VAT', 'BOLILAO MANDURIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2331, '009-720-295-003', 'THREE WISE MONKEYS INC', 'VAT', 'GENERAL LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2332, '009-720-295-001', 'THREE WISE MONKEYS INC.', 'VAT', 'BRGY. SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2333, '000-250-833-009', 'TIBIAO BAKERY INC- JARO BRANCH', 'VAT', 'NO. 4 WASHINGTON STREET, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2334, '000-250-833-007', 'TIBIAO BAKERY INC.,', 'VAT', 'SAN AGUSTIN ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2335, '000-250-833-008', 'TIBIAO BAKERY INC.,', 'VAT', 'AVANCENA ST. NORTH FUNDIDOR MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2336, '000-250-833-000', 'TIBIAO BAKERY, INC', 'VAT', 'COMPANIA ST.,MOLO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2337, '009-628-640-000', 'TIBIAO CALAWAG VENTURES, INC', 'VAT', 'MALABOR, TIBIAO ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2338, '485-164-712-001', 'TICKET TIME', 'VAT', 'ROBINSON,E. LOPEZ ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2339, '008-686-273-007', 'TITAN HOME DIAMOND CORP.', 'VAT', 'BANTAYAN ROAD FUNDA-DALIPE SAN JOSE D BUENAVISTA ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2340, '007-627-215-00015', 'TITANOMACHY, INC.', 'VAT', 'UNIT 25-26 UPPER GROUND FLOOR, BONIFACIO GLOBAL CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2341, '004-865-333-000', 'TJ FOODS INC.', 'VAT', 'GAISANO CITY, LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2342, '418-412-293-000', 'TOBINIFE FUEL STATION', 'VAT', 'TABUCAN, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2343, '008-532-597-104', 'TOBISTRO FOODS INC.', 'VAT', 'BOLILAO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2344, '000-060-348-00024', 'TOBYS SPORTS', 'VAT', 'SM CITY MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2345, '472-746-383-000', 'TODELAVIAJAR.CORP.', 'VAT', 'YULO DRIVE COR. BONIFACIO ST.VILLA AREVALO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2346, '010-271-621-005', 'TONG YANG SM ILOILO', 'VAT', 'BENIGNO AQUINO AVENUE SAN RAFAEL MANDURRIAO CITY OF ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2347, '604-835-303-001', 'TONY REYES TIU', 'VAT', 'ABETO MIRASOL TAFT SOUTH QUIRINO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2348, '287-830-523-000', 'TOOLMATE MERCHANDISING', 'VAT', 'LEDESMA ST, HIPODROMO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2349, '112-362-144-0000', 'TOP GEAR SERVICE STATION', 'VAT', 'PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2350, '009-882-548-004', 'TOP PHIL CITY CORP.', 'VAT', 'T. MAGBANUA ST. BRGY. LOPEZ JAENA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2351, '009-882-548-001', 'TOP PHIL CITY CORPORATION', 'VAT', 'RIZAL ST.,GUIMBAL ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2352, '433-806-488-002', 'TOTAL SUPREME DRAGON INC', 'VAT', 'CASANDRA BLDG., ROXAS STS., SAN MIGUEL, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2353, '938-049-602-000', 'TOTO LARRY VULCANIZING SHOP', 'NV', 'TANZA TIMAWA ZONE 1, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2354, '000-069-987-000', 'TRADERS CORP.', 'VAT', 'FORTUNA ST., BANILAD M.C.', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2355, '117-383-677-005', 'TRANSCEND FUEL SERVICE STATION', 'VAT', 'C.L MONTELIBANO AVE.,VILLAMONTE, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2356, '246-099-058-000', 'TRAVELLERS INTERNATIONAL HOTEL GROUP, INC.', 'VAT', 'ILOILO BUSINESS PARK 101 MEGAWORLD BLVD., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2357, '009-305-120-00025', 'TRAVELLING GOURMATES CO.LTD.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2358, '009-721-827-000', 'TREASURE INSTYLE CO. LTD', 'VAT', 'BALINTAWAK BALINGASA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2359, '460-985-621-000', 'TR-ELYON BUSINESS CORPORATION', 'VAT', '101 AVANCENA ST., DULONAN, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2360, '468-115-284-000', 'TRENGOLD SPOON FOOD SOLUTIONS, INC.', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2361, '930-547-741-002', 'TRES AMIGOS RESTAURANT (CRISANTA M. PEDERSEN)', 'VAT', 'BORACAY, MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2362, '180-361-368-000', 'TRES HIJOS GRILL&SEAFOODS', 'VAT', 'SIMEON AGUILAR ST., CITY OF PASSI ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2363, '297-108-244-000', 'TRI ADVERTISING', 'VAT', 'BRGY. YULO-ARROYO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2364, '000-402-652-094', 'TRI VISION VENTURES INC', 'VAT', 'ESPANA COR IBARRA & SISA ST BRGY. 525 ZONE 052 SAMPALOK MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2365, '006-126-873-000', 'TRIDONG VENTURES INC', 'VAT', 'VALERIA ., ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2366, '006-126-873-001', 'TRIDONS VENTURES, INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2367, '460-985-621-001', 'TRI-ELYON BUSINESS CORPORATION', 'VAT', 'MCARTHUR HIGHWAY, BRGY.TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2368, '005-076-804-000', 'TRIMA VENTURESINC', 'VAT', 'B.S AQUINO DRIVE, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2369, '934-617-336-002', 'TRIPLE C & J FOOD BEVERAGE', 'VAT', '2ND LEVEL SM CITY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2370, '009-914-382-003', 'TRUMART FOODS CORPORATION', 'VAT', 'PARK DISTRICT COR DONATO PISON AVENUE SAN RAFAEL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2371, '765-434-586-000', 'TRZ FOOD AND INDUSTRY COPORATION', 'VAT', 'GAISANO GRAND SARA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2372, '443-803-488-001', 'TSD ILOILO', 'VAT', 'JANLANDONI ST. COR GOMEZ ST. ZARRAGA ILOILO PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2373, '256-343-407-000', 'TSD WATER REFILLING STATION', 'NV', 'SITIO RED FOX, CABUGAO SUR, PAVIA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2374, '009-705-010-002', 'TSINUWERTE CORP', 'VAT', 'TUAZON ST. COR DOMINGO SANTIAGO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2375, '938-059-010-000', 'TUKI RESTOBAR', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2376, '246-093-037-00020', 'TURKS SHAWARMA', 'VAT', 'SM CITY MARIKINA FOODCOURT MARCOS HIWAY CALUMPANG MARIKINA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2377, '010-452-188-001', 'TWIN FLAMES GLOBAL CORPORATION', 'VAT', '5811 JACOBO ST., POBLACION 1210 CITY OF MAKATI', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2378, '499-591-879-000', 'TYCOON-SEAFOODS VENTURE MARKET', 'NV', 'BRGY. MALAg-it, PONTEVEDRA, CAPIZ', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2379, '167-212-784-000', 'TYPICAL GERMAN RESTAURANT', 'NV', 'SEMINARIO ST., JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2380, '275-405-603-00001', 'UELA FOOD CORNER (JUSTINE V. TOLENTINO)', 'VAT', 'ROBINSONS PLACE JARO, E LOPEZ ST., SAN VICENTE JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2381, '000-405-340-00845', 'UNCLE JOHN\'S ROBINSON\'S SUPERMARKET CORP.', 'VAT', 'GF THE COLUMNS SALCEDO TOWER 3 BRGY. BEL-AIR MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2382, '102-269-980-001', 'UNCLE TOM\'S FRIED CHICKEN RIBS AND STEAKS', 'VAT', 'B. AQUINO AVE. , JARO WEST DIVERSION RD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2383, '102-269-980-000', 'UNCLE TOM\'S GARDEN RESTAURANT', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2384, '102-264-855-000', 'UNI ART SUPPLY', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2385, '001-799-123-030', 'UNIGLOBE TRAVELWARE CO. INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2386, '001-799-123-167', 'UNIGLOBE TRAVELWARE CO., INC.', 'VAT', 'SM CITY ILOILO BENIGNO AQUINO AVE., MANDURRIAO, BRGY. BOLILAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2387, '215-024-976-162', 'UNISILVER GROUP INTERNATIONAL CORPORATION', 'VAT', 'ROBINSONS PLACE ILOILO DE LEON & QUEZON STS. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2388, '246-347-154-046', 'UNITOP GEN. MDSE. INC.', 'VAT', 'PAVIA-ROXAS AVE. STS ROXAS CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2389, '246-347-154-051', 'UNITOP GENERAL MERCHANDISE 1', 'VAT', 'BANTAYAN ROAD.,BRGY.2 SAN JOSE ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2390, '246-347-154-011', 'UNITOP GENERAL MERCHANDISE INC.', 'VAT', 'LEDESMA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2391, '246-347-154-023', 'UNITOP GENERAL MERCHANDISE INC.', 'VAT', 'ALDEGUER ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2392, '203-188-660-074', 'UNO FACTORY OUTLET ILOILO', 'VAT', 'CIVIL COR. JALANDONI ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2393, '636-715-825-00003', 'UNO LIFESTYLE CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2394, '939-389-362-00002', 'UNO-CAT CAFE', 'VAT', 'GREENFIELD SQUARE, SAN RAFAEL MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2395, '756-284-641-00002', 'UNWND FLASHTELS, INC.', 'VAT', '5396 GEN LUNA ST POBLACION MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2396, '756-284-641-002', 'UNWND FLASHTELS, INC.', 'VAT', '5396 GEN LUNA STREET POBLACION MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2397, '200-146-472-001', 'UPS DELBROS INTERNATIONAL EXPRESS LTD., INC.', 'VAT', 'GALCO DOMESTIC BLDG, MCIAA, CARGO AREA, PUSOK, LAPU-LAPU CITY, CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2398, '004-780-008-178', 'UPSON INTL. CORP.', 'VAT', 'BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2399, '004-780-008-051', 'UPSON INTL.CORP.', 'VAT', '3/F SM CITY ILOILO BENIGNO AQUINO BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2400, '004-780-008-211', 'UPSON INTL.CORP.', 'VAT', '3/F ROBINSON\'S PLACE JARO, SAN VICENTE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2401, '004-780-008-222', 'UPSON INTL.CORP.', 'VAT', '3/F SM CITY ILOILO BENIGNO AQUINO BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2402, '009-051-957-025', 'URBAN DENIM MAKERS INC.', 'VAT', 'ROBINSONS PLACE JARO,E. LOPEZ ST. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2403, '009-051-957-064', 'URBAN DENIM MAKERS INC.', 'VAT', 'ROBINSONS PLACE JARO,E. LOPEZ ST. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2404, '257-872-047-000', 'URQUAN INC.', 'VAT', '2303 IMPERIAL BLDG.,DON CHINO ROCES AVE.,PHILIPPINES', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2405, '776-274-508-00001', 'USI SWEETS INC.', 'VAT', 'DE LEON ST., ROXAS VILLAGE', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2406, '776-274-508-00004', 'USI SWEETS INC.', 'VAT', 'GF UNIT#21 FESTIVE WALK MALL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2407, '776-274-508-002', 'USI SWEETS INC.', 'VAT', 'GF UNIT#21 FESTIVE WALK MALL MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2408, '776-274-508-00003', 'USI SWEETS INC.', 'VAT', 'E. LOPEZ ST. JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2409, '764-365-968-001', 'UY MARKETING ENTERPRISES', 'VAT', 'GRAN PLAINS SUBD., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2410, '485-025-503-000', 'UY MARKETING ENTERPRISES AND SERVICES', 'VAT', 'CARLOS F.UYBLDG.SEN.BENIGNOAVE.MANDURRIAOILOILOCITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2411, '485-025-503-001', 'UY MARKETING SERVITEX', 'VAT', 'GRAN PLAINS SUBD., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2412, '932-450-545-000', 'UY PETRON SOUTH STATION', 'VAT', 'POBLACION SOUTH, OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2413, '764-606-600-001', 'UYGQ BLENDS INC.', 'VAT', 'BRGY. SAN RAFAEL DIVERSION RD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2414, '148-768-385-000', 'UY\'S GAS CENTER', 'VAT', 'QUEZON ST., CALACHUCHI, CATARMAN, NORTHERN SAMAR', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2415, '466-111-282-000', 'VALHALLA RESORT & REST, INC', 'VAT', 'UNIT 3 PHASE 4 D MALL D BORACAY, BALABAG BORACAY MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2416, '110-497-887-000', 'VALIANT(ILOILO)ENTERPRISES', 'VAT', 'MABINI ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2417, '001-899-888-0007', 'VALLACAR TRANSITINC,', 'VAT', 'CAMALIG JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2418, '001-899-888-00007', 'VALLACAR TRANSITINC,', 'VAT', 'CAMALIG JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2419, 'VAT', 'VALUE ADDED TAX (VAT)', 'N/A', 'BIR', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2420, '005-166-520-000', 'VEE-EM SALES CO.', 'VAT', 'BLOCK 3, LOT 6, GREENFIELD SUBDIVISION, CUBAY, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2421, '003-884-943-015', 'VERDE FOODS, INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2422, '003-884-943-016', 'VERDE FOODS, INC.', 'VAT', 'MARYMART MALL, VALERIA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2423, '008-541-677-000', 'VERTEX TOLLWAYS DEV. INC.', 'VAT', 'MANDALUYONG CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2424, '183-468-990-000', 'VGS LEGEND GLASS AND ALUMINUM SUPPLY', 'VAT', 'MUELLE LONEY ST., BRGY. MUELLE LONEY, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2425, '002-332-428-0001', 'VICTORIAS FOOD CORPORATION', 'VAT', '16TH ST.,LACSON, BRGY.4, BACOLOD CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2426, '009-889-976-020', 'VIRGINIA COMMUNITIES MEGATRADERS,INC.', 'VAT', 'CORNER GUSTILO BURGOS-MABINI LAPAZ,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2427, '000-260-162-047', 'VIRGINIA FARMS INC.', 'VAT', 'CORNER DE LEON STREET, BRGY. SAN JOSE ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2428, '000-065-503-022', 'VIRGINIA FOOD INC.', 'VAT', 'GUSTILO BURGOS-MABINI LAPAZ ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2429, '000-065-503-00022', 'VIRGINIA FOOD, INC', 'VAT', 'COR.GUSTILO BURGOS-MABINI LA PAZ, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2430, '628-412-602-000', 'VISAYAS PAINTLAND OPC', 'VAT', 'STAR COMMERCIAL BLDG MC ARTHUR HI-WAY BANKERS VILLAGE QUINTIN SALAS JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2431, '009-445-220-000', 'VIST SERVE CORP', 'VAT', '109 QUEZON AVE COR G ARANETA AVE QC', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2432, '222-750-722-000', 'VITECH CELLULAR GEN.MERCHANDISE CORP', 'VAT', 'BL-18 UPPER LEVEL BRIDGEWAY LINK GREENHILLS SAN JUAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2433, '001-251-565-120', 'VIVA INTERNATIONAL FOOD & RESTAURANT, INC.', 'VAT', 'UP TOWN CENTER KATIPUNAN AVENUE UP CAMPUS QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2434, '001-251-565-155', 'VIVA INTERNATIONAL FOOD & RESTAURANTS INC', 'VAT', '07A GF FESTIVE WALK MALL MEGAWORLD BLVD. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2435, '001-251-565-156', 'VIVA INTERNATIONAL FOOD & RESTAURANTS INC', 'VAT', 'MEGAWORLD BLVD. ABETO MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2436, '001-251-565-157', 'VIVA INTERNATIONAL FOOD & RESTAURANTS INC.', 'VAT', '07A GF FESTIVE WALK MALL MEGAWORLD BLVD. ABETO MIRASOL TAFT SOUTH QUIRINO ABETO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2437, '102-269-980-00002', 'VIVIAN EUDELA MAGALONA-PROP', 'VAT', 'E. LOPEZ ST.,SAN VICENTE JARO,ILOILO CITY 5000', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2438, '600-531-827-000', 'VIZCOS CORP', 'VAT', 'BUILDING SESSION ROAD AREA BAGUIO CITY BENGUET', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2439, '008-497-158-000', 'VOLUCORE BUSINESS VENTURES CORPORATION', 'VAT', 'KM23 NORTH LUZON EXPRESS LIAS MARILAO, BULAKAN', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2440, '776-493-132-002', 'VRY RESOURCES INC.,', 'VAT', 'JV JOCSON ST.,SAN JOSE AREVALO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:08', '2026-07-13 03:05:08'),
(2441, '005-982-291-434', 'WAFFLE TIME INC.', 'VAT', 'AQUINO AVENUE, MOLO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2442, '005-982-291-488', 'WAFFLE TIME INC.', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2443, '005-982-291-455', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2444, '005-982-291-494', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'COR. LOPEZ JAENA & STA. ISABELA ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2445, '005-982-291-467', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2446, '005-982-291-473', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2447, '005-982-291-507', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2448, '005-982-291-459', 'WAFFLE TIME INC. (QUIXMART)', 'VAT', 'MC ARTHUR DRIVE, TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2449, '005-982-291-410', 'WAFFLE TIME INC. (QUIXMART) SMALLVILLE QM01', 'VAT', 'SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2450, '005-982-291-534', 'WAFFLE TIME INC.- QUIXMART MOHON', 'VAT', 'MOHON, AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2451, '008-992-474-028', 'WALK EZ RETAIL CORP.', 'VAT', 'SM CITY ILOILO SEN B. AQUINO AVENUE BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2452, '008-992-474-051', 'WALK EZ RETAIL CORP.', 'VAT', 'SM CITY ILOILO SEN B. AQUINO AVENUE BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2453, '435-367-344-000', 'WALLACE T. UY', 'VAT', 'LOPEZ JAENA ST., BRGY DEMOCRACIA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2454, '143-274-931-001', 'WALTER T. UY', 'VAT', 'COR.,VALERIA EXT.,DE LEON ST.,', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2455, '003-501-787-063', 'WALTERMART SUPERMARKET INC', 'VAT', 'NORTH EDSA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2456, '003-501-787-004', 'WALTERMART SUPERMARKET INC', 'VAT', '#222 E. RODRIGUEZ AVE. KALUSUGAN DIST QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2457, '003-501-787-054', 'WALTERMART SUPERMARKET INC', 'VAT', 'FOURTH DISTRICT, PASAY CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2458, '296-475-668-003', 'WASHINGTON COMMERCIAL', 'VAT', 'RIZAL PALA PALA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2459, '440-446-869-002', 'WASHINGTON COMMERCIAL', 'VAT', 'RIZAL PALA PALA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2460, '908-797-272-012', 'WASHINGTON SUPERTMART', 'VAT', 'COR. JM BASA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2461, '134-560-373-000', 'WATER GUEST', 'VAT', 'ALTA TIERRA VILL. JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2462, '134-560-673-000', 'WATER QUEST REFILL STATION', 'VAT', 'PHASE V, ALTA TIERRA VILLAGE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2463, '615-795-227-000', 'WATERFRONT HOSPITALITY DINING', 'VAT', 'GEN. LUNA ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2464, '214-706-591-862', 'WATSON S PERSONAL CARE STORES INC', 'VAT', 'TANDANG SORA AVE CROSS ROAD NOVALICHES SANGANDAAN QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2465, '214-706-591-348', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'GROUND FLR, CITY MALL PAROLA WHARF, MUELLE LONEY PORT AREA CONCEPCION-MONTES ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2466, '214-706-591-876', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'E LOPEZ ST. PLACE JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2467, '214-706-591-483', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'STATION 1 BORACAY ISLAND BALABAG MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2468, '214-706-591-813', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'NATIONAL HIGHWAY BALABAG MALAY AKLAN', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2469, '214-706-591-782', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'COR VALERIA DELGADO ST. DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2470, '214-706-591-298', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'COR VALERIA DELGADO ST. DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2471, '214-706-591-00876', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'COR VALERIA DELGADO ST. DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2472, '214-706-591-308', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'COR VALERIA DELGADO ST. DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2473, '214-706-591-855', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'GROUND FLR. VILLA BUILDING #78 JUPITER STREET CORNER MAKATI AVENUE BEL-AIR MAKATI CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2474, '214-705-591-315', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'ESPANA BLVD AND MAYON STREET SANTA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2475, '214-706-591-785', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'GROUND FLOOR YULO BLDG. ALONG JM BASA ORTIZ CITY PROPER ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2476, '214-706-591-360', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', '2ND FLR FESTIVE WALK MALL MEGAWORLD BLVD MANDURRIAO ABETO MIRASOL TAFT SOUTH QUIRINO ABETO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2477, '214-706-591-349', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'COR VALERIA DELGADO ST. DANAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2478, '214-706-591-01089', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'POBLACION BAROTAC VIEJO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2479, '217-706-591-00989', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'SM HYPERMARKET, JALANDONI ST. OUR LADY OF FATIMA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2480, '214-706-591-00348', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'MUELLE LONEY PORT ARE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2481, '214-706-591-00337', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'QUEZON CITY, NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2482, '214-706-591-01222', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'MAGSAYSAY CITY PROPER, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2483, '214-706-591-00770', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'ROBINSONS PLACE, DE LEON-QUEZON STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2484, '214-706-591-01020', 'WATSONS PERSONAL CARE STORES (PHILIPPINES) INC.', 'VAT', 'SAVEMORE ZARRAGA, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2485, '214-706-591-433', 'WATSONS PERSONAL CARE STORES INC.', 'VAT', 'BENIGNO AQUINO AVENUE BOLILAO DISTRICT OF MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2486, '214-706-591-530', 'WATSONS PERSONAL CARE STORES INC.', 'VAT', 'BENIGNO AQUINO AVENUE BOLILAO DISTRICT OF MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2487, '214-706-591-754', 'WATSONS PERSONAL CARE STORES PHILIPPINES', 'VAT', 'THIRD FLOOR FARMERS PLAZA ARANETA CENTER CUBAO SOCORRO QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2488, '214-706-591-315', 'WATSONS POERSONAL CARE STORES PHILIPPINES INC', 'VAT', 'STA TERESITA QUEZON CITY NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2489, '230-393-680-014', 'WATSONS-CEBU', 'VAT', 'CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2490, '414-520-850-033', 'WAVE MOBILE INC', 'VAT', 'BENIGNO AQUINO AVE. MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2491, '286-248-628-0000', 'WBH MERCHANDISING', 'VAT', 'J.C. ZULUETA ST., OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2492, '286-248-628-00004', 'WBH MERCHANDISING', 'VAT', 'J.C. ZULUETA ST., OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2493, '286-248-628-000', 'WBH MERCHANDISING', 'VAT', 'JC ZULUETA ST. POBLACION SOUTH OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2494, '286-248-628-004', 'WBH MERCHANDISING', 'VAT', 'RIZAL ST. POBLACION BAROTAC VIEJO ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2495, '000-388-634-000', 'WELCOME SUPERMART, INC', 'VAT', 'WELCOME ROTONDA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2496, '000-388-634-0000', 'WELCOME SUPERMART, INC.', 'VAT', '2 N. RAMIREZ ST. DON MANUEL WELCOME RETONDA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2497, '945-762-122-000', 'WELCOME TO BAKEVILLE INC.', 'VAT', 'OUR LADYOF FATIMA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2498, '945-762-122-00000', 'WELCOME TO BAKEVILLE INC.', 'VAT', 'OUR LADYOF FATIMA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2499, '766-872-999-000', 'WELCOME TO BAKEVILLE INC.-CHEFS AND BAKERS', 'VAT', 'OUR LADYOF FATIMA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2500, '009-682-202-001', 'WELLNESS CENTER-ANTIQUE', 'VAT', 'AML BLDG.11 FUNDA DALIPE SAN JOSE ANQTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2501, '001-096-549-000', 'WENPHIL CORPORATION', 'VAT', 'SAMPALOK MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2502, '001-096-949-008', 'WENPHIL CORPORATION', 'VAT', '1427 DAPITAN ST SAMPALOC MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2503, '257-595-823-000', 'WESTERN EMOIRE FOOD VENTURE, INC.', 'VAT', 'STAR MALL PLAZA BLDG., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2504, '000-250-027-000', 'WESTERN LAMP AND ELECTRICAL SUPPLY, INC.', 'VAT', 'DELGADO ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2505, '006-459-382-001', 'WESTERN VISAYAS ALLIANCE OF COOPERATIVES', 'VAT', 'BRGY. LOPEZ JAENA NORTE,LAPAZ, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2506, 'WET', 'WET MARKET', 'NV', 'X', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2507, '005-821-883-005', 'WEWINS CORPORATION', 'VAT', 'E.LOPEZ SREET, BRGY. SAN JOSE, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2508, '437-771-503-000', 'WHAPACK PACKAGING SUPPLIES TRADING', 'PT', 'JJ&E5 DEL ROSARIO ST. A.S FORTUNA CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2509, '935-737-060-001', 'WHEELERS MARKETING-BRANCH', 'NV', 'EL 98 ST., BRGY. TAYTAY ZONE 2, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2510, '739-045-309-001', 'WHEY KING SUPPLEMENTS ILOILO', 'VAT', 'GREENFIELD SQUARE,BENIGNO AQUINO AVENUE SAN RAFAEL,MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2511, '009-774-423-004', 'WHITE TOWER CORPORATION', 'VAT', 'RIZALINA PIZON AVENUE BOARDWALK MANDURRIAOILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2512, '009-192-878-040', 'WILCON DEPOT INC.', 'VAT', 'NORTH DIVERSION ROAD, BRGY. DUNGON B, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2513, '224-343-697-000', 'WILCON DEPOT, INC', 'VAT', 'FESTIVE WALK ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2514, '009-192-878-055', 'WILCON DEPOT, INC.', 'VAT', 'SANTA BARBARA ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2515, '009-192-878-045', 'WILCON DEPOT,INC.', 'VAT', 'GF UNIT A25-A26 FESTIVE WALK M ILOILO BUSINESS PARK MANDURRIAO ABETO MIRASOL TAFT NORTH ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2516, '102-221-724-001', 'WILFREDO P. UY MARKETING', 'VAT', 'TRADETOWN,DALIPE, SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2517, '002-727-551-000', 'WINBEST FOODS CORP.', 'VAT', 'SM BLDG. COR EL 98- LIBERTAD STS., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2518, '005-442-615-000', 'WINCHESTER FOODS CORP.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2519, '239-074-493-001', 'WINLUCK F AND B CORPORATION', 'VAT', 'SM DELGADO COR DELGADO-VALERIA STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2520, '239-074-493-002', 'WINLUCK F AND B CORPORATION', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2521, '239-074-493-00002', 'WINLUCK F AND B CORPORATION', 'VAT', 'SM DELGADO COR DELGADO-VALERIA STS., DANAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2522, '932-454-759-000', 'WISE BUY COMMERCIAL', 'VAT', 'ALDEGUER ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2523, '771-250-250-000', 'WISE GAS SERVICE STATION OPC', 'VAT', 'BRGY TACAS JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2524, '000-499-673-00031', 'WONDERFUL INC', 'VAT', 'BENIGNO AQUINO AVE., MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2525, '421-140-565-000', 'WORKSWORTH CO.', 'VAT', 'MUELLE LONEY ST., PRESIDENT ROXAS, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2526, '007-150-435-079', 'WORLD BALANCE', 'VAT', 'UPP. GROUND UNIT 050 051 EVER GOTESCO COMMONWELATH BATASAN HILLS', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2527, '007-150-435-115', 'WORLD BALANCE (CHG GLOBAL INC.)', 'VAT', 'PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2528, '010-314-863-189', 'WOW BRAND HOLDINGS INC', 'VAT', 'BLVD CORNER MAYONS STREET SANTA TERESITA QUEZON CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2529, '010-314-863-337', 'WOW BRAND HOLDINGS INC.', 'VAT', 'MANDURIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2530, '214-706-591-086', 'WPCS (PHILS), INC.', 'VAT', 'OUTLET 1 SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2531, '214-706-591-085', 'WPCS (PHILS), INC.', 'VAT', 'SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2532, '214-706-591-082', 'WPCS (PHILS), INC.', 'VAT', 'LIBERTAD ST., JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2533, '214-706-591-083', 'WPCS (PHILS), INC.', 'VAT', 'OUTLET 1 SM CITY ILOILO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2534, '214-706-591-081', 'WPCS, INC.', 'VAT', 'BOLILAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2535, '008-101-491-041', 'WS AND LANDIN, INC.', 'VAT', 'ATRIA, DONATO PISON AVENUE, BRGY. SAN RAFAEL, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2536, '008-101-491-101', 'WS AND LANDIN, INC.', 'VAT', 'BARANGAY 76 PASAY CITY NCR', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2537, '004-864-735', 'wvsu MEDICAL CENTER', 'VAT', 'JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2538, '009-693-414-000', 'XISHAN COMMERCIAL', 'VAT', 'RIZAL ST., POB. ILAWOD, CALINOG, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2539, '134-782-019-001', 'Y2K TALABAHAN', 'VAT', 'ATRIA-ILOILO, MAND. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2540, '459-138-814-002', 'YAKSKI, INC', 'VAT', 'APAS, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2541, '622-946-702-000', 'YANG DYNASTY FOOD CORP.', 'VAT', 'GF SM CITY ILOILO NORTH POINT ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2542, '916-483-728', 'YM ROOFING AND SERVICES', 'VAT', 'JARO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2543, '102-274-767-002', 'YOUNG PETRON SERVICE CENTER', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2544, '007-068-922-0000', 'YOUNG PETRON SERVICE CENTER.', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2545, '007-068-922-000', 'YOUNG PETRON SERVICE CENTER.', 'VAT', 'TABUC SUBA, JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2546, '412-199-297-001', 'YZM VEGETABLE ENTERPRISE', 'NV', 'FUENTES ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2547, '402-579-141-000', 'Z SIGNS AND ADVERTISING', 'NV', 'BRGY. TACAS, JARO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2548, '009-220-829-000', 'ZARK\'S BCD CORPORATION', 'VAT', 'UNIT 003-004 LOWER G/F SM CITY EXPANSION MANDURRIAO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2549, '009-220-829-00000', 'ZARK\'S BCD VENTURES CORP.', 'VAT', 'UNIT 003-004 LOWER GROUND FLOOR,SM CITY EXPANSION MANDURRIAO,ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2550, '276-266-238-001', 'ZATAZZA CAFÉ', 'VAT', 'JARO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2551, '618-368-322-001', 'ZAYDEN\'S FOOD HUB', 'NV', 'ABAS CEBU CITY CAPITAL CEBU', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2552, '010-134-220-000', 'ZENSUALS BEAUTY CORPORATION', 'VAT', 'B34 SECOND FLR., FESTIVE WALK MALL, ILOILO BUSINESS PARK, AIRPORT ROAD, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2553, '009-108-698-000', 'Z-ICE', 'VAT', 'ROBINSONS PLACE , E. LOPEZ ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2554, '009-748-656-001', 'ZURI HOTELS AND RESORTS CORP.', 'VAT', 'MANDURRIAO ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2555, '635-381-780-006', 'ZUSPRESSO PHIIPPINES INC', 'VAT', 'SAMPALOC NCR, CITY OF MANILA', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2556, '635-381-780-047', 'ZUSPRESSO PHILIPPINES INC.', 'VAT', 'SM CITY MANILA NATIVIDAD ALMEDA LOP COR A VILLEGAS & SAN MARCELINO ST BRGY 569 ZONE 71 ERMITA MANILA CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2557, '000-390-189-1624', 'PHILIPPINE SEVEN CORPORATION', 'VAT', 'VILLA AREVALO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2558, '935-315-585', 'MABBY\'S VIEW BEACH RESORT (NOEMI B. GEVERO)', 'VAT', 'ALEGRE, OTON, ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2559, '275-807-053-000', 'PEDI-EM GASOLINE STATION (PAOLO MARTIN S. LOTILLA)', 'VAT', 'SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2560, '003-480-168-00210', 'JOLIBEE- ILOILO TRI-STAR (FREEMONT FOODS CORPORATION)', 'VAT', 'MANDURRIAO, SAN RAFAEL, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2561, '209-609-185-062', 'SM HYPERMARKET ILOILO TERMINAL (SUPER SHOPPING MARKET, INC.)', 'VAT', 'ILOILO TERMINAL MARKET, MABINI STREET FLORES 500, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2562, '000-844-246-334', 'BENCH BOUTIQUE (SUYEN CORPORATION)', 'VAT', 'DELGADO STREET, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2563, '629-208-566-001', 'GATTO GELATO FOOD SERVICE OPC', 'VAT', 'SM CITY ILOILO BS AQUINO AVENUE, BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09');
INSERT INTO `vendor_masterlist_unified` (`id`, `tin`, `company_name`, `vat_status`, `address`, `particulars`, `document_type`, `contact`, `notes`, `saved_by`, `created_at`, `updated_at`) VALUES
(2564, '203-120-687-140', 'PAN DE MANILA FOOD CO INC', 'VAT', 'ROBINSONS PLACE ILOILO QUEZON DE LEON, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2565, '010-054-392-045', 'TERRY RETAIL CONCEPTS, INC (HAVAIANAS SM ILOILO)', 'VAT', 'SM CITY BOLILAO, MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2566, '760-168-255-003', 'COFFE BREWTHERHOOD (5BOXES VENTURES CORP.)', 'VAT', 'PAVIA ILOILO', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2567, '001-251-656-00157', 'VIVA INTERNATIONAL FOOD & RESTAURANTS, INC.', 'VAT', 'SM CITY, BENIGNO AQUINO AVE, BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2568, '312-001-764-000', '7-ELEVEN (KEENA M. FEDELICIO)', 'VAT', 'SAN JOSE, ANTIQUE', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2569, '000-390-189-02539', '7-ELEVEN (PHILIPPINE SEVEN CORPORATION)', 'VAT', 'ILOILO BUSINESS PARK, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2570, '203-120-687-180', 'PAN DE MANILA FOOD CO INC', 'VAT', 'SM CITY, BENIGNO AQUINO AVE, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2571, '440-446-869-001', 'WASHINGTON SUPERMART (AGNES KAREN B. QUE)', 'VAT', 'J.M BASA ST. ARNESAL ADUANA, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2572, '009-914-382-004', 'CK TRUMART FOODS CORPORATION', 'VAT', 'SM CITY ILOILO, DIVERSION ROAD, BOLILAO MANDURRIAO, ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2573, '472-998-204-013', 'ORANGE VALUE SOUVENIR SHOP INC.', 'VAT', 'CEBU NRA, SAN ROQUE, CEBU CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2574, '309-551-131-000', 'C.L.A ESTRERA (VIVRE BOUTIQUE)', 'VAT', 'IZNART ST., ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09'),
(2575, '916-944-297-000', 'ALDEGUER NOVELTY TRADING (LOBELLA A. CHUA)', 'VAT', 'ALDEGUER ST. ILOILO CITY', '', '', '', '', 'Stella', '2026-07-13 03:05:09', '2026-07-13 03:05:09');

-- --------------------------------------------------------

--
-- Structure for view `unified_daily_reports`
--
DROP TABLE IF EXISTS `unified_daily_reports`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `unified_daily_reports`  AS SELECT `daily_reports`.`id` AS `id`, `daily_reports`.`report_date` AS `report_date`, `daily_reports`.`store_name` AS `store_name`, `daily_reports`.`gross_sales` AS `gross_sales`, `daily_reports`.`sales_discount` AS `sales_discount`, `daily_reports`.`gulay_commissary` AS `gulay_commissary`, `daily_reports`.`direct_purchases` AS `direct_purchases`, `daily_reports`.`other_expenses` AS `other_expenses`, `daily_reports`.`kitchen_manpower` AS `kitchen_manpower`, `daily_reports`.`frontline_manpower` AS `frontline_manpower`, `daily_reports`.`overtime` AS `overtime`, `daily_reports`.`undertime` AS `undertime`, `daily_reports`.`quota_target` AS `quota_target`, `daily_reports`.`cogs_threshold` AS `cogs_threshold`, `daily_reports`.`mp_threshold` AS `mp_threshold`, `daily_reports`.`np_threshold` AS `np_threshold` FROM `daily_reports`union all select `d`.`id` AS `id`,`d`.`report_date` AS `report_date`,`d`.`store_name` AS `store_name`,`d`.`sales_revenue` AS `gross_sales`,`d`.`sales_discount` AS `sales_discount`,0 AS `gulay_commissary`,0 AS `direct_purchases`,coalesce(`e`.`total_exp`,0) AS `other_expenses`,0 AS `kitchen_manpower`,0 AS `frontline_manpower`,0 AS `overtime`,0 AS `undertime`,`d`.`quota_target` AS `quota_target`,100 AS `cogs_threshold`,100 AS `mp_threshold`,20 AS `np_threshold` from (`demic_daily_reports` `d` left join (select `demiclab_expenses`.`expense_date` AS `expense_date`,sum(`demiclab_expenses`.`row_total`) AS `total_exp` from `demiclab_expenses` group by `demiclab_expenses`.`expense_date`) `e` on(`e`.`expense_date` = `d`.`report_date`)) where `d`.`store_name` = 'DemicLab-Main' union all select `d`.`id` AS `id`,`d`.`report_date` AS `report_date`,`d`.`store_name` AS `store_name`,`d`.`sales_revenue` AS `gross_sales`,`d`.`sales_discount` AS `sales_discount`,0 AS `gulay_commissary`,0 AS `direct_purchases`,coalesce(`e`.`total_exp`,0) AS `other_expenses`,0 AS `kitchen_manpower`,0 AS `frontline_manpower`,0 AS `overtime`,0 AS `undertime`,`d`.`quota_target` AS `quota_target`,100 AS `cogs_threshold`,100 AS `mp_threshold`,20 AS `np_threshold` from (`demic_daily_reports` `d` left join (select `demiclab_jaro_expenses`.`expense_date` AS `expense_date`,sum(`demiclab_jaro_expenses`.`row_total`) AS `total_exp` from `demiclab_jaro_expenses` group by `demiclab_jaro_expenses`.`expense_date`) `e` on(`e`.`expense_date` = `d`.`report_date`)) where `d`.`store_name` = 'DemicLab-Jaro'  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `commissary_cashflow`
--
ALTER TABLE `commissary_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_cashflow_balance`
--
ALTER TABLE `commissary_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_categories`
--
ALTER TABLE `commissary_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `commissary_cf_vat_selection`
--
ALTER TABLE `commissary_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `commissary_cogs`
--
ALTER TABLE `commissary_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_dinein_rows`
--
ALTER TABLE `commissary_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_disbursement`
--
ALTER TABLE `commissary_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `commissary_expenses`
--
ALTER TABLE `commissary_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `commissary_income_statement`
--
ALTER TABLE `commissary_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `commissary_month_end_inv`
--
ALTER TABLE `commissary_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_pdc`
--
ALTER TABLE `commissary_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_pl_revenue`
--
ALTER TABLE `commissary_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `commissary_purchases`
--
ALTER TABLE `commissary_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `commissary_reconcile`
--
ALTER TABLE `commissary_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `commissary_sales_detail_rows`
--
ALTER TABLE `commissary_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `commissary_sales_report`
--
ALTER TABLE `commissary_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `commissary_supplier`
--
ALTER TABLE `commissary_supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_supplier_name` (`supplier_name`);

--
-- Indexes for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `demiclab_acc_titles`
--
ALTER TABLE `demiclab_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `demiclab_bank_statement`
--
ALTER TABLE `demiclab_bank_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `demiclab_bank_statement_locks`
--
ALTER TABLE `demiclab_bank_statement_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `demiclab_bank_statement_rows`
--
ALTER TABLE `demiclab_bank_statement_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_cashflow`
--
ALTER TABLE `demiclab_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_cashflow_balance`
--
ALTER TABLE `demiclab_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_cashflow_withdrawal_rows`
--
ALTER TABLE `demiclab_cashflow_withdrawal_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_categories`
--
ALTER TABLE `demiclab_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `demiclab_categories_meta`
--
ALTER TABLE `demiclab_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `demiclab_cogs`
--
ALTER TABLE `demiclab_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_dinein_rows`
--
ALTER TABLE `demiclab_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_disbursement`
--
ALTER TABLE `demiclab_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `demiclab_expenses`
--
ALTER TABLE `demiclab_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `demiclab_income_statement`
--
ALTER TABLE `demiclab_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `demiclab_jaro_acc_titles`
--
ALTER TABLE `demiclab_jaro_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `demiclab_jaro_cashflow`
--
ALTER TABLE `demiclab_jaro_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_cashflow_balance`
--
ALTER TABLE `demiclab_jaro_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_categories`
--
ALTER TABLE `demiclab_jaro_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `demiclab_jaro_categories_meta`
--
ALTER TABLE `demiclab_jaro_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `demiclab_jaro_cf_vat_selection`
--
ALTER TABLE `demiclab_jaro_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `demiclab_jaro_cogs`
--
ALTER TABLE `demiclab_jaro_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_dinein_rows`
--
ALTER TABLE `demiclab_jaro_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_disbursement`
--
ALTER TABLE `demiclab_jaro_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `demiclab_jaro_expenses`
--
ALTER TABLE `demiclab_jaro_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `demiclab_jaro_month_end_inv`
--
ALTER TABLE `demiclab_jaro_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_pl_revenue`
--
ALTER TABLE `demiclab_jaro_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `demiclab_jaro_reconcile`
--
ALTER TABLE `demiclab_jaro_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `demiclab_jaro_sales_detail_rows`
--
ALTER TABLE `demiclab_jaro_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_jaro_sales_report`
--
ALTER TABLE `demiclab_jaro_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `demiclab_month_end_inv`
--
ALTER TABLE `demiclab_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_pdc`
--
ALTER TABLE `demiclab_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_pl_revenue`
--
ALTER TABLE `demiclab_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `demiclab_reconcile`
--
ALTER TABLE `demiclab_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `demiclab_report_entries`
--
ALTER TABLE `demiclab_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `demiclab_sales_detail_rows`
--
ALTER TABLE `demiclab_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demiclab_sales_report`
--
ALTER TABLE `demiclab_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `demic_balance_sheet`
--
ALTER TABLE `demic_balance_sheet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_stmt_date` (`stmt_date`);

--
-- Indexes for table `demic_daily_cashflow`
--
ALTER TABLE `demic_daily_cashflow`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`);

--
-- Indexes for table `demic_daily_reports`
--
ALTER TABLE `demic_daily_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date_store` (`report_date`,`store_name`);

--
-- Indexes for table `demic_discounts`
--
ALTER TABLE `demic_discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `demic_purchases`
--
ALTER TABLE `demic_purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `demic_sales_distribution`
--
ALTER TABLE `demic_sales_distribution`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`);

--
-- Indexes for table `demic_sales_report_summary`
--
ALTER TABLE `demic_sales_report_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date_store` (`entry_date`,`store_name`);

--
-- Indexes for table `demic_tests`
--
ALTER TABLE `demic_tests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `test_name` (`test_name`);

--
-- Indexes for table `dois_acc_titles`
--
ALTER TABLE `dois_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `dois_cashflow`
--
ALTER TABLE `dois_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_cashflow_balance`
--
ALTER TABLE `dois_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_categories`
--
ALTER TABLE `dois_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `dois_categories_meta`
--
ALTER TABLE `dois_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `dois_cf_vat_selection`
--
ALTER TABLE `dois_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `dois_cogs`
--
ALTER TABLE `dois_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_dinein_rows`
--
ALTER TABLE `dois_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_disbursement`
--
ALTER TABLE `dois_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `dois_expenses`
--
ALTER TABLE `dois_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `dois_income_statement`
--
ALTER TABLE `dois_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `dois_month_end_inv`
--
ALTER TABLE `dois_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_pdc`
--
ALTER TABLE `dois_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_pl_revenue`
--
ALTER TABLE `dois_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `dois_reconcile`
--
ALTER TABLE `dois_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `dois_report_entries`
--
ALTER TABLE `dois_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `dois_sales_detail_rows`
--
ALTER TABLE `dois_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dois_sales_report`
--
ALTER TABLE `dois_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `h_acc_titles`
--
ALTER TABLE `h_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `h_bank_statement`
--
ALTER TABLE `h_bank_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `h_bank_statement_locks`
--
ALTER TABLE `h_bank_statement_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `h_bank_statement_rows`
--
ALTER TABLE `h_bank_statement_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_carwash_cash_rows`
--
ALTER TABLE `h_carwash_cash_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_carwash_detail_rows`
--
ALTER TABLE `h_carwash_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_carwash_income_statement`
--
ALTER TABLE `h_carwash_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`stmt_date`,`store_name`),
  ADD KEY `idx_date` (`stmt_date`);

--
-- Indexes for table `h_carwash_marketing_rows`
--
ALTER TABLE `h_carwash_marketing_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_carwash_report`
--
ALTER TABLE `h_carwash_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `h_carwash_services`
--
ALTER TABLE `h_carwash_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `h_carwash_summary_entries`
--
ALTER TABLE `h_carwash_summary_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `h_carwash_transactions`
--
ALTER TABLE `h_carwash_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_cashflow`
--
ALTER TABLE `h_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_cashflow_balance`
--
ALTER TABLE `h_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_cashflow_recon`
--
ALTER TABLE `h_cashflow_recon`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`recon_year`,`recon_month`);

--
-- Indexes for table `h_cashflow_recon_payable_rows`
--
ALTER TABLE `h_cashflow_recon_payable_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_cashflow_recon_receivable_rows`
--
ALTER TABLE `h_cashflow_recon_receivable_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_cashflow_withdrawal_rows`
--
ALTER TABLE `h_cashflow_withdrawal_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_categories`
--
ALTER TABLE `h_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `h_categories_meta`
--
ALTER TABLE `h_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `h_cf_vat_selection`
--
ALTER TABLE `h_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `h_check_report`
--
ALTER TABLE `h_check_report`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_check_report_summary`
--
ALTER TABLE `h_check_report_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`cr_year`,`cr_month`);

--
-- Indexes for table `h_cogs`
--
ALTER TABLE `h_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_dinein_rows`
--
ALTER TABLE `h_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_disbursement`
--
ALTER TABLE `h_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `h_expenses`
--
ALTER TABLE `h_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `h_extra_sales_rows`
--
ALTER TABLE `h_extra_sales_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_income_statement`
--
ALTER TABLE `h_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `h_month_end_inv`
--
ALTER TABLE `h_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_pdc`
--
ALTER TABLE `h_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_pl_revenue`
--
ALTER TABLE `h_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `h_reconcile`
--
ALTER TABLE `h_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `h_report_entries`
--
ALTER TABLE `h_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `h_sales_detail_rows`
--
ALTER TABLE `h_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `h_sales_report`
--
ALTER TABLE `h_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `payables_cash_snapshot`
--
ALTER TABLE `payables_cash_snapshot`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payables_ledger`
--
ALTER TABLE `payables_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`);

--
-- Indexes for table `payables_pdc`
--
ALTER TABLE `payables_pdc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`obligation_type`),
  ADD KEY `idx_due` (`due_date`);

--
-- Indexes for table `payables_pdc_sections`
--
ALTER TABLE `payables_pdc_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_name` (`name`);

--
-- Indexes for table `payables_releasing`
--
ALTER TABLE `payables_releasing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payables_summary`
--
ALTER TABLE `payables_summary`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_cogs_monitoring`
--
ALTER TABLE `pub_cogs_monitoring`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_acc_titles`
--
ALTER TABLE `pub_express_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `pub_express_cashflow`
--
ALTER TABLE `pub_express_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_cashflow_balance`
--
ALTER TABLE `pub_express_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_cf_vat_selection`
--
ALTER TABLE `pub_express_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `pub_express_cogs`
--
ALTER TABLE `pub_express_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_dinein_rows`
--
ALTER TABLE `pub_express_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_disbursement`
--
ALTER TABLE `pub_express_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `pub_express_expenses`
--
ALTER TABLE `pub_express_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `pub_express_inv_breakdown`
--
ALTER TABLE `pub_express_inv_breakdown`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_inv_main`
--
ALTER TABLE `pub_express_inv_main`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date_item` (`inv_date`,`store_name`,`item_name`(100));

--
-- Indexes for table `pub_express_inv_summary`
--
ALTER TABLE `pub_express_inv_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_date` (`inv_date`,`store_name`);

--
-- Indexes for table `pub_express_month_end_inv`
--
ALTER TABLE `pub_express_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_pdc`
--
ALTER TABLE `pub_express_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_pl_revenue`
--
ALTER TABLE `pub_express_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `pub_express_reconcile`
--
ALTER TABLE `pub_express_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `pub_express_sales_detail_rows`
--
ALTER TABLE `pub_express_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pub_express_sales_report`
--
ALTER TABLE `pub_express_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `pub_income_statement`
--
ALTER TABLE `pub_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `pub_report_entries`
--
ALTER TABLE `pub_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `receivables_ledger`
--
ALTER TABLE `receivables_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client` (`client_name`),
  ADD KEY `idx_billing` (`billing_date`);

--
-- Indexes for table `receivables_monitoring`
--
ALTER TABLE `receivables_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hmo` (`hmo_name`);

--
-- Indexes for table `receivables_others`
--
ALTER TABLE `receivables_others`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receivables_pastdue`
--
ALTER TABLE `receivables_pastdue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_due` (`due_date`);

--
-- Indexes for table `receivables_soatracker`
--
ALTER TABLE `receivables_soatracker`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `receivables_summary`
--
ALTER TABLE `receivables_summary`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_acc_titles`
--
ALTER TABLE `recovery_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `recovery_cashflow`
--
ALTER TABLE `recovery_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_cashflow_balance`
--
ALTER TABLE `recovery_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_cash_breakdown`
--
ALTER TABLE `recovery_cash_breakdown`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_categories`
--
ALTER TABLE `recovery_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `recovery_categories_meta`
--
ALTER TABLE `recovery_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `recovery_cf_vat_selection`
--
ALTER TABLE `recovery_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `recovery_cogs`
--
ALTER TABLE `recovery_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_commission_fees`
--
ALTER TABLE `recovery_commission_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_dinein_rows`
--
ALTER TABLE `recovery_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_disbursement`
--
ALTER TABLE `recovery_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `recovery_expenses`
--
ALTER TABLE `recovery_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `recovery_gc_sold`
--
ALTER TABLE `recovery_gc_sold`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_income_statement`
--
ALTER TABLE `recovery_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_date` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `recovery_mktg_services`
--
ALTER TABLE `recovery_mktg_services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_month_end_inv`
--
ALTER TABLE `recovery_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_pdc`
--
ALTER TABLE `recovery_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_pl_revenue`
--
ALTER TABLE `recovery_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `recovery_product_sold`
--
ALTER TABLE `recovery_product_sold`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_reconcile`
--
ALTER TABLE `recovery_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `recovery_report_entries`
--
ALTER TABLE `recovery_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `recovery_sales_detail_rows`
--
ALTER TABLE `recovery_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_sales_report`
--
ALTER TABLE `recovery_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `recovery_sales_services`
--
ALTER TABLE `recovery_sales_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ss_date` (`store_name`,`report_date`);

--
-- Indexes for table `recovery_services_pricelist`
--
ALTER TABLE `recovery_services_pricelist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recovery_stylist_handles`
--
ALTER TABLE `recovery_stylist_handles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_locks`
--
ALTER TABLE `report_locks`
  ADD PRIMARY KEY (`store_name`,`report_date`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `stella_acc_titles`
--
ALTER TABLE `stella_acc_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_title` (`title`);

--
-- Indexes for table `stella_cashflow`
--
ALTER TABLE `stella_cashflow`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_cashflow_balance`
--
ALTER TABLE `stella_cashflow_balance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_categories`
--
ALTER TABLE `stella_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_name` (`store_name`,`name`);

--
-- Indexes for table `stella_categories_meta`
--
ALTER TABLE `stella_categories_meta`
  ADD PRIMARY KEY (`store_name`);

--
-- Indexes for table `stella_cf_vat_selection`
--
ALTER TABLE `stella_cf_vat_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`sel_year`,`sel_month`);

--
-- Indexes for table `stella_cogs`
--
ALTER TABLE `stella_cogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_dinein_rows`
--
ALTER TABLE `stella_dinein_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_disbursement`
--
ALTER TABLE `stella_disbursement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`entry_date`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `stella_expenses`
--
ALTER TABLE `stella_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`expense_date`);

--
-- Indexes for table `stella_income_statement`
--
ALTER TABLE `stella_income_statement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_store_month` (`store_name`,`stmt_date`),
  ADD KEY `idx_date` (`stmt_date`),
  ADD KEY `idx_store` (`store_name`),
  ADD KEY `idx_year` (`stmt_year`),
  ADD KEY `idx_month` (`stmt_month`),
  ADD KEY `idx_day` (`stmt_day`);

--
-- Indexes for table `stella_month_end_inv`
--
ALTER TABLE `stella_month_end_inv`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_pdc`
--
ALTER TABLE `stella_pdc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_pl_revenue`
--
ALTER TABLE `stella_pl_revenue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_year_month_type` (`year`,`month`,`rev_type`);

--
-- Indexes for table `stella_reconcile`
--
ALTER TABLE `stella_reconcile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_month` (`store_name`,`rec_year`,`rec_month`);

--
-- Indexes for table `stella_sales_detail_rows`
--
ALTER TABLE `stella_sales_detail_rows`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stella_sales_report`
--
ALTER TABLE `stella_sales_report`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_store_date` (`store_name`,`report_date`);

--
-- Indexes for table `summary_reports`
--
ALTER TABLE `summary_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_month` (`report_month`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `summary_report_entries`
--
ALTER TABLE `summary_report_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date_store` (`report_date`,`store_name`),
  ADD KEY `idx_date` (`report_date`),
  ADD KEY `idx_store` (`store_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vendor_masterlist`
--
ALTER TABLE `vendor_masterlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tin` (`tin`),
  ADD KEY `idx_company_name` (`company_name`);

--
-- Indexes for table `vendor_masterlist_unified`
--
ALTER TABLE `vendor_masterlist_unified`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_tin_address` (`tin`,`address`(150)),
  ADD KEY `idx_tin` (`tin`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `commissary_cashflow`
--
ALTER TABLE `commissary_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_cashflow_balance`
--
ALTER TABLE `commissary_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commissary_categories`
--
ALTER TABLE `commissary_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `commissary_cf_vat_selection`
--
ALTER TABLE `commissary_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `commissary_cogs`
--
ALTER TABLE `commissary_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commissary_dinein_rows`
--
ALTER TABLE `commissary_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_disbursement`
--
ALTER TABLE `commissary_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_expenses`
--
ALTER TABLE `commissary_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `commissary_income_statement`
--
ALTER TABLE `commissary_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_month_end_inv`
--
ALTER TABLE `commissary_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `commissary_pdc`
--
ALTER TABLE `commissary_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commissary_pl_revenue`
--
ALTER TABLE `commissary_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_purchases`
--
ALTER TABLE `commissary_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_reconcile`
--
ALTER TABLE `commissary_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_sales_detail_rows`
--
ALTER TABLE `commissary_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_sales_report`
--
ALTER TABLE `commissary_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissary_supplier`
--
ALTER TABLE `commissary_supplier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_reports`
--
ALTER TABLE `daily_reports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `demiclab_acc_titles`
--
ALTER TABLE `demiclab_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `demiclab_bank_statement`
--
ALTER TABLE `demiclab_bank_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_bank_statement_locks`
--
ALTER TABLE `demiclab_bank_statement_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_bank_statement_rows`
--
ALTER TABLE `demiclab_bank_statement_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_cashflow`
--
ALTER TABLE `demiclab_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_cashflow_balance`
--
ALTER TABLE `demiclab_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_cashflow_withdrawal_rows`
--
ALTER TABLE `demiclab_cashflow_withdrawal_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_categories`
--
ALTER TABLE `demiclab_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `demiclab_cogs`
--
ALTER TABLE `demiclab_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_dinein_rows`
--
ALTER TABLE `demiclab_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_disbursement`
--
ALTER TABLE `demiclab_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `demiclab_expenses`
--
ALTER TABLE `demiclab_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_income_statement`
--
ALTER TABLE `demiclab_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_jaro_acc_titles`
--
ALTER TABLE `demiclab_jaro_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `demiclab_jaro_cashflow`
--
ALTER TABLE `demiclab_jaro_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_cashflow_balance`
--
ALTER TABLE `demiclab_jaro_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_categories`
--
ALTER TABLE `demiclab_jaro_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `demiclab_jaro_cf_vat_selection`
--
ALTER TABLE `demiclab_jaro_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_jaro_cogs`
--
ALTER TABLE `demiclab_jaro_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_dinein_rows`
--
ALTER TABLE `demiclab_jaro_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_disbursement`
--
ALTER TABLE `demiclab_jaro_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_jaro_expenses`
--
ALTER TABLE `demiclab_jaro_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_month_end_inv`
--
ALTER TABLE `demiclab_jaro_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `demiclab_jaro_pl_revenue`
--
ALTER TABLE `demiclab_jaro_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_reconcile`
--
ALTER TABLE `demiclab_jaro_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_sales_detail_rows`
--
ALTER TABLE `demiclab_jaro_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_jaro_sales_report`
--
ALTER TABLE `demiclab_jaro_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_month_end_inv`
--
ALTER TABLE `demiclab_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `demiclab_pdc`
--
ALTER TABLE `demiclab_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demiclab_pl_revenue`
--
ALTER TABLE `demiclab_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_reconcile`
--
ALTER TABLE `demiclab_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demiclab_report_entries`
--
ALTER TABLE `demiclab_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `demiclab_sales_detail_rows`
--
ALTER TABLE `demiclab_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `demiclab_sales_report`
--
ALTER TABLE `demiclab_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `demic_balance_sheet`
--
ALTER TABLE `demic_balance_sheet`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `demic_daily_cashflow`
--
ALTER TABLE `demic_daily_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `demic_daily_reports`
--
ALTER TABLE `demic_daily_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `demic_discounts`
--
ALTER TABLE `demic_discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `demic_purchases`
--
ALTER TABLE `demic_purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `demic_sales_distribution`
--
ALTER TABLE `demic_sales_distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demic_sales_report_summary`
--
ALTER TABLE `demic_sales_report_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `demic_tests`
--
ALTER TABLE `demic_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT for table `dois_acc_titles`
--
ALTER TABLE `dois_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `dois_cashflow`
--
ALTER TABLE `dois_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dois_cashflow_balance`
--
ALTER TABLE `dois_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dois_categories`
--
ALTER TABLE `dois_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `dois_cf_vat_selection`
--
ALTER TABLE `dois_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `dois_cogs`
--
ALTER TABLE `dois_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dois_dinein_rows`
--
ALTER TABLE `dois_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `dois_disbursement`
--
ALTER TABLE `dois_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `dois_expenses`
--
ALTER TABLE `dois_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dois_income_statement`
--
ALTER TABLE `dois_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dois_month_end_inv`
--
ALTER TABLE `dois_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `dois_pdc`
--
ALTER TABLE `dois_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dois_pl_revenue`
--
ALTER TABLE `dois_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dois_reconcile`
--
ALTER TABLE `dois_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dois_report_entries`
--
ALTER TABLE `dois_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `dois_sales_detail_rows`
--
ALTER TABLE `dois_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=351;

--
-- AUTO_INCREMENT for table `dois_sales_report`
--
ALTER TABLE `dois_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `h_acc_titles`
--
ALTER TABLE `h_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `h_bank_statement`
--
ALTER TABLE `h_bank_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `h_bank_statement_locks`
--
ALTER TABLE `h_bank_statement_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `h_bank_statement_rows`
--
ALTER TABLE `h_bank_statement_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `h_carwash_cash_rows`
--
ALTER TABLE `h_carwash_cash_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `h_carwash_detail_rows`
--
ALTER TABLE `h_carwash_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `h_carwash_income_statement`
--
ALTER TABLE `h_carwash_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `h_carwash_marketing_rows`
--
ALTER TABLE `h_carwash_marketing_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `h_carwash_report`
--
ALTER TABLE `h_carwash_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `h_carwash_services`
--
ALTER TABLE `h_carwash_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `h_carwash_summary_entries`
--
ALTER TABLE `h_carwash_summary_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `h_carwash_transactions`
--
ALTER TABLE `h_carwash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `h_cashflow`
--
ALTER TABLE `h_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `h_cashflow_balance`
--
ALTER TABLE `h_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `h_cashflow_recon`
--
ALTER TABLE `h_cashflow_recon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `h_cashflow_recon_payable_rows`
--
ALTER TABLE `h_cashflow_recon_payable_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `h_cashflow_recon_receivable_rows`
--
ALTER TABLE `h_cashflow_recon_receivable_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `h_cashflow_withdrawal_rows`
--
ALTER TABLE `h_cashflow_withdrawal_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `h_categories`
--
ALTER TABLE `h_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `h_cf_vat_selection`
--
ALTER TABLE `h_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `h_check_report`
--
ALTER TABLE `h_check_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `h_check_report_summary`
--
ALTER TABLE `h_check_report_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `h_cogs`
--
ALTER TABLE `h_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `h_dinein_rows`
--
ALTER TABLE `h_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `h_disbursement`
--
ALTER TABLE `h_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `h_expenses`
--
ALTER TABLE `h_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `h_extra_sales_rows`
--
ALTER TABLE `h_extra_sales_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `h_income_statement`
--
ALTER TABLE `h_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `h_month_end_inv`
--
ALTER TABLE `h_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `h_pdc`
--
ALTER TABLE `h_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `h_pl_revenue`
--
ALTER TABLE `h_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `h_reconcile`
--
ALTER TABLE `h_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `h_report_entries`
--
ALTER TABLE `h_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `h_sales_detail_rows`
--
ALTER TABLE `h_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=433;

--
-- AUTO_INCREMENT for table `h_sales_report`
--
ALTER TABLE `h_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `payables_ledger`
--
ALTER TABLE `payables_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payables_pdc`
--
ALTER TABLE `payables_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payables_pdc_sections`
--
ALTER TABLE `payables_pdc_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payables_releasing`
--
ALTER TABLE `payables_releasing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payables_summary`
--
ALTER TABLE `payables_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_cogs_monitoring`
--
ALTER TABLE `pub_cogs_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pub_express_acc_titles`
--
ALTER TABLE `pub_express_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `pub_express_cashflow`
--
ALTER TABLE `pub_express_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_express_cashflow_balance`
--
ALTER TABLE `pub_express_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pub_express_cf_vat_selection`
--
ALTER TABLE `pub_express_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `pub_express_cogs`
--
ALTER TABLE `pub_express_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_express_dinein_rows`
--
ALTER TABLE `pub_express_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_express_disbursement`
--
ALTER TABLE `pub_express_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pub_express_expenses`
--
ALTER TABLE `pub_express_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pub_express_inv_breakdown`
--
ALTER TABLE `pub_express_inv_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `pub_express_inv_main`
--
ALTER TABLE `pub_express_inv_main`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `pub_express_inv_summary`
--
ALTER TABLE `pub_express_inv_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `pub_express_month_end_inv`
--
ALTER TABLE `pub_express_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pub_express_pdc`
--
ALTER TABLE `pub_express_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pub_express_pl_revenue`
--
ALTER TABLE `pub_express_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_express_reconcile`
--
ALTER TABLE `pub_express_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_express_sales_detail_rows`
--
ALTER TABLE `pub_express_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `pub_express_sales_report`
--
ALTER TABLE `pub_express_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pub_income_statement`
--
ALTER TABLE `pub_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pub_report_entries`
--
ALTER TABLE `pub_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receivables_ledger`
--
ALTER TABLE `receivables_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receivables_monitoring`
--
ALTER TABLE `receivables_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `receivables_others`
--
ALTER TABLE `receivables_others`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `receivables_pastdue`
--
ALTER TABLE `receivables_pastdue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `receivables_soatracker`
--
ALTER TABLE `receivables_soatracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `receivables_summary`
--
ALTER TABLE `receivables_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_acc_titles`
--
ALTER TABLE `recovery_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `recovery_cashflow`
--
ALTER TABLE `recovery_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_cashflow_balance`
--
ALTER TABLE `recovery_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `recovery_cash_breakdown`
--
ALTER TABLE `recovery_cash_breakdown`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=430;

--
-- AUTO_INCREMENT for table `recovery_categories`
--
ALTER TABLE `recovery_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `recovery_cf_vat_selection`
--
ALTER TABLE `recovery_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `recovery_cogs`
--
ALTER TABLE `recovery_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_commission_fees`
--
ALTER TABLE `recovery_commission_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `recovery_dinein_rows`
--
ALTER TABLE `recovery_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recovery_disbursement`
--
ALTER TABLE `recovery_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recovery_expenses`
--
ALTER TABLE `recovery_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `recovery_gc_sold`
--
ALTER TABLE `recovery_gc_sold`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `recovery_income_statement`
--
ALTER TABLE `recovery_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_mktg_services`
--
ALTER TABLE `recovery_mktg_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `recovery_month_end_inv`
--
ALTER TABLE `recovery_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `recovery_pdc`
--
ALTER TABLE `recovery_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `recovery_pl_revenue`
--
ALTER TABLE `recovery_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recovery_product_sold`
--
ALTER TABLE `recovery_product_sold`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `recovery_reconcile`
--
ALTER TABLE `recovery_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recovery_report_entries`
--
ALTER TABLE `recovery_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `recovery_sales_detail_rows`
--
ALTER TABLE `recovery_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `recovery_sales_report`
--
ALTER TABLE `recovery_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `recovery_sales_services`
--
ALTER TABLE `recovery_sales_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `recovery_services_pricelist`
--
ALTER TABLE `recovery_services_pricelist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `recovery_stylist_handles`
--
ALTER TABLE `recovery_stylist_handles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `stella_acc_titles`
--
ALTER TABLE `stella_acc_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `stella_cashflow`
--
ALTER TABLE `stella_cashflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stella_cashflow_balance`
--
ALTER TABLE `stella_cashflow_balance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stella_categories`
--
ALTER TABLE `stella_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `stella_cf_vat_selection`
--
ALTER TABLE `stella_cf_vat_selection`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=262;

--
-- AUTO_INCREMENT for table `stella_cogs`
--
ALTER TABLE `stella_cogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stella_dinein_rows`
--
ALTER TABLE `stella_dinein_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `stella_disbursement`
--
ALTER TABLE `stella_disbursement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `stella_expenses`
--
ALTER TABLE `stella_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `stella_income_statement`
--
ALTER TABLE `stella_income_statement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stella_month_end_inv`
--
ALTER TABLE `stella_month_end_inv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stella_pdc`
--
ALTER TABLE `stella_pdc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stella_pl_revenue`
--
ALTER TABLE `stella_pl_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stella_reconcile`
--
ALTER TABLE `stella_reconcile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stella_sales_detail_rows`
--
ALTER TABLE `stella_sales_detail_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=390;

--
-- AUTO_INCREMENT for table `stella_sales_report`
--
ALTER TABLE `stella_sales_report`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `summary_reports`
--
ALTER TABLE `summary_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `summary_report_entries`
--
ALTER TABLE `summary_report_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT for table `vendor_masterlist`
--
ALTER TABLE `vendor_masterlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_masterlist_unified`
--
ALTER TABLE `vendor_masterlist_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2577;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
