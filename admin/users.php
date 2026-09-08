<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireAdministrator();

$pdo = getDb();
$users = $pdo->query('SELECT id, username, role, created_at FROM admins ORDER BY username')->fetchAll();

$pageTitle = 'Benutzerverwaltung';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1>Benutzerverwaltung</h1>
    <a href="user_form.php" class="btn btn-primary">+ Neuer Benutzer</a>
</div>

<?php if ($flash): ?>
    <p class="alert alert-success"><?= e($flash) ?></p>
<?php endif; ?>

<table class="table">
    <thead>
        <tr>
            <th>Benutzername</th>
            <th>Rolle</th>
            <th>Angelegt am</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$users): ?>
        <tr>
            <td colspan="4" class="empty">Keine Benutzer gefunden.</td>
        </tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
        <tr>
            <td>
                <?= e($u['username']) ?>
                <?php if ((int) $u['id'] === currentAdminId()): ?>
                    <span class="badge badge-gray">Du</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge-<?= $u['role'] === 'administrator' ? 'green' : 'gray' ?>">
                    <?= e($u['role'] === 'administrator' ? 'Administrator' : 'Bearbeiter') ?>
                </span>
            </td>
            <td><?= e(date('d.m.Y', strtotime($u['created_at']))) ?></td>
            <td class="actions">
                <a href="user_form.php?id=<?= (int) $u['id'] ?>">Bearbeiten</a>
                <?php if ((int) $u['id'] !== currentAdminId()): ?>
                    <form method="post" action="user_delete.php" class="inline-form"
                          data-confirm="Benutzer &quot;<?= e($u['username']) ?>&quot; wirklich löschen?">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="link-button danger">Löschen</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
