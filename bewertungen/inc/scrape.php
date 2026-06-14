<?php
declare(strict_types=1);

require_once __DIR__ . '/serpapi.php';

/**
 * Bewertungen einer Anfrage abrufen und speichern.
 *
 * @param bool $reconcile  false = Erstbefüllung (alle als aktiv).
 *                         true  = Abgleich: nicht mehr gefundene aktive
 *                                 Bewertungen werden als gelöscht markiert
 *                                 (nur bei vollständigem, fehlerfreiem Crawl).
 * @return array{pages:int,seen:int,new:int,deleted:int,err:?string}
 */
function scrape_request(int $requestId, bool $reconcile): array
{
    $pdo = db();

    $req = $pdo->prepare('SELECT * FROM bm_requests WHERE id = ?');
    $req->execute([$requestId]);
    $request = $req->fetch();
    if (!$request) {
        return ['pages' => 0, 'seen' => 0, 'new' => 0, 'deleted' => 0, 'err' => 'Anfrage nicht gefunden.'];
    }

    $token    = (string) $request['property_token'];
    $runStart = date('Y-m-d H:i:s');
    $seen     = 0;
    $new      = 0;
    $err      = null;
    $pages    = 0;

    $insert = $pdo->prepare(
        'INSERT INTO bm_reviews
            (request_id, fingerprint, author, rating, source, text, date_relative, first_seen_at, last_seen_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            last_seen_at  = NOW(),
            is_deleted    = 0,
            deleted_at    = NULL,
            author        = VALUES(author),
            rating        = VALUES(rating),
            source        = VALUES(source),
            text          = VALUES(text),
            date_relative = VALUES(date_relative)'
    );

    try {
        $pages = serpapi_fetch_all_reviews($token, function (array $r) use ($insert, $requestId, &$seen, &$new) {
            $author = (string) ($r['user']['name'] ?? '');
            $rating = isset($r['rating']) ? (int) $r['rating'] : null;
            $source = (string) ($r['source'] ?? '');
            $text   = (string) ($r['snippet'] ?? ($r['description'] ?? ''));
            $date   = (string) ($r['date'] ?? '');
            $fp     = review_fingerprint($author, $source, $rating ?? '', $text);

            $insert->execute([$requestId, $fp, $author, $rating, $source, $text, $date]);
            // MySQL: rowCount() == 1 -> echtes INSERT, == 2 -> Update bei Duplikat.
            if ($insert->rowCount() === 1) {
                $new++;
            }
            $seen++;
        }, defined('SCRAPE_MAX_PAGES') ? SCRAPE_MAX_PAGES : 50);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }

    $deleted = 0;
    if ($reconcile && $err === null) {
        // Sweep: aktive, in diesem Lauf nicht gesehene Bewertungen = gelöscht.
        $sweep = $pdo->prepare(
            'UPDATE bm_reviews
                SET is_deleted = 1, deleted_at = NOW()
              WHERE request_id = ? AND is_deleted = 0 AND last_seen_at < ?'
        );
        $sweep->execute([$requestId, $runStart]);
        $deleted = $sweep->rowCount();
        $pdo->prepare('UPDATE bm_requests SET reconciled_at = NOW() WHERE id = ?')->execute([$requestId]);
    }

    if ($err === null) {
        // Erststatus von "neu" auf "gescraped" heben, sonst Status belassen.
        $pdo->prepare(
            "UPDATE bm_requests
                SET scraped_at = NOW(),
                    status = IF(status = 'neu', 'gescraped', status)
              WHERE id = ?"
        )->execute([$requestId]);
    }

    return ['pages' => $pages, 'seen' => $seen, 'new' => $new, 'deleted' => $deleted, 'err' => $err];
}
