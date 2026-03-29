<?php
/**
 * Developer — Video Call Page
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Developer';

// Upcoming meetings
try {
    $upcoming = $pdo->prepare(
        "SELECT DISTINCT vr.*, u.name as host_name,
                (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id AND vp.left_at IS NULL) as p_count
         FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
         WHERE vr.status IN ('waiting','active')
           AND (vr.created_by = ? OR mi.invited_user_id = ?)
         ORDER BY COALESCE(vr.scheduled_at, vr.created_at) ASC LIMIT 20"
    );
    $upcoming->execute([$userId, $userId]);
    $upcoming = $upcoming->fetchAll();
} catch (Exception $e) { $upcoming = []; }

// Call history
try {
    $historyStmt = $pdo->prepare(
        "SELECT DISTINCT vr.*, u.name as host_name,
                TIMESTAMPDIFF(MINUTE, vr.created_at, COALESCE(vr.ended_at, NOW())) as duration_min
         FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         LEFT JOIN video_participants vp ON vp.room_id = vr.id AND vp.user_id = ?
         LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id AND mi.invited_user_id = ?
         WHERE vr.status = 'ended' AND (vp.user_id = ? OR mi.invited_user_id = ? OR vr.created_by = ?)
         ORDER BY vr.ended_at DESC LIMIT 10"
    );
    $historyStmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $history = $historyStmt->fetchAll();
} catch (Exception $e) { $history = []; }

// All users for invite
try {
    $all_users = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name ASC");
    $all_users->execute([$userId]);
    $all_users = $all_users->fetchAll();
} catch (Exception $e) { $all_users = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Calls — Developer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/video.css" rel="stylesheet">
<style>
:root { --dev-color: #0ea5e9; }
body { background: #f0f7ff; }
.sidebar { background: linear-gradient(180deg, #0c4a6e, #0ea5e9); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 100; }
.main-content { margin-left: 240px; padding: 24px; }
@media (max-width: 768px) { .sidebar { width: 100%; min-height: auto; position: relative; } .main-content { margin-left: 0; } }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/sidebar_developer.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-video me-2"></i>Video Calls</h4>
            <p class="text-muted mb-0">Manage your video meetings and calls</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/video/join.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Meeting</a>
            <button class="btn btn-success" onclick="quickStart()"><i class="fas fa-video me-1"></i>Start Instant Call</button>
        </div>
    </div>

    <!-- Active / Upcoming -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between">
            <span><i class="fas fa-calendar me-2"></i>My Meetings</span>
            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="fas fa-calendar-plus me-1"></i>Schedule
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($upcoming)): ?>
            <div class="text-center py-4 text-muted">No upcoming meetings</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Meeting</th><th>Host</th><th>Scheduled</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $m): ?>
                    <tr>
                        <td><strong><?php echo h($m['room_name']); ?></strong><br><small class="text-muted"><?php echo h($m['room_code']); ?></small></td>
                        <td><?php echo h($m['host_name']); ?></td>
                        <td><?php echo $m['scheduled_at'] ? date('M j, g:i A', strtotime($m['scheduled_at'])) : 'Now'; ?></td>
                        <td><span class="badge bg-<?php echo $m['status']==='active'?'success':'warning'; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                        <td><a href="/video/room.php?code=<?php echo urlencode($m['room_code']); ?>" class="btn btn-sm btn-success">Join</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Call History -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-history me-2"></i>Call History
        </div>
        <div class="card-body p-0">
            <?php if (empty($history)): ?>
            <div class="text-center py-4 text-muted">No call history yet</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Meeting</th><th>Host</th><th>Duration</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo h($h['room_name']); ?></td>
                        <td><?php echo h($h['host_name']); ?></td>
                        <td><?php echo (int)$h['duration_min']; ?> min</td>
                        <td><?php echo date('M j, Y', strtotime($h['ended_at'] ?? $h['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Schedule Meeting Modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Meeting</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleForm" onsubmit="scheduleMeeting(event)">
                    <div class="mb-3">
                        <label class="form-label">Meeting Name</label>
                        <input type="text" name="room_name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End</label>
                            <input type="datetime-local" name="scheduled_end" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password (optional)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Participants</label>
                            <select name="max_participants" class="form-select">
                                <option value="2">2</option><option value="3">3</option><option value="4">4</option>
                                <option value="5">5</option><option value="6" selected>6</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invite Participants</label>
                        <select name="invite_users[]" class="form-select" multiple size="4">
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"><?php echo h($u['name']); ?> (<?php echo h($u['role']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check me-1"></i>Schedule</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function quickStart() {
    const fd = new FormData();
    fd.append('action', 'create_room');
    fd.append('room_name', 'Dev Quick Meeting');
    fd.append('room_type', 'instant');
    fd.append('max_participants', '6');
    fetch('/video/signaling.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json())
        .then(d => {
            if(d.success) window.location.href='/video/room.php?code='+encodeURIComponent(d.room_code);
            else alert(d.error||'Error');
        }).catch(()=>alert('Network error'));
}

function scheduleMeeting(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('scheduleForm'));
    fd.append('action', 'schedule_meeting');
    fetch('/video/signaling.php', { method:'POST', body:fd, credentials:'same-origin' })
        .then(r=>r.json())
        .then(d => {
            if(d.success) { alert('Meeting scheduled! Code: '+d.room_code); location.reload(); }
            else alert(d.error||'Error');
        }).catch(()=>alert('Network error'));
}
</script>
</body>
</html>
