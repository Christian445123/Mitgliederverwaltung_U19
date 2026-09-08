<?php

/** Setzt grundlegende Sicherheits-Header (Schutz u. a. vor Clickjacking/MIME-Sniffing). */
function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'");
}

/** HTML-sicher ausgeben */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path)
{
    header('Location: ' . $path);
    exit;
}

/** CSRF-Token für das aktuelle Formular holen/erzeugen */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verstecktes Formularfeld mit CSRF-Token */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** CSRF-Token einer eingehenden Anfrage prüfen */
function verifyCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Zufälligen, URL-sicheren Verifizierungs-Token erzeugen */
function generateVerifyToken(): string
{
    return bin2hex(random_bytes(32)); // 64 Zeichen
}

/**
 * Zufälligen Zugangscode (Passwort) erzeugen, der zusätzlich zur E-Mail-Adresse
 * benötigt wird, um über den Verifizierungs-Link Mitgliedsdaten einzusehen.
 * Zeichensatz ohne leicht verwechselbare Zeichen (0/O, 1/l/I).
 */
function generateAccessPassword(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

/** Nächste freie Mitgliedsnummer ermitteln, Format: M-2026-0001 */
function generateMitgliedsnummer(PDO $pdo): string
{
    $year = date('Y');
    $prefix = 'M-' . $year . '-';

    $stmt = $pdo->prepare(
        "SELECT mitgliedsnummer FROM members
         WHERE mitgliedsnummer LIKE :prefix
         ORDER BY mitgliedsnummer DESC LIMIT 1"
    );
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = 1;
    if ($last) {
        $lastNumber = (int) substr($last, strlen($prefix));
        $next = $lastNumber + 1;
    }

    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

/** Vollständigen Verifizierungs-Link für ein Mitglied bauen */
function buildVerifyLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/verify.php?token=' . $token;
}

/** Datum (Y-m-d) für <input type="date"> vorbereiten, robust gegen NULL */
function formatDateForInput(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return '';
    }
    return $date;
}
