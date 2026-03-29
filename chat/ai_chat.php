<?php
/**
 * AI Chatbot Logic
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = strtolower(trim($input['message'] ?? ''));
$conv_id = (int)($input['conversation_id'] ?? 0);

if (!$message || !$conv_id) {
    echo json_encode(['success' => false]);
    exit;
}

// Get AI response from chatbot_rules
$rules = $pdo->query("SELECT * FROM chatbot_rules WHERE is_active=1 ORDER BY priority DESC")->fetchAll();
$response = "Thank you for your message! An admin will be with you shortly.";

foreach ($rules as $rule) {
    $keywords = array_map('trim', explode(',', strtolower($rule['keywords'])));
    foreach ($keywords as $keyword) {
        if (strpos($message, $keyword) !== false) {
            $response = $rule['response'];
            break 2;
        }
    }
}

// Find or create AI user (system user for AI messages)
$ai_user = $pdo->query("SELECT id FROM users WHERE email='ai@system.local'")->fetch();
if (!$ai_user) {
    $random_pass = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (name, email, password, role, is_active) VALUES ('AI Assistant', 'ai@system.local', ?, 'client', 0)")->execute([$random_pass]);
    $ai_user_id = $pdo->lastInsertId();
} else {
    $ai_user_id = $ai_user['id'];
}

// Insert AI response
$stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, message_type) VALUES (?, ?, ?, 'ai')");
$stmt->execute([$conv_id, $ai_user_id, $response]);

echo json_encode(['success' => true, 'response' => $response]);
