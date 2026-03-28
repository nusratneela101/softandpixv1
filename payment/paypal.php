<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();
$invoiceId=(int)($_GET['invoice']??0);
if(!$invoiceId){header('Location: /client/invoices.php');exit;}
try{
    $stmt=$pdo->prepare("SELECT i.*,u.name as client_name,u.email as client_email FROM invoices i LEFT JOIN users u ON u.id=i.client_id WHERE i.id=?");
    $stmt->execute([$invoiceId]);$invoice=$stmt->fetch();
    if($_SESSION['user_role']!=='admin'&&$invoice['client_id']!=$_SESSION['user_id']){header('Location: /client/invoices.php');exit;}
    $paypalClientId=getSetting($pdo,'paypal_client_id');
}catch(Exception $e){die('Error loading invoice.');}
if(!$invoice||in_array($invoice['status'],['paid','cancelled'])){flashMessage('error','Invoice not available.');header('Location: /client/invoices.php');exit;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>PayPal Payment - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f0f2f5;}</style></head><body>
<div class="container py-5" style="max-width:550px;">
  <div class="card shadow border-0" style="border-radius:16px;">
    <div class="card-header bg-primary text-white text-center py-4"><i class="bi bi-paypal fs-2"></i><h5 class="mb-0 mt-2">Pay with PayPal</h5></div>
    <div class="card-body p-4">
      <table class="table table-sm mb-4">
        <tr><th>Invoice</th><td class="text-end"><?php echo h($invoice['invoice_number']);?></td></tr>
        <tr><th>Amount Due</th><td class="text-end fw-bold text-success"><?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?></td></tr>
        <tr><th>Due Date</th><td class="text-end"><?php echo $invoice['due_date']?date('M j, Y',strtotime($invoice['due_date'])):'—';?></td></tr>
      </table>
      <?php if(empty($paypalClientId)):?>
      <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>PayPal is not configured. Contact admin.</div>
      <?php else:?><div id="paypal-button-container"></div><?php endif;?>
      <a href="/client/invoice_view.php?id=<?php echo $invoiceId;?>" class="btn btn-outline-secondary w-100 mt-3">← Back to Invoice</a>
    </div>
  </div>
</div>
<?php if(!empty($paypalClientId)):?>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo h($paypalClientId);?>&currency=<?php echo h($invoice['currency']);?>"></script>
<script>
paypal.Buttons({
  createOrder:(d,a)=>a.order.create({purchase_units:[{amount:{value:'<?php echo number_format($invoice['total'],2,'.','')?>'}, description:'Invoice <?php echo h($invoice['invoice_number'])?>'}]}),
  onApprove:(d,a)=>a.order.capture().then(()=>window.location.href='/payment/paypal_return.php?invoice=<?php echo $invoiceId?>&order_id='+d.orderID),
  onError:()=>window.location.href='/payment/failed.php?invoice=<?php echo $invoiceId?>'
}).render('#paypal-button-container');
</script>
<?php endif;?>
</body></html>
