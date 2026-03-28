<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');
requireLogin();
$input=json_decode(file_get_contents('php://input'),true);
$invoiceId=(int)($input['invoice_id']??0);
if(!$invoiceId){echo json_encode(['error'=>'Invalid invoice']);exit;}
try{
    $stmt=$pdo->prepare("SELECT * FROM invoices WHERE id=?"); $stmt->execute([$invoiceId]); $invoice=$stmt->fetch();
    if(!$invoice||$invoice['status']==='paid'){echo json_encode(['error'=>'Invoice not available']);exit;}
    if($_SESSION['user_role']!=='admin'&&(int)$invoice['client_id']!==(int)$_SESSION['user_id']){echo json_encode(['error'=>'Unauthorized']);exit;}
    $stripeSecret=getSetting($pdo,'stripe_secret_key');
    if(empty($stripeSecret)){echo json_encode(['error'=>'Stripe not configured']);exit;}
    $amount=(int)round($invoice['total']*100);
    $currency=strtolower($invoice['currency']??'usd');
    $ch=curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query(['amount'=>$amount,'currency'=>$currency,'metadata[invoice_id]'=>$invoiceId]),
        CURLOPT_USERPWD=>$stripeSecret.':',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>30]);
    $response=curl_exec($ch); $httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($httpCode===200){ $data=json_decode($response,true); echo json_encode(['client_secret'=>$data['client_secret']]); }
    else{ $err=json_decode($response,true); echo json_encode(['error'=>$err['error']['message']??'Stripe error']); }
}catch(Exception $e){echo json_encode(['error'=>'Server error']);}
