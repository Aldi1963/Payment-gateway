-- ---------------------------------------------------------------------
-- Migration: Account-level API key
-- ---------------------------------------------------------------------
-- Moves the API key concept from per-project (merchants.api_key) to
-- per-account (users.api_key). One key now works across all of a user's
-- projects; the target project is selected per-request via the
-- X-Project-Id / X-Project header (or project_id / project query param).
--
-- Backward compatible: existing merchants.api_key values remain valid.
-- The API auth layer tries the account key first, then falls back to the
-- legacy per-merchant key.
--
-- Idempotent where practical. Safe to re-run.
-- ---------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add api_key column to users (account-level key)
--    Note: ALTER TABLE ADD COLUMN is not idempotent on MySQL < 8.0.
--    The migration runner is expected to ignore "duplicate column" errors,
--    or you can guard manually before running.
ALTER TABLE `users` ADD COLUMN `api_key` VARCHAR(70) NULL DEFAULT NULL AFTER `password_hash`;
ALTER TABLE `users` ADD UNIQUE KEY `uk_users_api_key` (`api_key`);

-- 2. Backfill: give every account owner (role = 'merchant') an API key.
--    Generates a pk_ + 64 hex-char key using two UUIDs (unique per row).
UPDATE `users`
SET `api_key` = CONCAT('pk_', REPLACE(UUID(), '-', ''), REPLACE(UUID(), '-', ''))
WHERE `api_key` IS NULL
  AND `role` = 'merchant';

SET FOREIGN_KEY_CHECKS = 1;
