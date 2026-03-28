<?php
/**
 * api/files/download_zip.php
 * POST: file_ids[] array OR folder_id — creates and streams a ZIP archive.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// CSRF
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$fileIds  = array_filter(array_map('intval', (array)($_POST['file_ids'] ?? [])));
$folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

if (!$fileIds && !$folderId) {
    http_response_code(400);
    exit('No files specified');
}

// ── Collect files ─────────────────────────────────────────────
$files = [];

if ($folderId) {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM project_files
             WHERE folder_id = ? AND is_deleted = 0
             ORDER BY original_name"
        );
        $stmt->execute([$folderId]);
        $files = $stmt->fetchAll();
    } catch (Exception $e) {}
} elseif ($fileIds) {
    try {
        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT * FROM project_files
             WHERE id IN ($placeholders) AND is_deleted = 0"
        );
        $stmt->execute($fileIds);
        $files = $stmt->fetchAll();
    } catch (Exception $e) {}
}

if (!$files) {
    http_response_code(404);
    exit('No files found');
}

// ── Create ZIP ────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive not available');
}

$tmpZip = sys_get_temp_dir() . '/softandpix_' . uniqid() . '.zip';
$zip    = new ZipArchive();

if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    exit('Failed to create ZIP');
}

$baseDir = dirname(__DIR__, 2);
$usedNames = [];

foreach ($files as $file) {
    $diskPath = $baseDir . '/' . $file['file_path'];
    if (!file_exists($diskPath)) continue;

    // Avoid duplicate names inside ZIP
    $name = $file['original_name'];
    if (isset($usedNames[$name])) {
        $usedNames[$name]++;
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $name = $base . '_' . $usedNames[$name] . ($ext ? '.' . $ext : '');
    } else {
        $usedNames[$name] = 1;
    }

    $zip->addFile($diskPath, $name);
}

$zip->close();

// ── Stream ZIP ────────────────────────────────────────────────
$zipName = 'softandpix_files_' . date('Ymd_His') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: private, no-cache, no-store');

readfile($tmpZip);
@unlink($tmpZip);
exit;
