-- PayGate Pro - MySQL Database Schema
-- Character set: utf8mb4, Collation: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- 1. users
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NULL DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('super_admin','admin','finance','support','merchant','staff_merchant') NOT NULL DEFAULT 'merchant',
    `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `permissions` JSON NULL DEFAULT NULL,
    `email_verified` TINYINT NOT NULL DEFAULT 0,
    `verify_token` VARCHAR(64) NULL DEFAULT NULL,
    `verify_token_at` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_merchant_id` (`merchant_id`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 2. merchants
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `merchants` (
    `id` VARCHAR(36) NOT NULL,
    `business_name` VARCHAR(255) NOT NULL,
    `owner_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `status` ENUM('pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    `api_key` VARCHAR(70) NOT NULL,
    `webhook_url` TEXT NULL DEFAULT NULL,
    `redirect_url` TEXT NULL DEFAULT NULL,
    `website` VARCHAR(255) NULL DEFAULT NULL,
    `address` VARCHAR(255) NULL DEFAULT NULL,
    `city` VARCHAR(100) NULL DEFAULT NULL,
    `business_type` VARCHAR(100) NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `fee_type` VARCHAR(20) NULL DEFAULT NULL,
    `fee_value` DECIMAL(10,4) NULL DEFAULT NULL,
    `fee_flat` DECIMAL(10,2) NULL DEFAULT NULL,
    `ip_whitelist` TEXT NULL DEFAULT NULL,
    `payment_expiry_minutes` INT NOT NULL DEFAULT 60,
    `bank_name` VARCHAR(100) NULL DEFAULT NULL,
    `bank_account_number` VARCHAR(50) NULL DEFAULT NULL,
    `bank_account_name` VARCHAR(255) NULL DEFAULT NULL,
    `bank_branch` VARCHAR(100) NULL DEFAULT NULL,
    `notif_email_payment` VARCHAR(255) NULL DEFAULT NULL,
    `notif_email_withdrawal` VARCHAR(255) NULL DEFAULT NULL,
    `notif_wa_payment` VARCHAR(30) NULL DEFAULT NULL,
    `notif_wa_number` VARCHAR(30) NULL DEFAULT NULL,
    `thank_you_message` TEXT NULL DEFAULT NULL,
    `default_redirect_url` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_merchants_api_key` (`api_key`),
    KEY `idx_merchants_status` (`status`),
    KEY `idx_merchants_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 3. transactions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `order_id` VARCHAR(50) NOT NULL,
    `amount` BIGINT NOT NULL,
    `fee` BIGINT NOT NULL DEFAULT 0,
    `fee_type` VARCHAR(20) NULL DEFAULT NULL,
    `fee_rule_id` VARCHAR(36) NULL DEFAULT NULL,
    `fee_snapshot` JSON NULL DEFAULT NULL,
    `net_amount` BIGINT NOT NULL DEFAULT 0,
    `status` ENUM('PENDING','PAID','FAILED','EXPIRED','REFUNDED') NOT NULL DEFAULT 'PENDING',
    `link_name` VARCHAR(255) NULL DEFAULT NULL,
    `customer_name` VARCHAR(255) NULL DEFAULT NULL,
    `customer_wa` VARCHAR(30) NULL DEFAULT NULL,
    `customer_email` VARCHAR(255) NULL DEFAULT NULL,
    `webhook_url` TEXT NULL DEFAULT NULL,
    `redirect_url` TEXT NULL DEFAULT NULL,
    `note` TEXT NULL DEFAULT NULL,
    `payment_url` TEXT NULL DEFAULT NULL,
    `qr_url` TEXT NULL DEFAULT NULL,
    `api_request` TEXT NULL DEFAULT NULL,
    `api_response` TEXT NULL DEFAULT NULL,
    `paid_at` DATETIME NULL DEFAULT NULL,
    `expired_at` DATETIME NULL DEFAULT NULL,
    `payment_channel` VARCHAR(30) NULL DEFAULT 'qris',
    `payment_method` VARCHAR(50) NULL DEFAULT NULL,
    `snap_token` VARCHAR(255) NULL DEFAULT NULL,
    `refund_amount` BIGINT NOT NULL DEFAULT 0,
    `refund_status` VARCHAR(20) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_transactions_merchant_order` (`merchant_id`, `order_id`),
    KEY `idx_transactions_merchant_id` (`merchant_id`),
    KEY `idx_transactions_order_id` (`order_id`),
    KEY `idx_transactions_status` (`status`),
    KEY `idx_transactions_created_at` (`created_at`),
    CONSTRAINT `fk_transactions_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 4. wallets
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `pending_balance` BIGINT NOT NULL DEFAULT 0,
    `available_balance` BIGINT NOT NULL DEFAULT 0,
    `hold_balance` BIGINT NOT NULL DEFAULT 0,
    `withdrawn_balance` BIGINT NOT NULL DEFAULT 0,
    `total_received` BIGINT NOT NULL DEFAULT 0,
    `total_fee` BIGINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wallets_merchant_id` (`merchant_id`),
    CONSTRAINT `fk_wallets_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 5. wallet_ledger
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wallet_ledger` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NULL DEFAULT NULL,
    `type` ENUM('credit','debit','hold','release','withdrawal','fee') NOT NULL,
    `amount` BIGINT NOT NULL,
    `balance_before` BIGINT NOT NULL,
    `balance_after` BIGINT NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wallet_ledger_merchant_id` (`merchant_id`),
    KEY `idx_wallet_ledger_created_at` (`created_at`),
    CONSTRAINT `fk_wallet_ledger_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 6. withdrawals
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `withdrawals` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `amount` BIGINT NOT NULL,
    `fee` BIGINT NOT NULL DEFAULT 0,
    `net_amount` BIGINT NOT NULL DEFAULT 0,
    `fee_type` VARCHAR(20) NULL DEFAULT NULL,
    `fee_snapshot` JSON NULL DEFAULT NULL,
    `bank_name` VARCHAR(100) NULL DEFAULT NULL,
    `account_number` VARCHAR(50) NULL DEFAULT NULL,
    `account_name` VARCHAR(255) NULL DEFAULT NULL,
    `note` TEXT NULL DEFAULT NULL,
    `status` ENUM('PENDING','REVIEWING','APPROVED','PROCESSING','SUCCESS','FAILED','REJECTED','CANCELED') NOT NULL DEFAULT 'PENDING',
    `admin_note` TEXT NULL DEFAULT NULL,
    `processed_by` VARCHAR(36) NULL DEFAULT NULL,
    `processed_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_withdrawals_merchant_id` (`merchant_id`),
    KEY `idx_withdrawals_status` (`status`),
    CONSTRAINT `fk_withdrawals_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 7. settlements
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settlements` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `period` VARCHAR(7) NOT NULL,
    `total_transactions` INT NOT NULL DEFAULT 0,
    `total_gross` BIGINT NOT NULL DEFAULT 0,
    `total_fee` BIGINT NOT NULL DEFAULT 0,
    `total_net` BIGINT NOT NULL DEFAULT 0,
    `transaction_ids` JSON NULL DEFAULT NULL,
    `status` ENUM('PENDING','APPROVED','TRANSFERRED','COMPLETED','FAILED') NOT NULL DEFAULT 'PENDING',
    `created_by` VARCHAR(36) NOT NULL,
    `approved_by` VARCHAR(36) NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `note` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_settlements_merchant_id` (`merchant_id`),
    KEY `idx_settlements_status` (`status`),
    CONSTRAINT `fk_settlements_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 8. webhook_events
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_events` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NULL DEFAULT NULL,
    `status` VARCHAR(30) NOT NULL,
    `payload` LONGTEXT NULL DEFAULT NULL,
    `message` TEXT NULL DEFAULT NULL,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_webhook_events_merchant_id` (`merchant_id`),
    KEY `idx_webhook_events_status` (`status`),
    KEY `idx_webhook_events_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 9. webhook_retries
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `webhook_retries` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NULL DEFAULT NULL,
    `url` TEXT NOT NULL,
    `payload` JSON NULL DEFAULT NULL,
    `status` ENUM('pending','delivered','failed','exhausted') NOT NULL DEFAULT 'pending',
    `attempts` INT NOT NULL DEFAULT 0,
    `max_retries` INT NOT NULL DEFAULT 3,
    `last_attempt_at` DATETIME NULL DEFAULT NULL,
    `next_retry_at` DATETIME NULL DEFAULT NULL,
    `last_http_code` INT NULL DEFAULT NULL,
    `last_error` TEXT NULL DEFAULT NULL,
    `delivered_at` DATETIME NULL DEFAULT NULL,
    `attempts_log` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_webhook_retries_merchant_id` (`merchant_id`),
    KEY `idx_webhook_retries_status` (`status`),
    KEY `idx_webhook_retries_next_retry_at` (`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 10. audit_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` VARCHAR(36) NOT NULL,
    `actor_id` VARCHAR(36) NOT NULL,
    `actor_role` VARCHAR(30) NOT NULL,
    `merchant_id` VARCHAR(36) NULL DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_merchant_id` (`merchant_id`),
    KEY `idx_audit_logs_actor_id` (`actor_id`),
    KEY `idx_audit_logs_action` (`action`),
    KEY `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 11. settings
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` VARCHAR(36) NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 12. config_changes
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `config_changes` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `merchant_name` VARCHAR(255) NULL DEFAULT NULL,
    `change_type` VARCHAR(50) NOT NULL,
    `change_label` VARCHAR(100) NULL DEFAULT NULL,
    `old_value` TEXT NULL DEFAULT NULL,
    `new_value` TEXT NULL DEFAULT NULL,
    `reason` TEXT NULL DEFAULT NULL,
    `status` ENUM('pending','approved','rejected','canceled','rolled_back') NOT NULL DEFAULT 'pending',
    `requested_by` VARCHAR(36) NOT NULL,
    `requested_by_role` VARCHAR(30) NULL DEFAULT NULL,
    `reviewed_by` VARCHAR(36) NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `review_note` TEXT NULL DEFAULT NULL,
    `applied_at` DATETIME NULL DEFAULT NULL,
    `rolled_back_at` DATETIME NULL DEFAULT NULL,
    `rolled_back_by` VARCHAR(36) NULL DEFAULT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `ip` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_config_changes_merchant_id` (`merchant_id`),
    KEY `idx_config_changes_status` (`status`),
    CONSTRAINT `fk_config_changes_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 13. fee_rules
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fee_rules` (
    `id` VARCHAR(36) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `rule_type` ENUM('transaction','withdrawal','settlement') NOT NULL,
    `fee_type` ENUM('flat','percentage','random','hybrid','tier') NOT NULL,
    `min_amount` BIGINT NOT NULL DEFAULT 0,
    `max_amount` BIGINT NOT NULL DEFAULT 0,
    `config` JSON NULL DEFAULT NULL,
    `merchant_id` VARCHAR(36) NULL DEFAULT NULL,
    `priority` INT NOT NULL DEFAULT 10,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `description` TEXT NULL DEFAULT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fee_rules_rule_type` (`rule_type`),
    KEY `idx_fee_rules_status` (`status`),
    KEY `idx_fee_rules_merchant_id` (`merchant_id`),
    KEY `idx_fee_rules_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 14. refunds
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refunds` (
    `id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NOT NULL,
    `order_id` VARCHAR(50) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `amount` BIGINT NOT NULL,
    `reason` TEXT NULL DEFAULT NULL,
    `type` ENUM('full','partial') NOT NULL DEFAULT 'full',
    `status` ENUM('completed','reversed') NOT NULL DEFAULT 'completed',
    `initiated_by` VARCHAR(36) NOT NULL,
    `initiated_by_role` VARCHAR(30) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_refunds_transaction_id` (`transaction_id`),
    KEY `idx_refunds_merchant_id` (`merchant_id`),
    CONSTRAINT `fk_refunds_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_refunds_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 15. notifications
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` VARCHAR(36) NOT NULL,
    `recipient_id` VARCHAR(36) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `message` TEXT NULL DEFAULT NULL,
    `data` JSON NULL DEFAULT NULL,
    `read` TINYINT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_recipient_id` (`recipient_id`),
    KEY `idx_notifications_read` (`read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Default Settings
-- -----------------------------------------------------------
INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(UUID(), 'app_name', 'PayGate Pro', NOW(), NOW()),
(UUID(), 'default_fee_type', 'percentage', NOW(), NOW()),
(UUID(), 'default_fee_value', '0.7', NOW(), NOW()),
(UUID(), 'default_fee_flat', '0', NOW(), NOW()),
(UUID(), 'min_withdrawal', '10000', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
