<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';

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
            $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            // Bewusst dieselbe Fehlermeldung bei unbekanntem Benutzer und falschem Passwort
            if ($admin && password_verify($password, $admin['password_hash'])) {
                loginAdmin((int) $admin['id'], $admin['username']);
                redirect('index.php');
            } else {
                $error = 'Benutzername oder Passwort ist falsch.';
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
