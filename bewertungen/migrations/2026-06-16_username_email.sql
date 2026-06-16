-- Migration: Benutzername = E-Mail-Adresse (Feld auf E-Mail-Länge erweitern).
--   mysql -u USER -p bewertungen_ < migrations/2026-06-16_username_email.sql
-- Nur einmal ausführen.

ALTER TABLE bm_customers
  MODIFY username VARCHAR(190) NOT NULL;
