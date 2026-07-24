<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$pdo = getDb();

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$member = [
    'vorname' => '', 'nachname' => '', 'geburtsdatum' => '',
    'strasse' => '', 'plz' => '', 'ort' => '',
    'email' => '', 'telefon' => '',
    'erziehungsberechtigter' => '', 'erziehungsberechtigter_email' => '', 'erziehungsberechtigter_telefon' => '',
    'beitrittsdatum' => date('Y-m-d'), 'status' => 'aktiv',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['flash'] = 'Mitglied wurde nicht gefunden.';
        redirect('index.php');
    }
    $member = $existing;
}

$errors = [];

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
        $member['beitrittsdatum'] = trim($_POST['beitrittsdatum'] ?? '');
        $member['status'] = ($_POST['status'] ?? 'aktiv') === 'inaktiv' ? 'inaktiv' : 'aktiv';

        if ($member['vorname'] === '') {
            $errors[] = 'Vorname ist ein Pflichtfeld.';
        }
        if ($member['nachname'] === '') {
            $errors[] = 'Nachname ist ein Pflichtfeld.';
        }
        if ($member['email'] !== '' && !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Mitglieds ist ungültig.';
        }
        if ($member['erziehungsberechtigter_email'] !== '' && !filter_var($member['erziehungsberechtigter_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Erziehungsberechtigten ist ungültig.';
        }
        foreach (['geburtsdatum', 'beitrittsdatum'] as $dateField) {
            if ($member[$dateField] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member[$dateField])) {
                $errors[] = 'Ungültiges Datumsformat.';
                break;
            }
        }

        if (!$errors) {
            $baseParams = [
                'vorname' => $member['vorname'],
                'nachname' => $member['nachname'],
                'geburtsdatum' => $member['geburtsdatum'] ?: null,
                'strasse' => $member['strasse'],
                'plz' => $member['plz'],
                'ort' => $member['ort'],
                'email' => $member['email'],
                'telefon' => $member['telefon'],
                'erziehungsberechtigter' => $member['erziehungsberechtigter'],
                'erziehungsberechtigter_email' => $member['erziehungsberechtigter_email'],
                'erziehungsberechtigter_telefon' => $member['erziehungsberechtigter_telefon'],
                'beitrittsdatum' => $member['beitrittsdatum'] ?: null,
                'status' => $member['status'],
            ];

            if ($isEdit) {
                $params = $baseParams;
                $params['id'] = $id;
                $sql = 'UPDATE members SET vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                        strasse = :strasse, plz = :plz, ort = :ort, email = :email, telefon = :telefon,
                        erziehungsberechtigter = :erziehungsberechtigter,
                        erziehungsberechtigter_email = :erziehungsberechtigter_email,
                        erziehungsberechtigter_telefon = :erziehungsberechtigter_telefon,
                        beitrittsdatum = :beitrittsdatum, status = :status
                        WHERE id = :id';
                $pdo->prepare($sql)->execute($params);
                $_SESSION['flash'] = 'Mitglied wurde aktualisiert.';
                redirect('index.php');
            } else {
                $params = $baseParams;
                $params['mitgliedsnummer'] = generateMitgliedsnummer($pdo);
                $params['verify_token'] = generateVerifyToken();
                $sql = 'INSERT INTO members
                        (mitgliedsnummer, vorname, nachname, geburtsdatum, strasse, plz, ort, email, telefon,
                         erziehungsberechtigter, erziehungsberechtigter_email, erziehungsberechtigter_telefon,
                         beitrittsdatum, status, verify_token)
                        VALUES
                        (:mitgliedsnummer, :vorname, :nachname, :geburtsdatum, :strasse, :plz, :ort, :email, :telefon,
                         :erziehungsberechtigter, :erziehungsberechtigter_email, :erziehungsberechtigter_telefon,
                         :beitrittsdatum, :status, :verify_token)';
                $pdo->prepare($sql)->execute($params);
                $newId = (int) $pdo->lastInsertId();
                $_SESSION['flash'] = 'Mitglied wurde angelegt. Der Verifizierungs-Link kann nun weitergegeben werden.';
                redirect('member_link.php?id=' . $newId);
            }
        }
    }
}

$pageTitle = $isEdit ? 'Mitglied bearbeiten' : 'Neues Mitglied';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1><?= e($pageTitle) ?></h1>
    <a href="index.php" class="btn btn-link">&larr; Zurück zur Liste</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="member_form.php<?= $isEdit ? '?id=' . (int) $id : '' ?>" class="member-form" novalidate>
    <?= csrfField() ?>

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
        <div class="form-row">
            <div class="form-group">
                <label for="geburtsdatum">Geburtsdatum</label>
                <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= e(formatDateForInput($member['geburtsdatum'])) ?>">
            </div>
            <div class="form-group">
                <label for="beitrittsdatum">Beitrittsdatum</label>
                <input type="date" id="beitrittsdatum" name="beitrittsdatum" value="<?= e(formatDateForInput($member['beitrittsdatum'])) ?>">
            </div>
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
        <legend>Erziehungsberechtigte/r (optional, z. B. bei Minderjährigen)</legend>
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

    <fieldset>
        <legend>Status</legend>
        <div class="form-group">
            <label for="status">Mitgliedsstatus</label>
            <select id="status" name="status">
                <option value="aktiv" <?= $member['status'] === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
                <option value="inaktiv" <?= $member['status'] === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
            </select>
        </div>
    </fieldset>

    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Änderungen speichern' : 'Mitglied anlegen' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
