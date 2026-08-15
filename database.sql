-- --------------------------------------------------------
-- IFW Global Intelligence & Asset Recovery Portal
-- Master Database Schema (Full Production v10)
-- --------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- 1. Users & RBAC
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NULL,
  `role` ENUM('superadmin', 'admin', 'staff', 'agent', 'viewer') DEFAULT 'admin',
  `password_hash` VARCHAR(255) NOT NULL,
  `pin_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `display_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `permission_key` VARCHAR(100) NOT NULL UNIQUE,
  `permission_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 2. Clients & Accounts
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NULL,
  `pin_hash` VARCHAR(255) NULL,
  `assigned_agent_id` INT NULL,
  `phone` VARCHAR(50) NULL,
  `country` VARCHAR(100) NULL,
  `dob` DATE NULL,
  `address` TEXT NULL,
  `preferred_currency` VARCHAR(10) DEFAULT 'USD',
  `status` ENUM('Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery', 'Closed') DEFAULT 'Received',
  `private_notes` TEXT NULL,
  `last_seen` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_agent` (`assigned_agent_id`),
  FOREIGN KEY (`assigned_agent_id`) REFERENCES `IFW_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Cases & Investigations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_cases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_number` VARCHAR(100) NULL UNIQUE,
  `client_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `case_type` VARCHAR(50) DEFAULT 'Recovery',
  `priority` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
  `description` TEXT NULL,
  `amount_lost` DECIMAL(15,2) DEFAULT 0.00,
  `amount_recovered` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `status` ENUM('Received', 'Investigating', 'Evidence Gathered', 'Legal Action', 'Recovery', 'Settled', 'Closed') DEFAULT 'Received',
  `lifecycle_stage` INT DEFAULT 1,
  `progress_percent` INT DEFAULT 20,
  `stage_1_title` VARCHAR(100) DEFAULT 'Intelligence Intake',
  `stage_2_title` VARCHAR(100) DEFAULT 'Forensic Asset Tracing',
  `stage_3_title` VARCHAR(100) DEFAULT 'Cross-Border Injunctions',
  `stage_4_title` VARCHAR(100) DEFAULT 'Judicial Seizure & Freezing',
  `stage_5_title` VARCHAR(100) DEFAULT 'Disbursement & Settlement',
  `flow_node_1` VARCHAR(100) DEFAULT 'Intake & Verification',
  `flow_node_2` VARCHAR(100) DEFAULT 'Blockchain & Banking Tracing',
  `flow_node_3` VARCHAR(100) DEFAULT 'Multi-Jurisdiction Injunction',
  `flow_node_4` VARCHAR(100) DEFAULT 'Repatriation & Escrow Settlement',
  `show_lifecycle_bar` TINYINT(1) DEFAULT 1,
  `show_flow_visualizer` TINYINT(1) DEFAULT 1,
  `show_blockchain_watcher` TINYINT(1) DEFAULT 0,
  `show_settlement_escrow` TINYINT(1) DEFAULT 0,
  `show_recovery_map` TINYINT(1) DEFAULT 0,
  `closing_notes` TEXT NULL,
  `satisfaction_requested` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_case_timeline` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `created_by` INT NULL,
  `milestone_title` VARCHAR(255) NOT NULL,
  `milestone_body` TEXT NULL,
  `milestone_date` DATE NULL,
  `status_color` VARCHAR(20) DEFAULT 'primary',
  `icon` VARCHAR(50) DEFAULT 'circle',
  `is_client_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`case_id`) REFERENCES `IFW_cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_case_notes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `case_id` INT NULL,
  `agent_id` INT NOT NULL,
  `note_text` TEXT NOT NULL,
  `note` TEXT NULL,
  `is_visible_to_client` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`) REFERENCES `IFW_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_case_ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `rating` TINYINT NOT NULL,
  `feedback` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_case_rating` (`case_id`, `client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 4. Feature 1: Blockchain Watcher
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_blockchain_wallets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `crypto_type` VARCHAR(50) NOT NULL,
  `wallet_address` VARCHAR(255) NOT NULL,
  `wallet_label` VARCHAR(255) NULL,
  `balance` DECIMAL(20,8) DEFAULT 0.00000000,
  `usd_value` DECIMAL(15,2) DEFAULT 0.00,
  `risk_score` INT DEFAULT 90,
  `threat_level` VARCHAR(50) DEFAULT 'CRITICAL / FRAUDSTER AGGREGATOR',
  `exchange_tags` VARCHAR(255) NULL,
  `status` VARCHAR(50) DEFAULT 'Monitored',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`case_id`) REFERENCES `IFW_cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_blockchain_txs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `wallet_id` INT NOT NULL,
  `case_id` INT NOT NULL,
  `tx_hash` VARCHAR(255) NOT NULL,
  `tx_type` ENUM('INCOMING', 'OUTGOING', 'HOP', 'EXCHANGE_DEPOSIT') DEFAULT 'HOP',
  `amount` DECIMAL(20,8) NOT NULL,
  `usd_amount` DECIMAL(15,2) DEFAULT 0.00,
  `from_address` VARCHAR(255) NOT NULL,
  `to_address` VARCHAR(255) NOT NULL,
  `cluster_tag` VARCHAR(100) NULL,
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_wallet` (`wallet_id`),
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`wallet_id`) REFERENCES `IFW_blockchain_wallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 5. Feature 2: Escrow & Settlement Hub
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_case_settlements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL UNIQUE,
  `client_id` INT NOT NULL,
  `gross_recovered` DECIMAL(15,2) DEFAULT 0.00,
  `fee_percent` DECIMAL(5,2) DEFAULT 10.00,
  `fee_amount` DECIMAL(15,2) DEFAULT 0.00,
  `net_payout` DECIMAL(15,2) DEFAULT 0.00,
  `escrow_ref` VARCHAR(100) NULL,
  `custody_entity` VARCHAR(255) DEFAULT 'Swiss Multi-Sig Escrow Vault (FINMA Compliant)',
  `clearance_stage` INT DEFAULT 1,
  `status` VARCHAR(100) DEFAULT 'Secured in Escrow',
  `payout_method` VARCHAR(100) NULL,
  `payout_destination_details` TEXT NULL,
  `client_confirmed_at` DATETIME NULL,
  `client_signature_hash` VARCHAR(255) NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_case` (`case_id`),
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`case_id`) REFERENCES `IFW_cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 6. Feature 3: Global Recovery Radar & Jurisdictions
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_case_jurisdictions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `case_id` INT NOT NULL,
  `country_code` VARCHAR(10) NOT NULL,
  `country_name` VARCHAR(100) NOT NULL,
  `city_court` VARCHAR(255) NULL,
  `action_type` VARCHAR(255) NOT NULL,
  `case_ref` VARCHAR(100) NULL,
  `status` VARCHAR(100) DEFAULT 'Active Freeze Order',
  `date_filed` DATE NULL,
  `notes` TEXT NULL,
  `is_enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_case` (`case_id`),
  FOREIGN KEY (`case_id`) REFERENCES `IFW_cases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. Feature 4: Security, Authentication & Login History
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_login_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `role` ENUM('client', 'admin', 'staff') NOT NULL DEFAULT 'client',
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(100) NOT NULL,
  `user_agent` VARCHAR(500) NULL,
  `device_type` VARCHAR(50) DEFAULT 'Desktop',
  `browser` VARCHAR(100) NULL,
  `os` VARCHAR(100) NULL,
  `city_country` VARCHAR(255) NULL,
  `is_new_device` TINYINT(1) DEFAULT 0,
  `login_status` ENUM('success', 'failed_credentials', 'failed_otp') DEFAULT 'success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(50) DEFAULT 'client',
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_type` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 8. Invoicing, Payments & Late Fees
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(100) NULL UNIQUE,
  `client_id` INT NOT NULL,
  `case_id` INT NULL,
  `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(15,2) DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `tax_amount` DECIMAL(15,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_amount` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `notes` TEXT NULL,
  `payment_info` TEXT NULL,
  `issue_date` DATE NULL,
  `due_date` DATE NULL,
  `late_fee_enabled` TINYINT(1) DEFAULT 0,
  `late_fee_type` ENUM('daily','weekly','monthly','hourly') DEFAULT 'daily',
  `late_fee_amount` DECIMAL(10,2) DEFAULT 0.00,
  `late_fee_start_date` DATE NULL,
  `late_fee_accumulated` DECIMAL(15,2) DEFAULT 0.00,
  `late_fee_is_percentage` TINYINT(1) DEFAULT 0,
  `has_instalments` TINYINT(1) DEFAULT 0,
  `status` ENUM('unpaid', 'paid', 'pending_verification', 'partially_paid', 'cancelled') DEFAULT 'unpaid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `qty` DECIMAL(10,2) DEFAULT 1.00,
  `rate` DECIMAL(15,2) DEFAULT 0.00,
  `amount` DECIMAL(15,2) DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoice` (`invoice_id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `IFW_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_invoice_payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'USD',
  `payment_method` VARCHAR(100) NULL,
  `reference_number` VARCHAR(200) NULL,
  `proof_file` VARCHAR(500) NULL,
  `notes` TEXT NULL,
  `status` ENUM('Pending','Confirmed','Rejected') DEFAULT 'Pending',
  `reviewed_by` INT NULL,
  `reviewed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_invoice` (`invoice_id`),
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `IFW_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_invoice_instalments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT NOT NULL,
  `instalment_number` INT DEFAULT 1,
  `amount` DECIMAL(15,2) NOT NULL,
  `due_date` DATE NULL,
  `status` ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
  `paid_at` TIMESTAMP NULL,
  `notes` VARCHAR(500) NULL,
  INDEX `idx_invoice` (`invoice_id`),
  FOREIGN KEY (`invoice_id`) REFERENCES `IFW_invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 9. Documents, Signatures & KYC
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NULL,
  `document_type` VARCHAR(255) DEFAULT 'Standard',
  `document_body` LONGTEXT NULL,
  `requires_signature` BOOLEAN DEFAULT FALSE,
  `is_signed` BOOLEAN DEFAULT FALSE,
  `signed_at` TIMESTAMP NULL,
  `signature_data` LONGTEXT NULL,
  `signature_ip` VARCHAR(50) NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_kyc_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_name` VARCHAR(100) NOT NULL UNIQUE,
  `field_label` VARCHAR(100) NOT NULL,
  `field_type` VARCHAR(50) NOT NULL,
  `field_options` TEXT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_kyc_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `submission_data` JSON NOT NULL,
  `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `rejection_reason` TEXT NULL,
  `reviewed_by` INT NULL,
  `reviewed_at` TIMESTAMP NULL,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_kyc_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `document_type` ENUM('Government ID', 'Proof of Address', 'Other') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `admin_feedback` TEXT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 10. Live Chat & Messaging
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `sender_type` ENUM('admin', 'client') NOT NULL,
  `sender_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `attachment_path` VARCHAR(500) NULL,
  `attachment_name` VARCHAR(255) NULL,
  `attachment_size` INT DEFAULT 0,
  `email_notified` TINYINT(1) DEFAULT 0,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_chat_attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message_id` INT NULL,
  `client_id` INT NULL,
  `sender` VARCHAR(20) NULL,
  `file_path` VARCHAR(500) NULL,
  `file_name` VARCHAR(255) NULL,
  `file_type` VARCHAR(100) NULL,
  `file_size` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_chat_status` (
  `user_type` ENUM('client', 'admin') NOT NULL,
  `user_id` INT NOT NULL,
  `is_typing` BOOLEAN DEFAULT FALSE,
  `is_online` BOOLEAN DEFAULT FALSE,
  `last_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `sender` ENUM('admin', 'client') NOT NULL,
  `admin_id` INT NULL,
  `message_text` TEXT NOT NULL,
  `attachment_path` VARCHAR(500) NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NULL,
  `icon` VARCHAR(50) DEFAULT 'bell',
  `link` VARCHAR(500) NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_client` (`client_id`),
  INDEX `idx_read` (`is_read`),
  FOREIGN KEY (`client_id`) REFERENCES `IFW_clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 11. Site Settings, Forms & CMS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `IFW_site_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_testimonials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_name` VARCHAR(100) NOT NULL,
  `location` VARCHAR(100) NULL,
  `testimonial_text` TEXT NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_faqs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_form_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_name` VARCHAR(100) NOT NULL,
  `field_label` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text', 'email', 'textarea', 'select', 'checkbox') NOT NULL,
  `field_options` TEXT NULL,
  `is_required` BOOLEAN DEFAULT FALSE,
  `display_order` INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_form_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `IFW_contact_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `submission_data` JSON NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 12. Default Pre-Seeded Data
-- --------------------------------------------------------

-- Default Admin User (username: admin | password: Password123! | PIN: 1234)
INSERT INTO `IFW_users` (`id`, `username`, `email`, `full_name`, `role`, `password_hash`, `pin_hash`) VALUES 
(1, 'admin', 'admin@example.com', 'Senior Forensic Director', 'admin', '$2y$10$ohEO9ShUK15XG/a69ZXXI.8ewNdkhr2GXgnRwkJhrM.qnI5yrwgXO', '$2y$10$QafXQGeBpjj0Do3KZGHsy.VcUcGe13foakJon/tTWXTzawVh./rPG')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Default Site Settings
INSERT INTO `IFW_site_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'IFW Global Intelligence & Asset Recovery'),
('contact_email', 'investigations@ifwglobalrecovery.site'),
('company_name', 'IFW Global'),
('company_tagline', 'Intelligence-Led Cyber & Financial Investigations'),
('company_address', 'Level 5, 20 Bond Street, Sydney NSW 2000, Australia'),
('footer_about', 'IFW Global is a private intelligence and asset recovery firm operating globally to investigate cyber fraud, investment scams, and major multi-jurisdictional financial crimes.'),
('chat_provider', 'internal'),
('tawkto_property_id', ''),
('tawkto_widget_id', 'default'),
('smtp_host', 'mail.privateemail.com'),
('smtp_port', '465'),
('smtp_user', 'notifications@ifwglobal.com'),
('smtp_pass', ''),
('smtp_from_email', 'notifications@ifwglobal.com'),
('smtp_from_name', 'IFW Global Portal'),
('smtp_secure', 'ssl'),
('currency_api_key', ''),
('recipient_email', 'investigations@ifwglobalrecovery.site'),
('bank_swift_iban', 'IFWGAUS33XXX'),
('payment_instructions', 'Payment instructions:\nPlease specify your Invoice Reference in the transfer memo.\nAll payments are processed securely through certified escrow vaults.'),
('crypto_usdt_trc20_address', 'TXy7n3K19oP4mQ9wLv8B2xZ5cR6vN1aM4t'),
('crypto_usdt_erc20_address', '0x71C8FB96E7d832A2DFeF67e0e7a27F993A9e8e5A'),
('crypto_btc_address', 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq'),
('crypto_eth_address', '0x71C8FB96E7d832A2DFeF67e0e7a27F993A9e8e5A'),
('hero_headline', 'Global Intelligence & High-Stakes Asset Recovery'),
('hero_subheadline', 'Cross-border cyber fraud investigations, blockchain forensics, and multi-jurisdictional asset repatriation.'),
('maintenance_mode', '0'),
('show_lifecycle_tracker', '1'),
('show_fund_flow_visualizer', '1')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default Form Settings
INSERT INTO `IFW_form_settings` (`setting_key`, `setting_value`) VALUES 
('recipient_email', 'investigations@ifwglobalrecovery.site'),
('success_message', 'Thank you for contacting IFW Global. A senior forensic investigator will review your file and respond shortly.')
ON DUPLICATE KEY UPDATE `setting_key`=`setting_key`;

-- Default KYC Fields
INSERT INTO `IFW_kyc_fields` (`id`, `field_name`, `field_label`, `field_type`, `field_options`, `is_required`, `sort_order`) VALUES
(1, 'government_id', 'Government Issued ID (Passport / Driver\'s License)', 'file', NULL, 1, 1),
(2, 'proof_of_address', 'Proof of Address (Utility Bill / Bank Statement)', 'file', NULL, 1, 2),
(3, 'customs_declaration', 'Customs Declaration / Ownership Proof', 'file', NULL, 0, 3)
ON DUPLICATE KEY UPDATE `field_name`=`field_name`;

-- Default Form Fields
INSERT INTO `IFW_form_fields` (`field_name`, `field_label`, `field_type`, `is_required`, `display_order`) VALUES
('first_name', 'First Name', 'text', TRUE, 1),
('last_name', 'Last Name', 'text', TRUE, 2),
('email', 'Email Address', 'email', TRUE, 3),
('phone', 'Phone Number', 'text', FALSE, 4),
('message', 'Message', 'textarea', TRUE, 5)
ON DUPLICATE KEY UPDATE `field_name`=`field_name`;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
