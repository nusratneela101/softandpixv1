<?php
/**
 * api/chat/send.php
 * POST — send a chat message with CSRF verification and rate limiting.
 * Supports text, link, and system message types (file/image via upload.php).
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

// CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']); exit;
}

$convId  = (int)($_POST['conversation_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$msgType = in_array($_POST['message_type'] ?? 'text', ['text','link','system']) ? ($_POST['message_type'] ?? 'text') : 'text';

if (!$convId || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Missing data']); exit;
}

// Sanitise
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

try {
    // Verify participant (admin always allowed)
    if (!$isAdmin) {
        $check = $pdo->prepare("SELECT id FROM chat_participants WHERE conversation_id=? AND user_id=?");
        $check->execute([$convId, $userId]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Not a participant']); exit;
        }
    }

    // Rate limiting: max 1 message per second per user
    $rateKey = 'chat_rate_' . ($isAdmin ? 'admin' : $userId);
    $lastSent = $_SESSION[$rateKey] ?? 0;
    if ((time() - $lastSent) < 1) {
        echo json_encode(['success' => false, 'error' => 'Too fast, slow down']); exit;
    }
    $_SESSION[$rateKey] = time();

    // Insert message
    $stmt = $pdo->prepare(
        "INSERT INTO chat_messages (conversation_id, sender_id, message, message_type)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$convId, $userId, $message, $msgType]);
    $msgId = (int)$pdo->lastInsertId();

    // Update conversation updated_at
    $pdo->prepare("UPDATE chat_conversations SET updated_at=NOW() WHERE id=?")->execute([$convId]);

    // Create notifications for other participants
    $parts = $pdo->prepare("SELECT user_id FROM chat_participants WHERE conversation_id=? AND user_id!=?");
    $parts->execute([$convId, $userId]);
    $senderName = $isAdmin ? 'Admin' : htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
    foreach ($parts->fetchAll() as $p) {
        if ($p['user_id'] == 0) continue; // skip admin notification (admin sees all)
        $notifMsg = $senderName . ': ' . mb_substr(strip_tags($message), 0, 60);
        $pdo->prepare(
            "INSERT INTO notifications (user_id, type, message, link)
             VALUES (?, 'chat', ?, ?)"
        )->execute([$p['user_id'], $notifMsg, '/client/chat.php?conv=' . $convId]);
    }

    // Fetch the inserted message back
    $fetch = $pdo->prepare(
        "SELECT id, conversation_id, sender_id, message, message_type, created_at
         FROM chat_messages WHERE id=?"
    );
    $fetch->execute([$msgId]);
    $msg = $fetch->fetch();

    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}

