<?php
declare(strict_types=1);

/**
 * SerpApi-Client: Objekt-Lookup (google_hotels) und Reviews-Abruf
 * (google_hotels_reviews). Der API-Key bleibt serverseitig.
 */

function serpapi_get(array $params): array
{
    $params['api_key'] = SERPAPI_KEY;
    $url = 'https://serpapi.com/search?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $body  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0 || $body === false) {
        throw new RuntimeException('Verbindungsfehler: ' . $err);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Antwort nicht lesbar.');
    }
    if (!empty($data['error'])) {
        throw new RuntimeException('SerpApi: ' . $data['error']);
    }
    return $data;
}

/** Objektsuche → Liste passender Listings mit property_token. */
function serpapi_property_lookup(string $q): array
{
    $data = serpapi_get([
        'engine'         => 'google_hotels',
        'q'              => $q,
        'check_in_date'  => date('Y-m-d', strtotime('+30 days')),
        'check_out_date' => date('Y-m-d', strtotime('+31 days')),
        'adults'         => 2,
        'hl'             => 'de',
        'gl'             => 'de',
        'currency'       => 'EUR',
    ]);

    $out = [];
    foreach (($data['properties'] ?? []) as $p) {
        if (empty($p['property_token'])) {
            continue; // Anzeigen ohne Token überspringen
        }
        $out[] = [
            'name'    => $p['name'] ?? '(ohne Namen)',
            'token'   => $p['property_token'],
            'type'    => $p['type'] ?? '',
            'rating'  => $p['overall_rating'] ?? null,
            'reviews' => $p['reviews'] ?? null,
            'lat'     => $p['gps_coordinates']['latitude'] ?? null,
            'lng'     => $p['gps_coordinates']['longitude'] ?? null,
        ];
    }
    return $out;
}

/**
 * Dedup-Fingerprint (stabile Felder, da Google keine stabile Review-ID liefert).
 * fingerprint = sha256( lower(trim(author)) | source | rating | trim(text) )
 */
function review_fingerprint(string $author, string $source, $rating, string $text): string
{
    $norm = mb_strtolower(trim($author)) . '|' . $source . '|' . $rating . '|' . trim($text);
    return hash('sha256', $norm);
}

/**
 * Alle Reviews eines property_token paginiert abrufen.
 * Ruft $onReview(array $review) für jede Bewertung auf.
 * Gibt die Anzahl abgerufener Seiten zurück.
 */
function serpapi_fetch_all_reviews(string $token, callable $onReview, int $maxPages = 50): int
{
    $page = 0;
    $next = null;
    do {
        $params = [
            'engine'         => 'google_hotels_reviews',
            'property_token' => $token,
            'sort_by'        => 2, // neueste zuerst
            'hl'             => 'de',
        ];
        if ($next) {
            $params['next_page_token'] = $next;
        }
        $data = serpapi_get($params);

        foreach (($data['reviews'] ?? []) as $r) {
            $onReview($r);
        }
        $page++;
        $next = $data['serpapi_pagination']['next_page_token']
            ?? ($data['next_page_token'] ?? null);
    } while ($next && $page < $maxPages);

    return $page;
}
