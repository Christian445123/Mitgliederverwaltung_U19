<?php
/**
 * Aktualisiert die Anwendung auf dem Server: zieht den neuesten Code per
 * `git pull` und stößt danach die Datenbank-Migration an (siehe
 * includes/migrate.php), damit neue Spalten/Tabellen sofort verfügbar sind,
 * ohne auf den nächsten Seitenaufruf warten zu müssen.
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

$projectRoot = dirname(__DIR__);

function runCommand(string $command, string $cwd): array
{
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        return [1, '', "Konnte Befehl nicht starten: $command"];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    return [$exitCode, $stdout, $stderr];
}

echo "== Prüfe auf lokale Änderungen auf dem Server ==\n";
[$code, $out] = runCommand('git status --porcelain', $projectRoot);
if ($code !== 0) {
    fwrite(STDERR, "Fehler beim Prüfen des Git-Status (ist das Projektverzeichnis ein Git-Checkout?).\n");
    exit(1);
}
if (trim($out) !== '') {
    fwrite(STDERR, "Abgebrochen: Es gibt nicht committete Änderungen auf dem Server:\n$out\n"
        . "Bitte zuerst sichern/committen oder verwerfen, dann erneut versuchen.\n");
    exit(1);
}
echo "OK, keine lokalen Änderungen.\n\n";

echo "== git pull --ff-only ==\n";
[$code, $out, $err] = runCommand('git pull --ff-only', $projectRoot);
echo $out;
if ($code !== 0) {
    fwrite(STDERR, $err . "\n");
    fwrite(STDERR, "git pull fehlgeschlagen (Exit-Code $code). Möglicherweise ist die Historie"
        . " auf dem Server divergiert - dann manuell per `git fetch`/`git log` prüfen.\n");
    exit(1);
}
echo "\n";

echo "== Datenbank-Migration ==\n";
require_once $projectRoot . '/includes/db.php';
try {
    getDb(); // Verbindungsaufbau stößt runMigrations() automatisch an (includes/migrate.php)
    echo "Migration geprüft/durchgeführt.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration fehlgeschlagen: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n== Update abgeschlossen ==\n";
