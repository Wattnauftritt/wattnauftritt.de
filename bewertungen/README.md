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
   - Öffentlich: `https://wattnauftritt.de/bewertungen/`
   - Admin:      `https://wattnauftritt.de/bewertungen/admin/`
   - Kunde:      `https://wattnauftritt.de/bewertungen/kunde/`

> **Update einer bestehenden Installation:** die Migrationen im Ordner `migrations/`
> einmalig einspielen, z. B.
> `mysql -u USER -p bewertungen_ < migrations/2026-06-14_provider_active.sql`
> (Aktiv-Schalter, Anbieter-/Async-Felder, `external_id`) und
> `mysql -u USER -p bewertungen_ < migrations/2026-06-14_customer_reset.sql`
> (Passwort-Reset für Kundenlogins).
>
> **Bequemer:** Wer nur das ursprüngliche `schema.sql` importiert hat, spielt
> **alle** Nachträge in einem Rutsch ein:
> `mysql -u USER -p bewertungen_ < migrations/upgrade_all.sql`
> (nur einmal ausführen; nicht zusätzlich zu den Einzel-Migrationen).

Kundenlogins: Beim Erstellen werden die **Zugangsdaten per E-Mail** an den Kunden
gesendet (Passwort wird im Panel nicht angezeigt; nur als Fallback bei Mailfehler).
Kunden können ihr Passwort unter „Passwort ändern" anpassen und über
„Passwort vergessen?" per E-Mail-Link (1 Std. gültig) zurücksetzen.

## Auftragssystem & Abrechnung

- **Anfragen → Aufträge:** In der Anfrage-Detailseite „Anfrage annehmen → Auftrag".
  Angenommene Anfragen erscheinen unter **„Aufträge"** (`admin/auftraege.php`) und sind
  dort anklickbar.
- **Bewertungen** in der Detailseite sind **sortierbar** (Neueste / Beste / Schlechteste)
  und **filterbar** (Alle / Aktiv / Neu / Gelöscht). Zähler zeigen sofort: gesamt, aktiv,
  gelöscht (entfernt) und „neu seit Start" (Bewertungen, die nach dem ersten Scrape
  dazukamen).
- **Abrechnung** (`admin/abrechnung.php`): druckbare Aufstellung auf Basis der
  **entfernten** Bewertungen × Preis je Entfernung (+ optionale Grundgebühr, Defaults aus
  `config.php`: `PRICE_PER_REMOVAL`, `BILLING_BASE_FEE`). Preise pro Abrechnung anpassbar,
  „Drucken / als PDF speichern". Dient als Grundlage – für rechtsgültige Rechnungen ins
  eigene Rechnungssystem übernehmen.

## Anbieter wählen (SerpApi oder Outscraper)

In `config.php`:
```php
define('REVIEWS_PROVIDER', 'serpapi');   // oder 'outscraper'
define('OUTSCRAPER_KEY', '...');          // nur für Outscraper
```
- **serpapi** – synchron, einfach, pro Suche 1 Credit.
- **outscraper** – pay-as-you-go, günstiger; Reviews-Abruf läuft **asynchron** (Job →
  Ergebnis). Im Adminpanel erscheint dann ggf. „Ergebnis abrufen", der Cron holt
  wartende Jobs automatisch nach. Endpunkt-Pfade/Feldnamen in `inc/outscraper.php`
  ggf. an die aktuelle Outscraper-API-Doku anpassen.

## Aktiv-Schalter & Kostenkontrolle

Jeder Auftrag hat einen **Aktiv-Schalter** (im Detail umschaltbar). Der Cron aktualisiert
**nur aktive** Aufträge. Wird ein Kunde inaktiv gesetzt (z. B. zahlt nicht mehr), entstehen
keine weiteren API-Kosten. Kundenlogins lassen sich über `bm_customers.is_active`
deaktivieren.

## Cronjobs (automatische Aktualisierung)

CLI-Skript `cron.php` aktualisiert aktive, bereits gescrapte Aufträge:
```cron
# Wöchentlicher Vollabgleich inkl. Löscherkennung (So 04:00)
0 4 * * 0 /opt/plesk/php/8.3/bin/php /var/www/vhosts/wattnauftritt.de/httpdocs/bewertungen/cron.php reconcile >> /var/www/vhosts/wattnauftritt.de/bewertungen-cron.log 2>&1

# Täglich neue Bewertungen einlesen (günstig, ohne Löscherkennung)
0 6 * * * /opt/plesk/php/8.3/bin/php /var/www/vhosts/wattnauftritt.de/httpdocs/bewertungen/cron.php quick >> /var/www/vhosts/wattnauftritt.de/bewertungen-cron.log 2>&1
```
**Jeder Lauf kostet API-Credits pro aktivem Auftrag** – Frequenz bewusst wählen
(z. B. Abgleich wöchentlich statt täglich).

## Sicherheit

- **API-Key & Secrets nur in `config.php`** (nicht im Repo). Key bleibt serverseitig,
  nie im Browser/JS.
- Öffentliche Endpunkte (`api/lookup.php`, `api/submit.php`) sind durch **Honeypot** und
  **IP-Rate-Limits** (`bm_api_hits`) gegen Credit-Missbrauch geschützt.
- Admin- und Kundenbereich über PHP-Sessions; Formulare mit CSRF-Token; Panels `noindex`.
- Empfehlung: `admin/` zusätzlich per Plesk/.htaccess auf bekannte IPs beschränken.

## Struktur

```
bewertungen/
  index.php            öffentliche Anfrageseite (Wizard)
  config.sample.php    Vorlage – echte Werte in config.php (gitignored)
  schema.sql           Datenbankschema (bm_*-Tabellen)
  api/lookup.php       Live-Objektsuche (geschützt)
  api/submit.php       Anfrage anlegen + E-Mail (kein Scrape)
  admin/               Login, Anfrage-Liste, Detail (Scrape/Reconcile/Aktiv/Kundenlogin)
  kunde/               Login, Auftragsansicht (aktiv/entfernt)
  cron.php             CLI-Cron (quick|reconcile) für aktive Aufträge
  migrations/          DB-Updates für bestehende Installationen
  inc/                 bootstrap, db, auth, layout, helpers, mail,
                       reviews_provider (Adapter), serpapi, outscraper, scrape
  assets/              public.css, wizard.js, panel.css
```
