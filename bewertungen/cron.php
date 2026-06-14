<?php
declare(strict_types=1);

/**
 * CLI-Cron: Bewertungen AKTIVER Aufträge aktualisieren.
 *
 *   php cron.php quick      – nur neueste Bewertungen einlesen (günstig)
 *   php cron.php reconcile  – Vollabgleich inkl. Löscherkennung
 *
 * Verarbeitet ausschließlich Aufträge mit is_active = 1 -> inaktive (z. B.
 * nicht mehr zahlende) Kunden verursachen keine API-Kosten.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Nur über die Kommandozeile aufrufbar.\n");
}

require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/scrape.php';

$mode = $argv[1] ?? 'quick';
if (!in_array($mode, ['quick', 'reconcile'], true)) {
    fwrite(STDERR, "Aufruf: php cron.php quick|reconcile\n");
    exit(1);
}

$log = function (string $m): void {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n");
};

// 1) Hängende Async-Jobs aus früheren Läufen zuerst einsammeln.
$pending = db()->query(
    "SELECT id FROM bm_requests WHERE scrape_job_status = 'pending' AND is_active = 1"
)->fetchAll();
foreach ($pending as $p) {
    $id  = (int) $p['id'];
    $res = resume_request_job($id);
    if ($res['err']) {
        $log("#$id Resume-Fehler: " . $res['err']);
    } elseif ($res['pending']) {
        $log("#$id Async noch nicht fertig.");
    } else {
        $log("#$id Async abgeholt: {$res['new']} neu.");
    }
}

// 2) Aktive, bereits gescrapte Aufträge aktualisieren.
$rows = db()->query(
    "SELECT id, property_name FROM bm_requests
      WHERE is_active = 1 AND scraped_at IS NOT NULL
        AND status NOT IN ('abgelehnt', 'abgeschlossen')
      ORDER BY id"
)->fetchAll();

$log("Cron $mode: " . count($rows) . ' aktive Auftraege');

foreach ($rows as $r) {
    $id  = (int) $r['id'];
    // Im Cron darf großzügig auf Async-Ergebnisse gewartet werden.
    $res = scrape_request($id, $mode, 180);
    if ($res['err']) {
        $log("#$id FEHLER: " . $res['err']);
    } elseif ($res['pending']) {
        $log("#$id wartet (Async) – wird beim naechsten Lauf abgeholt.");
    } else {
        $msg = "#$id ok: {$res['seen']} gesehen, {$res['new']} neu";
        if ($mode === 'reconcile') {
            $msg .= ", {$res['deleted']} geloescht";
        }
        $log($msg);
    }
}

$log('Fertig.');
