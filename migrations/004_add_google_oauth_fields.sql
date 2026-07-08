-- Migration: Add Google OAuth fields to users table
-- Date: 2026-07-08

ALTER TABLE `users`
    ADD COLUMN `google_id` VARCHAR(100) NULL DEFAULT NULL AFTER `verify_token_at`,
    ADD COLUMN `avatar_url` TEXT NULL DEFAULT NULL AFTER `google_id`;

-- Add index for Google ID lookup
ALTER TABLE `users`
    ADD KEY `idx_users_google_id` (`google_id`);
