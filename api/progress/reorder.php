<?php
/**
 * API: Reorder tasks or milestones
 * POST: type ('task'|'milestone'), items=[{id, sort_order}, ...]
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

$type  = $input['type']  ?? 'task';
$items = $input['items'] ?? [];

if (!in_array($type, ['task', 'milestone']) || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$table = ($type === 'task') ? 'project_tasks' : 'project_milestones';

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE {$table} SET sort_order=? WHERE id=?");
    foreach ($items as $item) {
        $id         = (int)($item['id']         ?? 0);
        $sort_order = (int)($item['sort_order'] ?? 0);
        if ($id > 0) {
            $stmt->execute([$sort_order, $id]);
        }
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
