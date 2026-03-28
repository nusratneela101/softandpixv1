<?php
/**
 * api/files/create_folder.php
 * POST: project_id, parent_id (optional), name, csrf_token
 * Also handles: action=rename with folder_id, name
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
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];

// ── CSRF ─────────────────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    jsonErr('Invalid CSRF token', 403);
}

$action = $_POST['action'] ?? 'create';

// ── Rename ────────────────────────────────────────────────────
if ($action === 'rename') {
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $newName  = trim($_POST['name'] ?? '');
    if (!$folderId || !$newName) jsonErr('Missing folder_id or name');
    if (strlen($newName) > 255) jsonErr('Name too long');

    try {
        $pdo->prepare("UPDATE project_folders SET name = ? WHERE id = ?")
            ->execute([$newName, $folderId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        jsonErr('Database error', 500);
    }
    exit;
}

// ── Create ────────────────────────────────────────────────────
$projectId = (int)($_POST['project_id'] ?? 0);
$parentId  = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$name      = trim($_POST['name'] ?? '');

if (!$projectId) jsonErr('Missing project_id');
if (!$name)      jsonErr('Folder name required');
if (strlen($name) > 255) jsonErr('Name too long');

// No duplicate folder in same parent
try {
    $dup = $pdo->prepare(
        "SELECT id FROM project_folders
         WHERE project_id = ? AND name = ?
           AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL))
         LIMIT 1"
    );
    $dup->execute([$projectId, $name, $parentId, $parentId]);
    if ($dup->fetch()) jsonErr('A folder with that name already exists here');
} catch (Exception $e) {
    jsonErr('Database error', 500);
}

try {
    $ins = $pdo->prepare(
        "INSERT INTO project_folders (project_id, parent_id, name, created_by)
         VALUES (?, ?, ?, ?)"
    );
    $ins->execute([$projectId, $parentId, $name, $userId]);
    $folderId = $pdo->lastInsertId();
    echo json_encode([
        'success' => true,
        'folder'  => [
            'id'         => (int)$folderId,
            'project_id' => $projectId,
            'parent_id'  => $parentId,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Exception $e) {
    jsonErr('Database error', 500);
}
