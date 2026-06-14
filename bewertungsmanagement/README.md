# Bewertungsmanagement

Modul zum Entfernen unrechtmäßiger Google-Bewertungen: öffentliche Anfrageseite mit
Live-Objektsuche, Admin-Panel zur Verwaltung + Scrape, und Kundenportal zum Verfolgen.

## Ablauf

1. **Kunde** (öffentlich, `index.php`): sucht sein Objekt live über SerpApi, wählt das
   richtige Google-Listing, hinterlässt Kontaktdaten → Anfrage wird gespeichert, E-Mail
   an `info@wattnauftritt.de`. **Es wird hier noch nichts gescrapt** (Credit-Schutz).
2. **Admin** (`admin/`): sichtet die Anfrage und klickt **„Freigeben & Bewertungen
   abrufen"** → einmaliger Voll-Scrape aller Bewertungen in die Datenbank. Danach
   **„Abgleichen"** möglich (erkennt gelöschte Bewertungen). Erstellt bei Bedarf einen
   **Kundenlogin** (Passwort wird einmalig angezeigt).
3. **Kunde** (`kunde/`): meldet sich an und sieht seinen Auftrag mit **aktiven** und
   **entfernten** Bewertungen.

## Einrichtung auf dem Server (Plesk, PHP 8.3, MySQL)

1. `config.php` aus `config.sample.php` erstellen und Werte eintragen
   (SerpApi-Key, DB-Zugang, Admin-Hash, E-Mail). **`config.php` ist gitignored.**
   ```bash
   php -r "echo password_hash('DEIN_ADMIN_PASSWORT', PASSWORD_DEFAULT), PHP_EOL;"
   ```
2. Eigene Datenbank `bewertungen_` anlegen und Schema einspielen:
   ```bash
   mysql -u USER -p bewertungen_ < schema.sql
   ```
3. Aufrufen:
   - Öffentlich: `https://wattnauftritt.de/bewertungsmanagement/`
   - Admin:      `https://wattnauftritt.de/bewertungsmanagement/admin/`
   - Kunde:      `https://wattnauftritt.de/bewertungsmanagement/kunde/`

## Sicherheit

- **API-Key & Secrets nur in `config.php`** (nicht im Repo). Key bleibt serverseitig,
  nie im Browser/JS.
- Öffentliche Endpunkte (`api/lookup.php`, `api/submit.php`) sind durch **Honeypot** und
  **IP-Rate-Limits** (`bm_api_hits`) gegen Credit-Missbrauch geschützt.
- Admin- und Kundenbereich über PHP-Sessions; Formulare mit CSRF-Token; Panels `noindex`.
- Empfehlung: `admin/` zusätzlich per Plesk/.htaccess auf bekannte IPs beschränken.

## Struktur

```
bewertungsmanagement/
  index.php            öffentliche Anfrageseite (Wizard)
  config.sample.php    Vorlage – echte Werte in config.php (gitignored)
  schema.sql           Datenbankschema (bm_*-Tabellen)
  api/lookup.php       Live-Objektsuche (geschützt)
  api/submit.php       Anfrage anlegen + E-Mail (kein Scrape)
  admin/               Login, Anfrage-Liste, Detail (Scrape/Reconcile/Kundenlogin)
  kunde/               Login, Auftragsansicht (aktiv/entfernt)
  inc/                 bootstrap, db, auth, serpapi, scrape, mail, layout, helpers
  assets/              public.css, wizard.js, panel.css
```
