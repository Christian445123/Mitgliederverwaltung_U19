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

/**
 * Datei-basierte Brute-Force-Sperre für den Notfall-Admin-Login (EMERGENCY_ADMIN_*),
 * der ohne Datenbank funktioniert und daher nicht über die admins-Tabelle
 * gesperrt werden kann. Zustand liegt in data/emergency_login.json (per
 * .htaccess gegen Web-Zugriff gesperrt).
 */
function emergencyLoginStatePath(): string
{
    return __DIR__ . '/../data/emergency_login.json';
}

function getEmergencyLoginLockout(): array
{
    $path = emergencyLoginStatePath();
    if (!is_file($path)) {
        return ['attempts' => 0, 'locked_until' => null];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['attempts' => 0, 'locked_until' => null];
    }
    return [
        'attempts' => (int) ($data['attempts'] ?? 0),
        'locked_until' => $data['locked_until'] ?? null,
    ];
}

function recordEmergencyLoginFailure(int $maxAttempts, int $lockoutMinutes): void
{
    $state = getEmergencyLoginLockout();
    $state['attempts']++;
    if ($state['attempts'] >= $maxAttempts) {
        $state['locked_until'] = date('Y-m-d H:i:s', time() + $lockoutMinutes * 60);
    }
    file_put_contents(emergencyLoginStatePath(), json_encode($state), LOCK_EX);
}

function clearEmergencyLoginLockout(): void
{
    $path = emergencyLoginStatePath();
    if (is_file($path)) {
        unlink($path);
    }
}

/**
 * Prüft ein Passwort gegen EMERGENCY_ADMIN_PASSWORD_HASH. Unterstützt zwei Formate:
 * - bcrypt-Hashes von password_hash() (Standard, erzeugt von bin/hash_password.php)
 * - "sha256:<salt_hex>:<hash_hex>" als Fallback für Umgebungen ohne PHP-CLI beim
 *   Erstellen des Hashs (z. B. Hash manuell mit einem anderen Tool erzeugt).
 */
function verifyEmergencyPassword(string $password, string $storedHash): bool
{
    if (str_starts_with($storedHash, 'sha256:')) {
        $parts = explode(':', $storedHash, 3);
        if (count($parts) !== 3) {
            return false;
        }
        [, $salt, $hash] = $parts;
        return hash_equals($hash, hash('sha256', $salt . $password));
    }

    return password_verify($password, $storedHash);
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

/**
 * Sozialversicherungsnummer für die Anzeige maskieren (nur letzte 4 Ziffern sichtbar).
 * Die volle Nummer wird nirgends außer im eigenen Selbstauskunfts-Formular angezeigt.
 */
function maskSvnr(?string $svnr): string
{
    $svnr = trim((string) $svnr);
    if ($svnr === '') {
        return '';
    }
    $visible = substr($svnr, -4);
    return str_repeat('•', max(0, strlen($svnr) - 4)) . $visible;
}

/**
 * Hochgeladenes Passfoto validieren und speichern. Gibt den neuen Dateinamen zurück
 * oder null bei fehlendem Upload. Wirft eine RuntimeException bei ungültiger Datei.
 */
function handlePassFotoUpload(array $file, int $memberId): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fehler beim Hochladen des Fotos.');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Das Foto darf höchstens 5 MB groß sein.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new RuntimeException('Die Datei ist kein gültiges Bild.');
    }

    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
    ];
    $extension = $allowedTypes[$imageInfo[2]] ?? null;
    if ($extension === null) {
        throw new RuntimeException('Nur JPG- oder PNG-Bilder sind erlaubt.');
    }

    $uploadDir = __DIR__ . '/../uploads/members';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = $memberId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Foto konnte nicht gespeichert werden.');
    }

    return $filename;
}

/** Löscht ein zuvor hochgeladenes Passfoto vom Dateisystem (falls vorhanden). */
function deletePassFoto(?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = __DIR__ . '/../uploads/members/' . basename($filename);
    if (is_file($path)) {
        unlink($path);
    }
}
