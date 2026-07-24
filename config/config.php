<?php
/**
 * Zentrale Konfiguration.
 * Trage hier die Datenbank-Zugangsdaten ein, sobald du sie erhalten hast.
 */

// --- Datenbank ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'mitglieddb');
define('DB_USER', 'mitglied');
define('DB_PASS', 'Pj35urSlgd4VoTaaVbsP');
define('DB_CHARSET', 'utf8mb4');

// --- Basis-URL (für die Erstellung der Verifizierungs-Links) -----------
// Beispiel: 'https://verein.example.org/mitgliederverwaltung'
// Ohne abschließenden Slash!
define('BASE_URL', 'https://mitgliedverwaltung.gamingcommunity.at');

// --- Sicherheit ----------------------------------------------------------
// true, wenn die Seite ausschließlich über HTTPS erreichbar ist (empfohlen)
define('FORCE_HTTPS_COOKIE', true);

// --- Fehleranzeige --------------------------------------------------------
// Im Produktivbetrieb auf false stellen!
define('DEBUG', true);

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

date_default_timezone_set('Europe/Vienna');
