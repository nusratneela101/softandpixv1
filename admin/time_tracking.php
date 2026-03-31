<?php
/**
 * Admin — Time Tracking: view all time entries, approve/reject
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/includes/language.php';
require_once dirname(__DIR__) . '/includes/activity_logger.php';
requireAuth();

$page_title = __('time_tracking');
$admin_id   = $_SESSION['admin_id'];

// Handle approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $id = (int)$_POST['approve_id'];
    try {
        $pdo->prepare("UPDATE time_entries SET is_approved=1, approved_by=? WHERE id=?")->execute([$admin_id, $id]);
        log_activity($pdo, $admin_id, 'time_entry_approved', "Approved time entry #$id", 'time_entry', $id);
    } catch (Exception $e) {}
    header('Location: time_tracking.php');
    exit;
}
// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    try {
        $pdo->prepare("DELETE FROM time_entries WHERE id=?")->execute([$id]);
    } catch (Exception $e) {}
    header('Location: time_tracking.php');
    exit;
}

// Filters
$filter_project = (int)($_GET['project'] ?? 0);
$filter_dev     = (int)($_GET['developer'] ?? 0);
$filter_from    = $_GET['from'] ?? '';
$filter_to      = $_GET['to']   ?? '';

$params = [];
$where  = ['1=1'];

if ($filter_project) { $where[] = 'te.project_id = ?'; $params[] = $filter_project; }
if ($filter_dev)     { $where[] = 'te.user_id = ?';    $params[] = $filter_dev; }
if ($filter_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_from)) { $where[] = 'DATE(te.start_time) >= ?'; $params[] = $filter_from; }
if ($filter_to   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_to))   { $where[] = 'DATE(te.start_time) <= ?'; $params[] = $filter_to; }

$whereStr = implode(' AND ', $where);

try {
    $entries = $pdo->prepare(
        "SELECT te.*, u.name as dev_name, p.name as project_name, t.title as task_title
         FROM time_entries te
         JOIN users u ON u.id = te.user_id
         JOIN projects p ON p.id = te.project_id
         LEFT JOIN tasks t ON t.id = te.task_id
         WHERE $whereStr ORDER BY te.start_time DESC LIMIT 200"
    );
    $entries->execute($params);
    $entries = $entries->fetchAll();
} catch (Exception $e) { $entries = []; }

try {
    $projects   = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
    $developers = $pdo->query("SELECT id, name FROM users WHERE role='developer' ORDER BY name")->fetchAll();
} catch (Exception $e) { $projects = []; $developers = []; }

// Summary stats
try {
    $total_minutes = $pdo->query("SELECT SUM(duration_minutes) FROM time_entries")->fetchColumn() ?? 0;
    $total_hours   = round($total_minutes / 60, 1);
} catch (Exception $e) { $total_hours = 0; }

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-clock me-2"></i><?= __('time_tracking') ?></h1>
</div>
<div class="container-fluid">
    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center p-3">
                <h3 class="text-primary mb-0"><?= $total_hours ?>h</h3>
                <small class="text-muted"><?= __('total_hours') ?></small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center p-3">
                <h3 class="text-success mb-0"><?= count($entries) ?></h3>
                <small class="text-muted"><?= __('time_entries') ?></small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= __('project') ?></label>
                    <select name="project" class="form-select">
                        <option value="">— <?= __('filter') ?> —</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_project === (int)$p['id'] ? 'selected' : '' ?>><?= h($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('developer') ?></label>
                    <select name="developer" class="form-select">
                        <option value="">— <?= __('filter') ?> —</option>
                        <?php foreach ($developers as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $filter_dev === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('start_date') ?></label>
                    <input type="date" name="from" class="form-control" value="<?= h($filter_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?= __('end_date') ?></label>
                    <input type="date" name="to" class="form-control" value="<?= h($filter_to) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><?= __('filter') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Entries Table -->
    <div class="card">
        <div class="card-header bg-white fw-bold"><?= __('time_entries') ?></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?= __('developer') ?></th>
                        <th><?= __('project') ?></th>
                        <th><?= __('task') ?></th>
                        <th><?= __('description') ?></th>
                        <th><?= __('start_date') ?></th>
                        <th><?= __('duration') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><?= __('no_records') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= h($e['dev_name']) ?></td>
                        <td><?= h($e['project_name']) ?></td>
                        <td><?= h($e['task_title'] ?? '—') ?></td>
                        <td><?= h(mb_substr($e['description'] ?? '', 0, 60)) ?></td>
                        <td><?= h(substr($e['start_time'] ?? '', 0, 16)) ?></td>
                        <td><?= (int)floor($e['duration_minutes'] / 60) ?>h <?= (int)($e['duration_minutes'] % 60) ?>m</td>
                        <td>
                            <?php if ($e['is_approved']): ?>
                            <span class="badge bg-success"><?= __('approved') ?></span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark"><?= __('not_approved') ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$e['is_approved']): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="approve_id" value="<?= $e['id'] ?>">
                                <button class="btn btn-xs btn-success btn-sm" onclick="return confirm('Approve?')">✓</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="delete_id" value="<?= $e['id'] ?>">
                                <button class="btn btn-xs btn-danger btn-sm" onclick="return confirm('Delete?')">✕</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
