<?php
/**
 * Client — Video Call Page
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';
requireClient();

$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Client';

// Upcoming meetings (client is invited to or created)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Video Calls — Client</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/video.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.sidebar { background: linear-gradient(180deg, #1e3a5f, #2563eb); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 100; }
.main-content { margin-left: 240px; padding: 24px; }
@media (max-width: 768px) { .sidebar { width: 100%; min-height: auto; position: relative; } .main-content { margin-left: 0; } }
</style>
</head>
<body>

<?php require_once __DIR__ . '/../includes/sidebar_client.php'; ?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-video me-2"></i>Video Calls</h4>
            <p class="text-muted mb-0">Join meetings and view your call history</p>
        </div>
        <a href="/video/join.php" class="btn btn-primary"><i class="fas fa-sign-in-alt me-1"></i>Join / Create Meeting</a>
    </div>

    <!-- Join Meeting -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-sign-in-alt me-2"></i>Join a Meeting
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <input type="text" id="joinCode" class="form-control" placeholder="Enter Room Code (e.g., XXXX-XXXX-XXXX)">
                </div>
                <div class="col-md-4">
                    <input type="password" id="joinPwd" class="form-control" placeholder="Password (if required)">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" onclick="joinMeeting()"><i class="fas fa-sign-in-alt me-1"></i>Join</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming / Active Meetings -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between">
            <span><i class="fas fa-calendar me-2"></i>My Meetings</span>
            <span class="badge bg-light text-dark"><?php echo count($upcoming); ?></span>
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
                        <td><strong><?php echo h($m['room_name']); ?></strong></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function joinMeeting() {
    const code = document.getElementById('joinCode').value.trim();
    if (!code) { alert('Enter a room code'); return; }
    window.location.href = '/video/room.php?code=' + encodeURIComponent(code);
}
</script>
</body>
</html>
