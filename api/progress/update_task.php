<?php
/**
 * API: Update task status
 * POST: task_id, new_status
 * Auto-recalculates project progress_percent
 * Logs activity
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Must be logged in
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { $input = $_POST; }

$csrfToken = $input['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$taskId    = (int)($input['task_id'] ?? 0);
$newStatus = $input['new_status'] ?? '';
$validStatuses = ['todo', 'in_progress', 'review', 'completed'];

if (!$taskId || !in_array($newStatus, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$actorId = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

try {
    // Fetch task + project
    $stmt = $pdo->prepare("SELECT t.*, p.progress_auto_calculate FROM project_tasks t
        JOIN projects p ON p.id = t.project_id
        WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    // Developer role: can only update tasks assigned to them
    if (!empty($_SESSION['user_role']) && !in_array($_SESSION['user_role'], ['admin']) && empty($_SESSION['admin_id'])) {
        if ((int)$task['assigned_to'] !== $actorId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Not your task']);
            exit;
        }
    }

    $completedAt = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
    $pdo->prepare("UPDATE project_tasks SET status=?, completed_at=?, updated_at=NOW() WHERE id=?")
        ->execute([$newStatus, $completedAt, $taskId]);

    $projectId = (int)$task['project_id'];
    $newProgress = 0;

    // Auto-recalculate progress
    if ($task['progress_auto_calculate']) {
        $totStmt = $pdo->prepare("SELECT COUNT(*) FROM project_tasks WHERE project_id=?");
        $totStmt->execute([$projectId]);
        $total = (int)$totStmt->fetchColumn();

        if ($total > 0) {
            $doneStmt = $pdo->prepare("SELECT COUNT(*) FROM project_tasks WHERE project_id=? AND status='completed'");
            $doneStmt->execute([$projectId]);
            $done = (int)$doneStmt->fetchColumn();
            $newProgress = (int)round(($done / $total) * 100);
        }

        $pdo->prepare("UPDATE projects SET progress_percent=? WHERE id=?")
            ->execute([$newProgress, $projectId]);
    } else {
        $prog = $pdo->prepare("SELECT progress_percent FROM projects WHERE id=?");
        $prog->execute([$projectId]);
        $newProgress = (int)($prog->fetchColumn() ?? 0);
    }

    // Log activity
    $oldStatus = $task['status'];
    $pdo->prepare("INSERT INTO project_activity_log (project_id, user_id, action, description, entity_type, entity_id)
        VALUES (?, ?, 'task_status_changed', ?, 'task', ?)")
        ->execute([$projectId, $actorId,
            "Task \"" . $task['title'] . "\" moved from {$oldStatus} to {$newStatus}",
            $taskId]);

    echo json_encode([
        'success'          => true,
        'new_status'       => $newStatus,
        'progress_percent' => $newProgress,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
