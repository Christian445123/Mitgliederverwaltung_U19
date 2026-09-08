<?php
/**
 * Zentrale Konfiguration dieser Installation.
 *
 * Kritische Zugangsdaten (DB, SMTP) stehen in der .env-Datei im Projekt-
 * Wurzelverzeichnis und werden hier nur eingelesen. Diese Datei UND die
 * .env sind bewusst NICHT im Git-Repository (siehe .gitignore), damit
 * Zugangsdaten nicht versehentlich veröffentlicht werden.
 * Vorlagen für eine Neuinstallation: config.example.php und .env.example.
 */

require_once __DIR__ . '/../includes/env.php';
loadEnv(__DIR__ . '/../.env');

// --- Datenbank ---------------------------------------------------------
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'mitglieddb'));
define('DB_USER', env('DB_USER', 'mitglied'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// --- E-Mail-Versand (SMTP) ------------------------------------------------
// Zugangsdaten stehen in der .env (siehe .env.example). Leerer SMTP_HOST
// bedeutet: Mail-Versand deaktiviert (includes/mailer.php wirft dann beim
// Versandversuch eine RuntimeException).
define('SMTP_HOST', env('SMTP_HOST', ''));
define('SMTP_PORT', (int) env('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', env('SMTP_ENCRYPTION', 'tls')); // 'tls'/'starttls', 'ssl' oder ''
define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
define('SMTP_FROM_EMAIL', env('SMTP_FROM', env('SMTP_USERNAME', 'verein@example.org')));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'Mitgliederverwaltung'));

// --- Basis-URL (für die Erstellung der Verifizierungs-Links) -----------
// Beispiel: 'https://verein.example.org/mitgliederverwaltung'
// Ohne abschließenden Slash!
define('BASE_URL', 'https://mitgliedverwaltung.gamingcommunity.at');

// --- Sicherheit ----------------------------------------------------------
// true, wenn die Seite ausschließlich über HTTPS erreichbar ist (empfohlen)
define('FORCE_HTTPS_COOKIE', true);

// --- Fehleranzeige --------------------------------------------------------
// Im Produktivbetrieb auf false stellen! (verhindert, dass Fehlermeldungen
// mit ggf. sensiblen Details öffentlich angezeigt werden)
define('DEBUG', false);

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('Europe/Vienna');
