<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Bitte Benutzername und Passwort eingeben.';
        } else {
            $pdo = getDb();
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role, failed_login_attempts, login_locked_until FROM admins WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            $lockedUntil = ($admin && $admin['login_locked_until']) ? strtotime($admin['login_locked_until']) : null;

            if ($lockedUntil && $lockedUntil > time()) {
                $minutesLeft = max(1, (int) ceil(($lockedUntil - time()) / 60));
                $error = "Zu viele Fehlversuche. Bitte in etwa {$minutesLeft} Minute(n) erneut versuchen.";
            } elseif ($admin && password_verify($password, $admin['password_hash'])) {
                $pdo->prepare('UPDATE admins SET failed_login_attempts = 0, login_locked_until = NULL WHERE id = :id')
                    ->execute(['id' => $admin['id']]);
                loginAdmin((int) $admin['id'], $admin['username'], $admin['role']);
                redirect('index.php');
            } else {
                // Bewusst dieselbe Fehlermeldung bei unbekanntem Benutzer und falschem Passwort
                $error = 'Benutzername oder Passwort ist falsch.';

                if ($admin) {
                    $attempts = (int) $admin['failed_login_attempts'] + 1;
                    $params = ['attempts' => $attempts, 'id' => $admin['id']];
                    $lockClause = '';
                    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                        $lockClause = ', login_locked_until = DATE_ADD(NOW(), INTERVAL ' . LOGIN_LOCKOUT_MINUTES . ' MINUTE)';
                    }
                    $pdo->prepare("UPDATE admins SET failed_login_attempts = :attempts $lockClause WHERE id = :id")
                        ->execute($params);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Mitgliederverwaltung</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="auth-page">
    <div class="auth-box">
        <h1>Mitgliederverwaltung</h1>
        <h2>Admin-Login</h2>

        <?php if ($error): ?>
            <p class="alert alert-error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php" novalidate>
            <?= csrfField() ?>
            <label for="username">Benutzername</label>
            <input type="text" id="username" name="username" autocomplete="username" required autofocus>

            <label for="password">Passwort</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>
