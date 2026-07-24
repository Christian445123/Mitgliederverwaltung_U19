<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

// Löschen ist eine irreversible Aktion und muss daher per POST + CSRF-Token erfolgen,
// damit sie nicht über einen einfachen (Cross-Site-)Link ausgelöst werden kann.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    $_SESSION['flash'] = 'Ungültige Anfrage.';
    redirect('index.php');
}

$pdo = getDb();
$id = (int) ($_POST['id'] ?? 0);

$stmt = $pdo->prepare('DELETE FROM members WHERE id = :id');
$stmt->execute(['id' => $id]);

$_SESSION['flash'] = $stmt->rowCount() > 0
    ? 'Mitglied wurde gelöscht.'
    : 'Mitglied wurde nicht gefunden.';

redirect('index.php');
