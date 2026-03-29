<?php
/**
 * Square Payment Gateway
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$payment = $pdo->prepare("SELECT p.*, i.invoice_number, i.total FROM payments p JOIN invoices i ON p.invoice_id=i.id WHERE p.id=? AND p.client_id=?");
$payment->execute([$payment_id, $_SESSION['user_id']]); $payment = $payment->fetch();
if (!$payment) { redirect(BASE_URL . '/client/invoices.php'); }

$gw = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_name='square'"); $gw->execute(); $gw = $gw->fetch();
$csrf = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    // In production, this would use Square Web Payments SDK
    // For now, mark as completed (placeholder)
    $tx_id = 'SQ-' . time() . '-' . mt_rand(1000, 9999);
    $pdo->prepare("UPDATE payments SET status='completed', transaction_id=? WHERE id=?")->execute([$tx_id, $payment_id]);
    $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW() WHERE id=?")->execute([$payment['invoice_id']]);
    
    // Send receipt via info@
    $client = get_user($pdo, $_SESSION['user_id']);
    send_email($client['email'], $client['name'], 'Payment Receipt - ' . $payment['invoice_number'], '<h3>Payment Received</h3><p>Amount: $' . number_format($payment['total'], 2) . '</p><p>Transaction: ' . $tx_id . '</p>', 'info');
    
    // Notify admin
    $admin = $pdo->query("SELECT * FROM users WHERE role='admin' LIMIT 1")->fetch();
    if ($admin) {
        create_notification($pdo, $admin['id'], 'payment', 'Payment Received', '$' . number_format($payment['total'], 2) . ' for ' . $payment['invoice_number'], '/admin/payments.php');
        send_email($admin['email'], $admin['name'], 'Payment Received - ' . $payment['invoice_number'], '<h3>Payment Received</h3><p>Client: ' . htmlspecialchars($client['name']) . '</p><p>Amount: $' . number_format($payment['total'], 2) . '</p>', 'info');
    }
    
    set_flash('success', 'Payment successful! Transaction ID: ' . $tx_id);
    redirect(BASE_URL . '/invoice/view.php?id=' . $payment['invoice_id']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pay with Square — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-5"><div class="card mx-auto" style="max-width:500px">
<div class="card-body p-4">
<h4 class="text-center mb-4"><i class="fas fa-square fa-2x d-block mb-2 text-primary"></i>Pay with Square</h4>
<div class="alert alert-info"><strong>Invoice:</strong> <?= e($payment['invoice_number']) ?><br><strong>Amount:</strong> $<?= number_format($payment['total'], 2) ?></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<div class="mb-3"><label class="form-label">Card Number</label><input type="text" class="form-control" placeholder="4111 1111 1111 1111" required></div>
<div class="row g-2 mb-3"><div class="col"><input type="text" class="form-control" placeholder="MM/YY" required></div>
<div class="col"><input type="text" class="form-control" placeholder="CVV" required></div></div>
<button type="submit" class="btn btn-primary w-100 py-2"><i class="fas fa-lock me-1"></i>Pay $<?= number_format($payment['total'], 2) ?></button>
</form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
