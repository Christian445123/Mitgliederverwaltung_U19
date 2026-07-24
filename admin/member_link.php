<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$pdo = getDb();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id');
$stmt->execute(['id' => $id]);
$member = $stmt->fetch();

if (!$member) {
    $_SESSION['flash'] = 'Mitglied wurde nicht gefunden.';
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    if (!verifyCsrf()) {
        die('Ungültige Anfrage.');
    }
    $newToken = generateVerifyToken();
    $upd = $pdo->prepare('UPDATE members SET verify_token = :token, verified_at = NULL WHERE id = :id');
    $upd->execute(['token' => $newToken, 'id' => $id]);
    $member['verify_token'] = $newToken;
    $member['verified_at'] = null;
}

$link = buildVerifyLink($member['verify_token']);
$pageTitle = 'Verifizierungs-Link';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1>Verifizierungs-Link</h1>
    <a href="index.php" class="btn btn-link">&larr; Zurück zur Liste</a>
</div>

<p>Für <strong><?= e($member['vorname'] . ' ' . $member['nachname']) ?></strong>
(Mitgl.-Nr. <?= e($member['mitgliedsnummer']) ?>).</p>

<p>Dieser Link kann dem Mitglied geschickt werden, damit es seine hinterlegten
Daten prüfen und bei Bedarf korrigieren kann:</p>

<div class="link-box">
    <input type="text" readonly value="<?= e($link) ?>" id="verifyLink" onclick="this.select();">
    <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('verifyLink').value); this.textContent='Kopiert!';">Kopieren</button>
</div>

<?php if ($member['verified_at']): ?>
    <p class="alert alert-success">Das Mitglied hat die Daten zuletzt am
        <?= e(date('d.m.Y H:i', strtotime($member['verified_at']))) ?> Uhr bestätigt.</p>
<?php else: ?>
    <p class="alert alert-warning">Die Daten wurden von diesem Mitglied noch nicht bestätigt.</p>
<?php endif; ?>

<form method="post" action="member_link.php?id=<?= (int) $id ?>"
      onsubmit="return confirm('Neuen Link erzeugen? Der alte Link funktioniert danach nicht mehr.');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="regenerate">
    <button type="submit" class="btn btn-secondary">Neuen Link erzeugen (alten ungültig machen)</button>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
