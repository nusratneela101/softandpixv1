<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: users.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT u.*, cr.role_label, cr.role_color FROM users u LEFT JOIN custom_roles cr ON cr.role_name = u.role WHERE u.id = ? AND (u._cf = 0 OR u._cf IS NULL)");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    $projectCountStmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? OR developer_id = ?");
    $projectCountStmt->execute([$id, $id]);
    $projectCount = (int)$projectCountStmt->fetchColumn();

    $invoiceCount = 0;
    $invoiceTotal = 0.0;
    try {
        $inv = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(total), 0) FROM invoices WHERE client_id = ?");
        $inv->execute([$id]);
        $row = $inv->fetch(PDO::FETCH_NUM);
        $invoiceCount = (int)$row[0];
        $invoiceTotal = (float)$row[1];
    } catch (Exception $e) {}

    // Recent projects
    $recentProjects = [];
    try {
        $rp = $pdo->prepare("SELECT p.id, p.title, p.status, p.progress, p.deadline FROM projects p WHERE p.client_id = ? OR p.developer_id = ? ORDER BY p.created_at DESC LIMIT 5");
        $rp->execute([$id, $id]);
        $recentProjects = $rp->fetchAll();
    } catch (Exception $e) {}

} catch (Exception $e) { $user = null; }

if (!$user) { flashMessage('error', 'User not found.'); header('Location: users.php'); exit; }

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-person-circle me-2"></i>User Profile</h1></div>
    <div>
        <a href="users_edit.php?id=<?php echo $id; ?>" class="btn btn-primary me-2"><i class="bi bi-pencil me-1"></i>Edit</a>
        <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
    <div class="row g-4">
        <!-- Profile Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <?php if (!empty($user['avatar'])): ?>
                <img src="../<?php echo h($user['avatar']); ?>" class="rounded-circle mx-auto d-block mb-3" style="width:100px;height:100px;object-fit:cover;" alt="">
                <?php else: ?>
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3 text-white fw-bold"
                     style="width:100px;height:100px;background:<?php echo h($user['role_color'] ?? '#6c757d'); ?>;font-size:2.5rem;">
                    <?php echo h(strtoupper(substr($user['name'] ?? '?', 0, 1))); ?>
                </div>
                <?php endif; ?>
                <h5 class="mb-1 fw-bold"><?php echo h($user['name']); ?></h5>
                <span class="badge mb-2" style="background:<?php echo h($user['role_color'] ?? '#6c757d'); ?>;"><?php echo h($user['role_label'] ?? $user['role']); ?></span>
                <p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i><?php echo h($user['email']); ?></p>
                <?php if (!empty($user['phone'])): ?>
                <p class="text-muted small mb-1"><i class="bi bi-phone me-1"></i><?php echo h($user['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($user['company'])): ?>
                <p class="text-muted small mb-1"><i class="bi bi-building me-1"></i><?php echo h($user['company']); ?></p>
                <?php endif; ?>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="fw-bold text-primary"><?php echo $projectCount; ?></div>
                        <div class="small text-muted">Projects</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold text-success">$<?php echo number_format($invoiceTotal, 0); ?></div>
                        <div class="small text-muted">Invoiced</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Details -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold text-muted mb-3 text-uppercase">User Details</h6>
                    <table class="table table-sm">
                        <tr>
                            <th style="width:180px;">Status</th>
                            <td>
                                <?php echo $user['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?>
                                <?php echo !empty($user['email_verified']) ? '<span class="badge bg-info ms-1">Email Verified</span>' : '<span class="badge bg-warning text-dark ms-1">Unverified</span>'; ?>
                            </td>
                        </tr>
                        <tr><th>Country</th><td><?php echo h($user['country'] ?? '—'); ?></td></tr>
                        <tr><th>Address</th><td><?php echo h($user['address'] ?? '—'); ?></td></tr>
                        <tr><th>Joined</th><td><?php echo h(date('F j, Y', strtotime($user['created_at']))); ?></td></tr>
                        <tr><th>Last Login</th><td><?php echo !empty($user['last_login']) ? h(date('F j, Y H:i', strtotime($user['last_login']))) : 'Never'; ?></td></tr>
                        <?php if (!empty($user['bio'])): ?>
                        <tr><th>Bio</th><td><?php echo h($user['bio']); ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($user['skills'])): ?>
                        <tr><th>Skills</th><td><?php echo h($user['skills']); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach (['portfolio_url' => 'Portfolio', 'github_url' => 'GitHub', 'linkedin_url' => 'LinkedIn', 'dribbble_url' => 'Dribbble', 'behance_url' => 'Behance'] as $field => $label): ?>
                        <?php if (!empty($user[$field])): ?>
                        <tr><th><?php echo h($label); ?></th><td><a href="<?php echo h($user[$field]); ?>" target="_blank" rel="noopener noreferrer"><?php echo h($user[$field]); ?></a></td></tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <!-- Recent Projects -->
            <?php if (!empty($recentProjects)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold">
                    <i class="bi bi-kanban me-2"></i>Recent Projects
                    <a href="projects.php" class="btn btn-sm btn-outline-secondary float-end">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Title</th><th>Status</th><th>Progress</th><th>Deadline</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProjects as $proj):
                                $sc = ['pending' => 'warning', 'in_progress' => 'primary', 'on_hold' => 'secondary', 'completed' => 'success', 'cancelled' => 'danger'][$proj['status']] ?? 'secondary';
                            ?>
                            <tr>
                                <td><a href="project_view.php?id=<?php echo (int)$proj['id']; ?>"><?php echo h($proj['title']); ?></a></td>
                                <td><span class="badge bg-<?php echo $sc; ?>"><?php echo h(ucwords(str_replace('_', ' ', $proj['status']))); ?></span></td>
                                <td>
                                    <div class="progress" style="height:6px;width:70px;display:inline-block;">
                                        <div class="progress-bar" style="width:<?php echo (int)$proj['progress']; ?>%"></div>
                                    </div>
                                    <small class="ms-1"><?php echo (int)$proj['progress']; ?>%</small>
                                </td>
                                <td><?php echo $proj['deadline'] ? h(date('M j, Y', strtotime($proj['deadline']))) : '—'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
