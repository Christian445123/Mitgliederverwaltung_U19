<?php
/**
 * Aktualisiert die Anwendung auf dem Server: zieht den neuesten Code per
 * `git pull` und stößt danach die Datenbank-Migration an (siehe
 * includes/updater.php für die eigentliche Logik - dieselbe Funktion nutzt
 * auch der Update-Button im Admin-Bereich, admin/update.php).
 *
 * Aufruf (Kommandozeile, im Projektverzeichnis auf dem Server):
 *   php bin/update.php
 *
 * Voraussetzung: Das Projektverzeichnis ist ein Git-Checkout mit
 * konfiguriertem Remote (z. B. durch `git clone` auf dem Server angelegt).
 * Bricht sicherheitshalber ab, wenn es auf dem Server nicht committete
 * lokale Änderungen gibt, statt sie zu überschreiben.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Dieses Script darf nur über die Kommandozeile ausgeführt werden.\n");
}

require_once __DIR__ . '/../includes/updater.php';
require_once __DIR__ . '/../includes/db.php';

$result = performUpdate(dirname(__DIR__));

echo $result['log'];
exit($result['success'] ? 0 : 1);
