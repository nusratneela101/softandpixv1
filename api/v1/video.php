<?php
/**
 * API v1 — Video Call endpoints
 *
 * POST   /api/v1/video/create     — create room
 * POST   /api/v1/video/join       — join room
 * POST   /api/v1/video/leave      — leave room
 * GET    /api/v1/video/room       — get room details (?code=XXX)
 * GET    /api/v1/video/scheduled  — get user's scheduled meetings
 * POST   /api/v1/video/schedule   — schedule a meeting with invitations
 * POST   /api/v1/video/invite     — invite users to room
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

set_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$video_idx = array_search('video', $segments);
$sub_route = $segments[$video_idx + 1] ?? '';

switch ($method . ':' . $sub_route) {
    case 'POST:create':   api_create_room($pdo, $api_user); break;
    case 'POST:join':     api_join_room($pdo, $api_user); break;
    case 'POST:leave':    api_leave_room($pdo, $api_user); break;
    case 'GET:room':      api_get_room($pdo, $api_user); break;
    case 'GET:scheduled': api_get_scheduled($pdo, $api_user); break;
    case 'POST:schedule': api_schedule_meeting($pdo, $api_user); break;
    case 'POST:invite':   api_invite_users($pdo, $api_user); break;
    default:
        api_error('Video endpoint not found.', 404);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function api_generate_room_code(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 3; $i++) {
        if ($i > 0) $code .= '-';
        for ($j = 0; $j < 4; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $code;
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function api_create_room(PDO $pdo, array $api_user): never {
    api_rate_limit($pdo, 'api_video_create', 30, 60);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $room_name        = trim($input['room_name'] ?? 'Meeting');
    $room_type        = in_array($input['room_type'] ?? '', ['instant', 'scheduled', 'recurring']) ? $input['room_type'] : 'instant';
    $max_participants = max(2, min(6, (int)($input['max_participants'] ?? 6)));
    $description      = trim($input['description'] ?? '');
    $password         = !empty($input['password']) ? password_hash($input['password'], PASSWORD_DEFAULT) : null;
    $scheduled_at     = $input['scheduled_at'] ?? null;
    $scheduled_end    = $input['scheduled_end'] ?? null;

    $room_code = api_generate_room_code();
    $attempts = 0;
    while ($attempts < 10) {
        $check = $pdo->prepare("SELECT id FROM video_rooms WHERE room_code = ?");
        $check->execute([$room_code]);
        if (!$check->fetch()) break;
        $room_code = api_generate_room_code();
        $attempts++;
    }

    $pdo->prepare(
        "INSERT INTO video_rooms (room_code, room_name, room_type, created_by, max_participants, scheduled_at, scheduled_end, description, password)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    )->execute([$room_code, $room_name, $room_type, $api_user['user_id'], $max_participants, $scheduled_at, $scheduled_end, $description, $password]);
    $room_id = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO video_participants (room_id, user_id, role) VALUES (?, ?, 'host')")
        ->execute([$room_id, $api_user['user_id']]);

    log_activity($pdo, $api_user['user_id'], 'api_room_created', 'Created video room via API: ' . $room_name, 'video_room', $room_id);

    json_response(['success' => true, 'room_code' => $room_code, 'room_id' => $room_id], 201);
}

function api_join_room(PDO $pdo, array $api_user): never {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $room_code = trim($input['room_code'] ?? '');
    $password  = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM video_rooms WHERE room_code = ? LIMIT 1");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();

    if (!$room) api_error('Room not found.', 404);
    if (in_array($room['status'], ['ended', 'cancelled'])) api_error('Meeting has ended.');
    if (!empty($room['password']) && !password_verify($password, $room['password'])) {
        api_error('Invalid password.', 403);
    }

    $active = $pdo->prepare("SELECT COUNT(*) FROM video_participants WHERE room_id = ? AND left_at IS NULL");
    $active->execute([$room['id']]);
    $activeCount = (int)$active->fetchColumn();
    $existing = $pdo->prepare("SELECT id FROM video_participants WHERE room_id = ? AND user_id = ? AND left_at IS NULL");
    $existing->execute([$room['id'], $api_user['user_id']]);
    $existingRow = $existing->fetch();
    if ($activeCount >= $room['max_participants'] && !$existingRow) {
        api_error('Room is full.');
    }

    if (!$existingRow) {
        $role = ($room['created_by'] == $api_user['user_id']) ? 'host' : 'participant';
        $pdo->prepare("INSERT INTO video_participants (room_id, user_id, role) VALUES (?, ?, ?)")
            ->execute([$room['id'], $api_user['user_id'], $role]);
    }

    if ($room['status'] === 'waiting') {
        $pdo->prepare("UPDATE video_rooms SET status = 'active' WHERE id = ?")->execute([$room['id']]);
    }

    json_response(['success' => true, 'room' => [
        'id' => $room['id'], 'room_code' => $room['room_code'], 'room_name' => $room['room_name'],
        'status' => 'active', 'max_participants' => $room['max_participants']
    ]]);
}

function api_leave_room(PDO $pdo, array $api_user): never {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $room_code = trim($input['room_code'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM video_rooms WHERE room_code = ? LIMIT 1");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();
    if (!$room) api_error('Room not found.', 404);

    $pdo->prepare("UPDATE video_participants SET left_at = NOW() WHERE room_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$room['id'], $api_user['user_id']]);

    $remaining = $pdo->prepare("SELECT COUNT(*) FROM video_participants WHERE room_id = ? AND left_at IS NULL");
    $remaining->execute([$room['id']]);
    if ((int)$remaining->fetchColumn() === 0) {
        $pdo->prepare("UPDATE video_rooms SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$room['id']]);
    }

    json_response(['success' => true]);
}

function api_get_room(PDO $pdo, array $api_user): never {
    $code = trim($_GET['code'] ?? '');
    if (!$code) api_error('Room code required.');

    $stmt = $pdo->prepare("SELECT vr.*, u.name as host_name FROM video_rooms vr LEFT JOIN users u ON u.id=vr.created_by WHERE vr.room_code=? LIMIT 1");
    $stmt->execute([$code]);
    $room = $stmt->fetch();
    if (!$room) api_error('Room not found.', 404);

    $pStmt = $pdo->prepare("SELECT vp.user_id, vp.role, vp.is_muted, vp.is_video_on, u.name FROM video_participants vp LEFT JOIN users u ON u.id=vp.user_id WHERE vp.room_id=? AND vp.left_at IS NULL");
    $pStmt->execute([$room['id']]);

    json_response(['success' => true, 'room' => $room, 'participants' => $pStmt->fetchAll()]);
}

function api_get_scheduled(PDO $pdo, array $api_user): never {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT vr.*, u.name as host_name FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
         WHERE vr.status IN ('waiting','active')
           AND (vr.created_by = ? OR mi.invited_user_id = ?)
         ORDER BY COALESCE(vr.scheduled_at, vr.created_at) ASC"
    );
    $stmt->execute([$api_user['user_id'], $api_user['user_id']]);
    json_response(['success' => true, 'meetings' => $stmt->fetchAll()]);
}

function api_schedule_meeting(PDO $pdo, array $api_user): never {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $input['room_type'] = 'scheduled';

    // Re-use create logic
    $_POST = $input;
    $_POST['action'] = 'create_room';

    // Inline creation for API
    $room_name        = trim($input['room_name'] ?? 'Scheduled Meeting');
    $max_participants = max(2, min(6, (int)($input['max_participants'] ?? 6)));
    $scheduled_at     = $input['scheduled_at'] ?? null;
    $scheduled_end    = $input['scheduled_end'] ?? null;
    $description      = trim($input['description'] ?? '');
    $password         = !empty($input['password']) ? password_hash($input['password'], PASSWORD_DEFAULT) : null;

    $room_code = api_generate_room_code();
    $pdo->prepare(
        "INSERT INTO video_rooms (room_code, room_name, room_type, created_by, max_participants, scheduled_at, scheduled_end, description, password)
         VALUES (?, ?, 'scheduled', ?, ?, ?, ?, ?, ?)"
    )->execute([$room_code, $room_name, $api_user['user_id'], $max_participants, $scheduled_at, $scheduled_end, $description, $password]);
    $room_id = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO video_participants (room_id, user_id, role) VALUES (?, ?, 'host')")
        ->execute([$room_id, $api_user['user_id']]);

    // Invite users
    $invite_users = $input['invite_users'] ?? [];
    if (is_array($invite_users)) {
        $roomStmt = $pdo->prepare("SELECT * FROM video_rooms WHERE id=?");
        $roomStmt->execute([$room_id]);
        $room = $roomStmt->fetch();
        foreach ($invite_users as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;
            $pdo->prepare("INSERT INTO meeting_invitations (room_id, invited_by, invited_user_id) VALUES (?,?,?)")
                ->execute([$room_id, $api_user['user_id'], $uid]);
            create_notification($pdo, $uid, 'meeting', 'Meeting Invitation', 'You are invited to: ' . $room_name, '/video/room.php?code=' . $room_code);
        }
    }

    json_response(['success' => true, 'room_code' => $room_code, 'room_id' => $room_id], 201);
}

function api_invite_users(PDO $pdo, array $api_user): never {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $room_code    = trim($input['room_code'] ?? '');
    $invite_users = $input['invite_users'] ?? [];

    $stmt = $pdo->prepare("SELECT * FROM video_rooms WHERE room_code=? LIMIT 1");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();
    if (!$room) api_error('Room not found.', 404);

    $invited = 0;
    if (is_array($invite_users)) {
        foreach ($invite_users as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) continue;
            $check = $pdo->prepare("SELECT id FROM meeting_invitations WHERE room_id=? AND invited_user_id=?");
            $check->execute([$room['id'], $uid]);
            if ($check->fetch()) continue;

            $pdo->prepare("INSERT INTO meeting_invitations (room_id, invited_by, invited_user_id) VALUES (?,?,?)")
                ->execute([$room['id'], $api_user['user_id'], $uid]);

            // Send email
            $invitee = get_user($pdo, $uid);
            $inviter = get_user($pdo, $api_user['user_id']);
            if ($invitee && $inviter) {
                $site_url = defined('SITE_URL') ? SITE_URL : '';
                $body = '<h2>Meeting Invitation</h2>';
                $body .= '<p>' . htmlspecialchars($inviter['name']) . ' invited you to <strong>' . htmlspecialchars($room['room_name']) . '</strong></p>';
                $body .= '<p><a href="' . htmlspecialchars($site_url . '/video/room.php?code=' . $room['room_code']) . '">Join Meeting</a></p>';
                send_email($invitee['email'], $invitee['name'], 'Meeting Invitation: ' . $room['room_name'], $body, 'info');
            }

            create_notification($pdo, $uid, 'meeting', 'Meeting Invitation', 'You are invited to: ' . $room['room_name'], '/video/room.php?code=' . $room['room_code']);
            $invited++;
        }
    }

    json_response(['success' => true, 'invited' => $invited]);
}
