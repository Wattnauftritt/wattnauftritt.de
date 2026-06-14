<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_admin();

// Angenommene Anfragen = Aufträge im Kundensystem.
$rows = db()->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM bm_reviews v WHERE v.request_id = r.id AND v.is_deleted = 0) AS aktiv,
            (SELECT COUNT(*) FROM bm_reviews v WHERE v.request_id = r.id AND v.is_deleted = 1) AS geloescht,
            (SELECT COUNT(*) FROM bm_reviews v WHERE v.request_id = r.id
                AND r.first_scraped_at IS NOT NULL AND v.first_seen_at > r.first_scraped_at) AS neu,
            c.username AS customer_user
       FROM bm_requests r
       LEFT JOIN bm_customers c ON c.id = r.customer_id
      WHERE r.accepted_at IS NOT NULL
      ORDER BY r.is_active DESC, r.accepted_at DESC"
)->fetchAll();

panel_header('Kunden & Aufträge', 'admin');
?>
<?php if (!$rows): ?>
  <p class="muted">Noch keine angenommenen Aufträge. Eine Anfrage wird über „Anfrage annehmen" zum Auftrag.</p>
<?php else: ?>
<div class="table-wrap">
<table class="data">
  <thead><tr>
    <th>#</th><th>Kunde / Objekt</th><th>Angenommen</th><th>Status</th><th>Aktiv</th>
    <th>Bewertungen</th><th>Login</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): [$lbl, $cls] = status_label($r['status']); ?>
    <tr>
      <td><?= (int) $r['id'] ?></td>
      <td>
        <strong><?= e($r['contact_name']) ?></strong><br>
        <span class="muted small"><?= e($r['property_name']) ?></span>
      </td>
      <td><?= e(date('d.m.Y', strtotime($r['accepted_at']))) ?></td>
      <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
      <td><?php if (!empty($r['is_active'])): ?><span class="badge st-done">aktiv</span><?php else: ?><span class="badge st-reject">inaktiv</span><?php endif; ?></td>
      <td>
        <?= (int) $r['aktiv'] ?> aktiv ·
        <span class="del"><?= (int) $r['geloescht'] ?> entfernt</span>
        <?php if ((int) $r['neu']): ?> · <?= (int) $r['neu'] ?> neu<?php endif; ?>
      </td>
      <td><?= $r['customer_user'] ? e($r['customer_user']) : '<span class="muted small">–</span>' ?></td>
      <td>
        <a class="btn-sm" href="request.php?id=<?= (int) $r['id'] ?>">Öffnen</a>
        <a class="btn-sm" href="abrechnung.php?id=<?= (int) $r['id'] ?>" style="background:var(--brand-deep);">Abrechnung</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php panel_footer();
