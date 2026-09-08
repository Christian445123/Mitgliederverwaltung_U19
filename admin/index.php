<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$pdo = getDb();

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(vorname LIKE :search OR nachname LIKE :search OR mitgliedsnummer LIKE :search OR email LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter === 'aktiv' || $statusFilter === 'inaktiv') {
    $where[] = 'status = :status';
    $params['status'] = $statusFilter;
}

$sql = 'SELECT id, mitgliedsnummer, vorname, nachname, email, status, verified_at, created_at,
        nada_zertifikat_gueltig_bis, nada_erlaubnis_gueltig_bis, reisepass_gueltig_bis FROM members';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

$pageTitle = 'Mitglieder';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1>Mitglieder</h1>
    <div>
        <?php if (isAdministrator()): ?>
            <a href="member_import.php" class="btn btn-secondary">Import</a>
        <?php endif; ?>
        <a href="member_form.php" class="btn btn-primary">+ Neues Mitglied</a>
    </div>
</div>

<?php if ($flash): ?>
    <p class="alert alert-success"><?= e($flash) ?></p>
<?php endif; ?>

<form method="get" action="index.php" class="filter-bar">
    <input type="text" name="q" placeholder="Suche: Name, Mitgliedsnummer, E-Mail" value="<?= e($search) ?>">
    <select name="status">
        <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Alle Status</option>
        <option value="aktiv" <?= $statusFilter === 'aktiv' ? 'selected' : '' ?>>Aktiv</option>
        <option value="inaktiv" <?= $statusFilter === 'inaktiv' ? 'selected' : '' ?>>Inaktiv</option>
    </select>
    <button type="submit" class="btn">Filtern</button>
    <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a href="index.php" class="btn btn-link">Zurücksetzen</a>
    <?php endif; ?>
</form>

<table class="table">
    <thead>
        <tr>
            <th>Mitgl.-Nr.</th>
            <th>Name</th>
            <th>E-Mail</th>
            <th>Status</th>
            <th>Daten geprüft</th>
            <th>NADA</th>
            <th>Reisepass</th>
            <th>Angelegt am</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$members): ?>
        <tr>
            <td colspan="9" class="empty">Keine Mitglieder gefunden.</td>
        </tr>
        <?php endif; ?>
        <?php
        $nadaColors = ['red' => 'red', 'blue' => 'blue', 'yellow' => 'orange', 'ok' => 'green'];
        $passportColors = ['red' => 'red', 'blue' => 'blue', 'ok' => 'green'];
        ?>
        <?php foreach ($members as $m): ?>
        <?php
            $nadaStatus = worstDateStatus([
                nadaDateStatus($m['nada_zertifikat_gueltig_bis']),
                nadaDateStatus($m['nada_erlaubnis_gueltig_bis']),
            ]);
            $nadaDate = $nadaStatus === nadaDateStatus($m['nada_zertifikat_gueltig_bis']) && $nadaStatus !== ''
                ? $m['nada_zertifikat_gueltig_bis'] : $m['nada_erlaubnis_gueltig_bis'];
            $passportStatus = passportDateStatus($m['reisepass_gueltig_bis']);
        ?>
        <tr>
            <td><?= e($m['mitgliedsnummer']) ?></td>
            <td><?= e($m['nachname']) ?>, <?= e($m['vorname']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td>
                <span class="badge badge-<?= $m['status'] === 'aktiv' ? 'green' : 'gray' ?>">
                    <?= e(ucfirst($m['status'])) ?>
                </span>
            </td>
            <td>
                <?php if ($m['verified_at']): ?>
                    <span class="badge badge-green" title="Bestätigt am <?= e($m['verified_at']) ?>">✓ Bestätigt</span>
                <?php else: ?>
                    <span class="badge badge-orange">Ausstehend</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($nadaStatus === ''): ?>
                    <span class="badge badge-gray">–</span>
                <?php else: ?>
                    <span class="badge badge-<?= $nadaColors[$nadaStatus] ?? 'gray' ?>"
                          title="Zertifikat: <?= e($m['nada_zertifikat_gueltig_bis'] ? date('d.m.Y', strtotime($m['nada_zertifikat_gueltig_bis'])) : '–') ?> · Erlaubnis: <?= e($m['nada_erlaubnis_gueltig_bis'] ? date('d.m.Y', strtotime($m['nada_erlaubnis_gueltig_bis'])) : '–') ?>">
                        <?= e($nadaDate ? date('d.m.Y', strtotime($nadaDate)) : '–') ?>
                    </span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($passportStatus === ''): ?>
                    <span class="badge badge-gray">–</span>
                <?php else: ?>
                    <span class="badge badge-<?= $passportColors[$passportStatus] ?? 'gray' ?>">
                        <?= e(date('d.m.Y', strtotime($m['reisepass_gueltig_bis']))) ?>
                    </span>
                <?php endif; ?>
            </td>
            <td><?= e(date('d.m.Y', strtotime($m['created_at']))) ?></td>
            <td class="actions">
                <a href="member_form.php?id=<?= (int) $m['id'] ?>">Bearbeiten</a>
                <a href="member_link.php?id=<?= (int) $m['id'] ?>">Link</a>
                <form method="post" action="member_delete.php" class="inline-form"
                      data-confirm="Mitglied &quot;<?= e($m['vorname'] . ' ' . $m['nachname']) ?>&quot; wirklich löschen?">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                    <button type="submit" class="link-button danger">Löschen</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
