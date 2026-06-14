<?php
declare(strict_types=1);

/** Gemeinsames Panel-Layout für Admin- und Kundenbereich. */

function status_label(string $status): array
{
    return [
        'neu'            => ['Neu', 'st-new'],
        'gescraped'      => ['Gescraped', 'st-scraped'],
        'in_bearbeitung' => ['In Bearbeitung', 'st-work'],
        'abgeschlossen'  => ['Abgeschlossen', 'st-done'],
        'abgelehnt'      => ['Abgelehnt', 'st-reject'],
    ][$status] ?? [$status, 'st-new'];
}

/**
 * @param string $role 'admin' | 'kunde'
 */
function panel_header(string $title, string $role, string $base = ''): void
{
    $isAdmin = $role === 'admin';
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="de"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . ' – Bewertungsmanagement</title>';
    echo '<link rel="stylesheet" href="/bewertungsmanagement/assets/panel.css">';
    echo '</head><body class="panel ' . ($isAdmin ? 'panel--admin' : 'panel--kunde') . '">';

    echo '<header class="topbar"><div class="topbar__in">';
    echo '<a class="topbar__brand" href="' . e($base) . 'index.php">';
    echo '<img src="/assets/logo.webp" alt="Watt\'n Auftritt" height="34">';
    echo '<span>' . ($isAdmin ? 'Adminpanel' : 'Mein Auftrag') . '</span></a>';
    echo '<nav class="topbar__nav">';
    if ($isAdmin) {
        echo '<a href="' . e($base) . 'index.php">Anfragen</a>';
    }
    echo '<a class="topbar__logout" href="' . e($base) . 'logout.php">Abmelden</a>';
    echo '</nav></div></header>';

    echo '<main class="wrap">';
    foreach (flash_take() as $f) {
        echo '<div class="flash flash--' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    echo '<h1 class="page-title">' . e($title) . '</h1>';
}

function panel_footer(): void
{
    echo '</main></body></html>';
}

/** Schlankes Layout für die Login-Seiten. */
function login_layout(string $title, string $formHtml): void
{
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="de"><head>';
    echo '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . '</title>';
    echo '<link rel="stylesheet" href="/bewertungsmanagement/assets/panel.css">';
    echo '</head><body class="login-page">';
    echo '<div class="login-card">';
    echo '<img class="login-logo" src="/assets/logo.webp" alt="Watt\'n Auftritt" height="44">';
    echo '<h1>' . e($title) . '</h1>';
    foreach (flash_take() as $f) {
        echo '<div class="flash flash--' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    echo $formHtml;
    echo '</div></body></html>';
}
