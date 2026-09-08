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

        $member['koerpergroesse_cm'] = trim($_POST['koerpergroesse_cm'] ?? '');
        $member['gewicht_kg'] = trim($_POST['gewicht_kg'] ?? '');
        $member['geburtsland'] = trim($_POST['geburtsland'] ?? '');
        $member['geburtsort'] = trim($_POST['geburtsort'] ?? '');
        $member['reisepass_nr'] = trim($_POST['reisepass_nr'] ?? '');
        $member['reisepass_ausgestellt_am'] = trim($_POST['reisepass_ausgestellt_am'] ?? '');
        $member['reisepass_gueltig_bis'] = trim($_POST['reisepass_gueltig_bis'] ?? '');
        $member['reisepass_ausstellungsbehoerde'] = trim($_POST['reisepass_ausstellungsbehoerde'] ?? '');
        $member['sozialversicherungsnummer'] = trim($_POST['sozialversicherungsnummer'] ?? '');
        $member['essen'] = trim($_POST['essen'] ?? '');
        $member['jersey_groesse'] = trim($_POST['jersey_groesse'] ?? '');
        $member['hosen_groesse'] = trim($_POST['hosen_groesse'] ?? '');
        $member['mesh_shorts_groesse'] = trim($_POST['mesh_shorts_groesse'] ?? '');
        $member['helm_groesse'] = trim($_POST['helm_groesse'] ?? '');
        $member['helm_modell'] = trim($_POST['helm_modell'] ?? '');
        $member['tshirt_polo_groesse'] = trim($_POST['tshirt_polo_groesse'] ?? '');
        $member['hoodie_groesse'] = trim($_POST['hoodie_groesse'] ?? '');
        $member['socken_groesse'] = trim($_POST['socken_groesse'] ?? '');
        $member['helm_vorhanden'] = in_array($_POST['helm_vorhanden'] ?? '', ['ja', 'nein'], true) ? $_POST['helm_vorhanden'] : null;
        $rechtePflichtenAkzeptiert = !empty($_POST['rechte_pflichten_akzeptiert']);
        $member['bild_einverstaendnis_akzeptiert_at'] = !empty($_POST['bild_einverstaendnis_akzeptiert'])
            ? ($member['bild_einverstaendnis_akzeptiert_at'] ?: date('Y-m-d H:i:s'))
            : null;

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
        foreach (['geburtsdatum', 'nada_kurs_datum', 'reisepass_ausgestellt_am', 'reisepass_gueltig_bis'] as $dateField) {
            if ($member[$dateField] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $member[$dateField])) {
                $errors[] = 'Ungültiges Datumsformat.';
                break;
            }
        }
        foreach (['koerpergroesse_cm' => 'Körpergröße', 'gewicht_kg' => 'Gewicht'] as $numField => $label) {
            if ($member[$numField] !== '' && (!ctype_digit((string) $member[$numField]) || (int) $member[$numField] <= 0)) {
                $errors[] = "$label muss eine positive Zahl sein.";
            }
        }
        if (!$rechtePflichtenAkzeptiert) {
            $errors[] = 'Bitte bestätige, dass du die Rechte & Pflichten akzeptierst.';
        }

        $uploadedPhoto = null;
        if (!$errors && !empty($_FILES['pass_foto']['name'])) {
            try {
                $uploadedPhoto = handlePassFotoUpload($_FILES['pass_foto'], $memberId);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!$errors) {
            $member['rechte_pflichten_akzeptiert_at'] = $member['rechte_pflichten_akzeptiert_at'] ?: date('Y-m-d H:i:s');

            $sql = 'UPDATE members SET vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                    strasse = :strasse, plz = :plz, ort = :ort, email = :email, telefon = :telefon,
                    erziehungsberechtigter = :erziehungsberechtigter,
                    erziehungsberechtigter_email = :erziehungsberechtigter_email,
                    erziehungsberechtigter_telefon = :erziehungsberechtigter_telefon,
                    passnummer = :passnummer, name_laut_pass = :name_laut_pass,
                    allergien = :allergien, nada_kurs_datum = :nada_kurs_datum,
                    koerpergroesse_cm = :koerpergroesse_cm, gewicht_kg = :gewicht_kg,
                    geburtsland = :geburtsland, geburtsort = :geburtsort,
                    reisepass_nr = :reisepass_nr, reisepass_ausgestellt_am = :reisepass_ausgestellt_am,
                    reisepass_gueltig_bis = :reisepass_gueltig_bis,
                    reisepass_ausstellungsbehoerde = :reisepass_ausstellungsbehoerde,
                    sozialversicherungsnummer = :sozialversicherungsnummer,
                    essen = :essen, jersey_groesse = :jersey_groesse, hosen_groesse = :hosen_groesse,
                    mesh_shorts_groesse = :mesh_shorts_groesse, helm_groesse = :helm_groesse, helm_modell = :helm_modell,
                    tshirt_polo_groesse = :tshirt_polo_groesse, hoodie_groesse = :hoodie_groesse,
                    socken_groesse = :socken_groesse,
                    helm_vorhanden = :helm_vorhanden,
                    rechte_pflichten_akzeptiert_at = :rechte_pflichten_akzeptiert_at,
                    bild_einverstaendnis_akzeptiert_at = :bild_einverstaendnis_akzeptiert_at,'
                    . ($uploadedPhoto ? ' pass_foto_pfad = :pass_foto_pfad,' : '') . '
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
                'koerpergroesse_cm' => $member['koerpergroesse_cm'] !== '' ? (int) $member['koerpergroesse_cm'] : null,
                'gewicht_kg' => $member['gewicht_kg'] !== '' ? (int) $member['gewicht_kg'] : null,
                'geburtsland' => $member['geburtsland'],
                'geburtsort' => $member['geburtsort'],
                'reisepass_nr' => $member['reisepass_nr'],
                'reisepass_ausgestellt_am' => $member['reisepass_ausgestellt_am'] ?: null,
                'reisepass_gueltig_bis' => $member['reisepass_gueltig_bis'] ?: null,
                'reisepass_ausstellungsbehoerde' => $member['reisepass_ausstellungsbehoerde'],
                'sozialversicherungsnummer' => $member['sozialversicherungsnummer'],
                'essen' => $member['essen'],
                'jersey_groesse' => $member['jersey_groesse'],
                'hosen_groesse' => $member['hosen_groesse'],
                'mesh_shorts_groesse' => $member['mesh_shorts_groesse'],
                'helm_groesse' => $member['helm_groesse'],
                'helm_modell' => $member['helm_modell'],
                'tshirt_polo_groesse' => $member['tshirt_polo_groesse'],
                'hoodie_groesse' => $member['hoodie_groesse'],
                'socken_groesse' => $member['socken_groesse'],
                'helm_vorhanden' => $member['helm_vorhanden'],
                'rechte_pflichten_akzeptiert_at' => $member['rechte_pflichten_akzeptiert_at'],
                'bild_einverstaendnis_akzeptiert_at' => $member['bild_einverstaendnis_akzeptiert_at'],
                'id' => $member['id'],
            ];
            if ($uploadedPhoto) {
                deletePassFoto($member['pass_foto_pfad'] ?? null);
                $params['pass_foto_pfad'] = $uploadedPhoto;
                $member['pass_foto_pfad'] = $uploadedPhoto;
            }
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

    <form method="post" action="verify.php?token=<?= e($token) ?>" class="member-form" enctype="multipart/form-data" novalidate>
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
            <div class="form-row">
                <div class="form-group">
                    <label for="geburtsdatum">Geburtsdatum</label>
                    <input type="date" id="geburtsdatum" name="geburtsdatum" value="<?= e(formatDateForInput($member['geburtsdatum'])) ?>">
                </div>
                <div class="form-group">
                    <label for="geburtsland">Geburtsland</label>
                    <input type="text" id="geburtsland" name="geburtsland" value="<?= e($member['geburtsland']) ?>">
                </div>
                <div class="form-group">
                    <label for="geburtsort">Geburtsort</label>
                    <input type="text" id="geburtsort" name="geburtsort" value="<?= e($member['geburtsort']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="koerpergroesse_cm">Körpergröße (cm)</label>
                    <input type="number" min="1" id="koerpergroesse_cm" name="koerpergroesse_cm" value="<?= e((string) $member['koerpergroesse_cm']) ?>">
                </div>
                <div class="form-group">
                    <label for="gewicht_kg">Gewicht (kg)</label>
                    <input type="number" min="1" id="gewicht_kg" name="gewicht_kg" value="<?= e((string) $member['gewicht_kg']) ?>">
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
            <legend>Reisedokumente</legend>
            <div class="form-row">
                <div class="form-group">
                    <label for="passnummer">Sport-Passnummer</label>
                    <input type="text" id="passnummer" name="passnummer" value="<?= e($member['passnummer']) ?>">
                </div>
                <div class="form-group">
                    <label for="name_laut_pass">Name laut Pass (falls abweichend)</label>
                    <input type="text" id="name_laut_pass" name="name_laut_pass" value="<?= e($member['name_laut_pass']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="reisepass_nr">Reisepass-Nr.</label>
                    <input type="text" id="reisepass_nr" name="reisepass_nr" value="<?= e($member['reisepass_nr']) ?>">
                </div>
                <div class="form-group">
                    <label for="reisepass_ausstellungsbehoerde">Ausstellungsbehörde</label>
                    <input type="text" id="reisepass_ausstellungsbehoerde" name="reisepass_ausstellungsbehoerde" value="<?= e($member['reisepass_ausstellungsbehoerde']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="reisepass_ausgestellt_am">Reisepass ausgestellt am</label>
                    <input type="date" id="reisepass_ausgestellt_am" name="reisepass_ausgestellt_am" value="<?= e(formatDateForInput($member['reisepass_ausgestellt_am'])) ?>">
                </div>
                <div class="form-group">
                    <label for="reisepass_gueltig_bis">Reisepass gültig bis</label>
                    <input type="date" id="reisepass_gueltig_bis" name="reisepass_gueltig_bis" value="<?= e(formatDateForInput($member['reisepass_gueltig_bis'])) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="sozialversicherungsnummer">Sozialversicherungsnummer</label>
                <input type="text" id="sozialversicherungsnummer" name="sozialversicherungsnummer" autocomplete="off" value="<?= e($member['sozialversicherungsnummer']) ?>">
            </div>
            <div class="form-group">
                <label for="pass_foto">Passfoto <?= !empty($member['pass_foto_pfad']) ? '(bereits hochgeladen – Auswahl ersetzt es)' : '' ?></label>
                <input type="file" id="pass_foto" name="pass_foto" accept="image/png,image/jpeg">
            </div>
        </fieldset>

        <fieldset>
            <legend>Verpflegung & Ausrüstung</legend>
            <div class="form-group">
                <label for="essen">Essen (Ernährung/Unverträglichkeiten)</label>
                <input type="text" id="essen" name="essen" value="<?= e($member['essen']) ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="jersey_groesse">Game Jersey Größe</label>
                    <input type="text" id="jersey_groesse" name="jersey_groesse" value="<?= e($member['jersey_groesse']) ?>">
                </div>
                <div class="form-group">
                    <label for="hosen_groesse">Game Hosen Größe</label>
                    <input type="text" id="hosen_groesse" name="hosen_groesse" value="<?= e($member['hosen_groesse']) ?>">
                </div>
                <div class="form-group">
                    <label for="mesh_shorts_groesse">Mesh Shorts Größe</label>
                    <input type="text" id="mesh_shorts_groesse" name="mesh_shorts_groesse" value="<?= e($member['mesh_shorts_groesse']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="helm_groesse">Helm Größe</label>
                    <input type="text" id="helm_groesse" name="helm_groesse" value="<?= e($member['helm_groesse']) ?>">
                </div>
                <div class="form-group">
                    <label for="helm_modell">Helm verwendest du (Modell)</label>
                    <input type="text" id="helm_modell" name="helm_modell" value="<?= e($member['helm_modell']) ?>" placeholder="z. B. Riddell Speedflex">
                </div>
                <div class="form-group">
                    <label for="socken_groesse">Socken Größe</label>
                    <input type="text" id="socken_groesse" name="socken_groesse" value="<?= e($member['socken_groesse']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="tshirt_polo_groesse">T-Shirt & Polo Größe (MACRON)</label>
                    <input type="text" id="tshirt_polo_groesse" name="tshirt_polo_groesse" value="<?= e($member['tshirt_polo_groesse']) ?>">
                </div>
                <div class="form-group">
                    <label for="hoodie_groesse">Hoodie Größe (MACRON)</label>
                    <input type="text" id="hoodie_groesse" name="hoodie_groesse" value="<?= e($member['hoodie_groesse']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="helm_vorhanden">Eigener Helm vorhanden</label>
                <select id="helm_vorhanden" name="helm_vorhanden">
                    <option value="">– bitte wählen –</option>
                    <option value="ja" <?= $member['helm_vorhanden'] === 'ja' ? 'selected' : '' ?>>Ja</option>
                    <option value="nein" <?= $member['helm_vorhanden'] === 'nein' ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
        </fieldset>

        <fieldset>
            <legend>Sonstige Angaben</legend>
            <div class="form-group">
                <label for="nada_kurs_datum">NADA-Kurs absolviert am</label>
                <input type="date" id="nada_kurs_datum" name="nada_kurs_datum" value="<?= e(formatDateForInput($member['nada_kurs_datum'])) ?>">
            </div>
            <div class="form-group">
                <label for="allergien">Allergien</label>
                <textarea id="allergien" name="allergien" rows="3"><?= e($member['allergien']) ?></textarea>
            </div>
        </fieldset>

        <fieldset>
            <legend>Einwilligungen</legend>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="rechte_pflichten_akzeptiert" value="1" required <?= $member['rechte_pflichten_akzeptiert_at'] ? 'checked' : '' ?>>
                    Ich akzeptiere die Rechte & Pflichten des Vereins. *
                </label>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="bild_einverstaendnis_akzeptiert" value="1" <?= $member['bild_einverstaendnis_akzeptiert_at'] ? 'checked' : '' ?>>
                    Ich bin damit einverstanden, dass Foto-/Videoaufnahmen von mir für Vereinszwecke
                    (z. B. Homepage, Social Media) verwendet werden. (optional, jederzeit widerrufbar)
                </label>
            </div>
        </fieldset>

        <button type="submit" class="btn btn-primary">Bestätigen & Speichern</button>
    </form>

    <p class="privacy-note">
        Hinweis zum Datenschutz: Deine Angaben werden ausschließlich zur Mitgliederverwaltung
        des Vereins verarbeitet und nicht an Dritte weitergegeben. Details dazu findest du in
        der Datenschutzerklärung des Vereins. Die Sozialversicherungsnummer und Reisepassdaten
        werden ausschließlich für die Anmeldung zu Meisterschaften/Turnieren sowie ggf. für
        Reisebuchungen bei internationalen Spielen verwendet.
    </p>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
