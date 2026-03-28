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
    $stripePublicKey=getSetting($pdo,'stripe_public_key');
}catch(Exception $e){die('Error.');}
if(!$invoice||$invoice['status']==='paid'){header('Location: /client/invoices.php');exit;}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stripe Payment - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body{background:#f0f2f5;}#card-element{border:1px solid #ced4da;border-radius:8px;padding:12px;background:#fff;}</style>
</head><body>
<div class="container py-5" style="max-width:550px;">
  <div class="card shadow border-0" style="border-radius:16px;">
    <div class="card-header text-white text-center py-4" style="background:linear-gradient(135deg,#6772e5,#5469d4);">
      <i class="bi bi-credit-card fs-2"></i><h5 class="mb-0 mt-2">Pay with Stripe</h5></div>
    <div class="card-body p-4">
      <table class="table table-sm mb-4">
        <tr><th>Invoice</th><td class="text-end"><?php echo h($invoice['invoice_number']);?></td></tr>
        <tr><th>Amount</th><td class="text-end fw-bold text-success"><?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?></td></tr>
      </table>
      <?php if(empty($stripePublicKey)):?>
      <div class="alert alert-warning">Stripe is not configured. Contact admin.</div>
      <?php else:?>
      <form id="stripeForm">
        <div class="mb-3"><label class="form-label fw-semibold">Card Details</label>
          <div id="card-element"></div><div id="card-errors" class="text-danger small mt-1"></div></div>
        <button type="submit" class="btn btn-primary w-100 fw-bold py-2" id="payBtn">
          <i class="bi bi-lock me-2"></i>Pay <?php echo h($invoice['currency']);?> <?php echo number_format($invoice['total'],2);?></button>
      </form>
      <?php endif;?>
      <a href="/client/invoice_view.php?id=<?php echo $invoiceId;?>" class="btn btn-outline-secondary w-100 mt-3">← Back</a>
    </div>
  </div>
</div>
<?php if(!empty($stripePublicKey)):?>
<script src="https://js.stripe.com/v3/"></script>
<script>
var stripe=Stripe('<?php echo h($stripePublicKey);?>');
var elements=stripe.elements();
var card=elements.create('card');card.mount('#card-element');
document.getElementById('stripeForm').addEventListener('submit',async function(e){
  e.preventDefault();var btn=document.getElementById('payBtn');
  btn.disabled=true;btn.textContent='Processing...';
  var resp=await fetch('/api/payment/create_intent.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({invoice_id:<?php echo $invoiceId;?>})});
  var data=await resp.json();
  if(!data.client_secret){document.getElementById('card-errors').textContent=data.error||'Payment failed.';btn.disabled=false;btn.textContent='Pay';return;}
  var result=await stripe.confirmCardPayment(data.client_secret,{payment_method:{card:card}});
  if(result.error){document.getElementById('card-errors').textContent=result.error.message;btn.disabled=false;btn.innerHTML='<i class="bi bi-lock me-2"></i>Pay';}
  else{window.location.href='/payment/success.php?invoice=<?php echo $invoiceId;?>&pi='+result.paymentIntent.id;}
});
</script>
<?php endif;?>
</body></html>
