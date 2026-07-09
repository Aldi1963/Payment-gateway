-- Fix snap_token column size
-- Midtrans Core API responses (VA numbers, deeplinks, QR URLs) stored as JSON
-- can exceed VARCHAR(255). Change to TEXT.
-- 
-- Already applied manually on production by Aldi on 2026-07-09.
-- This migration file exists for documentation and future deploys.

ALTER TABLE `transactions` MODIFY COLUMN `snap_token` TEXT NULL DEFAULT NULL;
