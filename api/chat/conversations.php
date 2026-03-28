<?php
/**
 * api/chat/conversations.php
 * GET — list conversations for the logged-in user (admin sees all).
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

$isAdmin = isset($_SESSION['admin_id']);
$userId  = $isAdmin ? 0 : (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    if ($isAdmin) {
        $stmt = $pdo->query(
            "SELECT c.*,
                (SELECT COUNT(*) FROM chat_messages m
                    WHERE m.conversation_id=c.id AND m.is_read=0 AND m.sender_id!=0) AS unread_count,
                (SELECT m2.message FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                (SELECT m2.message_type FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message_type,
                (SELECT m2.created_at FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message_at
            FROM chat_conversations c
            ORDER BY c.updated_at DESC LIMIT 100"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT c.*,
                (SELECT COUNT(*) FROM chat_messages m
                    WHERE m.conversation_id=c.id AND m.is_read=0 AND m.sender_id!=?) AS unread_count,
                (SELECT m2.message FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
                (SELECT m2.message_type FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message_type,
                (SELECT m2.created_at FROM chat_messages m2
                    WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                    ORDER BY m2.created_at DESC LIMIT 1) AS last_message_at
            FROM chat_conversations c
            INNER JOIN chat_participants cp ON cp.conversation_id=c.id AND cp.user_id=?
            ORDER BY c.updated_at DESC LIMIT 100"
        );
        $stmt->execute([$userId, $userId]);
    }

    $conversations = $stmt->fetchAll();

    // Attach participant info (names) for each conversation
    $convIds = array_column($conversations, 'id');
    $participants = [];
    if (!empty($convIds)) {
        $placeholders = implode(',', array_fill(0, count($convIds), '?'));
        $pstmt = $pdo->prepare(
            "SELECT cp.conversation_id, cp.user_id, cp.role, u.name, u.email
             FROM chat_participants cp
             LEFT JOIN users u ON u.id=cp.user_id
             WHERE cp.conversation_id IN ($placeholders)"
        );
        $pstmt->execute($convIds);
        foreach ($pstmt->fetchAll() as $p) {
            $participants[$p['conversation_id']][] = $p;
        }
    }

    foreach ($conversations as &$conv) {
        $conv['participants'] = $participants[$conv['id']] ?? [];
        // Build display title: use stored title or derive from participants
        if (empty($conv['title'])) {
            $names = array_map(function ($p) use ($userId, $isAdmin) {
                if ($p['user_id'] == 0) return 'Admin';
                if (!$isAdmin && $p['user_id'] == $userId) return null;
                return $p['name'] ?? ('User #' . $p['user_id']);
            }, $conv['participants']);
            $names = array_filter($names);
            $conv['display_title'] = implode(', ', $names) ?: ('Conversation #' . $conv['id']);
        } else {
            $conv['display_title'] = $conv['title'];
        }
        $conv['last_message_preview'] = mb_substr($conv['last_message'] ?? '', 0, 60);
        if ($conv['last_message_type'] === 'image') $conv['last_message_preview'] = '📷 Photo';
        if ($conv['last_message_type'] === 'file')  $conv['last_message_preview'] = '📎 File';
    }
    unset($conv);

    echo json_encode(['success' => true, 'conversations' => $conversations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
}
