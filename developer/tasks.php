<?php
/**
 * Developer — My Tasks
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
if ($_SESSION['user_role'] !== 'developer') { redirect(BASE_URL . '/' . $_SESSION['user_role'] . '/'); }
require_once BASE_PATH . '/includes/activity_logger.php';
update_online_status($pdo, $_SESSION['user_id']);

$userId     = (int)$_SESSION['user_id'];
$csrf_token = generateCsrfToken();
$msg        = '';
$msgType    = 'success';

// Ensure tables exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        assigned_to INT DEFAULT NULL,
        created_by INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
        status ENUM('pending','in_progress','completed','on_hold') DEFAULT 'pending',
        due_date DATE DEFAULT NULL,
        completed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS task_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request.'; $msgType = 'danger';
    } else {
        $taskId    = (int)($_POST['task_id'] ?? 0);
        $newStatus = in_array($_POST['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? $_POST['status'] : null;
        if ($taskId && $newStatus) {
            // Verify this task is assigned to me
            $check = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND assigned_to=?");
            $check->execute([$taskId, $userId]);
            if ($check->fetch()) {
                $completed_at = ($newStatus === 'completed') ? date('Y-m-d H:i:s') : null;
                $pdo->prepare("UPDATE tasks SET status=?, completed_at=? WHERE id=?")->execute([$newStatus, $completed_at, $taskId]);
                log_activity($pdo, $userId, 'task_status_updated', "Task #{$taskId} status set to {$newStatus}", 'task', $taskId);
                $msg = 'Status updated.';
            } else {
                $msg = 'Task not found or access denied.'; $msgType = 'danger';
            }
        }
    }
    header("Location: tasks.php?msg=" . urlencode($msg) . "&msgtype=$msgType"); exit;
}

// Handle add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_comment') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid request.'; $msgType = 'danger';
    } else {
        $taskId  = (int)($_POST['task_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        if ($taskId && $comment) {
            $check = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND assigned_to=?");
            $check->execute([$taskId, $userId]);
            if ($check->fetch()) {
                $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")
                    ->execute([$taskId, $userId, $comment]);
                $msg = 'Comment added.';
            } else {
                $msg = 'Access denied.'; $msgType = 'danger';
            }
        }
    }
    header("Location: tasks.php?msg=" . urlencode($msg) . "&msgtype=$msgType"); exit;
}

if (!empty($_GET['msg'])) {
    $msg     = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
    $msgType = htmlspecialchars($_GET['msgtype'] ?? 'success', ENT_QUOTES, 'UTF-8');
}

// Filters
$filterProject = (int)($_GET['project_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE t.assigned_to = ?";
$params = [$userId];
if ($filterProject) { $where .= " AND t.project_id=?"; $params[] = $filterProject; }
if ($filterStatus)  { $where .= " AND t.status=?"; $params[] = $filterStatus; }

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT t.*, p.title AS project_title FROM tasks t
        LEFT JOIN projects p ON p.id=t.project_id
        $where ORDER BY FIELD(t.status,'in_progress','pending','on_hold','completed'), t.due_date ASC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (Exception $e) { $tasks = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// My projects for filter
try {
    $myProjects = $pdo->prepare("SELECT DISTINCT p.id, p.title FROM projects p INNER JOIN tasks t ON t.project_id=p.id WHERE t.assigned_to=? ORDER BY p.title");
    $myProjects->execute([$userId]);
    $myProjects = $myProjects->fetchAll();
} catch (Exception $e) { $myProjects = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks — SoftandPix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include BASE_PATH . '/includes/sidebar_developer.php'; ?>
<div class="topbar">
    <div class="topbar-left">
        <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <h5 class="mb-0">My Tasks</h5>
    </div>
</div>
<div class="main-content">

<?php if ($msg): ?>
<div class="alert alert-<?= h($msgType) ?> alert-dismissible fade show mx-3 mt-3">
    <i class="fas fa-info-circle me-2"></i><?= h($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

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

<!-- Tasks -->
<div class="table-card">
    <div class="card-header">My Tasks (<?= $total ?>)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Title</th><th>Project</th><th>Priority</th><th>Status</th><th>Due</th><th>Actions</th></tr>
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
                <td><span class="badge bg-<?= $prioColor ?>"><?= ucfirst($t['priority']) ?></span></td>
                <td><span class="badge bg-<?= $statColor ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span></td>
                <td class="<?= $overdue?'text-danger fw-bold':'' ?>">
                    <?= $t['due_date'] ? h(date('M j, Y', strtotime($t['due_date']))) : '—' ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#updateStatusModal"
                        data-taskid="<?= (int)$t['id'] ?>"
                        data-taskname="<?= h($t['title']) ?>"
                        data-status="<?= h($t['status']) ?>">
                        <i class="fas fa-edit"></i> Update
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                        data-bs-target="#addCommentModal"
                        data-taskid="<?= (int)$t['id'] ?>"
                        data-taskname="<?= h($t['title']) ?>">
                        <i class="fas fa-comment"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No tasks assigned to you.</td></tr>
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

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="task_id" id="modalTaskId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="modalTaskName">Update Task Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">New Status</label>
                    <select name="status" id="modalStatus" class="form-select form-select-lg">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Comment Modal -->
<div class="modal fade" id="addCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="add_comment">
            <input type="hidden" name="task_id" id="commentTaskId">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="commentTaskName">Add Comment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">Comment / Note</label>
                    <textarea name="comment" class="form-control" rows="4" required placeholder="Enter your comment or note about this task..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Add Comment</button>
                </div>
            </div>
        </form>
    </div>
</div>

</div><!-- /main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-target="#updateStatusModal"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('modalTaskId').value = btn.dataset.taskid;
        document.getElementById('modalTaskName').textContent = btn.dataset.taskname;
        document.getElementById('modalStatus').value = btn.dataset.status;
    });
});
document.querySelectorAll('[data-bs-target="#addCommentModal"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('commentTaskId').value = btn.dataset.taskid;
        document.getElementById('commentTaskName').textContent = btn.dataset.taskname;
    });
});
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}
</script>
</body>
</html>
