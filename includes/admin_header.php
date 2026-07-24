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
                    <a href="users.php">Benutzer</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="topbar-user">
            Angemeldet als <strong><?= e(currentAdminUsername()) ?></strong>
            (<?= e(isAdministrator() ? 'Administrator' : 'Bearbeiter') ?>)
            &middot; <a href="logout.php">Abmelden</a>
        </div>
    </header>
    <main class="content">
