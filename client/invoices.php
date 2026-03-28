<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();

$userId = $_SESSION['user_id'];
$flash  = getFlashMessage();
$status = $_GET['status'] ?? 'all';

try {
    $validStatuses = ['draft','sent','paid','overdue','partial','cancelled'];
    if ($status !== 'all' && in_array($status, $validStatuses)) {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE client_id=? AND status=? ORDER BY created_at DESC");
        $stmt->execute([$userId, $status]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE client_id=? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    }
    $invoices = $stmt->fetchAll();
} catch (Exception $e) { $invoices = []; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoices - Client Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.sidebar { background:linear-gradient(180deg,#1e3a5f,#2563eb); width:240px; min-height:100vh; position:fixed; top:0; left:0; z-index:100; }
.sidebar .brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:10px 20px; display:flex; align-items:center; gap:10px; }
.sidebar .nav-link:hover, .sidebar .nav-link.active { background:rgba(255,255,255,.15); color:#fff; border-radius:8px; margin:2px 8px; padding:10px 12px; }
.main-content { margin-left:240px; padding:24px; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="brand">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="" style="max-height:35px;filter:brightness(10);">
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link" href="/client/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link active" href="/client/invoices.php"><i class="bi bi-receipt"></i> Invoices</a>
        <a class="nav-link" href="/client/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
        <a class="nav-link" href="/client/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?><button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>My Invoices</h4>
    </div>

    <!-- Filter tabs -->
    <div class="mb-3">
        <?php foreach (['all','sent','paid','overdue','partial','draft','cancelled'] as $tab): ?>
        <a href="?status=<?php echo $tab; ?>"
           class="btn btn-sm <?php echo $status === $tab ? 'btn-primary' : 'btn-outline-secondary'; ?> me-1 mb-1">
            <?php echo ucfirst($tab); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <?php if (empty($invoices)): ?>
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-receipt" style="font-size:3rem;opacity:.4;"></i>
            <p class="mt-2">No invoices found.</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Issue Date</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoices as $inv):
                    $sc = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','partial'=>'warning','cancelled'=>'dark'][$inv['status']] ?? 'secondary';
                    $isOverdue = $inv['status'] === 'overdue' || ($inv['due_date'] && strtotime($inv['due_date']) < time() && $inv['status'] === 'sent');
                ?>
                <tr class="<?php echo $isOverdue ? 'table-warning' : ''; ?>">
                    <td class="fw-semibold"><?php echo h($inv['invoice_number']); ?></td>
                    <td class="fw-bold"><?php echo h($inv['currency']); ?> <?php echo number_format($inv['total'],2); ?></td>
                    <td><?php echo $inv['issue_date'] ? date('M j, Y',strtotime($inv['issue_date'])) : '—'; ?></td>
                    <td><?php echo $inv['due_date'] ? date('M j, Y',strtotime($inv['due_date'])) : '—'; ?></td>
                    <td><span class="badge bg-<?php echo $sc; ?>"><?php echo ucfirst($inv['status']); ?></span></td>
                    <td>
                        <?php if (in_array($inv['status'],['sent','overdue','partial'])): ?>
                        <a href="/client/invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-credit-card me-1"></i>Pay Now
                        </a>
                        <?php else: ?>
                        <a href="/client/invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
