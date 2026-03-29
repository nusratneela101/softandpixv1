<?php
/**
 * Reports API — returns Chart.js-ready JSON data
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

$type   = $_GET['type']   ?? '';
$period = $_GET['period'] ?? 'monthly';
$from   = $_GET['from']   ?? date('Y-01-01');
$to     = $_GET['to']     ?? date('Y-m-d');
$status = $_GET['status'] ?? 'all';
$metric = $_GET['metric'] ?? 'tasks_completed';
$groupby = $_GET['groupby'] ?? 'priority';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-01-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

try {
    switch ($type) {
        case 'revenue':
            $fmt = match($period) {
                'quarterly' => "CONCAT(YEAR(created_at), '-Q', QUARTER(created_at))",
                'yearly'    => "YEAR(created_at)",
                default     => "DATE_FORMAT(created_at,'%Y-%m')",
            };
            $stmt = $pdo->prepare(
                "SELECT {$fmt} as period, SUM(amount) as revenue
                 FROM invoices WHERE status='paid' AND created_at BETWEEN ? AND ?
                 GROUP BY period ORDER BY period"
            );
            $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'labels' => array_column($rows, 'period'), 'values' => array_map(fn($r) => (float)$r['revenue'], $rows)]);
            break;

        case 'projects':
            $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM projects GROUP BY status");
            $rows = $stmt->fetchAll();
            $labelMap = ['pending'=>'Pending','active'=>'Active','completed'=>'Completed','on_hold'=>'On Hold','cancelled'=>'Cancelled'];
            echo json_encode(['success' => true, 'labels' => array_map(fn($r) => $labelMap[$r['status']] ?? $r['status'], $rows), 'values' => array_column($rows, 'cnt')]);
            break;

        case 'developers':
            $stmt = $pdo->prepare(
                "SELECT u.name, COUNT(t.id) as cnt
                 FROM users u LEFT JOIN tasks t ON t.assigned_to=u.id AND t.status='completed' AND t.completed_at BETWEEN ? AND ?
                 WHERE u.role='developer'
                 GROUP BY u.id, u.name ORDER BY cnt DESC LIMIT 10"
            );
            $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'labels' => array_column($rows, 'name'), 'values' => array_column($rows, 'cnt')]);
            break;

        case 'invoices':
            $stmt = $pdo->prepare(
                "SELECT
                    SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN status='unpaid' AND due_date >= CURDATE() THEN 1 ELSE 0 END) as unpaid,
                    SUM(CASE WHEN status='unpaid' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                 FROM invoices WHERE created_at BETWEEN ? AND ?"
            );
            $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
            $row = $stmt->fetch();
            echo json_encode(['success' => true, 'labels' => ['Paid','Unpaid','Overdue'], 'values' => [(int)$row['paid'], (int)$row['unpaid'], (int)$row['overdue']]]);
            break;

        case 'tasks':
            if ($groupby === 'priority') {
                $stmt = $pdo->query("SELECT priority, COUNT(*) as cnt FROM tasks GROUP BY priority ORDER BY FIELD(priority,'low','medium','high','urgent')");
                $rows = $stmt->fetchAll();
                $labelMap = ['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'];
                echo json_encode(['success' => true, 'labels' => array_map(fn($r) => $labelMap[$r['priority']] ?? $r['priority'], $rows), 'values' => array_column($rows, 'cnt')]);
            } else {
                $stmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM tasks GROUP BY status");
                $rows = $stmt->fetchAll();
                echo json_encode(['success' => true, 'labels' => array_column($rows, 'status'), 'values' => array_column($rows, 'cnt')]);
            }
            break;

        case 'clients':
            $stmt = $pdo->prepare(
                "SELECT u.name, COUNT(DISTINCT p.id) as projects, COALESCE(SUM(i.amount),0) as revenue
                 FROM users u
                 LEFT JOIN projects p ON p.client_id = u.id
                 LEFT JOIN invoices i ON i.client_id = u.id AND i.status='paid' AND i.created_at BETWEEN ? AND ?
                 WHERE u.role='client'
                 GROUP BY u.id, u.name ORDER BY revenue DESC LIMIT 10"
            );
            $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'rows' => $rows]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown report type']);
    }
} catch (Exception $e) {
    error_log('reports api: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Query error']);
}
