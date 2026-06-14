<?php
declare(strict_types=1);

/** Basis-URL der Seite (für Links in E-Mails). */
function site_url(): string
{
    if (defined('SITE_URL') && SITE_URL) {
        return rtrim(SITE_URL, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'wattnauftritt.de';
    return 'https://' . $host;
}

/** Einfacher Text-Mailversand (UTF-8). Gibt Erfolg zurück. */
function bm_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $headers = 'From: ' . MAIL_FROM . "\r\n";
    if ($replyTo) {
        $headers .= 'Reply-To: ' . $replyTo . "\r\n";
    }
    $headers .= 'Content-Type: text/plain; charset=utf-8';

    return @mail(
        $to,
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        $headers
    );
}

/** Benachrichtigung an info@ über eine neue Anfrage. */
function notify_new_request(array $req): void
{
    $body = implode("\n", [
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
    ]);

    bm_mail(NOTIFY_EMAIL, 'Neue Bewertungsmanagement-Anfrage: ' . $req['property_name'], $body, $req['contact_email']);
}

/** Zugangsdaten an den Kunden senden (bei Erstellung des Logins). */
function send_customer_credentials(string $email, string $name, string $username, string $password): bool
{
    $url  = site_url() . '/bewertungen/kunde/login.php';
    $body = implode("\n", [
        'Hallo ' . ($name ?: 'zusammen') . ',',
        '',
        'für die Verfolgung Ihres Auftrags haben wir Ihnen einen persönlichen Zugang eingerichtet:',
        '',
        'Login:        ' . $url,
        'Benutzername: ' . $username,
        'Passwort:     ' . $password,
        '',
        'Bitte ändern Sie Ihr Passwort nach dem ersten Login (oben „Passwort ändern").',
        'Falls Sie es einmal vergessen, können Sie es über „Passwort vergessen?" neu setzen.',
        '',
        'Viele Grüße',
        'Watt\'n Auftritt',
    ]);

    return bm_mail($email, 'Ihr Zugang zum Bewertungsmanagement', $body);
}

/** Link zum Zurücksetzen des Passworts senden. */
function send_password_reset(string $email, string $name, string $resetUrl): bool
{
    $body = implode("\n", [
        'Hallo ' . ($name ?: 'zusammen') . ',',
        '',
        'Sie haben das Zurücksetzen Ihres Passworts angefordert.',
        'Über folgenden Link können Sie ein neues Passwort vergeben (1 Stunde gültig):',
        '',
        $resetUrl,
        '',
        'Wenn Sie das nicht waren, ignorieren Sie diese E-Mail einfach – es ändert sich nichts.',
        '',
        'Viele Grüße',
        'Watt\'n Auftritt',
    ]);

    return bm_mail($email, 'Passwort zurücksetzen – Bewertungsmanagement', $body);
}
