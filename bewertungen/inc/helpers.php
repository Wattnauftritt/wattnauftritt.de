<?php
declare(strict_types=1);

/** HTML-sicher ausgeben. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** JSON-Antwort senden und beenden. */
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** IP als kompakte Binärform für die Rate-Limit-Tabelle. */
function client_ip_bin(): string
{
    $packed = @inet_pton(client_ip());
    return $packed !== false ? $packed : (string) inet_pton('0.0.0.0');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/** Leeren String zu NULL machen (für optionale DB-Felder). */
function nn(?string $s): ?string
{
    $s = is_string($s) ? trim($s) : '';
    return $s === '' ? null : $s;
}

// --- CSRF -------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $t): bool
{
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

// --- Flash-Messages ---------------------------------------------------------
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}
