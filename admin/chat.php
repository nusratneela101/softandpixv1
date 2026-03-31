<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

// Handle new conversation creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_conv'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $title     = trim($_POST['title'] ?? '');
        $type      = in_array($_POST['type'] ?? '', ['project','direct','support']) ? $_POST['type'] : 'direct';
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $userIds   = array_map('intval', (array)($_POST['user_ids'] ?? []));
        if (!empty($userIds)) {
            try {
                $pdo->prepare("INSERT INTO chat_conversations (project_id, type, title) VALUES (?, ?, ?)")
                    ->execute([$projectId, $type, $title ?: null]);
                $convId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id, role) VALUES (?, 0, 'admin')")
                    ->execute([$convId]);
                $addPart = $pdo->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id, role) VALUES (?, ?, ?)");
                foreach ($userIds as $uid) {
                    $roleRow = $pdo->prepare("SELECT role FROM users WHERE id=?");
                    $roleRow->execute([$uid]);
                    $uRole    = $roleRow->fetchColumn() ?: 'client';
                    $chatRole = in_array($uRole, ['developer','editor','ui_designer','seo_specialist']) ? 'developer' : 'client';
                    $addPart->execute([$convId, $uid, $chatRole]);
                }
                header("Location: chat.php?conv=$convId"); exit;
            } catch (Exception $e) {}
        }
    }
}

$activeConvId = (int)($_GET['conv'] ?? 0);

try {
    $convRows = $pdo->query(
        "SELECT c.*,
            (SELECT COUNT(*) FROM chat_messages m
                WHERE m.conversation_id=c.id AND m.is_read=0 AND m.sender_id!=0) AS unread_count,
            (SELECT m2.message FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
            (SELECT m2.message_type FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message_type,
            (SELECT m2.created_at FROM chat_messages m2
                WHERE m2.conversation_id=c.id AND m2.is_deleted=0
                ORDER BY m2.created_at DESC LIMIT 1) AS last_message_at
         FROM chat_conversations c ORDER BY c.updated_at DESC LIMIT 100"
    )->fetchAll();

    foreach ($convRows as &$cr) {
        if (!empty($cr['title'])) {
            $cr['display_title'] = $cr['title'];
        } else {
            $ps = $pdo->prepare(
                "SELECT COALESCE(u.name,'User') AS n FROM chat_participants cp
                 LEFT JOIN users u ON u.id=cp.user_id AND cp.user_id>0
                 WHERE cp.conversation_id=? AND cp.user_id!=0 LIMIT 3"
            );
            $ps->execute([$cr['id']]);
            $pnames = array_column($ps->fetchAll(), 'n');
            $cr['display_title'] = $pnames ? implode(', ', $pnames) : ('Chat #' . $cr['id']);
        }
        $lm = $cr['last_message'] ?? '';
        if ($cr['last_message_type'] === 'image') $lm = '📷 Photo';
        elseif ($cr['last_message_type'] === 'file') $lm = '📎 File';
        $cr['last_preview'] = mb_substr($lm, 0, 55);
    }
    unset($cr);

    $activeConv = null; $messages = []; $initialLastId = 0;
    if ($activeConvId) {
        $cs = $pdo->prepare("SELECT * FROM chat_conversations WHERE id=?");
        $cs->execute([$activeConvId]);
        $activeConv = $cs->fetch();
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
            $pdo->prepare("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_id!=0")->execute([$activeConvId]);
            $pdo->prepare("INSERT INTO chat_participants (conversation_id,user_id,role,last_read_at) VALUES(?,0,'admin',NOW()) ON DUPLICATE KEY UPDATE last_read_at=NOW()")->execute([$activeConvId]);
        }
    }
    $users    = $pdo->query("SELECT id, name, role FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
    $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $convRows=[]; $messages=[]; $activeConv=null; $users=[]; $projects=[]; $initialLastId=0;
}

require_once 'includes/header.php';
?>
<style>
.chat-layout{display:flex;height:calc(100vh - 145px);min-height:480px;}
.conv-list{width:290px;min-width:200px;overflow-y:auto;border-right:1px solid #e2e8f0;background:#fff;flex-shrink:0;}
.conv-item{padding:11px 14px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:block;text-decoration:none;color:inherit;transition:background .15s;}
.conv-item:hover,.conv-item.active{background:#eff6ff;}
.conv-name{font-weight:600;font-size:.88rem;color:#1e293b;}
.conv-preview{font-size:.78rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.conv-time{font-size:.7rem;color:#94a3b8;}
.badge-unread{background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:.69rem;}
.chat-main{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#f8fafc;}
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

<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-chat-dots me-2"></i>Live Chat</h1></div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newConvModal">
        <i class="bi bi-plus-circle me-1"></i>New Conversation
    </button>
</div>

<div class="chat-layout">
    <!-- Conversation list -->
    <div class="conv-list">
        <div class="p-2 border-bottom">
            <input class="form-control form-control-sm" id="convSearch" placeholder="Search conversations…">
        </div>
        <?php if (empty($convRows)): ?>
        <div class="text-center text-muted p-4 small">No conversations yet.</div>
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
            <div class="conv-time"><?php echo $cr['last_message_at'] ? date('M j, H:i', strtotime($cr['last_message_at'])) : ''; ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Chat window -->
    <div class="chat-main">
        <?php if ($activeConv): ?>
        <div class="chat-header">
            <i class="bi bi-chat-dots fs-5"></i>
            <div>
                <div class="fw-bold"><?php echo h($activeConv['title'] ?: ('Chat #' . $activeConv['id'])); ?></div>
                <small style="opacity:.7;"><?php echo ucfirst(h($activeConv['type'])); ?> conversation</small>
            </div>
        </div>
        <div class="chat-messages" id="chatMessages">
            <?php foreach ($messages as $msg): ?>
            <div id="msg_<?php echo (int)$msg['id']; ?>"
                 class="d-flex <?php echo $msg['sender_id']==0?'justify-content-end':'justify-content-start'; ?> mb-2">
                <div class="chat-msg <?php echo $msg['sender_id']==0?'chat-msg-mine':'chat-msg-other'; ?>">
                    <?php if ($msg['sender_id'] != 0): ?>
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
                           class="d-flex align-items-center gap-2 text-decoration-none <?php echo $msg['sender_id']==0?'text-white':''; ?>">
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
            <p class="mt-3">Select a conversation or start a new one</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="modal fade" id="newConvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Conversation</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="create_conv" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title (optional)</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Project Alpha Chat">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Type</label>
                        <select name="type" class="form-select">
                            <option value="direct">Direct</option>
                            <option value="project">Project</option>
                            <option value="support">Support</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Link to Project (optional)</label>
                        <select name="project_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Participants *</label>
                        <select name="user_ids[]" class="form-select" multiple size="6" required>
                            <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>">
                                <?php echo h($u['name']); ?> (<?php echo h($u['role']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple users.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/chat.js"></script>
<script>
document.getElementById('convSearch').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(function (el) {
        el.style.display = el.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
<?php if ($activeConv): ?>
var chat = new ChatManager({
    userId:       0,
    csrfToken:    <?php echo json_encode($csrf_token); ?>,
    basePath:     '',
    initialLastId: <?php echo (int)$initialLastId; ?>,
});
chat.startPolling(<?php echo (int)$activeConvId; ?>);
chat.scrollToBottom();
<?php endif; ?>
</script>
<?php require_once 'includes/footer.php'; ?>
