<?php
session_start();
require_once '../config/db.php';

// Extract subdomain from HTTP_HOST
$host = $_SERVER['HTTP_HOST'] ?? '';
$subdomain = '';

// Match <subdomain>.softandpix.com (also handles localhost-based testing with ?subdomain=...)
if (preg_match('/^([a-z0-9-]+)\.softandpix\.com$/i', $host, $m)) {
    $subdomain = strtolower($m[1]);
} elseif (!empty($_GET['subdomain'])) {
    // Development/testing fallback
    $subdomain = preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['subdomain']));
}

if (empty($subdomain) || $subdomain === 'www') {
    http_response_code(404);
    showError('Demo Not Found', 'No demo project is associated with this address.');
    exit;
}

// Look up project by demo_subdomain
$project = null;
try {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.name AS client_name, u.email AS client_email
         FROM projects p
         LEFT JOIN users u ON u.id = p.client_id
         WHERE p.demo_subdomain = ? LIMIT 1"
    );
    $stmt->execute([$subdomain]);
    $project = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    showError('Server Error', 'Unable to load demo at this time.');
    exit;
}

if (!$project) {
    http_response_code(404);
    showError('Demo Not Found', 'No project demo is associated with <strong>' . htmlspecialchars($subdomain, ENT_QUOTES, 'UTF-8') . '.softandpix.com</strong>.');
    exit;
}

// Check demo_enabled
if (empty($project['demo_enabled'])) {
    showError('Demo Unavailable', 'The live demo for <strong>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</strong> is currently disabled.');
    exit;
}

// Check expiry
if (!empty($project['demo_expires_at']) && strtotime($project['demo_expires_at']) < time()) {
    showError('Demo Expired', 'The live demo for <strong>' . htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8') . '</strong> has expired on ' . date('F j, Y', strtotime($project['demo_expires_at'])) . '.');
    exit;
}

// ── Check if uploaded demo files exist ────────────────────────────────────────
$sitesBase  = __DIR__ . '/sites';
$siteDir    = $sitesBase . '/' . $subdomain;
$hasFiles   = !empty($project['demo_has_files']) && is_dir($siteDir);

// If uploaded files exist, serve the demo site directly
if ($hasFiles) {
    // Password protection applies to uploaded sites too
    $sessionKey = 'demo_auth_' . (int)$project['id'];
    if (!empty($project['demo_password'])) {
        if (empty($_SESSION[$sessionKey])) {
            require_once 'auth.php';
            exit;
        }
    }

    // Determine the requested path within the demo site
    $requestPath = $_GET['path'] ?? '';
    // Also support PATH_INFO and REQUEST_URI based routing
    if (empty($requestPath)) {
        $requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName  = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir   = dirname($scriptName); // e.g. /demo
        // Strip query string
        $uriPath     = strtok($requestUri, '?');
        // Remove the /demo prefix if present
        if ($scriptDir !== '/' && strpos($uriPath, $scriptDir) === 0) {
            $requestPath = substr($uriPath, strlen($scriptDir));
        } else {
            $requestPath = $uriPath;
        }
    }

    // Default to index.html or index.php
    $requestPath = ltrim($requestPath, '/');
    if ($requestPath === '' || $requestPath === 'index.php') {
        // Try index.html first, then index.php
        if (file_exists($siteDir . '/index.html')) {
            $requestPath = 'index.html';
        } elseif (file_exists($siteDir . '/index.php')) {
            $requestPath = 'index.php';
        } else {
            // Find any HTML file at root
            $htmlFiles = glob($siteDir . '/*.html');
            if (!empty($htmlFiles)) {
                $requestPath = basename($htmlFiles[0]);
            } else {
                showError('Demo Not Ready', 'The demo site has been uploaded but no index.html or index.php was found.');
                exit;
            }
        }
    }

    // Sanitize and build the full path; realpath() below performs the actual
    // security check against path traversal and symlinks.
    $requestPath = ltrim($requestPath, '/\\');

    $filePath = $siteDir . '/' . $requestPath;

    // Ensure the file is within the site directory
    $realSiteDir = realpath($siteDir);
    $realFilePath = realpath($filePath);

    if ($realFilePath === false || $realSiteDir === false ||
        strpos($realFilePath . '/', $realSiteDir . '/') !== 0) {
        http_response_code(404);
        showError('File Not Found', 'The requested file was not found in the demo.');
        exit;
    }

    if (!file_exists($realFilePath)) {
        http_response_code(404);
        showError('File Not Found', 'The requested file was not found in the demo.');
        exit;
    }

    // Serve the file with appropriate Content-Type
    $ext = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'html' => 'text/html; charset=UTF-8',
        'htm'  => 'text/html; charset=UTF-8',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'pdf'  => 'application/pdf',
        'txt'  => 'text/plain',
        'xml'  => 'text/xml',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mp3'  => 'audio/mpeg',
        'php'  => null, // PHP files executed via include
    ];

    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

    if ($ext === 'php') {
        // Include PHP file so it runs in this context
        header('X-Frame-Options: SAMEORIGIN');
        // Change working directory context for relative includes within the demo
        $origDir = getcwd();
        chdir(dirname($realFilePath));
        include $realFilePath;
        chdir($origDir);
    } else {
        // Serve static file
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($realFilePath));
        header('X-Frame-Options: SAMEORIGIN');
        readfile($realFilePath);
    }
    exit;
}

// ── Fallback: iframe viewer (existing behavior) ────────────────────────────────
if (empty($project['demo_url'])) {
    showError('Demo Not Configured', 'The live demo URL has not been set up yet. Please check back later.');
    exit;
}

// Password protection
$sessionKey = 'demo_auth_' . (int)$project['id'];
if (!empty($project['demo_password'])) {
    // Check if already authenticated
    if (empty($_SESSION[$sessionKey])) {
        // Show password form or handle auth POST
        require_once 'auth.php';
        exit;
    }
}

// All checks passed — render demo viewer
$title      = htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8');
$demoUrl    = htmlspecialchars($project['demo_url'], ENT_QUOTES, 'UTF-8');
$clientName = htmlspecialchars($project['client_name'] ?? '', ENT_QUOTES, 'UTF-8');
$deadline   = !empty($project['deadline']) ? date('F j, Y', strtotime($project['deadline'])) : null;
$statusLabels = [
    'pending'     => 'Pending',
    'in_progress' => 'In Progress',
    'on_hold'     => 'On Hold',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
];
$statusColors = [
    'pending'     => '#f59e0b',
    'in_progress' => '#3b82f6',
    'on_hold'     => '#6b7280',
    'completed'   => '#10b981',
    'cancelled'   => '#ef4444',
];
$status      = $project['status'] ?? 'pending';
$statusLabel = $statusLabels[$status] ?? ucfirst($status);
$statusColor = $statusColors[$status] ?? '#6b7280';

// Set X-Frame-Options to SAMEORIGIN so the demo viewer itself can be embedded
// by same-origin pages; the embedded demo_url uses iframe sandbox for security.
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?> — Live Demo | Softandpix</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.topbar { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #fff; padding: 0 20px; height: 52px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,.3); z-index: 10; }
.topbar-left { display: flex; align-items: center; gap: 14px; overflow: hidden; }
.topbar-brand { font-size: .78rem; color: rgba(255,255,255,.65); white-space: nowrap; }
.topbar-brand span { color: #fff; font-weight: 700; }
.topbar-divider { width: 1px; height: 24px; background: rgba(255,255,255,.25); flex-shrink: 0; }
.topbar-project { font-weight: 600; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 360px; }
.status-badge { display: inline-block; padding: 2px 10px; border-radius: 100px; font-size: .72rem; font-weight: 600; color: #fff; background: <?php echo $statusColor; ?>; white-space: nowrap; flex-shrink: 0; }
.topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.topbar-meta { font-size: .78rem; color: rgba(255,255,255,.75); white-space: nowrap; }
.topbar-meta strong { color: #fff; }
.powered { font-size: .72rem; color: rgba(255,255,255,.5); white-space: nowrap; }
.powered a { color: rgba(255,255,255,.75); text-decoration: none; }
.powered a:hover { color: #fff; }
.demo-frame { flex: 1; width: 100%; border: none; background: #fff; display: block; }
.pw-lock { display: none; }
@media (max-width: 600px) {
    .topbar-meta { display: none; }
    .topbar-project { max-width: 160px; }
}
</style>
</head>
<body>
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-brand">Powered by <span>Softandpix</span></div>
        <div class="topbar-divider"></div>
        <div class="topbar-project"><?php echo $title; ?></div>
        <span class="status-badge"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="topbar-right">
        <?php if ($clientName): ?>
        <div class="topbar-meta"><strong>Client:</strong> <?php echo $clientName; ?></div>
        <?php endif; ?>
        <?php if ($deadline): ?>
        <div class="topbar-meta"><strong>Deadline:</strong> <?php echo htmlspecialchars($deadline, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($project['demo_expires_at'])): ?>
        <div class="topbar-meta"><strong>Demo expires:</strong> <?php echo htmlspecialchars(date('M j, Y', strtotime($project['demo_expires_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="powered"><a href="https://softandpix.com" target="_blank">softandpix.com</a></div>
    </div>
</div>
<iframe
    class="demo-frame"
    src="<?php echo $demoUrl; ?>"
    title="<?php echo $title; ?> — Live Demo"
    sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
    allow="fullscreen"
    loading="lazy"
></iframe>
</body>
</html>
<?php

// ─── Helper: render a styled error page ───────────────────────────────────────
function showError(string $heading, string $message): void {
    http_response_code(http_response_code() ?: 404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?> — Softandpix</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,#1e3a5f,#2563eb); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.card { background:#fff; border-radius:16px; padding:48px 40px; max-width:440px; width:90%; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.icon { font-size:3rem; margin-bottom:16px; }
h1 { font-size:1.4rem; font-weight:700; color:#1e3a5f; margin-bottom:10px; }
p { color:#6b7280; line-height:1.6; font-size:.95rem; }
.brand { margin-top:28px; font-size:.8rem; color:#9ca3af; }
.brand a { color:#2563eb; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="card">
    <div class="icon">🔒</div>
    <h1><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo $message; ?></p>
    <div class="brand">Powered by <a href="https://softandpix.com">Softandpix</a></div>
</div>
</body>
</html>
    <?php
}
