-- Schema für die Mitgliederverwaltung
-- Zeichensatz: utf8mb4 (volle Unicode-/Emoji-Unterstützung)

CREATE DATABASE IF NOT EXISTS `mitgliederverwaltung`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `mitgliederverwaltung`;

-- Administratoren (Login für den Verwaltungsbereich)
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mitglieder
CREATE TABLE IF NOT EXISTS `members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mitgliedsnummer` VARCHAR(20) NOT NULL,

  `vorname` VARCHAR(100) NOT NULL,
  `nachname` VARCHAR(100) NOT NULL,
  `geburtsdatum` DATE NULL,

  `strasse` VARCHAR(150) NULL,
  `plz` VARCHAR(10) NULL,
  `ort` VARCHAR(100) NULL,

  `email` VARCHAR(190) NULL,
  `telefon` VARCHAR(50) NULL,

  `erziehungsberechtigter` VARCHAR(150) NULL,
  `erziehungsberechtigter_email` VARCHAR(190) NULL,
  `erziehungsberechtigter_telefon` VARCHAR(50) NULL,

  `beitrittsdatum` DATE NULL,
  `status` ENUM('aktiv','inaktiv') NOT NULL DEFAULT 'aktiv',

  `verify_token` CHAR(64) NOT NULL,
  `verified_at` DATETIME NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_members_mitgliedsnummer` (`mitgliedsnummer`),
  UNIQUE KEY `uq_members_verify_token` (`verify_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
