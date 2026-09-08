<?php
/**
 * Bootstrap dieser Installation: liest die .env-Datei im Projekt-
 * Wurzelverzeichnis ein (kein Composer/Library nötig) und stellt alle
 * Einstellungen als Konstanten bereit. Diese Datei enthält selbst keine
 * echten Werte - alles steht in .env (nicht in Git, siehe .gitignore).
 * Vorlage für eine Neuinstallation: .env.example.
 */

function loadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Umschließende Anführungszeichen entfernen, falls vorhanden
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"')
            || ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        // Bereits gesetzte Umgebungsvariablen (z. B. vom Hoster) nicht überschreiben
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

/** Wert aus der Umgebung (.env oder echte Server-Env-Variable) lesen, mit Fallback. */
function env(string $key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

loadEnv(__DIR__ . '/../.env');

// --- Datenbank ---------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'mitglieddb'));
define('DB_USER', env('DB_USER', 'mitglied'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// --- E-Mail-Versand (SMTP) ------------------------------------------------
// Leerer SMTP_HOST bedeutet: Mail-Versand deaktiviert (includes/mailer.php
// wirft dann beim Versandversuch eine RuntimeException).
define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls')); // 'tls'/'starttls', 'ssl' oder ''
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM', env('SMTP_USERNAME', 'verein@example.org')));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Mitgliederverwaltung'));

// --- Basis-URL (für die Erstellung der Verifizierungs-Links) -----------
// Ohne abschließenden Slash! Beispiel: 'https://verein.example.org'
define('BASE_URL', rtrim(env('BASE_URL', 'https://example.org'), '/'));

// --- Sicherheit ----------------------------------------------------------
// true, wenn die Seite ausschließlich über HTTPS erreichbar ist (empfohlen)
define('FORCE_HTTPS_COOKIE', filter_var(env('FORCE_HTTPS_COOKIE', 'true'), FILTER_VALIDATE_BOOLEAN));

// --- Fehleranzeige --------------------------------------------------------
// Im Produktivbetrieb auf false stellen! (verhindert, dass Fehlermeldungen
// mit ggf. sensiblen Details öffentlich angezeigt werden)
define('DEBUG', filter_var(env('DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('Europe/Vienna');
