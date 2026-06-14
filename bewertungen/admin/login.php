<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

if (admin_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('login.php');
    }
    if (admin_login(trim($_POST['user'] ?? ''), (string) ($_POST['pass'] ?? ''))) {
        redirect('index.php');
    }
    flash_set('error', 'Login fehlgeschlagen.');
    redirect('login.php');
}

ob_start(); ?>
<form method="post" class="login-form">
  <?= csrf_field() ?>
  <label>Benutzername<input type="text" name="user" autocomplete="username" required autofocus></label>
  <label>Passwort<input type="password" name="pass" autocomplete="current-password" required></label>
  <button type="submit" class="btn btn--primary btn--block">Anmelden</button>
</form>
<?php
login_layout('Adminpanel – Login', ob_get_clean());
