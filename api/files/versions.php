<?php
/**
 * api/files/versions.php
 * GET: file_id — returns all versions of a file and file details.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$fileId = (int)($_GET['file_id'] ?? 0);
if (!$fileId) {
    echo json_encode(['success' => false, 'error' => 'Missing file_id']);
    exit;
}

try {
    // Get the requested file
    $stmt = $pdo->prepare(
        "SELECT pf.*, u.name AS uploader_name
         FROM project_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         WHERE pf.id = ? AND pf.is_deleted = 0"
    );
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if (!$file) {
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// ── Find all versions by same original_name in same project/folder ───────────
try {
    $vStmt = $pdo->prepare(
        "SELECT pf.*, u.name AS uploader_name
         FROM project_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         WHERE pf.project_id = ?
           AND pf.original_name = ?
           AND (pf.folder_id = ? OR (pf.folder_id IS NULL AND ? IS NULL))
         ORDER BY pf.version DESC"
    );
    $vStmt->execute([
        $file['project_id'],
        $file['original_name'],
        $file['folder_id'],
        $file['folder_id'],
    ]);
    $versions = $vStmt->fetchAll();
} catch (Exception $e) {
    $versions = [$file];
}

echo json_encode([
    'success'  => true,
    'file'     => $file,
    'versions' => $versions,
]);
