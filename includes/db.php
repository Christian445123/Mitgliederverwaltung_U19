<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/migrate.php';

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            runMigrations($pdo);
        } catch (PDOException $e) {
            if (DEBUG) {
                die('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
            }
            die('Datenbankverbindung fehlgeschlagen. Bitte später erneut versuchen.');
        }
    }

    return $pdo;
}
