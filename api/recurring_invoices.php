<?php
/**
 * Recurring Invoices API — AJAX CRUD for admin
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/activity_logger.php';
requireAdmin();

header('Content-Type: application/json');
$admin_id = $_SESSION['admin_id'];
$action   = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query(
                "SELECT ri.*, u.name as client_name, p.name as project_name
                 FROM recurring_invoices ri
                 JOIN users u ON u.id=ri.client_id
                 LEFT JOIN projects p ON p.id=ri.project_id
                 ORDER BY ri.created_at DESC"
            );
            echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()]);
            break;

        case 'update_status':
            $id     = (int)($_POST['id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (!$id || !in_array($status, ['active','paused','cancelled'], true)) {
                echo json_encode(['success'=>false,'message'=>'Invalid input']);
                break;
            }
            $pdo->prepare("UPDATE recurring_invoices SET status=? WHERE id=?")->execute([$status, $id]);
            log_activity($pdo, $admin_id, 'recurring_invoice_'.$status, "Recurring invoice #$id => $status", 'recurring_invoice', $id);
            echo json_encode(['success'=>true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success'=>false,'message'=>'No ID']); break; }
            $pdo->prepare("DELETE FROM recurring_invoices WHERE id=?")->execute([$id]);
            echo json_encode(['success'=>true]);
            break;

        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
} catch (Exception $e) {
    error_log('recurring_invoices api: ' . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Server error']);
}
