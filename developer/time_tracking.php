<?php
/**
 * Developer — Time Tracking: start/stop timer, manual entries, view log
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../includes/activity_logger.php';
requireDeveloper();

$page_title = __('time_tracking');
$user_id    = $_SESSION['user_id'];

// Handle AJAX actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'start_timer':
                // Check if timer already running
                // Check if timer already running (unused variable removed)
                $pdo->prepare("DELETE FROM active_timers WHERE user_id=?")->execute([$user_id]);
                $pdo->prepare(
                    "INSERT INTO active_timers (user_id, project_id, task_id, start_time, description) VALUES (?,?,?,NOW(),?)"
                )->execute([$user_id, (int)$_POST['project_id'], ($_POST['task_id'] ?: null), $_POST['description'] ?? '']);
                echo json_encode(['success'=>true,'message'=>'Timer started']);
                break;
            case 'stop_timer':
                $timer = $pdo->prepare("SELECT * FROM active_timers WHERE user_id=?");
                $timer->execute([$user_id]);
                $timer = $timer->fetch();
                if (!$timer) { echo json_encode(['success'=>false,'message'=>'No active timer']); break; }
                $duration = (int)round((time() - strtotime($timer['start_time'])) / 60);
                $pdo->prepare(
                    "INSERT INTO time_entries (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual) VALUES (?,?,?,?,?,NOW(),?,0)"
                )->execute([$user_id, $timer['project_id'], $timer['task_id'], $timer['description'], $timer['start_time'], $duration]);
                $pdo->prepare("DELETE FROM active_timers WHERE user_id=?")->execute([$user_id]);
                log_activity($pdo, $user_id, 'timer_stopped', "Logged {$duration} minutes", 'time_entry', null);
                echo json_encode(['success'=>true,'message'=>'Timer stopped','duration'=>$duration]);
                break;
            case 'manual_entry':
                $hours   = max(0, (int)$_POST['hours']);
                $minutes = max(0, min(59, (int)$_POST['minutes']));
                $total   = ($hours * 60) + $minutes;
                $date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'] ?? '') ? $_POST['date'] : date('Y-m-d');
                if ($total < 1) { echo json_encode(['success'=>false,'message'=>'Duration must be at least 1 minute']); break; }
                $pdo->prepare(
                    "INSERT INTO time_entries (user_id, project_id, task_id, description, start_time, end_time, duration_minutes, is_manual) VALUES (?,?,?,?,?,?,?,1)"
                )->execute([$user_id, (int)$_POST['project_id'], ($_POST['task_id'] ?: null), $_POST['description'] ?? '', $date . ' 09:00:00', $date . ' ' . str_pad($hours,2,'0',STR_PAD_LEFT) . ':' . str_pad($minutes,2,'0',STR_PAD_LEFT) . ':00', $total]);
                echo json_encode(['success'=>true,'message'=>'Entry added']);
                break;
            case 'delete_entry':
                $id = (int)$_POST['entry_id'];
                $check = $pdo->prepare("SELECT id FROM time_entries WHERE id=? AND user_id=? AND is_approved=0");
                $check->execute([$id, $user_id]);
                if ($check->fetch()) {
                    $pdo->prepare("DELETE FROM time_entries WHERE id=?")->execute([$id]);
                    echo json_encode(['success'=>true]);
                } else {
                    echo json_encode(['success'=>false,'message'=>'Cannot delete']);
                }
                break;
            default:
                echo json_encode(['success'=>false,'message'=>'Unknown action']);
        }
    } catch (Exception $e) {
        error_log('time_tracking dev: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Error']);
    }
    exit;
}

// Load data for page
try {
    $active_timer = $pdo->prepare("SELECT * FROM active_timers WHERE user_id=?");
    $active_timer->execute([$user_id]);
    $active_timer = $active_timer->fetch();
} catch (Exception $e) { $active_timer = null; }

try {
    $entries = $pdo->prepare(
        "SELECT te.*, p.name as project_name, t.title as task_title
         FROM time_entries te
         JOIN projects p ON p.id=te.project_id
         LEFT JOIN tasks t ON t.id=te.task_id
         WHERE te.user_id=?
         ORDER BY te.start_time DESC LIMIT 100"
    );
    $entries->execute([$user_id]);
    $entries = $entries->fetchAll();
} catch (Exception $e) { $entries = []; }

try {
    $projects = $pdo->prepare("SELECT DISTINCT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? OR p.id IN (SELECT project_id FROM tasks WHERE assigned_to=?) ORDER BY p.name");
    $projects->execute([$user_id, $user_id]);
    $projects = $projects->fetchAll();
    if (empty($projects)) {
        $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
    }
} catch (Exception $e) { $projects = []; }

try {
    $my_tasks = $pdo->prepare("SELECT id, title, project_id FROM tasks WHERE assigned_to=? AND status != 'completed' ORDER BY title");
    $my_tasks->execute([$user_id]);
    $my_tasks = $my_tasks->fetchAll();
} catch (Exception $e) { $my_tasks = []; }

// Weekly summary
try {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_mins  = $pdo->prepare("SELECT SUM(duration_minutes) FROM time_entries WHERE user_id=? AND start_time >= ?");
    $week_mins->execute([$user_id, $week_start]);
    $week_hours = round($week_mins->fetchColumn() / 60, 1);
} catch (Exception $e) { $week_hours = 0; }

require_once '../includes/header.php';
require_once '../includes/sidebar_developer.php';
?>
<div class="content-area">
<div class="page-header">
    <h1><i class="fas fa-clock me-2"></i><?= __('time_tracking') ?></h1>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center p-3 <?= $active_timer ? 'border-danger' : '' ?>">
            <?php if ($active_timer): ?>
            <div class="timer-indicator justify-content-center mb-2">
                <span>●</span> <span><?= __('timer_running') ?></span>
            </div>
            <h3 class="text-danger mb-0" id="liveTimer">00:00</h3>
            <?php else: ?>
            <h3 class="text-secondary mb-0">—</h3>
            <?php endif; ?>
            <small class="text-muted"><?= __('start_timer') ?></small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-3">
            <h3 class="text-primary mb-0"><?= $week_hours ?>h</h3>
            <small class="text-muted"><?= __('hours_this_week') ?></small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center p-3">
            <h3 class="text-success mb-0"><?= count($entries) ?></h3>
            <small class="text-muted"><?= __('time_entries') ?></small>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Timer / Manual Entry -->
    <div class="col-lg-5">
        <!-- Start/Stop Timer -->
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold"><i class="fas fa-stopwatch me-2"></i><?= __('start_timer') ?></div>
            <div class="card-body">
                <?php if ($active_timer): ?>
                <div class="alert alert-danger">
                    <strong><?= __('timer_running') ?>:</strong> since <?= h(substr($active_timer['start_time'],0,16)) ?>
                </div>
                <button class="btn btn-danger w-100" id="stopTimerBtn">
                    <i class="fas fa-stop me-2"></i><?= __('stop_timer') ?>
                </button>
                <?php else: ?>
                <form id="startTimerForm">
                    <div class="mb-3">
                        <label class="form-label"><?= __('project') ?> *</label>
                        <select name="project_id" class="form-select" required id="timerProject">
                            <option value="">— <?= __('project') ?> —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('task') ?></label>
                        <select name="task_id" class="form-select" id="timerTask">
                            <option value="">— <?= __('task') ?> (<?= __('status_active') ?>) —</option>
                            <?php foreach ($my_tasks as $t): ?>
                            <option value="<?= $t['id'] ?>" data-project="<?= $t['project_id'] ?>"><?= h($t['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('description') ?></label>
                        <input type="text" name="description" class="form-control" placeholder="What are you working on?">
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-play me-2"></i><?= __('start_timer') ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manual Entry -->
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="fas fa-pencil-alt me-2"></i><?= __('manual_entry') ?></div>
            <div class="card-body">
                <form id="manualEntryForm">
                    <div class="mb-3">
                        <label class="form-label"><?= __('project') ?> *</label>
                        <select name="project_id" class="form-select" required>
                            <option value="">— <?= __('project') ?> —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('task') ?></label>
                        <select name="task_id" class="form-select">
                            <option value="">— <?= __('task') ?> —</option>
                            <?php foreach ($my_tasks as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= h($t['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label"><?= __('hours') ?></label>
                            <input type="number" name="hours" class="form-control" min="0" max="24" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?= __('minutes') ?></label>
                            <input type="number" name="minutes" class="form-control" min="0" max="59" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('date') ?></label>
                        <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('description') ?></label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i><?= __('add') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Entries List -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white fw-bold"><?= __('time_entries') ?></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="entriesTable">
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('project') ?></th>
                            <th><?= __('duration') ?></th>
                            <th><?= __('status') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?= __('no_records') ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($entries as $e): ?>
                        <tr id="entry-<?= $e['id'] ?>">
                            <td><?= h(substr($e['start_time'], 0, 10)) ?></td>
                            <td><?= h($e['project_name']) ?><?= $e['task_title'] ? '<br><small class="text-muted">' . h($e['task_title']) . '</small>' : '' ?></td>
                            <td><?= (int)floor($e['duration_minutes']/60) ?>h <?= (int)($e['duration_minutes']%60) ?>m</td>
                            <td><?= $e['is_approved'] ? '<span class="badge bg-success">' . __('approved') . '</span>' : '<span class="badge bg-secondary">' . __('not_approved') . '</span>' ?></td>
                            <td>
                                <?php if (!$e['is_approved']): ?>
                                <button class="btn btn-xs btn-danger btn-sm" onclick="deleteEntry(<?= $e['id'] ?>)">✕</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div><!-- /content-area -->

<script>
<?php if ($active_timer): ?>
SP.startLiveTimer('<?= $active_timer['start_time'] ?>', document.getElementById('liveTimer'));
<?php endif; ?>

document.getElementById('startTimerForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    fd.append('action', 'start_timer');
    fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d => {
            if (d.success) location.reload();
            else SP.toast(d.message, 'error');
        });
});

document.getElementById('stopTimerBtn')?.addEventListener('click', function() {
    var fd = new FormData();
    fd.append('action', 'stop_timer');
    fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d => {
            if (d.success) { SP.toast('Time logged: ' + d.duration + ' minutes', 'success'); setTimeout(()=>location.reload(), 1000); }
            else SP.toast(d.message, 'error');
        });
});

document.getElementById('manualEntryForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    fd.append('action', 'manual_entry');
    fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d => {
            if (d.success) { SP.toast('<?= __("created_successfully") ?>', 'success'); setTimeout(()=>location.reload(), 800); }
            else SP.toast(d.message, 'error');
        });
});

function deleteEntry(id) {
    if (!confirm('<?= __("confirm_delete") ?>')) return;
    var fd = new FormData();
    fd.append('action', 'delete_entry');
    fd.append('entry_id', id);
    fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(r=>r.json()).then(d => {
            if (d.success) { var row = document.getElementById('entry-'+id); if(row) row.remove(); SP.toast('Deleted', 'success'); }
            else SP.toast(d.message, 'error');
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>
