<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/mail.php';

if (customer_id()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('passwort-vergessen.php');
    }
    $email = trim($_POST['email'] ?? '');

    // Aus Datenschutzgründen immer dieselbe Rückmeldung (keine Konto-Enumeration).
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare('SELECT id, display_name FROM bm_customers WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $c = $stmt->fetch();
        if ($c) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde
            db()->prepare('UPDATE bm_customers SET reset_token = ?, reset_expires = ? WHERE id = ?')
                ->execute([hash('sha256', $token), $expires, (int) $c['id']]);
            $resetUrl = site_url() . '/bewertungen/kunde/passwort-neu.php?token=' . $token;
            send_password_reset($email, (string) $c['display_name'], $resetUrl);
        }
    }

    flash_set('ok', 'Falls ein Konto mit dieser E-Mail existiert, haben wir Ihnen einen Link zum Zurücksetzen gesendet.');
    redirect('login.php');
}

ob_start(); ?>
<p class="login-intro">Geben Sie Ihre E-Mail-Adresse ein. Wir senden Ihnen einen Link zum Zurücksetzen.</p>
<form method="post" class="login-form">
  <?= csrf_field() ?>
  <label>E-Mail-Adresse<input type="email" name="email" autocomplete="email" required autofocus></label>
  <button type="submit" class="btn btn--primary btn--block">Link anfordern</button>
</form>
<p class="login-foot"><a href="login.php">← Zurück zum Login</a></p>
<?php
login_layout('Passwort vergessen', ob_get_clean());
