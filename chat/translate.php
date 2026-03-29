<?php
/**
 * Chat Translation AJAX Endpoint
 *
 * POST /chat/translate.php
 * Params: csrf_token, message_id, target_lang
 * Returns: { "success": true, "translated": "...", "source_lang": "en" }
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// CSRF validation
$csrf = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
verify_csrf_token($csrf);

$message_id  = (int)($input['message_id'] ?? 0);
$target_lang = trim($input['target_lang'] ?? '');

if (!$message_id || !$target_lang) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Rate limiting: 60 translations per user per 5 minutes
require_once BASE_PATH . '/includes/rate_limiter.php';
$rate = check_rate_limit($pdo, 'chat_translate_' . (int)$_SESSION['user_id'], 60, 5);
if (!$rate['allowed']) {
    echo json_encode(['success' => false, 'error' => 'Too many requests. Please wait a moment.']);
    exit;
}

// Fetch the message (support both table names used in the codebase)
$msg = null;
try {
    $stmt = $pdo->prepare(
        "SELECT id, message, message_type FROM chat_messages WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch();
} catch (Exception $e) {
    // Fallback to old table name
    try {
        $stmt = $pdo->prepare(
            "SELECT id, message, message_type FROM messages WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$message_id]);
        $msg = $stmt->fetch();
    } catch (Exception $e2) {
        // both tables failed
    }
}

if (!$msg) {
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

// Only translate plain text messages
$type = $msg['message_type'] ?? 'text';
if (!in_array($type, ['text', 'ai', ''], true)) {
    echo json_encode(['success' => false, 'error' => 'Cannot translate this message type']);
    exit;
}

$message_text = $msg['message'];
if (empty(trim($message_text))) {
    echo json_encode(['success' => true, 'translated' => '', 'source_lang' => 'auto']);
    exit;
}

require_once BASE_PATH . '/includes/chat_translate.php';
$result = translate_message($pdo, $message_id, $message_text, $target_lang);

echo json_encode($result);
