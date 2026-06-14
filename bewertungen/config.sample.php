<?php
/**
 * Bewertungsmanagement – Konfiguration (BEISPIEL).
 *
 * Auf dem Server zu `config.php` kopieren und echte Werte eintragen.
 * `config.php` steht in .gitignore und darf NIEMALS committet werden.
 */

// --- SerpApi --------------------------------------------------------------
// Privater API-Key des SerpApi-Accounts (verbraucht Search-Credits).
define('SERPAPI_KEY', 'HIER_SERPAPI_KEY_EINTRAGEN');

// --- Datenbank (MySQL) ----------------------------------------------------
// Eigene Datenbank für das Bewertungsmanagement (getrennt vom Tracker).
define('DB_HOST', 'localhost');
define('DB_NAME', 'bewertungen_');
define('DB_USER', 'DB_BENUTZER');
define('DB_PASS', 'DB_PASSWORT');

// --- Admin-Login ----------------------------------------------------------
// Passwort-Hash erzeugen:
//   php -r "echo password_hash('DEINPASSWORT', PASSWORD_DEFAULT), PHP_EOL;"
define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', '$2y$10$ERSETZEN_DURCH_ECHTEN_BCRYPT_HASH______________________');

// --- E-Mail-Benachrichtigung ---------------------------------------------
define('NOTIFY_EMAIL', 'info@wattnauftritt.de');
define('MAIL_FROM',    'no-reply@wattnauftritt.de');

// --- Sicherheit / Limits --------------------------------------------------
define('LOOKUP_RATE_LIMIT', 8);     // Live-Lookups pro IP / Stunde
define('SUBMIT_RATE_LIMIT', 5);     // Anfragen pro IP / Tag
define('SCRAPE_MAX_PAGES', 50);     // Sicherheitslimit für den Voll-Scrape (SerpApi)
define('SESSION_NAME', 'WNA_BM');

// --- Bewertungs-Anbieter --------------------------------------------------
// 'serpapi' (Standard) oder 'outscraper'.
define('REVIEWS_PROVIDER', 'serpapi');
define('OUTSCRAPER_KEY', '');                              // nur bei REVIEWS_PROVIDER='outscraper'
define('OUTSCRAPER_BASE', 'https://api.outscraper.cloud'); // ggf. an aktuelle API-Basis anpassen
define('SCRAPE_POLL_TIMEOUT', 25);                         // Sek. Inline-Warten auf Async-Ergebnis (Web)
define('QUICK_LIMIT', 20);                                 // Reviews pro Quick-Lauf (Cron)
