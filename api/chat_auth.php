<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');
$action=$_POST['action']??'';
if($action==='register'){
    $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if(empty($name)||empty($email)||strlen($pass)<6){echo json_encode(['success'=>false,'error'=>'All fields required (password min 6 chars)']);exit;}
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){echo json_encode(['success'=>false,'error'=>'Invalid email']);exit;}
    try{
        $check=$pdo->prepare("SELECT id FROM users WHERE email=?"); $check->execute([$email]);
        if($check->fetch()){echo json_encode(['success'=>false,'error'=>'Email already registered. Please sign in.']);exit;}
        $hash=password_hash($pass,PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users(name,email,password,role,is_active,email_verified)VALUES(?,?,?,'client',1,0)")
            ->execute([htmlspecialchars($name,ENT_QUOTES,'UTF-8'),$email,$hash]);
        $userId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO chat_conversations(title,type)VALUES(?,?)")
            ->execute(['Support: '.htmlspecialchars($name,ENT_QUOTES,'UTF-8'),'support']);
        $roomId=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO chat_participants(conversation_id,user_id)VALUES(?,?)")->execute([$roomId,$userId]);
        $pdo->prepare("INSERT INTO chat_messages(conversation_id,sender_id,message)VALUES(?,NULL,'Welcome! How can we help you today?')")->execute([$roomId]);
        $_SESSION['user_id']=$userId; $_SESSION['user_name']=htmlspecialchars($name,ENT_QUOTES,'UTF-8'); $_SESSION['user_role']='client'; $_SESSION['user_email']=$email;
        echo json_encode(['success'=>true,'room_id'=>$roomId,'user_name'=>htmlspecialchars($name,ENT_QUOTES,'UTF-8')]);
    }catch(Exception $e){echo json_encode(['success'=>false,'error'=>'Registration failed']);}
}elseif($action==='login'){
    $email=trim($_POST['email']??''); $pass=$_POST['password']??'';
    if(empty($email)||empty($pass)){echo json_encode(['success'=>false,'error'=>'Email and password required']);exit;}
    try{
        $stmt=$pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1"); $stmt->execute([$email]); $user=$stmt->fetch();
        if($user&&password_verify($pass,$user['password'])){
            $rs=$pdo->prepare("SELECT r.id FROM chat_conversations r INNER JOIN chat_participants m ON m.conversation_id=r.id WHERE r.type='support' AND m.user_id=? LIMIT 1");
            $rs->execute([$user['id']]); $room=$rs->fetch();
            if(!$room){
                $pdo->prepare("INSERT INTO chat_conversations(title,type)VALUES(?,?)")->execute(['Support: '.$user['name'],'support']);
                $roomId=(int)$pdo->lastInsertId();
                $pdo->prepare("INSERT IGNORE INTO chat_participants(conversation_id,user_id)VALUES(?,?)")->execute([$roomId,$user['id']]);
            }else $roomId=(int)$room['id'];
            $_SESSION['user_id']=$user['id']; $_SESSION['user_name']=$user['name']; $_SESSION['user_role']=$user['role']; $_SESSION['user_email']=$user['email'];
            echo json_encode(['success'=>true,'room_id'=>$roomId,'user_name'=>htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8')]);
        }else echo json_encode(['success'=>false,'error'=>'Invalid email or password']);
    }catch(Exception $e){echo json_encode(['success'=>false,'error'=>'Login failed']);}
}else echo json_encode(['success'=>false,'error'=>'Invalid action']);
