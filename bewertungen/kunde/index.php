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
    $stars = $r['rating'] !== null ? str_repeat('★', (int) $r['rating']) . str_repeat('☆', max(0, 5 - (int) $r['rating'])) : '';
    $html  = '<li class="rev' . ($r['is_deleted'] ? ' rev--del' : '') . '">';
    $html .= '<div class="rev__head"><strong>' . e($r['author'] ?: 'Anonym') . '</strong>';
    $html .= '<span class="rev__stars">' . e($stars) . '</span>';
    if ($r['date_relative']) { $html .= '<span class="muted small">' . e($r['date_relative']) . '</span>'; }
    if ($r['is_deleted']) { $html .= '<span class="badge st-reject">entfernt</span>'; }
    $html .= '</div>';
    if ($r['text']) { $html .= '<p class="rev__text">' . nl2br(e($r['text'])) . '</p>'; }
    $html .= '</li>';
    return $html;
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
    $active  = array_values(array_filter($reviews, fn($r) => !$r['is_deleted']));
    $deleted = array_values(array_filter($reviews, fn($r) => $r['is_deleted']));
    ?>
    <section class="box">
      <div class="order-head">
        <div>
          <h2 style="margin:0;"><?= e($req['property_name']) ?></h2>
          <p class="muted small" style="margin:.2rem 0 0;">Auftrag #<?= (int) $req['id'] ?> · seit <?= e(date('d.m.Y', strtotime($req['created_at']))) ?></p>
        </div>
        <span class="badge <?= e($stCls) ?>"><?= e($stLbl) ?></span>
      </div>

      <div class="stats">
        <div class="stat"><span class="stat__num"><?= count($active) ?></span><span class="stat__lbl">aktive Bewertungen</span></div>
        <div class="stat"><span class="stat__num del"><?= count($deleted) ?></span><span class="stat__lbl">entfernte Bewertungen</span></div>
        <div class="stat"><span class="stat__num"><?= count($reviews) ?></span><span class="stat__lbl">gesichert insgesamt</span></div>
      </div>

      <?php if (!$reviews): ?>
        <p class="muted">Ihre Bewertungen werden gerade gesichert. Sobald das abgeschlossen ist, erscheinen sie hier.</p>
      <?php else: ?>
        <?php if ($deleted): ?>
          <h3 class="sub">Bereits entfernte Bewertungen</h3>
          <ul class="revlist"><?php foreach ($deleted as $r) { echo c_review_row($r); } ?></ul>
        <?php endif; ?>
        <?php if ($active): ?>
          <h3 class="sub">Aktuelle Bewertungen</h3>
          <ul class="revlist"><?php foreach ($active as $r) { echo c_review_row($r); } ?></ul>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php
}
panel_footer();
