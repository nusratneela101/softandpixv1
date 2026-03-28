<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['admin_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'")->fetchAll();
    $s = []; foreach ($rows as $r) $s[$r['setting_key']] = $r['setting_value'];
    if (empty($s['smtp_host'])) { echo json_encode(['success'=>false,'message'=>'SMTP host not configured']); exit; }
    $autoload = __DIR__.'/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->Host=$s['smtp_host']; $mail->SMTPAuth=true;
            $mail->Username=$s['smtp_username']??''; $mail->Password=$s['smtp_password']??'';
            $mail->Port=(int)($s['smtp_port']??587); $mail->SMTPDebug=0;
            $enc=$s['smtp_encryption']??'tls';
            if($enc==='ssl') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            elseif($enc==='tls') $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            else $mail->SMTPAutoTLS=false;
            if($mail->smtpConnect()){ $mail->smtpClose(); echo json_encode(['success'=>true,'message'=>'SMTP connection successful!']); exit; }
            echo json_encode(['success'=>false,'message'=>'Connection failed: '.$mail->ErrorInfo]); exit;
        }
    }
    echo json_encode(['success'=>false,'message'=>'PHPMailer not installed.']);
} catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
