<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');
if(!isset($_SESSION['user_id'])){echo json_encode(['success'=>false,'error'=>'Unauthorized']);exit;}
$projectId=(int)($_POST['project_id']??0); $userId=(int)$_SESSION['user_id'];
if(!$projectId||empty($_FILES['file'])){echo json_encode(['success'=>false,'error'=>'Missing data']);exit;}
$allowed=['image/jpeg','image/png','image/gif','image/webp','application/pdf','application/zip','application/msword','text/plain'];
$val=validateUploadedFile($_FILES['file'],$allowed,10485760);
if(!$val['ok']){echo json_encode(['success'=>false,'error'=>$val['error']]);exit;}
$dir=__DIR__.'/../../assets/uploads/projects/'.$projectId.'/';
if(!is_dir($dir))mkdir($dir,0755,true);
$orig=basename($_FILES['file']['name']);
$fname=time().'_'.bin2hex(random_bytes(4)).'.'.$val['ext'];
if(move_uploaded_file($_FILES['file']['tmp_name'],$dir.$fname)){
    try{
        $size=filesize($dir.$fname);
        $pdo->prepare("INSERT INTO project_files(project_id,user_id,filename,original_name,file_size,file_type)VALUES(?,?,?,?,?,?)")
            ->execute([$projectId,$userId,$fname,htmlspecialchars($orig,ENT_QUOTES,'UTF-8'),$size,$val['mime']]);
        echo json_encode(['success'=>true,'filename'=>$fname]);
    }catch(Exception $e){echo json_encode(['success'=>false,'error'=>'DB error']);}
}else{echo json_encode(['success'=>false,'error'=>'Upload failed']);}
