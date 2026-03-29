<?php
/**
 * View Invoice
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(BASE_URL); }

$invoice = $pdo->prepare("SELECT i.*, u.name as client_name, u.email as client_email, u.address as client_address FROM invoices i JOIN users u ON i.client_id=u.id WHERE i.id=?");
$invoice->execute([$id]); $invoice = $invoice->fetch();

if (!$invoice) { redirect(BASE_URL); }

// Check access
if ($_SESSION['user_role'] === 'client' && $invoice['client_id'] !== $_SESSION['user_id']) { redirect(BASE_URL); }
if ($_SESSION['user_role'] === 'developer') { redirect(BASE_URL); }

$items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
$items->execute([$id]); $items = $items->fetchAll();

// Get active payment gateways for Pay Now
$gateways = $pdo->query("SELECT * FROM payment_gateways WHERE is_active=1")->fetchAll();
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= e($invoice['invoice_number']) ?> — SoftandPix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .invoice-box { background: white; max-width: 800px; margin: 30px auto; padding: 40px; border-radius: 10px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .invoice-header { border-bottom: 2px solid #667eea; padding-bottom: 20px; margin-bottom: 20px; }
        .badge-paid { background: #28a745; } .badge-pending { background: #ffc107; color: #000; } .badge-overdue { background: #dc3545; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="invoice-box">
    <div class="no-print mb-3">
        <a href="<?= e(BASE_URL) ?>/<?= $_SESSION['user_role'] ?>/invoices.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <button onclick="window.print()" class="btn btn-outline-primary btn-sm ms-2"><i class="fas fa-print me-1"></i>Print</button>
        <a href="<?= e(BASE_URL) ?>/invoice/pdf.php?id=<?= $id ?>&print=1" target="_blank" class="btn btn-outline-success btn-sm ms-2"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
    </div>
    
    <div class="invoice-header">
        <div class="row">
            <div class="col-6">
                <h2 style="color:#667eea;font-weight:800">SoftandPix</h2>
                <small class="text-muted">Project Management System</small>
            </div>
            <div class="col-6 text-end">
                <h3>INVOICE</h3>
                <p class="mb-0"><strong><?= e($invoice['invoice_number']) ?></strong></p>
                <p class="mb-0 small text-muted">Date: <?= date('M j, Y', strtotime($invoice['created_at'])) ?></p>
                <span class="badge badge-<?= $invoice['status'] ?>"><?= ucfirst($invoice['status']) ?></span>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-6">
            <h6>Bill To:</h6>
            <strong><?= e($invoice['client_name']) ?></strong><br>
            <span class="text-muted"><?= e($invoice['client_email']) ?></span><br>
            <?php if($invoice['client_address']):?><small><?= e($invoice['client_address']) ?></small><?php endif;?>
        </div>
        <div class="col-6 text-end">
            <?php if($invoice['due_date']):?><p><strong>Due Date:</strong> <?= date('M j, Y', strtotime($invoice['due_date'])) ?></p><?php endif;?>
        </div>
    </div>
    
    <table class="table">
        <thead class="table-light"><tr><th>Description</th><th class="text-center">Qty</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        <?php foreach($items as $item):?>
        <tr><td><?= e($item['description']) ?></td><td class="text-center"><?= (int)$item['quantity'] ?></td>
        <td class="text-end"><?= format_currency($item['rate']) ?></td><td class="text-end"><?= format_currency($item['amount']) ?></td></tr>
        <?php endforeach;?>
        </tbody>
        <tfoot>
            <tr><td colspan="3" class="text-end"><strong>Subtotal:</strong></td><td class="text-end"><?= format_currency($invoice['subtotal']) ?></td></tr>
            <?php if($invoice['tax_percent'] > 0):?><tr><td colspan="3" class="text-end">Tax (<?= $invoice['tax_percent'] ?>%):</td><td class="text-end"><?= format_currency($invoice['tax_amount']) ?></td></tr><?php endif;?>
            <?php if($invoice['discount'] > 0):?><tr><td colspan="3" class="text-end">Discount:</td><td class="text-end">-<?= format_currency($invoice['discount']) ?></td></tr><?php endif;?>
            <tr><td colspan="3" class="text-end"><h5>Total:</h5></td><td class="text-end"><h5><?= format_currency($invoice['total']) ?></h5></td></tr>
        </tfoot>
    </table>
    
    <?php if($invoice['notes']):?><div class="mt-3 p-3 bg-light rounded"><strong>Notes:</strong><br><?= e($invoice['notes']) ?></div><?php endif;?>
    
    <?php if($_SESSION['user_role'] === 'client' && $invoice['status'] === 'pending' && !empty($gateways)):?>
    <div class="mt-4 no-print">
        <h5><i class="fas fa-credit-card me-2"></i>Pay Now</h5>
        <div class="row g-3">
            <?php foreach($gateways as $gw):?>
            <div class="col-md-4">
                <form method="POST" action="<?= e(BASE_URL) ?>/invoice/pay.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                    <input type="hidden" name="gateway" value="<?= e($gw['gateway_name']) ?>">
                    <button type="submit" class="btn btn-outline-primary w-100 py-3">
                        <i class="fas fa-<?= $gw['gateway_name'] === 'paypal' ? 'paypal' : ($gw['gateway_name'] === 'stripe' ? 'stripe-s' : 'square') ?> fa-2x d-block mb-2"></i>
                        Pay with <?= ucfirst(e($gw['gateway_name'])) ?>
                    </button>
                </form>
            </div>
            <?php endforeach;?>
        </div>
    </div>
    <?php endif;?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
