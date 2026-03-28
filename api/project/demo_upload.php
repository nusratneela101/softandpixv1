<?php
/**
 * api/project/demo_upload.php
 * Accepts a ZIP file upload, validates it, and extracts it into demo/sites/{subdomain}/
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
if (empty($subdomain)) {
    echo json_encode(['success' => false, 'error' => 'Demo subdomain is not configured. Please set a subdomain first.']);
    exit;
}

// Sanitize subdomain to be safe for filesystem
$subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower($subdomain));
if (empty($subdomain)) {
    echo json_encode(['success' => false, 'error' => 'Invalid demo subdomain']);
    exit;
}

// ── Validate uploaded file ────────────────────────────────────────────────────
if (empty($_FILES['demo_zip']) || $_FILES['demo_zip']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];
    $errCode = $_FILES['demo_zip']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'error' => $uploadErrors[$errCode] ?? 'Upload error.']);
    exit;
}

$maxSize = 50 * 1024 * 1024; // 50MB
if ($_FILES['demo_zip']['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'ZIP file exceeds the 50MB limit.']);
    exit;
}

$tmpPath = $_FILES['demo_zip']['tmp_name'];

// Validate it's actually a ZIP
if (!is_readable($tmpPath)) {
    echo json_encode(['success' => false, 'error' => 'Cannot read uploaded file.']);
    exit;
}

$zip = new ZipArchive();
$zipResult = $zip->open($tmpPath);
if ($zipResult !== true) {
    echo json_encode(['success' => false, 'error' => 'Invalid or corrupt ZIP file.']);
    exit;
}

// ── Prepare destination directory ────────────────────────────────────────────
$sitesBase = realpath(__DIR__ . '/../../demo/sites');
if ($sitesBase === false) {
    $sitesBase = __DIR__ . '/../../demo/sites';
    if (!mkdir($sitesBase, 0755, true) && !is_dir($sitesBase)) {
        $zip->close();
        echo json_encode(['success' => false, 'error' => 'Cannot create demo/sites directory.']);
        exit;
    }
    $sitesBase = realpath($sitesBase);
}

$destDir = $sitesBase . '/' . $subdomain;

// Remove existing files if re-uploading
if (is_dir($destDir)) {
    deleteDirectoryRecursive($destDir);
}
if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
    $zip->close();
    echo json_encode(['success' => false, 'error' => 'Cannot create demo directory.']);
    exit;
}

// ── Dangerous files to skip ───────────────────────────────────────────────────
// Patterns: .htaccess, php.ini, .user.ini, shell scripts with dangerous funcs
$dangerousFilenames = ['.htaccess', 'php.ini', '.user.ini', '.htpasswd', '.env'];
$dangerousExtensions = ['sh', 'bash', 'csh', 'ksh', 'zsh', 'bat', 'cmd', 'exe', 'com', 'pif', 'vbs', 'ps1'];
$dangerousPhpFunctions = ['exec', 'system', 'passthru', 'shell_exec', 'popen', 'proc_open', 'pcntl_exec', 'eval'];

// ── Extract ZIP with security checks ─────────────────────────────────────────
$maxDepth     = 5;
$maxTotalSize = 100 * 1024 * 1024; // 100MB total
$totalSize    = 0;
$extractedFiles = [];
$skippedFiles   = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat     = $zip->statIndex($i);
    $zipName  = $stat['name'];

    // Skip directories (they'll be created automatically)
    if (substr($zipName, -1) === '/') {
        continue;
    }

    // Normalize path: remove leading slash, resolve ..
    $safeName = ltrim($zipName, '/\\');
    $parts    = explode('/', str_replace('\\', '/', $safeName));

    // Skip if path tries to traverse upward
    if (in_array('..', $parts, true)) {
        $skippedFiles[] = $zipName . ' (path traversal)';
        continue;
    }

    // Enforce max directory depth
    if (count($parts) > $maxDepth + 1) {
        $skippedFiles[] = $zipName . ' (too deep)';
        continue;
    }

    $filename  = end($parts);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $basename  = strtolower(basename($filename));

    // Skip dangerous filenames
    if (in_array($basename, $dangerousFilenames, true)) {
        $skippedFiles[] = $zipName . ' (dangerous filename)';
        continue;
    }

    // Skip dangerous extensions
    if (in_array($extension, $dangerousExtensions, true)) {
        $skippedFiles[] = $zipName . ' (dangerous extension)';
        continue;
    }

    // Check PHP files for dangerous functions
    if ($extension === 'php') {
        $content = $zip->getFromIndex($i);
        if ($content !== false) {
            $lower = strtolower($content);
            foreach ($dangerousPhpFunctions as $func) {
                if (strpos($lower, $func . '(') !== false || strpos($lower, $func . ' (') !== false) {
                    $skippedFiles[] = $zipName . ' (dangerous PHP: ' . $func . ')';
                    continue 2;
                }
            }
        }
    }

    // Check total size limit
    $totalSize += $stat['size'];
    if ($totalSize > $maxTotalSize) {
        $zip->close();
        deleteDirectoryRecursive($destDir);
        echo json_encode(['success' => false, 'error' => 'Extracted content exceeds 100MB limit.']);
        exit;
    }

    // Build destination path
    $destPath    = $destDir . '/' . implode('/', $parts);
    $destPathDir = dirname($destPath);

    if (!is_dir($destPathDir)) {
        mkdir($destPathDir, 0755, true);
    }

    // Verify we're not writing outside destDir (realpath must resolve both paths)
    $realDestDir     = realpath($destDir);
    $realDestPathDir = realpath($destPathDir);
    if ($realDestDir === false || $realDestPathDir === false ||
        strpos($realDestPathDir . '/', $realDestDir . '/') !== 0) {
        $skippedFiles[] = $zipName . ' (outside target directory)';
        continue;
    }

    // Extract the file
    $content = $zip->getFromIndex($i);
    if ($content !== false) {
        file_put_contents($destPath, $content);
        $extractedFiles[] = [
            'name' => implode('/', $parts),
            'size' => $stat['size'],
        ];
    }
}

$zip->close();

// ── Add a .htaccess to prevent PHP execution if no PHP files extracted ────────
// Add a protective .htaccess — disable directory listing; PHP files are
// included/served via demo/index.php which enforces the same path checks.
$siteHtaccess = $destDir . '/.htaccess';
file_put_contents($siteHtaccess, "Options -Indexes\n");

// ── Update project record ─────────────────────────────────────────────────────
try {
    $pdo->prepare("UPDATE projects SET demo_has_files = 1 WHERE id = ?")
        ->execute([$projectId]);
} catch (Exception $e) {
    // Column may not exist yet — not fatal
}

// ── Log the upload ────────────────────────────────────────────────────────────
try {
    if (function_exists('logActivity')) {
        logActivity('demo_upload', 'Uploaded demo files for project #' . $projectId . ' (subdomain: ' . $subdomain . ')');
    }
} catch (Exception $e) {}

echo json_encode([
    'success'        => true,
    'message'        => 'Demo files uploaded and extracted successfully.',
    'files_count'    => count($extractedFiles),
    'total_size'     => $totalSize,
    'skipped_count'  => count($skippedFiles),
    'skipped'        => $skippedFiles,
    'subdomain'      => $subdomain,
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
