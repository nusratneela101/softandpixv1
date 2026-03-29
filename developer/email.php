<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
if ($_SESSION['user_role'] !== 'developer') { redirect(BASE_URL . '/' . $_SESSION['user_role'] . '/'); }
update_online_status($pdo, $_SESSION['user_id']);
$csrf = generate_csrf_token();
$flash = get_flash();
$folder = $_GET['folder'] ?? 'inbox';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($action === 'send' || $action === 'draft') {
        $to_email = trim($_POST['to_email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        $is_draft = ($action === 'draft') ? 1 : 0;
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?"); $stmt->execute([$to_email]); $to_user = $stmt->fetch();

        // Handle attachments
        $attachmentPaths = [];
        if (!$is_draft && !empty($_FILES['attachments']['name'][0])) {
            if (array_sum($_FILES['attachments']['size']) > 10 * 1024 * 1024) {
                set_flash('error', 'Attachments exceed 10MB limit.');
                redirect(BASE_URL . '/developer/email.php?compose=1');
            }
            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $f = ['error'=>UPLOAD_ERR_OK,'tmp_name'=>$tmp,'size'=>$_FILES['attachments']['size'][$i],'name'=>$_FILES['attachments']['name'][$i]];
                $savedPath = upload_file($f, 'email_attachments');
                if ($savedPath) $attachmentPaths[] = $savedPath;
            }
        }

        $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, is_draft, folder, sent_via_smtp) VALUES (?,?,?,?,?,?,'support',?,?,?)")
            ->execute([$_SESSION['user_id'], $_SESSION['user_email'], $to_user['id'] ?? null, $to_email, $subject, $body, $is_draft, $is_draft ? 'draft' : 'sent', $is_draft ? 0 : 1]);
        $emailId = (int)$pdo->lastInsertId();
        foreach ($attachmentPaths as $ap) {
            try { $pdo->prepare("INSERT INTO email_attachments (email_id, file_name, file_path, file_size) VALUES (?,?,?,?)")->execute([$emailId, basename($ap), $ap, filesize(BASE_PATH.'/'.$ap)]); } catch (Exception $e) {}
        }

        if (!$is_draft && $to_user) {
            $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder) VALUES (?,?,?,?,?,?,'support','inbox')")
                ->execute([$_SESSION['user_id'], $_SESSION['user_email'], $to_user['id'], $to_email, $subject, $body]);
        }
        if (!$is_draft) send_email($to_email, $to_email, $subject, $body, 'support');
        set_flash('success', $is_draft ? 'Draft saved.' : 'Email sent!');
        redirect(BASE_URL . '/developer/email.php?folder=' . ($is_draft ? 'draft' : 'sent'));
    }
}

$allowed_folders = ['inbox', 'sent', 'draft', 'starred', 'trash'];
if (!in_array($folder, $allowed_folders)) { $folder = 'inbox'; }
$where = $folder === 'starred' ? 'is_starred=1 AND is_trash=0' : "folder='" . $folder . "'";
$stmt = $pdo->prepare("SELECT * FROM emails WHERE ($where) AND (from_user_id=? OR to_user_id=?) ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]); $emails = $stmt->fetchAll();
$view_id = (int)($_GET['view'] ?? 0); $view_email = null;
if ($view_id) { $stmt = $pdo->prepare("SELECT * FROM emails WHERE id=? AND (from_user_id=? OR to_user_id=?)"); $stmt->execute([$view_id, $_SESSION['user_id'], $_SESSION['user_id']]); $view_email = $stmt->fetch(); if($view_email) $pdo->prepare("UPDATE emails SET is_read=1 WHERE id=?")->execute([$view_id]); }
$compose = $_GET['compose'] ?? false;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Email — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet">
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script></head><body>
<?php include BASE_PATH . '/includes/sidebar_developer.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Email</h5></div>
<div class="topbar-right"><a href="?folder=<?= $folder ?>&compose=1" class="btn btn-primary btn-sm"><i class="fas fa-pen me-1"></i>Compose</a></div></div>
<div class="main-content">
<?php if($flash):?><div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif;?>
<div class="email-container">
<div class="email-sidebar">
<?php foreach(['inbox'=>['fa-inbox','Inbox'],'sent'=>['fa-paper-plane','Sent'],'draft'=>['fa-file-alt','Drafts'],'starred'=>['fa-star','Starred'],'trash'=>['fa-trash','Trash']] as $f=>[$icon,$label]):?>
<a href="?folder=<?= $f ?>" class="text-decoration-none"><div class="email-folder <?= $folder===$f?'active':'' ?>"><i class="fas <?= $icon ?>"></i><span><?= $label ?></span></div></a>
<?php endforeach;?></div>
<div class="email-list">
<?php if(empty($emails)):?><div class="text-center text-muted p-4">No emails</div>
<?php else: foreach($emails as $em):?>
<a href="?folder=<?= $folder ?>&view=<?= $em['id'] ?>" class="text-decoration-none"><div class="email-item <?= !$em['is_read']?'unread':'' ?>">
<div class="small fw-bold text-dark"><?= e($em['folder']==='sent'?'To: '.$em['to_email']:$em['from_email']) ?></div>
<div class="small text-dark"><?= e($em['subject']?:'(No subject)') ?></div>
<small class="text-muted"><?= time_ago($em['created_at']) ?></small></div></a>
<?php endforeach; endif;?></div>
<div class="email-view">
<?php if($compose):?>
<h5 class="mb-3"><i class="fas fa-pen me-2"></i>Compose</h5>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<div class="mb-3"><input type="email" name="to_email" class="form-control" placeholder="To email" required value="<?= e($_GET['to']??'') ?>"></div>
<div class="mb-3"><input type="text" name="subject" class="form-control" placeholder="Subject" value="<?= e($_GET['subject']??'') ?>"></div>
<div class="mb-3"><textarea name="body" id="emailBody" class="form-control"></textarea></div>
<div class="mb-3"><label class="form-label small text-muted">Attachments (max 10MB total)</label><input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.txt,.csv,.zip"></div>
<button type="submit" name="action" value="send" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send</button>
<button type="submit" name="action" value="draft" class="btn btn-outline-secondary ms-2">Draft</button></form>
<?php elseif($view_email):?>
<h5><?= e($view_email['subject']?:'(No subject)') ?></h5>
<div class="text-muted small mb-3">From: <?= e($view_email['from_email']) ?></div><hr>
<div><?= htmlspecialchars_decode(htmlspecialchars($view_email['body'], ENT_QUOTES, 'UTF-8')) ?></div>
<a href="?folder=<?= $folder ?>&compose=1&to=<?= urlencode($view_email['from_email']) ?>&subject=<?= urlencode('Re: '.$view_email['subject']) ?>" class="btn btn-sm btn-outline-primary mt-3"><i class="fas fa-reply me-1"></i>Reply</a>
<?php else:?><div class="text-center text-muted" style="margin-top:100px"><p>Select an email or <a href="?compose=1">compose</a></p></div><?php endif;?>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script><?php if($compose):?>tinymce.init({selector:'#emailBody',height:300,plugins:'lists link image emoticons',toolbar:'undo redo | bold italic underline | bullist numlist | link image emoticons',menubar:false,branding:false});<?php endif;?></script></body></html>
