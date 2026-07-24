<?php
/**
 * Legt einen Admin-Account an (oder ändert dessen Passwort, falls er schon existiert).
 *
 * Aufruf (Kommandozeile):
 *   php bin/create_admin.php <benutzername> <passwort>
 */

require_once __DIR__ . '/../includes/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Dieses Script darf nur über die Kommandozeile ausgeführt werden.\n");
}

if ($argc !== 3) {
    fwrite(STDERR, "Verwendung: php bin/create_admin.php <benutzername> <passwort>\n");
    exit(1);
}

[$scriptName, $username, $password] = $argv;
$username = trim($username);

if ($username === '' || strlen($password) < 8) {
    fwrite(STDERR, "Benutzername darf nicht leer sein, Passwort muss mindestens 8 Zeichen haben.\n");
    exit(1);
}

$pdo = getDb();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username');
$stmt->execute(['username' => $username]);

if ($stmt->fetch()) {
    $pdo->prepare('UPDATE admins SET password_hash = :hash WHERE username = :username')
        ->execute(['hash' => $hash, 'username' => $username]);
    echo "Passwort für '$username' wurde aktualisiert.\n";
} else {
    $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :hash)')
        ->execute(['username' => $username, 'hash' => $hash]);
    echo "Admin '$username' wurde angelegt.\n";
}
