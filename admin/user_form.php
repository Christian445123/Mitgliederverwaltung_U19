<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireAdministrator();

$pdo = getDb();

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$user = ['username' => '', 'role' => 'bearbeiter'];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT id, username, role FROM admins WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        $_SESSION['flash'] = 'Benutzer wurde nicht gefunden.';
        redirect('users.php');
    }
    $user = $existing;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $user['username'] = trim($_POST['username'] ?? '');
        $user['role'] = ($_POST['role'] ?? 'bearbeiter') === 'administrator' ? 'administrator' : 'bearbeiter';
        $password = (string) ($_POST['password'] ?? '');

        if ($user['username'] === '') {
            $errors[] = 'Benutzername ist ein Pflichtfeld.';
        }
        if (!$isEdit && strlen($password) < 8) {
            $errors[] = 'Passwort muss mindestens 8 Zeichen haben.';
        }
        if ($isEdit && $password !== '' && strlen($password) < 8) {
            $errors[] = 'Neues Passwort muss mindestens 8 Zeichen haben.';
        }

        // Es muss immer mindestens ein Administrator übrig bleiben.
        if (!$errors && $isEdit && $user['role'] !== 'administrator') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE role = 'administrator' AND id != :id");
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() === 0) {
                $errors[] = 'Es muss mindestens ein Administrator bestehen bleiben. Rolle kann nicht geändert werden.';
            }
        }

        if (!$errors) {
            try {
                if ($isEdit) {
                    if ($password !== '') {
                        $sql = 'UPDATE admins SET username = :username, role = :role, password_hash = :hash WHERE id = :id';
                        $params = [
                            'username' => $user['username'],
                            'role' => $user['role'],
                            'hash' => password_hash($password, PASSWORD_DEFAULT),
                            'id' => $id,
                        ];
                    } else {
                        $sql = 'UPDATE admins SET username = :username, role = :role WHERE id = :id';
                        $params = ['username' => $user['username'], 'role' => $user['role'], 'id' => $id];
                    }
                    $pdo->prepare($sql)->execute($params);

                    if ($id === currentAdminId()) {
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['admin_role'] = $user['role'];
                    }
                    $_SESSION['flash'] = 'Benutzer wurde aktualisiert.';
                } else {
                    $sql = 'INSERT INTO admins (username, password_hash, role) VALUES (:username, :hash, :role)';
                    $pdo->prepare($sql)->execute([
                        'username' => $user['username'],
                        'hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => $user['role'],
                    ]);
                    $_SESSION['flash'] = 'Benutzer wurde angelegt.';
                }
                redirect('users.php');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Dieser Benutzername ist bereits vergeben.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$pageTitle = $isEdit ? 'Benutzer bearbeiten' : 'Neuer Benutzer';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="content-header">
    <h1><?= e($pageTitle) ?></h1>
    <a href="users.php" class="btn btn-link">&larr; Zurück zur Liste</a>
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

<form method="post" action="user_form.php<?= $isEdit ? '?id=' . (int) $id : '' ?>" class="member-form" novalidate>
    <?= csrfField() ?>

    <fieldset>
        <legend>Zugangsdaten</legend>
        <div class="form-group">
            <label for="username">Benutzername *</label>
            <input type="text" id="username" name="username" required autocomplete="username" value="<?= e($user['username']) ?>">
        </div>
        <div class="form-group">
            <label for="password"><?= $isEdit ? 'Neues Passwort (leer lassen = unverändert)' : 'Passwort *' ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
        </div>
    </fieldset>

    <fieldset>
        <legend>Rolle</legend>
        <div class="form-group">
            <label for="role">Berechtigung</label>
            <select id="role" name="role">
                <option value="bearbeiter" <?= $user['role'] === 'bearbeiter' ? 'selected' : '' ?>>Bearbeiter (Mitglieder verwalten)</option>
                <option value="administrator" <?= $user['role'] === 'administrator' ? 'selected' : '' ?>>Administrator (alles inkl. Benutzerverwaltung)</option>
            </select>
        </div>
    </fieldset>

    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Änderungen speichern' : 'Benutzer anlegen' ?></button>
</form>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
