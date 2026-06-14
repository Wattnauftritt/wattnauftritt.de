-- Migration: Auftragssystem & Abrechnung.
--   mysql -u USER -p bewertungen_ < migrations/2026-06-14_orders_billing.sql
-- Nur einmal ausführen.

ALTER TABLE bm_requests
  ADD COLUMN accepted_at      DATETIME NULL AFTER reconciled_at,
  ADD COLUMN first_scraped_at DATETIME NULL AFTER accepted_at,
  ADD KEY idx_accepted (accepted_at);
