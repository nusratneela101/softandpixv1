<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){echo json_encode(['success'=>false]);exit;}
$userId=(int)$_SESSION['user_id'];
$notifId=(int)($_POST['notif_id']??0);
try{
    if($notifId>0) $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$notifId,$userId]);
    else $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    echo json_encode(['success'=>true]);
}catch(Exception $e){echo json_encode(['success'=>false]);}
