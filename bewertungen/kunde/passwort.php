<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_customer();

$cid = customer_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('passwort.php');
    }
    $current = (string) ($_POST['current'] ?? '');
    $new     = (string) ($_POST['new'] ?? '');
    $new2    = (string) ($_POST['new2'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM bm_customers WHERE id = ?');
    $stmt->execute([$cid]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        flash_set('error', 'Aktuelles Passwort ist nicht korrekt.');
        redirect('passwort.php');
    }
    if (mb_strlen($new) < 8) {
        flash_set('error', 'Das neue Passwort muss mindestens 8 Zeichen haben.');
        redirect('passwort.php');
    }
    if ($new !== $new2) {
        flash_set('error', 'Die beiden neuen Passwörter stimmen nicht überein.');
        redirect('passwort.php');
    }

    db()->prepare('UPDATE bm_customers SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $cid]);
    flash_set('ok', 'Passwort geändert.');
    redirect('index.php');
}

panel_header('Passwort ändern', 'kunde');
?>
<section class="box" style="max-width:480px;">
  <form method="post">
    <?= csrf_field() ?>
    <div class="bm-field" style="margin-bottom:1rem;">
      <label class="lbl">Aktuelles Passwort</label>
      <input type="password" name="current" autocomplete="current-password" required>
    </div>
    <div class="bm-field" style="margin-bottom:1rem;">
      <label class="lbl">Neues Passwort (min. 8 Zeichen)</label>
      <input type="password" name="new" autocomplete="new-password" required minlength="8">
    </div>
    <div class="bm-field" style="margin-bottom:1rem;">
      <label class="lbl">Neues Passwort wiederholen</label>
      <input type="password" name="new2" autocomplete="new-password" required minlength="8">
    </div>
    <button class="btn btn--primary">Passwort speichern</button>
    <a class="btn-sm" href="index.php" style="margin-left:.6rem;">Zurück</a>
  </form>
</section>
<?php panel_footer();
