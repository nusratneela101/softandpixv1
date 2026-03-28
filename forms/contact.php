<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/email.php';

header('Content-Type: text/plain');

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = str_replace(["\r", "\n"], '', trim($_POST['subject'] ?? ''));
$message = trim($_POST['message'] ?? '');

if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    echo 'Please fill in all fields.';
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'Invalid email address.';
    exit;
}

$htmlBody  = '<h3>New Contact Form Submission</h3>';
$htmlBody .= '<table style="border-collapse:collapse;width:100%;">';
$htmlBody .= '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Name</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>';
$htmlBody .= '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Email</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>';
$htmlBody .= '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Subject</td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</td></tr>';
$htmlBody .= '<tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Message</td><td style="padding:8px;border:1px solid #ddd;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</td></tr>';
$htmlBody .= '</table>';

try {
    $result = sendAdminNotification($pdo, 'Contact Form: ' . $subject, $htmlBody);
    if ($result) {
        echo 'OK';
    } else {
        echo 'Unable to send your message. Please try again later.';
    }
} catch (Exception $e) {
    echo 'An error occurred. Please try again later.';
}
