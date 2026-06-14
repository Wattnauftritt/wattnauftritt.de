<?php
declare(strict_types=1);

/**
 * Anbieter-Adapter: kapselt SerpApi und Outscraper hinter einer einheitlichen
 * Schnittstelle. Umschaltbar über REVIEWS_PROVIDER in config.php.
 */

require_once __DIR__ . '/serpapi.php';
require_once __DIR__ . '/outscraper.php';

function reviews_provider(): string
{
    return defined('REVIEWS_PROVIDER') && REVIEWS_PROVIDER ? REVIEWS_PROVIDER : 'serpapi';
}

/** Objekt-/Listing-Suche. Liefert [['name','token','type','rating','reviews','lat','lng'], ...] */
function provider_lookup(string $q): array
{
    return reviews_provider() === 'outscraper'
        ? outscraper_lookup($q)
        : serpapi_property_lookup($q);
}

/** SerpApi-Review -> einheitliche Struktur. */
function normalize_serpapi_review(array $r): array
{
    return [
        'external_id' => null, // SerpApi liefert keine stabile ID
        'author'      => (string) ($r['user']['name'] ?? ''),
        'rating'      => isset($r['rating']) ? (int) $r['rating'] : null,
        'source'      => (string) ($r['source'] ?? 'Google'),
        'text'        => (string) ($r['snippet'] ?? ($r['description'] ?? '')),
        'date'        => (string) ($r['date'] ?? ''),
    ];
}

/**
 * Reviews holen. $limit 0 = alle, sonst Höchstzahl (Quick-Lauf).
 * Rückgabe:
 *   ['status'=>'done','reviews'=>[...],'pages'=>int]
 *   ['status'=>'pending','job_id'=>string,'job_url'=>string]   (nur Outscraper async)
 */
function provider_fetch_reviews(string $token, int $limit, int $pollTimeout): array
{
    if (reviews_provider() === 'outscraper') {
        return outscraper_fetch_reviews($token, $limit, $pollTimeout);
    }

    // SerpApi: synchron paginieren und einsammeln.
    $maxPages = defined('SCRAPE_MAX_PAGES') ? SCRAPE_MAX_PAGES : 50;
    if ($limit > 0) {
        $maxPages = max(1, (int) ceil($limit / 10)); // ~10 Reviews pro Seite
    }
    $reviews = [];
    $pages = serpapi_fetch_all_reviews($token, function (array $r) use (&$reviews) {
        $reviews[] = normalize_serpapi_review($r);
    }, $maxPages);

    if ($limit > 0 && count($reviews) > $limit) {
        $reviews = array_slice($reviews, 0, $limit);
    }
    return ['status' => 'done', 'reviews' => $reviews, 'pages' => $pages];
}

/** Wartenden Async-Job (Outscraper) erneut abfragen. */
function provider_resume_job(string $jobUrl): array
{
    return outscraper_resume_job($jobUrl);
}

/** Dedup-Schlüssel: stabile external_id bevorzugt, sonst Fingerprint stabiler Felder. */
function review_dedup_key(array $r): string
{
    if (!empty($r['external_id'])) {
        return hash('sha256', 'id:' . $r['external_id']);
    }
    $norm = mb_strtolower(trim((string) ($r['author'] ?? ''))) . '|'
        . (string) ($r['source'] ?? '') . '|'
        . (string) ($r['rating'] ?? '') . '|'
        . trim((string) ($r['text'] ?? ''));
    return hash('sha256', $norm);
}
