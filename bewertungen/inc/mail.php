<?php
declare(strict_types=1);

/** Benachrichtigung an info@ über eine neue Anfrage. */
function notify_new_request(array $req): void
{
    $subject = 'Neue Bewertungsmanagement-Anfrage: ' . $req['property_name'];
    $lines = [
        'Es ist eine neue Anfrage über die Website eingegangen.',
        '',
        'Objekt:    ' . $req['property_name'],
        'Typ:       ' . ($req['property_type'] ?: '-'),
        'Token:     ' . $req['property_token'],
        'Google:    ' . ($req['property_reviews'] ?? '?') . ' Bewertungen (Ø ' . ($req['property_rating'] ?? '?') . ')',
        '',
        'Name:      ' . $req['contact_name'],
        'E-Mail:    ' . $req['contact_email'],
        'Telefon:   ' . ($req['contact_phone'] ?: '-'),
        'Firma:     ' . ($req['company'] ?: '-'),
        '',
        'Nachricht:',
        ($req['message'] ?: '-'),
        '',
        '-> Im Adminpanel prüfen und freigeben, um die Bewertungen abzurufen.',
    ];

    $headers = 'From: ' . MAIL_FROM . "\r\n"
        . 'Reply-To: ' . $req['contact_email'] . "\r\n"
        . 'Content-Type: text/plain; charset=utf-8';

    @mail(
        NOTIFY_EMAIL,
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        implode("\n", $lines),
        $headers
    );
}
