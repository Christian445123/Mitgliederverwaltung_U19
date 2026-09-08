<?php
/**
 * Liefert das Passfoto eines Mitglieds aus - nur für angemeldete Admins.
 * Die Datei selbst liegt in uploads/members/, das per .htaccess gegen
 * direkten Web-Zugriff gesperrt ist.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

startSecureSession();
requireLogin();

$pdo = getDb();
$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT pass_foto_pfad FROM members WHERE id = :id');
$stmt->execute(['id' => $id]);
$filename = $stmt->fetchColumn();

if (!$filename) {
    http_response_code(404);
    die('Kein Foto vorhanden.');
}

$path = __DIR__ . '/../uploads/members/' . basename($filename);
if (!is_file($path)) {
    http_response_code(404);
    die('Kein Foto vorhanden.');
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $extension === 'png' ? 'image/png' : 'image/jpeg';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
