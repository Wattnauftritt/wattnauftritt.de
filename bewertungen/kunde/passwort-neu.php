<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';

if (customer_id()) {
    redirect('index.php');
}

$token = (string) ($_POST['token'] ?? ($_GET['token'] ?? ''));

function find_by_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT * FROM bm_customers
          WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1
          LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

$customer = find_by_token($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('passwort-neu.php?token=' . urlencode($token));
    }
    if (!$customer) {
        flash_set('error', 'Der Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.');
        redirect('passwort-vergessen.php');
    }
    $new  = (string) ($_POST['new'] ?? '');
    $new2 = (string) ($_POST['new2'] ?? '');
    if (mb_strlen($new) < 8) {
        flash_set('error', 'Das neue Passwort muss mindestens 8 Zeichen haben.');
        redirect('passwort-neu.php?token=' . urlencode($token));
    }
    if ($new !== $new2) {
        flash_set('error', 'Die beiden Passwörter stimmen nicht überein.');
        redirect('passwort-neu.php?token=' . urlencode($token));
    }

    db()->prepare('UPDATE bm_customers SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $customer['id']]);
    flash_set('ok', 'Passwort wurde gesetzt. Sie können sich jetzt anmelden.');
    redirect('login.php');
}

ob_start();
if (!$customer) {
    ?>
    <p class="login-intro">Der Link ist ungültig oder abgelaufen.</p>
    <p class="login-foot"><a href="passwort-vergessen.php">Neuen Link anfordern</a></p>
    <?php
} else {
    ?>
    <p class="login-intro">Vergeben Sie ein neues Passwort.</p>
    <form method="post" class="login-form">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label>Neues Passwort (min. 8 Zeichen)<input type="password" name="new" autocomplete="new-password" required minlength="8" autofocus></label>
      <label>Passwort wiederholen<input type="password" name="new2" autocomplete="new-password" required minlength="8"></label>
      <button type="submit" class="btn btn--primary btn--block">Passwort speichern</button>
    </form>
    <?php
}
login_layout('Neues Passwort', ob_get_clean());
