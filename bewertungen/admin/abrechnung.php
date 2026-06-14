<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM bm_requests WHERE id = ?');
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) {
    http_response_code(404);
    panel_header('Nicht gefunden', 'admin');
    echo '<p class="muted">Auftrag nicht gefunden.</p>';
    panel_footer();
    exit;
}

// Entfernte (gelöschte) Bewertungen = abrechenbare Leistung.
$rev = db()->prepare('SELECT * FROM bm_reviews WHERE request_id = ? AND is_deleted = 1 ORDER BY deleted_at DESC, id DESC');
$rev->execute([$id]);
$removed = $rev->fetchAll();
$count   = count($removed);

// Preise: per GET anpassbar, Default aus config.
$price = isset($_GET['p']) && $_GET['p'] !== '' ? (float) str_replace(',', '.', $_GET['p']) : (defined('PRICE_PER_REMOVAL') ? (float) PRICE_PER_REMOVAL : 0.0);
$base  = isset($_GET['b']) && $_GET['b'] !== '' ? (float) str_replace(',', '.', $_GET['b']) : (defined('BILLING_BASE_FEE') ? (float) BILLING_BASE_FEE : 0.0);
$cur   = defined('CURRENCY') ? CURRENCY : 'EUR';

$net   = $base + $count * $price;
$vat   = round($net * 0.19, 2);
$gross = $net + $vat;

$money = fn(float $v): string => number_format($v, 2, ',', '.') . ' ' . $cur;

panel_header('Abrechnung – Auftrag #' . $id, 'admin');
?>
<style>
@media print {
  .topbar, .no-print { display: none !important; }
  .wrap { padding: 0 !important; }
  .box { box-shadow: none !important; border: 0 !important; }
}
.bill-head { display:flex; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
.bill-tot { width:100%; max-width:360px; margin-left:auto; }
.bill-tot td { padding:.35rem 0; }
.bill-tot tr.sum td { border-top:1px solid var(--line); font-weight:700; font-size:1.1rem; }
</style>

<p class="no-print">
  <a class="btn-sm" href="request.php?id=<?= (int) $id ?>">← Zum Auftrag</a>
  <button class="btn-sm" onclick="window.print()" style="border:0;cursor:pointer;">Drucken / als PDF speichern</button>
</p>

<section class="box">
  <div class="bill-head">
    <div>
      <h2 style="margin:0;">Abrechnung Bewertungsmanagement</h2>
      <p class="muted small" style="margin:.3rem 0 0;">Auftrag #<?= (int) $id ?> · Datum <?= e(date('d.m.Y')) ?></p>
    </div>
    <div>
      <strong><?= e($request['contact_name']) ?></strong><br>
      <?php if ($request['company']): ?><?= e($request['company']) ?><br><?php endif; ?>
      <span class="muted small"><?= e($request['contact_email']) ?></span>
    </div>
  </div>

  <table class="kv" style="margin-top:1rem;">
    <tr><th>Objekt</th><td><?= e($request['property_name']) ?></td></tr>
    <tr><th>Zeitraum</th><td><?= $request['accepted_at'] ? e(date('d.m.Y', strtotime($request['accepted_at']))) : '–' ?> – <?= e(date('d.m.Y')) ?></td></tr>
    <tr><th>Entfernte Bewertungen</th><td><strong><?= $count ?></strong></td></tr>
  </table>
</section>

<section class="box">
  <h2>Leistungen</h2>
  <form method="get" class="inline-form no-print" style="margin-bottom:1rem;">
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <label class="lbl" style="margin:0;">Preis je Entfernung
      <input type="text" name="p" value="<?= e(number_format($price, 2, '.', '')) ?>" style="width:90px;">
    </label>
    <label class="lbl" style="margin:0;">Grundgebühr
      <input type="text" name="b" value="<?= e(number_format($base, 2, '.', '')) ?>" style="width:90px;">
    </label>
    <button class="btn-sm">Aktualisieren</button>
  </form>

  <table class="data" style="min-width:auto;">
    <thead><tr><th>Position</th><th>Menge</th><th>Einzel</th><th style="text-align:right;">Summe</th></tr></thead>
    <tbody>
      <?php if ($base > 0): ?>
      <tr><td>Grundgebühr</td><td>1</td><td><?= $money($base) ?></td><td style="text-align:right;"><?= $money($base) ?></td></tr>
      <?php endif; ?>
      <tr>
        <td>Entfernte Bewertungen</td>
        <td><?= $count ?></td>
        <td><?= $money($price) ?></td>
        <td style="text-align:right;"><?= $money($count * $price) ?></td>
      </tr>
    </tbody>
  </table>

  <table class="bill-tot" style="margin-top:1rem;">
    <tr><td>Netto</td><td style="text-align:right;"><?= $money($net) ?></td></tr>
    <tr><td>zzgl. 19 % USt.</td><td style="text-align:right;"><?= $money($vat) ?></td></tr>
    <tr class="sum"><td>Gesamt</td><td style="text-align:right;"><?= $money($gross) ?></td></tr>
  </table>
  <p class="muted small">Hinweis: Vorschau zur internen Abrechnung. Für eine rechtsgültige Rechnung bitte in euer Rechnungssystem übernehmen (Rechnungsnummer, Steuer-/USt-Angaben, Anschrift).</p>
</section>

<?php if ($removed): ?>
<section class="box">
  <h2>Entfernte Bewertungen im Detail</h2>
  <ul class="revlist">
    <?php foreach ($removed as $r):
        $stars = $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) . str_repeat('☆', max(0, 5 - (int) $r['rating'])) : ''; ?>
      <li class="rev rev--del">
        <div class="rev__head">
          <strong><?= e($r['author'] ?: 'Anonym') ?></strong>
          <span class="rev__stars"><?= e($stars) ?></span>
          <?php if ($r['deleted_at']): ?><span class="muted small">entfernt am <?= e(date('d.m.Y', strtotime($r['deleted_at']))) ?></span><?php endif; ?>
        </div>
        <?php if ($r['text']): ?><p class="rev__text"><?= nl2br(e($r['text'])) ?></p><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php panel_footer();
