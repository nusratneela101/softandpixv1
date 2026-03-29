<?php
/**
 * API v1 Router
 *
 * Parses the request URI and delegates to the appropriate handler file.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';

set_cors_headers();

// Handle preflight requests immediately.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

// Determine path relative to /api/v1/
$request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
$script_name  = dirname($_SERVER['SCRIPT_NAME'] ?? '/api/v1/index.php');

// Strip query string.
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove the base path prefix so we get only the segment after /api/v1
$base = rtrim($script_name, '/');
if (!empty($base) && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . ltrim($path, '/');

// Determine the first path segment (the resource group).
$segments = array_values(array_filter(explode('/', $path)));
$resource = $segments[0] ?? '';

switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/auth.php';
        break;
    case 'projects':
        require_once __DIR__ . '/projects.php';
        break;
    case 'tasks':
        require_once __DIR__ . '/tasks.php';
        break;
    case 'invoices':
        require_once __DIR__ . '/invoices.php';
        break;
    case 'chat':
        require_once __DIR__ . '/chat.php';
        break;
    case 'time':
        require_once __DIR__ . '/time_tracking.php';
        break;
    case 'notifications':
        require_once __DIR__ . '/notifications.php';
        break;
    default:
        api_error('Endpoint not found.', 404);
}
