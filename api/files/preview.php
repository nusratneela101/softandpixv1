<?php
/**
 * api/files/preview.php
 * GET: file_id
 * Images/PDFs: served inline. Other types: download.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$fileId = (int)($_GET['file_id'] ?? 0);
if (!$fileId) {
    http_response_code(400);
    exit('Missing file_id');
}

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM project_files WHERE id = ? AND is_deleted = 0"
    );
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    exit('Database error');
}

if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$diskPath = dirname(__DIR__, 2) . '/' . $file['file_path'];
if (!file_exists($diskPath)) {
    http_response_code(404);
    exit('File not found on disk');
}

$mimeType = $file['mime_type'] ?: 'application/octet-stream';
$fileName = $file['original_name'];

// Inline types
$inlineTypes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'image/bmp', 'application/pdf', 'text/plain', 'text/html',
];

$isInline = in_array($mimeType, $inlineTypes, true) ||
            strpos($mimeType, 'image/') === 0;

header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . addslashes($fileName) . '"');
header('Content-Length: ' . filesize($diskPath));
header('Cache-Control: private, max-age=3600');
readfile($diskPath);
