<?php
/**
 * Chat Send Message API
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$conv_id = (int)($input['conversation_id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$conv_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// Verify user is participant
$check = $pdo->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id=? AND user_id=?");
$check->execute([$conv_id, $_SESSION['user_id']]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Insert message
$stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, ?, 'text')");
$stmt->execute([$conv_id, $_SESSION['user_id'], $message]);
$msg_id = $pdo->lastInsertId();

// Update sender's online status
update_online_status($pdo, $_SESSION['user_id']);

// Check if any participants are offline and send email notification
$participants = $pdo->prepare("SELECT u.* FROM conversation_participants cp JOIN users u ON cp.user_id=u.id WHERE cp.conversation_id=? AND cp.user_id != ?");
$participants->execute([$conv_id, $_SESSION['user_id']]);
while ($p = $participants->fetch()) {
    if (!$p['last_activity'] || strtotime($p['last_activity']) < time() - 300) {
        // User is offline, send notification email
        send_email($p['email'], $p['name'], 'New Message from ' . $_SESSION['user_name'], '<p>You have a new message from <strong>' . htmlspecialchars($_SESSION['user_name']) . '</strong>:</p><blockquote>' . htmlspecialchars($message) . '</blockquote><p><a href="' . BASE_URL . '/' . $p['role'] . '/chat.php?conv=' . $conv_id . '">View Chat</a></p>', 'support');
    }
}

echo json_encode(['success' => true, 'message_id' => $msg_id]);
