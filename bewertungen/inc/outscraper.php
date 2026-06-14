<?php
declare(strict_types=1);

/**
 * Outscraper-Client (Google Maps Suche + Reviews, asynchron).
 *
 * HINWEIS: Endpunkt-Pfade und Feldnamen nach der aktuellen Outscraper-API-Doku
 * prüfen, falls sich etwas geändert hat. Alle Zugriffe sind defensiv (?? null),
 * die Normalisierung passiert zentral in normalize_outscraper_review().
 */

function outscraper_base(): string
{
    return defined('OUTSCRAPER_BASE') && OUTSCRAPER_BASE ? rtrim(OUTSCRAPER_BASE, '/') : 'https://api.outscraper.cloud';
}

function outscraper_key(): string
{
    $k = defined('OUTSCRAPER_KEY') ? (string) OUTSCRAPER_KEY : '';
    if ($k === '') {
        throw new RuntimeException('Outscraper-Key fehlt (OUTSCRAPER_KEY in config.php).');
    }
    return $k;
}

/** GET auf eine vollständige Outscraper-URL, JSON zurück. */
function outscraper_get_url(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_HTTPHEADER     => ['X-API-KEY: ' . outscraper_key(), 'Accept: application/json'],
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        throw new RuntimeException('Verbindungsfehler (Outscraper): ' . $err);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Outscraper: Antwort nicht lesbar.');
    }
    if ($code >= 400) {
        throw new RuntimeException('Outscraper: HTTP ' . $code . ' ' . ($data['errorMessage'] ?? $data['error'] ?? ''));
    }
    return $data;
}

function outscraper_request(string $path, array $params): array
{
    return outscraper_get_url(outscraper_base() . '/' . ltrim($path, '/') . '?' . http_build_query($params));
}

/** Objektsuche -> Liste passender Google-Listings. */
function outscraper_lookup(string $q): array
{
    $data = outscraper_request('maps/search', [
        'query'    => $q,
        'limit'    => 6,
        'language' => 'de',
        'region'   => 'DE',
        'async'    => 'false',
    ]);

    $rows = $data['data'][0] ?? ($data['data'] ?? []);
    $out  = [];
    foreach ($rows as $p) {
        if (!is_array($p)) {
            continue;
        }
        $token = (string) ($p['place_id'] ?? ($p['google_id'] ?? ($p['data_id'] ?? '')));
        if ($token === '') {
            continue;
        }
        $out[] = [
            'name'    => $p['name'] ?? '(ohne Namen)',
            'token'   => $token,
            'type'    => $p['type'] ?? ($p['category'] ?? ''),
            'rating'  => $p['rating'] ?? null,
            'reviews' => $p['reviews'] ?? ($p['reviews_count'] ?? null),
            'lat'     => $p['latitude'] ?? null,
            'lng'     => $p['longitude'] ?? null,
        ];
    }
    return $out;
}

/** Eine Outscraper-Review in die einheitliche Struktur überführen. */
function normalize_outscraper_review(array $r): array
{
    $date = $r['review_datetime_utc'] ?? ($r['review_timestamp'] ?? ($r['date'] ?? ''));
    $id   = (string) ($r['review_id'] ?? ($r['review_link'] ?? ''));
    return [
        'external_id' => $id !== '' ? $id : null,
        'author'      => (string) ($r['author_title'] ?? ($r['author_name'] ?? '')),
        'rating'      => isset($r['review_rating']) ? (int) $r['review_rating']
            : (isset($r['rating']) ? (int) $r['rating'] : null),
        'source'      => 'Google',
        'text'        => (string) ($r['review_text'] ?? ($r['text'] ?? '')),
        'date'        => (string) $date,
    ];
}

/** Reviews aus einer (fertigen) Outscraper-Antwort extrahieren. */
function outscraper_extract_reviews(array $data): array
{
    $entry = $data['data'][0] ?? [];
    $list  = $entry['reviews_data'] ?? ($entry['reviews'] ?? []);
    $out   = [];
    foreach ($list as $r) {
        if (is_array($r)) {
            $out[] = normalize_outscraper_review($r);
        }
    }
    return $out;
}

/**
 * Reviews-Job starten und bis $pollTimeout Sekunden inline auf das Ergebnis warten.
 * @return array done: ['status'=>'done','reviews'=>[...],'pages'=>1]
 *               pending: ['status'=>'pending','job_id'=>..,'job_url'=>..]
 */
function outscraper_fetch_reviews(string $token, int $limit, int $pollTimeout): array
{
    $submit = outscraper_request('maps/reviews', [
        'query'        => $token,
        'reviewsLimit' => $limit > 0 ? $limit : 0,   // 0 = alle
        'sort'         => 'newest',
        'language'     => 'de',
        'region'       => 'DE',
        'async'        => 'true',
    ]);

    // Manche Antworten liefern die Daten direkt mit:
    if (isset($submit['data'][0]) && (isset($submit['data'][0]['reviews_data']) || isset($submit['data'][0]['reviews']))) {
        return ['status' => 'done', 'reviews' => outscraper_extract_reviews($submit), 'pages' => 1];
    }

    $jobId = (string) ($submit['id'] ?? '');
    $url   = (string) ($submit['results_location'] ?? '');
    if ($url === '') {
        throw new RuntimeException('Outscraper: kein results_location erhalten.');
    }

    $deadline = time() + max(1, $pollTimeout);
    while (time() < $deadline) {
        sleep(3);
        $res    = outscraper_get_url($url);
        $status = strtolower((string) ($res['status'] ?? ''));
        if ($status === 'success') {
            return ['status' => 'done', 'reviews' => outscraper_extract_reviews($res), 'pages' => 1];
        }
        if ($status === 'error' || $status === 'failed') {
            throw new RuntimeException('Outscraper-Job fehlgeschlagen.');
        }
    }
    return ['status' => 'pending', 'job_id' => $jobId, 'job_url' => $url];
}

/** Wartenden Job erneut abfragen. */
function outscraper_resume_job(string $jobUrl): array
{
    $res    = outscraper_get_url($jobUrl);
    $status = strtolower((string) ($res['status'] ?? ''));
    if ($status === 'success') {
        return ['status' => 'done', 'reviews' => outscraper_extract_reviews($res), 'pages' => 1];
    }
    if ($status === 'error' || $status === 'failed') {
        throw new RuntimeException('Outscraper-Job fehlgeschlagen.');
    }
    return ['status' => 'pending', 'job_url' => $jobUrl];
}
