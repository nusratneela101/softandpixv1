<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();
$invoiceId=(int)($_GET['invoice']??0);
// Mark paid if not already (Stripe PI callback)
$pi=trim($_GET['pi']??'');
if($invoiceId&&$pi){
    try{
        $pdo->prepare("UPDATE invoices SET status='paid',paid_at=NOW() WHERE id=? AND status!='paid'")->execute([$invoiceId]);
        $pdo->prepare("INSERT IGNORE INTO invoice_payments (invoice_id,amount,method,transaction_id,status) SELECT id,total,'stripe',?,'completed' FROM invoices WHERE id=?")->execute([$pi,$invoiceId]);
    }catch(Exception $e){}
}
$invoice=null;
try{$s=$pdo->prepare("SELECT * FROM invoices WHERE id=?");$s->execute([$invoiceId]);$invoice=$s->fetch();}catch(Exception $e){}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Payment Successful - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f0f2f5;}</style></head><body>
<div class="container py-5 text-center" style="max-width:480px;">
  <div class="card shadow border-0 p-5" style="border-radius:16px;">
    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
    <h3 class="fw-bold mt-3 text-success">Payment Successful!</h3>
    <p class="text-muted">Thank you for your payment. Your invoice has been marked as paid.</p>
    <?php if($invoice):?>
    <div class="alert alert-light border">
      <strong><?php echo h($invoice['invoice_number']);?></strong><br>
      <span class="text-success fw-bold"><?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?></span>
    </div>
    <?php endif;?>
    <a href="/client/invoices.php" class="btn btn-primary w-100">View All Invoices</a>
    <a href="/client/" class="btn btn-outline-secondary w-100 mt-2">Back to Dashboard</a>
  </div>
</div>
</body></html>
