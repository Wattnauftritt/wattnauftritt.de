<?php
declare(strict_types=1);

require_once __DIR__ . '/reviews_provider.php';

function bm_quick_limit(): int
{
    return defined('QUICK_LIMIT') ? (int) QUICK_LIMIT : 20;
}

function bm_poll_timeout(): int
{
    return defined('SCRAPE_POLL_TIMEOUT') ? (int) SCRAPE_POLL_TIMEOUT : 25;
}

function bm_get_request(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM bm_requests WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Normalisierte Reviews in die DB schreiben. Gibt [seen, new] zurück. */
function bm_ingest_reviews(int $requestId, array $reviews): array
{
    $ins = db()->prepare(
        'INSERT INTO bm_reviews
            (request_id, fingerprint, external_id, author, rating, source, text, date_relative, first_seen_at, last_seen_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            last_seen_at  = NOW(),
            is_deleted    = 0,
            deleted_at    = NULL,
            external_id   = VALUES(external_id),
            author        = VALUES(author),
            rating        = VALUES(rating),
            source        = VALUES(source),
            text          = VALUES(text),
            date_relative = VALUES(date_relative)'
    );

    $seen = 0;
    $new  = 0;
    foreach ($reviews as $r) {
        $fp = review_dedup_key($r);
        $ins->execute([
            $requestId,
            $fp,
            $r['external_id'] ?? null,
            $r['author'] ?? null,
            $r['rating'] ?? null,
            $r['source'] ?? null,
            $r['text'] ?? null,
            $r['date'] ?? null,
        ]);
        // MySQL: rowCount() == 1 -> echtes INSERT, == 2 -> Update bei Duplikat.
        if ($ins->rowCount() === 1) {
            $new++;
        }
        $seen++;
    }
    return [$seen, $new];
}

/** Aktive, in diesem Lauf nicht gesehene Bewertungen als gelöscht markieren. */
function bm_sweep_deleted(int $requestId, string $runStart): int
{
    $s = db()->prepare(
        'UPDATE bm_reviews SET is_deleted = 1, deleted_at = NOW()
          WHERE request_id = ? AND is_deleted = 0 AND last_seen_at < ?'
    );
    $s->execute([$requestId, $runStart]);
    return $s->rowCount();
}

function bm_finish_request(int $requestId): void
{
    db()->prepare(
        "UPDATE bm_requests
            SET scraped_at = NOW(), scrape_job_status = 'done',
                scrape_job_id = NULL, scrape_job_url = NULL, scrape_job_mode = NULL,
                status = IF(status = 'neu', 'gescraped', status)
          WHERE id = ?"
    )->execute([$requestId]);
}

/**
 * Bewertungen einer Anfrage abrufen und speichern.
 *
 * @param string $mode 'full' (alle, kein Löschen) | 'quick' (nur neueste, günstig)
 *                     | 'reconcile' (alle + Löscherkennung)
 * @return array{pages:int,seen:int,new:int,deleted:int,pending:bool,err:?string}
 */
function scrape_request(int $requestId, string $mode = 'full', ?int $pollTimeout = null): array
{
    $pdo = db();
    $base = ['pages' => 0, 'seen' => 0, 'new' => 0, 'deleted' => 0, 'pending' => false, 'err' => null];

    $req = bm_get_request($requestId);
    if (!$req) {
        $base['err'] = 'Anfrage nicht gefunden.';
        return $base;
    }

    $limit    = $mode === 'quick' ? bm_quick_limit() : 0;
    $poll     = $pollTimeout ?? bm_poll_timeout();
    $runStart = date('Y-m-d H:i:s');

    try {
        $res = provider_fetch_reviews((string) $req['property_token'], $limit, $poll);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE bm_requests SET scrape_job_status = 'error' WHERE id = ?")->execute([$requestId]);
        $base['err'] = $e->getMessage();
        return $base;
    }

    // Async noch nicht fertig -> Job merken, später abholen.
    if (($res['status'] ?? '') === 'pending') {
        $pdo->prepare(
            "UPDATE bm_requests
                SET provider = ?, scrape_job_id = ?, scrape_job_url = ?, scrape_job_mode = ?, scrape_job_status = 'pending'
              WHERE id = ?"
        )->execute([reviews_provider(), $res['job_id'] ?? null, $res['job_url'] ?? null, $mode, $requestId]);
        $base['pending'] = true;
        return $base;
    }

    [$seen, $new] = bm_ingest_reviews($requestId, $res['reviews'] ?? []);
    $base['seen']  = $seen;
    $base['new']   = $new;
    $base['pages'] = $res['pages'] ?? 0;

    if ($mode === 'reconcile') {
        $base['deleted'] = bm_sweep_deleted($requestId, $runStart);
        $pdo->prepare('UPDATE bm_requests SET reconciled_at = NOW() WHERE id = ?')->execute([$requestId]);
    }

    bm_finish_request($requestId);
    return $base;
}

/** Wartenden Async-Job (Outscraper) abholen und einlesen. */
function resume_request_job(int $requestId): array
{
    $pdo = db();
    $base = ['pages' => 0, 'seen' => 0, 'new' => 0, 'deleted' => 0, 'pending' => false, 'err' => null];

    $req = bm_get_request($requestId);
    if (!$req) {
        $base['err'] = 'Anfrage nicht gefunden.';
        return $base;
    }
    if (($req['scrape_job_status'] ?? '') !== 'pending' || empty($req['scrape_job_url'])) {
        $base['err'] = 'Kein wartender Abruf vorhanden.';
        return $base;
    }

    $runStart = date('Y-m-d H:i:s');
    try {
        $res = provider_resume_job((string) $req['scrape_job_url']);
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE bm_requests SET scrape_job_status = 'error' WHERE id = ?")->execute([$requestId]);
        $base['err'] = $e->getMessage();
        return $base;
    }

    if (($res['status'] ?? '') === 'pending') {
        $base['pending'] = true;
        return $base;
    }

    [$seen, $new] = bm_ingest_reviews($requestId, $res['reviews'] ?? []);
    $base['seen'] = $seen;
    $base['new']  = $new;

    if (($req['scrape_job_mode'] ?? '') === 'reconcile') {
        $base['deleted'] = bm_sweep_deleted($requestId, $runStart);
        $pdo->prepare('UPDATE bm_requests SET reconciled_at = NOW() WHERE id = ?')->execute([$requestId]);
    }

    bm_finish_request($requestId);
    return $base;
}
