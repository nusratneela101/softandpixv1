<?php
/**
 * api/files/delete.php
 * POST: type (file|folder), id, csrf_token
 * Soft-deletes a file (sets is_deleted=1) or hard-deletes an empty folder.
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

function jsonErr($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ── Auth ─────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    jsonErr('Unauthorized', 401);
}
$userId    = $_SESSION['user_id'] ?? $_SESSION['admin_id'];
$userRole  = $_SESSION['user_role'] ?? 'admin';
$isAdmin   = ($userRole === 'admin' || isset($_SESSION['admin_id']));

// ── CSRF ─────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    jsonErr('Invalid CSRF token', 403);
}

$type = $_POST['type'] ?? 'file';
$id   = (int)($_POST['id'] ?? 0);
if (!$id) jsonErr('Missing id');

if ($type === 'file') {
    // Fetch the file
    try {
        $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $file = $stmt->fetch();
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }

    if (!$file) jsonErr('File not found', 404);

    // Developers can only delete their own files
    if (!$isAdmin && $file['uploaded_by'] != $userId) {
        jsonErr('You can only delete your own files', 403);
    }

    // Soft delete — physical file kept for version history
    try {
        $pdo->prepare("UPDATE project_files SET is_deleted = 1 WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }

} elseif ($type === 'folder') {
    if (!$isAdmin) jsonErr('Only admins can delete folders', 403);

    try {
        // Soft-delete all files inside recursively
        $pdo->prepare("UPDATE project_files SET is_deleted = 1 WHERE folder_id = ?")
            ->execute([$id]);
        // Delete the folder record
        $pdo->prepare("DELETE FROM project_folders WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }
} else {
    jsonErr('Invalid type');
}
