<?php
/**
 * api/email_send.php — Send email via SMTP for admin webmail.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

require_once '../includes/auth.php';
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$account   = (int)($_POST['account'] ?? 2);
$to        = trim($_POST['to'] ?? '');
$subject   = trim($_POST['subject'] ?? '');
$body      = trim($_POST['body'] ?? '');
$inReplyTo = trim($_POST['in_reply_to'] ?? '');

if (empty($to) || empty($subject) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'To, Subject, and Body are required.']);
    exit;
}
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid recipient email address.']);
    exit;
}

// Load account-specific SMTP settings from DB
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'email_account_" . (int)$account . "_%'")->fetchAll();
    $s = [];
    foreach ($rows as $row) {
        $s[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$pfx      = 'email_account_' . $account . '_';
$smtpHost = $s[$pfx . 'smtp_host'] ?? '';
$smtpPort = (int)($s[$pfx . 'smtp_port'] ?? 465);
$smtpEnc  = $s[$pfx . 'smtp_encryption'] ?? 'ssl';
$fromEmail = $s[$pfx . 'email'] ?? '';
$password  = $s[$pfx . 'password'] ?? '';
$label     = $s[$pfx . 'label'] ?? 'Softandpix';

if (empty($smtpHost) || empty($fromEmail) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Account ' . $account . ' SMTP is not fully configured.']);
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer not installed.']);
    exit;
}
require_once $autoload;
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer class not found.']);
    exit;
}

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host     = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $fromEmail;
    $mail->Password = $password;
    $mail->Port     = $smtpPort;
    if ($smtpEnc === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($smtpEnc === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPAutoTLS = false;
    }
    $mail->setFrom($fromEmail, $label);
    $mail->addAddress($to);
    $mail->isHTML(false);
    $mail->Subject = $subject;
    $mail->Body    = $body;

    if (!empty($inReplyTo)) {
        $mail->addCustomHeader('In-Reply-To', $inReplyTo);
        $mail->addCustomHeader('References', $inReplyTo);
    }

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Email sent successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Could not send email: ' . $mail->ErrorInfo]);
}
