<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

try {
    $services_count   = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
} catch(Exception $e) { $services_count = 0; }
try {
    $portfolio_count  = $pdo->query("SELECT COUNT(*) FROM portfolio")->fetchColumn();
} catch(Exception $e) { $portfolio_count = 0; }
try {
    $team_count       = $pdo->query("SELECT COUNT(*) FROM team")->fetchColumn();
} catch(Exception $e) { $team_count = 0; }
try {
    $unread_msgs      = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
} catch(Exception $e) { $unread_msgs = 0; }
try {
    $recent_messages  = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch(Exception $e) { $recent_messages = []; }

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard</h1>
    <p>Welcome back, <?php echo h($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
</div>
<div class="container-fluid">
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stats-card" style="background: linear-gradient(135deg,#667eea,#764ba2);">
                <i class="bi bi-grid icon"></i>
                <h3><?php echo (int)$services_count; ?></h3>
                <p>Services</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stats-card" style="background: linear-gradient(135deg,#f093fb,#f5576c);">
                <i class="bi bi-images icon"></i>
                <h3><?php echo (int)$portfolio_count; ?></h3>
                <p>Portfolio Items</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stats-card" style="background: linear-gradient(135deg,#4facfe,#00f2fe);">
                <i class="bi bi-people icon"></i>
                <h3><?php echo (int)$team_count; ?></h3>
                <p>Team Members</p>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stats-card" style="background: linear-gradient(135deg,#43e97b,#38f9d7);">
                <i class="bi bi-envelope icon"></i>
                <h3><?php echo (int)$unread_msgs; ?></h3>
                <p>Unread Messages</p>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-lightning-charge me-2"></i>Quick Actions
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="services.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-grid me-1"></i>Manage Services</a>
                        <a href="portfolio.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-images me-1"></i>Manage Portfolio</a>
                        <a href="team.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>Manage Team</a>
                        <a href="messages.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-envelope me-1"></i>View Messages <?php if($unread_msgs > 0): ?><span class="badge bg-danger"><?php echo (int)$unread_msgs; ?></span><?php endif; ?></a>
                        <a href="settings.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>Site Settings</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Messages -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-envelope me-2"></i>Recent Messages</span>
            <a href="messages.php" class="btn btn-sm btn-outline-light">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_messages as $msg): ?>
                        <tr class="<?php echo !$msg['is_read'] ? 'unread-row' : ''; ?>">
                            <td><?php echo h($msg['name']); ?></td>
                            <td><?php echo h($msg['email']); ?></td>
                            <td><?php echo h($msg['subject'] ?? '(no subject)'); ?></td>
                            <td><?php echo h($msg['created_at']); ?></td>
                            <td>
                                <?php if (!$msg['is_read']): ?>
                                <span class="badge bg-danger">Unread</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Read</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_messages)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No messages yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
