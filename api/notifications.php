<?php
/**
 * Notification API Endpoints
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'fetch';

if ($action === 'fetch') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
    $unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $unread->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread->fetchColumn()]);
} elseif ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $_SESSION['user_id']]);
    } else {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
