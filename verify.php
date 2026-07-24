<?php
/**
 * Öffentliche Seite, die per individuellem Token aufgerufen wird
 * (kein Admin-Login erforderlich). Das Mitglied kann hier die bei
 * der Anlage hinterlegten Daten prüfen und korrigieren.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$pdo = getDb();
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    require __DIR__ . '/includes/public_header.php';
    echo '<p class="alert alert-error">Dieser Link ist ungültig.</p>';
    require __DIR__ . '/includes/public_footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE verify_token = :token');
$stmt->execute(['token' => $token]);
$member = $stmt->fetch();

if (!$member) {
    http_response_code(404);
    require __DIR__ . '/includes/public_header.php';
    echo '<p class="alert alert-error">Dieser Link ist ungültig oder wurde bereits erneuert. Bitte wende dich an den Verein, um einen neuen Link zu erhalten.</p>';
    require __DIR__ . '/includes/public_footer.php';
    exit;
}

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $member['vorname'] = trim($_POST['vorname'] ?? '');
        $member['nachname'] = trim($_POST['nachname'] ?? '');
        $member['geburtsdatum'] = trim($_POST['geburtsdatum'] ?? '');
        $member['strasse'] = trim($_POST['strasse'] ?? '');
        $member['plz'] = trim($_POST['plz'] ?? '');
        $member['ort'] = trim($_POST['ort'] ?? '');
        $member['email'] = trim($_POST['email'] ?? '');
        $member['telefon'] = trim($_POST['telefon'] ?? '');
        $member['erziehungsberechtigter'] = trim($_POST['erziehungsberechtigter'] ?? '');
        $member['erziehungsberechtigter_email'] = trim($_POST['erziehungsberechtigter_email'] ?? '');
        $member['erziehungsberechtigter_telefon'] = trim($_POST['erziehungsberechtigter_telefon'] ?? '');

        if ($member['vorname'] === '') {
            $errors[] = 'Vorname ist ein Pflichtfeld.';
        }
        if ($member['nachname'] === '') {
            $errors[] = 'Nachname ist ein Pflichtfeld.';
        }
        if ($member['email'] !== '' && !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die angegebene E-Mail-Adresse ist ungültig.';
        }
        if ($member['erziehungsberechtigter_email'] !== '' && !filter_var($member['erziehungsberechtigter_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Erziehungsberechtigten ist ungültig.';
        }
        if ($member['geburtsdatum'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['geburtsdatum'])) {
            $errors[] = 'Ungültiges Geburtsdatum.';
        }

        if (!$errors) {
            $sql = 'UPDATE members SET vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                    strasse = :strasse, plz = :plz, ort = :ort, email = :email, telefon = :telefon,
                    erziehungsberechtigter = :erziehungsberechtigter,
                    erziehungsberechtigter_email = :erziehungsberechtigter_email,
                    erziehungsberechtigter_telefon = :erziehungsberechtigter_telefon,
                    verified_at = NOW()
                    WHERE id = :id';
            $params = $member;
            $params['geburtsdatum'] = $member['geburtsdatum'] ?: null;
            $params['id'] = $member['id'];
            $pdo->prepare($sql)->execute($params);
            $saved = true;
        }
    }
}

$pageTitle = 'Meine Daten prüfen';
require __DIR__ . '/includes/public_header.php';
?>
<div class="verify-box">
    <h1>Deine Mitgliedsdaten</h1>
    <p>Bitte überprüfe die unten stehenden Angaben. Falls etwas nicht (mehr) stimmt,
       korrigiere es direkt im Formular und klicke auf „Bestätigen &amp; Speichern“.</p>

    <?php if ($saved): ?>
        <p class="alert alert-success">Danke! Deine Daten wurden gespeichert und als geprüft markiert.</p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="verify.php?token=<?= e($token) ?>" class="member-form" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>Persönliche Daten</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="vorname">Vorname *</label>
                    <input type="text" id="vorname" name="vorname" required value="<?= e($member['vorname']) ?>">
                </div>
                <div class="form-group">
                    <label for="nachname">Nachname *</label>
                    <input type="text" id="nachname" name="nachname" required value="<?= e($member['nachname']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="geburtsdatum">Geburtsdatum</label>
                <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= e(formatDateForInput($member['geburtsdatum'])) ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend>Kontakt & Adresse</legend>
            <div class="form-group">
                <label for="strasse">Straße & Hausnummer</label>
                <input type="text" id="strasse" name="strasse" value="<?= e($member['strasse']) ?>">
            </div>
            <div class="form-row">
                <div class="form-group form-group-small">
                    <label for="plz">PLZ</label>
                    <input type="text" id="plz" name="plz" value="<?= e($member['plz']) ?>">
                </div>
                <div class="form-group">
                    <label for="ort">Ort</label>
                    <input type="text" id="ort" name="ort" value="<?= e($member['ort']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <input type="email" id="email" name="email" value="<?= e($member['email']) ?>">
                </div>
                <div class="form-group">
                    <label for="telefon">Telefon</label>
                    <input type="text" id="telefon" name="telefon" value="<?= e($member['telefon']) ?>">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Erziehungsberechtigte/r (falls minderjährig)</legend>
            <div class="form-group">
                <label for="erziehungsberechtigter">Name</label>
                <input type="text" id="erziehungsberechtigter" name="erziehungsberechtigter" value="<?= e($member['erziehungsberechtigter']) ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="erziehungsberechtigter_email">E-Mail</label>
                    <input type="email" id="erziehungsberechtigter_email" name="erziehungsberechtigter_email" value="<?= e($member['erziehungsberechtigter_email']) ?>">
                </div>
                <div class="form-group">
                    <label for="erziehungsberechtigter_telefon">Telefon</label>
                    <input type="text" id="erziehungsberechtigter_telefon" name="erziehungsberechtigter_telefon" value="<?= e($member['erziehungsberechtigter_telefon']) ?>">
                </div>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary">Bestätigen & Speichern</button>
    </form>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
