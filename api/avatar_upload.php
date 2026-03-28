<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
    exit;
}

$maxSize = 2 * 1024 * 1024; // 2 MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

$validation = validateUploadedFile($_FILES['avatar'], $allowedTypes, $maxSize);
if (!$validation['ok']) {
    echo json_encode(['ok' => false, 'error' => $validation['error']]);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = $validation['ext'];
$filename = 'avatar_' . (int)$_SESSION['user_id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

try {
    // Fetch old avatar to delete it
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $old = $stmt->fetchColumn();

    if ($old && $old !== $filename) {
        $oldPath = $uploadDir . basename($old);
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    $upd = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $upd->execute([$filename, (int)$_SESSION['user_id']]);

    echo json_encode(['ok' => true, 'avatar' => $filename]);
} catch (Exception $e) {
    // Roll back file
    if (is_file($destPath)) unlink($destPath);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
