<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();
$invoiceId=(int)($_GET['invoice']??0);
$orderId=trim($_GET['order_id']??'');
if($invoiceId&&$orderId){
    try{
        $pdo->prepare("UPDATE invoices SET status='paid',paid_at=NOW() WHERE id=? AND status!='paid'")->execute([$invoiceId]);
        $pdo->prepare("INSERT INTO invoice_payments (invoice_id,amount,method,transaction_id,status) SELECT id,total,'paypal',?,'completed' FROM invoices WHERE id=?")->execute([$orderId,$invoiceId]);
    }catch(Exception $e){}
}
header('Location: /payment/success.php?invoice='.$invoiceId);exit;
