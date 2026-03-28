<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once 'includes/auth.php';
requireClient();

$userId    = $_SESSION['user_id'];
$invoiceId = (int)($_GET['id'] ?? 0);
$flash     = getFlashMessage();

if (!$invoiceId) { header('Location: /client/invoices.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT i.*, p.title AS project_title FROM invoices i
        LEFT JOIN projects p ON p.id = i.project_id
        WHERE i.id=? AND (i.client_id=? OR ? = 'admin')");
    $stmt->execute([$invoiceId, $userId, $_SESSION['user_role']]);
    $invoice = $stmt->fetch();
} catch (Exception $e) { $invoice = null; }

if (!$invoice) { header('Location: /client/invoices.php'); exit; }

// Invoice items
$items = [];
try {
    $ist = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC");
    $ist->execute([$invoiceId]);
    $items = $ist->fetchAll();
} catch (Exception $e) {}

// Payment methods configured
$paypalClientId  = getSetting($pdo, 'paypal_client_id');
$stripePublicKey = getSetting($pdo, 'stripe_public_key');
$squareAppId     = getSetting($pdo, 'square_app_id');

$canPay = in_array($invoice['status'], ['sent', 'overdue', 'partial']);
$statusColor = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','overdue'=>'danger','partial'=>'warning','cancelled'=>'dark'][$invoice['status']] ?? 'secondary';

// Load payment history
$payments = [];
try {
    $ps = $pdo->prepare("SELECT * FROM invoice_payments WHERE invoice_id=? ORDER BY created_at ASC");
    $ps->execute([$invoiceId]);
    $payments = $ps->fetchAll();
} catch (Exception $e) {}

$amountPaid = (float)($invoice['amount_paid'] ?? array_sum(array_column($payments, 'amount')));
$amountDue  = max(0, (float)$invoice['total'] - $amountPaid);

// Unread notifications
$unreadNotifs = 0;
try {
    $un = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $un->execute([$userId]); $unreadNotifs = (int)$un->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?php echo h($invoice['invoice_number']); ?> - Client Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8fafc; }
.sidebar { background:linear-gradient(180deg,#1e3a5f,#2563eb); width:240px; min-height:100vh; position:fixed; top:0; left:0; z-index:100; }
.sidebar .brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:10px 20px; display:flex; align-items:center; gap:10px; }
.sidebar .nav-link:hover { background:rgba(255,255,255,.15); color:#fff; border-radius:8px; margin:2px 8px; padding:10px 12px; }
.main-content { margin-left:240px; padding:24px; }
.invoice-card { border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
@media print {
    .sidebar, .no-print { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
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
        <a class="nav-link" href="/client/notifications.php">
            <i class="bi bi-bell"></i> Notifications
            <?php if ($unreadNotifs > 0): ?><span class="badge bg-warning text-dark ms-auto"><?php echo $unreadNotifs; ?></span><?php endif; ?>
        </a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show no-print">
        <?php echo h($flash['message']); ?><button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/client/invoices.php">Invoices</a></li>
                <li class="breadcrumb-item active"><?php echo h($invoice['invoice_number']); ?></li>
            </ol>
        </nav>
        <a href="/api/invoice_pdf.php?id=<?php echo $invoiceId; ?>" target="_blank" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>

    <div class="row g-4">
        <!-- Invoice Document -->
        <div class="col-lg-8">
            <div class="invoice-card p-4 p-md-5">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:45px;" class="mb-3">
                        <div class="text-muted small">
                            Softandpix<br>
                            <?php echo h(getSetting($pdo, 'company_address', '')); ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <h3 class="fw-bold text-primary mb-1">INVOICE</h3>
                        <div class="fw-semibold"><?php echo h($invoice['invoice_number']); ?></div>
                        <span class="badge bg-<?php echo $statusColor; ?> mt-1"><?php echo ucfirst($invoice['status']); ?></span>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Bill To</div>
                        <div class="fw-semibold"><?php echo h($_SESSION['user_name']); ?></div>
                        <div class="text-muted small"><?php echo h($_SESSION['user_email'] ?? ''); ?></div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="mb-1"><span class="text-muted small">Issue Date:</span>
                            <span class="fw-semibold ms-1"><?php echo $invoice['issue_date'] ? date('M j, Y',strtotime($invoice['issue_date'])) : '—'; ?></span>
                        </div>
                        <div class="mb-1"><span class="text-muted small">Due Date:</span>
                            <span class="fw-semibold ms-1 <?php echo $invoice['status']==='overdue'?'text-danger':''; ?>">
                                <?php echo $invoice['due_date'] ? date('M j, Y',strtotime($invoice['due_date'])) : '—'; ?>
                            </span>
                        </div>
                        <?php if (!empty($invoice['project_title'])): ?>
                        <div><span class="text-muted small">Project:</span>
                            <span class="fw-semibold ms-1"><?php echo h($invoice['project_title']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th class="text-center" style="width:80px;">Qty</th>
                                <th class="text-end" style="width:120px;">Rate</th>
                                <th class="text-end" style="width:120px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo h($item['description']); ?></td>
                                <td class="text-center"><?php echo (float)$item['quantity']; ?></td>
                                <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format($item['unit_price'],2); ?></td>
                                <td class="text-end fw-semibold"><?php echo h($invoice['currency']); ?> <?php echo number_format($item['quantity']*$item['unit_price'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">Services rendered</td></tr>
                        <?php endif; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <?php if (!empty($invoice['subtotal'])): ?>
                            <tr>
                                <td colspan="3" class="text-end text-muted">Subtotal</td>
                                <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format($invoice['subtotal'],2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($invoice['tax_amount'])): ?>
                            <tr>
                                <td colspan="3" class="text-end text-muted">Tax (<?php echo h($invoice['tax_rate']??''); ?>%)</td>
                                <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format($invoice['tax_amount'],2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($invoice['discount_amount'])): ?>
                            <tr>
                                <td colspan="3" class="text-end text-muted">Discount</td>
                                <td class="text-end text-success">-<?php echo h($invoice['currency']); ?> <?php echo number_format($invoice['discount_amount'],2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" class="text-end fw-bold fs-5">Total</td>
                                <td class="text-end fw-bold fs-5 text-primary"><?php echo h($invoice['currency']); ?> <?php echo number_format($invoice['total'],2); ?></td>
                            </tr>
                            <?php if ($amountPaid > 0): ?>
                            <tr class="text-success">
                                <td colspan="3" class="text-end">Amount Paid</td>
                                <td class="text-end fw-semibold">− <?php echo h($invoice['currency']); ?> <?php echo number_format($amountPaid,2); ?></td>
                            </tr>
                            <tr class="fw-bold">
                                <td colspan="3" class="text-end">Balance Due</td>
                                <td class="text-end <?php echo $amountDue > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue,2); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($invoice['notes'])): ?>
                <div class="border-top pt-3">
                    <div class="text-muted small fw-semibold mb-1">Notes</div>
                    <p class="text-muted small mb-0"><?php echo nl2br(h($invoice['notes'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Panel -->
        <div class="col-lg-4 no-print">
            <?php if ($invoice['status'] === 'paid'): ?>
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-body text-center py-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
                    <h5 class="mt-3 fw-bold text-success">Paid</h5>
                    <p class="text-muted">This invoice has been paid in full.</p>
                    <?php if ($invoice['paid_at']): ?>
                    <div class="small text-muted">Paid on <?php echo date('M j, Y',strtotime($invoice['paid_at'])); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($canPay): ?>
            <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                <div class="card-header fw-bold text-center py-3 <?php echo $invoice['status']==='partial'?'bg-warning text-dark':'bg-primary text-white'; ?>" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-credit-card me-2"></i>
                    <?php echo $invoice['status']==='partial' ? 'Partially Paid' : 'Pay Invoice'; ?>
                </div>
                <div class="card-body p-3">
                    <div class="text-center mb-3">
                        <?php if ($invoice['status'] === 'partial'): ?>
                        <div class="text-muted small">Total</div>
                        <div class="fw-semibold text-muted"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['total'],2); ?></div>
                        <div class="text-muted small mt-1">Amount Paid</div>
                        <div class="fw-semibold text-success">− <?php echo h($invoice['currency']); ?> <?php echo number_format($amountPaid,2); ?></div>
                        <hr class="my-2">
                        <div class="text-muted small">Balance Due</div>
                        <div class="fw-bold fs-3 text-danger"><?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue,2); ?></div>
                        <?php else: ?>
                        <div class="text-muted small">Amount Due</div>
                        <div class="fw-bold fs-3 text-primary"><?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue,2); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-grid gap-2">
                        <?php if (!empty($paypalClientId)): ?>
                        <a href="/payment/paypal.php?invoice=<?php echo $invoiceId; ?>" class="btn btn-primary">
                            <i class="bi bi-paypal me-2"></i>Pay with PayPal
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($stripePublicKey)): ?>
                        <a href="/payment/stripe.php?invoice=<?php echo $invoiceId; ?>" class="btn" style="background:linear-gradient(135deg,#6772e5,#5469d4);color:#fff;">
                            <i class="bi bi-credit-card me-2"></i>Pay with Card (Stripe)
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($squareAppId)): ?>
                        <a href="/payment/square.php?invoice=<?php echo $invoiceId; ?>" class="btn btn-dark">
                            <i class="bi bi-square me-2"></i>Pay with Square
                        </a>
                        <?php endif; ?>
                        <?php if (empty($paypalClientId) && empty($stripePublicKey) && empty($squareAppId)): ?>
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            No payment methods configured. Please contact us directly.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                <div class="card-body text-center py-4">
                    <span class="badge bg-<?php echo $statusColor; ?> fs-6"><?php echo ucfirst($invoice['status']); ?></span>
                    <p class="text-muted mt-2 small">This invoice is not available for payment.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($payments)): ?>
            <!-- Payment History -->
            <div class="card border-0 shadow-sm" style="border-radius:12px;">
                <div class="card-header fw-semibold" style="border-radius:12px 12px 0 0;">
                    <i class="bi bi-clock-history me-2"></i>Payment History
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($payments as $pay): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold small text-success"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$pay['amount'],2); ?></span>
                            <span class="text-muted small"><?php echo $pay['created_at'] ? date('M j, Y',strtotime($pay['created_at'])) : '—'; ?></span>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            <?php echo h(ucfirst(str_replace('_',' ',$pay['method'] ?? 'manual'))); ?>
                            <?php if (!empty($pay['notes'])): ?> — <?php echo h($pay['notes']); ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
