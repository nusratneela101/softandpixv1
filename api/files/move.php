<?php
/**
 * api/files/move.php
 * POST: type (file|folder), id, target_folder_id, csrf_token
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

// ── CSRF ─────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    jsonErr('Invalid CSRF token', 403);
}

$type           = $_POST['type'] ?? 'file';
$id             = (int)($_POST['id'] ?? 0);
$targetFolderId = !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;

if (!$id) jsonErr('Missing id');

if ($type === 'file') {
    try {
        $pdo->prepare("UPDATE project_files SET folder_id = ? WHERE id = ? AND is_deleted = 0")
            ->execute([$targetFolderId, $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }
} elseif ($type === 'folder') {
    // Prevent moving a folder into itself
    if ($targetFolderId === $id) jsonErr('Cannot move a folder into itself');
    try {
        $pdo->prepare("UPDATE project_folders SET parent_id = ? WHERE id = ?")
            ->execute([$targetFolderId, $id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }
} else {
    jsonErr('Invalid type');
}
