<?php
declare(strict_types=1);

// --- Admin ------------------------------------------------------------------
function admin_logged_in(): bool
{
    return !empty($_SESSION['admin']);
}

function admin_login(string $user, string $pass): bool
{
    if (!hash_equals(ADMIN_USER, $user)) {
        return false;
    }
    if (!password_verify($pass, ADMIN_PASS_HASH)) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    return true;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        redirect('login.php');
    }
}

function admin_logout(): void
{
    unset($_SESSION['admin']);
}

// --- Kunde ------------------------------------------------------------------
function customer_id(): ?int
{
    return isset($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null;
}

function customer_login(string $user, string $pass): bool
{
    $stmt = db()->prepare('SELECT * FROM bm_customers WHERE username = ? AND is_active = 1');
    $stmt->execute([$user]);
    $c = $stmt->fetch();
    if (!$c || !password_verify($pass, $c['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $c['id'];
    db()->prepare('UPDATE bm_customers SET last_login_at = NOW() WHERE id = ?')
        ->execute([(int) $c['id']]);
    return true;
}

function require_customer(): void
{
    if (!customer_id()) {
        redirect('login.php');
    }
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);
}
