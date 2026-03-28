<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){echo json_encode(['success'=>false,'error'=>'Unauthorized']);exit;}
$projectId=(int)($_POST['project_id']??0); $message=trim($_POST['message']??''); $userId=(int)$_SESSION['user_id'];
if(!$projectId||empty($message)){echo json_encode(['success'=>false,'error'=>'Missing data']);exit;}
try{
    $pdo->prepare("INSERT INTO project_updates(project_id,user_id,message)VALUES(?,?,?)")
        ->execute([$projectId,$userId,htmlspecialchars($message,ENT_QUOTES,'UTF-8')]);
    echo json_encode(['success'=>true]);
}catch(Exception $e){echo json_encode(['success'=>false,'error'=>'DB error']);}
