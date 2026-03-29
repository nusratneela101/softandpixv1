<?php
/**
 * API v1 — Projects endpoints
 *
 * GET    /api/v1/projects
 * GET    /api/v1/projects/{id}
 * POST   /api/v1/projects
 * PUT    /api/v1/projects/{id}
 * DELETE /api/v1/projects/{id}
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$proj_idx   = array_search('projects', $segments);
$project_id = isset($segments[$proj_idx + 1]) ? (int)$segments[$proj_idx + 1] : null;

if ($project_id && $project_id <= 0) {
    api_error('Invalid project ID.', 400);
}

switch ($method) {
    case 'GET':
        $project_id ? get_project($pdo, $api_user, $project_id) : list_projects($pdo, $api_user);
        break;
    case 'POST':
        create_project($pdo, $api_user);
        break;
    case 'PUT':
        if (!$project_id) api_error('Project ID required.', 400);
        update_project($pdo, $api_user, $project_id);
        break;
    case 'DELETE':
        if (!$project_id) api_error('Project ID required.', 400);
        delete_project($pdo, $api_user, $project_id);
        break;
    default:
        api_error('Method not allowed.', 405);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function list_projects(PDO $pdo, array $api_user): never {
    $params = [];
    $where  = [];

    if ($api_user['role'] === 'client') {
        $where[]  = 'p.client_id = ?';
        $params[] = $api_user['user_id'];
    } elseif ($api_user['role'] === 'developer') {
        $where[]  = 'p.developer_id = ?';
        $params[] = $api_user['user_id'];
    }

    if (isset($_GET['status'])) {
        $where[]  = 'p.status = ?';
        $params[] = $_GET['status'];
    }

    $sql = "SELECT p.*, u.name AS client_name, d.name AS developer_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.client_id
            LEFT JOIN users d ON d.id = p.developer_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY p.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();

    json_response(['success' => true, 'data' => $projects]);
}

function get_project(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.name AS client_name, d.name AS developer_name
         FROM projects p
         LEFT JOIN users u ON u.id = p.client_id
         LEFT JOIN users d ON d.id = p.developer_id
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    if (!$project) {
        api_error('Project not found.', 404);
    }

    if (!can_access_project($api_user, $project)) {
        api_error('Access denied.', 403);
    }

    json_response(['success' => true, 'data' => $project]);
}

function create_project(PDO $pdo, array $api_user): never {
    if ($api_user['role'] !== 'admin') {
        api_error('Only admins can create projects.', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name  = trim($input['name'] ?? '');

    if (empty($name)) {
        api_error('Project name is required.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO projects (name, description, client_id, developer_id, deadline, budget, status, progress, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([
        $name,
        $input['description'] ?? null,
        (int)($input['client_id'] ?? 0) ?: null,
        (int)($input['developer_id'] ?? 0) ?: null,
        $input['deadline'] ?? null,
        isset($input['budget']) ? (float)$input['budget'] : null,
        $input['status'] ?? 'active',
    ]);

    $project_id = (int)$pdo->lastInsertId();
    log_activity($pdo, $api_user['user_id'], 'api_project_created', "Project #{$project_id} created", 'project', $project_id);

    json_response(['success' => true, 'id' => $project_id, 'message' => 'Project created.'], 201);
}

function update_project(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    if (!$project) {
        api_error('Project not found.', 404);
    }

    if (!can_access_project($api_user, $project)) {
        api_error('Access denied.', 403);
    }

    $input   = json_decode(file_get_contents('php://input'), true) ?? [];
    $allowed = ['name', 'description', 'client_id', 'developer_id', 'deadline', 'budget', 'status', 'progress'];
    $sets    = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $sets[]  = "{$field} = ?";
            $params[] = $input[$field];
        }
    }

    if (empty($sets)) {
        api_error('No fields to update.');
    }

    $params[] = $id;
    $pdo->prepare("UPDATE projects SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    log_activity($pdo, $api_user['user_id'], 'api_project_updated', "Project #{$id} updated", 'project', $id);

    json_response(['success' => true, 'message' => 'Project updated.']);
}

function delete_project(PDO $pdo, array $api_user, int $id): never {
    if ($api_user['role'] !== 'admin') {
        api_error('Only admins can delete projects.', 403);
    }

    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        api_error('Project not found.', 404);
    }

    $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    log_activity($pdo, $api_user['user_id'], 'api_project_deleted', "Project #{$id} deleted", 'project', $id);

    json_response(['success' => true, 'message' => 'Project deleted.']);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function can_access_project(array $api_user, array $project): bool {
    if ($api_user['role'] === 'admin') {
        return true;
    }
    if ($api_user['role'] === 'client') {
        return (int)$project['client_id'] === (int)$api_user['user_id'];
    }
    if ($api_user['role'] === 'developer') {
        return (int)$project['developer_id'] === (int)$api_user['user_id'];
    }
    return false;
}
