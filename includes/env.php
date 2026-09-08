<?php
/**
 * Minimaler .env-Loader ohne externe Abhängigkeiten (kein Composer nötig).
 * Liest KEY=VALUE-Zeilen aus einer Datei und stellt sie über env() bereit.
 * Kommentarzeilen (#) und leere Zeilen werden ignoriert, Anführungszeichen
 * um Werte werden entfernt. Bereits gesetzte echte Umgebungsvariablen
 * (z. B. vom Hoster vorgegeben) haben Vorrang vor der .env-Datei.
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
