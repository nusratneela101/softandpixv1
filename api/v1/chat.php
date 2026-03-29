<?php
/**
 * API v1 — Chat endpoints
 *
 * GET    /api/v1/chat/messages?user_id={id}
 * POST   /api/v1/chat/send
 * GET    /api/v1/chat/groups
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$chat_idx  = array_search('chat', $segments);
$sub_route = $segments[$chat_idx + 1] ?? '';

switch ($method . ':' . $sub_route) {
    case 'GET:messages':
        get_messages($pdo, $api_user);
        break;
    case 'POST:send':
        send_message($pdo, $api_user);
        break;
    case 'GET:groups':
        get_groups($pdo, $api_user);
        break;
    default:
        api_error('Chat endpoint not found.', 404);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function get_messages(PDO $pdo, array $api_user): never {
    $other_user_id     = (int)($_GET['user_id'] ?? 0);
    $conversation_id   = (int)($_GET['conversation_id'] ?? 0);

    if ($other_user_id > 0) {
        // Find direct conversation between the two users.
        $stmt = $pdo->prepare(
            "SELECT c.id FROM conversations c
             JOIN conversation_participants cp1 ON cp1.conversation_id = c.id AND cp1.user_id = ?
             JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id = ?
             WHERE c.type = 'direct' LIMIT 1"
        );
        $stmt->execute([$api_user['user_id'], $other_user_id]);
        $conv = $stmt->fetch();
        if (!$conv) {
            json_response(['success' => true, 'data' => []]);
        }
        $conversation_id = (int)$conv['id'];
    }

    if ($conversation_id <= 0) {
        api_error('Provide user_id or conversation_id.');
    }

    // Ensure the requesting user is a participant.
    $check = $pdo->prepare(
        "SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1"
    );
    $check->execute([$conversation_id, $api_user['user_id']]);
    if (!$check->fetch()) {
        api_error('Access denied.', 403);
    }

    $limit  = min((int)($_GET['limit'] ?? 50), 200);
    $before = (int)($_GET['before_id'] ?? 0);

    $sql    = "SELECT m.*, u.name AS sender_name, u.avatar AS sender_avatar
               FROM messages m
               LEFT JOIN users u ON u.id = m.sender_id
               WHERE m.conversation_id = ?";
    $params = [$conversation_id];

    if ($before > 0) {
        $sql    .= ' AND m.id < ?';
        $params[] = $before;
    }

    $sql .= ' ORDER BY m.created_at DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = array_reverse($stmt->fetchAll());

    // Mark messages as read.
    $pdo->prepare(
        "UPDATE messages SET is_read = 1
         WHERE conversation_id = ? AND sender_id != ? AND is_read = 0"
    )->execute([$conversation_id, $api_user['user_id']]);

    json_response(['success' => true, 'data' => $messages, 'conversation_id' => $conversation_id]);
}

function send_message(PDO $pdo, array $api_user): never {
    api_rate_limit($pdo, 'api_chat_send', 120, 60);

    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($input['message'] ?? '');

    if (empty($message)) {
        api_error('Message text is required.');
    }

    $conversation_id = (int)($input['conversation_id'] ?? 0);
    $recipient_id    = (int)($input['recipient_id'] ?? 0);

    if ($conversation_id <= 0 && $recipient_id <= 0) {
        api_error('Provide conversation_id or recipient_id.');
    }

    if ($conversation_id <= 0) {
        // Find or create a direct conversation.
        $stmt = $pdo->prepare(
            "SELECT c.id FROM conversations c
             JOIN conversation_participants cp1 ON cp1.conversation_id = c.id AND cp1.user_id = ?
             JOIN conversation_participants cp2 ON cp2.conversation_id = c.id AND cp2.user_id = ?
             WHERE c.type = 'direct' LIMIT 1"
        );
        $stmt->execute([$api_user['user_id'], $recipient_id]);
        $conv = $stmt->fetch();

        if ($conv) {
            $conversation_id = (int)$conv['id'];
        } else {
            $pdo->prepare(
                "INSERT INTO conversations (type, created_by, created_at) VALUES ('direct', ?, NOW())"
            )->execute([$api_user['user_id']]);
            $conversation_id = (int)$pdo->lastInsertId();

            $part_stmt = $pdo->prepare(
                "INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)"
            );
            $part_stmt->execute([$conversation_id, $api_user['user_id']]);
            $part_stmt->execute([$conversation_id, $recipient_id]);
        }
    }

    // Confirm user is a participant.
    $check = $pdo->prepare(
        "SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1"
    );
    $check->execute([$conversation_id, $api_user['user_id']]);
    if (!$check->fetch()) {
        api_error('Access denied.', 403);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO messages (conversation_id, sender_id, message, message_type, created_at)
         VALUES (?, ?, ?, 'text', NOW())"
    );
    $stmt->execute([$conversation_id, $api_user['user_id'], $message]);
    $message_id = (int)$pdo->lastInsertId();

    json_response(['success' => true, 'id' => $message_id, 'conversation_id' => $conversation_id], 201);
}

function get_groups(PDO $pdo, array $api_user): never {
    $stmt = $pdo->prepare(
        "SELECT c.*, COUNT(cp2.user_id) AS member_count
         FROM conversations c
         JOIN conversation_participants cp ON cp.conversation_id = c.id AND cp.user_id = ?
         LEFT JOIN conversation_participants cp2 ON cp2.conversation_id = c.id
         WHERE c.type = 'group'
         GROUP BY c.id
         ORDER BY c.created_at DESC"
    );
    $stmt->execute([$api_user['user_id']]);

    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}
