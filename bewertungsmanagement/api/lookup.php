<?php
declare(strict_types=1);

/** Öffentlicher (geschützter) Live-Lookup eines Objekts. 1 SerpApi-Credit pro Suche. */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/serpapi.php';

// Honeypot (Bots füllen versteckte Felder)
if (!empty($_GET['website'])) {
    json_out(['ok' => false, 'error' => 'Abgelehnt.'], 400);
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    json_out(['ok' => false, 'error' => 'Bitte mindestens 3 Zeichen eingeben.']);
}

if (!rate_ok('lookup', LOOKUP_RATE_LIMIT, 3600)) {
    json_out(['ok' => false, 'error' => 'Zu viele Suchanfragen. Bitte etwas später erneut versuchen.'], 429);
}
rate_hit('lookup');

try {
    $props = serpapi_property_lookup($q);
} catch (Throwable $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], 502);
}

json_out(['ok' => true, 'count' => count($props), 'properties' => $props]);
