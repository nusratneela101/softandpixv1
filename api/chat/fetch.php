<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])&&!isset($_SESSION['admin_id'])){echo json_encode(['messages'=>[]]);exit;}
$roomId=(int)($_GET['room_id']??0); $lastId=(int)($_GET['last_id']??0);
if(!$roomId){echo json_encode(['messages'=>[]]);exit;}
try{
    $stmt=$pdo->prepare("SELECT cm.id,cm.sender_id,cm.sender_name,cm.message,cm.attachment,cm.created_at,u.name as user_name FROM chat_messages cm LEFT JOIN users u ON u.id=cm.sender_id WHERE cm.room_id=? AND cm.id>? ORDER BY cm.created_at ASC LIMIT 50");
    $stmt->execute([$roomId,$lastId]);
    $messages=$stmt->fetchAll();
    $userId=(int)($_SESSION['user_id']??0);
    if($userId) $pdo->prepare("UPDATE chat_messages SET is_read=1 WHERE room_id=? AND (sender_id!=? OR sender_id IS NULL)")->execute([$roomId,$userId]);
    echo json_encode(['messages'=>$messages]);
}catch(Exception $e){echo json_encode(['messages'=>[]]);}
