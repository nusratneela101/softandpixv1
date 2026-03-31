<?php
/**
 * Admin Payments Management
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

try {
    $payments = $pdo->query("SELECT p.*, i.invoice_number, u.name as client_name FROM payments p JOIN invoices i ON p.invoice_id=i.id JOIN users u ON p.client_id=u.id ORDER BY p.created_at DESC")->fetchAll();
    $total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn();
} catch (Exception $e) {
    $payments = [];
    $total_revenue = 0.0;
}

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-credit-card me-2"></i>Payments</h1></div>
    <span class="badge bg-success fs-6">Total Revenue: $<?php echo number_format($total_revenue, 2); ?></span>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-header">Payment History (<?php echo count($payments); ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th class="text-end">Amount</th>
                            <th>Gateway</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No payments yet</td></tr>
                        <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?php echo h($p['invoice_number']); ?></td>
                            <td><?php echo h($p['client_name']); ?></td>
                            <td class="text-end fw-semibold">$<?php echo number_format((float)$p['amount'], 2); ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst(h($p['gateway'])); ?></span></td>
                            <td><code><?php echo h($p['transaction_id'] ?? '—'); ?></code></td>
                            <td><span class="badge bg-<?php echo $p['status'] === 'completed' ? 'success' : ($p['status'] === 'failed' ? 'danger' : 'warning'); ?>"><?php echo ucfirst(h($p['status'])); ?></span></td>
                            <td class="small"><?php echo $p['created_at'] ? date('M j, Y', strtotime($p['created_at'])) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
