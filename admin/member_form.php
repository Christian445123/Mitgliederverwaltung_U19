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
    'passnummer' => '', 'name_laut_pass' => '', 'allergien' => '', 'nada_kurs_datum' => '',
    'beitrittsdatum' => date('Y-m-d'), 'status' => 'aktiv',
    'spielernummer' => '', 'sz' => '', 'bezirk' => '', 'spielposition' => '', 'herkunftsverein' => '',
    'koerpergroesse_cm' => '', 'gewicht_kg' => '',
    'camp_1' => '', 'camp_2' => '', 'camp_spanien' => '', 'camp_tschechien' => '',
    'nada_zertifikat_gueltig_bis' => '', 'nada_erlaubnis_gueltig_bis' => '',
    'rechte_pflichten_akzeptiert_at' => null, 'bild_einverstaendnis_akzeptiert_at' => null,
    'dokument_typ' => '',
    'sozialversicherungsnummer' => '',
    'geburtsland' => '', 'geburtsort' => '',
    'reisepass_nr' => '', 'reisepass_ausgestellt_am' => '', 'reisepass_gueltig_bis' => '', 'reisepass_ausstellungsbehoerde' => '',
    'pass_foto_pfad' => null,
    'essen' => '', 'jersey_groesse' => '', 'hosen_groesse' => '', 'mesh_shorts_groesse' => '',
    'helm_groesse' => '', 'helm_modell' => '', 'tshirt_polo_groesse' => '', 'hoodie_groesse' => '',
    'socken_groesse' => '', 'helm_vorhanden' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM members WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['flash'] = 'Mitglied wurde nicht gefunden.';
        redirect('index.php');
    }
    $member = array_merge($member, $existing);
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
        $member['passnummer'] = trim($_POST['passnummer'] ?? '');
        $member['name_laut_pass'] = trim($_POST['name_laut_pass'] ?? '');
        $member['allergien'] = trim($_POST['allergien'] ?? '');
        $member['nada_kurs_datum'] = trim($_POST['nada_kurs_datum'] ?? '');
        $member['beitrittsdatum'] = trim($_POST['beitrittsdatum'] ?? '');
        $member['status'] = ($_POST['status'] ?? 'aktiv') === 'inaktiv' ? 'inaktiv' : 'aktiv';

        $member['spielernummer'] = trim($_POST['spielernummer'] ?? '');
        $member['sz'] = trim($_POST['sz'] ?? '');
        $member['bezirk'] = trim($_POST['bezirk'] ?? '');
        $member['spielposition'] = trim($_POST['position'] ?? '');
        $member['herkunftsverein'] = trim($_POST['herkunftsverein'] ?? '');
        $member['koerpergroesse_cm'] = trim($_POST['koerpergroesse_cm'] ?? '');
        $member['gewicht_kg'] = trim($_POST['gewicht_kg'] ?? '');
        $member['camp_1'] = trim($_POST['camp_1'] ?? '');
        $member['camp_2'] = trim($_POST['camp_2'] ?? '');
        $member['camp_spanien'] = trim($_POST['camp_spanien'] ?? '');
        $member['camp_tschechien'] = trim($_POST['camp_tschechien'] ?? '');
        $member['nada_zertifikat_gueltig_bis'] = trim($_POST['nada_zertifikat_gueltig_bis'] ?? '');
        $member['nada_erlaubnis_gueltig_bis'] = trim($_POST['nada_erlaubnis_gueltig_bis'] ?? '');
        $member['dokument_typ'] = trim($_POST['dokument_typ'] ?? '');
        $member['geburtsland'] = trim($_POST['geburtsland'] ?? '');
        $member['geburtsort'] = trim($_POST['geburtsort'] ?? '');
        $member['reisepass_nr'] = trim($_POST['reisepass_nr'] ?? '');
        $member['reisepass_ausgestellt_am'] = trim($_POST['reisepass_ausgestellt_am'] ?? '');
        $member['reisepass_gueltig_bis'] = trim($_POST['reisepass_gueltig_bis'] ?? '');
        $member['reisepass_ausstellungsbehoerde'] = trim($_POST['reisepass_ausstellungsbehoerde'] ?? '');
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

        // SVNR: Feld bleibt maskiert, nur bei expliziter Neueingabe wird der Wert geändert.
        $newSvnr = trim($_POST['sozialversicherungsnummer_neu'] ?? '');
        if ($newSvnr !== '') {
            $member['sozialversicherungsnummer'] = $newSvnr;
        }

        // Einwilligungen: Zeitstempel setzen/löschen, je nachdem ob die Checkbox aktiv ist.
        $member['rechte_pflichten_akzeptiert_at'] = !empty($_POST['rechte_pflichten_akzeptiert'])
            ? ($member['rechte_pflichten_akzeptiert_at'] ?: date('Y-m-d H:i:s'))
            : null;
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
            $errors[] = 'E-Mail ist ein Pflichtfeld (wird für den Zugriff auf den Verifizierungs-Link benötigt).';
        } elseif (!filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Mitglieds ist ungültig.';
        }
        if ($member['erziehungsberechtigter_email'] !== '' && !filter_var($member['erziehungsberechtigter_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Die E-Mail-Adresse des Erziehungsberechtigten ist ungültig.';
        }
        foreach (['geburtsdatum', 'beitrittsdatum', 'nada_kurs_datum', 'nada_zertifikat_gueltig_bis',
                  'nada_erlaubnis_gueltig_bis', 'reisepass_ausgestellt_am', 'reisepass_gueltig_bis'] as $dateField) {
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

        // Für neue Mitglieder wird der Upload erst NACH dem Insert verarbeitet (siehe unten),
        // da der Dateiname die künftige ID enthält und move_uploaded_file() nur einmal pro
        // Request funktioniert - hier also nur für die Bearbeitung eines bestehenden Mitglieds.
        $uploadedPhoto = null;
        if (!$errors && $isEdit && !empty($_FILES['pass_foto']['name'])) {
            try {
                $uploadedPhoto = handlePassFotoUpload($_FILES['pass_foto'], $id);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
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
                'passnummer' => $member['passnummer'],
                'name_laut_pass' => $member['name_laut_pass'],
                'allergien' => $member['allergien'],
                'nada_kurs_datum' => $member['nada_kurs_datum'] ?: null,
                'beitrittsdatum' => $member['beitrittsdatum'] ?: null,
                'status' => $member['status'],
                'spielernummer' => $member['spielernummer'],
                'sz' => $member['sz'],
                'bezirk' => $member['bezirk'],
                'spielposition' => $member['spielposition'],
                'herkunftsverein' => $member['herkunftsverein'],
                'koerpergroesse_cm' => $member['koerpergroesse_cm'] !== '' ? (int) $member['koerpergroesse_cm'] : null,
                'gewicht_kg' => $member['gewicht_kg'] !== '' ? (int) $member['gewicht_kg'] : null,
                'camp_1' => $member['camp_1'],
                'camp_2' => $member['camp_2'],
                'camp_spanien' => $member['camp_spanien'],
                'camp_tschechien' => $member['camp_tschechien'],
                'nada_zertifikat_gueltig_bis' => $member['nada_zertifikat_gueltig_bis'] ?: null,
                'nada_erlaubnis_gueltig_bis' => $member['nada_erlaubnis_gueltig_bis'] ?: null,
                'rechte_pflichten_akzeptiert_at' => $member['rechte_pflichten_akzeptiert_at'],
                'bild_einverstaendnis_akzeptiert_at' => $member['bild_einverstaendnis_akzeptiert_at'],
                'dokument_typ' => $member['dokument_typ'],
                'sozialversicherungsnummer' => $member['sozialversicherungsnummer'],
                'geburtsland' => $member['geburtsland'],
                'geburtsort' => $member['geburtsort'],
                'reisepass_nr' => $member['reisepass_nr'],
                'reisepass_ausgestellt_am' => $member['reisepass_ausgestellt_am'] ?: null,
                'reisepass_gueltig_bis' => $member['reisepass_gueltig_bis'] ?: null,
                'reisepass_ausstellungsbehoerde' => $member['reisepass_ausstellungsbehoerde'],
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
            ];

            if ($isEdit) {
                $params = $baseParams;
                $params['id'] = $id;
                if ($uploadedPhoto) {
                    deletePassFoto($member['pass_foto_pfad'] ?? null);
                    $params['pass_foto_pfad'] = $uploadedPhoto;
                } else {
                    $params['pass_foto_pfad'] = $member['pass_foto_pfad'];
                }
                $sql = 'UPDATE members SET vorname = :vorname, nachname = :nachname, geburtsdatum = :geburtsdatum,
                        strasse = :strasse, plz = :plz, ort = :ort, email = :email, telefon = :telefon,
                        erziehungsberechtigter = :erziehungsberechtigter,
                        erziehungsberechtigter_email = :erziehungsberechtigter_email,
                        erziehungsberechtigter_telefon = :erziehungsberechtigter_telefon,
                        passnummer = :passnummer, name_laut_pass = :name_laut_pass,
                        allergien = :allergien, nada_kurs_datum = :nada_kurs_datum,
                        beitrittsdatum = :beitrittsdatum, status = :status,
                        spielernummer = :spielernummer, sz = :sz, bezirk = :bezirk, spielposition = :spielposition,
                        herkunftsverein = :herkunftsverein, koerpergroesse_cm = :koerpergroesse_cm,
                        gewicht_kg = :gewicht_kg,
                        camp_1 = :camp_1, camp_2 = :camp_2, camp_spanien = :camp_spanien, camp_tschechien = :camp_tschechien,
                        nada_zertifikat_gueltig_bis = :nada_zertifikat_gueltig_bis,
                        nada_erlaubnis_gueltig_bis = :nada_erlaubnis_gueltig_bis,
                        rechte_pflichten_akzeptiert_at = :rechte_pflichten_akzeptiert_at,
                        bild_einverstaendnis_akzeptiert_at = :bild_einverstaendnis_akzeptiert_at,
                        dokument_typ = :dokument_typ,
                        sozialversicherungsnummer = :sozialversicherungsnummer,
                        geburtsland = :geburtsland, geburtsort = :geburtsort,
                        reisepass_nr = :reisepass_nr, reisepass_ausgestellt_am = :reisepass_ausgestellt_am,
                        reisepass_gueltig_bis = :reisepass_gueltig_bis,
                        reisepass_ausstellungsbehoerde = :reisepass_ausstellungsbehoerde,
                        pass_foto_pfad = :pass_foto_pfad,
                        essen = :essen, jersey_groesse = :jersey_groesse, hosen_groesse = :hosen_groesse,
                        mesh_shorts_groesse = :mesh_shorts_groesse, helm_groesse = :helm_groesse, helm_modell = :helm_modell,
                        tshirt_polo_groesse = :tshirt_polo_groesse, hoodie_groesse = :hoodie_groesse,
                        socken_groesse = :socken_groesse,
                        helm_vorhanden = :helm_vorhanden
                        WHERE id = :id';
                $pdo->prepare($sql)->execute($params);
                $_SESSION['flash'] = 'Mitglied wurde aktualisiert.';
                redirect('index.php');
            } else {
                $accessPassword = generateAccessPassword();

                $params = $baseParams;
                $params['mitgliedsnummer'] = generateMitgliedsnummer($pdo);
                $params['verify_token'] = generateVerifyToken();
                $params['access_password_hash'] = password_hash($accessPassword, PASSWORD_DEFAULT);
                $params['pass_foto_pfad'] = null; // Foto wird nach dem Insert nachgetragen (Dateiname enthält die neue ID)
                $sql = 'INSERT INTO members
                        (mitgliedsnummer, vorname, nachname, geburtsdatum, strasse, plz, ort, email, telefon,
                         erziehungsberechtigter, erziehungsberechtigter_email, erziehungsberechtigter_telefon,
                         passnummer, name_laut_pass, allergien, nada_kurs_datum,
                         beitrittsdatum, status, verify_token, access_password_hash,
                         spielernummer, sz, bezirk, spielposition, herkunftsverein, koerpergroesse_cm, gewicht_kg,
                         camp_1, camp_2, camp_spanien, camp_tschechien,
                         nada_zertifikat_gueltig_bis, nada_erlaubnis_gueltig_bis,
                         rechte_pflichten_akzeptiert_at, bild_einverstaendnis_akzeptiert_at, dokument_typ,
                         sozialversicherungsnummer, geburtsland, geburtsort,
                         reisepass_nr, reisepass_ausgestellt_am, reisepass_gueltig_bis, reisepass_ausstellungsbehoerde,
                         pass_foto_pfad, essen, jersey_groesse, hosen_groesse, mesh_shorts_groesse, helm_groesse, helm_modell,
                         tshirt_polo_groesse, hoodie_groesse, socken_groesse, helm_vorhanden)
                        VALUES
                        (:mitgliedsnummer, :vorname, :nachname, :geburtsdatum, :strasse, :plz, :ort, :email, :telefon,
                         :erziehungsberechtigter, :erziehungsberechtigter_email, :erziehungsberechtigter_telefon,
                         :passnummer, :name_laut_pass, :allergien, :nada_kurs_datum,
                         :beitrittsdatum, :status, :verify_token, :access_password_hash,
                         :spielernummer, :sz, :bezirk, :spielposition, :herkunftsverein, :koerpergroesse_cm, :gewicht_kg,
                         :camp_1, :camp_2, :camp_spanien, :camp_tschechien,
                         :nada_zertifikat_gueltig_bis, :nada_erlaubnis_gueltig_bis,
                         :rechte_pflichten_akzeptiert_at, :bild_einverstaendnis_akzeptiert_at, :dokument_typ,
                         :sozialversicherungsnummer, :geburtsland, :geburtsort,
                         :reisepass_nr, :reisepass_ausgestellt_am, :reisepass_gueltig_bis, :reisepass_ausstellungsbehoerde,
                         :pass_foto_pfad, :essen, :jersey_groesse, :hosen_groesse, :mesh_shorts_groesse, :helm_groesse, :helm_modell,
                         :tshirt_polo_groesse, :hoodie_groesse, :socken_groesse, :helm_vorhanden)';
                $pdo->prepare($sql)->execute($params);
                $newId = (int) $pdo->lastInsertId();

                if (!empty($_FILES['pass_foto']['name'])) {
                    try {
                        $photo = handlePassFotoUpload($_FILES['pass_foto'], $newId);
                        if ($photo) {
                            $pdo->prepare('UPDATE members SET pass_foto_pfad = :p WHERE id = :id')
                                ->execute(['p' => $photo, 'id' => $newId]);
                        }
                    } catch (RuntimeException $e) {
                        // Mitglied wurde bereits angelegt; Foto kann nachträglich über "Bearbeiten" ergänzt werden.
                        $_SESSION['flash'] = 'Mitglied wurde angelegt, Foto-Upload ist aber fehlgeschlagen: ' . $e->getMessage();
                    }
                }

                if (empty($_SESSION['flash'])) {
                    $_SESSION['flash'] = 'Mitglied wurde angelegt. Link und Zugangscode können nun weitergegeben werden.';
                }
                $_SESSION['generated_password'] = $accessPassword;
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

<?php
$expiryStatusColors = ['red' => 'red', 'blue' => 'blue', 'yellow' => 'orange'];
$expiryStatusLabels = ['red' => 'Abgelaufen', 'blue' => 'Bald fällig', 'yellow' => 'Bald fällig'];
function expiryBadge(string $status, array $colors, array $labels): string
{
    if ($status === '' || $status === 'ok' || !isset($colors[$status])) {
        return '';
    }
    return ' <span class="badge badge-' . $colors[$status] . '">' . e($labels[$status]) . '</span>';
}
?>
<form method="post" action="member_form.php<?= $isEdit ? '?id=' . (int) $id : '' ?>" class="member-form" enctype="multipart/form-data" novalidate>
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
        <div class="form-row">
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
        <legend>Team & Spielbetrieb</legend>
        <div class="form-row">
            <div class="form-group form-group-small">
                <label for="spielernummer">Jersey-Nr.</label>
                <input type="text" id="spielernummer" name="spielernummer" value="<?= e($member['spielernummer']) ?>">
            </div>
            <div class="form-group form-group-small">
                <label for="sz">SZ</label>
                <input type="text" id="sz" name="sz" value="<?= e($member['sz']) ?>">
            </div>
            <div class="form-group form-group-small">
                <label for="bezirk">Bez.</label>
                <input type="text" id="bezirk" name="bezirk" value="<?= e($member['bezirk']) ?>">
            </div>
            <div class="form-group">
                <label for="position">Position</label>
                <input type="text" id="position" name="position" value="<?= e($member['spielposition']) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="herkunftsverein">Herkunftsverein</label>
            <input type="text" id="herkunftsverein" name="herkunftsverein" value="<?= e($member['herkunftsverein']) ?>">
        </div>
    </fieldset>

    <fieldset>
        <legend>Camp-/Turnier-Teilnahmen</legend>
        <div class="form-row">
            <div class="form-group form-group-small">
                <label for="camp_1">Camp 1</label>
                <input type="text" id="camp_1" name="camp_1" value="<?= e($member['camp_1']) ?>" placeholder="X">
            </div>
            <div class="form-group form-group-small">
                <label for="camp_2">Camp 2</label>
                <input type="text" id="camp_2" name="camp_2" value="<?= e($member['camp_2']) ?>" placeholder="X">
            </div>
            <div class="form-group form-group-small">
                <label for="camp_spanien">Spanien</label>
                <input type="text" id="camp_spanien" name="camp_spanien" value="<?= e($member['camp_spanien']) ?>" placeholder="X">
            </div>
            <div class="form-group form-group-small">
                <label for="camp_tschechien">Tschechien</label>
                <input type="text" id="camp_tschechien" name="camp_tschechien" value="<?= e($member['camp_tschechien']) ?>" placeholder="X">
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Zertifikate</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="nada_zertifikat_gueltig_bis">NADA-Zertifikat gültig bis<?= expiryBadge(nadaDateStatus($member['nada_zertifikat_gueltig_bis']), $expiryStatusColors, $expiryStatusLabels) ?></label>
                <input type="date" id="nada_zertifikat_gueltig_bis" name="nada_zertifikat_gueltig_bis" value="<?= e(formatDateForInput($member['nada_zertifikat_gueltig_bis'])) ?>">
            </div>
            <div class="form-group">
                <label for="nada_erlaubnis_gueltig_bis">NADA-Erlaubnis gültig bis<?= expiryBadge(nadaDateStatus($member['nada_erlaubnis_gueltig_bis']), $expiryStatusColors, $expiryStatusLabels) ?></label>
                <input type="date" id="nada_erlaubnis_gueltig_bis" name="nada_erlaubnis_gueltig_bis" value="<?= e(formatDateForInput($member['nada_erlaubnis_gueltig_bis'])) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="nada_kurs_datum">NADA-Kurs absolviert am</label>
            <input type="date" id="nada_kurs_datum" name="nada_kurs_datum" value="<?= e(formatDateForInput($member['nada_kurs_datum'])) ?>">
        </div>
    </fieldset>

    <fieldset>
        <legend>Reisedokumente</legend>
        <div class="form-row">
            <div class="form-group">
                <label for="dokument_typ">Ausweistyp (Bild E-Card)</label>
                <input type="text" id="dokument_typ" name="dokument_typ" value="<?= e($member['dokument_typ']) ?>" placeholder="z. B. ECard, Personalausweis">
            </div>
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
                <label for="reisepass_gueltig_bis">Reisepass gültig bis<?= expiryBadge(passportDateStatus($member['reisepass_gueltig_bis']), $expiryStatusColors, $expiryStatusLabels) ?></label>
                <input type="date" id="reisepass_gueltig_bis" name="reisepass_gueltig_bis" value="<?= e(formatDateForInput($member['reisepass_gueltig_bis'])) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="sozialversicherungsnummer_neu">
                Sozialversicherungsnummer
                <?php if (!empty($member['sozialversicherungsnummer'])): ?>
                    (aktuell hinterlegt: <?= e(maskSvnr($member['sozialversicherungsnummer'])) ?>)
                <?php endif; ?>
            </label>
            <input type="text" id="sozialversicherungsnummer_neu" name="sozialversicherungsnummer_neu"
                   placeholder="Nur ausfüllen, um die SVNR zu ändern" autocomplete="off">
        </div>
        <div class="form-group">
            <label for="pass_foto">Passfoto <?= !empty($member['pass_foto_pfad']) ? '(bereits vorhanden – Auswahl ersetzt es)' : '' ?></label>
            <?php if (!empty($member['pass_foto_pfad']) && $isEdit): ?>
                <p><img src="member_photo.php?id=<?= (int) $id ?>" alt="Aktuelles Passfoto" style="max-height:120px;border-radius:4px;display:block;margin-bottom:8px;"></p>
            <?php endif; ?>
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
            <div class="form-group">
                <label for="helm_vorhanden">Helm vorhanden</label>
                <select id="helm_vorhanden" name="helm_vorhanden">
                    <option value="">– bitte wählen –</option>
                    <option value="ja" <?= $member['helm_vorhanden'] === 'ja' ? 'selected' : '' ?>>Ja</option>
                    <option value="nein" <?= $member['helm_vorhanden'] === 'nein' ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Einwilligungen</legend>
        <div class="form-group">
            <label>
                <input type="checkbox" name="rechte_pflichten_akzeptiert" value="1" <?= $member['rechte_pflichten_akzeptiert_at'] ? 'checked' : '' ?>>
                Rechte & Pflichten wurden akzeptiert
                <?php if ($member['rechte_pflichten_akzeptiert_at']): ?>
                    <span class="badge badge-gray">seit <?= e(date('d.m.Y', strtotime($member['rechte_pflichten_akzeptiert_at']))) ?></span>
                <?php endif; ?>
            </label>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="bild_einverstaendnis_akzeptiert" value="1" <?= $member['bild_einverstaendnis_akzeptiert_at'] ? 'checked' : '' ?>>
                Einverständnis zur Bildnutzung liegt vor
                <?php if ($member['bild_einverstaendnis_akzeptiert_at']): ?>
                    <span class="badge badge-gray">seit <?= e(date('d.m.Y', strtotime($member['bild_einverstaendnis_akzeptiert_at']))) ?></span>
                <?php endif; ?>
            </label>
        </div>
        <div class="form-group">
            <label for="allergien">Allergien</label>
            <textarea id="allergien" name="allergien" rows="3"><?= e($member['allergien']) ?></textarea>
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
