<?php
/**
 * Mitglieder-Massenimport aus CSV/TXT (Komma-, Semikolon- oder Tab-getrennt).
 * Ablauf: 1) Datei hochladen  2) Spalten den DB-Feldern zuordnen  3) Import
 * mit Ergebnis-/Fehlerliste. Legt für jede Zeile ein neues Mitglied an
 * (kein Abgleich mit bestehenden Mitgliedern) inkl. Zugangscode wie beim
 * normalen Anlegen über member_form.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireAdministrator();

$pdo = getDb();

/** Ziel-Felder für die Zuordnung, gruppiert für die Anzeige. */
function importableMemberFields(): array
{
    return [
        'Persönliche Daten' => [
            'vorname' => 'Vorname *',
            'nachname' => 'Nachname *',
            'geburtsdatum' => 'Geburtsdatum (Datum)',
            'geburtsland' => 'Geburtsland',
            'geburtsort' => 'Geburtsort',
            'koerpergroesse_cm' => 'Körpergröße (cm)',
            'gewicht_kg' => 'Gewicht (kg)',
        ],
        'Kontakt & Adresse' => [
            'email' => 'E-Mail',
            'telefon' => 'Telefon',
            'strasse' => 'Straße',
            'plz' => 'PLZ',
            'ort' => 'Ort',
        ],
        'Erziehungsberechtigte/r' => [
            'erziehungsberechtigter' => 'Name',
            'erziehungsberechtigter_email' => 'E-Mail',
            'erziehungsberechtigter_telefon' => 'Telefon',
        ],
        'Team & Spielbetrieb' => [
            'spielernummer' => 'Jersey-Nr.',
            'sz' => 'SZ',
            'bezirk' => 'Bez.',
            'spielposition' => 'Position',
            'herkunftsverein' => 'Verein',
            'beitrittsdatum' => 'Beitrittsdatum (Datum)',
        ],
        'Camp-/Turnier-Teilnahmen' => [
            'camp_1' => 'Camp 1',
            'camp_2' => 'Camp 2',
            'camp_spanien' => 'Spanien',
            'camp_tschechien' => 'Tschechien',
        ],
        'Zertifikate & Einwilligungen' => [
            'nada_kurs_datum' => 'NADA-Kurs absolviert am (Datum)',
            'nada_zertifikat_gueltig_bis' => 'Nada gültig bis (Datum)',
            'rechte_pflichten_akzeptiert_at' => 'Rechte + Pflichten (akzeptiert, falls Zelle ausgefüllt)',
        ],
        'Ausweis & Reisedokumente' => [
            'dokument_typ' => 'Bild E-Card (Ausweistyp)',
            'sozialversicherungsnummer' => 'Sozial Ver. Nr.',
            'passnummer' => 'Sport-Passnummer',
            'name_laut_pass' => 'Name laut Pass',
            'reisepass_nr' => 'Reisepass Nr',
            'reisepass_ausgestellt_am' => 'Reisepass ausgestellt am (Datum)',
            'reisepass_gueltig_bis' => 'Reisepass gültig bis (Datum)',
            'reisepass_ausstellungsbehoerde' => 'Ausstellungsbehörde',
        ],
        'Verpflegung & Ausrüstung' => [
            'allergien' => 'Allergien',
            'essen' => 'Essen (Ernährung)',
            'jersey_groesse' => 'Game Jersey Grösse',
            'hosen_groesse' => 'Game Hosen Grösse',
            'mesh_shorts_groesse' => 'Mesh Shorts Grösse',
            'helm_groesse' => 'Helm Grösse',
            'helm_modell' => 'Helm verwendest du (Modell)',
            'tshirt_polo_groesse' => 'T-Shirt & Polo Grösse',
            'hoodie_groesse' => 'Hoodie Grösse',
            'socken_groesse' => 'Socken Grösse',
        ],
    ];
}

function importDateFields(): array
{
    return [
        'geburtsdatum', 'beitrittsdatum', 'nada_kurs_datum', 'nada_zertifikat_gueltig_bis',
        'nada_erlaubnis_gueltig_bis', 'reisepass_ausgestellt_am', 'reisepass_gueltig_bis',
    ];
}

/** Erkennt TT.MM.JJJJ oder bereits vorhandenes JJJJ-MM-TT, sonst null. */
function parseImportDate(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    return null;
}

function detectDelimiter(string $line): string
{
    $candidates = [
        "\t" => substr_count($line, "\t"),
        ';' => substr_count($line, ';'),
        ',' => substr_count($line, ','),
    ];
    arsort($candidates);
    $best = array_key_first($candidates);
    return $candidates[$best] > 0 ? $best : ',';
}

/**
 * Schlägt anhand eines Spaltentitels ein passendes Ziel-Feld vor.
 * Erst exakter Abgleich mit den bekannten Original-Spaltennamen (AFBÖ-Sheet),
 * danach eine grobe Stichwortsuche als Fallback für abweichende Exporte.
 */
function guessFieldFromHeader(string $header): ?string
{
    $h = mb_strtolower(trim($header));

    // Exakte Spaltennamen aus dem Original-Excel-Sheet
    $exact = [
        'jersy nr.' => 'spielernummer',
        'jersey nr.' => 'spielernummer',
        'camp 1' => 'camp_1',
        'camp 2' => 'camp_2',
        'spanien' => 'camp_spanien',
        'tschechien' => 'camp_tschechien',
        'nachname' => 'nachname',
        'vorname' => 'vorname',
        'name& vorname' => null, // nur berechnete Anzeige-Spalte, keine eigenen Daten
        'sz' => 'sz',
        'bez.' => 'bezirk',
        'position' => 'spielposition',
        'geburts-datum' => 'geburtsdatum',
        'geburtsdatum' => 'geburtsdatum',
        'verein' => 'herkunftsverein',
        'cm' => 'koerpergroesse_cm',
        'kg' => 'gewicht_kg',
        'telfon' => 'telefon',
        'telefon' => 'telefon',
        'mail' => 'email',
        'name erziehungsberechtigter' => 'erziehungsberechtigter',
        'telefon erzieh' => 'erziehungsberechtigter_telefon',
        'mail erzieh' => 'erziehungsberechtigter_email',
        'rechte + pflichten' => 'rechte_pflichten_akzeptiert_at',
        'bild e-card' => 'dokument_typ',
        'sozial ver. nr.' => 'sozialversicherungsnummer',
        'nada zertifikat' => null, // nur Label-Spalte ("NADA"), kein eigenes Feld
        'nada gültig bis' => 'nada_zertifikat_gueltig_bis',
        'pass foto' => null, // nur Label-Spalte ("Pass"), kein eigenes Feld
        'reisepass nr' => 'reisepass_nr',
        'reisepass ausgestellt am' => 'reisepass_ausgestellt_am',
        'reisepass gültig bis' => 'reisepass_gueltig_bis',
        'geburtsland' => 'geburtsland',
        'geburtsort' => 'geburtsort',
        'ausstellungsbehörde' => 'reisepass_ausstellungsbehoerde',
        'plz' => 'plz',
        'ort' => 'ort',
        'straße' => 'strasse',
        'essen' => 'essen',
        'game jersey grösse (macron)' => 'jersey_groesse',
        'game hosen grösse (macron)' => 'hosen_groesse',
        'helm grösse' => 'helm_groesse',
        'helm verwendest du' => 'helm_modell',
        't-shirt & polo grösse (macron)' => 'tshirt_polo_groesse',
        'hoodie grösse (macron)' => 'hoodie_groesse',
        'mesh shorts grösse (macron)' => 'mesh_shorts_groesse',
        'socken grösse' => 'socken_groesse',
    ];
    if (array_key_exists($h, $exact)) {
        return $exact[$h];
    }

    // Grobe Stichwortsuche als Fallback (z. B. bei leicht abweichenden Exporten)
    $fuzzy = [
        'nachname' => 'nachname',
        'vorname' => 'vorname',
        'geburtsdatum' => 'geburtsdatum',
        'geburtsland' => 'geburtsland',
        'geburtsort' => 'geburtsort',
        'verein' => 'herkunftsverein',
        'position' => 'spielposition',
        'mail erzie' => 'erziehungsberechtigter_email',
        'telefon erzie' => 'erziehungsberechtigter_telefon',
        'erziehungsberechtigter' => 'erziehungsberechtigter',
        'mail' => 'email',
        'e-mail' => 'email',
        'telefon' => 'telefon',
        'plz' => 'plz',
        'straße' => 'strasse',
        'strasse' => 'strasse',
        'allergi' => 'allergien',
        'essen' => 'essen',
        'nada' => 'nada_kurs_datum',
        'sozialversicherung' => 'sozialversicherungsnummer',
        'reisepass nr' => 'reisepass_nr',
        'ausgestellt' => 'reisepass_ausgestellt_am',
        'gültig bis' => 'reisepass_gueltig_bis',
        'ablaufsdatum' => 'reisepass_gueltig_bis',
        'ausstellungsbehörde' => 'reisepass_ausstellungsbehoerde',
        'passnummer' => 'passnummer',
        'jersey' => 'jersey_groesse',
        'hosen' => 'hosen_groesse',
        'shorts' => 'mesh_shorts_groesse',
        'socken' => 'socken_groesse',
        'helm verwendest' => 'helm_modell',
        'helm größe' => 'helm_groesse',
        'helm grösse' => 'helm_groesse',
        'helm' => 'helm_groesse',
        't-shirt' => 'tshirt_polo_groesse',
        'polo' => 'tshirt_polo_groesse',
        'hoodie' => 'hoodie_groesse',
        'beitritt' => 'beitrittsdatum',
    ];
    foreach ($fuzzy as $needle => $field) {
        if (str_contains($h, $needle)) {
            return $field;
        }
    }
    return null;
}

$importDir = __DIR__ . '/../data/imports';
$importDirError = null;
if (!is_dir($importDir) && !@mkdir($importDir, 0755, true) && !is_dir($importDir)) {
    $importDirError = "Verzeichnis „data/imports“ konnte nicht angelegt werden (Schreibrechte auf "
        . "„data/“ prüfen, z. B. `chmod 775 data` bzw. passenden Besitzer setzen).";
} else {
    // Alte, abgebrochene Import-Sitzungen (älter als 1 Stunde) aufräumen
    foreach (glob($importDir . '/*.json') ?: [] as $oldFile) {
        if (filemtime($oldFile) < time() - 3600) {
            unlink($oldFile);
        }
    }
}

$step = $_POST['step'] ?? 'upload';
$errors = [];
$preview = null;
$importResult = null;

if ($importDirError) {
    $errors[] = $importDirError;
    $step = 'upload';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$importDirError && !verifyCsrf()) {
    $errors[] = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    $step = 'upload';
}

// --- Schritt 1 -> 2: Datei hochladen, parsen, Mapping-Formular vorbereiten ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors && $step === 'parse') {
    if (empty($_FILES['import_file']['name']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Bitte eine CSV- oder TXT-Datei auswählen.';
    } else {
        $content = (string) file_get_contents($_FILES['import_file']['tmp_name']);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // UTF-8 BOM entfernen
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_values(array_filter($lines, fn($l) => trim($l) !== ''));

        if (count($lines) < 1) {
            $errors[] = 'Die Datei enthält keine Daten.';
        } else {
            $delimiterChoice = $_POST['delimiter'] ?? 'auto';
            $delimiterMap = ['semicolon' => ';', 'comma' => ',', 'tab' => "\t"];
            $delimiter = $delimiterMap[$delimiterChoice] ?? detectDelimiter($lines[0]);
            $hasHeader = !empty($_POST['has_header']);
            $rows = array_map(fn($l) => str_getcsv($l, $delimiter, '"', ''), $lines);
            $header = $hasHeader ? array_shift($rows) : null;

            if (!$rows) {
                $errors[] = 'Nach Abzug der Kopfzeile sind keine Datenzeilen mehr übrig.';
            } elseif (!is_writable($importDir)) {
                $errors[] = "Verzeichnis „data/imports“ ist auf dem Server nicht beschreibbar. "
                    . "Bitte Schreibrechte für den Webserver-Benutzer setzen (z. B. `chmod 775 data data/imports` "
                    . "bzw. `chown` auf den richtigen Benutzer) und erneut versuchen.";
            } else {
                $token = bin2hex(random_bytes(16));
                $written = file_put_contents($importDir . '/' . $token . '.json', json_encode([
                    'header' => $header,
                    'rows' => $rows,
                ]));

                if ($written === false) {
                    $errors[] = 'Die hochgeladenen Daten konnten nicht zwischengespeichert werden (Schreibfehler in data/imports). Bitte Verzeichnisrechte auf dem Server prüfen.';
                } else {
                    $columnCount = max(array_map('count', $rows));
                    $preview = [
                        'token' => $token,
                        'header' => $header,
                        'sample' => array_slice($rows, 0, 3),
                        'rows' => $rows,
                        'columnCount' => $columnCount,
                        'rowCount' => count($rows),
                    ];
                    $step = 'mapping';
                }
            }
        }
    }
}

// --- Schritt 2 -> 3: Mapping anwenden, Mitglieder anlegen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors && $step === 'import') {
    $token = preg_replace('/[^a-f0-9]/', '', $_POST['token'] ?? '');
    $path = $importDir . '/' . $token . '.json';

    $data = $token !== '' && is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    if (!is_array($data) || !isset($data['rows']) || !is_array($data['rows'])) {
        $errors[] = 'Die Import-Sitzung ist abgelaufen, beschädigt oder ungültig. Bitte Datei erneut hochladen.';
        $step = 'upload';
        if ($token !== '' && is_file($path)) {
            unlink($path); // beschädigte/leere Datei aufräumen
        }
    } else {
        $mapping = $_POST['map'] ?? [];
        $dateFields = importDateFields();

        $successCount = 0;
        $rowErrors = [];
        $created = [];

        foreach ($data['rows'] as $rowIndex => $row) {
            $member = [];
            $skip = false;

            foreach ($mapping as $csvIndex => $dbField) {
                if ($dbField === '') {
                    continue;
                }
                $value = trim((string) ($row[(int) $csvIndex] ?? ''));

                if (in_array($dbField, $dateFields, true)) {
                    if ($value === '') {
                        $value = null;
                    } else {
                        $parsed = parseImportDate($value);
                        if ($parsed === null) {
                            $rowErrors[] = 'Zeile ' . ($rowIndex + 1) . ": Datum \"$value\" ($dbField) unlesbar - Zeile übersprungen.";
                            $skip = true;
                            break;
                        }
                        $value = $parsed;
                    }
                } elseif ($dbField === 'rechte_pflichten_akzeptiert_at') {
                    // Spalte enthält Text wie "Pflicht" statt eines Datums - jede
                    // ausgefüllte Zelle zählt als Einwilligung, Zeitpunkt = Import.
                    $value = $value !== '' ? date('Y-m-d H:i:s') : null;
                }
                $member[$dbField] = $value;
            }

            if ($skip) {
                continue;
            }
            if (empty($member['vorname']) || empty($member['nachname'])) {
                $rowErrors[] = 'Zeile ' . ($rowIndex + 1) . ': Vorname/Nachname fehlt - übersprungen.';
                continue;
            }
            if (!empty($member['email']) && !filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Zeile ' . ($rowIndex + 1) . ': ungültige E-Mail „' . $member['email'] . '“ - übersprungen.';
                continue;
            }
            if (!empty($member['erziehungsberechtigter_email']) && !filter_var($member['erziehungsberechtigter_email'], FILTER_VALIDATE_EMAIL)) {
                unset($member['erziehungsberechtigter_email']); // nicht kritisch, nur leeren statt ganze Zeile zu verwerfen
            }
            if (isset($member['helm_vorhanden']) && !in_array(mb_strtolower($member['helm_vorhanden']), ['ja', 'nein'], true)) {
                $member['helm_vorhanden'] = null;
            }

            $accessPassword = generateAccessPassword();
            $member['mitgliedsnummer'] = generateMitgliedsnummer($pdo);
            $member['verify_token'] = generateVerifyToken();
            $member['access_password_hash'] = password_hash($accessPassword, PASSWORD_DEFAULT);
            $member['status'] = 'aktiv';

            $columns = array_keys($member);
            $placeholders = array_map(fn($c) => ':' . $c, $columns);
            $sql = 'INSERT INTO members (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

            try {
                $pdo->prepare($sql)->execute($member);
                $successCount++;
                $created[] = [
                    'mitgliedsnummer' => $member['mitgliedsnummer'],
                    'name' => $member['vorname'] . ' ' . $member['nachname'],
                    'password' => $accessPassword,
                    'id' => (int) $pdo->lastInsertId(),
                ];
            } catch (PDOException $e) {
                $rowErrors[] = 'Zeile ' . ($rowIndex + 1) . ': Datenbankfehler - ' . $e->getMessage();
            }
        }

        unlink($path);

        $importResult = [
            'successCount' => $successCount,
            'rowErrors' => $rowErrors,
            'created' => $created,
        ];
        $step = 'done';
    }
}

$pageTitle = 'Mitglieder importieren';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1>Mitglieder importieren</h1>
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

<?php if ($step === 'upload'): ?>
    <p>Importiert mehrere Mitglieder auf einmal aus einer CSV- oder TXT-Datei
       (Komma-, Semikolon- oder Tab-getrennt - z. B. auch eine per Copy-Paste
       aus Excel erzeugte .txt-Datei). Für jede Zeile wird ein <strong>neues</strong>
       Mitglied angelegt, inkl. Zugangscode wie beim normalen Anlegen - ein
       Abgleich mit bereits bestehenden Mitgliedern findet nicht statt.</p>

    <form method="post" action="member_import.php" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="parse">
        <div class="form-group">
            <label for="import_file">Datei (CSV/TXT)</label>
            <input type="file" id="import_file" name="import_file" accept=".csv,.txt" required>
        </div>
        <div class="form-group">
            <label for="delimiter">Trennzeichen</label>
            <select id="delimiter" name="delimiter">
                <option value="semicolon" selected>Semikolon (;)</option>
                <option value="tab">Tab</option>
                <option value="comma">Komma (,)</option>
                <option value="auto">Automatisch erkennen</option>
            </select>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="has_header" value="1" checked>
                Erste Zeile enthält Spaltenüberschriften
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Weiter zur Spalten-Zuordnung</button>
    </form>

<?php elseif ($step === 'mapping' && $preview): ?>
    <p><?= (int) $preview['rowCount'] ?> Datenzeile(n) gefunden, <?= (int) $preview['columnCount'] ?> Spalten.
       Bitte jede Spalte einem Feld zuordnen (oder „– ignorieren –“ lassen).</p>

    <form method="post" action="member_import.php">
        <?= csrfField() ?>
        <input type="hidden" name="step" value="import">
        <input type="hidden" name="token" value="<?= e($preview['token']) ?>">

        <div class="table-scroll" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Spalte</th>
                    <th>Beispielwerte</th>
                    <th>Zuordnen zu</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $preview['columnCount']; $i++): ?>
                    <?php
                    $headerLabel = $preview['header'][$i] ?? ('Spalte ' . ($i + 1));
                    $samples = array_map(fn($r) => $r[$i] ?? '', $preview['sample']);
                    $samples = array_filter($samples, fn($v) => $v !== '');
                    $guess = $preview['header'] ? guessFieldFromHeader((string) ($preview['header'][$i] ?? '')) : null;
                    ?>
                    <tr>
                        <td><strong><?= e($headerLabel) ?></strong></td>
                        <td><?= e(implode(' / ', array_slice($samples, 0, 3))) ?></td>
                        <td>
                            <select name="map[<?= $i ?>]">
                                <option value="">– ignorieren –</option>
                                <?php foreach (importableMemberFields() as $groupLabel => $fields): ?>
                                    <optgroup label="<?= e($groupLabel) ?>">
                                        <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
                                            <option value="<?= e($fieldKey) ?>" <?= $guess === $fieldKey ? 'selected' : '' ?>>
                                                <?= e($fieldLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        </div>

        <h2 style="margin-top:24px;">Vorschau aller <?= (int) $preview['rowCount'] ?> Datensätze</h2>
        <div class="table-scroll" style="overflow:auto;max-height:600px;border:1px solid var(--color-border);border-radius:var(--radius);">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <?php for ($i = 0; $i < $preview['columnCount']; $i++): ?>
                        <th><?= e($preview['header'][$i] ?? ('Spalte ' . ($i + 1))) ?></th>
                    <?php endfor; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preview['rows'] as $rowIndex => $row): ?>
                    <tr>
                        <td><?= $rowIndex + 1 ?></td>
                        <?php for ($i = 0; $i < $preview['columnCount']; $i++): ?>
                            <td style="white-space:nowrap;"><?= e($row[$i] ?? '') ?></td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <p class="alert alert-warning" style="margin-top:16px;">
            Nach dem Klick auf „Importieren“ werden <?= (int) $preview['rowCount'] ?> neue Mitglieder
            angelegt. Das lässt sich nicht automatisch rückgängig machen (Mitglieder müssten einzeln
            in der Liste gelöscht werden).
        </p>
        <button type="submit" class="btn btn-primary" data-confirm="<?= (int) $preview['rowCount'] ?> neue Mitglieder jetzt importieren?">
            Importieren
        </button>
    </form>

<?php elseif ($step === 'done' && $importResult): ?>
    <p class="alert alert-<?= $importResult['successCount'] > 0 ? 'success' : 'error' ?>">
        <?= (int) $importResult['successCount'] ?> Mitglied(er) erfolgreich importiert.
    </p>

    <?php if ($importResult['rowErrors']): ?>
        <div class="alert alert-error">
            <strong><?= count($importResult['rowErrors']) ?> Zeile(n) übersprungen:</strong>
            <ul>
                <?php foreach ($importResult['rowErrors'] as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($importResult['created']): ?>
        <p class="alert alert-warning">
            Die Zugangscodes werden aus Sicherheitsgründen <strong>nur jetzt einmalig</strong> angezeigt
            (danach ist nur noch der Hash gespeichert). Bitte jetzt sichern bzw. über
            „Link“ in der Mitgliederliste einzeln weitergeben.
        </p>
        <div class="table-scroll" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Mitgl.-Nr.</th>
                    <th>Name</th>
                    <th>Zugangscode</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($importResult['created'] as $c): ?>
                    <tr>
                        <td><?= e($c['mitgliedsnummer']) ?></td>
                        <td><?= e($c['name']) ?></td>
                        <td><code><?= e($c['password']) ?></code></td>
                        <td><a href="member_link.php?id=<?= (int) $c['id'] ?>">Link/Details</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <p style="margin-top:16px;"><a href="index.php" class="btn btn-primary">Zur Mitgliederliste</a></p>

<?php else: ?>
    <p class="alert alert-error">Ungültiger Schritt. <a href="member_import.php">Neu starten</a></p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
