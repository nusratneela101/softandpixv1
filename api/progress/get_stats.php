<?php
/**
 * API: Get project progress stats
 * GET: project_id
 * Returns: progress_percent, task_counts, milestone_counts, recent_activity
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$projectId = (int)($_GET['project_id'] ?? 0);
if (!$projectId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'project_id required']);
    exit;
}

try {
    // Project info
    $proj = $pdo->prepare("SELECT progress_percent, title FROM projects WHERE id=?");
    $proj->execute([$projectId]);
    $project = $proj->fetch();

    if (!$project) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        exit;
    }

    // Task counts by status
    $taskStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM project_tasks WHERE project_id=? GROUP BY status");
    $taskStmt->execute([$projectId]);
    $taskRows = $taskStmt->fetchAll();
    $taskCounts = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'completed' => 0, 'total' => 0];
    foreach ($taskRows as $row) {
        if (isset($taskCounts[$row['status']])) {
            $taskCounts[$row['status']] = (int)$row['cnt'];
        }
        $taskCounts['total'] += (int)$row['cnt'];
    }

    // Milestone counts
    $msStmt = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM project_milestones WHERE project_id=? GROUP BY status");
    $msStmt->execute([$projectId]);
    $msRows = $msStmt->fetchAll();
    $msCounts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'total' => 0];
    foreach ($msRows as $row) {
        if (isset($msCounts[$row['status']])) {
            $msCounts[$row['status']] = (int)$row['cnt'];
        }
        $msCounts['total'] += (int)$row['cnt'];
    }

    // Recent activity
    $actStmt = $pdo->prepare("SELECT pal.*, u.name AS actor_name FROM project_activity_log pal
        LEFT JOIN users u ON u.id = pal.user_id
        WHERE pal.project_id=? ORDER BY pal.created_at DESC LIMIT 10");
    $actStmt->execute([$projectId]);
    $recentActivity = $actStmt->fetchAll();

    echo json_encode([
        'success'          => true,
        'progress_percent' => (int)$project['progress_percent'],
        'task_counts'      => $taskCounts,
        'milestone_counts' => $msCounts,
        'recent_activity'  => $recentActivity,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
