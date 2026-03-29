<?php
/**
 * API v1 — Time Tracking endpoints
 *
 * POST   /api/v1/time/start
 * POST   /api/v1/time/stop
 * GET    /api/v1/time/entries
 * POST   /api/v1/time/manual
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$time_idx  = array_search('time', $segments);
$sub_route = $segments[$time_idx + 1] ?? '';

switch ($method . ':' . $sub_route) {
    case 'POST:start':
        start_timer($pdo, $api_user);
        break;
    case 'POST:stop':
        stop_timer($pdo, $api_user);
        break;
    case 'GET:entries':
        list_entries($pdo, $api_user);
        break;
    case 'POST:manual':
        manual_entry($pdo, $api_user);
        break;
    default:
        api_error('Time tracking endpoint not found.', 404);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function start_timer(PDO $pdo, array $api_user): never {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $project_id = (int)($input['project_id'] ?? 0);

    if ($project_id <= 0) {
        api_error('A valid project_id is required.');
    }

    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$project_id]);
    if (!$stmt->fetch()) {
        api_error('Project not found.', 404);
    }

    // Prevent duplicate active timers per user.
    $check = $pdo->prepare("SELECT id FROM active_timers WHERE user_id = ? LIMIT 1");
    $check->execute([$api_user['user_id']]);
    if ($check->fetch()) {
        api_error('A timer is already running. Stop it before starting a new one.', 409);
    }

    $task_id = (int)($input['task_id'] ?? 0) ?: null;

    $pdo->prepare(
        "INSERT INTO active_timers (user_id, project_id, task_id, start_time, description)
         VALUES (?, ?, ?, NOW(), ?)"
    )->execute([$api_user['user_id'], $project_id, $task_id, $input['description'] ?? null]);

    log_activity($pdo, $api_user['user_id'], 'api_timer_started', "Timer started for project #{$project_id}");

    json_response(['success' => true, 'message' => 'Timer started.', 'started_at' => date('Y-m-d H:i:s')], 201);
}

function stop_timer(PDO $pdo, array $api_user): never {
    $stmt = $pdo->prepare("SELECT * FROM active_timers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$api_user['user_id']]);
    $timer = $stmt->fetch();

    if (!$timer) {
        api_error('No active timer found.', 404);
    }

    $start_time      = strtotime($timer['start_time']);
    $end_time        = time();
    $duration_minutes = (int)round(($end_time - $start_time) / 60);

    $entry_stmt = $pdo->prepare(
        "INSERT INTO time_entries
            (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual, created_at)
         VALUES (?, ?, ?, ?, ?, NOW(), ?, 0, NOW())"
    );
    $entry_stmt->execute([
        $api_user['user_id'],
        $timer['project_id'],
        $timer['task_id'],
        $timer['description'],
        $timer['start_time'],
        $duration_minutes,
    ]);

    $entry_id = (int)$pdo->lastInsertId();
    $pdo->prepare("DELETE FROM active_timers WHERE user_id = ?")->execute([$api_user['user_id']]);

    log_activity($pdo, $api_user['user_id'], 'api_timer_stopped', "Timer stopped. Entry #{$entry_id} created ({$duration_minutes} min)");

    json_response([
        'success'          => true,
        'message'          => 'Timer stopped.',
        'entry_id'         => $entry_id,
        'duration_minutes' => $duration_minutes,
    ]);
}

function list_entries(PDO $pdo, array $api_user): never {
    $where  = [];
    $params = [];

    if ($api_user['role'] !== 'admin') {
        $where[]  = 'te.user_id = ?';
        $params[] = $api_user['user_id'];
    } elseif (isset($_GET['user_id'])) {
        $where[]  = 'te.user_id = ?';
        $params[] = (int)$_GET['user_id'];
    }

    if (isset($_GET['project_id'])) {
        $where[]  = 'te.project_id = ?';
        $params[] = (int)$_GET['project_id'];
    }
    if (isset($_GET['from'])) {
        $where[]  = 'DATE(te.start_time) >= ?';
        $params[] = $_GET['from'];
    }
    if (isset($_GET['to'])) {
        $where[]  = 'DATE(te.start_time) <= ?';
        $params[] = $_GET['to'];
    }

    $sql = "SELECT te.*, u.name AS user_name, p.name AS project_name
            FROM time_entries te
            LEFT JOIN users u ON u.id = te.user_id
            LEFT JOIN projects p ON p.id = te.project_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY te.start_time DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

function manual_entry(PDO $pdo, array $api_user): never {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $project_id = (int)($input['project_id'] ?? 0);
    $start_time = $input['start_time'] ?? '';
    $end_time   = $input['end_time'] ?? '';

    if ($project_id <= 0) {
        api_error('A valid project_id is required.');
    }
    if (empty($start_time) || empty($end_time)) {
        api_error('start_time and end_time are required.');
    }

    $start_ts = strtotime($start_time);
    $end_ts   = strtotime($end_time);

    if ($start_ts === false || $end_ts === false) {
        api_error('Invalid date/time format. Use Y-m-d H:i:s.');
    }
    if ($end_ts <= $start_ts) {
        api_error('end_time must be after start_time.');
    }

    $duration_minutes = (int)round(($end_ts - $start_ts) / 60);

    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$project_id]);
    if (!$stmt->fetch()) {
        api_error('Project not found.', 404);
    }

    $entry_stmt = $pdo->prepare(
        "INSERT INTO time_entries
            (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())"
    );
    $entry_stmt->execute([
        $api_user['user_id'],
        $project_id,
        (int)($input['task_id'] ?? 0) ?: null,
        $input['description'] ?? null,
        date('Y-m-d H:i:s', $start_ts),
        date('Y-m-d H:i:s', $end_ts),
        $duration_minutes,
    ]);

    $entry_id = (int)$pdo->lastInsertId();
    log_activity($pdo, $api_user['user_id'], 'api_time_manual', "Manual time entry #{$entry_id} added ({$duration_minutes} min)");

    json_response([
        'success'          => true,
        'id'               => $entry_id,
        'duration_minutes' => $duration_minutes,
        'message'          => 'Time entry added.',
    ], 201);
}
