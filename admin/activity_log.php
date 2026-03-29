<?php
/**
 * Admin — Activity Log
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/auth.php';
requireAdmin();

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_created (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where  = "WHERE 1=1";
    $params = [];
    if (!empty($_GET['user_id'])) { $where .= " AND al.user_id=?"; $params[] = (int)$_GET['user_id']; }
    if (!empty($_GET['action']))  { $where .= " AND al.action LIKE ?"; $params[] = '%' . $_GET['action'] . '%'; }
    if (!empty($_GET['date_from'])){ $where .= " AND DATE(al.created_at) >= ?"; $params[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $_GET['date_to']; }

    $stmt = $pdo->prepare("SELECT al.id, u.name AS user_name, al.action, al.details, al.entity_type, al.entity_id, al.ip_address, al.created_at
        FROM activity_log al LEFT JOIN users u ON u.id=al.user_id $where ORDER BY al.created_at DESC LIMIT 5000");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User', 'Action', 'Details', 'Entity Type', 'Entity ID', 'IP Address', 'Date/Time']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['id'], $row['user_name'] ?? 'System', $row['action'], $row['details'], $row['entity_type'], $row['entity_id'], $row['ip_address'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

// Filters
$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterAction = trim($_GET['action'] ?? '');
$filterFrom   = trim($_GET['date_from'] ?? '');
$filterTo     = trim($_GET['date_to'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($filterUser)   { $where .= " AND al.user_id=?"; $params[] = $filterUser; }
if ($filterAction) { $where .= " AND al.action LIKE ?"; $params[] = '%' . $filterAction . '%'; }
if ($filterFrom)   { $where .= " AND DATE(al.created_at) >= ?"; $params[] = $filterFrom; }
if ($filterTo)     { $where .= " AND DATE(al.created_at) <= ?"; $params[] = $filterTo; }

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log al $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT al.*, u.name AS user_name, u.role AS user_role
        FROM activity_log al LEFT JOIN users u ON u.id=al.user_id
        $where ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (Exception $e) { $logs = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// Users for filter dropdown
try {
    $users = $pdo->query("SELECT DISTINCT u.id, u.name FROM users u INNER JOIN activity_log al ON al.user_id=u.id ORDER BY u.name")->fetchAll();
} catch (Exception $e) { $users = []; }

require_once BASE_PATH . '/admin/includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-clock-history me-2"></i>Activity Log</h1>
        <p>Audit trail of all system actions</p>
    </div>
    <a href="activity_log.php?export=csv&<?= http_build_query(['user_id'=>$filterUser,'action'=>$filterAction,'date_from'=>$filterFrom,'date_to'=>$filterTo]) ?>"
       class="btn btn-success"><i class="bi bi-download me-1"></i>Export CSV</a>
</div>
<div class="container-fluid">

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $filterUser===(int)$u['id']?'selected':'' ?>><?= h($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="action" class="form-control" placeholder="Action keyword..." value="<?= h($filterAction) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="<?= h($filterFrom) ?>" title="From date">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="<?= h($filterTo) ?>" title="To date">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <a href="activity_log.php" class="btn btn-outline-secondary ms-1">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Entity</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log):
                        $actionColor = match (true) {
                            str_contains($log['action'], 'delete') => 'danger',
                            str_contains($log['action'], 'create') || str_contains($log['action'], 'register') => 'success',
                            str_contains($log['action'], 'login') => 'primary',
                            str_contains($log['action'], 'update') || str_contains($log['action'], 'edit') => 'warning',
                            default => 'secondary'
                        };
                    ?>
                    <tr>
                        <td class="text-nowrap small"><?= h(date('M j Y, H:i', strtotime($log['created_at']))) ?></td>
                        <td>
                            <?php if ($log['user_name']): ?>
                            <span class="fw-semibold"><?= h($log['user_name']) ?></span>
                            <div class="text-muted" style="font-size:11px"><?= h($log['user_role'] ?? '') ?></div>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $actionColor ?>"><?= h(str_replace('_', ' ', $log['action'])) ?></span></td>
                        <td class="small"><?= h(mb_strimwidth($log['details'] ?? '', 0, 120, '...')) ?></td>
                        <td class="small text-nowrap">
                            <?php if ($log['entity_type']): ?>
                            <span class="badge bg-light text-dark border"><?= h($log['entity_type']) ?> #<?= (int)$log['entity_id'] ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= h($log['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No activity log entries found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex align-items-center justify-content-between">
            <small class="text-muted">Showing <?= ($offset+1) ?>–<?= min($offset+$perPage, $total) ?> of <?= $total ?> entries</small>
            <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(['user_id'=>$filterUser,'action'=>$filterAction,'date_from'=>$filterFrom,'date_to'=>$filterTo]) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once BASE_PATH . '/admin/includes/footer.php'; ?>
