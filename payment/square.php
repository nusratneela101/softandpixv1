<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();
$invoiceId=(int)($_GET['invoice']??0);
if(!$invoiceId){header('Location: /client/invoices.php');exit;}
try{
    $stmt=$pdo->prepare("SELECT * FROM invoices WHERE id=?");$stmt->execute([$invoiceId]);$invoice=$stmt->fetch();
    if($_SESSION['user_role']!=='admin'&&$invoice['client_id']!=$_SESSION['user_id']){header('Location: /client/invoices.php');exit;}
    $squareAppId=getSetting($pdo,'square_app_id');
    $squareLocation=getSetting($pdo,'square_location_id');
}catch(Exception $e){die('Error.');}
if(!$invoice||$invoice['status']==='paid'){header('Location: /client/invoices.php');exit;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Square Payment - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f0f2f5;}#card-container{min-height:90px;}</style></head><body>
<div class="container py-5" style="max-width:550px;">
  <div class="card shadow border-0" style="border-radius:16px;">
    <div class="card-header bg-dark text-white text-center py-4">
      <i class="bi bi-square fs-2"></i><h5 class="mb-0 mt-2">Pay with Square</h5></div>
    <div class="card-body p-4">
      <table class="table table-sm mb-4">
        <tr><th>Invoice</th><td class="text-end"><?php echo h($invoice['invoice_number']);?></td></tr>
        <tr><th>Amount</th><td class="text-end fw-bold text-success"><?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?></td></tr>
      </table>
      <?php if(empty($squareAppId)):?>
      <div class="alert alert-warning">Square is not configured. Contact admin.</div>
      <?php else:?>
      <div id="card-container"></div>
      <div id="payment-status" class="text-danger small mt-1"></div>
      <button id="card-button" class="btn btn-dark w-100 mt-3 fw-bold py-2">
        <i class="bi bi-lock me-2"></i>Pay <?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?>
      </button>
      <?php endif;?>
      <a href="/client/invoice_view.php?id=<?php echo $invoiceId;?>" class="btn btn-outline-secondary w-100 mt-3">← Back</a>
    </div>
  </div>
</div>
<?php if(!empty($squareAppId)):?>
<script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
<script>
const appId='<?php echo h($squareAppId);?>';
const locationId='<?php echo h($squareLocation);?>';
async function initSquare(){
  const payments=Square.payments(appId,locationId);
  const card=await payments.card();
  await card.attach('#card-container');
  document.getElementById('card-button').addEventListener('click',async function(){
    this.disabled=true;this.textContent='Processing...';
    try{
      const result=await card.tokenize();
      if(result.status==='OK'){
        const fd=new FormData();fd.append('invoice_id',<?php echo $invoiceId;?>);fd.append('token',result.token);
        const resp=await fetch('/api/payment/square_charge.php',{method:'POST',body:fd});
        const data=await resp.json();
        if(data.success)window.location.href='/payment/success.php?invoice=<?php echo $invoiceId;?>';
        else{document.getElementById('payment-status').textContent=data.error||'Payment failed.';this.disabled=false;this.textContent='Pay';}
      }else{document.getElementById('payment-status').textContent='Card error.';this.disabled=false;this.textContent='Pay';}
    }catch(e){document.getElementById('payment-status').textContent='An error occurred.';this.disabled=false;this.textContent='Pay';}
  });
}
initSquare();
</script>
<?php endif;?>
</body></html>
