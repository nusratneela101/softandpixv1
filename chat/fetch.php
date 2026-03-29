<?php
/**
 * Chat Fetch Messages API
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

$conv_id = (int)($_GET['conversation_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);

if (!$conv_id) {
    echo json_encode(['success' => false]);
    exit;
}

// Auto-translate preference: update session if client sends the parameter
if (isset($_GET['auto_translate'])) {
    $_SESSION['chat_auto_translate'] = ($_GET['auto_translate'] === '1') ? '1' : '0';
}
if (!empty($_GET['target_lang'])) {
    $tl = trim($_GET['target_lang']);
    $supported = defined('SUPPORTED_LANGS') ? SUPPORTED_LANGS : ['en','bn','fr','pa','zh','zh_tw','es','tl','ar','it','de','pt'];
    if (in_array($tl, $supported, true)) {
        $_SESSION['chat_translate_lang'] = $tl;
    }
}

$auto_translate = ($_SESSION['chat_auto_translate'] ?? '0') === '1';
$target_lang    = $_SESSION['chat_translate_lang'] ?? current_lang();

$stmt = $pdo->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.conversation_id=? AND m.id > ? ORDER BY m.created_at ASC");
$stmt->execute([$conv_id, $last_id]);
$messages = $stmt->fetchAll();

foreach ($messages as &$m) {
    $m['time'] = time_ago($m['created_at']);
}

// Auto-translate incoming messages (not sent by the current user)
if ($auto_translate && !empty($messages)) {
    require_once BASE_PATH . '/includes/chat_translate.php';
    foreach ($messages as &$m) {
        $sender_id = (int)($m['sender_id'] ?? -1);
        $msg_type  = $m['message_type'] ?? 'text';
        if ($sender_id !== (int)$_SESSION['user_id']
            && in_array($msg_type, ['text', 'ai', ''], true)
            && !empty(trim($m['message'] ?? ''))
        ) {
            $tr = translate_message($pdo, (int)$m['id'], $m['message'], $target_lang);
            if ($tr['success'] && $tr['translated'] !== $m['message']) {
                $m['translated_text'] = $tr['translated'];
                $m['source_lang']     = $tr['source_lang'];
                $m['target_lang']     = $target_lang;
            }
        }
    }
    unset($m);
}

// Mark messages as read
$pdo->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND sender_id != ? AND is_read=0")->execute([$conv_id, $_SESSION['user_id']]);

// Update online status
update_online_status($pdo, $_SESSION['user_id']);

echo json_encode(['success' => true, 'messages' => $messages]);
