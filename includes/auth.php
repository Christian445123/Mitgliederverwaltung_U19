<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/functions.php';

function startSecureSession(): void
{
    sendSecurityHeaders();

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => FORCE_HTTPS_COOKIE,
        'samesite' => 'Lax',
    ]);

    session_start();

    // Absolute Session-Lebensdauer (8 Stunden), unabhängig von Aktivität
    if (empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = time();
    } elseif (time() - $_SESSION['created_at'] > 8 * 3600) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['created_at'] = time();
    }
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function currentAdminUsername(): string
{
    return $_SESSION['admin_username'] ?? '';
}

function currentAdminId(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function currentAdminRole(): string
{
    return $_SESSION['admin_role'] ?? '';
}

function isAdministrator(): bool
{
    return currentAdminRole() === 'administrator';
}

/** Nur für Seiten, die ausschließlich Administratoren zugänglich sein dürfen (z. B. Benutzerverwaltung). */
function requireAdministrator(): void
{
    requireLogin();
    if (!isAdministrator()) {
        http_response_code(403);
        die('Kein Zugriff: Diese Seite ist nur für Administratoren zugänglich.');
    }
}

function loginAdmin(int $id, string $username, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_role'] = $role;
    $_SESSION['created_at'] = time();
}

function logoutAdmin(): void
{
    $_SESSION = [];
    session_unset();
    session_destroy();
}
