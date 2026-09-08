<?php
/**
 * Erwartet, dass startSecureSession() + requireLogin() bereits aufgerufen wurden
 * und $pageTitle gesetzt ist.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Verwaltung') ?> – Mitgliederverwaltung</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <header class="topbar">
        <div class="topbar-left">
            <span class="topbar-title">Mitgliederverwaltung</span>
            <nav class="topbar-nav">
                <a href="index.php">Mitglieder</a>
                <?php if (isAdministrator()): ?>
                    <a href="member_import.php">Import</a>
                    <a href="users.php">Benutzer</a>
                    <a href="update.php">Update</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="topbar-user">
            Angemeldet als <strong><?= e(currentAdminUsername()) ?></strong>
            (<?= e(isAdministrator() ? 'Administrator' : 'Bearbeiter') ?>)
            &middot; <a href="logout.php">Abmelden</a>
        </div>
    </header>
    <?php if (!empty($_SESSION['emergency_login'])): ?>
        <p class="alert alert-error" style="margin:0;border-radius:0;">
            ⚠️ Angemeldet über den <strong>Notfall-Zugang</strong> (ohne Datenbank-Benutzerprüfung).
            Bitte sobald wie möglich einen regulären Administrator-Account reparieren/anlegen
            (<code>php bin/create_admin.php</code>) und diesen Zugang danach nicht mehr nutzen.
        </p>
    <?php endif; ?>
    <main class="content">
