-- Migration: Passwort-Reset für Kundenlogins.
--   mysql -u USER -p bewertungen_ < migrations/2026-06-14_customer_reset.sql
-- Nur einmal ausführen.

ALTER TABLE bm_customers
  ADD COLUMN reset_token   CHAR(64) NULL AFTER is_active,
  ADD COLUMN reset_expires DATETIME NULL AFTER reset_token,
  ADD KEY idx_reset (reset_token);
