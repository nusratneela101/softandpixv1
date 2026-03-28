<?php
/**
 * api/project/demo_check.php
 * GET  ?subdomain=<value>[&exclude_id=<project_id>]
 * Returns JSON: {"available": true/false, "message": "..."}
 */
session_start();
require_once '../../config/db.php';
require_once '../../admin/includes/auth.php';

header('Content-Type: application/json');

// Only admins may use this endpoint
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['available' => false, 'message' => 'Unauthorized']);
    exit;
}

$subdomain = strtolower(trim($_GET['subdomain'] ?? ''));
$excludeId = (int)($_GET['exclude_id'] ?? 0);

// Validate format: only lowercase letters, digits, hyphens; 3–50 chars
if (!preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/', $subdomain) && !preg_match('/^[a-z0-9]{3,50}$/', $subdomain)) {
    echo json_encode(['available' => false, 'message' => 'Subdomain must be 3–50 characters and contain only lowercase letters, numbers, and hyphens.']);
    exit;
}

// Reserved subdomains
$reserved = ['www', 'mail', 'ftp', 'admin', 'api', 'app', 'dev', 'test', 'stage', 'staging', 'demo', 'blog', 'shop', 'help', 'support'];
if (in_array($subdomain, $reserved)) {
    echo json_encode(['available' => false, 'message' => 'This subdomain is reserved and cannot be used.']);
    exit;
}

// Check uniqueness in DB
try {
    $sql    = "SELECT COUNT(*) FROM projects WHERE demo_subdomain = ?";
    $params = [$subdomain];
    if ($excludeId > 0) {
        $sql    .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['available' => false, 'message' => 'Database error.']);
    exit;
}

if ($count > 0) {
    echo json_encode(['available' => false, 'message' => 'This subdomain is already in use by another project.']);
} else {
    echo json_encode(['available' => true, 'message' => $subdomain . '.softandpix.com is available!']);
}
