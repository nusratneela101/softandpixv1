<?php
session_start();
require_once '../includes/auth.php';
requireLogin();
$invoiceId=(int)($_GET['invoice']??0);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Failed - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f0f2f5;}</style></head><body>
<div class="container py-5 text-center" style="max-width:480px;">
  <div class="card shadow border-0 p-5" style="border-radius:16px;">
    <i class="bi bi-x-circle-fill text-danger" style="font-size:4rem;"></i>
    <h3 class="fw-bold mt-3 text-danger">Payment Failed</h3>
    <p class="text-muted">Your payment could not be processed. Please try again or use a different payment method.</p>
    <?php if($invoiceId):?>
    <a href="/client/invoice_view.php?id=<?php echo $invoiceId;?>" class="btn btn-primary w-100">Try Again</a>
    <?php endif;?>
    <a href="/client/invoices.php" class="btn btn-outline-secondary w-100 mt-2">View Invoices</a>
  </div>
</div>
</body></html>
