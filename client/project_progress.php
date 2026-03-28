<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();

$userId    = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);
if (!$projectId) { header('Location: /client/'); exit; }

// Load project (only client's own)
try {
    $stmt = $pdo->prepare("SELECT p.*, u.name AS developer_name FROM projects p
        LEFT JOIN users u ON u.id = p.developer_id
        WHERE p.id=? AND (p.client_id=? OR ? = 'admin')");
    $stmt->execute([$projectId, $userId, $_SESSION['user_role']]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { header('Location: /client/'); exit; }

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

// Tasks (read-only view)
$tasks = [];
try {
    $tStmt = $pdo->prepare("SELECT t.*, u.name AS assignee_name FROM project_tasks t
        LEFT JOIN users u ON u.id=t.assigned_to
        WHERE t.project_id=?
        ORDER BY t.status ASC, t.sort_order ASC, t.id ASC");
    $tStmt->execute([$projectId]);
    $tasks = $tStmt->fetchAll();
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

$pct = (int)($project['progress_percent'] ?? 0);
$flash = getFlashMessage();

// Client header (inline lightweight)
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
        body { background: #f8f9fa; }
        .nav-top { background: #0d6efd; }
        .stat-card { border-left: 4px solid; }
    </style>
</head>
<body>
<nav class="navbar navbar-dark nav-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/client/"><i class="bi bi-grid me-2"></i>Dashboard</a>
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
            <p class="text-muted mb-0">Progress Overview</p>
        </div>
        <?php if ($project['deadline']): ?>
        <div class="text-end">
            <div class="small text-muted">Deadline</div>
            <div class="fw-semibold <?php echo strtotime($project['deadline']) < time() && $project['status']!=='completed' ? 'text-danger' : ''; ?>">
                <?php echo h(date('M j, Y', strtotime($project['deadline']))); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Charts + milestones -->
        <div class="col-lg-8">
            <!-- Stat cards -->
            <div class="row g-3 mb-4">
                <?php
                $statCards = [
                    ['label'=>'Todo',        'val'=>$taskCounts['todo'],        'color'=>'#6c757d','icon'=>'bi-circle'],
                    ['label'=>'In Progress', 'val'=>$taskCounts['in_progress'], 'color'=>'#0d6efd','icon'=>'bi-clock'],
                    ['label'=>'Review',      'val'=>$taskCounts['review'],      'color'=>'#ffc107','icon'=>'bi-eye'],
                    ['label'=>'Completed',   'val'=>$taskCounts['completed'],   'color'=>'#198754','icon'=>'bi-check-circle-fill'],
                ];
                foreach ($statCards as $sc):
                ?>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm stat-card" style="border-left-color:<?php echo $sc['color']; ?>!important;">
                        <div class="card-body text-center py-3">
                            <i class="bi <?php echo $sc['icon']; ?> fs-3 mb-1" style="color:<?php echo $sc['color']; ?>;"></i>
                            <div class="fs-3 fw-bold"><?php echo $sc['val']; ?></div>
                            <div class="small text-muted"><?php echo $sc['label']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Charts row -->
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
                            <div class="mt-3 fw-semibold text-muted small">Overall Progress</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light fw-semibold small"><i class="bi bi-bar-chart me-2"></i>Tasks by Status</div>
                        <div class="card-body">
                            <canvas id="barChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Milestones timeline -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-flag me-2"></i>Milestones</div>
                <div class="card-body p-0">
                    <?php if (empty($milestones)): ?>
                    <div class="text-center py-4 text-muted small">No milestones set.</div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($milestones as $ms):
                            $msColor = ['pending'=>'secondary','in_progress'=>'primary','completed'=>'success'][$ms['status']] ?? 'secondary';
                            $msIcon  = ['pending'=>'bi-circle','in_progress'=>'bi-clock-fill','completed'=>'bi-check-circle-fill'][$ms['status']] ?? 'bi-circle';
                            $msPct   = $ms['task_count'] > 0 ? round($ms['tasks_done']/$ms['task_count']*100) : ($ms['status']==='completed'?100:0);
                        ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $msIcon; ?> text-<?php echo $msColor; ?> fs-5 me-3"></i>
                                    <div>
                                        <div class="fw-semibold <?php echo $ms['status']==='completed'?'text-decoration-line-through text-muted':''; ?>">
                                            <?php echo h($ms['title']); ?>
                                        </div>
                                        <?php if ($ms['due_date']): ?>
                                        <div class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?php echo h(date('M j, Y', strtotime($ms['due_date']))); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $msColor; ?>"><?php echo h(ucwords(str_replace('_',' ',$ms['status']))); ?></span>
                                    <?php if ($ms['task_count'] > 0): ?>
                                    <div class="progress mt-1" style="height:4px;min-width:80px;">
                                        <div class="progress-bar bg-<?php echo $msColor; ?>" style="width:<?php echo $msPct; ?>%"></div>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size:.7rem;"><?php echo $ms['tasks_done']; ?>/<?php echo $ms['task_count']; ?> tasks</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Task list (read-only) -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-list-check me-2"></i>Tasks</div>
                <div class="card-body p-0">
                    <?php if (empty($tasks)): ?>
                    <div class="text-center py-4 text-muted small">No tasks yet.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Task</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $t):
                                    $sc = ['todo'=>'secondary','in_progress'=>'primary','review'=>'warning','completed'=>'success'][$t['status']]??'secondary';
                                    $pc = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$t['priority']]??'secondary';
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo h($t['title']); ?></td>
                                    <td><span class="badge bg-<?php echo $sc; ?>"><?php echo h(ucwords(str_replace('_',' ',$t['status']))); ?></span></td>
                                    <td><span class="badge bg-<?php echo $pc; ?>"><?php echo h(ucfirst($t['priority'])); ?></span></td>
                                    <td class="text-muted"><?php echo $t['due_date'] ? h(date('M j, Y', strtotime($t['due_date']))) : '—'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Activity log -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-activity me-2"></i>Recent Activity</div>
                <div class="card-body p-0" style="max-height:600px;overflow-y:auto;">
                    <?php if (empty($activityLog)): ?>
                    <div class="text-center py-4 text-muted small">No activity yet.</div>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($activityLog as $log):
                            $icon = ['task_created'=>'bi-plus-circle text-success','task_status_changed'=>'bi-arrow-left-right text-primary','milestone_completed'=>'bi-check2-circle text-success','progress_updated'=>'bi-bar-chart-line text-primary'][$log['action']] ?? 'bi-dot text-muted';
                        ?>
                        <li class="d-flex gap-2 px-3 py-2 border-bottom">
                            <i class="bi <?php echo $icon; ?> fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="small"><?php echo h($log['description'] ?? $log['action']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo h(date('M j, H:i', strtotime($log['created_at']))); ?></div>
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
(function() {
    var pct = <?php echo $pct; ?>;
    new Chart(document.getElementById('doughnutChart'), {
        type: 'doughnut',
        data: {
            datasets: [{ data: [pct, 100-pct], backgroundColor: ['#0d6efd','#e9ecef'], borderWidth: 0 }]
        },
        options: { cutout: '72%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
    });
    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: ['Todo','In Progress','Review','Completed'],
            datasets: [{ label: 'Tasks', data: [<?php echo $taskCounts['todo'].','.$taskCounts['in_progress'].','.$taskCounts['review'].','.$taskCounts['completed']; ?>], backgroundColor: ['#6c757d','#0d6efd','#ffc107','#198754'], borderRadius: 6 }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
})();
</script>
</body>
</html>
