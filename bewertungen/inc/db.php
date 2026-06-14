<?php
declare(strict_types=1);

/** PDO-Verbindung (einmalig pro Request). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/** Rate-Limit-Prüfung. true = noch erlaubt. */
function rate_ok(string $action, int $limit, int $windowSeconds): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM bm_api_hits
         WHERE ip = ? AND action = ? AND created_at > (NOW() - INTERVAL ' . (int) $windowSeconds . ' SECOND)'
    );
    $stmt->execute([client_ip_bin(), $action]);
    return (int) $stmt->fetchColumn() < $limit;
}

/** Einen Treffer auf den Rate-Limit-Zähler buchen. */
function rate_hit(string $action): void
{
    db()->prepare('INSERT INTO bm_api_hits (ip, action) VALUES (?, ?)')
        ->execute([client_ip_bin(), $action]);
}
