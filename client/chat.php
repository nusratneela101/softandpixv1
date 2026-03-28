<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();

$userId     = (int)$_SESSION['user_id'];
$userName   = htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$csrf_token = generateCsrfToken();

function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60)    return 'just now';
    if ($d < 3600)  return floor($d/60).'m ago';
    if ($d < 86400) return floor($d/3600).'h ago';
    return date('M j', strtotime($dt));
}

$activeConvId = (int)($_GET['conv'] ?? 0);

try {
    $cstmt = $pdo->prepare(
        "SELECT c.*,
            (SELECT COUNT(*) FROM chat_messages m
                WHERE m.conversation_id=c.id AND m.is_read=0 AND m.sender_id!=?) AS unread_count,
            (SELECT m2.message FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
            (SELECT m2.message_type FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message_type,
            (SELECT m2.created_at FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message_at
         FROM chat_conversations c
         INNER JOIN chat_participants cp ON cp.conversation_id=c.id AND cp.user_id=?
         ORDER BY c.updated_at DESC LIMIT 100"
    );
    $cstmt->execute([$userId, $userId]);
    $convRows = $cstmt->fetchAll();

    if (!$activeConvId && !empty($convRows)) {
        $activeConvId = (int)$convRows[0]['id'];
    }

    foreach ($convRows as &$cr) {
        if (!empty($cr['title'])) {
            $cr['display_title'] = $cr['title'];
        } else {
            $ps = $pdo->prepare(
                "SELECT COALESCE(u.name, IF(cp.user_id=0,'Support','User')) AS n
                 FROM chat_participants cp
                 LEFT JOIN users u ON u.id=cp.user_id AND cp.user_id>0
                 WHERE cp.conversation_id=? AND cp.user_id!=? LIMIT 3"
            );
            $ps->execute([$cr['id'], $userId]);
            $pnames = array_column($ps->fetchAll(), 'n');
            $cr['display_title'] = $pnames ? implode(', ', $pnames) : ('Chat #' . $cr['id']);
        }
        $lm = $cr['last_message'] ?? '';
        if ($cr['last_message_type'] === 'image') $lm = '📷 Photo';
        elseif ($cr['last_message_type'] === 'file') $lm = '�� File';
        $cr['last_preview'] = mb_substr($lm, 0, 55);
    }
    unset($cr);

    $activeConv = null; $messages = []; $initialLastId = 0;
    if ($activeConvId) {
        foreach ($convRows as $cr) {
            if ($cr['id'] == $activeConvId) { $activeConv = $cr; break; }
        }
        if ($activeConv) {
            $ms = $pdo->prepare(
                "SELECT m.*, COALESCE(u.name, IF(m.sender_id=0,'Admin','User')) AS sender_name
                 FROM chat_messages m
                 LEFT JOIN users u ON u.id=m.sender_id AND m.sender_id>0
                 WHERE m.conversation_id=? AND m.is_deleted=0
                 ORDER BY m.created_at ASC LIMIT 100"
            );
            $ms->execute([$activeConvId]);
            $messages      = $ms->fetchAll();
            $initialLastId = $messages ? (int)end($messages)['id'] : 0;
            $pdo->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_id!=? AND is_read=0")->execute([$activeConvId, $userId]);
            $pdo->prepare("UPDATE chat_participants SET last_read_at=NOW() WHERE conversation_id=? AND user_id=?")->execute([$activeConvId, $userId]);
        }
    }
} catch (Exception $e) {
    $convRows=[]; $messages=[]; $activeConv=null; $initialLastId=0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chat - Client Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box;}
body{background:#f8fafc;height:100vh;overflow:hidden;margin:0;}
.sidebar{background:linear-gradient(180deg,#1e3a5f,#2563eb);width:220px;min-height:100vh;position:fixed;top:0;left:0;z-index:100;display:flex;flex-direction:column;}
.sidebar .nav-link{color:rgba(255,255,255,.85);padding:9px 18px;display:flex;align-items:center;gap:9px;font-size:.9rem;}
.sidebar .nav-link:hover,.sidebar .nav-link.active{background:rgba(255,255,255,.15);color:#fff;border-radius:8px;margin:2px 8px;padding:9px 10px;}
.chat-wrapper{margin-left:220px;height:100vh;display:flex;}
.conv-list{width:270px;border-right:1px solid #e2e8f0;background:#fff;overflow-y:auto;flex-shrink:0;}
.conv-item{padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:block;text-decoration:none;color:inherit;transition:background .15s;}
.conv-item:hover,.conv-item.active{background:#eff6ff;}
.conv-name{font-weight:600;font-size:.88rem;color:#1e293b;}
.conv-preview{font-size:.78rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.conv-time{font-size:.7rem;color:#94a3b8;}
.badge-unread{background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:.69rem;}
.chat-panel{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#f8fafc;}
.chat-header{background:#1e3a5f;color:#fff;padding:13px 18px;display:flex;align-items:center;gap:12px;flex-shrink:0;}
.chat-messages{flex:1;overflow-y:auto;padding:18px;}
.chat-msg{max-width:66%;padding:10px 14px;border-radius:16px;word-break:break-word;font-size:.93rem;}
.chat-msg-mine{background:#2563eb;color:#fff;border-bottom-right-radius:4px;}
.chat-msg-other{background:#fff;border:1px solid #e2e8f0;border-bottom-left-radius:4px;color:#1e293b;}
.chat-msg-name{font-size:.71rem;font-weight:700;margin-bottom:2px;opacity:.75;}
.chat-msg-time{font-size:.67rem;margin-top:3px;opacity:.6;text-align:right;}
.chat-input-bar{background:#fff;border-top:1px solid #e2e8f0;padding:11px 14px;display:flex;gap:8px;align-items:flex-end;flex-shrink:0;}
#chatInput{border-radius:20px;resize:none;max-height:100px;flex:1;}
#typingIndicator{padding:3px 18px;font-size:.78rem;color:#64748b;font-style:italic;min-height:18px;display:none;}
.no-conv{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#94a3b8;}
</style>
</head>
<body>

<div class="sidebar">
    <div class="p-3 border-bottom" style="border-color:rgba(255,255,255,.2)!important;">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="" style="max-height:30px;filter:brightness(10);">
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link" href="/client/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/client/invoices.php"><i class="bi bi-receipt"></i> Invoices</a>
        <a class="nav-link active" href="/client/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
        <a class="nav-link" href="/client/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="chat-wrapper">
    <!-- Conversation list -->
    <div class="conv-list">
        <div class="p-2 border-bottom fw-bold text-muted small text-uppercase">
            <i class="bi bi-chat-dots me-1"></i>Conversations
        </div>
        <?php if (empty($convRows)): ?>
        <div class="text-center text-muted p-4 small">No conversations yet.<br>Contact admin to start.</div>
        <?php endif; ?>
        <?php foreach ($convRows as $cr): ?>
        <a href="chat.php?conv=<?php echo (int)$cr['id']; ?>"
           class="conv-item <?php echo $cr['id']==$activeConvId?'active':''; ?>">
            <div class="d-flex justify-content-between align-items-start">
                <span class="conv-name"><?php echo h($cr['display_title']); ?></span>
                <?php if ($cr['unread_count'] > 0): ?>
                <span class="badge-unread"><?php echo (int)$cr['unread_count']; ?></span>
                <?php endif; ?>
            </div>
            <div class="conv-preview"><?php echo h($cr['last_preview'] ?? 'No messages'); ?></div>
            <div class="conv-time"><?php echo $cr['last_message_at'] ? timeAgo($cr['last_message_at']) : ''; ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Chat panel -->
    <div class="chat-panel">
        <?php if ($activeConv): ?>
        <div class="chat-header">
            <i class="bi bi-chat-dots fs-5"></i>
            <div>
                <div class="fw-bold"><?php echo h($activeConv['display_title']); ?></div>
                <small style="opacity:.7;"><?php echo ucfirst(h($activeConv['type'])); ?> conversation</small>
            </div>
        </div>
        <div class="chat-messages" id="chatMessages">
            <?php foreach ($messages as $msg): ?>
            <div id="msg_<?php echo (int)$msg['id']; ?>"
                 class="d-flex <?php echo $msg['sender_id']==$userId?'justify-content-end':'justify-content-start'; ?> mb-2">
                <div class="chat-msg <?php echo $msg['sender_id']==$userId?'chat-msg-mine':'chat-msg-other'; ?>">
                    <?php if ($msg['sender_id'] != $userId): ?>
                    <div class="chat-msg-name"><?php echo h($msg['sender_name']); ?></div>
                    <?php endif; ?>
                    <div class="chat-msg-body">
                        <?php if ($msg['message_type']==='image'): ?>
                        <a href="/<?php echo h($msg['file_path']); ?>" target="_blank">
                            <img src="/<?php echo h($msg['file_path']); ?>" alt="<?php echo h($msg['file_name']); ?>"
                                 style="max-width:200px;max-height:180px;border-radius:8px;cursor:pointer;">
                        </a>
                        <?php elseif ($msg['message_type']==='file'): ?>
                        <a href="/<?php echo h($msg['file_path']); ?>" download
                           class="d-flex align-items-center gap-2 text-decoration-none <?php echo $msg['sender_id']==$userId?'text-white':''; ?>">
                            <i class="bi bi-file-earmark-arrow-down fs-4"></i>
                            <span><?php echo h($msg['file_name'] ?? $msg['message']); ?></span>
                        </a>
                        <?php else: ?>
                        <?php echo nl2br(h($msg['message'])); ?>
                        <?php endif; ?>
                    </div>
                    <div class="chat-msg-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="typingIndicator"></div>
        <div class="chat-input-bar">
            <label class="btn btn-outline-secondary btn-sm mb-0" title="Attach file">
                <i class="bi bi-paperclip"></i>
                <input type="file" id="chatFileInput" hidden
                       accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
            </label>
            <textarea id="chatInput" class="form-control" rows="1"
                      placeholder="Type a message…"></textarea>
            <button class="btn btn-primary px-3" id="sendBtn"><i class="bi bi-send"></i></button>
        </div>
        <?php else: ?>
        <div class="no-conv">
            <i class="bi bi-chat-dots" style="font-size:3.5rem;"></i>
            <p class="mt-3">Select a conversation to start chatting</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/chat.js"></script>
<?php if ($activeConv): ?>
<script>
var chat = new ChatManager({
    userId:       <?php echo (int)$userId; ?>,
    csrfToken:    <?php echo json_encode($csrf_token); ?>,
    basePath:     '',
    initialLastId: <?php echo (int)$initialLastId; ?>,
});
chat.startPolling(<?php echo (int)$activeConvId; ?>);
chat.scrollToBottom();
</script>
<?php endif; ?>
</body>
</html>
