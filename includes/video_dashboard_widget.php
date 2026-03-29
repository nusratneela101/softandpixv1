<?php
/**
 * Video Dashboard Widget — shared include for all dashboards.
 *
 * Queries upcoming meetings, active calls, and stats for the current user.
 * Respects user role: admin sees all, client/developer see only their meetings.
 *
 * Required: $pdo, $_SESSION['user_id'], $_SESSION['user_role']
 */

$_vw_user_id   = (int)($_SESSION['user_id'] ?? 0);
$_vw_user_role = $_SESSION['user_role'] ?? '';
$_vw_upcoming  = [];
$_vw_active    = [];
$_vw_stats     = ['today' => 0, 'week' => 0, 'month' => 0];

try {
    // Upcoming scheduled meetings
    if ($_vw_user_role === 'admin') {
        // Admin sees all upcoming meetings
        $stmt = $pdo->query(
            "SELECT vr.*, u.name as host_name FROM video_rooms vr
             LEFT JOIN users u ON u.id = vr.created_by
             WHERE vr.status IN ('waiting') AND vr.scheduled_at IS NOT NULL AND vr.scheduled_at > NOW()
             ORDER BY vr.scheduled_at ASC LIMIT 5"
        );
    } else {
        // Client/Developer sees only their meetings
        $stmt = $pdo->prepare(
            "SELECT DISTINCT vr.*, u.name as host_name FROM video_rooms vr
             LEFT JOIN users u ON u.id = vr.created_by
             LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
             WHERE vr.status IN ('waiting')
               AND (vr.created_by = ? OR mi.invited_user_id = ?)
               AND vr.scheduled_at IS NOT NULL AND vr.scheduled_at > NOW()
             ORDER BY vr.scheduled_at ASC LIMIT 5"
        );
        $stmt->execute([$_vw_user_id, $_vw_user_id]);
    }
    $_vw_upcoming = $stmt->fetchAll();

    // Active calls
    if ($_vw_user_role === 'admin') {
        $activeStmt = $pdo->query(
            "SELECT vr.*, u.name as host_name,
                    (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id AND vp.left_at IS NULL) as p_count
             FROM video_rooms vr
             LEFT JOIN users u ON u.id = vr.created_by
             WHERE vr.status = 'active'
             ORDER BY vr.created_at DESC LIMIT 5"
        );
    } else {
        $activeStmt = $pdo->prepare(
            "SELECT DISTINCT vr.*, u.name as host_name,
                    (SELECT COUNT(*) FROM video_participants vp WHERE vp.room_id = vr.id AND vp.left_at IS NULL) as p_count
             FROM video_rooms vr
             LEFT JOIN users u ON u.id = vr.created_by
             LEFT JOIN meeting_invitations mi ON mi.room_id = vr.id
             LEFT JOIN video_participants vp2 ON vp2.room_id = vr.id AND vp2.user_id = ?
             WHERE vr.status = 'active'
               AND (vr.created_by = ? OR mi.invited_user_id = ? OR vp2.user_id = ?)
             ORDER BY vr.created_at DESC LIMIT 5"
        );
        $activeStmt->execute([$_vw_user_id, $_vw_user_id, $_vw_user_id, $_vw_user_id]);
    }
    $_vw_active = $activeStmt->fetchAll();

    // Stats
    if ($_vw_user_role === 'admin') {
        $_vw_stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM video_rooms WHERE DATE(created_at)=CURDATE()")->fetchColumn();
        $_vw_stats['week']  = (int)$pdo->query("SELECT COUNT(*) FROM video_rooms WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
        $_vw_stats['month'] = (int)$pdo->query("SELECT COUNT(*) FROM video_rooms WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
    } else {
        $s = $pdo->prepare("SELECT COUNT(*) FROM video_rooms vr LEFT JOIN meeting_invitations mi ON mi.room_id=vr.id WHERE (vr.created_by=? OR mi.invited_user_id=?) AND DATE(vr.created_at)=CURDATE()");
        $s->execute([$_vw_user_id, $_vw_user_id]);
        $_vw_stats['today'] = (int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM video_rooms vr LEFT JOIN meeting_invitations mi ON mi.room_id=vr.id WHERE (vr.created_by=? OR mi.invited_user_id=?) AND vr.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $s->execute([$_vw_user_id, $_vw_user_id]);
        $_vw_stats['week'] = (int)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COUNT(*) FROM video_rooms vr LEFT JOIN meeting_invitations mi ON mi.room_id=vr.id WHERE (vr.created_by=? OR mi.invited_user_id=?) AND vr.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $s->execute([$_vw_user_id, $_vw_user_id]);
        $_vw_stats['month'] = (int)$s->fetchColumn();
    }
} catch (Exception $e) {
    // Silently fail — tables may not exist yet
}
?>

<!-- Video Call Dashboard Widgets -->
<?php if (!empty($_vw_active)): ?>
<div class="card video-widget-card mb-3">
    <div class="card-header">
        <span><i class="fas fa-video me-2"></i>Active Calls</span>
        <span class="badge bg-light text-dark"><?php echo count($_vw_active); ?></span>
    </div>
    <div class="card-body p-0">
        <?php foreach ($_vw_active as $ac): ?>
        <div class="meeting-list-item">
            <div>
                <div class="ml-name"><?php echo h($ac['room_name']); ?></div>
                <div class="ml-time"><i class="fas fa-user me-1"></i><?php echo h($ac['host_name']); ?> · <?php echo (int)$ac['p_count']; ?> participants</div>
            </div>
            <a href="/video/room.php?code=<?php echo urlencode($ac['room_code']); ?>" class="btn btn-sm btn-success">Join</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($_vw_upcoming)): ?>
<div class="card video-widget-card mb-3">
    <div class="card-header">
        <span><i class="fas fa-calendar me-2"></i>Upcoming Meetings</span>
        <span class="badge bg-light text-dark"><?php echo count($_vw_upcoming); ?></span>
    </div>
    <div class="card-body p-0">
        <?php foreach ($_vw_upcoming as $um): ?>
        <div class="meeting-list-item">
            <div>
                <div class="ml-name"><?php echo h($um['room_name']); ?></div>
                <div class="ml-time">
                    <i class="fas fa-clock me-1"></i><?php echo date('M j, g:i A', strtotime($um['scheduled_at'])); ?>
                    · <?php echo h($um['host_name']); ?>
                </div>
            </div>
            <a href="/video/room.php?code=<?php echo urlencode($um['room_code']); ?>" class="btn btn-sm btn-outline-primary">Join</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($_vw_user_role === 'admin'): ?>
<div class="card video-widget-card mb-3">
    <div class="card-header">
        <span><i class="fas fa-chart-bar me-2"></i>Meeting Stats</span>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-4">
                <div class="fs-4 fw-bold text-primary"><?php echo $_vw_stats['today']; ?></div>
                <div class="text-muted small">Today</div>
            </div>
            <div class="col-4">
                <div class="fs-4 fw-bold text-info"><?php echo $_vw_stats['week']; ?></div>
                <div class="text-muted small">This Week</div>
            </div>
            <div class="col-4">
                <div class="fs-4 fw-bold text-success"><?php echo $_vw_stats['month']; ?></div>
                <div class="text-muted small">This Month</div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
