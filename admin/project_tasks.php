<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

// Fetch project users for assignee dropdown
$projectUsers = [];
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.role FROM users u
        WHERE u.id = (SELECT client_id FROM projects WHERE id=?)
           OR u.id = (SELECT developer_id FROM projects WHERE id=?)
        UNION
        SELECT id, name, role FROM users WHERE role IN ('developer','editor','ui_designer','seo_specialist')
        ORDER BY name");
    $stmt->execute([$id, $id]);
    $projectUsers = $stmt->fetchAll();
} catch (Exception $e) {}

// Milestones for filter + task assignment
$milestones = [];
try {
    $ms = $pdo->prepare("SELECT id, title, status FROM project_milestones WHERE project_id=? ORDER BY sort_order ASC, id ASC");
    $ms->execute([$id]);
    $milestones = $ms->fetchAll();
} catch (Exception $e) {}

// Add task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $status  = in_array($_POST['status']??'', ['todo','in_progress','review','completed']) ? $_POST['status'] : 'todo';
        $priority = in_array($_POST['priority']??'', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $assignTo  = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $msId      = (int)($_POST['milestone_id'] ?? 0) ?: null;
        $estHrs    = !empty($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null;
        $dueDate   = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        if ($title) {
            try {
                $maxOrd = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM project_tasks WHERE project_id=$id AND status='$status'")->fetchColumn();
                $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
                $pdo->prepare("INSERT INTO project_tasks (project_id,milestone_id,assigned_to,title,description,status,priority,estimated_hours,due_date,sort_order,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$id, $msId, $assignTo, $title, $desc ?: null, $status, $priority, $estHrs, $dueDate, $maxOrd+1, $completedAt]);
                $newTaskId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id) VALUES (?,?,'task_created',?,'task',?)")
                    ->execute([$id, $_SESSION['admin_id'], "Task \"{$title}\" created in {$status}", $newTaskId]);
                // Auto-progress
                $tot  = (int)$pdo->query("SELECT COUNT(*) FROM project_tasks WHERE project_id=$id")->fetchColumn();
                $done = (int)$pdo->query("SELECT COUNT(*) FROM project_tasks WHERE project_id=$id AND status='completed'")->fetchColumn();
                $pct  = $tot > 0 ? (int)round($done/$tot*100) : 0;
                $pdo->prepare("UPDATE projects SET progress_percent=? WHERE id=? AND progress_auto_calculate=1")->execute([$pct, $id]);
                flashMessage('success', 'Task added.');
            } catch (Exception $e) { flashMessage('error', 'Failed: ' . $e->getMessage()); }
        }
    }
    header("Location: project_tasks.php?id=$id"); exit;
}

// Edit task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $taskId   = (int)($_POST['task_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $status   = in_array($_POST['status']??'', ['todo','in_progress','review','completed']) ? $_POST['status'] : 'todo';
        $priority = in_array($_POST['priority']??'', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $assignTo  = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $msId      = (int)($_POST['milestone_id'] ?? 0) ?: null;
        $estHrs    = !empty($_POST['estimated_hours']) ? (float)$_POST['estimated_hours'] : null;
        $actHrs    = !empty($_POST['actual_hours'])    ? (float)$_POST['actual_hours']    : null;
        $dueDate   = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        if ($taskId && $title) {
            try {
                $pdo->prepare("UPDATE project_tasks SET title=?,description=?,status=?,priority=?,assigned_to=?,milestone_id=?,estimated_hours=?,actual_hours=?,due_date=?,
                    completed_at=CASE WHEN ? = 'completed' AND completed_at IS NULL THEN NOW() WHEN ? != 'completed' THEN NULL ELSE completed_at END,
                    updated_at=NOW() WHERE id=? AND project_id=?")
                    ->execute([$title,$desc?:null,$status,$priority,$assignTo,$msId,$estHrs,$actHrs,$dueDate,$status,$status,$taskId,$id]);
                $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id) VALUES (?,?,'task_updated',?,'task',?)")
                    ->execute([$id, $_SESSION['admin_id'], "Task \"{$title}\" updated", $taskId]);
                // Auto-progress
                $tot  = (int)$pdo->query("SELECT COUNT(*) FROM project_tasks WHERE project_id=$id")->fetchColumn();
                $done = (int)$pdo->query("SELECT COUNT(*) FROM project_tasks WHERE project_id=$id AND status='completed'")->fetchColumn();
                $pct  = $tot > 0 ? (int)round($done/$tot*100) : 0;
                $pdo->prepare("UPDATE projects SET progress_percent=? WHERE id=? AND progress_auto_calculate=1")->execute([$pct, $id]);
                flashMessage('success', 'Task updated.');
            } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
        }
    }
    header("Location: project_tasks.php?id=$id"); exit;
}

// Delete task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            try {
                $pdo->prepare("DELETE FROM project_tasks WHERE id=? AND project_id=?")->execute([$taskId, $id]);
                flashMessage('success', 'Task deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
    }
    header("Location: project_tasks.php?id=$id"); exit;
}

// Fetch project
try {
    $pStmt = $pdo->prepare("SELECT * FROM projects WHERE id=?");
    $pStmt->execute([$id]);
    $project = $pStmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

// Filters
$filterMs       = (int)($_GET['milestone'] ?? 0);
$filterAssignee = (int)($_GET['assignee']  ?? 0);
$filterPriority = $_GET['priority'] ?? '';

$where  = "WHERE project_id = ?";
$params = [$id];
if ($filterMs)       { $where .= " AND milestone_id = ?"; $params[] = $filterMs; }
if ($filterAssignee) { $where .= " AND assigned_to = ?";  $params[] = $filterAssignee; }
if ($filterPriority && in_array($filterPriority, ['low','medium','high','urgent'])) {
    $where .= " AND priority = ?"; $params[] = $filterPriority;
}

// Load all tasks
$tasks = [];
try {
    $tStmt = $pdo->prepare("SELECT t.*, u.name AS assignee_name FROM project_tasks t
        LEFT JOIN users u ON u.id = t.assigned_to
        $where ORDER BY t.sort_order ASC, t.id ASC");
    $tStmt->execute($params);
    $rawTasks = $tStmt->fetchAll();
    foreach ($rawTasks as $t) {
        $tasks[$t['status']][] = $t;
    }
} catch (Exception $e) {}

$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<style>
.kanban-col { min-height: 400px; }
.kanban-col-header { border-radius: 8px 8px 0 0; }
.task-card { cursor: grab; transition: box-shadow .15s, opacity .15s; border-left: 4px solid transparent; }
.task-card:active { cursor: grabbing; }
.task-card.dragging { opacity: .4; }
.kanban-drop-zone { border: 2px dashed #dee2e6; border-radius: 8px; min-height: 60px; }
.kanban-drop-zone.drag-over { border-color: #0d6efd; background: #f0f7ff; }
.priority-low    { border-left-color: #198754; }
.priority-medium { border-left-color: #ffc107; }
.priority-high   { border-left-color: #fd7e14; }
.priority-urgent { border-left-color: #dc3545; }
.kanban-wrapper  { overflow-x: auto; }
</style>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-kanban me-2"></i>Kanban Board</h1>
        <p class="mb-0 text-muted"><?php echo h($project['title']); ?></p>
    </div>
    <div>
        <a href="project_progress.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2"><i class="bi bi-bar-chart-line me-1"></i>Dashboard</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
            <i class="bi bi-plus-circle me-1"></i>New Task
        </button>
    </div>
</div>

<div class="container-fluid">
    <!-- Filters -->
    <form method="GET" class="card border-0 shadow-sm mb-4">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Milestone</label>
                    <select name="milestone" class="form-select form-select-sm">
                        <option value="">All Milestones</option>
                        <?php foreach ($milestones as $ms): ?>
                        <option value="<?php echo $ms['id']; ?>" <?php echo $filterMs == $ms['id'] ? 'selected' : ''; ?>><?php echo h($ms['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Assignee</label>
                    <select name="assignee" class="form-select form-select-sm">
                        <option value="">All Assignees</option>
                        <?php foreach ($projectUsers as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo $filterAssignee == $pu['id'] ? 'selected' : ''; ?>><?php echo h($pu['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Priority</label>
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All Priorities</option>
                        <?php foreach (['low','medium','high','urgent'] as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $filterPriority === $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
                    <a href="project_tasks.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <!-- Kanban board -->
    <div class="kanban-wrapper">
        <div class="row g-3 flex-nowrap" style="min-width:900px;">
            <?php
            $columns = [
                'todo'        => ['label' => 'Todo',        'color' => '#6c757d', 'badge' => 'secondary'],
                'in_progress' => ['label' => 'In Progress', 'color' => '#0d6efd', 'badge' => 'primary'],
                'review'      => ['label' => 'Review',      'color' => '#ffc107', 'badge' => 'warning'],
                'completed'   => ['label' => 'Completed',   'color' => '#198754', 'badge' => 'success'],
            ];
            foreach ($columns as $colStatus => $col):
                $colTasks = $tasks[$colStatus] ?? [];
            ?>
            <div class="col" style="min-width:220px;">
                <div class="card border-0 shadow-sm kanban-col">
                    <div class="card-header py-2" style="background:<?php echo $col['color']; ?>;color:#fff;border-radius:8px 8px 0 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold"><?php echo $col['label']; ?></span>
                            <span class="badge bg-white text-dark"><?php echo count($colTasks); ?></span>
                        </div>
                    </div>
                    <div class="card-body p-2 kanban-drop-zone" data-status="<?php echo $colStatus; ?>">
                        <?php foreach ($colTasks as $task):
                            $prioColor = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$task['priority']] ?? 'secondary';
                            $isDue = $task['due_date'] && $task['status'] !== 'completed' && strtotime($task['due_date']) < time();
                        ?>
                        <div class="card mb-2 task-card priority-<?php echo $task['priority']; ?>"
                             draggable="true"
                             data-task-id="<?php echo $task['id']; ?>"
                             data-status="<?php echo $task['status']; ?>">
                            <div class="card-body p-2">
                                <div class="fw-semibold small mb-1"><?php echo h($task['title']); ?></div>
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <span class="badge bg-<?php echo $prioColor; ?> text-white" style="font-size:.65rem;"><?php echo ucfirst($task['priority']); ?></span>
                                    <?php if ($task['due_date']): ?>
                                    <span class="badge <?php echo $isDue ? 'bg-danger' : 'bg-light text-dark'; ?>" style="font-size:.65rem;">
                                        <i class="bi bi-calendar3"></i> <?php echo h(date('M j', strtotime($task['due_date']))); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($task['assignee_name']): ?>
                                <div class="d-flex align-items-center mt-1">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-1" style="width:20px;height:20px;font-size:.6rem;">
                                        <?php echo strtoupper(substr($task['assignee_name'],0,1)); ?>
                                    </div>
                                    <span class="text-muted" style="font-size:.7rem;"><?php echo h($task['assignee_name']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-end mt-1">
                                    <button class="btn btn-link btn-sm p-0 text-muted"
                                        onclick="openEditTask(<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card-footer bg-transparent border-0 p-2">
                        <button class="btn btn-sm btn-outline-secondary w-100"
                            onclick="quickAdd('<?php echo $colStatus; ?>')"
                            data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="bi bi-plus me-1"></i>Add Task
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="add_task" value="1">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="addTaskStatus" class="form-select">
                            <option value="todo">Todo</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Milestone</label>
                        <select name="milestone_id" class="form-select">
                            <option value="">No Milestone</option>
                            <?php foreach ($milestones as $ms): ?>
                            <option value="<?php echo $ms['id']; ?>"><?php echo h($ms['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Assignee</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($projectUsers as $pu): ?>
                            <option value="<?php echo $pu['id']; ?>"><?php echo h($pu['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estimated Hours</label>
                        <input type="number" name="estimated_hours" class="form-control" min="0" step="0.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Add Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="edit_task" value="1">
            <input type="hidden" name="task_id" id="etTaskId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                <form method="POST" class="ms-2" id="deleteTaskForm" onsubmit="return confirm('Delete this task?')">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="delete_task" value="1">
                    <input type="hidden" name="task_id" id="etDeleteId">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="etTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="etDesc" class="form-control" rows="3"></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="etStatus" class="form-select">
                            <option value="todo">Todo</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="etPriority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Milestone</label>
                        <select name="milestone_id" id="etMilestone" class="form-select">
                            <option value="">No Milestone</option>
                            <?php foreach ($milestones as $ms): ?>
                            <option value="<?php echo $ms['id']; ?>"><?php echo h($ms['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Assignee</label>
                        <select name="assigned_to" id="etAssignee" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach ($projectUsers as $pu): ?>
                            <option value="<?php echo $pu['id']; ?>"><?php echo h($pu['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Est. Hours</label>
                        <input type="number" name="estimated_hours" id="etEstHrs" class="form-control" min="0" step="0.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Actual Hours</label>
                        <input type="number" name="actual_hours" id="etActHrs" class="form-control" min="0" step="0.5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="etDue" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
            </div>
        </form>
    </div>
</div>

<script>
var csrfToken = '<?php echo h($csrf_token); ?>';

// Quick-add pre-selects column status
function quickAdd(status) {
    document.getElementById('addTaskStatus').value = status;
}

// Open edit task modal
function openEditTask(t) {
    document.getElementById('etTaskId').value    = t.id;
    document.getElementById('etDeleteId').value  = t.id;
    document.getElementById('etTitle').value     = t.title || '';
    document.getElementById('etDesc').value      = t.description || '';
    document.getElementById('etStatus').value    = t.status || 'todo';
    document.getElementById('etPriority').value  = t.priority || 'medium';
    document.getElementById('etMilestone').value = t.milestone_id || '';
    document.getElementById('etAssignee').value  = t.assigned_to || '';
    document.getElementById('etEstHrs').value    = t.estimated_hours || '';
    document.getElementById('etActHrs').value    = t.actual_hours || '';
    document.getElementById('etDue').value       = t.due_date || '';
    new bootstrap.Modal(document.getElementById('editTaskModal')).show();
}

// Kanban drag-and-drop
(function() {
    var dragging = null;

    document.querySelectorAll('.task-card').forEach(function(card) {
        card.addEventListener('dragstart', function(e) {
            dragging = card;
            setTimeout(() => card.classList.add('dragging'), 0);
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', function() {
            card.classList.remove('dragging');
            document.querySelectorAll('.kanban-drop-zone').forEach(z => z.classList.remove('drag-over'));
            dragging = null;
        });
    });

    document.querySelectorAll('.kanban-drop-zone').forEach(function(zone) {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('drag-over');
            if (dragging) {
                // Insert before closest card
                var afterEl = getDragAfterEl(zone, e.clientY);
                if (afterEl == null) {
                    zone.appendChild(dragging);
                } else {
                    zone.insertBefore(dragging, afterEl);
                }
            }
        });
        zone.addEventListener('dragleave', function() {
            zone.classList.remove('drag-over');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (!dragging) return;
            var newStatus  = zone.dataset.status;
            var taskId     = dragging.dataset.taskId;
            var oldStatus  = dragging.dataset.status;
            if (newStatus === oldStatus) { saveOrder(zone, newStatus); return; }

            // AJAX update
            fetch('<?php echo '../api/progress/update_task.php'; ?>', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ csrf_token: csrfToken, task_id: parseInt(taskId), new_status: newStatus })
            })
            .then(r => r.json())
            .then(function(data) {
                if (data.success) {
                    dragging.dataset.status = newStatus;
                    // Update column counters
                    document.querySelectorAll('.kanban-drop-zone').forEach(function(z) {
                        var header = z.closest('.card').querySelector('.badge.bg-white');
                        if (header) header.textContent = z.querySelectorAll('.task-card').length;
                    });
                } else {
                    alert('Update failed: ' + (data.error || 'Unknown error'));
                    location.reload();
                }
            })
            .catch(function() { location.reload(); });

            saveOrder(zone, newStatus);
        });
    });

    function getDragAfterEl(container, y) {
        var cards = [...container.querySelectorAll('.task-card:not(.dragging)')];
        return cards.reduce(function(closest, child) {
            var box   = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function saveOrder(zone, status) {
        var cards = zone.querySelectorAll('.task-card');
        var items = [];
        cards.forEach(function(c, idx) {
            items.push({ id: parseInt(c.dataset.taskId), sort_order: idx });
        });
        fetch('<?php echo '../api/progress/reorder.php'; ?>', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ csrf_token: csrfToken, type: 'task', items: items })
        });
    }
})();
</script>
<?php require_once 'includes/footer.php'; ?>
