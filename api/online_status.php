<?php
/**
 * Online Status Tracking API
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

// Update current user's activity
update_online_status($pdo, $_SESSION['user_id']);

// Get online users
$stmt = $pdo->query("SELECT id, name, role, last_activity, CASE WHEN last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online FROM users WHERE is_active=1");
$users = $stmt->fetchAll();

echo json_encode(['success' => true, 'users' => $users]);
