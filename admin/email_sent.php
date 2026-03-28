<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page-1)*$perPage;

try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM email_log")->fetchColumn();
    $logs  = $pdo->query("SELECT * FROM email_log ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
} catch (Exception $e) { $logs=[]; $total=0; }

$totalPages = (int)ceil($total/$perPage);
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-clock-history me-2"></i>Email Log</h1></div>
    <a href="email_dashboard.php" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send Email</a>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>To</th><th>Subject</th><th>Status</th><th>Sent At</th><th>Error</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo h($log['to_email']); ?></td>
                            <td><?php echo h($log['subject']); ?></td>
                            <td><span class="badge bg-<?php echo $log['status']==='sent'?'success':'danger'; ?>"><?php echo h($log['status']); ?></span></td>
                            <td><?php echo date('M j, Y H:i', strtotime($log['sent_at'])); ?></td>
                            <td class="text-danger small"><?php echo h($log['error_message'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No emails sent yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
