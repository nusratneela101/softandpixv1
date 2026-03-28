<?php
/**
 * api/chat/typing.php
 * POST — update typing indicator for the current user in a conversation.
 * Auto-expires after 5 seconds (poll.php filters by typing_updated_at).
 */
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

$isAdmin = isset($_SESSION['admin_id']);
$userId  = $isAdmin ? 0 : (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$userId) {
    echo json_encode(['success' => false]); exit;
}

$convId   = (int)($_POST['conversation_id'] ?? 0);
$isTyping = (int)(($_POST['is_typing'] ?? 0) ? 1 : 0);

if (!$convId) {
    echo json_encode(['success' => false]); exit;
}

try {
    $pdo->prepare(
        "INSERT INTO chat_participants (conversation_id, user_id, role, is_typing, typing_updated_at)
         VALUES (?, ?, IF(?=0,'admin','client'), ?, NOW())
         ON DUPLICATE KEY UPDATE is_typing=VALUES(is_typing), typing_updated_at=NOW()"
    )->execute([$convId, $userId, $userId, $isTyping]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
