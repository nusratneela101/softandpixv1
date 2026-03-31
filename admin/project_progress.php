<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

// Manual progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $pct = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $auto = isset($_POST['progress_auto_calculate']) ? 1 : 0;
        try {
            $pdo->prepare("UPDATE projects SET progress_percent=?, progress_auto_calculate=? WHERE id=?")
                ->execute([$pct, $auto, $id]);
            // Log
            $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id)
                VALUES (?,?,'progress_updated',?,  'project',?)")
                ->execute([$id, $_SESSION['admin_id'], "Progress manually set to {$pct}%", $id]);
            flashMessage('success', 'Progress updated.');
        } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
    }
    header("Location: project_progress.php?id=$id"); exit;
}

// Fetch project
try {
    $stmt = $pdo->prepare("SELECT p.*, u.name AS client_name, d.name AS developer_name
        FROM projects p
        LEFT JOIN users u ON u.id = p.client_id
        LEFT JOIN users d ON d.id = p.developer_id
        WHERE p.id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }

if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

// Task counts
$taskCounts = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'completed' => 0, 'total' => 0];
try {
    $ts = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM project_tasks WHERE project_id=? GROUP BY status");
    $ts->execute([$id]);
    foreach ($ts->fetchAll() as $row) {
        if (isset($taskCounts[$row['status']])) $taskCounts[$row['status']] = (int)$row['cnt'];
        $taskCounts['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

// Milestones
$milestones = [];
try {
    $ms = $pdo->prepare("SELECT m.*, COUNT(t.id) AS task_count,
        SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) AS tasks_done
        FROM project_milestones m
        LEFT JOIN project_tasks t ON t.milestone_id = m.id
        WHERE m.project_id = ?
        GROUP BY m.id
        ORDER BY m.sort_order ASC, m.due_date ASC");
    $ms->execute([$id]);
    $milestones = $ms->fetchAll();
} catch (Exception $e) {}

// Activity log
$activityLog = [];
try {
    $al = $pdo->prepare("SELECT pal.*, u.name AS actor_name FROM project_activity_log pal
        LEFT JOIN users u ON u.id = pal.user_id
        WHERE pal.project_id = ?
        ORDER BY pal.created_at DESC LIMIT 50");
    $al->execute([$id]);
    $activityLog = $al->fetchAll();
} catch (Exception $e) {}

$csrf_token  = generateCsrfToken();
$statusColor = ['pending'=>'warning','in_progress'=>'primary','on_hold'=>'secondary','completed'=>'success','cancelled'=>'danger'][$project['status']] ?? 'secondary';
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-bar-chart-line me-2"></i>Progress Dashboard</h1>
        <p class="mb-0 text-muted"><?php echo h($project['title']); ?></p>
    </div>
    <div>
        <a href="project_tasks.php?id=<?php echo $id; ?>" class="btn btn-outline-primary me-2"><i class="bi bi-kanban me-1"></i>Tasks</a>
        <a href="project_milestones.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2"><i class="bi bi-flag me-1"></i>Milestones</a>
        <a href="project_daily_logs.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary me-2"><i class="bi bi-journal-text me-1"></i>Daily Logs</a>
        <a href="project_view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="container-fluid">
    <div class="row g-4">

        <!-- Left column: charts -->
        <div class="col-lg-8">

            <!-- Stat cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-2 fw-bold text-secondary"><?php echo $taskCounts['todo']; ?></div>
                        <div class="small text-muted">Todo</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-2 fw-bold text-primary"><?php echo $taskCounts['in_progress']; ?></div>
                        <div class="small text-muted">In Progress</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-2 fw-bold text-warning"><?php echo $taskCounts['review']; ?></div>
                        <div class="small text-muted">Review</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-2 fw-bold text-success"><?php echo $taskCounts['completed']; ?></div>
                        <div class="small text-muted">Completed</div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="row g-4 mb-4">
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-semibold"><i class="bi bi-pie-chart me-2"></i>Overall Progress</div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center">
                            <div style="position:relative;max-width:200px;width:100%;">
                                <canvas id="doughnutChart"></canvas>
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                    <div class="fs-3 fw-bold text-primary"><?php echo (int)$project['progress_percent']; ?>%</div>
                                    <div class="small text-muted">Complete</div>
                                </div>
                            </div>
                            <div class="mt-3 d-flex gap-3 small">
                                <span><span class="badge bg-primary">&nbsp;</span> Done</span>
                                <span><span class="badge bg-light border">&nbsp;</span> Remaining</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-semibold"><i class="bi bi-bar-chart me-2"></i>Tasks by Status</div>
                        <div class="card-body">
                            <canvas id="barChart" height="180"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Milestones horizontal bar -->
            <?php if (!empty($milestones)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-flag me-2"></i>Milestone Progress</div>
                <div class="card-body">
                    <canvas id="milestoneChart" height="<?php echo max(80, count($milestones) * 40); ?>"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Milestones timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-calendar3 me-2"></i>Milestones Timeline</span>
                    <a href="project_milestones.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">Manage</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($milestones)): ?>
                    <div class="text-center py-4 text-muted small">No milestones yet. <a href="project_milestones.php?id=<?php echo $id; ?>">Add one</a></div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($milestones as $ms):
                            $msColor = ['pending'=>'secondary','in_progress'=>'primary','completed'=>'success'][$ms['status']] ?? 'secondary';
                            $msIcon  = ['pending'=>'bi-circle','in_progress'=>'bi-clock','completed'=>'bi-check-circle-fill'][$ms['status']] ?? 'bi-circle';
                            $taskPct = $ms['task_count'] > 0 ? round($ms['tasks_done'] / $ms['task_count'] * 100) : 0;
                        ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $msIcon; ?> text-<?php echo $msColor; ?> fs-5 me-3"></i>
                                    <div>
                                        <div class="fw-semibold <?php echo $ms['status']==='completed'?'text-decoration-line-through text-muted':''; ?>"><?php echo h($ms['title']); ?></div>
                                        <?php if ($ms['due_date']): ?>
                                        <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo h(date('M j, Y', strtotime($ms['due_date']))); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end" style="min-width:100px;">
                                    <span class="badge bg-<?php echo $msColor; ?> mb-1"><?php echo h(ucwords(str_replace('_',' ',$ms['status']))); ?></span>
                                    <?php if ($ms['task_count'] > 0): ?>
                                    <div class="progress mt-1" style="height:4px;min-width:80px;">
                                        <div class="progress-bar bg-<?php echo $msColor; ?>" style="width:<?php echo $taskPct; ?>%"></div>
                                    </div>
                                    <div class="small text-muted mt-1"><?php echo $ms['tasks_done']; ?>/<?php echo $ms['task_count']; ?> tasks</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="col-lg-4">
            <!-- Doughnut widget / manual update -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-sliders me-2"></i>Update Progress</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="update_progress" value="1">
                        <label class="form-label">Progress %</label>
                        <input type="range" class="form-range mb-2" name="progress_percent" min="0" max="100"
                            value="<?php echo (int)$project['progress_percent']; ?>"
                            oninput="document.getElementById('pctDisplay').textContent=this.value+'%'">
                        <div class="text-center fw-bold fs-5 mb-3" id="pctDisplay"><?php echo (int)$project['progress_percent']; ?>%</div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="progress_auto_calculate" id="autoCalc"
                                <?php echo $project['progress_auto_calculate'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="autoCalc">Auto-calculate from tasks</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Progress</button>
                    </form>
                </div>
            </div>

            <!-- Activity log -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-activity me-2"></i>Activity Log</div>
                <div class="card-body p-0" style="max-height:520px;overflow-y:auto;">
                    <?php if (empty($activityLog)): ?>
                    <div class="text-center py-4 text-muted small">No activity yet.</div>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($activityLog as $log):
                            $actionIcon = [
                                'task_created'         => 'bi-plus-circle text-success',
                                'task_status_changed'  => 'bi-arrow-left-right text-primary',
                                'task_updated'         => 'bi-pencil text-warning',
                                'milestone_created'    => 'bi-flag-fill text-info',
                                'milestone_completed'  => 'bi-check2-circle text-success',
                                'progress_updated'     => 'bi-bar-chart-line text-primary',
                                'daily_log_added'      => 'bi-journal-plus text-secondary',
                            ][$log['action']] ?? 'bi-dot text-muted';
                        ?>
                        <li class="d-flex gap-2 px-3 py-2 border-bottom">
                            <i class="bi <?php echo $actionIcon; ?> fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="small"><?php echo h($log['description'] ?? $log['action']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;">
                                    <?php echo h($log['actor_name'] ?? 'System'); ?> &middot;
                                    <?php echo h(date('M j, H:i', strtotime($log['created_at']))); ?>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    // Doughnut
    var pct = <?php echo (int)$project['progress_percent']; ?>;
    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [pct, 100 - pct],
                backgroundColor: ['#0d6efd', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: { cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });

    // Bar chart — tasks by status
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Todo', 'In Progress', 'Review', 'Completed'],
            datasets: [{
                label: 'Tasks',
                data: [
                    <?php echo $taskCounts['todo']; ?>,
                    <?php echo $taskCounts['in_progress']; ?>,
                    <?php echo $taskCounts['review']; ?>,
                    <?php echo $taskCounts['completed']; ?>
                ],
                backgroundColor: ['#6c757d', '#0d6efd', '#ffc107', '#198754'],
                borderRadius: 6
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    <?php if (!empty($milestones)): ?>
    // Horizontal bar — milestone progress
    new Chart(document.getElementById('milestoneChart'), {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(fn($m)=>"'".addslashes(mb_strimwidth($m['title'],0,30,'…'))."'", $milestones)); ?>],
            datasets: [{
                label: 'Tasks done (%)',
                data: [<?php echo implode(',', array_map(fn($m)=> $m['task_count']>0 ? round($m['tasks_done']/$m['task_count']*100) : ($m['status']==='completed'?100:0), $milestones)); ?>],
                backgroundColor: [<?php echo implode(',', array_map(fn($m)=> "'".((['pending'=>'#6c757d','in_progress'=>'#0d6efd','completed'=>'#198754'][$m['status']]??'#6c757d'))."'", $milestones)); ?>],
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { max: 100, ticks: { callback: v => v + '%' } } }
        }
    });
    <?php endif; ?>
})();
</script>
<?php require_once 'includes/footer.php'; ?>
