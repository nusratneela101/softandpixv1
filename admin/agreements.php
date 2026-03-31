<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

// Ensure agreements table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS agreements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        project_name VARCHAR(255) DEFAULT NULL,
        total_budget DECIMAL(12,2) DEFAULT 0,
        deadline DATE DEFAULT NULL,
        terms TEXT,
        status ENUM('draft','pending','approved','rejected','signed') DEFAULT 'pending',
        signed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Table already exists or DB error — continue
}

$agreements = $pdo->query("SELECT a.*, u.name as client_name FROM agreements a JOIN users u ON a.client_id=u.id ORDER BY a.created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-file-earmark-text me-2"></i>Agreements</h1>
        <p>All client agreements</p>
    </div>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">All Agreements (<?= count($agreements) ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>Project</th><th>Client</th><th>Budget</th><th>Deadline</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agreements)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No agreements. Send one from Chat.</td></tr>
                        <?php else: foreach ($agreements as $a): ?>
                        <tr>
                            <td><strong><?= h($a['project_name']) ?></strong></td>
                            <td><?= h($a['client_name']) ?></td>
                            <td>$<?= number_format((float)$a['total_budget'], 2) ?></td>
                            <td><?= $a['deadline'] ? date('M j, Y', strtotime($a['deadline'])) : '—' ?></td>
                            <td><span class="badge bg-<?= $a['status'] === 'approved' ? 'success' : ($a['status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= ucfirst($a['status']) ?></span></td>
                            <td><?= date('M j, Y H:i', strtotime($a['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
