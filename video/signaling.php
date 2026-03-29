<?php
/**
 * Video Call Signaling Server — AJAX-polling signaling endpoint.
 *
 * Actions: create_room, join_room, leave_room, send_signal, get_signals,
 *          send_chat, get_chat, get_participants, toggle_mute, toggle_video,
 *          toggle_screen, end_room, schedule_meeting, get_scheduled, invite_users
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/activity_logger.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? '';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_room':      create_room($pdo, $user_id, $user_name); break;
    case 'join_room':        join_room($pdo, $user_id, $user_name); break;
    case 'leave_room':       leave_room($pdo, $user_id, $user_name); break;
    case 'send_signal':      send_signal($pdo, $user_id); break;
    case 'get_signals':      get_signals($pdo, $user_id); break;
    case 'send_chat':        send_chat($pdo, $user_id); break;
    case 'get_chat':         get_chat($pdo, $user_id); break;
    case 'get_participants': get_participants($pdo, $user_id); break;
    case 'toggle_mute':      toggle_mute($pdo, $user_id); break;
    case 'toggle_video':     toggle_video($pdo, $user_id); break;
    case 'toggle_screen':    toggle_screen($pdo, $user_id); break;
    case 'end_room':         end_room($pdo, $user_id, $user_name); break;
    case 'schedule_meeting': schedule_meeting($pdo, $user_id, $user_name); break;
    case 'get_scheduled':    get_scheduled($pdo, $user_id); break;
    case 'invite_users':     invite_users($pdo, $user_id, $user_name); break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function generate_room_code(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 3; $i++) {
        if ($i > 0) $code .= '-';
        for ($j = 0; $j < 4; $j++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
    }
    return $code;
}

function get_room_by_code(PDO $pdo, string $code): ?array {
    $stmt = $pdo->prepare("SELECT * FROM video_rooms WHERE room_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $room = $stmt->fetch();
    return $room ?: null;
}

function send_meeting_invitation_email(PDO $pdo, int $room_id, int $invited_by, int $invited_user_id, array $room): void {
    $inviter = get_user($pdo, $invited_by);
    $invitee = get_user($pdo, $invited_user_id);
    if (!$inviter || !$invitee) return;

    $site_url = defined('SITE_URL') ? SITE_URL : '';
    $join_link = $site_url . '/video/room.php?code=' . urlencode($room['room_code']);

    $email_body = '<h2>Meeting Invitation</h2>';
    $email_body .= '<p>You have been invited to a video meeting by <strong>' . htmlspecialchars($inviter['name']) . '</strong>.</p>';
    $email_body .= '<p><strong>Meeting:</strong> ' . htmlspecialchars($room['room_name']) . '</p>';
    if (!empty($room['scheduled_at'])) {
        $email_body .= '<p><strong>Scheduled:</strong> ' . date('M j, Y g:i A', strtotime($room['scheduled_at'])) . '</p>';
    }
    if (!empty($room['scheduled_end'])) {
        $email_body .= '<p><strong>Ends:</strong> ' . date('M j, Y g:i A', strtotime($room['scheduled_end'])) . '</p>';
    }
    if (!empty($room['description'])) {
        $email_body .= '<p><strong>Description:</strong> ' . htmlspecialchars($room['description']) . '</p>';
    }
    $email_body .= '<p><strong>Room Code:</strong> ' . htmlspecialchars($room['room_code']) . '</p>';
    if (!empty($room['password'])) {
        $email_body .= '<p><em>This meeting is password protected.</em></p>';
    }
    $email_body .= '<p><a href="' . htmlspecialchars($join_link) . '" style="display:inline-block;padding:10px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Join Meeting</a></p>';

    $subject = 'Meeting Invitation: ' . $room['room_name'];
    send_email($invitee['email'], $invitee['name'], $subject, $email_body, 'info');

    // Save to emails table
    try {
        $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder, sent_via_smtp) VALUES (?,?,?,?,?,?,'info','sent',1)")
            ->execute([$invited_by, 'info@softandpix.com', $invited_user_id, $invitee['email'], $subject, $email_body]);
        $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder) VALUES (?,?,?,?,?,?,'info','inbox')")
            ->execute([$invited_by, 'info@softandpix.com', $invited_user_id, $invitee['email'], $subject, $email_body]);
    } catch (Exception $e) {
        error_log('Video email save error: ' . $e->getMessage());
    }

    // Mark email_sent
    try {
        $pdo->prepare("UPDATE meeting_invitations SET email_sent=1 WHERE room_id=? AND invited_user_id=?")
            ->execute([$room_id, $invited_user_id]);
    } catch (Exception $e) {}

    // Create in-app notification
    create_notification($pdo, $invited_user_id, 'meeting', 'Meeting Invitation', 'You are invited to: ' . $room['room_name'], '/video/room.php?code=' . $room['room_code']);
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

function create_room(PDO $pdo, int $user_id, string $user_name): void {
    $room_name        = trim($_POST['room_name'] ?? 'Instant Meeting');
    $room_type        = in_array($_POST['room_type'] ?? '', ['instant', 'scheduled', 'recurring']) ? $_POST['room_type'] : 'instant';
    $scheduled_at     = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    $scheduled_end    = !empty($_POST['scheduled_end']) ? $_POST['scheduled_end'] : null;
    $description      = trim($_POST['description'] ?? '');
    $password         = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $max_participants = max(2, min(6, (int)($_POST['max_participants'] ?? 6)));
    $invite_users     = $_POST['invite_users'] ?? [];

    // Generate unique room code
    $room_code = generate_room_code();
    $attempts = 0;
    while ($attempts < 10) {
        $check = $pdo->prepare("SELECT id FROM video_rooms WHERE room_code = ?");
        $check->execute([$room_code]);
        if (!$check->fetch()) break;
        $room_code = generate_room_code();
        $attempts++;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO video_rooms (room_code, room_name, room_type, created_by, max_participants, status, scheduled_at, scheduled_end, description, password)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $status = 'waiting';
    $stmt->execute([$room_code, $room_name, $room_type, $user_id, $max_participants, $status, $scheduled_at, $scheduled_end, $description, $password]);
    $room_id = (int)$pdo->lastInsertId();

    // Add creator as host
    $pdo->prepare("INSERT INTO video_participants (room_id, user_id, role) VALUES (?, ?, 'host')")
        ->execute([$room_id, $user_id]);

    // Get full room data for email
    $room = get_room_by_code($pdo, $room_code);

    // Invite users
    if (!empty($invite_users) && is_array($invite_users)) {
        foreach ($invite_users as $invited_uid) {
            $invited_uid = (int)$invited_uid;
            if ($invited_uid <= 0 || $invited_uid === $user_id) continue;
            $pdo->prepare("INSERT INTO meeting_invitations (room_id, invited_by, invited_user_id) VALUES (?, ?, ?)")
                ->execute([$room_id, $user_id, $invited_uid]);
            if ($room) {
                send_meeting_invitation_email($pdo, $room_id, $user_id, $invited_uid, $room);
            }
        }
    }

    log_activity($pdo, $user_id, 'room_created', 'Created video room: ' . $room_name, 'video_room', $room_id);
    echo json_encode(['success' => true, 'room_code' => $room_code, 'room_id' => $room_id]);
}

function join_room(PDO $pdo, int $user_id, string $user_name): void {
    $room_code = trim($_POST['room_code'] ?? '');
    $password  = $_POST['password'] ?? '';

    $room = get_room_by_code($pdo, $room_code);
    if (!$room) {
        echo json_encode(['success' => false, 'error' => 'Room not found']);
        return;
    }

    if ($room['status'] === 'ended' || $room['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'error' => 'This meeting has ended']);
        return;
    }

    // Check password
    if (!empty($room['password']) && !password_verify($password, $room['password'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid password', 'needs_password' => true]);
        return;
    }

    // Check max participants
    $active = $pdo->prepare("SELECT COUNT(*) FROM video_participants WHERE room_id = ? AND left_at IS NULL");
    $active->execute([$room['id']]);
    if ((int)$active->fetchColumn() >= $room['max_participants']) {
        // Check if user is already a participant
        $existing = $pdo->prepare("SELECT id FROM video_participants WHERE room_id = ? AND user_id = ? AND left_at IS NULL");
        $existing->execute([$room['id'], $user_id]);
        if (!$existing->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Room is full']);
            return;
        }
    }

    // Check if already in room
    $existing = $pdo->prepare("SELECT id FROM video_participants WHERE room_id = ? AND user_id = ? AND left_at IS NULL");
    $existing->execute([$room['id'], $user_id]);
    if (!$existing->fetch()) {
        $role = ($room['created_by'] == $user_id) ? 'host' : 'participant';
        $pdo->prepare("INSERT INTO video_participants (room_id, user_id, role) VALUES (?, ?, ?)")
            ->execute([$room['id'], $user_id, $role]);
    }

    // Set room active
    if ($room['status'] === 'waiting') {
        $pdo->prepare("UPDATE video_rooms SET status = 'active' WHERE id = ?")->execute([$room['id']]);
    }

    // Broadcast join signal to other participants
    $others = $pdo->prepare("SELECT user_id FROM video_participants WHERE room_id = ? AND user_id != ? AND left_at IS NULL");
    $others->execute([$room['id'], $user_id]);
    foreach ($others->fetchAll() as $p) {
        $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, 'join', ?)")
            ->execute([$room['id'], $user_id, $p['user_id'], json_encode(['user_id' => $user_id, 'user_name' => $user_name])]);
    }

    log_activity($pdo, $user_id, 'room_joined', 'Joined video room: ' . $room['room_name'], 'video_room', $room['id']);
    echo json_encode(['success' => true, 'room' => [
        'id'               => $room['id'],
        'room_code'        => $room['room_code'],
        'room_name'        => $room['room_name'],
        'created_by'       => $room['created_by'],
        'max_participants'  => $room['max_participants'],
        'status'           => 'active',
    ]]);
}

function leave_room(PDO $pdo, int $user_id, string $user_name): void {
    $room_code = trim($_POST['room_code'] ?? '');
    $room = get_room_by_code($pdo, $room_code);
    if (!$room) { echo json_encode(['success' => false, 'error' => 'Room not found']); return; }

    $pdo->prepare("UPDATE video_participants SET left_at = NOW() WHERE room_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$room['id'], $user_id]);

    // Broadcast leave signal
    $others = $pdo->prepare("SELECT user_id FROM video_participants WHERE room_id = ? AND user_id != ? AND left_at IS NULL");
    $others->execute([$room['id'], $user_id]);
    foreach ($others->fetchAll() as $p) {
        $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, 'leave', ?)")
            ->execute([$room['id'], $user_id, $p['user_id'], json_encode(['user_id' => $user_id])]);
    }

    // If last person, end room
    $remaining = $pdo->prepare("SELECT COUNT(*) FROM video_participants WHERE room_id = ? AND left_at IS NULL");
    $remaining->execute([$room['id']]);
    if ((int)$remaining->fetchColumn() === 0) {
        $pdo->prepare("UPDATE video_rooms SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$room['id']]);
    }

    log_activity($pdo, $user_id, 'room_left', 'Left video room: ' . $room['room_name'], 'video_room', $room['id']);
    echo json_encode(['success' => true]);
}

function send_signal(PDO $pdo, int $user_id): void {
    $room_id     = (int)($_POST['room_id'] ?? 0);
    $to_user     = (int)($_POST['to_user'] ?? 0);
    $signal_type = $_POST['signal_type'] ?? '';
    $signal_data = $_POST['signal_data'] ?? '{}';

    $valid_types = ['offer', 'answer', 'ice-candidate', 'join', 'leave', 'mute', 'unmute', 'screen-start', 'screen-stop'];
    if (!in_array($signal_type, $valid_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid signal type']);
        return;
    }

    $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, ?, ?)")
        ->execute([$room_id, $user_id, $to_user, $signal_type, $signal_data]);

    echo json_encode(['success' => true]);
}

function get_signals(PDO $pdo, int $user_id): void {
    $room_id = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT id, from_user, signal_type, signal_data, created_at FROM video_signals
         WHERE to_user = ? AND room_id = ? AND is_read = 0 ORDER BY id ASC"
    );
    $stmt->execute([$user_id, $room_id]);
    $signals = $stmt->fetchAll();

    // Mark as read
    if (!empty($signals)) {
        $ids = array_column($signals, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE video_signals SET is_read = 1 WHERE id IN ($placeholders)")->execute($ids);
    }

    echo json_encode(['success' => true, 'signals' => $signals]);
}

function send_chat(PDO $pdo, int $user_id): void {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        return;
    }

    $pdo->prepare("INSERT INTO video_chat (room_id, user_id, message) VALUES (?, ?, ?)")
        ->execute([$room_id, $user_id, $message]);

    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

function get_chat(PDO $pdo, int $user_id): void {
    $room_id = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
    $last_id = (int)($_GET['last_id'] ?? $_POST['last_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT vc.id, vc.user_id, vc.message, vc.created_at, u.name as user_name
         FROM video_chat vc LEFT JOIN users u ON u.id = vc.user_id
         WHERE vc.room_id = ? AND vc.id > ? ORDER BY vc.id ASC"
    );
    $stmt->execute([$room_id, $last_id]);

    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
}

function get_participants(PDO $pdo, int $user_id): void {
    $room_id = (int)($_GET['room_id'] ?? $_POST['room_id'] ?? 0);

    $stmt = $pdo->prepare(
        "SELECT vp.user_id, vp.is_muted, vp.is_video_on, vp.is_screen_sharing, vp.role, u.name, u.avatar
         FROM video_participants vp LEFT JOIN users u ON u.id = vp.user_id
         WHERE vp.room_id = ? AND vp.left_at IS NULL"
    );
    $stmt->execute([$room_id]);

    echo json_encode(['success' => true, 'participants' => $stmt->fetchAll()]);
}

function toggle_mute(PDO $pdo, int $user_id): void {
    $room_id  = (int)($_POST['room_id'] ?? 0);
    $is_muted = (int)($_POST['is_muted'] ?? 0);

    $pdo->prepare("UPDATE video_participants SET is_muted = ? WHERE room_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$is_muted, $room_id, $user_id]);

    // Broadcast mute/unmute signal
    $signal_type = $is_muted ? 'mute' : 'unmute';
    $others = $pdo->prepare("SELECT user_id FROM video_participants WHERE room_id = ? AND user_id != ? AND left_at IS NULL");
    $others->execute([$room_id, $user_id]);
    foreach ($others->fetchAll() as $p) {
        $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, ?, ?)")
            ->execute([$room_id, $user_id, $p['user_id'], $signal_type, json_encode(['user_id' => $user_id])]);
    }

    echo json_encode(['success' => true]);
}

function toggle_video(PDO $pdo, int $user_id): void {
    $room_id    = (int)($_POST['room_id'] ?? 0);
    $is_video_on = (int)($_POST['is_video_on'] ?? 1);

    $pdo->prepare("UPDATE video_participants SET is_video_on = ? WHERE room_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$is_video_on, $room_id, $user_id]);

    echo json_encode(['success' => true]);
}

function toggle_screen(PDO $pdo, int $user_id): void {
    $room_id          = (int)($_POST['room_id'] ?? 0);
    $is_screen_sharing = (int)($_POST['is_screen_sharing'] ?? 0);

    $pdo->prepare("UPDATE video_participants SET is_screen_sharing = ? WHERE room_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$is_screen_sharing, $room_id, $user_id]);

    // Broadcast screen-start/screen-stop
    $signal_type = $is_screen_sharing ? 'screen-start' : 'screen-stop';
    $others = $pdo->prepare("SELECT user_id FROM video_participants WHERE room_id = ? AND user_id != ? AND left_at IS NULL");
    $others->execute([$room_id, $user_id]);
    foreach ($others->fetchAll() as $p) {
        $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, ?, ?)")
            ->execute([$room_id, $user_id, $p['user_id'], $signal_type, json_encode(['user_id' => $user_id])]);
    }

    echo json_encode(['success' => true]);
}

function end_room(PDO $pdo, int $user_id, string $user_name): void {
    $room_code = trim($_POST['room_code'] ?? '');
    $room = get_room_by_code($pdo, $room_code);
    if (!$room) { echo json_encode(['success' => false, 'error' => 'Room not found']); return; }

    if ((int)$room['created_by'] !== $user_id) {
        echo json_encode(['success' => false, 'error' => 'Only the host can end the room']);
        return;
    }

    // Broadcast leave signal to all
    $others = $pdo->prepare("SELECT user_id FROM video_participants WHERE room_id = ? AND left_at IS NULL");
    $others->execute([$room['id']]);
    foreach ($others->fetchAll() as $p) {
        $pdo->prepare("INSERT INTO video_signals (room_id, from_user, to_user, signal_type, signal_data) VALUES (?, ?, ?, 'leave', ?)")
            ->execute([$room['id'], $user_id, $p['user_id'], json_encode(['user_id' => $user_id, 'ended' => true])]);
    }

    // Mark all participants as left
    $pdo->prepare("UPDATE video_participants SET left_at = NOW() WHERE room_id = ? AND left_at IS NULL")->execute([$room['id']]);
    $pdo->prepare("UPDATE video_rooms SET status = 'ended', ended_at = NOW() WHERE id = ?")->execute([$room['id']]);

    // Notify invited users about cancellation if scheduled
    if ($room['room_type'] === 'scheduled') {
        $invitations = $pdo->prepare("SELECT invited_user_id FROM meeting_invitations WHERE room_id = ? AND invited_user_id IS NOT NULL");
        $invitations->execute([$room['id']]);
        foreach ($invitations->fetchAll() as $inv) {
            $invitee = get_user($pdo, $inv['invited_user_id']);
            if ($invitee) {
                $cancel_body = '<h2>Meeting Cancelled</h2>';
                $cancel_body .= '<p>The meeting <strong>' . htmlspecialchars($room['room_name']) . '</strong> has been cancelled by the host.</p>';
                send_email($invitee['email'], $invitee['name'], 'Meeting Cancelled: ' . $room['room_name'], $cancel_body, 'info');
                create_notification($pdo, $inv['invited_user_id'], 'meeting', 'Meeting Cancelled', 'Meeting cancelled: ' . $room['room_name'], '');
            }
        }
    }

    log_activity($pdo, $user_id, 'room_ended', 'Ended video room: ' . $room['room_name'], 'video_room', $room['id']);
    echo json_encode(['success' => true]);
}

function schedule_meeting(PDO $pdo, int $user_id, string $user_name): void {
    $_POST['room_type'] = 'scheduled';
    create_room($pdo, $user_id, $user_name);
}

function get_scheduled(PDO $pdo, int $user_id): void {
    // Get meetings where user is creator or invited
    $stmt = $pdo->prepare(
        "SELECT DISTINCT vr.*, u.name as host_name FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
         LEFT JOIN video_participants vp ON vp.room_id = vr.id
         WHERE vr.status IN ('waiting', 'active')
           AND (vr.created_by = ? OR mi.invited_user_id = ? OR vp.user_id = ?)
         ORDER BY COALESCE(vr.scheduled_at, vr.created_at) ASC"
    );
    $stmt->execute([$user_id, $user_id, $user_id]);
    $meetings = $stmt->fetchAll();

    // Get participant counts
    foreach ($meetings as &$m) {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM video_participants WHERE room_id = ? AND left_at IS NULL");
        $cnt->execute([$m['id']]);
        $m['participant_count'] = (int)$cnt->fetchColumn();
    }

    echo json_encode(['success' => true, 'meetings' => $meetings]);
}

function invite_users(PDO $pdo, int $user_id, string $user_name): void {
    $room_code    = trim($_POST['room_code'] ?? '');
    $invite_users = $_POST['invite_users'] ?? [];

    $room = get_room_by_code($pdo, $room_code);
    if (!$room) { echo json_encode(['success' => false, 'error' => 'Room not found']); return; }

    $invited = 0;
    if (is_array($invite_users)) {
        foreach ($invite_users as $invited_uid) {
            $invited_uid = (int)$invited_uid;
            if ($invited_uid <= 0 || $invited_uid === $user_id) continue;

            // Check not already invited
            $check = $pdo->prepare("SELECT id FROM meeting_invitations WHERE room_id = ? AND invited_user_id = ?");
            $check->execute([$room['id'], $invited_uid]);
            if ($check->fetch()) continue;

            $pdo->prepare("INSERT INTO meeting_invitations (room_id, invited_by, invited_user_id) VALUES (?, ?, ?)")
                ->execute([$room['id'], $user_id, $invited_uid]);
            send_meeting_invitation_email($pdo, $room['id'], $user_id, $invited_uid, $room);
            $invited++;
        }
    }

    log_activity($pdo, $user_id, 'users_invited', "Invited $invited user(s) to room: " . $room['room_name'], 'video_room', $room['id']);
    echo json_encode(['success' => true, 'invited' => $invited]);
}

// Cron job reminder concept:
// A cron job could call this endpoint or a separate script to send reminders
// e.g., 15 minutes before scheduled meetings:
// SELECT vr.*, mi.invited_user_id FROM video_rooms vr
// JOIN meeting_invitations mi ON mi.room_id = vr.id
// WHERE vr.scheduled_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 15 MINUTE)
// AND vr.status = 'waiting'
// Then send reminder emails to all invited users.
