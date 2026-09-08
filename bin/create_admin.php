<?php
/**
 * Legt einen Benutzer-Account an (oder ändert dessen Passwort/Rolle, falls er
 * schon existiert).
 *
 * Aufruf (Kommandozeile):
 *   php bin/create_admin.php <benutzername> <passwort> [rolle]
 *
 * <rolle> ist optional: "administrator" (Standard) oder "bearbeiter".
 */

require_once __DIR__ . '/../includes/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Dieses Script darf nur über die Kommandozeile ausgeführt werden.\n");
}

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Verwendung: php bin/create_admin.php <benutzername> <passwort> [administrator|bearbeiter]\n");
    exit(1);
}

[$scriptName, $username, $password] = $argv;
$role = trim($argv[3] ?? 'administrator');
$username = trim($username);

if (!in_array($role, ['administrator', 'bearbeiter'], true)) {
    fwrite(STDERR, "Ungültige Rolle. Erlaubt: administrator, bearbeiter\n");
    exit(1);
}

if ($username === '' || strlen($password) < 8) {
    fwrite(STDERR, "Benutzername darf nicht leer sein, Passwort muss mindestens 8 Zeichen haben.\n");
    exit(1);
}

$pdo = getDb();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :username');
$stmt->execute(['username' => $username]);

if ($stmt->fetch()) {
    $pdo->prepare('UPDATE admins SET password_hash = :hash, role = :role WHERE username = :username')
        ->execute(['hash' => $hash, 'role' => $role, 'username' => $username]);
    echo "Benutzer '$username' wurde aktualisiert (Rolle: $role).\n";
} else {
    $pdo->prepare('INSERT INTO admins (username, password_hash, role) VALUES (:username, :hash, :role)')
        ->execute(['username' => $username, 'hash' => $hash, 'role' => $role]);
    echo "Benutzer '$username' wurde angelegt (Rolle: $role).\n";
}
