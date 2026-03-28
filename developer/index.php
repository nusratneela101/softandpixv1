<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId = $_SESSION['user_id'];
$flash  = getFlashMessage();

try {
    $projects = $pdo->prepare("SELECT * FROM projects WHERE developer_id=? AND status NOT IN ('cancelled') ORDER BY deadline ASC");
    $projects->execute([$userId]);
    $projects = $projects->fetchAll();

    $totalProjects     = count($projects);
    $activeProjects    = count(array_filter($projects, fn($p) => $p['status'] === 'in_progress'));
    $completedProjects = count(array_filter($projects, fn($p) => $p['status'] === 'completed'));
    $pendingDeadlines  = count(array_filter($projects, fn($p) =>
        $p['deadline'] && strtotime($p['deadline']) < strtotime('+7 days') && $p['status'] !== 'completed'));

    $notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
    $notifs->execute([$userId]);
    $notifs = $notifs->fetchAll();

    $unreadMsgs = 0;
    try {
        $mr = $pdo->prepare("SELECT COUNT(*) FROM chat_messages cm
            INNER JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.user_id=?
            WHERE cm.is_read=0 AND cm.sender_id!=?");
        $mr->execute([$userId, $userId]);
        $unreadMsgs = (int)$mr->fetchColumn();
    } catch (Exception $e) {}

} catch (Exception $e) {
    $projects = []; $totalProjects = 0; $activeProjects = 0;
    $completedProjects = 0; $pendingDeadlines = 0; $notifs = [];
}

$unreadNotifs = count(array_filter($notifs ?? [], fn($n) => !$n['is_read']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Developer Dashboard - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root { --dev-color: #0ea5e9; }
body { background: #f0f7ff; }
.sidebar { background: linear-gradient(180deg, #0c4a6e, #0ea5e9); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 100; }
.sidebar .brand { padding: 20px; border-bottom: 1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color: rgba(255,255,255,.85); padding: 10px 20px; display: flex; align-items: center; gap: 10px; }
.sidebar .nav-link:hover,
.sidebar .nav-link.active { background: rgba(255,255,255,.15); color: #fff; border-radius: 8px; margin: 2px 8px; padding: 10px 12px; }
.main-content { margin-left: 240px; padding: 24px; }
.stat-card { border-radius: 12px; padding: 24px; color: #fff; position: relative; overflow: hidden; }
.stat-card .icon { position: absolute; right: 20px; top: 20px; font-size: 2.5rem; opacity: .3; }
.project-card { border-radius: 12px; border: 1px solid #e2e8f0; transition: transform .2s; }
.project-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.1); }
@media (max-width: 768px) {
    .sidebar { width: 100%; min-height: auto; position: relative; }
    .main-content { margin-left: 0; }
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:35px;filter:brightness(10);">
    </div>
    <div class="mt-3 px-3 pb-2 small text-white-50">
        <?php echo h($_SESSION['user_name'] ?? ''); ?>
        <span class="badge ms-1" style="background:rgba(255,255,255,.2);"><?php echo h($_SESSION['user_role'] ?? 'developer'); ?></span>
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link active" href="/developer/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/developer/chat.php">
            <i class="bi bi-chat-dots"></i> Chat
            <?php if ($unreadMsgs > 0): ?>
                <span class="badge bg-danger ms-auto"><?php echo $unreadMsgs; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link" href="/developer/notifications.php">
            <i class="bi bi-bell"></i> Notifications
            <?php if ($unreadNotifs > 0): ?>
                <span class="badge bg-warning text-dark ms-auto"><?php echo $unreadNotifs; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">Welcome back, <?php echo h($_SESSION['user_name'] ?? 'Developer'); ?>! 👋</h4>
            <p class="text-muted mb-0"><?php echo date('l, F j, Y'); ?></p>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">
                <i class="bi bi-kanban icon"></i>
                <h3 class="mb-0"><?php echo $totalProjects; ?></h3>
                <p class="mb-0 small">Total Projects</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);">
                <i class="bi bi-arrow-repeat icon"></i>
                <h3 class="mb-0"><?php echo $activeProjects; ?></h3>
                <p class="mb-0 small">In Progress</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
                <i class="bi bi-check2-circle icon"></i>
                <h3 class="mb-0"><?php echo $completedProjects; ?></h3>
                <p class="mb-0 small">Completed</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <i class="bi bi-calendar-x icon"></i>
                <h3 class="mb-0"><?php echo $pendingDeadlines; ?></h3>
                <p class="mb-0 small">Due Soon</p>
            </div>
        </div>
    </div>

    <!-- Projects -->
    <h5 class="fw-bold mb-3">My Projects</h5>
    <?php if (empty($projects)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-folder" style="font-size:3rem;"></i>
        <p class="mt-2">No projects assigned yet.</p>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($projects as $p):
            $statusColor = ['pending'=>'warning','in_progress'=>'primary','on_hold'=>'secondary','completed'=>'success','cancelled'=>'danger'][$p['status']] ?? 'secondary';
            $isOverdue   = $p['deadline'] && strtotime($p['deadline']) < time() && $p['status'] !== 'completed';
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card project-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0"><?php echo h($p['title']); ?></h6>
                        <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucwords(str_replace('_', ' ', $p['status'])); ?></span>
                    </div>
                    <?php if (!empty($p['description'])): ?>
                    <p class="text-muted small mb-2"><?php echo h(substr($p['description'], 0, 100)); ?><?php echo strlen($p['description']) > 100 ? '…' : ''; ?></p>
                    <?php endif; ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Progress</small>
                            <small class="fw-semibold"><?php echo (int)($p['progress'] ?? 0); ?>%</small>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar <?php echo (int)($p['progress'] ?? 0) === 100 ? 'bg-success' : ''; ?>"
                                 style="width:<?php echo (int)($p['progress'] ?? 0); ?>%;"></div>
                        </div>
                    </div>
                    <?php if ($p['deadline']): ?>
                    <div class="small <?php echo $isOverdue ? 'text-danger fw-semibold' : 'text-muted'; ?>">
                        <i class="bi <?php echo $isOverdue ? 'bi-calendar-x' : 'bi-calendar'; ?> me-1"></i>
                        <?php echo $isOverdue ? 'Overdue: ' : 'Due: '; ?><?php echo date('M j, Y', strtotime($p['deadline'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="/developer/project_view.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                        View Details
                    </a>
                    <a href="/developer/deadline_request.php?project_id=<?php echo (int)$p['id']; ?>"
                       class="btn btn-sm btn-outline-warning" title="Request deadline extension">
                        <i class="bi bi-calendar-x"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
