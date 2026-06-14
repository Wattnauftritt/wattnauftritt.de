<?php
declare(strict_types=1);
require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/layout.php';
require_admin();

$filter = $_GET['status'] ?? '';
$valid  = ['neu', 'gescraped', 'in_bearbeitung', 'abgeschlossen', 'abgelehnt'];

$sql = 'SELECT r.*,
               (SELECT COUNT(*) FROM bm_reviews v WHERE v.request_id = r.id AND v.is_deleted = 0) AS active_reviews,
               (SELECT COUNT(*) FROM bm_reviews v WHERE v.request_id = r.id AND v.is_deleted = 1) AS deleted_reviews,
               c.username AS customer_user
        FROM bm_requests r
        LEFT JOIN bm_customers c ON c.id = r.customer_id';
$params = [];
if (in_array($filter, $valid, true)) {
    $sql .= ' WHERE r.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = db()->query("SELECT status, COUNT(*) c FROM bm_requests GROUP BY status")->fetchAll();
$countMap = [];
foreach ($counts as $c) { $countMap[$c['status']] = (int) $c['c']; }

panel_header('Anfragen', 'admin');
?>
<div class="filterbar">
  <a href="index.php" class="<?= $filter === '' ? 'is-active' : '' ?>">Alle</a>
  <?php foreach ($valid as $s): [$lbl, $cls] = status_label($s); ?>
    <a href="?status=<?= e($s) ?>" class="<?= $filter === $s ? 'is-active' : '' ?>">
      <?= e($lbl) ?> <span class="cnt"><?= (int) ($countMap[$s] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p class="muted">Keine Anfragen<?= $filter ? ' mit diesem Status' : '' ?>.</p>
<?php else: ?>
<div class="table-wrap">
<table class="data">
  <thead><tr>
    <th>#</th><th>Eingang</th><th>Objekt</th><th>Kontakt</th><th>Status</th><th>Bewertungen</th><th>Kunde</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): [$lbl, $cls] = status_label($r['status']); ?>
    <tr>
      <td><?= (int) $r['id'] ?></td>
      <td><?= e(date('d.m.Y H:i', strtotime($r['created_at']))) ?></td>
      <td><strong><?= e($r['property_name']) ?></strong><br><span class="muted small"><?= e($r['property_type'] ?: '') ?></span></td>
      <td><?= e($r['contact_name']) ?><br><span class="muted small"><?= e($r['contact_email']) ?></span></td>
      <td><span class="badge <?= e($cls) ?>"><?= e($lbl) ?></span></td>
      <td><?= (int) $r['active_reviews'] ?> aktiv<?php if ((int) $r['deleted_reviews']): ?> · <span class="del"><?= (int) $r['deleted_reviews'] ?> gelöscht</span><?php endif; ?></td>
      <td><?= $r['customer_user'] ? e($r['customer_user']) : '<span class="muted small">–</span>' ?></td>
      <td><a class="btn-sm" href="request.php?id=<?= (int) $r['id'] ?>">Öffnen</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php panel_footer();
