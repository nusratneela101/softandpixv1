<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId    = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { header('Location: /developer/'); exit; }

// Load project (developer must be assigned)
try {
    $stmt = $pdo->prepare("SELECT p.*, u.name AS client_name FROM projects p
        LEFT JOIN users u ON u.id=p.client_id
        WHERE p.id=? AND (p.developer_id=? OR ? = 'admin')");
    $stmt->execute([$projectId, $userId, $_SESSION['user_role']]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { header('Location: /developer/'); exit; }

$flash = getFlashMessage();

// Add/update daily log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $logDate = $_POST['log_date'] ?? date('Y-m-d');
        $hours   = (float)($_POST['hours_worked'] ?? 0);
        $desc    = trim($_POST['description'] ?? '');
        if ($logDate && $hours >= 0) {
            try {
                $pdo->prepare("INSERT INTO project_daily_logs (project_id,user_id,log_date,hours_worked,description)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE hours_worked=VALUES(hours_worked), description=VALUES(description)")
                    ->execute([$projectId, $userId, $logDate, $hours, $desc ?: null]);
                $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id)
                    VALUES (?,?,'daily_log_added',?,'project',?)")
                    ->execute([$projectId, $userId, "Daily log: {$hours}h on {$logDate}", $projectId]);
                flashMessage('success', 'Log saved.');
            } catch (Exception $e) { flashMessage('error', 'Save failed.'); }
        }
    }
    header("Location: project_progress.php?id=$projectId"); exit;
}

// Task counts
$taskCounts = ['todo'=>0,'in_progress'=>0,'review'=>0,'completed'=>0,'total'=>0];
try {
    $ts = $pdo->prepare("SELECT status, COUNT(*) AS cnt FROM project_tasks WHERE project_id=? GROUP BY status");
    $ts->execute([$projectId]);
    foreach ($ts->fetchAll() as $row) {
        if (isset($taskCounts[$row['status']])) $taskCounts[$row['status']] = (int)$row['cnt'];
        $taskCounts['total'] += (int)$row['cnt'];
    }
} catch (Exception $e) {}

// My tasks
$myTasks = [];
try {
    $mts = $pdo->prepare("SELECT * FROM project_tasks WHERE project_id=? AND assigned_to=? ORDER BY status ASC, sort_order ASC");
    $mts->execute([$projectId, $userId]);
    $myTasks = $mts->fetchAll();
} catch (Exception $e) {}

// Milestones
$milestones = [];
try {
    $ms = $pdo->prepare("SELECT m.*, COUNT(t.id) AS task_count,
        SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) AS tasks_done
        FROM project_milestones m
        LEFT JOIN project_tasks t ON t.milestone_id=m.id
        WHERE m.project_id=?
        GROUP BY m.id
        ORDER BY m.sort_order ASC, m.due_date ASC");
    $ms->execute([$projectId]);
    $milestones = $ms->fetchAll();
} catch (Exception $e) {}

// My daily logs (last 30 days)
$chartData = [];
try {
    $ch = $pdo->prepare("SELECT log_date, hours_worked FROM project_daily_logs
        WHERE project_id=? AND user_id=? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY log_date ASC");
    $ch->execute([$projectId, $userId]);
    $chartData = $ch->fetchAll();
} catch (Exception $e) {}

// Total my hours
$myHours = 0;
try {
    $mh = $pdo->prepare("SELECT COALESCE(SUM(hours_worked),0) FROM project_daily_logs WHERE project_id=? AND user_id=?");
    $mh->execute([$projectId, $userId]);
    $myHours = (float)$mh->fetchColumn();
} catch (Exception $e) {}

// Activity log
$activityLog = [];
try {
    $al = $pdo->prepare("SELECT pal.*, u.name AS actor_name FROM project_activity_log pal
        LEFT JOIN users u ON u.id=pal.user_id
        WHERE pal.project_id=? ORDER BY pal.created_at DESC LIMIT 30");
    $al->execute([$projectId]);
    $activityLog = $al->fetchAll();
} catch (Exception $e) {}

$pct        = (int)($project['progress_percent'] ?? 0);
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Progress — <?php echo h($project['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:#f8f9fa; }
        .nav-top { background:#0d6efd; }
        .task-status-btn { cursor:pointer; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark nav-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/developer/"><i class="bi bi-code-slash me-2"></i>Dev Panel</a>
        <a class="btn btn-outline-light btn-sm" href="project_view.php?id=<?php echo $projectId; ?>"><i class="bi bi-arrow-left me-1"></i>Project</a>
    </div>
</nav>
<div class="container pb-5">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><?php echo h($project['title']); ?></h2>
            <p class="text-muted mb-0">Progress &amp; My Work</p>
        </div>
        <?php if ($project['deadline']): ?>
        <div class="text-end">
            <div class="small text-muted">Deadline</div>
            <div class="fw-semibold" id="deadlineDisplay">
                <?php echo h(date('M j, Y', strtotime($project['deadline']))); ?>
            </div>
            <div class="small" id="countdown"></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-primary"><?php echo $pct; ?>%</div>
                        <div class="small text-muted">Progress</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-info"><?php echo count($myTasks); ?></div>
                        <div class="small text-muted">My Tasks</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-success"><?php echo number_format($myHours,1); ?></div>
                        <div class="small text-muted">My Hours</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-warning"><?php echo $taskCounts['total']; ?></div>
                        <div class="small text-muted">Total Tasks</div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">
                <div class="col-md-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                            <div style="position:relative;max-width:180px;width:100%;">
                                <canvas id="doughnutChart"></canvas>
                                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                                    <div class="fs-2 fw-bold text-primary"><?php echo $pct; ?>%</div>
                                    <div class="small text-muted">Complete</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-semibold small"><i class="bi bi-graph-up me-2"></i>My Hours (30 days)</div>
                        <div class="card-body">
                            <canvas id="hoursChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My tasks (can change status) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-list-task me-2"></i>My Tasks</div>
                <div class="card-body p-0">
                    <?php if (empty($myTasks)): ?>
                    <div class="text-center py-4 text-muted small">No tasks assigned to you.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($myTasks as $t):
                            $sc = ['todo'=>'secondary','in_progress'=>'primary','review'=>'warning','completed'=>'success'][$t['status']]??'secondary';
                            $pc = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$t['priority']]??'secondary';
                        ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold <?php echo $t['status']==='completed'?'text-decoration-line-through text-muted':''; ?>"><?php echo h($t['title']); ?></div>
                                    <div class="d-flex gap-2 mt-1">
                                        <span class="badge bg-<?php echo $pc; ?> small"><?php echo ucfirst($t['priority']); ?></span>
                                        <?php if ($t['due_date']): ?>
                                        <span class="badge bg-light text-dark small"><i class="bi bi-calendar3"></i> <?php echo h(date('M j', strtotime($t['due_date']))); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-<?php echo $sc; ?>"><?php echo h(ucwords(str_replace('_',' ',$t['status']))); ?></span>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Move</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php foreach (['todo'=>'Todo','in_progress'=>'In Progress','review'=>'Review','completed'=>'Completed'] as $sv=>$sl): ?>
                                            <?php if ($sv !== $t['status']): ?>
                                            <li><a class="dropdown-item task-status-btn" href="#"
                                                data-task-id="<?php echo $t['id']; ?>"
                                                data-new-status="<?php echo $sv; ?>"><?php echo $sl; ?></a></li>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Milestones -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-flag me-2"></i>Milestones</div>
                <div class="card-body p-0">
                    <?php if (empty($milestones)): ?>
                    <div class="text-center py-4 text-muted small">No milestones.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($milestones as $ms):
                            $msColor = ['pending'=>'secondary','in_progress'=>'primary','completed'=>'success'][$ms['status']] ?? 'secondary';
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold <?php echo $ms['status']==='completed'?'text-decoration-line-through text-muted':''; ?>"><?php echo h($ms['title']); ?></div>
                                <?php if ($ms['due_date']): ?>
                                <div class="small text-muted"><?php echo h(date('M j, Y', strtotime($ms['due_date']))); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo $msColor; ?>"><?php echo h(ucwords(str_replace('_',' ',$ms['status']))); ?></span>
                                <?php if ($ms['task_count'] > 0): ?>
                                <div class="progress mt-1" style="height:4px;min-width:80px;">
                                    <div class="progress-bar bg-<?php echo $msColor; ?>" style="width:<?php echo $ms['task_count']>0?round($ms['tasks_done']/$ms['task_count']*100):0; ?>%"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Log hours + activity -->
        <div class="col-lg-4">
            <!-- Log hours form -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-journal-plus me-2"></i>Log Hours</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="save_log" value="1">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hours</label>
                            <input type="number" name="hours_worked" class="form-control" min="0" max="24" step="0.5" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save</button>
                    </form>
                </div>
            </div>

            <!-- Activity -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-activity me-2"></i>Activity</div>
                <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                    <?php if (empty($activityLog)): ?>
                    <div class="text-center py-4 text-muted small">No activity.</div>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($activityLog as $log):
                            $icon = ['task_created'=>'bi-plus-circle text-success','task_status_changed'=>'bi-arrow-left-right text-primary','daily_log_added'=>'bi-journal-plus text-secondary','milestone_completed'=>'bi-check2-circle text-success'][$log['action']] ?? 'bi-dot text-muted';
                        ?>
                        <li class="d-flex gap-2 px-3 py-2 border-bottom">
                            <i class="bi <?php echo $icon; ?> fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="small"><?php echo h($log['description'] ?? $log['action']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo h($log['actor_name'] ?? ''); ?> · <?php echo h(date('M j, H:i', strtotime($log['created_at']))); ?></div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var csrfToken = '<?php echo h($csrf_token); ?>';

// Doughnut
(function() {
    var pct = <?php echo $pct; ?>;
    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: { datasets: [{ data: [pct,100-pct], backgroundColor:['#0d6efd','#e9ecef'], borderWidth:0 }] },
        options: { cutout: '72%', plugins: { legend:{display:false}, tooltip:{enabled:false} } }
    });
})();

// Hours chart
(function() {
    <?php if (!empty($chartData)): ?>
    var labels = [<?php echo implode(',', array_map(fn($r)=>"'".h(date('M j',strtotime($r['log_date'])))."'", $chartData)); ?>];
    var data   = [<?php echo implode(',', array_map(fn($r)=> (float)$r['hours_worked'], $chartData)); ?>];
    new Chart(document.getElementById('hoursChart'), {
        type: 'line',
        data: { labels: labels, datasets: [{ label:'Hours', data: data, borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,.1)', fill:true, tension:.4, pointRadius:3 }] },
        options: { plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true} } }
    });
    <?php endif; ?>
})();

// Deadline countdown
(function() {
    <?php if ($project['deadline']): ?>
    var deadline = new Date('<?php echo $project['deadline']; ?>T23:59:59');
    function updateCountdown() {
        var now  = new Date();
        var diff = deadline - now;
        var el   = document.getElementById('countdown');
        if (!el) return;
        if (diff <= 0) { el.innerHTML = '<span class="text-danger">Overdue!</span>'; return; }
        var days = Math.floor(diff / 86400000);
        var hrs  = Math.floor((diff % 86400000) / 3600000);
        el.innerHTML = '<span class="text-muted">' + days + 'd ' + hrs + 'h remaining</span>';
    }
    updateCountdown();
    setInterval(updateCountdown, 60000);
    <?php endif; ?>
})();

// Task status change
document.querySelectorAll('.task-status-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var taskId    = this.dataset.taskId;
        var newStatus = this.dataset.newStatus;
        fetch('/api/progress/update_task.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrfToken, task_id: parseInt(taskId), new_status: newStatus })
        })
        .then(r => r.json())
        .then(function(d) {
            if (d.success) { location.reload(); }
            else { alert('Failed: ' + (d.error||'Unknown')); }
        });
    });
});
</script>
</body>
</html>
