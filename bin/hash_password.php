<?php
/**
 * Erzeugt einen bcrypt-Hash für ein Passwort - z. B. zum Eintragen von
 * EMERGENCY_ADMIN_PASSWORD_HASH in der .env (siehe includes/env.php).
 *
 * Aufruf (Kommandozeile):
 *   php bin/hash_password.php <passwort>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Dieses Script darf nur über die Kommandozeile ausgeführt werden.\n");
}

if ($argc !== 2) {
    fwrite(STDERR, "Verwendung: php bin/hash_password.php <passwort>\n");
    exit(1);
}

$password = $argv[1];
if (strlen($password) < 12) {
    fwrite(STDERR, "Für den Notfall-Zugang wird ein Passwort mit mindestens 12 Zeichen empfohlen.\n");
}

echo password_hash($password, PASSWORD_DEFAULT) . "\n";
