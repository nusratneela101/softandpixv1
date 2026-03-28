<?php
/**
 * api/email_fetch.php — Fetch emails via IMAP for admin webmail.
 * Actions: list, read
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!function_exists('imap_open')) {
    echo json_encode(['success' => false, 'message' => 'PHP IMAP extension is not installed on this server.']);
    exit;
}

$action  = trim($_GET['action'] ?? 'list');
$account = (int)($_GET['account'] ?? 1);
$folder  = trim($_GET['folder'] ?? 'INBOX');
$page    = max(1, (int)($_GET['page'] ?? 1));
$search  = trim($_GET['search'] ?? '');
$uid     = (int)($_GET['uid'] ?? 0);
$perPage = 20;

// Load account settings from DB
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

$pfx        = 'email_account_' . $account . '_';
$imapHost   = $s[$pfx . 'imap_host'] ?? '';
$imapPort   = (int)($s[$pfx . 'imap_port'] ?? 993);
$imapEnc    = $s[$pfx . 'smtp_encryption'] ?? 'ssl';
$email      = $s[$pfx . 'email'] ?? '';
$password   = $s[$pfx . 'password'] ?? '';

if (empty($imapHost) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email account ' . $account . ' is not configured. Please set it up in Email Accounts settings.']);
    exit;
}

// Build IMAP connection string
function buildImapString($host, $port, $enc, $folder) {
    $flags = '/imap';
    if ($enc === 'ssl') $flags .= '/ssl';
    elseif ($enc === 'tls') $flags .= '/tls';
    else $flags .= '/notls';
    $flags .= '/novalidate-cert';
    return '{' . $host . ':' . $port . $flags . '}' . $folder;
}

// Decode email header value (handles encoded words)
function decodeHeader($str) {
    if (empty($str)) return '';
    $decoded = imap_mime_header_decode($str);
    $result = '';
    foreach ($decoded as $part) {
        $charset = ($part->charset === 'default') ? 'UTF-8' : $part->charset;
        $text = $part->text;
        if ($charset && strtoupper($charset) !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $charset);
        }
        $result .= $text;
    }
    return $result;
}

// Decode email body part
function decodeBody($text, $encoding, $charset = 'UTF-8') {
    switch ((int)$encoding) {
        case 1: $text = imap_8bit($text); break;
        case 2: $text = imap_binary($text); break;
        case 3: $text = base64_decode($text); break;
        case 4: $text = imap_qprint($text); break;
        default: break;
    }
    if ($charset && strtoupper($charset) !== 'UTF-8') {
        $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
        if ($converted !== false) $text = $converted;
    }
    return $text;
}

// Extract body from MIME structure
function getBodyParts($imap, $msgno, $structure, $prefix = '') {
    $htmlBody = '';
    $textBody = '';

    if (isset($structure->parts) && count($structure->parts) > 0) {
        foreach ($structure->parts as $i => $part) {
            $partNum = ($prefix === '') ? ($i + 1) : ($prefix . '.' . ($i + 1));
            list($html, $text) = getBodyParts($imap, $msgno, $part, $partNum);
            if (!$htmlBody) $htmlBody = $html;
            if (!$textBody) $textBody = $text;
        }
    } else {
        $partNum = ($prefix === '') ? '1' : $prefix;
        $charset = 'UTF-8';
        if (isset($structure->parameters)) {
            foreach ($structure->parameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    $charset = $param->value;
                }
            }
        }
        $raw = imap_fetchbody($imap, $msgno, $partNum);
        $decoded = decodeBody($raw, $structure->encoding, $charset);

        $subtype = strtoupper($structure->subtype ?? '');
        if ($structure->type == 0 && $subtype === 'HTML') {
            return [$decoded, ''];
        } elseif ($structure->type == 0) {
            return ['', $decoded];
        }
    }
    return [$htmlBody, $textBody];
}

$mailboxStr = buildImapString($imapHost, $imapPort, $imapEnc, $folder);
set_error_handler(function() {});
$imap = @imap_open($mailboxStr, $email, $password, 0, 1);
restore_error_handler();

if (!$imap) {
    $errors = imap_errors();
    $errMsg = is_array($errors) ? implode('; ', $errors) : 'Connection failed';
    echo json_encode(['success' => false, 'message' => 'IMAP connection failed: ' . $errMsg]);
    exit;
}

if ($action === 'list') {
    // Sanitize search to prevent IMAP injection
    $searchTerm = str_replace(["\r", "\n", '"'], ['', '', '\\"'], $search);
    $criteria = 'ALL';
    if (!empty($searchTerm)) {
        $criteria = 'OR OR SUBJECT "' . $searchTerm . '" FROM "' . $searchTerm . '" TO "' . $searchTerm . '"';
    }

    set_error_handler(function() {});
    $msgIds = @imap_search($imap, $criteria, SE_UID);
    restore_error_handler();

    if ($msgIds === false) $msgIds = [];
    $msgIds = array_reverse($msgIds); // newest first

    $total = count($msgIds);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $offset = ($page - 1) * $perPage;
    $pageIds = array_slice($msgIds, $offset, $perPage);

    $emails = [];
    foreach ($pageIds as $uid) {
        $overview = @imap_fetch_overview($imap, $uid, FT_UID);
        if ($overview && isset($overview[0])) {
            $ov = $overview[0];
            $emails[] = [
                'uid'     => $uid,
                'from'    => decodeHeader($ov->from ?? ''),
                'subject' => decodeHeader($ov->subject ?? '(no subject)'),
                'date'    => isset($ov->date) ? date('M d, H:i', strtotime($ov->date)) : '',
                'unseen'  => isset($ov->seen) ? !$ov->seen : true,
            ];
        }
    }

    imap_close($imap);
    echo json_encode([
        'success'     => true,
        'emails'      => $emails,
        'total'       => $total,
        'total_pages' => $totalPages,
        'page'        => $page,
    ]);
    exit;
}

if ($action === 'read') {
    if (!$uid) {
        imap_close($imap);
        echo json_encode(['success' => false, 'message' => 'No UID provided.']);
        exit;
    }

    $msgno = imap_msgno($imap, $uid);
    if (!$msgno) {
        imap_close($imap);
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
        exit;
    }

    $headerInfo = imap_headerinfo($imap, $msgno);
    $structure  = imap_fetchstructure($imap, $msgno);

    $from    = decodeHeader(isset($headerInfo->from[0]) ? $headerInfo->from[0]->personal . ' <' . $headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host . '>' : '');
    $fromEmail = isset($headerInfo->from[0]) ? $headerInfo->from[0]->mailbox . '@' . $headerInfo->from[0]->host : '';
    $to      = decodeHeader(isset($headerInfo->to[0]) ? $headerInfo->to[0]->mailbox . '@' . $headerInfo->to[0]->host : '');
    $subject = decodeHeader($headerInfo->subject ?? '(no subject)');
    $date    = isset($headerInfo->date) ? date('D, d M Y H:i:s', strtotime($headerInfo->date)) : '';
    $replyTo = isset($headerInfo->reply_to[0]) ? $headerInfo->reply_to[0]->mailbox . '@' . $headerInfo->reply_to[0]->host : $fromEmail;
    $msgId   = trim($headerInfo->message_id ?? '');

    // Mark as read
    imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);

    list($htmlBody, $textBody) = getBodyParts($imap, $msgno, $structure);

    if (empty($htmlBody) && !empty($textBody)) {
        $htmlBody = '<pre>' . htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8') . '</pre>';
    } elseif (empty($htmlBody)) {
        $rawBody = imap_fetchbody($imap, $msgno, '1');
        $htmlBody = '<pre>' . htmlspecialchars(decodeBody($rawBody, $structure->encoding ?? 0), ENT_QUOTES, 'UTF-8') . '</pre>';
    }

    imap_close($imap);
    echo json_encode([
        'success' => true,
        'email'   => [
            'uid'        => $uid,
            'from'       => $from,
            'from_email' => $fromEmail,
            'to'         => $to,
            'subject'    => $subject,
            'date'       => $date,
            'reply_to'   => $replyTo,
            'message_id' => $msgId,
            'body_html'  => $htmlBody,
            'body_text'  => $textBody,
        ],
    ]);
    exit;
}

imap_close($imap);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
