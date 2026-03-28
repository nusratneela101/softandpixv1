<?php
/**
 * api/email_test.php — Test IMAP or SMTP connection for an email account.
 * Called by admin/email_accounts.php and admin/settings.php via AJAX.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type       = trim($_POST['type'] ?? 'smtp');
$host       = trim($_POST['host'] ?? '');
$port       = (int)($_POST['port'] ?? 465);
$email      = trim($_POST['email'] ?? '');
$password   = trim($_POST['password'] ?? '');
$encryption = trim($_POST['encryption'] ?? 'ssl');

if (empty($host) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Host and email are required.']);
    exit;
}

// If password is blank, try loading from database
if ($password === '') {
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'email_account_%_password' OR setting_key = 'smtp_password'")->fetchAll();
        foreach ($rows as $row) {
            // Match by checking the email setting for the same account prefix
            if ($type === 'smtp' && $row['setting_key'] === 'smtp_password') {
                $password = $row['setting_value'];
                break;
            }
        }
        // Try to match by email address
        if ($password === '') {
            $allRows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'email_account_%'")->fetchAll();
            $acctSettings = [];
            foreach ($allRows as $r) {
                $acctSettings[$r['setting_key']] = $r['setting_value'];
            }
            for ($i = 1; $i <= 9; $i++) {
                if (($acctSettings["email_account_{$i}_email"] ?? '') === $email) {
                    $password = $acctSettings["email_account_{$i}_password"] ?? '';
                    break;
                }
            }
        }
    } catch (Exception $e) {}
}

if ($type === 'imap') {
    // Test IMAP connection
    if (!function_exists('imap_open')) {
        echo json_encode(['success' => false, 'message' => 'PHP IMAP extension is not installed on this server.']);
        exit;
    }
    $mailbox = '{' . $host . ':' . $port . '/imap/ssl/novalidate-cert}INBOX';
    if ($encryption === 'tls') {
        $mailbox = '{' . $host . ':' . $port . '/imap/tls/novalidate-cert}INBOX';
    } elseif ($encryption === 'none') {
        $mailbox = '{' . $host . ':' . $port . '/imap/notls/novalidate-cert}INBOX';
    }
    set_error_handler(function() {});
    $conn = @imap_open($mailbox, $email, $password, OP_HALFOPEN, 1);
    restore_error_handler();
    if ($conn) {
        imap_close($conn);
        echo json_encode(['success' => true, 'message' => 'IMAP connection successful! Connected to ' . htmlspecialchars($host, ENT_QUOTES)]);
    } else {
        $errors = imap_errors();
        $errMsg = is_array($errors) ? implode('; ', $errors) : 'Connection failed';
        echo json_encode(['success' => false, 'message' => 'IMAP failed: ' . htmlspecialchars($errMsg, ENT_QUOTES)]);
    }
    exit;
}

// Test SMTP connection via PHPMailer
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer not installed (vendor/autoload.php not found).']);
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
    $mail->Host     = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $email;
    $mail->Password = $password;
    $mail->Port     = $port;
    $mail->SMTPDebug = 0;
    if ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPAutoTLS = false;
    }
    if ($mail->smtpConnect()) {
        $mail->smtpClose();
        echo json_encode(['success' => true, 'message' => 'SMTP connection successful! Connected to ' . htmlspecialchars($host, ENT_QUOTES)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'SMTP connection failed: ' . $mail->ErrorInfo]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()]);
}
