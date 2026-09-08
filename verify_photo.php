<?php
/**
 * Liefert das eigene Passfoto eines Mitglieds aus - nur nach erfolgreicher
 * Freischaltung über verify.php (E-Mail + Zugangscode), analog zum
 * Admin-Pendant admin/member_photo.php.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

$token = $_GET['token'] ?? '';
if (!$token || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    die('Ungültig.');
}

$pdo = getDb();
$stmt = $pdo->prepare('SELECT id, pass_foto_pfad FROM members WHERE verify_token = :token');
$stmt->execute(['token' => $token]);
$member = $stmt->fetch();

if (!$member || empty($_SESSION['verify_unlocked_' . $member['id']])) {
    http_response_code(403);
    die('Kein Zugriff.');
}

if (!$member['pass_foto_pfad']) {
    http_response_code(404);
    die('Kein Foto vorhanden.');
}

$path = __DIR__ . '/uploads/members/' . basename($member['pass_foto_pfad']);
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
