<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
verify_csrf_token($_POST['csrf_token'] ?? '');

$to_email = trim($_POST['to_email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = $_POST['body'] ?? '';
$smtp = $_POST['smtp_account'] ?? 'support';

if (!$to_email || !$subject) { echo json_encode(['success' => false, 'message' => 'To and subject required']); exit; }

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?"); $stmt->execute([$to_email]); $to_user = $stmt->fetch();
$pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder, sent_via_smtp) VALUES (?,?,?,?,?,?,?,'sent',1)")
    ->execute([$_SESSION['user_id'], $_SESSION['user_email'], $to_user['id'] ?? null, $to_email, $subject, $body, $smtp]);
if ($to_user) {
    $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder) VALUES (?,?,?,?,?,?,?,'inbox')")
        ->execute([$_SESSION['user_id'], $_SESSION['user_email'], $to_user['id'], $to_email, $subject, $body, $smtp]);
}
$sent = send_email($to_email, $to_email, $subject, $body, $smtp);
echo json_encode(['success' => true, 'smtp_sent' => $sent]);
