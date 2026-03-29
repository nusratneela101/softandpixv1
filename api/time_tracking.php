<?php
/**
 * Time Tracking API — AJAX endpoints for timer operations
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'start':
            $project_id = (int)($_POST['project_id'] ?? 0);
            $task_id    = ($_POST['task_id'] ?? '') ?: null;
            $desc       = mb_substr($_POST['description'] ?? '', 0, 500);
            if (!$project_id) { echo json_encode(['success'=>false,'message'=>'project_id required']); break; }
            $pdo->prepare("DELETE FROM active_timers WHERE user_id=?")->execute([$user_id]);
            $pdo->prepare("INSERT INTO active_timers (user_id, project_id, task_id, start_time, description) VALUES (?,?,?,NOW(),?)")->execute([$user_id, $project_id, $task_id, $desc]);
            echo json_encode(['success'=>true]);
            break;

        case 'stop':
            $stmt = $pdo->prepare("SELECT * FROM active_timers WHERE user_id=?");
            $stmt->execute([$user_id]);
            $timer = $stmt->fetch();
            if (!$timer) { echo json_encode(['success'=>false,'message'=>'No active timer']); break; }
            $duration = (int)round((time() - strtotime($timer['start_time'])) / 60);
            if ($duration < 1) $duration = 1;
            $pdo->prepare(
                "INSERT INTO time_entries (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual)
                 VALUES (?,?,?,?,?,NOW(),?,0)"
            )->execute([$user_id, $timer['project_id'], $timer['task_id'], $timer['description'], $timer['start_time'], $duration]);
            $pdo->prepare("DELETE FROM active_timers WHERE user_id=?")->execute([$user_id]);
            echo json_encode(['success'=>true,'duration_minutes'=>$duration]);
            break;

        case 'create':
            $project_id = (int)($_POST['project_id'] ?? 0);
            $task_id    = ($_POST['task_id'] ?? '') ?: null;
            $hours      = max(0, (int)($_POST['hours'] ?? 0));
            $minutes    = max(0, min(59, (int)($_POST['minutes'] ?? 0)));
            $total      = ($hours * 60) + $minutes;
            $date       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
            $desc       = mb_substr($_POST['description'] ?? '', 0, 500);
            if (!$project_id || $total < 1) { echo json_encode(['success'=>false,'message'=>'Invalid data']); break; }
            $pdo->prepare(
                "INSERT INTO time_entries (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual)
                 VALUES (?,?,?,?,?,?,?,1)"
            )->execute([$user_id, $project_id, $task_id, $desc, $date . ' 09:00:00', $date . ' ' . str_pad($hours,2,'0',STR_PAD_LEFT) . ':' . str_pad($minutes,2,'0',STR_PAD_LEFT) . ':00', $total]);
            echo json_encode(['success'=>true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $check = $pdo->prepare("SELECT id FROM time_entries WHERE id=? AND user_id=? AND is_approved=0");
            $check->execute([$id, $user_id]);
            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM time_entries WHERE id=?")->execute([$id]);
                echo json_encode(['success'=>true]);
            } else {
                echo json_encode(['success'=>false,'message'=>'Not found or already approved']);
            }
            break;

        case 'get_entries':
            $stmt = $pdo->prepare(
                "SELECT te.*, p.name as project_name, t.title as task_title
                 FROM time_entries te
                 JOIN projects p ON p.id=te.project_id
                 LEFT JOIN tasks t ON t.id=te.task_id
                 WHERE te.user_id=? ORDER BY te.start_time DESC LIMIT 50"
            );
            $stmt->execute([$user_id]);
            echo json_encode(['success'=>true,'entries'=>$stmt->fetchAll()]);
            break;

        case 'get_active':
            $stmt = $pdo->prepare("SELECT at.*, p.name as project_name FROM active_timers at JOIN projects p ON p.id=at.project_id WHERE at.user_id=?");
            $stmt->execute([$user_id]);
            $timer = $stmt->fetch();
            echo json_encode(['success'=>true,'timer'=>$timer ?: null]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
} catch (Exception $e) {
    error_log('time_tracking api: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
