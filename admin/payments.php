<?php
/**
 * Admin Payments Management
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
update_online_status($pdo, $_SESSION['user_id']);

$payments = $pdo->query("SELECT p.*, i.invoice_number, u.name as client_name FROM payments p JOIN invoices i ON p.invoice_id=i.id JOIN users u ON p.client_id=u.id ORDER BY p.created_at DESC")->fetchAll();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'")->fetchColumn();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Payments — SoftandPix Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_admin.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Payments</h5></div>
<div class="topbar-right"><span class="badge bg-success fs-6">Total Revenue: <?= format_currency($total_revenue) ?></span></div></div>
<div class="main-content">
<div class="table-card"><div class="card-header">Payment History (<?= count($payments) ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Gateway</th><th>Transaction ID</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php if(empty($payments)):?><tr><td colspan="7" class="text-center text-muted py-4">No payments yet</td></tr>
<?php else: foreach($payments as $p):?>
<tr><td><?= e($p['invoice_number']) ?></td><td><?= e($p['client_name']) ?></td><td><?= format_currency($p['amount']) ?></td>
<td><span class="badge bg-info"><?= ucfirst(e($p['gateway'])) ?></span></td><td><code><?= e($p['transaction_id'] ?? '—') ?></code></td>
<td><span class="badge bg-<?= $p['status']==='completed'?'success':($p['status']==='failed'?'danger':'warning') ?>"><?= ucfirst($p['status']) ?></span></td>
<td><?= time_ago($p['created_at']) ?></td></tr>
<?php endforeach; endif;?></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
