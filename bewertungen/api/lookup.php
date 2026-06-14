<?php
declare(strict_types=1);

/** Öffentlicher (geschützter) Live-Lookup eines Objekts. 1 SerpApi-Credit pro Suche. */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/reviews_provider.php';

// Honeypot (Bots füllen versteckte Felder)
if (!empty($_GET['website'])) {
    json_out(['ok' => false, 'error' => 'Abgelehnt.'], 400);
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    json_out(['ok' => false, 'error' => 'Bitte mindestens 3 Zeichen eingeben.']);
}

try {
    if (!rate_ok('lookup', LOOKUP_RATE_LIMIT, 3600)) {
        json_out(['ok' => false, 'error' => 'Zu viele Suchanfragen. Bitte etwas später erneut versuchen.'], 429);
    }
    rate_hit('lookup');
} catch (Throwable $ex) {
    error_log('[bewertungen/lookup] DB: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Dienst derzeit nicht verfügbar. Bitte später erneut versuchen.']);
}

try {
    $props = provider_lookup($q);
} catch (Throwable $ex) {
    // Echten Grund (z. B. Kontingent erschöpft, ungültiger Key) nur protokollieren,
    // dem Besucher eine neutrale Meldung zeigen. HTTP 200, damit Cloudflare die
    // JSON-Antwort nicht durch eine eigene 5xx-Fehlerseite ersetzt.
    error_log('[bewertungen/lookup] SerpApi: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Die Live-Suche ist derzeit nicht verfügbar. Bitte versuchen Sie es später erneut oder kontaktieren Sie uns direkt.']);
}

json_out(['ok' => true, 'count' => count($props), 'properties' => $props]);
