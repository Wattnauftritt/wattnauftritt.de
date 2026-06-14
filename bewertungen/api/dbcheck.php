<?php
declare(strict_types=1);

/**
 * TEMPORÄRES Diagnose-Skript. Nach der Fehlersuche wieder löschen!
 * Aufruf: /bewertungen/api/dbcheck.php?token=wnadbcheck
 * Zeigt DB-Name/-User (kein Passwort), Verbindungsstatus, vorhandene Tabellen
 * und den echten Fehlertext.
 */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

if (($_GET['token'] ?? '') !== 'wnadbcheck') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$out = [
    'php_version' => PHP_VERSION,
    'db_host'     => DB_HOST,
    'db_name'     => DB_NAME,
    'db_user'     => DB_USER,
    'has_pdo_mysql' => extension_loaded('pdo_mysql'),
];

try {
    $pdo = db();
    $out['connect'] = 'ok';
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $out['tables'] = $tables;
    $out['has_bm_api_hits'] = in_array('bm_api_hits', $tables, true);
    $out['ok'] = $out['has_bm_api_hits'];
    if (!$out['ok']) {
        $out['hint'] = 'Verbindung steht, aber Tabellen fehlen -> schema.sql in diese DB importieren.';
    }
} catch (Throwable $e) {
    $out['ok'] = false;
    $out['connect'] = 'FEHLER';
    $out['error'] = $e->getMessage();
    $out['hint'] = 'DB-Name/Benutzer/Passwort in config.php pruefen (Plesk-Praefix beachten).';
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
