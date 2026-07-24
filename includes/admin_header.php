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
        <div class="topbar-title">Mitgliederverwaltung</div>
        <div class="topbar-user">
            Angemeldet als <strong><?= e(currentAdminUsername()) ?></strong>
            &middot; <a href="logout.php">Abmelden</a>
        </div>
    </header>
    <main class="content">
