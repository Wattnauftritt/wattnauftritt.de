<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_once __DIR__ . '/../inc/scrape.php';
require_once __DIR__ . '/../inc/mail.php';
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

    if (in_array($action, ['scrape', 'quick', 'reconcile', 'resume'], true)) {
        if ($action === 'resume') {
            $res = resume_request_job($id);
        } else {
            $mode = $action === 'reconcile' ? 'reconcile' : ($action === 'quick' ? 'quick' : 'full');
            $res = scrape_request($id, $mode);
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

    if ($action === 'accept') {
        db()->prepare(
            "UPDATE bm_requests
                SET accepted_at = COALESCE(accepted_at, NOW()),
                    status = IF(status IN ('neu','gescraped'), 'in_bearbeitung', status)
              WHERE id = ?"
        )->execute([$id]);
        flash_set('ok', 'Anfrage angenommen und ins Auftragssystem übernommen.');
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
        // Benutzername = E-Mail-Adresse des Anfragenden.
        $username = strtolower(trim((string) $request['contact_email']));

        // Existiert bereits ein Konto mit dieser E-Mail? Dann verknüpfen statt neu anlegen.
        $find = db()->prepare('SELECT id FROM bm_customers WHERE username = ?');
        $find->execute([$username]);
        $existingId = $find->fetchColumn();

        if ($existingId) {
            db()->prepare('UPDATE bm_requests SET customer_id = ? WHERE id = ?')->execute([(int) $existingId, $id]);
            flash_set('ok', 'Bestehender Kundenzugang (' . $username . ') mit diesem Auftrag verknüpft. '
                . 'Der Kunde sieht den Auftrag mit seinen vorhandenen Zugangsdaten.');
            redirect('request.php?id=' . $id);
        }

        $password = bin2hex(random_bytes(5)); // 10 Zeichen
        $hash = password_hash($password, PASSWORD_DEFAULT);

        db()->prepare(
            'INSERT INTO bm_customers (username, password_hash, display_name, email) VALUES (?, ?, ?, ?)'
        )->execute([$username, $hash, $request['contact_name'], $request['contact_email']]);
        $cid = (int) db()->lastInsertId();
        db()->prepare('UPDATE bm_requests SET customer_id = ? WHERE id = ?')->execute([$cid, $id]);

        // Zugangsdaten per E-Mail an den Kunden senden (Passwort nicht im Panel anzeigen).
        $sent = send_customer_credentials((string) $request['contact_email'], (string) $request['contact_name'], $username, $password);
        if ($sent) {
            flash_set('ok', 'Kundenlogin erstellt. Zugangsdaten wurden an ' . $request['contact_email'] . ' gesendet (Benutzer: ' . $username . ').');
        } else {
            // Fallback: Mailversand fehlgeschlagen -> Passwort einmalig anzeigen, damit es manuell übermittelt werden kann.
            flash_set('error', 'Kundenlogin erstellt, aber E-Mail-Versand fehlgeschlagen. Bitte manuell übermitteln – '
                . 'Benutzer: ' . $username . ' | Passwort: ' . $password);
        }
        redirect('request.php?id=' . $id);
    }

    redirect('request.php?id=' . $id);
}

// --- Daten für Anzeige -----------------------------------------------------
$request = load_request($id); // nach evtl. Änderungen neu laden
$isOrder = !empty($request['accepted_at']);
$firstScraped = $request['first_scraped_at'] ?: '1970-01-01 00:00:00';

// Zähler
$cnt = db()->prepare(
    'SELECT COUNT(*) AS total,
            SUM(is_deleted = 0) AS aktiv,
            SUM(is_deleted = 1) AS geloescht,
            SUM(first_seen_at > ?) AS neu
       FROM bm_reviews WHERE request_id = ?'
);
$cnt->execute([$firstScraped, $id]);
$counts = $cnt->fetch() ?: ['total' => 0, 'aktiv' => 0, 'geloescht' => 0, 'neu' => 0];

// Sterne-Verteilung (aktive Bewertungen)
$dstmt = db()->prepare(
    'SELECT rating, SUM(is_deleted = 0) AS aktiv
       FROM bm_reviews WHERE request_id = ? AND rating BETWEEN 1 AND 5 GROUP BY rating'
);
$dstmt->execute([$id]);
$dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($dstmt->fetchAll() as $d) { $dist[(int) $d['rating']] = (int) $d['aktiv']; }
$distMax = max(1, max($dist));

// Alle Bewertungen laden – Sortierung/Filter erfolgt clientseitig (kein Reload, kein Scrollsprung).
$rstmt = db()->prepare('SELECT * FROM bm_reviews WHERE request_id = ? ORDER BY first_seen_at DESC, id DESC');
$rstmt->execute([$id]);
$reviews = $rstmt->fetchAll();

$customer = null;
if (!empty($request['customer_id'])) {
    $cs = db()->prepare('SELECT * FROM bm_customers WHERE id = ?');
    $cs->execute([(int) $request['customer_id']]);
    $customer = $cs->fetch() ?: null;
}

[$stLbl, $stCls] = status_label($request['status']);

function review_row(array $r, string $firstScraped = ''): string
{
    $rating  = $r['rating'] !== null ? (int) $r['rating'] : 0;
    $stars   = $rating ? str_repeat('★', $rating) . str_repeat('☆', max(0, 5 - $rating)) : '';
    $isNew   = $firstScraped !== '' && !empty($r['first_seen_at']) && $r['first_seen_at'] > $firstScraped;
    $author  = trim((string) ($r['author'] ?? '')) ?: 'Anonym';
    $initial = mb_strtoupper(mb_substr($author, 0, 1));

    // Echtes Bewertungsdatum aus date_relative (z. B. "06/13/2018 10:13:27").
    $raw = trim((string) ($r['date_relative'] ?? ''));
    $ts  = $raw !== '' ? strtotime($raw) : false;
    $dateLabel = $ts !== false ? date('d.m.Y', $ts) : $raw;

    $h  = '<li class="rev' . ($r['is_deleted'] ? ' rev--del' : '') . '"'
        . ' data-rating="' . $rating . '"'
        . ' data-deleted="' . ((int) $r['is_deleted']) . '"'
        . ' data-new="' . ($isNew && !$r['is_deleted'] ? 1 : 0) . '"'
        . ' data-ts="' . ($ts !== false ? $ts : 0) . '"'
        . ' data-seen="' . e((string) ($r['first_seen_at'] ?? '')) . '"'
        . ' data-id="' . (int) $r['id'] . '">';
    $h .= '<div class="rev__inner"><div class="rev__avatar">' . e($initial) . '</div><div class="rev__body">';
    $h .= '<div class="rev__head"><strong>' . e($author) . '</strong>';
    $h .= '<span class="rev__stars" title="' . $rating . ' von 5">' . e($stars) . '</span>';
    if ($dateLabel !== '') { $h .= '<span class="muted small">' . e($dateLabel) . '</span>'; }
    if ($isNew && !$r['is_deleted']) { $h .= '<span class="badge st-scraped">neu</span>'; }
    if ($r['is_deleted']) { $h .= '<span class="badge st-reject">entfernt</span>'; }
    $h .= '</div>';
    if ($r['text']) { $h .= '<p class="rev__text">' . nl2br(e($r['text'])) . '</p>'; }
    $h .= '</div></div></li>';
    return $h;
}

panel_header(($isOrder ? 'Auftrag' : 'Anfrage') . ' #' . $id, 'admin');
?>
<p><a class="btn-sm" href="<?= $isOrder ? 'auftraege.php' : 'index.php' ?>">← Zurück zur <?= $isOrder ? 'Auftragsliste' : 'Anfrageliste' ?></a></p>

<div class="filterbar bm-maintabs" style="margin-bottom:1.2rem;">
  <button type="button" data-maintab="bewertungen" class="is-active">★ Bewertungen</button>
  <button type="button" data-maintab="verwaltung">Verwaltung &amp; Kunde</button>
</div>

<div class="tabpane" data-pane="bewertungen">
<section class="box">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
    <h2 style="margin:0;">Bewertungen</h2>
    <form method="post" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:0;">
      <?= csrf_field() ?>
      <?php if (($request['scrape_job_status'] ?? 'none') === 'pending'): ?>
        <button class="btn-sm" name="action" value="resume">Ergebnis abrufen</button>
      <?php elseif ($request['scraped_at']): ?>
        <button class="btn-sm" name="action" value="quick"
          onclick="return confirm('Nur neue Bewertungen abrufen (inkrementell, günstig)?');">
          ↻ Nur neue (günstig)
        </button>
        <button class="btn-sm" name="action" value="reconcile" style="background:var(--brand-deep);"
          onclick="return confirm('Abgleich starten und entfernte Bewertungen erkennen?');">
          Abgleichen
        </button>
        <button class="btn-sm" name="action" value="scrape" style="background:transparent;color:var(--ink);border:1px solid var(--line);"
          onclick="return confirm('Alle Bewertungen komplett neu abrufen? Verbraucht mehr Credits.');">
          Alle neu abrufen
        </button>
      <?php else: ?>
        <button class="btn-sm" name="action" value="scrape"
          onclick="return confirm('Bewertungen erstmalig abrufen? Das verbraucht API-Credits.');">
          Freigeben &amp; abrufen
        </button>
      <?php endif; ?>
    </form>
  </div>
  <p class="muted small" style="margin:.4rem 0 0;">
    <?= $request['scraped_at'] ? 'Zuletzt abgerufen: ' . e(date('d.m.Y H:i', strtotime($request['scraped_at']))) : 'Noch nicht abgerufen.' ?>
    <?= $request['reconciled_at'] ? ' · Letzter Abgleich: ' . e(date('d.m.Y H:i', strtotime($request['reconciled_at']))) : '' ?>
    · Anbieter: <?= e(reviews_provider()) ?>
  </p>
  <div class="stats" style="border-top:1px solid var(--line);margin-top:1rem;padding-top:1rem;">
    <div class="stat"><span class="stat__num"><?= (int) $counts['total'] ?></span><span class="stat__lbl">gesamt</span></div>
    <div class="stat"><span class="stat__num"><?= (int) $counts['aktiv'] ?></span><span class="stat__lbl">aktiv</span></div>
    <div class="stat"><span class="stat__num del"><?= (int) $counts['geloescht'] ?></span><span class="stat__lbl">gelöscht (entfernt)</span></div>
    <div class="stat"><span class="stat__num"><?= (int) $counts['neu'] ?></span><span class="stat__lbl">neu seit Start</span></div>
  </div>

  <?php if (!(int) $counts['total']): ?>
    <p class="muted" style="margin-top:1rem;">Noch keine Bewertungen abgerufen. Wechsle zu „Verwaltung &amp; Kunde", um sie abzurufen.</p>
  <?php else: ?>
    <h3 class="sub">Verteilung (aktiv) – zum Filtern klicken</h3>
    <div class="rating-dist" id="bm-dist">
      <?php for ($s = 5; $s >= 1; $s--): $w = (int) round($dist[$s] / $distMax * 100); ?>
        <button type="button" data-star="<?= $s ?>" title="Nur <?= $s ?>-Sterne-Bewertungen anzeigen">
          <span class="rd-label"><?= $s ?> <b>★</b></span>
          <span class="rd-track"><span class="rd-fill" style="width:<?= $w ?>%"></span></span>
          <span class="rd-count"><?= (int) $dist[$s] ?></span>
        </button>
      <?php endfor; ?>
    </div>

    <div class="filterbar" style="margin-top:1.2rem;">
      <span class="muted small" style="align-self:center;margin-right:.3rem;">Sortieren:</span>
      <button type="button" data-sort="neu" class="is-active">Neueste</button>
      <button type="button" data-sort="best">Beste</button>
      <button type="button" data-sort="schlecht">Schlechteste</button>
    </div>
    <div class="filterbar">
      <span class="muted small" style="align-self:center;margin-right:.3rem;">Filter:</span>
      <button type="button" data-filter="alle" class="is-active">Alle</button>
      <button type="button" data-filter="aktiv">Aktiv</button>
      <button type="button" data-filter="neu">Neu</button>
      <button type="button" data-filter="geloescht">Gelöscht</button>
    </div>

    <ul class="revlist" id="bm-revlist" style="margin-top:1rem;">
      <?php foreach ($reviews as $r) { echo review_row($r, $firstScraped); } ?>
    </ul>
    <p class="muted" id="bm-empty" style="margin-top:1rem;display:none;">Keine Bewertungen für diese Auswahl.</p>
  <?php endif; ?>
</section>
</div>

<div class="tabpane" data-pane="verwaltung" hidden>

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

    <?php if (empty($request['accepted_at'])): ?>
    <form method="post" style="margin-bottom:1rem;">
      <?= csrf_field() ?><input type="hidden" name="action" value="accept">
      <button class="btn btn--primary">Anfrage annehmen → Auftrag</button>
      <span class="muted small">Übernimmt die Anfrage ins Auftragssystem.</span>
    </form>
    <?php else: ?>
    <p class="muted small" style="margin-bottom:1rem;">
      Angenommen am <?= e(date('d.m.Y', strtotime($request['accepted_at']))) ?> ·
      <a href="abrechnung.php?id=<?= (int) $id ?>"><strong>Abrechnung erstellen</strong></a>
    </p>
    <?php endif; ?>

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
    <p class="muted small">Verbraucht API-Credits (<?= e(reviews_provider()) ?>). <strong>Nur neue</strong> = günstiger inkrementeller Abruf (nur die neuesten). <strong>Abgleichen</strong> = Vollabgleich inkl. Löscherkennung. <strong>Alle neu</strong> = kompletter Voll-Scrape.</p>

    <?php if (($request['scrape_job_status'] ?? 'none') === 'pending'): ?>
      <div class="flash flash--ok">Ein Abruf läuft im Hintergrund (Async). Klicke „Ergebnis abrufen", sobald er fertig ist.</div>
      <form method="post" class="actions-col">
        <?= csrf_field() ?>
        <button class="btn btn--primary" name="action" value="resume">Ergebnis abrufen</button>
      </form>
    <?php elseif ($request['scraped_at']): ?>
      <form method="post" class="actions-col">
        <?= csrf_field() ?>
        <button class="btn btn--primary" name="action" value="quick"
          onclick="return confirm('Nur neue Bewertungen abrufen (inkrementell, günstig)?');">
          Nur neue abrufen (günstig)
        </button>
        <button class="btn btn--ghost" name="action" value="reconcile" style="color:var(--ink);border-color:var(--line);"
          onclick="return confirm('Abgleich starten und Löschungen markieren?');">
          Abgleichen (Löschungen erkennen)
        </button>
        <button class="btn btn--ghost" name="action" value="scrape" style="color:var(--ink);border-color:var(--line);"
          onclick="return confirm('Alle Bewertungen komplett neu abrufen? Verbraucht mehr Credits.');">
          Alle neu abrufen
        </button>
      </form>
    <?php else: ?>
      <form method="post" class="actions-col">
        <?= csrf_field() ?>
        <button class="btn btn--primary" name="action" value="scrape"
          onclick="return confirm('Bewertungen erstmalig abrufen? Das verbraucht API-Credits.');">
          Freigeben &amp; Bewertungen abrufen
        </button>
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
</div>

<script>
(function () {
  var list = document.getElementById('bm-revlist');
  if (!list) return;
  var items = [].slice.call(list.children);
  var empty = document.getElementById('bm-empty');
  var state = { sort: 'neu', filter: 'alle', star: 0 };

  function mark(sel, attr, val) {
    document.querySelectorAll(sel).forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute(attr) === String(val));
    });
  }
  function apply() {
    var vis = items.filter(function (li) {
      if (state.filter === 'aktiv' && li.dataset.deleted === '1') return false;
      if (state.filter === 'geloescht' && li.dataset.deleted !== '1') return false;
      if (state.filter === 'neu' && li.dataset.new !== '1') return false;
      if (state.star && li.dataset.rating !== String(state.star)) return false;
      return true;
    });
    vis.sort(function (a, b) {
      if (state.sort === 'best') return (b.dataset.rating - a.dataset.rating) || (b.dataset.ts - a.dataset.ts);
      if (state.sort === 'schlecht') return (a.dataset.rating - b.dataset.rating) || (b.dataset.ts - a.dataset.ts);
      // Neueste: echtes Bewertungsdatum zuerst, dann Erfassung, dann id
      if (a.dataset.ts !== b.dataset.ts) return b.dataset.ts - a.dataset.ts;
      if (a.dataset.seen !== b.dataset.seen) return a.dataset.seen < b.dataset.seen ? 1 : -1;
      return b.dataset.id - a.dataset.id;
    });
    items.forEach(function (li) { li.style.display = 'none'; });
    vis.forEach(function (li) { li.style.display = ''; list.appendChild(li); });
    if (empty) empty.style.display = vis.length ? 'none' : '';
  }
  document.querySelectorAll('[data-sort]').forEach(function (b) {
    b.addEventListener('click', function () { state.sort = b.dataset.sort; mark('[data-sort]', 'data-sort', state.sort); apply(); });
  });
  document.querySelectorAll('[data-filter]').forEach(function (b) {
    b.addEventListener('click', function () { state.filter = b.dataset.filter; mark('[data-filter]', 'data-filter', state.filter); apply(); });
  });
  document.querySelectorAll('#bm-dist [data-star]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = parseInt(b.dataset.star, 10);
      state.star = (state.star === s) ? 0 : s;
      document.querySelectorAll('#bm-dist [data-star]').forEach(function (x) {
        x.classList.toggle('is-active', parseInt(x.dataset.star, 10) === state.star);
      });
      apply();
    });
  });
  apply();
})();

// Haupt-Tabs: Bewertungen / Verwaltung
(function () {
  var tabs = document.querySelectorAll('.bm-maintabs [data-maintab]');
  var panes = document.querySelectorAll('.tabpane[data-pane]');
  if (!tabs.length) return;
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      var target = t.dataset.maintab;
      tabs.forEach(function (x) { x.classList.toggle('is-active', x === t); });
      panes.forEach(function (p) { p.hidden = (p.dataset.pane !== target); });
    });
  });
})();
</script>
<?php panel_footer();
