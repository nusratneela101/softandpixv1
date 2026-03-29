<?php
/**
 * Admin — Video Call Management Page
 */
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAdmin();

// Load upcoming meetings (all for admin)
try {
    $upcoming = $pdo->query(
        "SELECT vr.*, u.name as host_name,
                (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id AND vp.left_at IS NULL) as p_count
         FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         WHERE vr.status IN ('waiting','active')
         ORDER BY COALESCE(vr.scheduled_at, vr.created_at) ASC LIMIT 20"
    )->fetchAll();
} catch (Exception $e) { $upcoming = []; }

// Active calls
try {
    $active = $pdo->query(
        "SELECT vr.*, u.name as host_name,
                (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id AND vp.left_at IS NULL) as p_count
         FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         WHERE vr.status = 'active'
         ORDER BY vr.created_at DESC"
    )->fetchAll();
} catch (Exception $e) { $active = []; }

// Call history
try {
    $history = $pdo->query(
        "SELECT vr.*, u.name as host_name,
                (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id) as total_participants,
                TIMESTAMPDIFF(MINUTE, vr.created_at, COALESCE(vr.ended_at, NOW())) as duration_min
         FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         WHERE vr.status = 'ended'
         ORDER BY vr.ended_at DESC LIMIT 20"
    )->fetchAll();
} catch (Exception $e) { $history = []; }

// All users for invite
try {
    $all_users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name ASC")->fetchAll();
} catch (Exception $e) { $all_users = []; }

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="fas fa-video me-2"></i>Video Calls</h1>
        <p>Manage video meetings, view active calls, and schedule new meetings.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/video/join.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Meeting</a>
        <button class="btn btn-success" onclick="quickStart()"><i class="fas fa-video me-1"></i>Start Instant Call</button>
    </div>
</div>

<div class="container-fluid">
    <!-- Active Calls -->
    <?php if (!empty($active)): ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-broadcast-tower me-2"></i>Active Calls (<?php echo count($active); ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Room</th><th>Host</th><th>Participants</th><th>Started</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($active as $a): ?>
                    <tr>
                        <td><strong><?php echo h($a['room_name']); ?></strong><br><small class="text-muted"><?php echo h($a['room_code']); ?></small></td>
                        <td><?php echo h($a['host_name']); ?></td>
                        <td><span class="badge bg-primary"><?php echo (int)$a['p_count']; ?>/<?php echo (int)$a['max_participants']; ?></span></td>
                        <td><?php echo date('M j, g:i A', strtotime($a['created_at'])); ?></td>
                        <td><a href="/video/room.php?code=<?php echo urlencode($a['room_code']); ?>" class="btn btn-sm btn-success">Join</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Meetings -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span><i class="fas fa-calendar me-2"></i>Upcoming Meetings</span>
            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#scheduleMeetingModal">
                <i class="fas fa-plus me-1"></i>Schedule
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($upcoming)): ?>
            <div class="text-center py-4 text-muted">No upcoming meetings</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Meeting</th><th>Type</th><th>Host</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming as $m): ?>
                    <tr>
                        <td><strong><?php echo h($m['room_name']); ?></strong><br><small class="text-muted"><?php echo h($m['room_code']); ?></small></td>
                        <td><span class="badge bg-info"><?php echo ucfirst($m['room_type']); ?></span></td>
                        <td><?php echo h($m['host_name']); ?></td>
                        <td><?php echo $m['scheduled_at'] ? date('M j, g:i A', strtotime($m['scheduled_at'])) : 'Now'; ?></td>
                        <td><span class="badge bg-<?php echo $m['status']==='active'?'success':'warning'; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                        <td>
                            <a href="/video/room.php?code=<?php echo urlencode($m['room_code']); ?>" class="btn btn-sm btn-success">Join</a>
                        </td>
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
            <span><i class="fas fa-history me-2"></i>Call History</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($history)): ?>
            <div class="text-center py-4 text-muted">No call history yet</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Meeting</th><th>Host</th><th>Participants</th><th>Duration</th><th>Date</th><th>Recording</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo h($h['room_name']); ?></td>
                        <td><?php echo h($h['host_name']); ?></td>
                        <td><?php echo (int)$h['total_participants']; ?></td>
                        <td><?php echo (int)$h['duration_min']; ?> min</td>
                        <td><?php echo date('M j, Y g:i A', strtotime($h['ended_at'] ?? $h['created_at'])); ?></td>
                        <td><?php echo $h['is_recording'] ? '<span class="badge bg-danger">Recorded</span>' : '<span class="text-muted">—</span>'; ?></td>
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
<div class="modal fade" id="scheduleMeetingModal" tabindex="-1">
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
                            <label class="form-label">Start Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date & Time</label>
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
                        <select name="invite_users[]" class="form-select" multiple size="5">
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"><?php echo h($u['name']); ?> (<?php echo h($u['role']); ?> — <?php echo h($u['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple users</small>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check me-1"></i>Schedule Meeting</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function quickStart() {
    const fd = new FormData();
    fd.append('action', 'create_room');
    fd.append('room_name', 'Admin Quick Meeting');
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
<?php require_once 'includes/footer.php'; ?>
