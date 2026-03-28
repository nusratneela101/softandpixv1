<?php
/**
 * api/project/demo_delete_files.php
 * Recursively deletes the uploaded demo files for a project.
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

// ── Auth ─────────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$projectId = (int)($_POST['project_id'] ?? 0);
if (!$projectId) {
    echo json_encode(['success' => false, 'error' => 'Missing project_id']);
    exit;
}

// ── Load project ──────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT id, demo_subdomain FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if (!$project) {
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}

$subdomain = $project['demo_subdomain'] ?? '';
$subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower($subdomain));
if (empty($subdomain)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing demo subdomain']);
    exit;
}

// ── Locate and delete the demo directory ─────────────────────────────────────
$sitesBase = realpath(__DIR__ . '/../../demo/sites');
if ($sitesBase === false) {
    echo json_encode(['success' => false, 'error' => 'Demo sites directory not found']);
    exit;
}

$destDir = $sitesBase . '/' . $subdomain;

// Security: ensure the target is inside demo/sites
$realDestDir = realpath($destDir);
if ($realDestDir !== false && strpos($realDestDir . '/', $sitesBase . '/') !== 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid path']);
    exit;
}

if (is_dir($destDir)) {
    deleteDirectoryRecursive($destDir);
}

// ── Update project record ─────────────────────────────────────────────────────
try {
    $pdo->prepare("UPDATE projects SET demo_has_files = 0 WHERE id = ?")
        ->execute([$projectId]);
} catch (Exception $e) {
    // Column may not exist yet — not fatal
}

// ── Log the deletion ──────────────────────────────────────────────────────────
try {
    if (function_exists('logActivity')) {
        logActivity('demo_delete_files', 'Deleted demo files for project #' . $projectId . ' (subdomain: ' . $subdomain . ')');
    }
} catch (Exception $e) {}

echo json_encode([
    'success' => true,
    'message' => 'Demo files deleted successfully.',
]);

// ── Helper: recursively delete a directory ────────────────────────────────────
function deleteDirectoryRecursive(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}
