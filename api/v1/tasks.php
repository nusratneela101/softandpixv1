<?php
/**
 * API v1 — Tasks endpoints
 *
 * GET    /api/v1/tasks
 * GET    /api/v1/tasks/{id}
 * POST   /api/v1/tasks
 * PUT    /api/v1/tasks/{id}
 * DELETE /api/v1/tasks/{id}
 * POST   /api/v1/tasks/{id}/comments
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$task_idx  = array_search('tasks', $segments);
$task_id   = isset($segments[$task_idx + 1]) ? (int)$segments[$task_idx + 1] : null;
$sub_route = $segments[$task_idx + 2] ?? '';

if ($task_id && $task_id <= 0) {
    api_error('Invalid task ID.', 400);
}

if ($task_id && $sub_route === 'comments' && $method === 'POST') {
    add_comment($pdo, $api_user, $task_id);
}

switch ($method) {
    case 'GET':
        $task_id ? get_task($pdo, $api_user, $task_id) : list_tasks($pdo, $api_user);
        break;
    case 'POST':
        create_task($pdo, $api_user);
        break;
    case 'PUT':
        if (!$task_id) api_error('Task ID required.', 400);
        update_task($pdo, $api_user, $task_id);
        break;
    case 'DELETE':
        if (!$task_id) api_error('Task ID required.', 400);
        delete_task($pdo, $api_user, $task_id);
        break;
    default:
        api_error('Method not allowed.', 405);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function list_tasks(PDO $pdo, array $api_user): never {
    $where  = [];
    $params = [];

    if (isset($_GET['project_id'])) {
        $where[]  = 't.project_id = ?';
        $params[] = (int)$_GET['project_id'];
    }
    if (isset($_GET['status'])) {
        $where[]  = 't.status = ?';
        $params[] = $_GET['status'];
    }
    if (isset($_GET['priority'])) {
        $where[]  = 't.priority = ?';
        $params[] = $_GET['priority'];
    }
    if (isset($_GET['assigned_to'])) {
        $where[]  = 't.assigned_to = ?';
        $params[] = (int)$_GET['assigned_to'];
    }

    // Role-based visibility.
    if ($api_user['role'] === 'developer') {
        $where[]  = '(t.assigned_to = ? OR t.created_by = ?)';
        $params[] = $api_user['user_id'];
        $params[] = $api_user['user_id'];
    } elseif ($api_user['role'] === 'client') {
        // Clients can see tasks for their projects only.
        $where[]  = 'p.client_id = ?';
        $params[] = $api_user['user_id'];
    }

    $sql = "SELECT t.*, u.name AS assigned_name, c.name AS creator_name, p.name AS project_name
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            LEFT JOIN users c ON c.id = t.created_by
            LEFT JOIN projects p ON p.id = t.project_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    json_response(['success' => true, 'data' => $tasks]);
}

function get_task(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.name AS assigned_name, c.name AS creator_name, p.name AS project_name, p.client_id
         FROM tasks t
         LEFT JOIN users u ON u.id = t.assigned_to
         LEFT JOIN users c ON c.id = t.created_by
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        api_error('Task not found.', 404);
    }

    if (!can_access_task($api_user, $task)) {
        api_error('Access denied.', 403);
    }

    // Fetch comments.
    $cstmt = $pdo->prepare(
        "SELECT tc.*, u.name AS author_name, u.avatar
         FROM task_comments tc
         LEFT JOIN users u ON u.id = tc.user_id
         WHERE tc.task_id = ?
         ORDER BY tc.created_at ASC"
    );
    $cstmt->execute([$id]);
    $task['comments'] = $cstmt->fetchAll();

    json_response(['success' => true, 'data' => $task]);
}

function create_task(PDO $pdo, array $api_user): never {
    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $title      = trim($input['title'] ?? '');
    $project_id = (int)($input['project_id'] ?? 0);

    if (empty($title)) {
        api_error('Task title is required.');
    }
    if ($project_id <= 0) {
        api_error('A valid project_id is required.');
    }

    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$project_id]);
    if (!$stmt->fetch()) {
        api_error('Project not found.', 404);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO tasks (project_id, title, description, priority, status, assigned_to, due_date, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $project_id,
        $title,
        $input['description'] ?? null,
        $input['priority'] ?? 'medium',
        $input['status'] ?? 'pending',
        (int)($input['assigned_to'] ?? 0) ?: null,
        $input['due_date'] ?? null,
        $api_user['user_id'],
    ]);

    $task_id = (int)$pdo->lastInsertId();
    log_activity($pdo, $api_user['user_id'], 'api_task_created', "Task #{$task_id} created", 'task', $task_id);

    json_response(['success' => true, 'id' => $task_id, 'message' => 'Task created.'], 201);
}

function update_task(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare(
        "SELECT t.*, p.client_id FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        api_error('Task not found.', 404);
    }
    if (!can_access_task($api_user, $task)) {
        api_error('Access denied.', 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['title', 'description', 'priority', 'status', 'assigned_to', 'due_date'];
    $sets    = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $sets[]  = "{$field} = ?";
            $params[] = $input[$field];
        }
    }

    // Mark completion timestamp.
    if (isset($input['status']) && $input['status'] === 'completed') {
        $sets[]  = 'completed_at = NOW()';
    }

    if (empty($sets)) {
        api_error('No fields to update.');
    }

    $params[] = $id;
    $pdo->prepare("UPDATE tasks SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    log_activity($pdo, $api_user['user_id'], 'api_task_updated', "Task #{$id} updated", 'task', $id);

    json_response(['success' => true, 'message' => 'Task updated.']);
}

function delete_task(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare(
        "SELECT t.*, p.client_id FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        api_error('Task not found.', 404);
    }

    // Only admins or the task creator can delete.
    if ($api_user['role'] !== 'admin' && (int)$task['created_by'] !== (int)$api_user['user_id']) {
        api_error('Only admins or the task creator can delete this task.', 403);
    }

    $pdo->prepare("DELETE FROM tasks WHERE id = ?")->execute([$id]);
    log_activity($pdo, $api_user['user_id'], 'api_task_deleted', "Task #{$id} deleted", 'task', $id);

    json_response(['success' => true, 'message' => 'Task deleted.']);
}

function add_comment(PDO $pdo, array $api_user, int $task_id): never {
    $stmt = $pdo->prepare(
        "SELECT t.*, p.client_id FROM tasks t
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        api_error('Task not found.', 404);
    }
    if (!can_access_task($api_user, $task)) {
        api_error('Access denied.', 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $comment = trim($input['comment'] ?? '');
    if (empty($comment)) {
        api_error('Comment text is required.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO task_comments (task_id, user_id, comment, created_at) VALUES (?, ?, ?, NOW())"
    );
    $stmt->execute([$task_id, $api_user['user_id'], $comment]);
    $comment_id = (int)$pdo->lastInsertId();

    json_response(['success' => true, 'id' => $comment_id, 'message' => 'Comment added.'], 201);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function can_access_task(array $api_user, array $task): bool {
    if ($api_user['role'] === 'admin') {
        return true;
    }
    if ($api_user['role'] === 'developer') {
        return (int)$task['assigned_to'] === (int)$api_user['user_id']
            || (int)$task['created_by'] === (int)$api_user['user_id'];
    }
    if ($api_user['role'] === 'client') {
        return (int)($task['client_id'] ?? 0) === (int)$api_user['user_id'];
    }
    return false;
}
