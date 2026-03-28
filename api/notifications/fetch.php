<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){echo json_encode(['notifications'=>[],'unread_count'=>0]);exit;}
$userId=(int)$_SESSION['user_id'];
try{
    $stmt=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]); $list=$stmt->fetchAll();
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $cnt->execute([$userId]); $unread=(int)$cnt->fetchColumn();
    echo json_encode(['notifications'=>$list,'unread_count'=>$unread]);
}catch(Exception $e){echo json_encode(['notifications'=>[],'unread_count'=>0]);}
