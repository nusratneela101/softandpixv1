<?php
session_start();
require_once '../../config/db.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){echo json_encode(['success'=>false,'error'=>'Unauthorized']);exit;}
$projectId=(int)($_POST['project_id']??0);
$progress=min(100,max(0,(int)($_POST['progress']??0)));
if(!$projectId){echo json_encode(['success'=>false,'error'=>'Missing project ID']);exit;}
$userId=(int)$_SESSION['user_id']; $role=$_SESSION['user_role']??'';
try{
    if($role==='admin') { $stmt=$pdo->prepare("SELECT id FROM projects WHERE id=?"); $stmt->execute([$projectId]); }
    else { $stmt=$pdo->prepare("SELECT id FROM projects WHERE id=? AND developer_id=?"); $stmt->execute([$projectId,$userId]); }
    if(!$stmt->fetch()){echo json_encode(['success'=>false,'error'=>'Access denied']);exit;}
    $status=$progress>=100?'completed':($progress>0?'in_progress':'pending');
    $pdo->prepare("UPDATE projects SET progress=?,status=?,updated_at=NOW() WHERE id=?")->execute([$progress,$status,$projectId]);
    echo json_encode(['success'=>true,'progress'=>$progress,'status'=>$status]);
}catch(Exception $e){echo json_encode(['success'=>false,'error'=>'DB error']);}
