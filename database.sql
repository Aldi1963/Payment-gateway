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
    `api_key` VARCHAR(70) NULL DEFAULT NULL,
    `role` ENUM('super_admin','admin','finance','support','merchant','staff_merchant') NOT NULL DEFAULT 'merchant',
    `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `permissions` JSON NULL DEFAULT NULL,
    `email_verified` TINYINT NOT NULL DEFAULT 0,
    `verify_token` VARCHAR(64) NULL DEFAULT NULL,
    `verify_token_at` DATETIME NULL DEFAULT NULL,
    `google_id` VARCHAR(100) NULL DEFAULT NULL,
    `avatar_url` TEXT NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    UNIQUE KEY `uk_users_api_key` (`api_key`),
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
-- 16. fraud_logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fraud_logs` (
    `id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NULL DEFAULT NULL,
    `merchant_id` VARCHAR(36) NULL DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `score` INT NOT NULL DEFAULT 0,
    `level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
    `factors` JSON NULL DEFAULT NULL,
    `action` VARCHAR(20) NOT NULL DEFAULT 'allowed',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fraud_logs_transaction_id` (`transaction_id`),
    KEY `idx_fraud_logs_merchant_id` (`merchant_id`),
    KEY `idx_fraud_logs_ip` (`ip`),
    KEY `idx_fraud_logs_level` (`level`),
    KEY `idx_fraud_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 17. login_history
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_history` (
    `id` VARCHAR(36) NOT NULL,
    `user_id` VARCHAR(36) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `status` ENUM('success','failed','blocked') NOT NULL DEFAULT 'success',
    `failure_reason` VARCHAR(100) NULL DEFAULT NULL,
    `location` VARCHAR(255) NULL DEFAULT NULL,
    `device_fingerprint` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_history_user_id` (`user_id`),
    KEY `idx_login_history_ip` (`ip`),
    KEY `idx_login_history_created_at` (`created_at`),
    CONSTRAINT `fk_login_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 18. user_sessions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(36) NOT NULL,
    `user_id` VARCHAR(36) NOT NULL,
    `session_id` VARCHAR(128) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `device_name` VARCHAR(255) NULL DEFAULT NULL,
    `is_active` TINYINT NOT NULL DEFAULT 1,
    `last_activity_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_sessions_user_id` (`user_id`),
    KEY `idx_user_sessions_session_id` (`session_id`),
    KEY `idx_user_sessions_is_active` (`is_active`),
    CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 19. payment_links
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_links` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `amount` BIGINT NULL DEFAULT NULL,
    `is_fixed_amount` TINYINT NOT NULL DEFAULT 1,
    `min_amount` BIGINT NULL DEFAULT NULL,
    `max_amount` BIGINT NULL DEFAULT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR',
    `slug` VARCHAR(100) NOT NULL,
    `is_reusable` TINYINT NOT NULL DEFAULT 1,
    `max_usage` INT NULL DEFAULT NULL,
    `usage_count` INT NOT NULL DEFAULT 0,
    `status` ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
    `custom_fields` JSON NULL DEFAULT NULL,
    `redirect_url` TEXT NULL DEFAULT NULL,
    `webhook_url` TEXT NULL DEFAULT NULL,
    `branding` JSON NULL DEFAULT NULL,
    `expires_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_payment_links_slug` (`slug`),
    KEY `idx_payment_links_merchant_id` (`merchant_id`),
    KEY `idx_payment_links_status` (`status`),
    CONSTRAINT `fk_payment_links_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 20. subscriptions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(30) NULL DEFAULT NULL,
    `plan_name` VARCHAR(255) NOT NULL,
    `amount` BIGINT NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR',
    `interval_type` ENUM('daily','weekly','monthly','yearly') NOT NULL DEFAULT 'monthly',
    `interval_count` INT NOT NULL DEFAULT 1,
    `total_cycles` INT NULL DEFAULT NULL,
    `completed_cycles` INT NOT NULL DEFAULT 0,
    `status` ENUM('active','paused','cancelled','completed','past_due') NOT NULL DEFAULT 'active',
    `payment_method` VARCHAR(50) NULL DEFAULT NULL,
    `payment_channel` VARCHAR(30) NULL DEFAULT NULL,
    `trial_days` INT NOT NULL DEFAULT 0,
    `grace_period_days` INT NOT NULL DEFAULT 3,
    `retry_count` INT NOT NULL DEFAULT 0,
    `max_retries` INT NOT NULL DEFAULT 3,
    `current_period_start` DATETIME NULL DEFAULT NULL,
    `current_period_end` DATETIME NULL DEFAULT NULL,
    `next_billing_at` DATETIME NULL DEFAULT NULL,
    `cancelled_at` DATETIME NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subscriptions_merchant_id` (`merchant_id`),
    KEY `idx_subscriptions_status` (`status`),
    KEY `idx_subscriptions_next_billing_at` (`next_billing_at`),
    CONSTRAINT `fk_subscriptions_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 21. subscription_invoices
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subscription_invoices` (
    `id` VARCHAR(36) NOT NULL,
    `subscription_id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NULL DEFAULT NULL,
    `amount` BIGINT NOT NULL,
    `cycle_number` INT NOT NULL,
    `status` ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    `billing_date` DATE NOT NULL,
    `paid_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_subscription_invoices_subscription_id` (`subscription_id`),
    KEY `idx_subscription_invoices_merchant_id` (`merchant_id`),
    KEY `idx_subscription_invoices_status` (`status`),
    CONSTRAINT `fk_subscription_invoices_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 22. invoices
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `transaction_id` VARCHAR(36) NULL DEFAULT NULL,
    `invoice_number` VARCHAR(50) NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NULL DEFAULT NULL,
    `customer_phone` VARCHAR(30) NULL DEFAULT NULL,
    `customer_address` TEXT NULL DEFAULT NULL,
    `items` JSON NOT NULL,
    `subtotal` BIGINT NOT NULL,
    `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `tax_amount` BIGINT NOT NULL DEFAULT 0,
    `discount_amount` BIGINT NOT NULL DEFAULT 0,
    `total_amount` BIGINT NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR',
    `status` ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `due_date` DATE NULL DEFAULT NULL,
    `paid_at` DATETIME NULL DEFAULT NULL,
    `notes` TEXT NULL DEFAULT NULL,
    `footer` TEXT NULL DEFAULT NULL,
    `template` VARCHAR(50) NOT NULL DEFAULT 'default',
    `pdf_path` VARCHAR(500) NULL DEFAULT NULL,
    `sent_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_invoices_number` (`merchant_id`, `invoice_number`),
    KEY `idx_invoices_merchant_id` (`merchant_id`),
    KEY `idx_invoices_status` (`status`),
    KEY `idx_invoices_due_date` (`due_date`),
    CONSTRAINT `fk_invoices_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- 23. idempotency_keys
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `idempotency_keys` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `idempotency_key` VARCHAR(255) NOT NULL,
    `request_path` VARCHAR(255) NOT NULL,
    `request_hash` VARCHAR(64) NOT NULL,
    `response_code` INT NOT NULL,
    `response_body` LONGTEXT NULL DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_idempotency_merchant_key` (`merchant_id`, `idempotency_key`),
    KEY `idx_idempotency_expires_at` (`expires_at`),
    CONSTRAINT `fk_idempotency_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- ALTER TABLE: Add 2FA columns to users
-- -----------------------------------------------------------
ALTER TABLE `users`
    ADD COLUMN `two_factor_enabled` TINYINT NOT NULL DEFAULT 0 AFTER `last_login_at`,
    ADD COLUMN `two_factor_secret` VARCHAR(64) NULL DEFAULT NULL AFTER `two_factor_enabled`,
    ADD COLUMN `two_factor_backup_codes` JSON NULL DEFAULT NULL AFTER `two_factor_secret`;

-- -----------------------------------------------------------
-- ALTER TABLE: Add idempotency_key to transactions
-- -----------------------------------------------------------
ALTER TABLE `transactions`
    ADD COLUMN `idempotency_key` VARCHAR(255) NULL DEFAULT NULL AFTER `refund_status`,
    ADD KEY `idx_transactions_idempotency_key` (`idempotency_key`);

-- -----------------------------------------------------------
-- ALTER TABLE: Add currency support to transactions
-- -----------------------------------------------------------
ALTER TABLE `transactions`
    ADD COLUMN `currency` VARCHAR(3) NOT NULL DEFAULT 'IDR' AFTER `amount`;

-- -----------------------------------------------------------
-- ALTER TABLE: Add webhook_events filter to merchants
-- -----------------------------------------------------------
ALTER TABLE `merchants`
    ADD COLUMN `webhook_events_filter` JSON NULL DEFAULT NULL AFTER `webhook_url`,
    ADD COLUMN `locale` VARCHAR(5) NOT NULL DEFAULT 'id' AFTER `default_redirect_url`;

-- -----------------------------------------------------------
-- Default Settings
-- -----------------------------------------------------------
INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(UUID(), 'app_name', 'PayGate Pro', NOW(), NOW()),
(UUID(), 'default_fee_type', 'percentage', NOW(), NOW()),
(UUID(), 'default_fee_value', '0.7', NOW(), NOW()),
(UUID(), 'default_fee_flat', '0', NOW(), NOW()),
(UUID(), 'min_withdrawal', '10000', NOW(), NOW()),
(UUID(), 'supported_currencies', 'IDR,USD,SGD,MYR', NOW(), NOW()),
(UUID(), 'default_currency', 'IDR', NOW(), NOW()),
(UUID(), 'default_locale', 'id', NOW(), NOW()),
(UUID(), 'fraud_velocity_threshold', '10', NOW(), NOW()),
(UUID(), 'fraud_ip_blocklist', '', NOW(), NOW()),
(UUID(), 'idempotency_key_ttl_hours', '24', NOW(), NOW()),
(UUID(), 'webhook_max_retries', '5', NOW(), NOW()),
(UUID(), 'webhook_retry_backoff_base', '60', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- Multi-Project (Multi-Merchant per User) + WhatsApp per Project
-- =====================================================================
SET FOREIGN_KEY_CHECKS = 0;

-- Add project fields to merchants
ALTER TABLE `merchants`
    ADD COLUMN `slug` VARCHAR(100) NULL DEFAULT NULL AFTER `business_name`,
    ADD COLUMN `owner_id` VARCHAR(36) NULL DEFAULT NULL AFTER `slug`,
    ADD COLUMN `mode` ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox' AFTER `status`,
    ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL AFTER `mode`,
    ADD COLUMN `verified_at` DATETIME NULL DEFAULT NULL AFTER `rejection_reason`,
    ADD COLUMN `verified_by` VARCHAR(36) NULL DEFAULT NULL AFTER `verified_at`,
    ADD UNIQUE KEY `uk_merchants_slug` (`slug`),
    ADD KEY `idx_merchants_owner_id` (`owner_id`);

-- user_merchants pivot: 1 user -> many projects
CREATE TABLE IF NOT EXISTS `user_merchants` (
    `id` VARCHAR(36) NOT NULL,
    `user_id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `role` ENUM('owner','manager','staff') NOT NULL DEFAULT 'owner',
    `is_default` TINYINT NOT NULL DEFAULT 0,
    `permissions` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_merchant` (`user_id`, `merchant_id`),
    KEY `idx_user_merchants_user_id` (`user_id`),
    KEY `idx_user_merchants_merchant_id` (`merchant_id`),
    CONSTRAINT `fk_user_merchants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_user_merchants_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- merchant_wa_configs: WhatsApp integration per project
CREATE TABLE IF NOT EXISTS `merchant_wa_configs` (
    `id` VARCHAR(36) NOT NULL,
    `merchant_id` VARCHAR(36) NOT NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'fonnte',
    `api_url` VARCHAR(500) NOT NULL,
    `api_key` VARCHAR(255) NOT NULL,
    `api_secret` VARCHAR(255) NULL DEFAULT NULL,
    `sender_number` VARCHAR(30) NULL DEFAULT NULL,
    `is_active` TINYINT NOT NULL DEFAULT 1,
    `notify_on_payment` TINYINT NOT NULL DEFAULT 1,
    `notify_on_withdrawal` TINYINT NOT NULL DEFAULT 1,
    `notify_on_expiry` TINYINT NOT NULL DEFAULT 0,
    `notify_admin_number` VARCHAR(30) NULL DEFAULT NULL,
    `message_template_payment` TEXT NULL DEFAULT NULL,
    `message_template_withdrawal` TEXT NULL DEFAULT NULL,
    `total_sent` INT NOT NULL DEFAULT 0,
    `last_sent_at` DATETIME NULL DEFAULT NULL,
    `last_error` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_merchant_wa_merchant` (`merchant_id`),
    KEY `idx_merchant_wa_provider` (`provider`),
    CONSTRAINT `fk_merchant_wa_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(UUID(), 'max_projects_per_user', '20', NOW(), NOW()),
(UUID(), 'require_admin_verification', '1', NOW(), NOW()),
(UUID(), 'wa_default_template_payment', 'Halo {customer}! Pembayaran untuk order *{order_id}* sebesar *{amount}* telah *{status}*. Terima kasih telah bertransaksi di {project}.', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

SET FOREIGN_KEY_CHECKS = 1;
