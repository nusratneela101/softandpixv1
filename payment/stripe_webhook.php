<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

$payload=file_get_contents('php://input');
$sigHeader=$_SERVER['HTTP_STRIPE_SIGNATURE']??'';
$webhookSecret=getSetting($pdo,'stripe_webhook_secret');

// Verify signature if secret configured
if(!empty($webhookSecret)){
    $parts=explode(',',$sigHeader);
    $timestamp='';$signatures=[];
    foreach($parts as $p){
        if(strpos($p,'t=')===0)$timestamp=substr($p,2);
        elseif(strpos($p,'v1=')===0)$signatures[]=substr($p,3);
    }
    if(empty($timestamp)||empty($signatures)){http_response_code(400);exit('Bad signature');}
    $expected=hash_hmac('sha256',$timestamp.'.'.$payload,$webhookSecret);
    if(!in_array($expected,$signatures)){http_response_code(400);exit('Invalid signature');}
}

$event=json_decode($payload,true);
if(!$event){http_response_code(400);exit('Invalid payload');}

if($event['type']==='payment_intent.succeeded'){
    $pi=$event['data']['object'];
    $invoiceId=(int)($pi['metadata']['invoice_id']??0);
    if($invoiceId){
        try{
            $pdo->prepare("UPDATE invoices SET status='paid',paid_at=NOW() WHERE id=? AND status!='paid'")->execute([$invoiceId]);
            $pdo->prepare("INSERT IGNORE INTO invoice_payments (invoice_id,amount,method,transaction_id,status) SELECT id,total,'stripe',?,'completed' FROM invoices WHERE id=?")
                ->execute([$pi['id'],$invoiceId]);
        }catch(Exception $e){}
    }
}
http_response_code(200);echo json_encode(['received'=>true]);
