<?php
/**
 * api/chat/mark_read.php
 * POST — mark all messages in a conversation as read for the current user.
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

$isAdmin = isset($_SESSION['admin_id']);
$userId  = $isAdmin ? 0 : (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']); exit;
}

$convId = (int)($_POST['conversation_id'] ?? 0);
if (!$convId) {
    echo json_encode(['success' => false, 'error' => 'Missing conversation_id']); exit;
}

try {
    // Mark messages as read (only those not sent by the current user)
    $pdo->prepare(
        "UPDATE chat_messages SET is_read=1
         WHERE conversation_id=? AND sender_id!=? AND is_read=0"
    )->execute([$convId, $userId]);

    // Update last_read_at in participants
    $pdo->prepare(
        "INSERT INTO chat_participants (conversation_id, user_id, role, last_read_at)
         VALUES (?, ?, IF(?=0,'admin','client'), NOW())
         ON DUPLICATE KEY UPDATE last_read_at=NOW()"
    )->execute([$convId, $userId, $userId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
