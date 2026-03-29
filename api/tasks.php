<?php
/**
 * API — Task CRUD & Comment Endpoints
 *
 * All endpoints require authentication.
 * Accepts JSON or form-data POST.
 * Returns JSON.
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/activity_logger.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? 'client';
$isAdmin  = isset($_SESSION['admin_id']) || $userRole === 'admin';

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$action = trim($_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? '');

// CSRF validation for state-changing requests
if ($method === 'POST') {
    $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {

        // ------------------------------------------------------------------
        // List tasks
        // ------------------------------------------------------------------
        case 'list':
            $where  = "WHERE 1=1";
            $params = [];

            if (!$isAdmin) {
                if ($userRole === 'developer') {
                    $where .= " AND t.assigned_to=?"; $params[] = $userId;
                } elseif ($userRole === 'client') {
                    // Only tasks belonging to client's projects
                    $pidStmt = $pdo->prepare("SELECT id FROM projects WHERE client_id=?");
                    $pidStmt->execute([$userId]);
                    $pids = array_column($pidStmt->fetchAll(), 'id');
                    if (empty($pids)) { echo json_encode(['success' => true, 'tasks' => []]); exit; }
                    $inP = implode(',', array_fill(0, count($pids), '?'));
                    $where .= " AND t.project_id IN ($inP)";
                    $params = array_merge($params, $pids);
                }
            }

            if (!empty($_GET['project_id'])) { $where .= " AND t.project_id=?"; $params[] = (int)$_GET['project_id']; }
            if (!empty($_GET['status']))     { $where .= " AND t.status=?";     $params[] = $_GET['status']; }
            if (!empty($_GET['priority']))   { $where .= " AND t.priority=?";   $params[] = $_GET['priority']; }

            $stmt = $pdo->prepare("SELECT t.*, p.title AS project_title, u.name AS developer_name
                FROM tasks t
                LEFT JOIN projects p ON p.id=t.project_id
                LEFT JOIN users u ON u.id=t.assigned_to
                $where ORDER BY t.created_at DESC LIMIT 100");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'tasks' => $stmt->fetchAll()]);
            break;

        // ------------------------------------------------------------------
        // Get single task with comments
        // ------------------------------------------------------------------
        case 'get':
            $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); break; }

            $stmt = $pdo->prepare("SELECT t.*, p.title AS project_title, u.name AS developer_name
                FROM tasks t LEFT JOIN projects p ON p.id=t.project_id LEFT JOIN users u ON u.id=t.assigned_to
                WHERE t.id=?");
            $stmt->execute([$id]);
            $task = $stmt->fetch();
            if (!$task) { echo json_encode(['success' => false, 'message' => 'Not found']); break; }

            // Access check
            if (!$isAdmin) {
                if ($userRole === 'developer' && (int)$task['assigned_to'] !== $userId) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']); break;
                }
                if ($userRole === 'client') {
                    $own = $pdo->prepare("SELECT id FROM projects WHERE id=? AND client_id=?");
                    $own->execute([$task['project_id'], $userId]);
                    if (!$own->fetch()) { echo json_encode(['success' => false, 'message' => 'Access denied']); break; }
                }
            }

            $cStmt = $pdo->prepare("SELECT tc.*, u.name AS author_name FROM task_comments tc LEFT JOIN users u ON u.id=tc.user_id WHERE tc.task_id=? ORDER BY tc.created_at ASC");
            $cStmt->execute([$id]);
            $task['comments'] = $cStmt->fetchAll();
            echo json_encode(['success' => true, 'task' => $task]);
            break;

        // ------------------------------------------------------------------
        // Create task (admin only)
        // ------------------------------------------------------------------
        case 'create':
            if (!$isAdmin) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin only']); break; }

            $title      = trim($input['title'] ?? $_POST['title'] ?? '');
            $desc       = trim($input['description'] ?? $_POST['description'] ?? '');
            $project_id = (int)($input['project_id'] ?? $_POST['project_id'] ?? 0);
            $assigned   = (int)($input['assigned_to'] ?? $_POST['assigned_to'] ?? 0) ?: null;
            $priority   = in_array($input['priority'] ?? '', ['low','medium','high','urgent']) ? $input['priority'] : 'medium';
            $status     = in_array($input['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? $input['status'] : 'pending';
            $due_date   = !empty($input['due_date'] ?? $_POST['due_date'] ?? '') ? ($input['due_date'] ?? $_POST['due_date']) : null;

            if (!$title || !$project_id) { echo json_encode(['success' => false, 'message' => 'Title and project_id required']); break; }

            $createdBy = $userId ?: 1;
            $ins = $pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, description, priority, status, due_date) VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([$project_id, $assigned, $createdBy, $title, $desc, $priority, $status, $due_date]);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, $userId, 'task_created', "Task '{$title}' created via API", 'task', $newId);
            echo json_encode(['success' => true, 'id' => $newId]);
            break;

        // ------------------------------------------------------------------
        // Update task
        // ------------------------------------------------------------------
        case 'update':
            $id = (int)($input['id'] ?? $_POST['task_id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); break; }

            // Developers can only update status of their own tasks
            if (!$isAdmin && $userRole === 'developer') {
                $newStatus = in_array($input['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? $input['status'] : null;
                if (!$newStatus) { echo json_encode(['success' => false, 'message' => 'Invalid status']); break; }
                $completed_at = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
                $pdo->prepare("UPDATE tasks SET status=?, completed_at=? WHERE id=? AND assigned_to=?")->execute([$newStatus, $completed_at, $id, $userId]);
                log_activity($pdo, $userId, 'task_status_updated', "Task #{$id} status → {$newStatus}", 'task', $id);
                echo json_encode(['success' => true]);
                break;
            }

            if (!$isAdmin) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin only']); break; }

            $fields = [];
            $vals   = [];
            $allowed = ['title', 'description', 'project_id', 'assigned_to', 'priority', 'status', 'due_date'];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $input)) { $fields[] = "$f=?"; $vals[] = $input[$f]; }
            }
            if (in_array('completed', $vals)) { $fields[] = 'completed_at=?'; $vals[] = date('Y-m-d H:i:s'); }
            if (empty($fields)) { echo json_encode(['success' => false, 'message' => 'Nothing to update']); break; }
            $vals[] = $id;
            $pdo->prepare("UPDATE tasks SET " . implode(',', $fields) . " WHERE id=?")->execute($vals);
            log_activity($pdo, $userId, 'task_updated', "Task #{$id} updated via API", 'task', $id);
            echo json_encode(['success' => true]);
            break;

        // ------------------------------------------------------------------
        // Delete task (admin only)
        // ------------------------------------------------------------------
        case 'delete':
            if (!$isAdmin) { http_response_code(403); echo json_encode(['success' => false, 'message' => 'Admin only']); break; }
            $id = (int)($input['id'] ?? $_POST['task_id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); break; }
            $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
            log_activity($pdo, $userId, 'task_deleted', "Task #{$id} deleted via API", 'task', $id);
            echo json_encode(['success' => true]);
            break;

        // ------------------------------------------------------------------
        // Add comment
        // ------------------------------------------------------------------
        case 'add_comment':
            $taskId  = (int)($input['task_id'] ?? $_POST['task_id'] ?? 0);
            $comment = trim($input['comment'] ?? $_POST['comment'] ?? '');
            if (!$taskId || !$comment) { echo json_encode(['success' => false, 'message' => 'task_id and comment required']); break; }

            // Verify access
            if (!$isAdmin) {
                if ($userRole === 'developer') {
                    $chk = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND assigned_to=?");
                    $chk->execute([$taskId, $userId]);
                    if (!$chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Access denied']); break; }
                } elseif ($userRole === 'client') {
                    $chk = $pdo->prepare("SELECT t.id FROM tasks t JOIN projects p ON p.id=t.project_id WHERE t.id=? AND p.client_id=?");
                    $chk->execute([$taskId, $userId]);
                    if (!$chk->fetch()) { echo json_encode(['success' => false, 'message' => 'Access denied']); break; }
                }
            }

            $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")->execute([$taskId, $userId ?: 1, $comment]);
            $newCId = (int)$pdo->lastInsertId();
            echo json_encode(['success' => true, 'comment_id' => $newCId]);
            break;

        // ------------------------------------------------------------------
        // Update task status (shortcut)
        // ------------------------------------------------------------------
        case 'update_status':
            $id        = (int)($input['id'] ?? $_POST['task_id'] ?? 0);
            $newStatus = in_array($input['status'] ?? $_POST['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? ($input['status'] ?? $_POST['status']) : null;
            if (!$id || !$newStatus) { echo json_encode(['success' => false, 'message' => 'id and status required']); break; }
            $completed_at = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
            if ($isAdmin) {
                $pdo->prepare("UPDATE tasks SET status=?, completed_at=? WHERE id=?")->execute([$newStatus, $completed_at, $id]);
            } else {
                $pdo->prepare("UPDATE tasks SET status=?, completed_at=? WHERE id=? AND assigned_to=?")->execute([$newStatus, $completed_at, $id, $userId]);
            }
            log_activity($pdo, $userId, 'task_status_updated', "Task #{$id} → {$newStatus}", 'task', $id);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Unknown action: {$action}"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
