<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function startSecureSession(): void
{
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

function loginAdmin(int $id, string $username): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_username'] = $username;
    $_SESSION['created_at'] = time();
}

function logoutAdmin(): void
{
    $_SESSION = [];
    session_unset();
    session_destroy();
}
