<?php
/**
 * api/chat/poll.php
 * GET — long-polling endpoint. Returns new messages since last_id,
 *       updates last_read_at, and returns typing indicators.
 */
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');

$isAdmin = isset($_SESSION['admin_id']);
$userId  = $isAdmin ? 0 : (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$userId) {
    echo json_encode(['messages' => [], 'typing' => []]); exit;
}

$convId = (int)($_GET['conversation_id'] ?? 0);
$lastId = (int)($_GET['last_id'] ?? 0);

if (!$convId) {
    echo json_encode(['messages' => [], 'typing' => []]); exit;
}

try {
    // Verify participant (admin always allowed)
    if (!$isAdmin) {
        $check = $pdo->prepare("SELECT id FROM chat_participants WHERE conversation_id=? AND user_id=?");
        $check->execute([$convId, $userId]);
        if (!$check->fetch()) {
            echo json_encode(['messages' => [], 'typing' => []]); exit;
        }
    }

    // Fetch new messages
    $stmt = $pdo->prepare(
        "SELECT m.id, m.conversation_id, m.sender_id, m.message, m.message_type,
                m.file_path, m.file_name, m.file_size, m.is_read, m.created_at,
                COALESCE(u.name, IF(m.sender_id=0,'Admin','Unknown')) AS sender_name
         FROM chat_messages m
         LEFT JOIN users u ON u.id = m.sender_id AND m.sender_id > 0
         WHERE m.conversation_id=? AND m.id>? AND m.is_deleted=0
         ORDER BY m.created_at ASC LIMIT 50"
    );
    $stmt->execute([$convId, $lastId]);
    $messages = $stmt->fetchAll();

    // Update last_read_at for the polling user
    if ($isAdmin) {
        $pdo->prepare(
            "INSERT INTO chat_participants (conversation_id, user_id, role, last_read_at)
             VALUES (?, 0, 'admin', NOW())
             ON DUPLICATE KEY UPDATE last_read_at=NOW()"
        )->execute([$convId]);
        // Mark messages as read for admin
        $pdo->prepare(
            "UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_id!=0"
        )->execute([$convId]);
    } else {
        $pdo->prepare(
            "UPDATE chat_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?"
        )->execute([$convId, $userId]);
        // Mark messages as read
        $pdo->prepare(
            "UPDATE chat_messages SET is_read=1
             WHERE conversation_id=? AND sender_id!=? AND is_read=0"
        )->execute([$convId, $userId]);
    }

    // Typing indicators (auto-expire after 5 seconds)
    $typingStmt = $pdo->prepare(
        "SELECT cp.user_id, COALESCE(u.name, IF(cp.user_id=0,'Admin','User')) AS name
         FROM chat_participants cp
         LEFT JOIN users u ON u.id=cp.user_id AND cp.user_id>0
         WHERE cp.conversation_id=? AND cp.user_id!=?
           AND cp.is_typing=1
           AND cp.typing_updated_at > (NOW() - INTERVAL 5 SECOND)"
    );
    $typingStmt->execute([$convId, $userId]);
    $typing = $typingStmt->fetchAll();

    echo json_encode([
        'messages' => $messages,
        'typing'   => $typing,
    ]);
} catch (Exception $e) {
    echo json_encode(['messages' => [], 'typing' => []]);
}
