<?php
/**
 * Gemeinsame Logik für das Aktualisieren der Anwendung per `git pull`
 * (genutzt von bin/update.php auf der Kommandozeile UND vom Update-Button
 * im Admin-Bereich, admin/update.php).
 */

function runShellCommand(string $command, string $cwd): array
{
    if (!function_exists('proc_open')) {
        return [1, '', 'proc_open() ist auf diesem Server deaktiviert - Updates sind daher nur per SSH/Kommandozeile möglich.'];
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptors, $pipes, $cwd);
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

/**
 * Führt das Update durch: prüft auf lokale Änderungen, macht `git pull --ff-only`
 * und stößt danach die Datenbank-Migration an. Bricht kontrolliert ab (success
 * = false), statt irgendetwas zu überschreiben, falls der Arbeitsbaum nicht
 * sauber ist oder der Pull nicht als reines Fast-Forward möglich ist.
 *
 * @return array{success: bool, log: string}
 */
function performUpdate(string $projectRoot): array
{
    $log = "== Prüfe auf lokale Änderungen ==\n";
    [$code, $out, $err] = runShellCommand('git status --porcelain', $projectRoot);
    if ($code !== 0) {
        return ['success' => false, 'log' => $log . "Fehler beim Prüfen des Git-Status (ist das Projektverzeichnis ein Git-Checkout?).\n$err"];
    }
    if (trim($out) !== '') {
        return ['success' => false, 'log' => $log . "Abgebrochen: nicht committete Änderungen im Projektverzeichnis:\n$out\n"
            . "Bitte zuerst sichern/committen oder verwerfen, dann erneut versuchen.\n"];
    }
    $log .= "OK, keine lokalen Änderungen.\n\n";

    $log .= "== git pull --ff-only ==\n";
    [$code, $out, $err] = runShellCommand('git pull --ff-only', $projectRoot);
    $log .= $out;
    if ($code !== 0) {
        $log .= $err . "\ngit pull fehlgeschlagen (Exit-Code $code). Möglicherweise ist die Historie"
            . " divergiert - dann per SSH manuell prüfen (`git fetch`/`git log`).\n";
        return ['success' => false, 'log' => $log];
    }
    $log .= "\n";

    $log .= "== Datenbank-Migration ==\n";
    try {
        getDb(); // Verbindungsaufbau stößt runMigrations() automatisch an (includes/migrate.php)
        $log .= "Migration geprüft/durchgeführt.\n";
    } catch (Throwable $e) {
        $log .= 'Migration fehlgeschlagen: ' . $e->getMessage() . "\n";
        return ['success' => false, 'log' => $log];
    }

    $log .= "\n== Update abgeschlossen ==\n";
    return ['success' => true, 'log' => $log];
}
