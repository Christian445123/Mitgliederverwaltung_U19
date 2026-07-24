<?php
/**
 * Sehr einfache Auto-Migration.
 *
 * Legt beim Verbindungsaufbau automatisch fehlende Tabellen und Spalten an,
 * damit nach einem Code-Update (z. B. ein neues Mitglieds-Feld) kein
 * manueller SQL-Import per SSH/phpMyAdmin mehr nötig ist.
 *
 * Neues Feld ergänzen: einfach unten in membersColumnDefinitions() eine
 * Zeile hinzufügen - die Spalte wird beim nächsten Aufruf automatisch angelegt.
 */

function runMigrations(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(64) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_admins_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `members` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `mitgliedsnummer` VARCHAR(20) NOT NULL,
        `vorname` VARCHAR(100) NOT NULL,
        `nachname` VARCHAR(100) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_members_mitgliedsnummer` (`mitgliedsnummer`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    syncColumns($pdo, 'admins', adminsColumnDefinitions());
    syncColumns($pdo, 'members', membersColumnDefinitions());
    syncIndexes($pdo, 'members', membersIndexDefinitions());
}

function adminsColumnDefinitions(): array
{
    return [
        'username'      => "VARCHAR(64) NOT NULL",
        'password_hash' => "VARCHAR(255) NOT NULL",
        'created_at'    => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
    ];
}

/** Gewünschte Spalten der members-Tabelle. Neue Felder hier ergänzen. */
function membersColumnDefinitions(): array
{
    return [
        'mitgliedsnummer'                  => "VARCHAR(20) NOT NULL",
        'vorname'                          => "VARCHAR(100) NOT NULL",
        'nachname'                         => "VARCHAR(100) NOT NULL",
        'geburtsdatum'                     => "DATE NULL",
        'strasse'                          => "VARCHAR(150) NULL",
        'plz'                              => "VARCHAR(10) NULL",
        'ort'                              => "VARCHAR(100) NULL",
        'email'                            => "VARCHAR(190) NULL",
        'telefon'                          => "VARCHAR(50) NULL",
        'erziehungsberechtigter'           => "VARCHAR(150) NULL",
        'erziehungsberechtigter_email'     => "VARCHAR(190) NULL",
        'erziehungsberechtigter_telefon'   => "VARCHAR(50) NULL",
        'passnummer'                       => "VARCHAR(30) NULL",
        'name_laut_pass'                   => "VARCHAR(150) NULL",
        'allergien'                        => "TEXT NULL",
        'nada_kurs_datum'                  => "DATE NULL",
        'beitrittsdatum'                   => "DATE NULL",
        'status'                           => "ENUM('aktiv','inaktiv') NOT NULL DEFAULT 'aktiv'",
        'verify_token'                     => "CHAR(64) NULL",
        'verified_at'                      => "DATETIME NULL",
        'access_password_hash'             => "VARCHAR(255) NULL",
        'failed_verify_attempts'           => "INT UNSIGNED NOT NULL DEFAULT 0",
        'verify_locked_until'              => "DATETIME NULL",
    ];
}

function membersIndexDefinitions(): array
{
    return [
        'uq_members_verify_token' => "UNIQUE KEY `uq_members_verify_token` (`verify_token`)",
    ];
}

/** Legt fehlende Spalten einer Tabelle an, bestehende Spalten werden nicht verändert. */
function syncColumns(PDO $pdo, string $table, array $columnDefinitions): void
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
    $existing = array_column($stmt->fetchAll(), 'Field');

    foreach ($columnDefinitions as $column => $definition) {
        if (!in_array($column, $existing, true)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
}

/** Legt fehlende Indizes/Unique-Keys einer Tabelle an. */
function syncIndexes(PDO $pdo, string $table, array $indexDefinitions): void
{
    $stmt = $pdo->query('SHOW INDEX FROM `' . $table . '`');
    $existing = array_column($stmt->fetchAll(), 'Key_name');

    foreach ($indexDefinitions as $indexName => $definition) {
        if (!in_array($indexName, $existing, true)) {
            $pdo->exec("ALTER TABLE `$table` ADD $definition");
        }
    }
}
