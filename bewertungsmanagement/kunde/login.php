<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

if (customer_id()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('login.php');
    }
    if (customer_login(trim($_POST['user'] ?? ''), (string) ($_POST['pass'] ?? ''))) {
        redirect('index.php');
    }
    flash_set('error', 'Login fehlgeschlagen.');
    redirect('login.php');
}

ob_start(); ?>
<p class="login-intro">Verfolgen Sie den Stand Ihres Auftrags und Ihre Bewertungen.</p>
<form method="post" class="login-form">
  <?= csrf_field() ?>
  <label>Benutzername<input type="text" name="user" autocomplete="username" required autofocus></label>
  <label>Passwort<input type="password" name="pass" autocomplete="current-password" required></label>
  <button type="submit" class="btn btn--primary btn--block">Anmelden</button>
</form>
<p class="login-foot"><a href="/">← Zur Website</a></p>
<?php
login_layout('Kundenbereich – Login', ob_get_clean());
