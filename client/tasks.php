<?php
/**
 * Client — Task Overview (read-only)
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
if ($_SESSION['user_role'] !== 'client') { redirect(BASE_URL . '/' . $_SESSION['user_role'] . '/'); }
update_online_status($pdo, $_SESSION['user_id']);

$userId = (int)$_SESSION['user_id'];

// Filters
$filterProject = (int)($_GET['project_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// Get client's project IDs first
try {
    $projStmt = $pdo->prepare("SELECT id, title FROM projects WHERE client_id=? ORDER BY title");
    $projStmt->execute([$userId]);
    $myProjects = $projStmt->fetchAll();
    $projectIds = array_column($myProjects, 'id');
} catch (Exception $e) { $myProjects = []; $projectIds = []; }

$tasks = []; $total = 0;
if (!empty($projectIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($projectIds), '?'));
    $where  = "WHERE t.project_id IN ($inPlaceholders)";
    $params = $projectIds;
    if ($filterProject && in_array($filterProject, $projectIds)) { $where .= " AND t.project_id=?"; $params[] = $filterProject; }
    if ($filterStatus)  { $where .= " AND t.status=?"; $params[] = $filterStatus; }

    try {
        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t $where");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT t.*, p.title AS project_title, u.name AS developer_name FROM tasks t
            LEFT JOIN projects p ON p.id=t.project_id
            LEFT JOIN users u ON u.id=t.assigned_to
            $where ORDER BY t.due_date ASC, t.created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
    } catch (Exception $e) { $tasks = []; $total = 0; }
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// Summary stats
$summaryTotal = $total;
try {
    if (!empty($projectIds)) {
        $inP = implode(',', array_fill(0, count($projectIds), '?'));
        $s = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM tasks WHERE project_id IN ($inP) GROUP BY status");
        $s->execute($projectIds);
        $summaryByStatus = array_column($s->fetchAll(), 'cnt', 'status');
    } else { $summaryByStatus = []; }
} catch (Exception $e) { $summaryByStatus = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Tasks — SoftandPix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar_client.php'; ?>
<div class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <h5 class="mb-0">Project Tasks</h5>
    </div>
</div>
<div class="main-content">

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="table-card text-center p-3">
            <div style="font-size:2rem;font-weight:700;color:#667eea"><?= (int)($summaryByStatus['pending'] ?? 0) ?></div>
            <div class="text-muted small">Pending</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="table-card text-center p-3">
            <div style="font-size:2rem;font-weight:700;color:#0d6efd"><?= (int)($summaryByStatus['in_progress'] ?? 0) ?></div>
            <div class="text-muted small">In Progress</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="table-card text-center p-3">
            <div style="font-size:2rem;font-weight:700;color:#198754"><?= (int)($summaryByStatus['completed'] ?? 0) ?></div>
            <div class="text-muted small">Completed</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="table-card text-center p-3">
            <div style="font-size:2rem;font-weight:700;color:#6c757d"><?= (int)($summaryByStatus['on_hold'] ?? 0) ?></div>
            <div class="text-muted small">On Hold</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="table-card mb-3">
    <form method="GET" class="row g-2 p-3">
        <div class="col-md-4">
            <select name="project_id" class="form-select">
                <option value="">All Projects</option>
                <?php foreach ($myProjects as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>" <?= $filterProject===$pr['id']?'selected':'' ?>><?= h($pr['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <?php foreach (['pending','in_progress','completed','on_hold'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="tasks.php" class="btn btn-outline-secondary ms-1">Clear</a>
        </div>
    </form>
</div>

<!-- Task List (read-only) -->
<div class="table-card">
    <div class="card-header">Tasks for My Projects (<?= $total ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Title</th><th>Project</th><th>Assigned To</th><th>Priority</th><th>Status</th><th>Due Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t):
                $prioColor = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$t['priority']] ?? 'secondary';
                $statColor = ['pending'=>'warning','in_progress'=>'primary','completed'=>'success','on_hold'=>'secondary'][$t['status']] ?? 'secondary';
                $overdue   = $t['due_date'] && $t['status'] !== 'completed' && strtotime($t['due_date']) < time();
            ?>
            <tr>
                <td><?= (int)$t['id'] ?></td>
                <td>
                    <strong><?= h($t['title']) ?></strong>
                    <?php if ($t['description']): ?>
                    <div class="text-muted small"><?= h(mb_strimwidth($t['description'], 0, 80, '...')) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= h($t['project_title'] ?? '—') ?></td>
                <td><?= h($t['developer_name'] ?? 'Unassigned') ?></td>
                <td><span class="badge bg-<?= $prioColor ?>"><?= ucfirst($t['priority']) ?></span></td>
                <td><span class="badge bg-<?= $statColor ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span></td>
                <td class="<?= $overdue?'text-danger fw-bold':'' ?>">
                    <?= $t['due_date'] ? h(date('M j, Y', strtotime($t['due_date']))) : '—' ?>
                    <?php if ($overdue): ?><i class="fas fa-exclamation-triangle ms-1" title="Overdue"></i><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">
                <?= empty($myProjects) ? 'You have no projects yet.' : 'No tasks found for your projects.' ?>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3">
        <nav><ul class="pagination pagination-sm mb-0">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $i ?>&project_id=<?= $filterProject ?>&status=<?= urlencode($filterStatus) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

</div><!-- /main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>
