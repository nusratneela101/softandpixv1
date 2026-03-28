<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

// Add/update daily log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_log'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $logDate  = $_POST['log_date']     ?? date('Y-m-d');
        $hours    = (float)($_POST['hours_worked'] ?? 0);
        $desc     = trim($_POST['description']     ?? '');
        $userId   = $_SESSION['admin_id'];
        if ($logDate && $hours >= 0) {
            try {
                $pdo->prepare("INSERT INTO project_daily_logs (project_id,user_id,log_date,hours_worked,description)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE hours_worked=VALUES(hours_worked), description=VALUES(description)")
                    ->execute([$id, $userId, $logDate, $hours, $desc ?: null]);
                $pdo->prepare("INSERT INTO project_activity_log (project_id,user_id,action,description,entity_type,entity_id)
                    VALUES (?,?,'daily_log_added',?,'project',?)")
                    ->execute([$id, $userId, "Daily log: {$hours}h on {$logDate}", $id]);
                flashMessage('success', 'Log saved.');
            } catch (Exception $e) { flashMessage('error', 'Save failed: ' . $e->getMessage()); }
        }
    }
    header("Location: project_daily_logs.php?id=$id"); exit;
}

// Delete log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $logId = (int)($_POST['log_id'] ?? 0);
        if ($logId > 0) {
            try {
                $pdo->prepare("DELETE FROM project_daily_logs WHERE id=? AND project_id=?")->execute([$logId, $id]);
                flashMessage('success', 'Log deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
    }
    header("Location: project_daily_logs.php?id=$id"); exit;
}

// Fetch project
try {
    $pStmt = $pdo->prepare("SELECT * FROM projects WHERE id=?");
    $pStmt->execute([$id]);
    $project = $pStmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

// All logs for this project
$logs = [];
try {
    $lStmt = $pdo->prepare("SELECT pdl.*, u.name AS user_name FROM project_daily_logs pdl
        LEFT JOIN users u ON u.id = pdl.user_id
        WHERE pdl.project_id = ?
        ORDER BY pdl.log_date DESC, pdl.id DESC");
    $lStmt->execute([$id]);
    $logs = $lStmt->fetchAll();
} catch (Exception $e) {}

// Chart data — last 30 days
$chartData = [];
try {
    $ch = $pdo->prepare("SELECT log_date, SUM(hours_worked) AS total_hours
        FROM project_daily_logs
        WHERE project_id = ? AND log_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY log_date ORDER BY log_date ASC");
    $ch->execute([$id]);
    $chartData = $ch->fetchAll();
} catch (Exception $e) {}

// Totals
$totalHours = array_sum(array_column($logs, 'hours_worked'));
$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-journal-text me-2"></i>Daily Work Logs</h1>
        <p class="mb-0 text-muted"><?php echo h($project['title']); ?></p>
    </div>
    <div>
        <a href="project_progress.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>
</div>

<div class="container-fluid">
    <div class="row g-4">
        <!-- Chart + summary -->
        <div class="col-lg-8">
            <!-- Summary cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-primary"><?php echo number_format($totalHours, 1); ?></div>
                        <div class="small text-muted">Total Hours</div>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-success"><?php echo count($logs); ?></div>
                        <div class="small text-muted">Log Entries</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm text-center py-3">
                        <div class="fs-3 fw-bold text-info">
                            <?php echo count($logs) > 0 ? number_format($totalHours / count($logs), 1) : '0'; ?>
                        </div>
                        <div class="small text-muted">Avg hrs/entry</div>
                    </div>
                </div>
            </div>

            <!-- Line chart -->
            <?php if (!empty($chartData)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-graph-up me-2"></i>Hours Worked (Last 30 Days)</div>
                <div class="card-body">
                    <canvas id="hoursChart" height="100"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <!-- Logs table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-list-ul me-2"></i>All Log Entries</div>
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                    <div class="text-center py-5 text-muted">No logs yet.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Hours</th>
                                    <th>Notes</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo h(date('M j, Y', strtotime($log['log_date']))); ?></td>
                                    <td><?php echo h($log['user_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="badge bg-primary"><?php echo number_format($log['hours_worked'], 1); ?>h</span></td>
                                    <td class="text-muted small"><?php echo $log['description'] ? h(mb_strimwidth($log['description'], 0, 60, '…')) : '—'; ?></td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this log?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                            <input type="hidden" name="delete_log" value="1">
                                            <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Add log form -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-journal-plus me-2"></i>Add Log Entry</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="save_log" value="1">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="log_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hours Worked</label>
                            <input type="number" name="hours_worked" class="form-control" min="0" max="24" step="0.5" required placeholder="e.g. 4.5">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes / Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="What did you work on?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Log</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($chartData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    var labels = [<?php echo implode(',', array_map(fn($r)=>"'".h(date('M j', strtotime($r['log_date'])))."'", $chartData)); ?>];
    var data   = [<?php echo implode(',', array_map(fn($r)=> (float)$r['total_hours'], $chartData)); ?>];
    new Chart(document.getElementById('hoursChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Hours',
                data: data,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Hours' } }
            }
        }
    });
})();
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
