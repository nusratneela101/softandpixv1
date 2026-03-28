<?php
/**
 * api/files/download.php
 * GET: file_id
 * Serves the file for download, supports range requests for large files.
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
        "SELECT pf.*, u.name AS uploader_name
         FROM project_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         WHERE pf.id = ? AND pf.is_deleted = 0"
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

$filePath = dirname(__DIR__, 2) . '/' . $file['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on disk');
}

// Increment download count
try {
    $pdo->prepare("UPDATE project_files SET download_count = download_count + 1 WHERE id = ?")
        ->execute([$fileId]);
} catch (Exception $e) {}

// ── Headers ───────────────────────────────────────────────────
$mimeType = $file['mime_type'] ?: 'application/octet-stream';
$fileSize = filesize($filePath);
$fileName = $file['original_name'];

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Accept-Ranges: bytes');

// ── Range requests ────────────────────────────────────────────
if (isset($_SERVER['HTTP_RANGE'])) {
    preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m);
    $start = (int)$m[1];
    $end   = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : $fileSize - 1;
    $end   = min($end, $fileSize - 1);
    $length = $end - $start + 1;

    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . $length);

    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    $sent = 0;
    while ($sent < $length) {
        $chunk = min(8192, $length - $sent);
        echo fread($fp, $chunk);
        $sent += $chunk;
        if (connection_aborted()) break;
    }
    fclose($fp);
    exit;
}

// ── Full file ─────────────────────────────────────────────────
readfile($filePath);
