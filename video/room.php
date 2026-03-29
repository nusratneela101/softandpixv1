<?php
/**
 * Video Call Room — full video calling UI.
 */
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$user_id   = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'client';
$room_code = trim($_GET['code'] ?? '');

if (empty($room_code)) {
    header('Location: /video/join.php');
    exit;
}

// Check room exists
try {
    $stmt = $pdo->prepare("SELECT * FROM video_rooms WHERE room_code = ? LIMIT 1");
    $stmt->execute([$room_code]);
    $room = $stmt->fetch();
} catch (Exception $e) {
    $room = null;
}

if (!$room) {
    header('Location: /video/join.php?error=notfound');
    exit;
}

$is_host = ((int)$room['created_by'] === $user_id);
$needs_password = (!empty($room['password']) && !$is_host);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($room['room_name']); ?> — Video Call</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="/public/assets/css/video.css" rel="stylesheet">
</head>
<body data-user-role="<?php echo h($user_role); ?>">

<div class="video-room-container" id="videoRoomContainer">
    <!-- Header -->
    <div class="video-room-header">
        <div class="room-info">
            <span class="room-name"><?php echo h($room['room_name']); ?></span>
            <span class="room-timer" id="roomTimer">00:00:00</span>
            <span class="room-code-badge" id="roomCodeBadge" title="Click to copy">
                <i class="fas fa-copy me-1"></i><?php echo h($room_code); ?>
            </span>
        </div>
        <div class="participant-count">
            <i class="fas fa-users"></i>
            <span id="participantCount">1</span>
        </div>
    </div>

    <!-- Video Grid Area -->
    <div class="video-grid-area">
        <div class="video-grid grid-1" id="videoGrid">
            <!-- Video tiles inserted dynamically -->
        </div>
        <!-- Local PiP inserted by JS -->
    </div>

    <!-- Control Bar -->
    <div class="video-controls">
        <button class="ctrl-btn active-on" id="btnMic" title="Toggle Microphone (M)" onclick="vcm.toggleMic()">
            <i class="fas fa-microphone"></i><span class="shortcut-hint">M</span>
        </button>
        <button class="ctrl-btn active-on" id="btnCamera" title="Toggle Camera (V)" onclick="vcm.toggleCamera()">
            <i class="fas fa-video"></i><span class="shortcut-hint">V</span>
        </button>
        <button class="ctrl-btn active-on" id="btnScreen" title="Share Screen (S)" onclick="vcm.toggleScreenShare()">
            <i class="fas fa-desktop"></i><span class="shortcut-hint">S</span>
        </button>
        <button class="ctrl-btn active-on" id="btnChat" title="Chat" onclick="toggleChatPanel()">
            <i class="fas fa-comment"></i>
            <span class="badge-count" id="chatBadge" style="display:none;">0</span>
        </button>
        <button class="ctrl-btn active-on" id="btnParticipants" title="Participants" onclick="toggleParticipantsPanel()">
            <i class="fas fa-users"></i>
        </button>
        <button class="ctrl-btn end-call" id="btnEndCall" title="End Call" onclick="endCallConfirm()">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>
</div>

<!-- Chat Side Panel -->
<div class="side-panel" id="chatPanel">
    <div class="panel-header">
        <h3><i class="fas fa-comment me-2"></i>In-Call Chat</h3>
        <button class="panel-close" onclick="toggleChatPanel()">&times;</button>
    </div>
    <div class="chat-messages" id="chatMessages">
        <!-- Messages inserted dynamically -->
    </div>
    <div class="chat-input-area">
        <input type="text" id="chatInput" placeholder="Type a message..." maxlength="500"
               onkeydown="if(event.key==='Enter'){sendChat();}">
        <button onclick="sendChat()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<!-- Participants Side Panel -->
<div class="side-panel" id="participantsPanel">
    <div class="panel-header">
        <h3><i class="fas fa-users me-2"></i>Participants</h3>
        <button class="panel-close" onclick="toggleParticipantsPanel()">&times;</button>
    </div>
    <div class="participants-list" id="participantsList">
        <!-- Populated dynamically -->
    </div>
</div>

<!-- Password Modal -->
<?php if ($needs_password): ?>
<div class="modal fade show" id="passwordModal" tabindex="-1" style="display:block;background:rgba(0,0,0,0.8);">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="background:#1a1a2e;color:#fff;border:1px solid rgba(255,255,255,0.1);">
            <div class="modal-body p-4">
                <h5 class="mb-3">Enter Room Password</h5>
                <input type="password" id="joinPassword" class="form-control mb-3"
                       style="background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.2);color:#fff;"
                       placeholder="Password">
                <button class="btn btn-primary w-100" onclick="submitPassword()">Join</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="/public/assets/js/webrtc.js"></script>
<script>
const vcm = new VideoCallManager({
    roomCode: '<?php echo addslashes($room_code); ?>',
    userId: <?php echo $user_id; ?>,
    userName: '<?php echo addslashes($user_name); ?>'
});

<?php if (!$needs_password): ?>
// Auto-init and join
(async function() {
    const ok = await vcm.init();
    if (!ok) return;
    try {
        await vcm.joinRoom('<?php echo addslashes($room_code); ?>');
        refreshParticipants();
        setInterval(refreshParticipants, 5000);
    } catch(err) {
        alert(err.message || 'Failed to join room');
        window.history.back();
    }
})();
<?php endif; ?>

function submitPassword() {
    (async function() {
        const ok = await vcm.init();
        if (!ok) return;
        try {
            await vcm.joinRoom('<?php echo addslashes($room_code); ?>');
            document.getElementById('passwordModal').style.display = 'none';
            refreshParticipants();
            setInterval(refreshParticipants, 5000);
        } catch(err) {
            alert(err.message || 'Failed to join room');
        }
    })();
}

function toggleChatPanel() {
    const panel = document.getElementById('chatPanel');
    const partPanel = document.getElementById('participantsPanel');
    if (partPanel.classList.contains('open')) partPanel.classList.remove('open');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        vcm.unreadChat = 0;
        vcm._updateChatBadge();
        document.getElementById('chatInput').focus();
    }
}

function toggleParticipantsPanel() {
    const panel = document.getElementById('participantsPanel');
    const chatPanel = document.getElementById('chatPanel');
    if (chatPanel.classList.contains('open')) chatPanel.classList.remove('open');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) refreshParticipants();
}

function sendChat() {
    const input = document.getElementById('chatInput');
    vcm.sendChatMessage(input.value);
    input.value = '';
}

function endCallConfirm() {
    if (confirm('Are you sure you want to leave the call?')) {
        vcm.endCall(<?php echo $is_host ? 'true' : 'false'; ?>);
    }
}

function refreshParticipants() {
    fetch('/video/signaling.php?action=get_participants&room_id=' + vcm.roomId, {credentials:'same-origin'})
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const list = document.getElementById('participantsList');
            if (!list) return;
            list.innerHTML = data.participants.map(p => `
                <div class="participant-item">
                    <div class="p-avatar">${(p.name||'U').charAt(0).toUpperCase()}</div>
                    <div class="p-info">
                        <div class="p-name">${escHtml(p.name||'User')}${p.user_id==<?php echo $user_id;?> ? ' (You)' : ''}</div>
                        <div class="p-role">${p.role}</div>
                    </div>
                    <div class="p-status-icons">
                        ${p.is_muted == 1 ? '<i class="fas fa-microphone-slash muted"></i>' : '<i class="fas fa-microphone"></i>'}
                        ${p.is_video_on == 0 ? '<i class="fas fa-video-slash video-off"></i>' : '<i class="fas fa-video"></i>'}
                    </div>
                </div>
            `).join('');
            document.getElementById('participantCount').textContent = data.participants.length;
        }).catch(()=>{});
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
}

// Copy room code
document.getElementById('roomCodeBadge').addEventListener('click', function() {
    navigator.clipboard.writeText('<?php echo addslashes($room_code); ?>').then(() => {
        this.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        setTimeout(() => {
            this.innerHTML = '<i class="fas fa-copy me-1"></i><?php echo addslashes($room_code); ?>';
        }, 2000);
    });
});
</script>
</body>
</html>
