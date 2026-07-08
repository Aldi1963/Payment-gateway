-- Migration: Add payment_channel, payment_method, snap_token to transactions
-- Run this on existing databases to support Midtrans integration
-- Date: 2026-07-08

ALTER TABLE `transactions`
    ADD COLUMN `payment_channel` VARCHAR(30) NULL DEFAULT 'qris' AFTER `expired_at`,
    ADD COLUMN `payment_method` VARCHAR(50) NULL DEFAULT NULL AFTER `payment_channel`,
    ADD COLUMN `snap_token` VARCHAR(255) NULL DEFAULT NULL AFTER `payment_method`;

-- Add index for channel filtering
ALTER TABLE `transactions`
    ADD KEY `idx_transactions_payment_channel` (`payment_channel`);

-- Update existing transactions to have 'qris' as default channel
UPDATE `transactions` SET `payment_channel` = 'qris' WHERE `payment_channel` IS NULL;
