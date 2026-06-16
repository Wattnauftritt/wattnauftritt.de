<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_customer();

$cid = customer_id();
$cs  = db()->prepare('SELECT * FROM bm_customers WHERE id = ?');
$cs->execute([$cid]);
$customer = $cs->fetch();

$rs = db()->prepare('SELECT * FROM bm_requests WHERE customer_id = ? ORDER BY created_at DESC');
$rs->execute([$cid]);
$requests = $rs->fetchAll();

function c_review_row(array $r): string
{
    $rating  = $r['rating'] !== null ? (int) $r['rating'] : 0;
    $stars   = $rating ? str_repeat('★', $rating) . str_repeat('☆', max(0, 5 - $rating)) : '';
    $author  = trim((string) ($r['author'] ?? '')) ?: 'Anonym';
    $initial = mb_strtoupper(mb_substr($author, 0, 1));
    $raw     = trim((string) ($r['date_relative'] ?? ''));
    $ts      = $raw !== '' ? strtotime($raw) : false;
    $dateLbl = $ts !== false ? date('d.m.Y', $ts) : $raw;

    $h  = '<li class="rev' . ($r['is_deleted'] ? ' rev--del' : '') . '"'
        . ' data-deleted="' . ((int) $r['is_deleted']) . '"'
        . ' data-rating="' . $rating . '"'
        . ' data-ts="' . ($ts !== false ? $ts : 0) . '"'
        . ' data-id="' . (int) $r['id'] . '">';
    $h .= '<div class="rev__inner"><div class="rev__avatar">' . e($initial) . '</div><div class="rev__body">';
    $h .= '<div class="rev__head"><strong>' . e($author) . '</strong>';
    $h .= '<span class="rev__stars" title="' . $rating . ' von 5">' . e($stars) . '</span>';
    if ($dateLbl !== '') { $h .= '<span class="muted small">' . e($dateLbl) . '</span>'; }
    if ($r['is_deleted']) { $h .= '<span class="badge st-reject">entfernt</span>'; }
    $h .= '</div>';
    if ($r['text']) { $h .= '<p class="rev__text">' . nl2br(e($r['text'])) . '</p>'; }
    $h .= '</div></div></li>';
    return $h;
}

panel_header('Mein Auftrag', 'kunde');
echo '<p class="muted">Willkommen, ' . e($customer['display_name'] ?: $customer['username']) . '.</p>';

if (!$requests) {
    echo '<p class="muted">Aktuell ist kein Auftrag hinterlegt.</p>';
    panel_footer();
    exit;
}

foreach ($requests as $req) {
    [$stLbl, $stCls] = status_label($req['status']);

    $vs = db()->prepare('SELECT * FROM bm_reviews WHERE request_id = ? ORDER BY id DESC');
    $vs->execute([(int) $req['id']]);
    $reviews = $vs->fetchAll();
    $activeN  = count(array_filter($reviews, fn($r) => !$r['is_deleted']));
    $deletedN = count(array_filter($reviews, fn($r) => $r['is_deleted']));
    ?>
    <section class="box bm-order" data-order="<?= (int) $req['id'] ?>">
      <div class="order-head">
        <div>
          <h2 style="margin:0;"><?= e($req['property_name']) ?></h2>
          <p class="muted small" style="margin:.2rem 0 0;">Auftrag #<?= (int) $req['id'] ?> · seit <?= e(date('d.m.Y', strtotime($req['created_at']))) ?></p>
        </div>
        <span class="badge <?= e($stCls) ?>"><?= e($stLbl) ?></span>
      </div>

      <div class="stats">
        <div class="stat"><span class="stat__num"><?= $activeN ?></span><span class="stat__lbl">aktuelle Bewertungen</span></div>
        <div class="stat"><span class="stat__num del"><?= $deletedN ?></span><span class="stat__lbl">entfernte Bewertungen</span></div>
        <div class="stat"><span class="stat__num"><?= count($reviews) ?></span><span class="stat__lbl">gesichert insgesamt</span></div>
      </div>

      <?php if (!$reviews): ?>
        <p class="muted" style="margin-top:1rem;">Ihre Bewertungen werden gerade gesichert. Sobald das abgeschlossen ist, erscheinen sie hier.</p>
      <?php else: ?>
        <div class="filterbar bm-tabs" style="margin-top:1.2rem;">
          <button type="button" data-tab="aktuell" class="is-active">Aktuelle (<?= $activeN ?>)</button>
          <button type="button" data-tab="entfernt">Entfernte (<?= $deletedN ?>)</button>
          <button type="button" data-tab="alle">Alle (<?= count($reviews) ?>)</button>
        </div>
        <div class="filterbar">
          <span class="muted small" style="align-self:center;margin-right:.3rem;">Sortieren:</span>
          <button type="button" data-sort="neu" class="is-active">Neueste</button>
          <button type="button" data-sort="best">Beste</button>
          <button type="button" data-sort="schlecht">Schlechteste</button>
        </div>

        <ul class="revlist" data-list style="margin-top:1rem;">
          <?php foreach ($reviews as $r) { echo c_review_row($r); } ?>
        </ul>
        <p class="muted" data-empty style="margin-top:1rem;display:none;">In dieser Ansicht sind keine Bewertungen.</p>
      <?php endif; ?>
    </section>
    <?php
}
?>
<script>
(function () {
  document.querySelectorAll('.bm-order').forEach(function (block) {
    var list = block.querySelector('[data-list]');
    if (!list) return;
    var items = [].slice.call(list.children);
    var empty = block.querySelector('[data-empty]');
    var state = { tab: 'aktuell', sort: 'neu' };

    function mark(sel, attr, val) {
      block.querySelectorAll(sel).forEach(function (b) {
        b.classList.toggle('is-active', b.getAttribute(attr) === String(val));
      });
    }
    function apply() {
      var vis = items.filter(function (li) {
        if (state.tab === 'aktuell') return li.dataset.deleted !== '1';
        if (state.tab === 'entfernt') return li.dataset.deleted === '1';
        return true;
      });
      vis.sort(function (a, b) {
        if (state.sort === 'best') return (b.dataset.rating - a.dataset.rating) || (b.dataset.ts - a.dataset.ts);
        if (state.sort === 'schlecht') return (a.dataset.rating - b.dataset.rating) || (b.dataset.ts - a.dataset.ts);
        if (a.dataset.ts !== b.dataset.ts) return b.dataset.ts - a.dataset.ts;
        return b.dataset.id - a.dataset.id;
      });
      items.forEach(function (li) { li.style.display = 'none'; });
      vis.forEach(function (li) { li.style.display = ''; list.appendChild(li); });
      if (empty) empty.style.display = vis.length ? 'none' : '';
    }
    block.querySelectorAll('[data-tab]').forEach(function (b) {
      b.addEventListener('click', function () { state.tab = b.dataset.tab; mark('[data-tab]', 'data-tab', state.tab); apply(); });
    });
    block.querySelectorAll('[data-sort]').forEach(function (b) {
      b.addEventListener('click', function () { state.sort = b.dataset.sort; mark('[data-sort]', 'data-sort', state.sort); apply(); });
    });
    apply();
  });
})();
</script>
<?php
panel_footer();
