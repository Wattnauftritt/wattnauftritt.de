-- Migration für bestehende Installationen (einmalig einspielen):
--   mysql -u USER -p bewertungen_ < migrations/2026-06-14_provider_active.sql
--
-- Fügt Aktiv-Schalter, Anbieter-/Async-Job-Felder und external_id hinzu.
-- Hinweis: MySQL 8 kennt kein "ADD COLUMN IF NOT EXISTS" -> nur einmal ausführen.

ALTER TABLE bm_requests
  ADD COLUMN is_active        TINYINT(1) NOT NULL DEFAULT 1 AFTER reconciled_at,
  ADD COLUMN provider          VARCHAR(20) NULL AFTER is_active,
  ADD COLUMN scrape_job_id     VARCHAR(190) NULL AFTER provider,
  ADD COLUMN scrape_job_url    VARCHAR(500) NULL AFTER scrape_job_id,
  ADD COLUMN scrape_job_mode   VARCHAR(12) NULL AFTER scrape_job_url,
  ADD COLUMN scrape_job_status ENUM('none','pending','done','error') NOT NULL DEFAULT 'none' AFTER scrape_job_mode,
  ADD KEY idx_active (is_active),
  ADD KEY idx_job_status (scrape_job_status);

ALTER TABLE bm_reviews
  ADD COLUMN external_id VARCHAR(190) NULL AFTER fingerprint,
  ADD KEY idx_external (external_id);
