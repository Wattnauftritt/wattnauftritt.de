-- ============================================================================
-- Sammel-Update für eine bereits mit dem URSPRÜNGLICHEN schema.sql angelegte DB.
-- Bringt die Tabellen auf den aktuellen Stand (Provider/Async, Aktiv-Schalter,
-- external_id, Passwort-Reset, Auftrag/Annahme, Abrechnungs-Baseline).
--
-- Einspielen (einmalig):
--   mysql -u USER -p bewertung_ < migrations/upgrade_all.sql
--
-- Hinweis: MySQL kennt kein "ADD COLUMN IF NOT EXISTS" -> nur EINMAL ausführen.
-- Wer einzelne Migrationen schon eingespielt hat, sollte diese Datei NICHT
-- zusätzlich laufen lassen (sonst "Duplicate column"-Fehler).
-- ============================================================================

-- 1) Anfragen/Aufträge: Anbieter, Async-Job, Aktiv-Schalter, Annahme, Baseline
ALTER TABLE bm_requests
  ADD COLUMN is_active         TINYINT(1) NOT NULL DEFAULT 1 AFTER reconciled_at,
  ADD COLUMN provider          VARCHAR(20) NULL AFTER is_active,
  ADD COLUMN scrape_job_id     VARCHAR(190) NULL AFTER provider,
  ADD COLUMN scrape_job_url    VARCHAR(500) NULL AFTER scrape_job_id,
  ADD COLUMN scrape_job_mode   VARCHAR(12) NULL AFTER scrape_job_url,
  ADD COLUMN scrape_job_status ENUM('none','pending','done','error') NOT NULL DEFAULT 'none' AFTER scrape_job_mode,
  ADD COLUMN accepted_at       DATETIME NULL AFTER scrape_job_status,
  ADD COLUMN first_scraped_at  DATETIME NULL AFTER accepted_at,
  ADD KEY idx_active (is_active),
  ADD KEY idx_job_status (scrape_job_status),
  ADD KEY idx_accepted (accepted_at);

-- 2) Bewertungen: stabile Anbieter-ID für robustere Dedup/Löscherkennung
ALTER TABLE bm_reviews
  ADD COLUMN external_id VARCHAR(190) NULL AFTER fingerprint,
  ADD KEY idx_external (external_id);

-- 3) Kundenlogins: Passwort-Reset + Benutzername = E-Mail (laengeres Feld)
ALTER TABLE bm_customers
  MODIFY username VARCHAR(190) NOT NULL,
  ADD COLUMN reset_token   CHAR(64) NULL AFTER is_active,
  ADD COLUMN reset_expires DATETIME NULL AFTER reset_token,
  ADD KEY idx_reset (reset_token);
