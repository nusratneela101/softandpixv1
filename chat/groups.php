<?php
/**
 * Group Chat Management API
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

if ($_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;
$action = $input['action'] ?? '';

if ($action === 'create') {
    $name = trim($input['name'] ?? '');
    $members = $input['members'] ?? [];
    
    if (!$name || empty($members)) {
        echo json_encode(['success' => false, 'message' => 'Name and members required']);
        exit;
    }
    
    $pdo->prepare("INSERT INTO conversations (type, name, created_by) VALUES ('group', ?, ?)")->execute([$name, $_SESSION['user_id']]);
    $conv_id = $pdo->lastInsertId();
    
    // Add admin as participant
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)")->execute([$conv_id, $_SESSION['user_id']]);
    
    // Add members
    foreach ($members as $uid) {
        $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)")->execute([$conv_id, (int)$uid]);
    }
    
    // System message
    $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, 'Group created', 'system')")->execute([$conv_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'conversation_id' => $conv_id]);
} elseif ($action === 'add_member') {
    $conv_id = (int)($input['conversation_id'] ?? 0);
    $user_id = (int)($input['user_id'] ?? 0);
    
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)")->execute([$conv_id, $user_id]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
