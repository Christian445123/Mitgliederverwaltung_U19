<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireAdministrator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    $_SESSION['flash'] = 'Ungültige Anfrage.';
    redirect('users.php');
}

$pdo = getDb();
$id = (int) ($_POST['id'] ?? 0);

if ($id === currentAdminId()) {
    $_SESSION['flash'] = 'Du kannst dich nicht selbst löschen.';
    redirect('users.php');
}

$stmt = $pdo->prepare('SELECT role FROM admins WHERE id = :id');
$stmt->execute(['id' => $id]);
$target = $stmt->fetch();

if (!$target) {
    $_SESSION['flash'] = 'Benutzer wurde nicht gefunden.';
    redirect('users.php');
}

if ($target['role'] === 'administrator') {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'administrator'")->fetchColumn();
    if ($count <= 1) {
        $_SESSION['flash'] = 'Der letzte Administrator kann nicht gelöscht werden.';
        redirect('users.php');
    }
}

$del = $pdo->prepare('DELETE FROM admins WHERE id = :id');
$del->execute(['id' => $id]);

$_SESSION['flash'] = 'Benutzer wurde gelöscht.';
redirect('users.php');
