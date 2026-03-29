<?php
/**
 * PayPal Payment Gateway
 */
define('BASE_PATH', dirname(dirname(__DIR__)));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();

$payment_id = (int)($_GET['payment_id'] ?? 0);
$payment = $pdo->prepare("SELECT p.*, i.invoice_number, i.total FROM payments p JOIN invoices i ON p.invoice_id=i.id WHERE p.id=? AND p.client_id=?");
$payment->execute([$payment_id, $_SESSION['user_id']]); $payment = $payment->fetch();
if (!$payment) { redirect(BASE_URL . '/client/invoices.php'); }
$csrf = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $tx_id = 'PP-' . time() . '-' . mt_rand(1000, 9999);
    $pdo->prepare("UPDATE payments SET status='completed', transaction_id=? WHERE id=?")->execute([$tx_id, $payment_id]);
    $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW() WHERE id=?")->execute([$payment['invoice_id']]);
    $client = get_user($pdo, $_SESSION['user_id']);
    send_email($client['email'], $client['name'], 'Payment Receipt - ' . $payment['invoice_number'], '<h3>Payment Received</h3><p>Amount: $' . number_format($payment['total'], 2) . '</p><p>Transaction: ' . $tx_id . '</p>', 'info');
    $admin = $pdo->query("SELECT * FROM users WHERE role='admin' LIMIT 1")->fetch();
    if ($admin) { create_notification($pdo, $admin['id'], 'payment', 'Payment Received', '$' . number_format($payment['total'], 2), '/admin/payments.php'); }
    set_flash('success', 'Payment successful! Transaction ID: ' . $tx_id);
    redirect(BASE_URL . '/invoice/view.php?id=' . $payment['invoice_id']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pay with PayPal — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-5"><div class="card mx-auto" style="max-width:500px"><div class="card-body p-4">
<h4 class="text-center mb-4"><i class="fab fa-paypal fa-2x d-block mb-2" style="color:#003087"></i>Pay with PayPal</h4>
<div class="alert alert-info"><strong>Invoice:</strong> <?= e($payment['invoice_number']) ?><br><strong>Amount:</strong> $<?= number_format($payment['total'], 2) ?></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<div class="mb-3"><label class="form-label">PayPal Email</label><input type="email" class="form-control" placeholder="your@email.com" required></div>
<button type="submit" class="btn w-100 py-2" style="background:#ffc439;color:#003087;font-weight:bold"><i class="fab fa-paypal me-1"></i>Pay $<?= number_format($payment['total'], 2) ?></button>
</form></div></div></div></body></html>
