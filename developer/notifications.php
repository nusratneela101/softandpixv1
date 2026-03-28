<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId = $_SESSION['user_id'];

$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();

    // Mark all as read
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0")
        ->execute([$userId]);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - Developer Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f0f7ff; }
.sidebar { background:linear-gradient(180deg,#0c4a6e,#0ea5e9); width:240px; min-height:100vh; position:fixed; top:0; left:0; z-index:100; }
.sidebar .brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:10px 20px; display:flex; align-items:center; gap:10px; }
.sidebar .nav-link:hover { background:rgba(255,255,255,.15); color:#fff; border-radius:8px; margin:2px 8px; padding:10px 12px; }
.main-content { margin-left:240px; padding:24px; }
.notif-item { border-left:3px solid #dee2e6; transition:background .15s; }
.notif-item.unread { border-color:#0ea5e9; background:#f0f7ff; }
.notif-item:hover { background:#f8fafc; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="brand">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="" style="max-height:35px;filter:brightness(10);">
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link" href="/developer/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/developer/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
        <a class="nav-link active" href="/developer/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-bell me-2 text-primary"></i>Notifications</h4>
        <?php if (!empty($notifications)): ?>
        <form method="POST" action="/api/notifications/mark_read.php">
            <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
            <input type="hidden" name="mark_all" value="1">
            <button class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-check2-all me-1"></i>Mark All Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <?php if (empty($notifications)): ?>
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-bell-slash" style="font-size:3rem;opacity:.4;"></i>
            <p class="mt-3">No notifications yet.</p>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush" style="border-radius:12px;">
            <?php foreach ($notifications as $n):
                $iconMap = [
                    'project_assigned' => ['bi-kanban','text-primary'],
                    'deadline_updated' => ['bi-calendar-x','text-warning'],
                    'message'          => ['bi-chat-dots','text-info'],
                    'invoice'          => ['bi-receipt','text-success'],
                    'system'           => ['bi-bell','text-secondary'],
                ];
                [$icon, $iconColor] = $iconMap[$n['type'] ?? 'system'] ?? ['bi-bell','text-secondary'];
            ?>
            <div class="list-group-item notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?> px-4 py-3">
                <div class="d-flex gap-3 align-items-start">
                    <i class="bi <?php echo $icon; ?> <?php echo $iconColor; ?> fs-5 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?php echo h($n['title'] ?? 'Notification'); ?></div>
                        <div class="text-muted"><?php echo h($n['message'] ?? ''); ?></div>
                        <?php if (!empty($n['link'])): ?>
                        <a href="<?php echo h($n['link']); ?>" class="small text-primary mt-1 d-inline-block">View →</a>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small text-nowrap"><?php echo timeAgo($n['created_at']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
