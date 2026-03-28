<?php
/**
 * admin/live_contact_chat.php
 * Admin chat view for a specific live contact
 * Two-panel layout: contact info (left) + chat (right)
 */
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();
$flash      = getFlashMessage();

$contactId = (int)($_GET['id'] ?? 0);
if (!$contactId) {
    header('Location: live_contacts.php');
    exit;
}

// Load contact
try {
    $stmt = $pdo->prepare("SELECT * FROM live_contacts WHERE id = ?");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();
} catch (Exception $e) { $contact = null; }

if (!$contact) {
    flashMessage('error', 'Contact not found.');
    header('Location: live_contacts.php');
    exit;
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: live_contact_chat.php?id=' . $contactId);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'close') {
        try {
            $pdo->prepare("UPDATE live_contacts SET status='closed' WHERE id=?")->execute([$contactId]);
            flashMessage('success', 'Conversation closed.');
        } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        header('Location: live_contact_chat.php?id=' . $contactId);
        exit;
    }

    if ($action === 'convert') {
        try {
            $pdo->prepare("UPDATE live_contacts SET status='converted' WHERE id=?")->execute([$contactId]);
            flashMessage('success', 'Marked as converted client.');
        } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        header('Location: live_contact_chat.php?id=' . $contactId);
        exit;
    }

    if ($action === 'assign') {
        $assignedId = (int)($_POST['assigned_admin_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE live_contacts SET assigned_admin_id=? WHERE id=?")->execute([$assignedId ?: null, $contactId]);
            flashMessage('success', 'Developer/Admin assigned.');
        } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        header('Location: live_contact_chat.php?id=' . $contactId);
        exit;
    }
}

// Load messages
try {
    $msgStmt = $pdo->prepare(
        "SELECT * FROM live_contact_messages WHERE contact_id = ? ORDER BY created_at ASC LIMIT 200"
    );
    $msgStmt->execute([$contactId]);
    $messages = $msgStmt->fetchAll();
} catch (Exception $e) { $messages = []; }

// Mark guest messages as read
try {
    $pdo->prepare(
        "UPDATE live_contact_messages SET is_read=1 WHERE contact_id=? AND sender_type='guest' AND is_read=0"
    )->execute([$contactId]);
} catch (Exception $e) {}

// Load admins/developers for assignment
try {
    $assignables = $pdo->query(
        "SELECT id, name, role FROM users WHERE role IN ('admin','developer') AND is_active=1 ORDER BY role, name"
    )->fetchAll();
} catch (Exception $e) { $assignables = []; }

$lastMsgId = !empty($messages) ? (int)end($messages)['id'] : 0;

$statusBadges = [
    'new'       => 'warning',
    'chatting'  => 'primary',
    'converted' => 'success',
    'closed'    => 'secondary',
];

require_once 'includes/header.php';
?>

<div class="container-fluid px-0" style="height:calc(100vh - 56px);">
<div class="row g-0 h-100">

    <!-- ===== LEFT PANEL: Contact Info ===== -->
    <div class="col-lg-3 border-end bg-white overflow-auto" style="min-width:260px;">
        <div class="p-3">
            <a href="live_contacts.php" class="btn btn-sm btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left"></i> All Contacts
            </a>

            <!-- Status badge -->
            <div class="mb-3">
                <span class="badge bg-<?php echo $statusBadges[$contact['status']] ?? 'secondary'; ?> fs-6">
                    <?php echo ucfirst(h($contact['status'])); ?>
                </span>
            </div>

            <!-- Contact details -->
            <div class="mb-3">
                <div class="d-flex align-items-center mb-2">
                    <div style="width:46px;height:46px;border-radius:50%;background:#0d6efd22;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;">
                        👤
                    </div>
                    <div class="ms-2">
                        <strong><?php echo h($contact['name']); ?></strong><br>
                        <small class="text-muted"><?php echo h($contact['email']); ?></small>
                    </div>
                </div>
            </div>

            <ul class="list-unstyled small text-muted mb-3">
                <?php if ($contact['phone']): ?>
                <li class="mb-1"><i class="bi bi-telephone me-1"></i><?php echo h($contact['phone']); ?></li>
                <?php endif; ?>
                <li class="mb-1"><i class="bi bi-geo me-1"></i><?php echo h($contact['ip_address'] ?? '—'); ?></li>
                <li class="mb-1"><i class="bi bi-calendar me-1"></i><?php echo date('M j, Y H:i', strtotime($contact['created_at'])); ?></li>
                <?php if ($contact['user_agent']): ?>
                <li class="mb-1 text-truncate" title="<?php echo h($contact['user_agent']); ?>">
                    <i class="bi bi-globe me-1"></i><?php echo h(mb_strimwidth($contact['user_agent'], 0, 40, '…')); ?>
                </li>
                <?php endif; ?>
                <?php if ($contact['user_id']): ?>
                <li class="mb-1">
                    <i class="bi bi-person me-1"></i>
                    <a href="user_view.php?id=<?php echo (int)$contact['user_id']; ?>">View User Account</a>
                </li>
                <?php endif; ?>
            </ul>

            <hr>

            <!-- Quick actions -->
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> py-2 small alert-dismissible">
                <?php echo h($flash['message']); ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
                <?php if ($contact['status'] !== 'converted'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="convert">
                    <button type="submit" class="btn btn-success btn-sm w-100"
                            onclick="return confirm('Mark as converted client?')">
                        <i class="bi bi-person-check me-1"></i>Convert to Client
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($contact['status'] !== 'closed'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="close">
                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100"
                            onclick="return confirm('Close this conversation?')">
                        <i class="bi bi-x-circle me-1"></i>Close Conversation
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <hr>

            <!-- Assign Developer/Admin -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="assign">
                <label class="form-label small fw-semibold">Assign to</label>
                <select name="assigned_admin_id" class="form-select form-select-sm mb-2">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($assignables as $a): ?>
                    <option value="<?php echo (int)$a['id']; ?>"
                        <?php echo (int)$contact['assigned_admin_id'] === (int)$a['id'] ? 'selected' : ''; ?>>
                        <?php echo h($a['name']); ?> (<?php echo h($a['role']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                    <i class="bi bi-person-plus me-1"></i>Save Assignment
                </button>
            </form>
        </div>
    </div>

    <!-- ===== RIGHT PANEL: Chat ===== -->
    <div class="col-lg-9 d-flex flex-column" style="background:#f8fafc;">

        <!-- Chat header -->
        <div class="p-3 bg-white border-bottom d-flex align-items-center justify-content-between">
            <div>
                <strong><?php echo h($contact['name']); ?></strong>
                <small class="text-muted ms-2"><?php echo h($contact['email']); ?></small>
            </div>
            <span id="admin-poll-status" class="badge bg-success">● Live</span>
        </div>

        <!-- Messages area -->
        <div id="admin-messages" class="flex-grow-1 overflow-auto p-3">
            <?php foreach ($messages as $msg): ?>
            <div class="d-flex mb-3 <?php echo $msg['sender_type'] === 'admin' ? 'justify-content-end' : 'justify-content-start'; ?>">
                <div style="max-width:70%;">
                    <div class="small text-muted mb-1 <?php echo $msg['sender_type'] === 'admin' ? 'text-end' : ''; ?>">
                        <?php echo $msg['sender_type'] === 'admin' ? 'You (Admin)' : h($contact['name']); ?>
                        <span class="ms-1"><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                    </div>
                    <div class="p-2 px-3 rounded-3 <?php echo $msg['sender_type'] === 'admin' ? 'bg-primary text-white' : 'bg-white shadow-sm'; ?>"
                         style="<?php echo $msg['sender_type'] === 'admin' ? 'border-radius: 14px 14px 4px 14px!important;' : 'border-radius: 4px 14px 14px 14px!important;'; ?>">
                        <?php echo nl2br(h($msg['message'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($messages)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-chat-dots" style="font-size:2rem;opacity:.3;"></i>
                <p class="mt-2">No messages yet.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Send area -->
        <?php if ($contact['status'] !== 'closed'): ?>
        <div class="p-3 bg-white border-top d-flex gap-2 align-items-end">
            <textarea id="admin-input" class="form-control" rows="2"
                      placeholder="Type a reply…" style="resize:none;border-radius:10px;"
                      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();adminSend();}"></textarea>
            <button class="btn btn-primary px-4" onclick="adminSend()">
                <i class="bi bi-send"></i>
            </button>
        </div>
        <?php else: ?>
        <div class="p-3 bg-white border-top text-center text-muted small">
            This conversation is closed.
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="reopen">
                <button type="submit" class="btn btn-link btn-sm p-0 ms-1">Re-open</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
var adminLastId = <?php echo $lastMsgId; ?>;
var adminContactId = <?php echo $contactId; ?>;
var adminPollTimer = null;
var contactStatus = <?php echo json_encode($contact['status']); ?>;

function adminScrollBottom() {
    var el = document.getElementById('admin-messages');
    el.scrollTop = el.scrollHeight;
}
adminScrollBottom();

function adminSend() {
    var input = document.getElementById('admin-input');
    var msg   = (input.value || '').trim();
    if (!msg) return;

    var fd = new FormData();
    fd.append('contact_id', adminContactId);
    fd.append('message', msg);

    fetch('/api/live-contact/send.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                input.value = '';
                adminFetch();
            } else {
                alert(d.error || 'Failed to send.');
            }
        })
        .catch(function() { alert('Connection error.'); });
}

function adminFetch() {
    if (contactStatus === 'closed') return;
    fetch('/api/live-contact/poll.php?contact_id=' + adminContactId + '&last_id=' + adminLastId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) return;
            if (d.status === 'closed') {
                contactStatus = 'closed';
                document.getElementById('admin-poll-status').className = 'badge bg-secondary';
                document.getElementById('admin-poll-status').textContent = '● Closed';
                clearInterval(adminPollTimer);
            }
            if (d.messages && d.messages.length) {
                var container = document.getElementById('admin-messages');
                d.messages.forEach(function(msg) {
                    var isAdmin = msg.sender_type === 'admin';
                    var wrap = document.createElement('div');
                    wrap.className = 'd-flex mb-3 ' + (isAdmin ? 'justify-content-end' : 'justify-content-start');
                    var name = isAdmin ? 'You (Admin)' : <?php echo json_encode(h($contact['name'])); ?>;
                    var timeStr = msg.created_at ? new Date(msg.created_at.replace(' ', 'T')).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '';
                    var esc = function(s) { var d2 = document.createElement('div'); d2.textContent = s || ''; return d2.innerHTML; };
                    wrap.innerHTML =
                        '<div style="max-width:70%;">' +
                        '<div class="small text-muted mb-1 ' + (isAdmin ? 'text-end' : '') + '">' + esc(name) + ' <span class="ms-1">' + timeStr + '</span></div>' +
                        '<div class="p-2 px-3 rounded-3 ' + (isAdmin ? 'bg-primary text-white' : 'bg-white shadow-sm') + '" style="' + (isAdmin ? 'border-radius:14px 14px 4px 14px!important;' : 'border-radius:4px 14px 14px 14px!important;') + '">' +
                        esc(msg.message).replace(/\n/g,'<br>') +
                        '</div></div>';
                    container.appendChild(wrap);
                    adminLastId = Math.max(adminLastId, parseInt(msg.id) || 0);
                });
                adminScrollBottom();
            }
        })
        .catch(function() {
            document.getElementById('admin-poll-status').className = 'badge bg-warning text-dark';
            document.getElementById('admin-poll-status').textContent = '● Reconnecting…';
        });
}

// Start polling every 5 seconds
adminPollTimer = setInterval(adminFetch, 5000);

// Sound on new guest message
var lastKnownId = adminLastId;
setInterval(function() {
    if (adminLastId > lastKnownId) {
        lastKnownId = adminLastId;
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var o = ctx.createOscillator(); var g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.frequency.value = 660;
            g.gain.setValueAtTime(0.25, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            o.start(ctx.currentTime); o.stop(ctx.currentTime + 0.35);
        } catch(e) {}
    }
}, 500);
</script>

<?php require_once 'includes/footer.php'; ?>
