<?php
/**
 * Vorlage für die zentrale Konfiguration.
 *
 * Kopiere diese Datei zu `config.php` und trage dort die echten Zugangsdaten
 * ein. `config.php` ist bewusst NICHT im Git-Repository (siehe .gitignore),
 * damit Zugangsdaten nicht versehentlich veröffentlicht werden.
 */

// --- Datenbank ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'mitglieddb');
define('DB_USER', 'mitglied');
define('DB_PASS', 'CHANGE_ME');
define('DB_CHARSET', 'utf8mb4');

// --- Basis-URL (für die Erstellung der Verifizierungs-Links) -----------
// Beispiel: 'https://verein.example.org/mitgliederverwaltung'
// Ohne abschließenden Slash!
define('BASE_URL', 'https://example.org');

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
