<?php
/**
 * API v1 — Notifications endpoints
 *
 * GET    /api/v1/notifications
 * PUT    /api/v1/notifications/{id}/read
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$notif_idx       = array_search('notifications', $segments);
$notification_id = isset($segments[$notif_idx + 1]) ? (int)$segments[$notif_idx + 1] : null;
$sub_route       = $segments[$notif_idx + 2] ?? '';

if ($notification_id && $notification_id <= 0) {
    api_error('Invalid notification ID.', 400);
}

if ($method === 'GET' && !$notification_id) {
    list_notifications($pdo, $api_user);
} elseif ($method === 'PUT' && $notification_id && $sub_route === 'read') {
    mark_as_read($pdo, $api_user, $notification_id);
} else {
    api_error('Notifications endpoint not found.', 404);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function list_notifications(PDO $pdo, array $api_user): never {
    $unread_only = isset($_GET['unread']) && $_GET['unread'] === '1';
    $limit       = min((int)($_GET['limit'] ?? 20), 100);

    $sql    = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$api_user['user_id']];

    if ($unread_only) {
        $sql .= ' AND is_read = 0';
    }

    $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Also return the unread count.
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $count_stmt->execute([$api_user['user_id']]);
    $unread_count = (int)$count_stmt->fetchColumn();

    json_response([
        'success'      => true,
        'data'         => $notifications,
        'unread_count' => $unread_count,
    ]);
}

function mark_as_read(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare("SELECT id, user_id FROM notifications WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $notification = $stmt->fetch();

    if (!$notification) {
        api_error('Notification not found.', 404);
    }

    if ((int)$notification['user_id'] !== (int)$api_user['user_id'] && $api_user['role'] !== 'admin') {
        api_error('Access denied.', 403);
    }

    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$id]);

    json_response(['success' => true, 'message' => 'Notification marked as read.']);
}
