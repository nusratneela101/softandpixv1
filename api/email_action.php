<?php
/**
 * api/email_action.php — Perform IMAP actions (delete, mark_read, mark_unread, move).
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

if (!function_exists('imap_open')) {
    echo json_encode(['success' => false, 'message' => 'PHP IMAP extension is not installed on this server.']);
    exit;
}

$account     = (int)($_POST['account'] ?? 1);
$action      = trim($_POST['action'] ?? '');
$uid         = (int)($_POST['uid'] ?? 0);
$folder      = trim($_POST['folder'] ?? 'INBOX');
$destination = trim($_POST['destination'] ?? '');

// Validate action
$allowedActions = ['delete', 'mark_read', 'mark_unread', 'move'];
if (!in_array($action, $allowedActions, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// Validate move destination against allowlist
$allowedFolders = ['INBOX', 'Sent', 'Drafts', 'Trash'];
if ($action === 'move' && !in_array($destination, $allowedFolders, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid destination folder.']);
    exit;
}

if (!$uid) {
    echo json_encode(['success' => false, 'message' => 'No UID provided.']);
    exit;
}

// Load account settings
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
$imapHost = $s[$pfx . 'imap_host'] ?? '';
$imapPort = (int)($s[$pfx . 'imap_port'] ?? 993);
$imapEnc  = $s[$pfx . 'smtp_encryption'] ?? 'ssl';
$email    = $s[$pfx . 'email'] ?? '';
$password = $s[$pfx . 'password'] ?? '';

if (empty($imapHost) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Account ' . $account . ' is not configured.']);
    exit;
}

// Build IMAP string
$flags = '/imap';
if ($imapEnc === 'ssl') $flags .= '/ssl';
elseif ($imapEnc === 'tls') $flags .= '/tls';
else $flags .= '/notls';
$flags .= '/novalidate-cert';

$mailboxStr = '{' . $imapHost . ':' . $imapPort . $flags . '}' . $folder;

set_error_handler(function() {});
$imap = @imap_open($mailboxStr, $email, $password, 0, 1);
restore_error_handler();

if (!$imap) {
    $errors = imap_errors();
    $errMsg = is_array($errors) ? implode('; ', $errors) : 'Connection failed';
    echo json_encode(['success' => false, 'message' => 'IMAP connection failed: ' . $errMsg]);
    exit;
}

switch ($action) {
    case 'delete':
        imap_delete($imap, (string)$uid, FT_UID);
        imap_expunge($imap);
        break;
    case 'mark_read':
        imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
        break;
    case 'mark_unread':
        imap_clearflag_full($imap, (string)$uid, '\\Seen', ST_UID);
        break;
    case 'move':
        $destMailbox = '{' . $imapHost . ':' . $imapPort . $flags . '}' . $destination;
        imap_mail_move($imap, (string)$uid, $destMailbox, CP_UID);
        imap_expunge($imap);
        break;
}

imap_close($imap);
echo json_encode(['success' => true, 'message' => 'Action completed.']);
