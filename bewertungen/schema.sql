-- Bewertungsmanagement – Datenbankschema (MySQL 8 / MariaDB 10+)
-- Eigene Datenbank (Standard: bewertungen_), getrennt vom Tracker.
-- Einspielen:  mysql -u USER -p bewertungen_ < schema.sql
SET NAMES utf8mb4;

-- Anfragen / Aufträge -------------------------------------------------------
CREATE TABLE IF NOT EXISTS bm_requests (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status           ENUM('neu','gescraped','in_bearbeitung','abgeschlossen','abgelehnt')
                     NOT NULL DEFAULT 'neu',
  -- Kontaktdaten des Anfragenden
  contact_name     VARCHAR(160) NOT NULL,
  contact_email    VARCHAR(190) NOT NULL,
  contact_phone    VARCHAR(60)  NULL,
  company          VARCHAR(190) NULL,
  message          TEXT NULL,
  -- gewähltes Objekt (Google-Listing)
  property_name    VARCHAR(255) NOT NULL,
  property_token   VARCHAR(255) NOT NULL,
  property_type    VARCHAR(120) NULL,
  property_rating  DECIMAL(2,1) NULL,
  property_reviews INT UNSIGNED NULL,
  property_lat     DECIMAL(10,7) NULL,
  property_lng     DECIMAL(10,7) NULL,
  -- Verwaltung
  customer_id      INT UNSIGNED NULL,
  admin_note       TEXT NULL,
  scraped_at       DATETIME NULL,
  reconciled_at    DATETIME NULL,
  accepted_at      DATETIME NULL,
  first_scraped_at DATETIME NULL,
  -- Aktiv-Schalter: nur aktive Auftraege werden vom Cron aktualisiert (Kostenkontrolle)
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  -- Anbieter & Async-Job-Status
  provider          VARCHAR(20) NULL,
  scrape_job_id     VARCHAR(190) NULL,
  scrape_job_url    VARCHAR(500) NULL,
  scrape_job_mode   VARCHAR(12) NULL,
  scrape_job_status ENUM('none','pending','done','error') NOT NULL DEFAULT 'none',
  PRIMARY KEY (id),
  KEY idx_status (status),
  KEY idx_token (property_token),
  KEY idx_customer (customer_id),
  KEY idx_active (is_active),
  KEY idx_job_status (scrape_job_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kundenlogins (vom Admin erzeugt) -----------------------------------------
CREATE TABLE IF NOT EXISTS bm_customers (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(80) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(160) NULL,
  email         VARCHAR(190) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  reset_token   CHAR(64) NULL,
  reset_expires DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  KEY idx_reset (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gescrapte Bewertungen pro Anfrage ----------------------------------------
CREATE TABLE IF NOT EXISTS bm_reviews (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id    INT UNSIGNED NOT NULL,
  fingerprint   CHAR(64) NOT NULL,
  external_id   VARCHAR(190) NULL,
  author        VARCHAR(190) NULL,
  rating        TINYINT UNSIGNED NULL,
  source        VARCHAR(120) NULL,
  text          MEDIUMTEXT NULL,
  date_relative VARCHAR(120) NULL,
  is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_req_fp (request_id, fingerprint),
  KEY idx_req (request_id),
  KEY idx_deleted (is_deleted),
  KEY idx_external (external_id),
  CONSTRAINT fk_review_request FOREIGN KEY (request_id)
    REFERENCES bm_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate-Limit / Audit für öffentliche Endpunkte -----------------------------
CREATE TABLE IF NOT EXISTS bm_api_hits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip         VARBINARY(16) NOT NULL,
  action     VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
