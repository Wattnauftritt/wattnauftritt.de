<?php
declare(strict_types=1);

/** Öffentliche Anfrage anlegen. KEIN Scrape hier – der läuft erst nach Freigabe im Adminpanel. */

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'Nur POST erlaubt.'], 405);
}
// Honeypot
if (!empty($_POST['website'])) {
    json_out(['ok' => false, 'error' => 'Abgelehnt.'], 400);
}
if (!rate_ok('submit', SUBMIT_RATE_LIMIT, 86400)) {
    json_out(['ok' => false, 'error' => 'Zu viele Anfragen von dieser Verbindung. Bitte später erneut versuchen.'], 429);
}

$name    = trim($_POST['contact_name'] ?? '');
$email   = trim($_POST['contact_email'] ?? '');
$token   = trim($_POST['property_token'] ?? '');
$pname   = trim($_POST['property_name'] ?? '');
$consent = !empty($_POST['consent']);

$errors = [];
if (mb_strlen($name) < 2)                              { $errors[] = 'Bitte Namen angeben.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        { $errors[] = 'Bitte gültige E-Mail-Adresse angeben.'; }
if ($token === '' || $pname === '')                    { $errors[] = 'Bitte zuerst ein Objekt auswählen.'; }
if (!$consent)                                         { $errors[] = 'Bitte der Verarbeitung zustimmen.'; }
if ($errors) {
    json_out(['ok' => false, 'error' => implode(' ', $errors)], 422);
}

$rating  = ($_POST['property_rating'] ?? '') !== '' ? (float) $_POST['property_rating'] : null;
$reviews = ($_POST['property_reviews'] ?? '') !== '' ? (int) $_POST['property_reviews'] : null;
$lat     = ($_POST['property_lat'] ?? '') !== '' ? (float) $_POST['property_lat'] : null;
$lng     = ($_POST['property_lng'] ?? '') !== '' ? (float) $_POST['property_lng'] : null;

$data = [
    'contact_name'     => $name,
    'contact_email'    => $email,
    'contact_phone'    => nn($_POST['contact_phone'] ?? null),
    'company'          => nn($_POST['company'] ?? null),
    'message'          => nn($_POST['message'] ?? null),
    'property_name'    => $pname,
    'property_token'   => $token,
    'property_type'    => nn($_POST['property_type'] ?? null),
    'property_rating'  => $rating,
    'property_reviews' => $reviews,
    'property_lat'     => $lat,
    'property_lng'     => $lng,
];

try {
    $sql = 'INSERT INTO bm_requests
        (contact_name, contact_email, contact_phone, company, message,
         property_name, property_token, property_type, property_rating, property_reviews,
         property_lat, property_lng)
        VALUES (:contact_name, :contact_email, :contact_phone, :company, :message,
         :property_name, :property_token, :property_type, :property_rating, :property_reviews,
         :property_lat, :property_lng)';
    db()->prepare($sql)->execute($data);
    $id = (int) db()->lastInsertId();
} catch (Throwable $ex) {
    json_out(['ok' => false, 'error' => 'Speichern fehlgeschlagen. Bitte später erneut versuchen.'], 500);
}

rate_hit('submit');
notify_new_request($data);

json_out(['ok' => true, 'id' => $id, 'message' => 'Vielen Dank! Ihre Anfrage ist eingegangen. Wir melden uns zeitnah bei Ihnen.']);
