-- =====================================================================
-- EL-FAITH MICROFINANCE - LOAN COLLECTION & MANAGEMENT SYSTEM
-- Raw PHP + MySQL Database Schema (Shared cPanel & Local Compatible)
-- Location: Wassa Powuako, Western Region, Ghana
-- Currency: Ghana Cedis (GH₵) - Stored in Integer Pesewas (100 pesewas = GH₵ 1.00)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `notifications_log`;
DROP TABLE IF EXISTS `cash_reconciliations`;
DROP TABLE IF EXISTS `agent_assignments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `repayment_schedule`;
DROP TABLE IF EXISTS `loans`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `system_settings`;

-- ---------------------------------------------------------------------
-- 1. USERS TABLE (Admin & Field Agents)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL UNIQUE,
  `staff_code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'agent') NOT NULL DEFAULT 'agent',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`),
  INDEX `idx_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. CUSTOMERS TABLE (Borrowers - strictly no login)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL UNIQUE,
  `customer_code` VARCHAR(30) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `alt_phone` VARCHAR(20) NULL,
  `id_type` VARCHAR(50) NOT NULL DEFAULT 'Ghana Card',
  `id_number` VARCHAR(50) NOT NULL,
  `residential_address` TEXT NOT NULL,
  `location_landmark` VARCHAR(255) NOT NULL,
  `gps_lat` DECIMAL(10, 8) NULL,
  `gps_lng` DECIMAL(11, 8) NULL,
  `occupation` VARCHAR(100) NOT NULL,
  `guarantor_name` VARCHAR(150) NOT NULL,
  `guarantor_phone` VARCHAR(20) NOT NULL,
  `guarantor_relation` VARCHAR(50) NOT NULL,
  `guarantor_address` TEXT NULL,
  `assigned_agent_id` INT NULL,
  `created_by` INT NOT NULL,
  `status` ENUM('active', 'inactive', 'blacklisted') NOT NULL DEFAULT 'active',
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_customers_assigned_agent` FOREIGN KEY (`assigned_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_customers_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  INDEX `idx_customers_assigned_agent` (`assigned_agent_id`),
  INDEX `idx_customers_phone` (`phone`),
  INDEX `idx_customers_id_number` (`id_number`),
  INDEX `idx_customers_status` (`status`),
  INDEX `idx_customers_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. LOANS TABLE (Financial contracts, all money stored in pesewas)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `loans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL UNIQUE,
  `loan_code` VARCHAR(30) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `agent_id` INT NULL,
  `principal_pesewas` BIGINT NOT NULL,
  `interest_rate` DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
  `interest_method` ENUM('flat', 'reducing_balance') NOT NULL DEFAULT 'flat',
  `interest_pesewas` BIGINT NOT NULL,
  `total_payable_pesewas` BIGINT NOT NULL,
  `total_paid_pesewas` BIGINT NOT NULL DEFAULT 0,
  `remaining_balance_pesewas` BIGINT NOT NULL,
  `term_duration` INT NOT NULL,
  `term_period` ENUM('days', 'weeks', 'months') NOT NULL DEFAULT 'months',
  `repayment_frequency` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
  `installment_amount_pesewas` BIGINT NOT NULL,
  `disbursed_at` DATETIME NULL,
  `first_due_date` DATE NOT NULL,
  `maturity_date` DATE NOT NULL,
  `status` ENUM('pending', 'active', 'overdue', 'completed', 'defaulted', 'written_off') NOT NULL DEFAULT 'active',
  `created_by` INT NOT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_loans_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_loans_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_loans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  INDEX `idx_loans_customer` (`customer_id`),
  INDEX `idx_loans_agent` (`agent_id`),
  INDEX `idx_loans_status` (`status`),
  INDEX `idx_loans_due_date` (`first_due_date`, `maturity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. REPAYMENT SCHEDULE TABLE (Generated Installments)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `repayment_schedule` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `loan_id` INT NOT NULL,
  `installment_number` INT NOT NULL,
  `due_date` DATE NOT NULL,
  `expected_amount_pesewas` BIGINT NOT NULL,
  `principal_portion_pesewas` BIGINT NOT NULL,
  `interest_portion_pesewas` BIGINT NOT NULL,
  `paid_amount_pesewas` BIGINT NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'partially_paid', 'paid', 'overdue') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_schedule_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  INDEX `idx_sched_loan_inst` (`loan_id`, `installment_number`),
  INDEX `idx_sched_due_date` (`due_date`),
  INDEX `idx_sched_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. PAYMENTS TABLE (Append-Only Ledger, Mistake = Reversal entry)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(36) NOT NULL UNIQUE,
  `receipt_number` VARCHAR(40) NOT NULL UNIQUE,
  `loan_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `agent_id` INT NOT NULL,
  `amount_pesewas` BIGINT NOT NULL,
  `payment_method` ENUM('cash', 'momo', 'bank_transfer') NOT NULL DEFAULT 'cash',
  `momo_reference` VARCHAR(100) NULL,
  `collection_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gps_lat` DECIMAL(10, 8) NULL,
  `gps_lng` DECIMAL(11, 8) NULL,
  `note` TEXT NULL,
  `is_reversal` TINYINT(1) NOT NULL DEFAULT 0,
  `reversal_of_payment_id` INT NULL,
  `reversal_reason` TEXT NULL,
  `status` ENUM('recorded', 'reversed') NOT NULL DEFAULT 'recorded',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_payments_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`),
  CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_payments_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_payments_reversal` FOREIGN KEY (`reversal_of_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  INDEX `idx_payments_loan` (`loan_id`),
  INDEX `idx_payments_customer` (`customer_id`),
  INDEX `idx_payments_agent` (`agent_id`),
  INDEX `idx_payments_created` (`collection_timestamp`),
  INDEX `idx_payments_method` (`payment_method`),
  INDEX `idx_payments_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. AGENT ASSIGNMENTS TABLE (Routes & Customer allocation)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `agent_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `customer_id` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `route_day` ENUM('all_days', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday') NOT NULL DEFAULT 'all_days',
  `notes` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_assign_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  UNIQUE KEY `uk_agent_customer` (`agent_id`, `customer_id`),
  INDEX `idx_assign_route_day` (`route_day`),
  INDEX `idx_assign_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. CASH RECONCILIATIONS TABLE (Collected vs Handed In at Office)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_reconciliations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent_id` INT NOT NULL,
  `reconciliation_date` DATE NOT NULL,
  `expected_cash_pesewas` BIGINT NOT NULL,
  `handed_in_cash_pesewas` BIGINT NOT NULL,
  `discrepancy_pesewas` BIGINT NOT NULL, -- (handed_in - expected)
  `status` ENUM('pending', 'verified', 'discrepancy_flagged') NOT NULL DEFAULT 'pending',
  `verified_by` INT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_recon_agent` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_recon_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  UNIQUE KEY `uk_agent_date` (`agent_id`, `reconciliation_date`),
  INDEX `idx_recon_date` (`reconciliation_date`),
  INDEX `idx_recon_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. NOTIFICATIONS LOG TABLE (SMS & Email delivery proof)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `channel` ENUM('sms', 'email') NOT NULL DEFAULT 'sms',
  `recipient` VARCHAR(150) NOT NULL,
  `customer_id` INT NULL,
  `payment_id` INT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('queued', 'sent', 'failed', 'delivered') NOT NULL DEFAULT 'queued',
  `attempts` INT NOT NULL DEFAULT 0,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'simulated',
  `provider_response` TEXT NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  INDEX `idx_notif_status` (`status`),
  INDEX `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. AUDIT LOG TABLE (Immutable Security & Operations Trail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `table_name` VARCHAR(50) NULL,
  `record_id` INT NULL,
  `old_values` LONGTEXT NULL,
  `new_values` LONGTEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_table` (`table_name`, `record_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. SYSTEM SETTINGS TABLE (Config key-value store)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
