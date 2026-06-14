<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/scrape.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);

function load_request(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM bm_requests WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    return $r ?: null;
}

$request = load_request($id);
if (!$request) {
    http_response_code(404);
    panel_header('Nicht gefunden', 'admin');
    echo '<p class="muted">Anfrage nicht gefunden.</p><p><a class="btn-sm" href="index.php">Zurück</a></p>';
    panel_footer();
    exit;
}

// --- Aktionen --------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung abgelaufen. Bitte erneut versuchen.');
        redirect('request.php?id=' . $id);
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'scrape' || $action === 'reconcile' || $action === 'resume') {
        if ($action === 'resume') {
            $res = resume_request_job($id);
        } else {
            $res = scrape_request($id, $action === 'reconcile' ? 'reconcile' : 'full');
        }
        if ($res['err']) {
            flash_set('error', 'Abruf abgebrochen: ' . $res['err']);
        } elseif (!empty($res['pending'])) {
            flash_set('ok', 'Abruf gestartet – das Ergebnis wird im Hintergrund erstellt. In ein paar Sekunden „Ergebnis abrufen" klicken.');
        } else {
            $msg = sprintf('%d Bewertungen verarbeitet, davon %d neu.', $res['seen'], $res['new']);
            if ($action === 'reconcile') {
                $msg .= sprintf(' %d als gelöscht markiert.', $res['deleted']);
            }
            flash_set('ok', $msg);
        }
        redirect('request.php?id=' . $id);
    }

    if ($action === 'toggle_active') {
        $newActive = empty($request['is_active']) ? 1 : 0;
        db()->prepare('UPDATE bm_requests SET is_active = ? WHERE id = ?')->execute([$newActive, $id]);
        flash_set('ok', $newActive
            ? 'Auftrag aktiviert – wird vom Cron automatisch aktualisiert.'
            : 'Auftrag deaktiviert – keine automatischen Updates mehr (keine API-Kosten).');
        redirect('request.php?id=' . $id);
    }

    if ($action === 'status') {
        $new = $_POST['status'] ?? '';
        $valid = ['neu', 'gescraped', 'in_bearbeitung', 'abgeschlossen', 'abgelehnt'];
        if (in_array($new, $valid, true)) {
            db()->prepare('UPDATE bm_requests SET status = ? WHERE id = ?')->execute([$new, $id]);
            flash_set('ok', 'Status aktualisiert.');
        }
        redirect('request.php?id=' . $id);
    }

    if ($action === 'note') {
        db()->prepare('UPDATE bm_requests SET admin_note = ? WHERE id = ?')->execute([nn($_POST['admin_note'] ?? null), $id]);
        flash_set('ok', 'Notiz gespeichert.');
        redirect('request.php?id=' . $id);
    }

    if ($action === 'create_customer') {
        if (!empty($request['customer_id'])) {
            flash_set('error', 'Für diese Anfrage existiert bereits ein Kundenlogin.');
            redirect('request.php?id=' . $id);
        }
        // Benutzernamen aus E-Mail-Lokalteil + Zufallssuffix bilden
        $baseRaw = strtolower((string) strtok((string) $request['contact_email'], '@'));
        $base = preg_replace('/[^a-z0-9]+/', '', $baseRaw) ?: 'kunde';
        $username = $base;
        for ($i = 0; $i < 6; $i++) {
            $exists = db()->prepare('SELECT 1 FROM bm_customers WHERE username = ?');
            $exists->execute([$username]);
            if (!$exists->fetchColumn()) {
                break;
            }
            $username = $base . random_int(100, 999);
        }
        $password = bin2hex(random_bytes(5)); // 10 Zeichen
        $hash = password_hash($password, PASSWORD_DEFAULT);

        db()->prepare(
            'INSERT INTO bm_customers (username, password_hash, display_name, email) VALUES (?, ?, ?, ?)'
        )->execute([$username, $hash, $request['contact_name'], $request['contact_email']]);
        $cid = (int) db()->lastInsertId();
        db()->prepare('UPDATE bm_requests SET customer_id = ? WHERE id = ?')->execute([$cid, $id]);

        flash_set('ok', 'Kundenlogin erstellt – bitte JETZT notieren (Passwort wird nur einmal angezeigt): '
            . 'Benutzer: ' . $username . ' | Passwort: ' . $password);
        redirect('request.php?id=' . $id);
    }

    redirect('request.php?id=' . $id);
}

// --- Daten für Anzeige -----------------------------------------------------
$request = load_request($id); // nach evtl. Änderungen neu laden
$reviewsStmt = db()->prepare('SELECT * FROM bm_reviews WHERE request_id = ? ORDER BY is_deleted ASC, id DESC');
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();
$active  = array_values(array_filter($reviews, fn($r) => !$r['is_deleted']));
$deleted = array_values(array_filter($reviews, fn($r) => $r['is_deleted']));

$customer = null;
if (!empty($request['customer_id'])) {
    $cs = db()->prepare('SELECT * FROM bm_customers WHERE id = ?');
    $cs->execute([(int) $request['customer_id']]);
    $customer = $cs->fetch() ?: null;
}

[$stLbl, $stCls] = status_label($request['status']);

function review_row(array $r): string
{
    $stars = $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) . str_repeat('☆', max(0, 5 - (int) $r['rating'])) : '';
    $html  = '<li class="rev' . ($r['is_deleted'] ? ' rev--del' : '') . '">';
    $html .= '<div class="rev__head"><strong>' . e($r['author'] ?: 'Anonym') . '</strong>';
    $html .= '<span class="rev__stars">' . e($stars) . '</span>';
    if ($r['date_relative']) { $html .= '<span class="muted small">' . e($r['date_relative']) . '</span>'; }
    if ($r['is_deleted']) { $html .= '<span class="badge st-reject">gelöscht</span>'; }
    $html .= '</div>';
    if ($r['text']) { $html .= '<p class="rev__text">' . nl2br(e($r['text'])) . '</p>'; }
    $html .= '</li>';
    return $html;
}

panel_header('Anfrage #' . $id, 'admin');
?>
<p><a class="btn-sm" href="index.php">← Zurück zur Liste</a></p>

<div class="grid2">
  <section class="box">
    <h2>Objekt</h2>
    <table class="kv">
      <tr><th>Name</th><td><?= e($request['property_name']) ?></td></tr>
      <tr><th>Typ</th><td><?= e($request['property_type'] ?: '–') ?></td></tr>
      <tr><th>Google</th><td><?= e((string) ($request['property_reviews'] ?? '?')) ?> Bewertungen · Ø <?= e((string) ($request['property_rating'] ?? '?')) ?></td></tr>
      <tr><th>Token</th><td><code class="token"><?= e($request['property_token']) ?></code></td></tr>
      <?php if ($request['property_lat']): ?><tr><th>Standort</th><td><?= e($request['property_lat']) ?>, <?= e($request['property_lng']) ?></td></tr><?php endif; ?>
    </table>
  </section>

  <section class="box">
    <h2>Kontakt</h2>
    <table class="kv">
      <tr><th>Name</th><td><?= e($request['contact_name']) ?></td></tr>
      <tr><th>E-Mail</th><td><a href="mailto:<?= e($request['contact_email']) ?>"><?= e($request['contact_email']) ?></a></td></tr>
      <tr><th>Telefon</th><td><?= e($request['contact_phone'] ?: '–') ?></td></tr>
      <tr><th>Firma</th><td><?= e($request['company'] ?: '–') ?></td></tr>
      <tr><th>Eingang</th><td><?= e(date('d.m.Y H:i', strtotime($request['created_at']))) ?></td></tr>
    </table>
    <?php if ($request['message']): ?><p class="msg"><?= nl2br(e($request['message'])) ?></p><?php endif; ?>
  </section>
</div>

<div class="grid2">
  <section class="box">
    <h2>Status &amp; Verwaltung</h2>
    <p>Aktuell: <span class="badge <?= e($stCls) ?>"><?= e($stLbl) ?></span>
       &nbsp;Auftrag:
       <?php if (!empty($request['is_active'])): ?>
         <span class="badge st-done">aktiv</span>
       <?php else: ?>
         <span class="badge st-reject">inaktiv</span>
       <?php endif; ?>
    </p>
    <form method="post" class="inline-form" style="margin-bottom:1rem;">
      <?= csrf_field() ?><input type="hidden" name="action" value="toggle_active">
      <button class="btn-sm" onclick="return confirm('<?= !empty($request['is_active']) ? 'Auftrag deaktivieren? Es laufen dann keine automatischen Updates mehr.' : 'Auftrag wieder aktivieren? Der Cron aktualisiert ihn dann automatisch.' ?>');">
        <?= !empty($request['is_active']) ? 'Auftrag deaktivieren' : 'Auftrag aktivieren' ?>
      </button>
      <span class="muted small">Nur aktive Aufträge werden automatisch aktualisiert (Kostenkontrolle).</span>
    </form>
    <form method="post" class="inline-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="status">
      <select name="status">
        <?php foreach (['neu', 'gescraped', 'in_bearbeitung', 'abgeschlossen', 'abgelehnt'] as $s): [$l] = status_label($s); ?>
          <option value="<?= e($s) ?>" <?= $request['status'] === $s ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn-sm">Status setzen</button>
    </form>

    <form method="post" style="margin-top:1rem;">
      <?= csrf_field() ?><input type="hidden" name="action" value="note">
      <label class="lbl">Interne Notiz</label>
      <textarea name="admin_note" rows="3"><?= e($request['admin_note'] ?? '') ?></textarea>
      <button class="btn-sm" style="margin-top:.5rem;">Notiz speichern</button>
    </form>
  </section>

  <section class="box">
    <h2>Bewertungen abrufen</h2>
    <p class="muted small">Verbraucht API-Credits (<?= e(reviews_provider()) ?>). „Abgleichen" erkennt zusätzlich gelöschte Bewertungen.</p>

    <?php if (($request['scrape_job_status'] ?? 'none') === 'pending'): ?>
      <div class="flash flash--ok">Ein Abruf läuft im Hintergrund (Async). Klicke „Ergebnis abrufen", sobald er fertig ist.</div>
      <form method="post" class="actions-col">
        <?= csrf_field() ?>
        <button class="btn btn--primary" name="action" value="resume">Ergebnis abrufen</button>
      </form>
    <?php else: ?>
      <form method="post" class="actions-col">
        <?= csrf_field() ?>
        <button class="btn btn--primary" name="action" value="scrape"
          onclick="return confirm('Jetzt alle Bewertungen abrufen? Das verbraucht API-Credits.');">
          <?= $request['scraped_at'] ? 'Erneut vollständig abrufen' : 'Freigeben &amp; Bewertungen abrufen' ?>
        </button>
        <?php if ($request['scraped_at']): ?>
        <button class="btn btn--ghost" name="action" value="reconcile" style="color:var(--ink);border-color:var(--line);"
          onclick="return confirm('Abgleich starten und Löschungen markieren?');">
          Abgleichen (Löschungen erkennen)
        </button>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <p class="muted small" style="margin-top:.6rem;">
      <?= $request['scraped_at'] ? 'Zuletzt abgerufen: ' . e(date('d.m.Y H:i', strtotime($request['scraped_at']))) : 'Noch nicht abgerufen.' ?>
      <?= $request['reconciled_at'] ? ' · Letzter Abgleich: ' . e(date('d.m.Y H:i', strtotime($request['reconciled_at']))) : '' ?>
    </p>
  </section>
</div>

<section class="box">
  <h2>Kundenzugang</h2>
  <?php if ($customer): ?>
    <p>Login vorhanden: <strong><?= e($customer['username']) ?></strong>
       <span class="muted small">(erstellt <?= e(date('d.m.Y', strtotime($customer['created_at']))) ?><?= $customer['last_login_at'] ? ', zuletzt aktiv ' . e(date('d.m.Y H:i', strtotime($customer['last_login_at']))) : '' ?>)</span>
    </p>
    <p class="muted small">Der Kunde sieht unter <code>/bewertungen/kunde/</code> seinen Auftrag mit aktiven und gelöschten Bewertungen.</p>
  <?php else: ?>
    <p class="muted small">Erzeugt einen Login, mit dem der Kunde seinen Auftrag verfolgen kann. Das Passwort wird nur einmal angezeigt.</p>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="create_customer">
      <button class="btn btn--primary">Kundenlogin erstellen</button>
    </form>
  <?php endif; ?>
</section>

<section class="box">
  <h2>Bewertungen <span class="muted small">(<?= count($active) ?> aktiv<?= $deleted ? ', ' . count($deleted) . ' gelöscht' : '' ?>)</span></h2>
  <?php if (!$reviews): ?>
    <p class="muted">Noch keine Bewertungen abgerufen.</p>
  <?php else: ?>
    <?php if ($active): ?>
      <h3 class="sub">Aktiv</h3>
      <ul class="revlist"><?php foreach ($active as $r) { echo review_row($r); } ?></ul>
    <?php endif; ?>
    <?php if ($deleted): ?>
      <h3 class="sub">Gelöscht</h3>
      <ul class="revlist"><?php foreach ($deleted as $r) { echo review_row($r); } ?></ul>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php panel_footer();
