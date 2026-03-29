<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

$folder = $_GET['folder'] ?? 'inbox';
$last_id = (int)($_GET['last_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM emails WHERE folder=? AND (from_user_id=? OR to_user_id=?) AND id > ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$folder, $_SESSION['user_id'], $_SESSION['user_id'], $last_id]);
echo json_encode(['success' => true, 'emails' => $stmt->fetchAll()]);
