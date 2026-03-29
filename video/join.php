<?php
/**
 * Video Call — Join / Create / Schedule Meeting Page
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'client';

// Load all users for invite dropdown
try {
    $usersStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name ASC");
    $usersStmt->execute([$user_id]);
    $all_users = $usersStmt->fetchAll();
} catch (Exception $e) { $all_users = []; }

// Load upcoming meetings
try {
    $meetingsStmt = $pdo->prepare(
        "SELECT DISTINCT vr.*, u.name as host_name FROM video_rooms vr
         LEFT JOIN users u ON u.id = vr.created_by
         LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
         LEFT JOIN video_participants vp ON vp.room_id = vr.id
         WHERE vr.status IN ('waiting', 'active')
           AND (vr.created_by = ? OR mi.invited_user_id = ? OR vp.user_id = ?)
         ORDER BY COALESCE(vr.scheduled_at, vr.created_at) ASC LIMIT 20"
    );
    $meetingsStmt->execute([$user_id, $user_id, $user_id]);
    $upcoming_meetings = $meetingsStmt->fetchAll();
} catch (Exception $e) { $upcoming_meetings = []; }

$error = $_GET['error'] ?? '';
$site_url = defined('SITE_URL') ? SITE_URL : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Calls — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/video.css" rel="stylesheet">
</head>
<body class="video-join-page">
<div class="container py-4" style="max-width:1000px;">
    <div class="text-center mb-4">
        <h2><i class="fas fa-video me-2"></i>Video Calls</h2>
        <p style="color:rgba(255,255,255,0.6);">Create, schedule, or join a video meeting</p>
    </div>

    <?php if ($error === 'notfound'): ?>
    <div class="alert alert-danger mb-3">Room not found. Please check the room code.</div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Quick Call -->
        <div class="col-md-6">
            <div class="join-card h-100">
                <h3><i class="fas fa-bolt me-2"></i>Quick Call</h3>
                <p style="color:rgba(255,255,255,0.5);font-size:14px;">Start an instant meeting with a random code.</p>
                <button class="btn btn-success w-100 mt-3" id="btnQuickCall" onclick="quickCall()">
                    <i class="fas fa-video me-2"></i>Start Instant Meeting
                </button>
            </div>
        </div>

        <!-- Join Meeting -->
        <div class="col-md-6">
            <div class="join-card h-100">
                <h3><i class="fas fa-sign-in-alt me-2"></i>Join Meeting</h3>
                <div class="mb-3">
                    <label class="form-label">Room Code</label>
                    <input type="text" id="joinRoomCode" class="form-control" placeholder="XXXX-XXXX-XXXX" maxlength="14">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password (if required)</label>
                    <input type="password" id="joinRoomPassword" class="form-control" placeholder="Optional">
                </div>
                <button class="btn btn-primary w-100" onclick="joinMeeting()">
                    <i class="fas fa-sign-in-alt me-2"></i>Join
                </button>
            </div>
        </div>

        <!-- Create Meeting -->
        <div class="col-md-6">
            <div class="join-card">
                <h3><i class="fas fa-plus-circle me-2"></i>Create Meeting</h3>
                <form id="createForm" onsubmit="createMeeting(event)">
                    <div class="mb-2">
                        <label class="form-label">Room Name</label>
                        <input type="text" name="room_name" class="form-control" required placeholder="Team Standup" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Meeting Type</label>
                        <select name="room_type" class="form-select" id="meetingType" onchange="toggleScheduleFields()">
                            <option value="instant">Instant</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                    </div>
                    <div id="scheduleFields" style="display:none;">
                        <div class="mb-2">
                            <label class="form-label">Start Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">End Date & Time</label>
                            <input type="datetime-local" name="scheduled_end" class="form-control">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Description (optional)</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Password (optional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank for no password">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Max Participants</label>
                        <select name="max_participants" class="form-select">
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6" selected>6</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Invite Participants</label>
                        <select name="invite_users[]" class="form-select" multiple size="4">
                            <?php foreach ($all_users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"><?php echo h($u['name']); ?> (<?php echo h($u['role']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:rgba(255,255,255,0.4);">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Create Meeting
                    </button>
                </form>
            </div>
        </div>

        <!-- Upcoming Meetings -->
        <div class="col-md-6">
            <div class="join-card">
                <h3><i class="fas fa-calendar me-2"></i>Upcoming Meetings</h3>
                <?php if (empty($upcoming_meetings)): ?>
                <p style="color:rgba(255,255,255,0.4);text-align:center;padding:20px 0;">No upcoming meetings</p>
                <?php else: ?>
                <div style="max-height:400px;overflow-y:auto;">
                    <?php foreach ($upcoming_meetings as $m): ?>
                    <div class="upcoming-meeting-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="meeting-name"><?php echo h($m['room_name']); ?></div>
                                <div class="meeting-meta">
                                    <i class="fas fa-user me-1"></i><?php echo h($m['host_name']); ?>
                                    <?php if ($m['scheduled_at']): ?>
                                    · <i class="fas fa-clock me-1"></i><?php echo date('M j, g:i A', strtotime($m['scheduled_at'])); ?>
                                    <?php endif; ?>
                                    · <span class="badge" style="background:<?php echo $m['status']==='active'?'#2ed573':'#ffa502'; ?>;font-size:10px;">
                                        <?php echo ucfirst($m['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="/video/room.php?code=<?php echo urlencode($m['room_code']); ?>"
                                   class="btn btn-sm btn-success">Join</a>
                                <?php if ((int)$m['created_by'] === $user_id): ?>
                                <button class="btn btn-sm btn-outline-danger" onclick="cancelMeeting('<?php echo h($m['room_code']); ?>')">Cancel</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="/<?php echo h($user_role === 'admin' ? 'admin' : ($user_role === 'developer' ? 'developer' : 'client')); ?>/video_call.php"
           class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    </div>
</div>

<script>
function toggleScheduleFields() {
    const sel = document.getElementById('meetingType');
    document.getElementById('scheduleFields').style.display = sel.value === 'scheduled' ? 'block' : 'none';
}

function quickCall() {
    const fd = new FormData();
    fd.append('action', 'create_room');
    fd.append('room_name', 'Quick Meeting');
    fd.append('room_type', 'instant');
    fd.append('max_participants', '6');

    fetch('/video/signaling.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/video/room.php?code=' + encodeURIComponent(data.room_code);
            } else {
                alert(data.error || 'Failed to create room');
            }
        }).catch(() => alert('Network error'));
}

function joinMeeting() {
    const code = document.getElementById('joinRoomCode').value.trim();
    if (!code) { alert('Enter a room code'); return; }
    window.location.href = '/video/room.php?code=' + encodeURIComponent(code);
}

function createMeeting(e) {
    e.preventDefault();
    const form = document.getElementById('createForm');
    const fd = new FormData(form);
    fd.append('action', 'create_room');

    fetch('/video/signaling.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const type = fd.get('room_type');
                if (type === 'instant') {
                    window.location.href = '/video/room.php?code=' + encodeURIComponent(data.room_code);
                } else {
                    alert('Meeting scheduled! Room code: ' + data.room_code);
                    window.location.reload();
                }
            } else {
                alert(data.error || 'Failed to create meeting');
            }
        }).catch(() => alert('Network error'));
}

function cancelMeeting(roomCode) {
    if (!confirm('Cancel this meeting?')) return;
    const fd = new FormData();
    fd.append('action', 'end_room');
    fd.append('room_code', roomCode);

    fetch('/video/signaling.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.reload();
            else alert(data.error || 'Failed to cancel');
        }).catch(() => alert('Network error'));
}
</script>
</body>
</html>
