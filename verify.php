<?php
/**
 * Öffentliche Seite, die per individuellem Token aufgerufen wird
 * (kein Admin-Login erforderlich). Das Mitglied kann hier die bei
 * der Anlage hinterlegten Daten prüfen und korrigieren.
 *
 * Zum Schutz der personenbezogenen Daten (DSGVO Art. 32) reicht der Link
 * allein nicht aus: Erst nach Eingabe von E-Mail-Adresse und dem separat
 * mitgeteilten Zugangscode werden die Daten angezeigt.
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

$memberId = (int) $member['id'];
$sessionKey = 'verify_unlocked_' . $memberId;
$unlocked = !empty($_SESSION[$sessionKey]);

const MAX_VERIFY_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

// --- Stufe 1: Zugang per E-Mail + Zugangscode freischalten ------------------
if (!$unlocked) {
    $unlockError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['stage'] ?? '') === 'unlock') {
        if (!verifyCsrf()) {
            $unlockError = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
        } else {
            $lockedUntil = $member['verify_locked_until'] ? strtotime($member['verify_locked_until']) : null;

            if ($lockedUntil && $lockedUntil > time()) {
                $minutesLeft = max(1, (int) ceil(($lockedUntil - time()) / 60));
                $unlockError = "Zu viele Fehlversuche. Bitte in etwa {$minutesLeft} Minute(n) erneut versuchen.";
            } else {
                $emailInput = trim($_POST['email'] ?? '');
                $passwordInput = (string) ($_POST['password'] ?? '');

                $emailMatches = !empty($member['email'])
                    && strcasecmp(trim($member['email']), $emailInput) === 0;
                $passwordMatches = !empty($member['access_password_hash'])
                    && password_verify($passwordInput, $member['access_password_hash']);

                if ($emailMatches && $passwordMatches) {
                    $_SESSION[$sessionKey] = true;
                    $pdo->prepare('UPDATE members SET failed_verify_attempts = 0, verify_locked_until = NULL WHERE id = :id')
                        ->execute(['id' => $memberId]);
                    redirect('verify.php?token=' . $token);
                } else {
                    $attempts = (int) $member['failed_verify_attempts'] + 1;
                    $params = ['attempts' => $attempts, 'id' => $memberId];
                    $lockClause = '';
                    if ($attempts >= MAX_VERIFY_ATTEMPTS) {
                        $lockClause = ', verify_locked_until = DATE_ADD(NOW(), INTERVAL ' . LOCKOUT_MINUTES . ' MINUTE)';
                    }
                    $pdo->prepare("UPDATE members SET failed_verify_attempts = :attempts $lockClause WHERE id = :id")
                        ->execute($params);
                    $member['failed_verify_attempts'] = $attempts;
                    $unlockError = 'E-Mail-Adresse oder Zugangscode ist falsch.';
                }
            }
        }
    }

    $pageTitle = 'Zugang zu meinen Daten';
    require __DIR__ . '/includes/public_header.php';
    ?>
    <div class="verify-box">
        <h1>Zugang zu deinen Mitgliedsdaten</h1>
        <p>Zum Schutz deiner Daten benötigen wir zusätzlich zum Link deine hinterlegte
           E-Mail-Adresse und den dir separat mitgeteilten Zugangscode.</p>

        <?php if ($unlockError): ?>
            <p class="alert alert-error"><?= e($unlockError) ?></p>
        <?php endif; ?>

        <form method="post" action="verify.php?token=<?= e($token) ?>" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="stage" value="unlock">
            <label for="email">E-Mail-Adresse</label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password">Zugangscode</label>
            <input type="text" id="password" name="password" required autocomplete="off">
            <button type="submit" class="btn btn-primary" style="margin-top:20px;">Zugang prüfen</button>
        </form>

        <p class="privacy-note">
            Hinweis zum Datenschutz: Deine Angaben werden ausschließlich zur Mitgliederverwaltung
            des Vereins verarbeitet und nicht an Dritte weitergegeben. Details dazu findest du in
            der Datenschutzerklärung des Vereins.
        </p>
    </div>
    <?php
    require __DIR__ . '/includes/public_footer.php';
    exit;
}

// --- Stufe 2: freigeschaltet - Daten anzeigen/bearbeiten --------------------
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
        $member['passnummer'] = trim($_POST['passnummer'] ?? '');
        $member['name_laut_pass'] = trim($_POST['name_laut_pass'] ?? '');
        $member['allergien'] = trim($_POST['allergien'] ?? '');
        $member['nada_kurs_datum'] = trim($_POST['nada_kurs_datum'] ?? '');

        if ($member['vorname'] === '') {
            $errors[] = 'Vorname ist ein Pflichtfeld.';
        }
        if ($member['nachname'] === '') {
            $errors[] = 'Nachname ist ein Pflichtfeld.';
        }
        if ($member['email'] === '') {
            $errors[] = 'E-Mail ist ein Pflichtfeld.';
        } elseif (!filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die angegebene E-Mail-Adresse ist ungültig.';
        }
        if ($member['erziehungsberechtigter_email'] !== '' && !filter_var($member['erziehungsberechtigter_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Erziehungsberechtigten ist ungültig.';
        }
        if ($member['geburtsdatum'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['geburtsdatum'])) {
            $errors[] = 'Ungültiges Geburtsdatum.';
        }
        if ($member['nada_kurs_datum'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member['nada_kurs_datum'])) {
            $errors[] = 'Ungültiges Datum beim NADA-Kurs.';
        }

        if (!$errors) {
            $sql = 'UPDATE members SET vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                    strasse = :strasse, plz = :plz, ort = :ort, email = :email, telefon = :telefon,
                    erziehungsberechtigter = :erziehungsberechtigter,
                    erziehungsberechtigter_email = :erziehungsberechtigter_email,
                    erziehungsberechtigter_telefon = :erziehungsberechtigter_telefon,
                    passnummer = :passnummer, name_laut_pass = :name_laut_pass,
                    allergien = :allergien, nada_kurs_datum = :nada_kurs_datum,
                    verified_at = NOW()
                    WHERE id = :id';
            $params = [
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
                'passnummer' => $member['passnummer'],
                'name_laut_pass' => $member['name_laut_pass'],
                'allergien' => $member['allergien'],
                'nada_kurs_datum' => $member['nada_kurs_datum'] ?: null,
                'id' => $member['id'],
            ];
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
                    <label for="email">E-Mail *</label>
                    <input type="email" id="email" name="email" required value="<?= e($member['email']) ?>">
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

        <fieldset>
            <legend>Sportliche & sonstige Angaben</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="passnummer">Passnummer</label>
                    <input type="text" id="passnummer" name="passnummer" value="<?= e($member['passnummer']) ?>">
                </div>
                <div class="form-group">
                    <label for="name_laut_pass">Name laut Pass (falls abweichend)</label>
                    <input type="text" id="name_laut_pass" name="name_laut_pass" value="<?= e($member['name_laut_pass']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="nada_kurs_datum">NADA-Kurs absolviert am</label>
                <input type="date" id="nada_kurs_datum" name="nada_kurs_datum" value="<?= e(formatDateForInput($member['nada_kurs_datum'])) ?>">
            </div>
            <div class="form-group">
                <label for="allergien">Allergien</label>
                <textarea id="allergien" name="allergien" rows="3"><?= e($member['allergien']) ?></textarea>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary">Bestätigen & Speichern</button>
    </form>

    <p class="privacy-note">
        Hinweis zum Datenschutz: Deine Angaben werden ausschließlich zur Mitgliederverwaltung
        des Vereins verarbeitet und nicht an Dritte weitergegeben. Details dazu findest du in
        der Datenschutzerklärung des Vereins.
    </p>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
