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

// Zugangscode aus der Neuanlage (member_form.php) einmalig anzeigen.
$generatedPassword = null;
if (!empty($_SESSION['generated_password'])) {
    $generatedPassword = $_SESSION['generated_password'];
    unset($_SESSION['generated_password']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        die('Ungültige Anfrage.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'regenerate') {
        $newToken = generateVerifyToken();
        $upd = $pdo->prepare('UPDATE members SET verify_token = :token, verified_at = NULL,
                               failed_verify_attempts = 0, verify_locked_until = NULL WHERE id = :id');
        $upd->execute(['token' => $newToken, 'id' => $id]);
        $member['verify_token'] = $newToken;
        $member['verified_at'] = null;
    } elseif ($action === 'regenerate_password') {
        $generatedPassword = generateAccessPassword();
        $upd = $pdo->prepare('UPDATE members SET access_password_hash = :hash,
                               failed_verify_attempts = 0, verify_locked_until = NULL WHERE id = :id');
        $upd->execute(['hash' => password_hash($generatedPassword, PASSWORD_DEFAULT), 'id' => $id]);
    }
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
Daten prüfen und bei Bedarf korrigieren kann. Zum Öffnen der Daten benötigt
das Mitglied zusätzlich seine E-Mail-Adresse und den Zugangscode:</p>

<div class="link-box">
    <input type="text" readonly value="<?= e($link) ?>" id="verifyLink" onclick="this.select();">
    <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('verifyLink').value); this.textContent='Kopiert!';">Kopieren</button>
</div>

<?php if ($generatedPassword): ?>
    <div class="alert alert-warning">
        <p><strong>Zugangscode (wird aus Sicherheitsgründen nur jetzt einmalig angezeigt):</strong></p>
        <div class="link-box">
            <input type="text" readonly value="<?= e($generatedPassword) ?>" id="accessPassword" onclick="this.select();">
            <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('accessPassword').value); this.textContent='Kopiert!';">Kopieren</button>
        </div>
        <p>Bitte <strong>getrennt vom Link</strong> übermitteln (z. B. telefonisch oder per SMS statt
        derselben E-Mail) – nur so bringt der Zugangscode zusätzlichen Schutz. Er wird nirgends im
        Klartext gespeichert; bei Verlust einfach unten neu generieren.</p>
    </div>
<?php else: ?>
    <p class="alert alert-warning">Der aktuelle Zugangscode wird aus Sicherheitsgründen nicht mehr angezeigt
        (es ist nur ein Hash gespeichert). Bei Bedarf unten einen neuen generieren.</p>
<?php endif; ?>

<?php if ($member['verified_at']): ?>
    <p class="alert alert-success">Das Mitglied hat die Daten zuletzt am
        <?= e(date('d.m.Y H:i', strtotime($member['verified_at']))) ?> Uhr bestätigt.</p>
<?php else: ?>
    <p class="alert alert-warning">Die Daten wurden von diesem Mitglied noch nicht bestätigt.</p>
<?php endif; ?>

<?php if ((int) $member['failed_verify_attempts'] > 0): ?>
    <p class="alert alert-error">
        <?= (int) $member['failed_verify_attempts'] ?> fehlgeschlagene(r) Zugangsversuch(e).
        <?php if ($member['verify_locked_until'] && strtotime($member['verify_locked_until']) > time()): ?>
            Aktuell gesperrt bis <?= e(date('d.m.Y H:i', strtotime($member['verify_locked_until']))) ?> Uhr.
        <?php endif; ?>
    </p>
<?php endif; ?>

<form method="post" action="member_link.php?id=<?= (int) $id ?>"
      onsubmit="return confirm('Neuen Link erzeugen? Der alte Link funktioniert danach nicht mehr.');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="regenerate">
    <button type="submit" class="btn btn-secondary">Neuen Link erzeugen (alten ungültig machen)</button>
</form>

<form method="post" action="member_link.php?id=<?= (int) $id ?>"
      onsubmit="return confirm('Neuen Zugangscode generieren? Der alte Code funktioniert danach nicht mehr.');">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="regenerate_password">
    <button type="submit" class="btn btn-secondary">Neuen Zugangscode generieren</button>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
