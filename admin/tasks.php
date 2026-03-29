<?php
/**
 * Admin — Task Management
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/activity_logger.php';
requireAdmin();

$csrf_token = generateCsrfToken();
$msg = '';
$msgType = 'success';

// Ensure tasks tables exist
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

$adminId = (int)($_SESSION['admin_id'] ?? 0);

// Handle Create Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid CSRF token.'; $msgType = 'danger';
    } else {
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $assigned   = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $priority   = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $status     = in_array($_POST['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? $_POST['status'] : 'pending';
        $due_date   = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if (empty($title) || !$project_id) {
            $msg = 'Title and project are required.'; $msgType = 'danger';
        } else {
            try {
                // Get admin user_id from users table
                $adminUserStmt = $pdo->prepare("SELECT id FROM users WHERE role='admin' LIMIT 1");
                $adminUserStmt->execute();
                $createdBy = (int)($adminUserStmt->fetchColumn() ?: 1);

                $stmt = $pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, description, priority, status, due_date) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$project_id, $assigned, $createdBy, $title, $desc, $priority, $status, $due_date]);
                $taskId = (int)$pdo->lastInsertId();
                log_activity($pdo, $createdBy, 'task_created', "Task '{$title}' created", 'task', $taskId);
                $msg = 'Task created successfully.';
            } catch (Exception $e) { $msg = 'Error: ' . $e->getMessage(); $msgType = 'danger'; }
        }
    }
    header("Location: tasks.php?msg=" . urlencode($msg) . "&msgtype=$msgType"); exit;
}

// Handle Edit Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Invalid CSRF token.'; $msgType = 'danger';
    } else {
        $id         = (int)($_POST['task_id'] ?? 0);
        $title      = trim($_POST['title'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $assigned   = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $priority   = in_array($_POST['priority'] ?? '', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $status     = in_array($_POST['status'] ?? '', ['pending','in_progress','completed','on_hold']) ? $_POST['status'] : 'pending';
        $due_date   = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        if ($id && !empty($title)) {
            try {
                $pdo->prepare("UPDATE tasks SET project_id=?, assigned_to=?, title=?, description=?, priority=?, status=?, due_date=?, completed_at=? WHERE id=?")
                    ->execute([$project_id, $assigned, $title, $desc, $priority, $status, $due_date, $completed_at, $id]);
                log_activity($pdo, null, 'task_updated', "Task #{$id} '{$title}' updated", 'task', $id);
                $msg = 'Task updated.';
            } catch (Exception $e) { $msg = 'Error: ' . $e->getMessage(); $msgType = 'danger'; }
        }
    }
    header("Location: tasks.php?msg=" . urlencode($msg) . "&msgtype=$msgType"); exit;
}

// Handle Delete Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $id = (int)($_POST['task_id'] ?? 0);
        if ($id) {
            try {
                $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
                log_activity($pdo, null, 'task_deleted', "Task #{$id} deleted", 'task', $id);
                $msg = 'Task deleted.';
            } catch (Exception $e) { $msg = 'Delete failed.'; $msgType = 'danger'; }
        }
    }
    header("Location: tasks.php?msg=" . urlencode($msg) . "&msgtype=$msgType"); exit;
}

// Flash from redirect
if (!empty($_GET['msg'])) {
    $msg     = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
    $msgType = htmlspecialchars($_GET['msgtype'] ?? 'success', ENT_QUOTES, 'UTF-8');
}

// Filters & pagination
$filterProject   = (int)($_GET['project_id'] ?? 0);
$filterDeveloper = (int)($_GET['developer_id'] ?? 0);
$filterStatus    = trim($_GET['status'] ?? '');
$filterPriority  = trim($_GET['priority'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($filterProject)   { $where .= " AND t.project_id=?"; $params[] = $filterProject; }
if ($filterDeveloper) { $where .= " AND t.assigned_to=?"; $params[] = $filterDeveloper; }
if ($filterStatus)    { $where .= " AND t.status=?"; $params[] = $filterStatus; }
if ($filterPriority)  { $where .= " AND t.priority=?"; $params[] = $filterPriority; }

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t $where");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT t.*, p.title AS project_title, u.name AS developer_name FROM tasks t
        LEFT JOIN projects p ON p.id=t.project_id
        LEFT JOIN users u ON u.id=t.assigned_to
        $where ORDER BY t.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (Exception $e) { $tasks = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// Load projects & developers for dropdowns
try {
    $projects   = $pdo->query("SELECT id, title FROM projects ORDER BY title")->fetchAll();
    $developers = $pdo->query("SELECT id, name FROM users WHERE role IN ('developer','admin') ORDER BY name")->fetchAll();
} catch (Exception $e) { $projects = []; $developers = []; }

require_once BASE_PATH . '/admin/includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-clipboard-check me-2"></i>Task Management</h1>
        <p>Create and manage project tasks</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
        <i class="bi bi-plus-circle me-1"></i>New Task
    </button>
</div>
<div class="container-fluid">
<?php if ($msg): ?>
<div class="alert alert-<?= h($msgType) ?> alert-dismissible fade show"><i class="bi bi-info-circle me-2"></i><?= h($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <select name="project_id" class="form-select">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $pr): ?>
                        <option value="<?= (int)$pr['id'] ?>" <?= $filterProject===$pr['id']?'selected':'' ?>><?= h($pr['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="developer_id" class="form-select">
                        <option value="">All Developers</option>
                        <?php foreach ($developers as $dev): ?>
                        <option value="<?= (int)$dev['id'] ?>" <?= $filterDeveloper===$dev['id']?'selected':'' ?>><?= h($dev['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','in_progress','completed','on_hold'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <?php foreach (['low','medium','high','urgent'] as $p): ?>
                        <option value="<?= $p ?>" <?= $filterPriority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">Filter</button>
                    <a href="tasks.php" class="btn btn-outline-secondary ms-1">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Task List -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th><th>Title</th><th>Project</th><th>Assigned To</th>
                            <th>Priority</th><th>Status</th><th>Due Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tasks as $t):
                        $prioColor = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$t['priority']] ?? 'secondary';
                        $statColor = ['pending'=>'warning','in_progress'=>'primary','completed'=>'success','on_hold'=>'secondary'][$t['status']] ?? 'secondary';
                        $overdue   = $t['due_date'] && $t['status'] !== 'completed' && strtotime($t['due_date']) < time();
                    ?>
                    <tr>
                        <td><?= (int)$t['id'] ?></td>
                        <td><?= h($t['title']) ?></td>
                        <td><?= h($t['project_title'] ?? '—') ?></td>
                        <td><?= h($t['developer_name'] ?? 'Unassigned') ?></td>
                        <td><span class="badge bg-<?= $prioColor ?>"><?= ucfirst($t['priority']) ?></span></td>
                        <td><span class="badge bg-<?= $statColor ?>"><?= ucwords(str_replace('_',' ',$t['status'])) ?></span></td>
                        <td class="<?= $overdue?'text-danger fw-semibold':'' ?>">
                            <?= $t['due_date'] ? h(date('M j, Y', strtotime($t['due_date']))) : '—' ?>
                            <?php if ($overdue): ?><i class="bi bi-exclamation-triangle ms-1"></i><?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-btn"
                                data-id="<?= (int)$t['id'] ?>"
                                data-title="<?= h($t['title']) ?>"
                                data-desc="<?= h($t['description'] ?? '') ?>"
                                data-project="<?= (int)$t['project_id'] ?>"
                                data-assigned="<?= (int)($t['assigned_to'] ?? 0) ?>"
                                data-priority="<?= h($t['priority']) ?>"
                                data-status="<?= h($t['status']) ?>"
                                data-duedate="<?= h($t['due_date'] ?? '') ?>"
                                data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                title="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this task?')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tasks)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">No tasks found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i===$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&project_id=<?= $filterProject ?>&developer_id=<?= $filterDeveloper ?>&status=<?= urlencode($filterStatus) ?>&priority=<?= urlencode($filterPriority) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= taskFormFields($projects, $developers) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="editTaskForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="task_id" id="editTaskId">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= taskFormFields($projects, $developers, 'edit_') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-lg me-1"></i>Update Task</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('editTaskId').value      = btn.dataset.id;
        document.getElementById('edit_title').value      = btn.dataset.title;
        document.getElementById('edit_description').value= btn.dataset.desc;
        document.getElementById('edit_project_id').value = btn.dataset.project;
        document.getElementById('edit_assigned_to').value= btn.dataset.assigned;
        document.getElementById('edit_priority').value   = btn.dataset.priority;
        document.getElementById('edit_status').value     = btn.dataset.status;
        document.getElementById('edit_due_date').value   = btn.dataset.duedate;
    });
});
</script>
<?php require_once BASE_PATH . '/admin/includes/footer.php'; ?>
<?php
function taskFormFields($projects, $developers, $prefix = '') {
    ob_start();
    ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="<?= $prefix ?>title" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Due Date</label>
            <input type="date" name="due_date" id="<?= $prefix ?>due_date" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" id="<?= $prefix ?>description" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Project <span class="text-danger">*</span></label>
            <select name="project_id" id="<?= $prefix ?>project_id" class="form-select" required>
                <option value="">— Select Project —</option>
                <?php foreach ($projects as $pr): ?>
                <option value="<?= (int)$pr['id'] ?>"><?= htmlspecialchars($pr['title'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Assign To</label>
            <select name="assigned_to" id="<?= $prefix ?>assigned_to" class="form-select">
                <option value="">— Unassigned —</option>
                <?php foreach ($developers as $dev): ?>
                <option value="<?= (int)$dev['id'] ?>"><?= htmlspecialchars($dev['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Priority</label>
            <select name="priority" id="<?= $prefix ?>priority" class="form-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" id="<?= $prefix ?>status" class="form-select">
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="on_hold">On Hold</option>
            </select>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
