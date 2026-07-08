-- =====================================================================
-- Migration: Multi-Project (Multi-Merchant per User) + WhatsApp per Project
-- =====================================================================
-- Each user can own multiple projects (stores). Each project = 1 merchant.
-- New projects start as 'pending' and must be verified by an admin.
-- Each project has its own API key, webhook, IP whitelist, and WA integration.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. Add project fields to merchants
--    (slug for short URL, owner_id for the creating user)
-- ---------------------------------------------------------------------
ALTER TABLE `merchants`
    ADD COLUMN `slug` VARCHAR(100) NULL DEFAULT NULL AFTER `business_name`,
    ADD COLUMN `owner_id` VARCHAR(36) NULL DEFAULT NULL AFTER `slug`,
    ADD COLUMN `mode` ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox' AFTER `status`,
    ADD COLUMN `rejection_reason` TEXT NULL DEFAULT NULL AFTER `mode`,
    ADD COLUMN `verified_at` DATETIME NULL DEFAULT NULL AFTER `rejection_reason`,
    ADD COLUMN `verified_by` VARCHAR(36) NULL DEFAULT NULL AFTER `verified_at`,
    ADD UNIQUE KEY `uk_merchants_slug` (`slug`),
    ADD KEY `idx_merchants_owner_id` (`owner_id`);

-- ---------------------------------------------------------------------
-- 2. user_merchants pivot: 1 user -> many projects (merchants)
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 3. merchant_wa_configs: WhatsApp integration per project
-- ---------------------------------------------------------------------
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

-- ---------------------------------------------------------------------
-- 4. Data migration: link existing users to their merchant (backfill)
--    Every existing user.merchant_id becomes a user_merchants row (owner, default).
-- ---------------------------------------------------------------------
INSERT INTO `user_merchants` (`id`, `user_id`, `merchant_id`, `role`, `is_default`, `created_at`, `updated_at`)
SELECT
    UUID(), u.`id`, u.`merchant_id`, 'owner', 1, NOW(), NOW()
FROM `users` u
WHERE u.`merchant_id` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM `user_merchants` um
      WHERE um.`user_id` = u.`id` AND um.`merchant_id` = u.`merchant_id`
  );

-- Backfill owner_id on merchants from the linked user
UPDATE `merchants` m
JOIN `users` u ON u.`merchant_id` = m.`id`
SET m.`owner_id` = u.`id`
WHERE m.`owner_id` IS NULL;

-- Backfill slug from business_name for existing merchants (lowercased, alnum only)
UPDATE `merchants`
SET `slug` = CONCAT(
        LOWER(REGEXP_REPLACE(`business_name`, '[^a-zA-Z0-9]', '')),
        '-', SUBSTRING(REPLACE(`id`, '-', ''), 1, 6)
    )
WHERE `slug` IS NULL;

-- ---------------------------------------------------------------------
-- 5. New settings for multi-project
-- ---------------------------------------------------------------------
INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(UUID(), 'max_projects_per_user', '20', NOW(), NOW()),
(UUID(), 'require_admin_verification', '1', NOW(), NOW()),
(UUID(), 'wa_default_template_payment', 'Halo {customer}! Pembayaran untuk order *{order_id}* sebesar *{amount}* telah *{status}*. Terima kasih telah bertransaksi di {project}.', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

SET FOREIGN_KEY_CHECKS = 1;
