<?php
declare(strict_types=1);

/**
 * Gemeinsamer Einstiegspunkt: Konfiguration laden, Session starten, Helfer einbinden.
 * Jede PHP-Datei dieses Moduls bindet zuerst diese Datei ein.
 */

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Konfiguration fehlt: bitte config.php aus config.sample.php erstellen.');
}
require_once $configFile;

// Fehler protokollieren, aber nicht an Besucher ausgeben.
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Session mit sicheren Cookie-Optionen.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'WNA_BM');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
