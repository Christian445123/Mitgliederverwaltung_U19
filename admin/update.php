<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/updater.php';

startSecureSession();
requireAdministrator();

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $result = ['success' => false, 'log' => 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.'];
    } else {
        $result = performUpdate(dirname(__DIR__));
    }
}

$pageTitle = 'Anwendung aktualisieren';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1>Anwendung aktualisieren</h1>
    <a href="index.php" class="btn btn-link">&larr; Zurück zur Liste</a>
</div>

<p>Zieht den neuesten Code aus dem Git-Repository (<code>git pull --ff-only</code>) und
führt danach automatisch die Datenbank-Migration aus (neue Spalten/Tabellen). Bricht
kontrolliert ab, ohne etwas zu verändern, falls auf dem Server nicht committete
Änderungen liegen oder der Pull nicht als reines Fast-Forward möglich ist.</p>

<?php if ($result): ?>
    <p class="alert alert-<?= $result['success'] ? 'success' : 'error' ?>">
        <?= $result['success'] ? 'Update erfolgreich.' : 'Update fehlgeschlagen - siehe Log unten.' ?>
    </p>
    <pre style="background:#1e1e1e;color:#e5e5e5;padding:12px;border-radius:6px;overflow-x:auto;white-space:pre-wrap;"><?= e($result['log']) ?></pre>
<?php endif; ?>

<form method="post" action="update.php" data-confirm="Jetzt den neuesten Code per git pull einspielen?">
    <?= csrfField() ?>
    <button type="submit" class="btn btn-primary">Jetzt aktualisieren (git pull)</button>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
