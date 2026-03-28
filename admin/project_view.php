<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

// Post a project update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_update'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = trim($_POST['update_content'] ?? '');
        if (!empty($message)) {
            try {
                $pdo->prepare("INSERT INTO project_updates (project_id, user_id, message) VALUES (?, ?, ?)")
                    ->execute([$id, $_SESSION['admin_id'], $message]);
                flashMessage('success', 'Update posted.');
            } catch (Exception $e) { flashMessage('error', 'Failed to post update.'); }
        }
    }
    header("Location: project_view.php?id=$id"); exit;
}

// Add milestone
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_milestone'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msTitle   = trim($_POST['milestone_title'] ?? '');
        $msDueDate = !empty($_POST['milestone_due_date']) ? $_POST['milestone_due_date'] : null;
        if (!empty($msTitle)) {
            try {
                $pdo->prepare("INSERT INTO project_milestones (project_id, title, due_date) VALUES (?, ?, ?)")
                    ->execute([$id, $msTitle, $msDueDate]);
                flashMessage('success', 'Milestone added.');
            } catch (Exception $e) { flashMessage('error', 'Failed to add milestone.'); }
        }
    }
    header("Location: project_view.php?id=$id"); exit;
}

// Toggle milestone completion (cycles between pending and completed via status column)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_milestone'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $msId       = (int)($_POST['milestone_id'] ?? 0);
        $msCurrent  = $_POST['ms_status'] ?? 'pending';
        if ($msId > 0) {
            try {
                $newStatus = ($msCurrent === 'completed') ? 'pending' : 'completed';
                $pdo->prepare("UPDATE project_milestones SET status=? WHERE id=? AND project_id=?")
                    ->execute([$newStatus, $msId, $id]);
            } catch (Exception $e) {}
        }
    }
    header("Location: project_view.php?id=$id"); exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, d.name AS developer_name, d.email AS developer_email, cr.role_color AS client_role_color, cr2.role_color AS dev_role_color FROM projects p LEFT JOIN users c ON c.id = p.client_id LEFT JOIN users d ON d.id = p.developer_id LEFT JOIN custom_roles cr ON cr.role_name = c.role LEFT JOIN custom_roles cr2 ON cr2.role_name = d.role WHERE p.id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    $milestones = [];
    try {
        $ms = $pdo->prepare("SELECT * FROM project_milestones WHERE project_id = ? ORDER BY due_date ASC, id ASC");
        $ms->execute([$id]);
        $milestones = $ms->fetchAll();
    } catch (Exception $e) {}

    $updates = [];
    try {
        $upd = $pdo->prepare("SELECT pu.*, u.name AS poster_name FROM project_updates pu LEFT JOIN users u ON u.id = pu.user_id WHERE pu.project_id = ? ORDER BY pu.created_at DESC LIMIT 30");
        $upd->execute([$id]);
        $updates = $upd->fetchAll();
    } catch (Exception $e) {}

    $files = [];
    try {
        $fl = $pdo->prepare("SELECT pf.*, u.name AS uploader_name FROM project_files pf LEFT JOIN users u ON u.id = pf.user_id WHERE pf.project_id = ? ORDER BY pf.created_at DESC");
        $fl->execute([$id]);
        $files = $fl->fetchAll();
    } catch (Exception $e) {}

} catch (Exception $e) { $project = null; }

if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

$statusColor   = ['pending' => 'warning', 'in_progress' => 'primary', 'on_hold' => 'secondary', 'completed' => 'success', 'cancelled' => 'danger'][$project['status']] ?? 'secondary';
$priorityColor = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'urgent' => 'danger'][$project['priority']] ?? 'secondary';
$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-kanban me-2"></i><?php echo h($project['title']); ?></h1>
        <p class="mb-0">
            <span class="badge bg-<?php echo $statusColor; ?> me-1"><?php echo h(ucwords(str_replace('_', ' ', $project['status']))); ?></span>
            <span class="badge bg-<?php echo $priorityColor; ?>"><?php echo h(ucfirst($project['priority'] ?? '')); ?></span>
        </p>
    </div>
    <div>
        <a href="project_progress.php?id=<?php echo $id; ?>" class="btn btn-outline-success me-2"><i class="bi bi-bar-chart-line me-1"></i>Progress</a>
        <?php if (!empty($project['demo_enabled']) && !empty($project['demo_subdomain'])): ?>
        <a href="https://<?php echo h($project['demo_subdomain']); ?>.softandpix.com" target="_blank" rel="noopener" class="btn btn-outline-info me-2"><i class="bi bi-play-circle me-1"></i>View Demo</a>
        <?php endif; ?>
        <a href="project_demo.php?id=<?php echo $id; ?>" class="btn btn-outline-primary me-2"><i class="bi bi-broadcast me-1"></i>Demo</a>
        <a href="project_edit.php?id=<?php echo $id; ?>" class="btn btn-primary me-2"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="projects.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
    <!-- Info Row -->
    <div class="row g-4 mb-4">
        <!-- Project Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-muted text-uppercase mb-3">Project Details</h6>
                    <?php if (!empty($project['description'])): ?>
                    <p class="mb-3"><?php echo nl2br(h($project['description'])); ?></p>
                    <?php endif; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold">Progress</span>
                            <span><?php echo (int)$project['progress']; ?>%</span>
                        </div>
                        <div class="progress" style="height:10px;">
                            <div class="progress-bar bg-<?php echo $statusColor; ?>" style="width:<?php echo (int)$project['progress']; ?>%"></div>
                        </div>
                    </div>
                    <div class="row g-2 text-sm">
                        <div class="col-md-4">
                            <div class="p-2 bg-light rounded">
                                <div class="text-muted small">Start Date</div>
                                <div class="fw-semibold"><?php echo $project['start_date'] ? h(date('M j, Y', strtotime($project['start_date']))) : '—'; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 bg-light rounded">
                                <div class="text-muted small">Deadline</div>
                                <div class="fw-semibold <?php echo (!empty($project['deadline']) && $project['status'] !== 'completed' && strtotime($project['deadline']) < time()) ? 'text-danger' : ''; ?>">
                                    <?php echo $project['deadline'] ? h(date('M j, Y', strtotime($project['deadline']))) : '—'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 bg-light rounded">
                                <div class="text-muted small">Budget</div>
                                <div class="fw-semibold"><?php echo !empty($project['budget']) ? h($project['currency'] ?? 'USD') . ' ' . number_format((float)$project['budget'], 2) : '—'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Milestones -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-flag me-2"></i>Milestones</span>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addMilestoneForm">
                        <i class="bi bi-plus me-1"></i>Add
                    </button>
                </div>
                <div class="collapse" id="addMilestoneForm">
                    <div class="card-body border-bottom">
                        <form method="POST" class="row g-2">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <input type="hidden" name="add_milestone" value="1">
                            <div class="col-md-6">
                                <input type="text" name="milestone_title" class="form-control form-control-sm" placeholder="Milestone title" required>
                            </div>
                            <div class="col-md-4">
                                <input type="date" name="milestone_due_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($milestones)): ?>
                    <div class="text-center py-4 text-muted small">No milestones yet.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($milestones as $ms):
                            $isCompleted = ($ms['status'] === 'completed');
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center <?php echo $isCompleted ? 'bg-light' : ''; ?>">
                            <div>
                                <i class="bi <?php echo $isCompleted ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'; ?> me-2"></i>
                                <span class="<?php echo $isCompleted ? 'text-decoration-line-through text-muted' : ''; ?>"><?php echo h($ms['title']); ?></span>
                                <?php if ($ms['due_date']): ?>
                                <span class="text-muted small ms-2"><i class="bi bi-calendar3 me-1"></i><?php echo h(date('M j, Y', strtotime($ms['due_date']))); ?></span>
                                <?php endif; ?>
                            </div>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                <input type="hidden" name="toggle_milestone" value="1">
                                <input type="hidden" name="milestone_id" value="<?php echo (int)$ms['id']; ?>">
                                <input type="hidden" name="ms_status" value="<?php echo h($ms['status']); ?>">
                                <button type="submit" class="btn btn-sm <?php echo $isCompleted ? 'btn-outline-secondary' : 'btn-outline-success'; ?>" title="<?php echo $isCompleted ? 'Mark Incomplete' : 'Mark Complete'; ?>">
                                    <i class="bi <?php echo $isCompleted ? 'bi-arrow-counterclockwise' : 'bi-check2'; ?>"></i>
                                </button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Post Update -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-pencil-square me-2"></i>Post Update</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="post_update" value="1">
                        <div class="row g-2">
                            <div class="col-md-10">
                                <textarea name="update_content" class="form-control" rows="3" placeholder="Write an update, note or status change..." required></textarea>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100 h-100"><i class="bi bi-send me-1"></i>Post</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Timeline / Updates -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-clock-history me-2"></i>Activity Timeline</div>
                <div class="card-body">
                    <?php if (empty($updates)): ?>
                    <div class="text-center text-muted py-3">No updates yet.</div>
                    <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($updates as $upd): ?>
                        <div class="d-flex mb-3">
                            <div class="me-3 text-muted pt-1"><i class="bi bi-chat-text fs-5"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold small"><?php echo h($upd['poster_name'] ?? 'Admin'); ?></span>
                                    <span class="text-muted small"><?php echo h(date('M j, Y H:i', strtotime($upd['created_at']))); ?></span>
                                </div>
                                <div class="mt-1"><?php echo nl2br(h($upd['message'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Live Demo Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-broadcast me-2 text-info"></i>Live Demo</div>
                <div class="card-body">
                    <?php if (!empty($project['demo_subdomain'])): ?>
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small text-muted">Demo URL</span>
                            <span class="badge bg-<?php echo !empty($project['demo_enabled']) ? 'success' : 'secondary'; ?>">
                                <?php echo !empty($project['demo_enabled']) ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>
                        <a href="https://<?php echo h($project['demo_subdomain']); ?>.softandpix.com" target="_blank" rel="noopener"
                           class="fw-semibold text-decoration-none d-block text-truncate">
                            <?php echo h($project['demo_subdomain']); ?>.softandpix.com
                        </a>
                    </div>
                    <?php if (!empty($project['demo_password'])): ?>
                    <div class="mb-2"><span class="badge bg-warning text-dark"><i class="bi bi-lock-fill me-1"></i>Password Protected</span></div>
                    <?php endif; ?>
                    <?php if (!empty($project['demo_expires_at'])): ?>
                    <div class="mb-2 small text-muted">
                        <i class="bi bi-calendar-x me-1"></i>Expires: <?php echo h(date('M j, Y H:i', strtotime($project['demo_expires_at']))); ?>
                        <?php if (strtotime($project['demo_expires_at']) < time()): ?>
                        <span class="text-danger fw-semibold">(Expired)</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-3">
                        <?php if (!empty($project['demo_enabled'])): ?>
                        <a href="https://<?php echo h($project['demo_subdomain']); ?>.softandpix.com" target="_blank" rel="noopener"
                           class="btn btn-sm btn-info text-white flex-grow-1">
                            <i class="bi bi-box-arrow-up-right me-1"></i>View Demo
                        </a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyDemoLink()" title="Copy Demo Link">
                            <i class="bi bi-clipboard me-1"></i>Copy Link
                        </button>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-2">No demo subdomain configured for this project.</p>
                    <a href="project_demo.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle me-1"></i>Set Up Demo
                    </a>
                    <?php endif; ?>
                    <div class="mt-2">
                        <a href="project_demo.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-gear me-1"></i>Manage Demo Settings
                        </a>
                    </div>
                </div>
            </div>

            <!-- Client Info -->
            <?php if (!empty($project['client_name'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-person me-2"></i>Client</div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 flex-shrink-0"
                             style="width:42px;height:42px;background:<?php echo h($project['client_role_color'] ?? '#6c757d'); ?>;font-size:1.1rem;">
                            <?php echo h(strtoupper(substr($project['client_name'], 0, 1))); ?>
                        </div>
                        <div>
                            <a href="user_view.php?id=<?php echo (int)$project['client_id']; ?>" class="fw-semibold text-decoration-none"><?php echo h($project['client_name']); ?></a>
                            <div class="small text-muted"><?php echo h($project['client_email'] ?? ''); ?></div>
                            <?php if (!empty($project['client_phone'])): ?>
                            <div class="small text-muted"><?php echo h($project['client_phone']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Developer Info -->
            <?php if (!empty($project['developer_name'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-code-slash me-2"></i>Developer</div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold me-3 flex-shrink-0"
                             style="width:42px;height:42px;background:<?php echo h($project['dev_role_color'] ?? '#6c757d'); ?>;font-size:1.1rem;">
                            <?php echo h(strtoupper(substr($project['developer_name'], 0, 1))); ?>
                        </div>
                        <div>
                            <a href="user_view.php?id=<?php echo (int)$project['developer_id']; ?>" class="fw-semibold text-decoration-none"><?php echo h($project['developer_name']); ?></a>
                            <div class="small text-muted"><?php echo h($project['developer_email'] ?? ''); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Files -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-paperclip me-2"></i>Files
                    <span class="badge bg-secondary ms-1"><?php echo count($files); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($files)): ?>
                    <div class="text-center py-3 text-muted small">No files uploaded.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($files as $f): ?>
                        <li class="list-group-item py-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark me-2 text-primary"></i>
                                <div class="flex-grow-1 overflow-hidden">
                                    <span class="text-truncate d-block small fw-semibold"><?php echo h($f['original_name'] ?? $f['filename'] ?? 'File'); ?></span>
                                    <span class="text-muted small"><?php echo h($f['uploader_name'] ?? 'Unknown'); ?> &middot; <?php echo h(date('M j', strtotime($f['created_at']))); ?></span>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
<script>
function copyDemoLink() {
    var url = 'https://<?php echo h($project['demo_subdomain'] ?? ''); ?>.softandpix.com';
    if (!navigator.clipboard) {
        var ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
    } else {
        navigator.clipboard.writeText(url);
    }
    var btn = event.currentTarget;
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied!';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-secondary');
    setTimeout(function() { btn.innerHTML = orig; btn.classList.remove('btn-success'); btn.classList.add('btn-outline-secondary'); }, 2000);
}
</script>
