<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

// Delete milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_milestone'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msId = (int)($_POST['milestone_id'] ?? 0);
        if ($msId > 0) {
            try {
                $pdo->prepare("DELETE FROM project_milestones WHERE id=? AND project_id=?")->execute([$msId, $id]);
                flashMessage('success', 'Milestone deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
    }
    header("Location: project_milestones.php?id=$id"); exit;
}

// Add milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_milestone'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $status  = in_array($_POST['status']??'', ['pending','in_progress','completed']) ? $_POST['status'] : 'pending';
        if ($title) {
            try {
                // Get max sort order
                $maxOrd = (int)$pdo->prepare("SELECT COALESCE(MAX(sort_order),0) FROM project_milestones WHERE project_id=?")->execute([$id]) && true
                    ? $pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM project_milestones WHERE project_id=$id")->fetchColumn()
                    : 0;
                $pdo->prepare("INSERT INTO project_milestones (project_id,title,description,due_date,status,sort_order) VALUES (?,?,?,?,?,?)")
                    ->execute([$id, $title, $desc ?: null, $dueDate, $status, $maxOrd + 1]);
                // Activity log
                $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id) VALUES (?,?,'milestone_created',?,  'milestone',LAST_INSERT_ID())")
                    ->execute([$id, $_SESSION['admin_id'], "Milestone \"{$title}\" created"]);
                flashMessage('success', 'Milestone added.');
            } catch (Exception $e) { flashMessage('error', 'Failed to add milestone.'); }
        }
    }
    header("Location: project_milestones.php?id=$id"); exit;
}

// Edit milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_milestone'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msId    = (int)($_POST['milestone_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $status  = in_array($_POST['status']??'', ['pending','in_progress','completed']) ? $_POST['status'] : 'pending';
        if ($msId && $title) {
            try {
                $completedAt = ($status === 'completed') ? "NOW()" : "NULL";
                $pdo->prepare("UPDATE project_milestones SET title=?,description=?,due_date=?,status=?,
                    completed_at = CASE WHEN ? = 'completed' AND completed_at IS NULL THEN NOW() WHEN ? != 'completed' THEN NULL ELSE completed_at END
                    WHERE id=? AND project_id=?")
                    ->execute([$title, $desc ?: null, $dueDate, $status, $status, $status, $msId, $id]);
                if ($status === 'completed') {
                    $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id) VALUES (?,?,'milestone_completed',?,'milestone',?)")
                        ->execute([$id, $_SESSION['admin_id'], "Milestone \"{$title}\" completed", $msId]);
                }
                flashMessage('success', 'Milestone updated.');
            } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
        }
    }
    header("Location: project_milestones.php?id=$id"); exit;
}

// Fetch project
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id=?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

// Fetch milestones with task counts
$milestones = [];
try {
    $ms = $pdo->prepare("SELECT m.*, COUNT(t.id) AS task_count,
        SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) AS tasks_done
        FROM project_milestones m
        LEFT JOIN project_tasks t ON t.milestone_id = m.id
        WHERE m.project_id = ?
        GROUP BY m.id
        ORDER BY m.sort_order ASC, m.id ASC");
    $ms->execute([$id]);
    $milestones = $ms->fetchAll();
} catch (Exception $e) {}

$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-flag me-2"></i>Milestones</h1>
        <p class="mb-0 text-muted"><?php echo h($project['title']); ?></p>
    </div>
    <div>
        <a href="project_progress.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2"><i class="bi bi-bar-chart-line me-1"></i>Dashboard</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
            <i class="bi bi-plus-circle me-1"></i>New Milestone
        </button>
    </div>
</div>

<div class="container-fluid">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (empty($milestones)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-flag fs-1 d-block mb-3"></i>
                No milestones yet. <button class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">Add your first milestone</button>
            </div>
            <?php else: ?>
            <ul id="milestoneList" class="list-group list-group-flush">
                <?php foreach ($milestones as $ms):
                    $msColor = ['pending'=>'secondary','in_progress'=>'primary','completed'=>'success'][$ms['status']] ?? 'secondary';
                    $taskPct = $ms['task_count'] > 0 ? round($ms['tasks_done'] / $ms['task_count'] * 100) : 0;
                ?>
                <li class="list-group-item milestone-item" data-id="<?php echo $ms['id']; ?>" style="cursor:grab;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-grip-vertical text-muted drag-handle" style="cursor:grab;"></i>
                            <div>
                                <div class="fw-semibold <?php echo $ms['status']==='completed'?'text-decoration-line-through text-muted':''; ?>">
                                    <?php echo h($ms['title']); ?>
                                </div>
                                <?php if (!empty($ms['description'])): ?>
                                <div class="small text-muted"><?php echo h(mb_strimwidth($ms['description'], 0, 80, '…')); ?></div>
                                <?php endif; ?>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span class="badge bg-<?php echo $msColor; ?>"><?php echo h(ucwords(str_replace('_',' ',$ms['status']))); ?></span>
                                    <?php if ($ms['due_date']): ?>
                                    <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo h(date('M j, Y', strtotime($ms['due_date']))); ?></span>
                                    <?php endif; ?>
                                    <?php if ($ms['task_count'] > 0): ?>
                                    <span class="small text-muted"><i class="bi bi-list-check me-1"></i><?php echo $ms['tasks_done']; ?>/<?php echo $ms['task_count']; ?> tasks</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($ms['task_count'] > 0): ?>
                                <div class="progress mt-2" style="height:4px;max-width:200px;">
                                    <div class="progress-bar bg-<?php echo $msColor; ?>" style="width:<?php echo $taskPct; ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary"
                                onclick="openEditModal(<?php echo $ms['id']; ?>, '<?php echo addslashes(h($ms['title'])); ?>', '<?php echo addslashes(h($ms['description']??'')); ?>', '<?php echo h($ms['due_date']??''); ?>', '<?php echo h($ms['status']); ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this milestone?')">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                <input type="hidden" name="delete_milestone" value="1">
                                <input type="hidden" name="milestone_id" value="<?php echo $ms['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="add_milestone" value="1">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-flag me-2"></i>New Milestone</h5>
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
                    <div class="col-md-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="edit_milestone" value="1">
            <input type="hidden" name="milestone_id" id="editMsId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Milestone</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="editMsTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="editMsDesc" class="form-control" rows="3"></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" id="editMsDue" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="editMsStatus" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
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
function openEditModal(id, title, desc, due, status) {
    document.getElementById('editMsId').value    = id;
    document.getElementById('editMsTitle').value = title;
    document.getElementById('editMsDesc').value  = desc;
    document.getElementById('editMsDue').value   = due;
    document.getElementById('editMsStatus').value = status;
    new bootstrap.Modal(document.getElementById('editMilestoneModal')).show();
}

// Drag-and-drop reorder
(function() {
    var list = document.getElementById('milestoneList');
    if (!list) return;
    var dragging = null;

    list.querySelectorAll('.milestone-item').forEach(function(item) {
        item.addEventListener('dragstart', function(e) {
            dragging = item;
            item.style.opacity = '0.5';
            e.dataTransfer.effectAllowed = 'move';
        });
        item.addEventListener('dragend', function() {
            item.style.opacity = '';
            dragging = null;
            saveOrder();
        });
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (dragging && dragging !== item) {
                var rect = item.getBoundingClientRect();
                var mid  = rect.top + rect.height / 2;
                if (e.clientY < mid) {
                    list.insertBefore(dragging, item);
                } else {
                    list.insertBefore(dragging, item.nextSibling);
                }
            }
        });
        item.setAttribute('draggable', 'true');
    });

    function saveOrder() {
        var items = list.querySelectorAll('.milestone-item');
        var data  = [];
        items.forEach(function(el, idx) {
            data.push({ id: parseInt(el.dataset.id), sort_order: idx });
        });
        fetch('<?php echo '../api/progress/reorder.php'; ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: '<?php echo h($csrf_token); ?>', type: 'milestone', items: data })
        });
    }
})();
</script>
<?php require_once 'includes/footer.php'; ?>
