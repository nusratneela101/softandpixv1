<?php
/**
 * api/files/upload.php
 * Upload one or more files to a project folder.
 * POST multipart/form-data: project_id, folder_id (optional), file, csrf_token
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

// ── Inputs ───────────────────────────────────────────────────
$projectId = (int)($_POST['project_id'] ?? 0);
$folderId  = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

if (!$projectId) jsonErr('Missing project_id');

// ── Check project access ─────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    if (!$stmt->fetch()) jsonErr('Project not found', 404);
} catch (Exception $e) {
    jsonErr('DB error', 500);
}

// ── File validation ───────────────────────────────────────────
if (empty($_FILES['file'])) {
    jsonErr('No file uploaded');
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
    ];
    jsonErr($errors[$file['error']] ?? 'Upload error');
}

// 25 MB limit
$maxBytes = 25 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    jsonErr('File exceeds 25 MB limit');
}

// ── MIME validation using finfo ───────────────────────────────
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

$blockedExtensions = ['php','phtml','php3','php4','php5','php7','phps','phar',
                      'cgi','pl','py','rb','sh','bash','exe','com','bat','cmd',
                      'asp','aspx','jsp','htaccess'];
$originalName = basename($file['name']);
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (in_array($ext, $blockedExtensions, true)) {
    jsonErr('File type not allowed');
}

// ── Build storage path ────────────────────────────────────────
$basePath   = dirname(__DIR__, 2) . '/uploads/projects/' . $projectId;
if (!is_dir($basePath)) {
    mkdir($basePath, 0755, true);
}

$storedName = uniqid('f_', true) . '.' . $ext;
$filePath   = $basePath . '/' . $storedName;
$dbPath     = 'uploads/projects/' . $projectId . '/' . $storedName;

// ── Version detection ─────────────────────────────────────────
$version      = 1;
$parentFileId = null;
try {
    $vStmt = $pdo->prepare(
        "SELECT id, version FROM project_files
         WHERE project_id = ? AND original_name = ? AND is_deleted = 0
           AND (folder_id = ? OR (folder_id IS NULL AND ? IS NULL))
         ORDER BY version DESC LIMIT 1"
    );
    $vStmt->execute([$projectId, $originalName, $folderId, $folderId]);
    $prev = $vStmt->fetch();
    if ($prev) {
        $version      = $prev['version'] + 1;
        $parentFileId = $prev['id'];
        // Soft-delete old version from active view (it stays in DB for version history)
    }
} catch (Exception $e) {}

// ── Move file ─────────────────────────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    jsonErr('Failed to save file', 500);
}

// ── Insert DB record ──────────────────────────────────────────
try {
    $ins = $pdo->prepare(
        "INSERT INTO project_files
         (project_id, folder_id, uploaded_by, original_name, stored_name, file_path,
          file_size, mime_type, file_extension, version, parent_file_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $projectId, $folderId, $userId, $originalName, $storedName, $dbPath,
        $file['size'], $mimeType, $ext, $version, $parentFileId
    ]);
    $fileId = $pdo->lastInsertId();
} catch (Exception $e) {
    @unlink($filePath);
    jsonErr('Database error: ' . $e->getMessage(), 500);
}

// ── Return result ─────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'file'    => [
        'id'            => (int)$fileId,
        'project_id'    => $projectId,
        'folder_id'     => $folderId,
        'original_name' => $originalName,
        'stored_name'   => $storedName,
        'file_path'     => $dbPath,
        'file_size'     => $file['size'],
        'mime_type'     => $mimeType,
        'file_extension'=> $ext,
        'version'       => $version,
        'created_at'    => date('Y-m-d H:i:s'),
        'uploader_name' => $_SESSION['user_name'] ?? $_SESSION['admin_username'] ?? 'Unknown',
    ],
]);
