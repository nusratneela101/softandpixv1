<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();

$userId = $_SESSION['user_id'];
$flash  = getFlashMessage();

try {
    $projects = $pdo->prepare("SELECT * FROM projects WHERE client_id=? AND status != 'cancelled' ORDER BY created_at DESC");
    $projects->execute([$userId]);
    $projects = $projects->fetchAll();

    $totalProjects  = count($projects);
    $activeProjects = count(array_filter($projects, fn($p) => $p['status'] === 'in_progress'));

    // Invoices
    $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE client_id=? ORDER BY created_at DESC LIMIT 5");
    $invStmt->execute([$userId]);
    $recentInvoices = $invStmt->fetchAll();

    $pendingInvoices = 0;
    $totalPaid       = 0;
    try {
        $pi = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE client_id=? AND status IN ('sent','overdue','partial')");
        $pi->execute([$userId]);
        $pendingInvoices = (int)$pi->fetchColumn();

        $tp = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE client_id=? AND status='paid'");
        $tp->execute([$userId]);
        $totalPaid = (float)$tp->fetchColumn();
    } catch (Exception $e) {}

    // Unread notifications
    $unreadNotifs = 0;
    try {
        $un = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $un->execute([$userId]);
        $unreadNotifs = (int)$un->fetchColumn();
    } catch (Exception $e) {}

    // Unread messages
    $unreadMsgs = 0;
    try {
        $um = $pdo->prepare("SELECT COUNT(*) FROM chat_messages cm
            INNER JOIN chat_room_members crm ON crm.room_id=cm.room_id AND crm.user_id=?
            WHERE cm.is_read=0 AND cm.sender_id!=?");
        $um->execute([$userId, $userId]);
        $unreadMsgs = (int)$um->fetchColumn();
    } catch (Exception $e) {}

} catch (Exception $e) {
    $projects=[]; $totalProjects=0; $activeProjects=0;
    $recentInvoices=[]; $pendingInvoices=0; $totalPaid=0; $unreadNotifs=0; $unreadMsgs=0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.sidebar { background: linear-gradient(180deg,#1e3a5f,#2563eb); width:240px; min-height:100vh; position:fixed; top:0; left:0; z-index:100; }
.sidebar .brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:10px 20px; display:flex; align-items:center; gap:10px; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background:rgba(255,255,255,.15); color:#fff; border-radius:8px; margin:2px 8px; padding:10px 12px; }
.main-content { margin-left:240px; padding:24px; }
.stat-card { border-radius:12px; padding:24px; color:#fff; position:relative; overflow:hidden; }
.stat-card .icon { position:absolute; right:20px; top:20px; font-size:2.5rem; opacity:.3; }
.project-card { border-radius:12px; border:1px solid #e2e8f0; transition:transform .2s; background:#fff; }
.project-card:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(0,0,0,.1); }
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
        <span class="badge ms-1" style="background:rgba(255,255,255,.2);">Client</span>
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link active" href="/client/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/client/invoices.php">
            <i class="bi bi-receipt"></i> Invoices
            <?php if ($pendingInvoices > 0): ?>
            <span class="badge bg-danger ms-auto"><?php echo $pendingInvoices; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link" href="/client/chat.php">
            <i class="bi bi-chat-dots"></i> Chat
            <?php if ($unreadMsgs > 0): ?>
            <span class="badge bg-danger ms-auto"><?php echo $unreadMsgs; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link" href="/client/notifications.php">
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
    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?><button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="mb-4">
        <h4 class="fw-bold mb-0">Welcome, <?php echo h($_SESSION['user_name'] ?? 'Client'); ?>! 👋</h4>
        <p class="text-muted mb-0"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                <i class="bi bi-kanban icon"></i>
                <h3 class="mb-0"><?php echo $totalProjects; ?></h3>
                <p class="mb-0 small">Total Projects</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669);">
                <i class="bi bi-arrow-repeat icon"></i>
                <h3 class="mb-0"><?php echo $activeProjects; ?></h3>
                <p class="mb-0 small">Active Projects</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <i class="bi bi-receipt icon"></i>
                <h3 class="mb-0"><?php echo $pendingInvoices; ?></h3>
                <p class="mb-0 small">Pending Invoices</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
                <i class="bi bi-currency-dollar icon"></i>
                <h3 class="mb-0">$<?php echo number_format($totalPaid, 0); ?></h3>
                <p class="mb-0 small">Total Paid</p>
            </div>
        </div>
    </div>

    <!-- Projects -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">My Projects</h5>
    </div>
    <?php if (empty($projects)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-folder" style="font-size:3rem;opacity:.4;"></i>
        <p class="mt-2">No projects yet. Contact us to get started!</p>
    </div>
    <?php else: ?>
    <div class="row g-3 mb-4">
        <?php foreach ($projects as $p):
            $statusColor = ['pending'=>'warning','in_progress'=>'primary','on_hold'=>'secondary','completed'=>'success','cancelled'=>'danger'][$p['status']] ?? 'secondary';
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card project-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="fw-bold mb-0"><?php echo h($p['title']); ?></h6>
                        <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucwords(str_replace('_',' ',$p['status'])); ?></span>
                    </div>
                    <?php if (!empty($p['description'])): ?>
                    <p class="text-muted small mb-2"><?php echo h(substr($p['description'],0,80)); ?><?php echo strlen($p['description'])>80?'…':''; ?></p>
                    <?php endif; ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Progress</small>
                            <small class="fw-semibold"><?php echo (int)($p['progress']??0); ?>%</small>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar bg-<?php echo $statusColor; ?>" style="width:<?php echo (int)($p['progress']??0); ?>%;"></div>
                        </div>
                    </div>
                    <?php if ($p['deadline']): ?>
                    <div class="small text-muted"><i class="bi bi-calendar me-1"></i>Due: <?php echo date('M j, Y',strtotime($p['deadline'])); ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/client/project_view.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary w-100">View Project</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent Invoices -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Recent Invoices</h5>
        <a href="/client/invoices.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <?php if (empty($recentInvoices)): ?>
    <div class="text-center py-4 text-muted">
        <i class="bi bi-receipt" style="font-size:2rem;opacity:.4;"></i>
        <p class="mt-2 small">No invoices yet.</p>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentInvoices as $inv):
                    $invStatusColor = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','partial'=>'warning','cancelled'=>'dark'][$inv['status']] ?? 'secondary';
                ?>
                <tr>
                    <td class="fw-semibold"><?php echo h($inv['invoice_number']); ?></td>
                    <td><?php echo h($inv['currency']); ?> <?php echo number_format($inv['total'],2); ?></td>
                    <td><?php echo $inv['due_date'] ? date('M j, Y',strtotime($inv['due_date'])) : '—'; ?></td>
                    <td><span class="badge bg-<?php echo $invStatusColor; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                    <td>
                        <?php if (in_array($inv['status'],['sent','overdue','partial'])): ?>
                        <a href="/client/invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-success">Pay Now</a>
                        <?php else: ?>
                        <a href="/client/invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
