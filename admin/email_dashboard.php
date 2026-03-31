<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$error = '';
$success = '';
$csrf_token = generateCsrfToken();

try {
    $users = $pdo->query("SELECT id, name, email, role FROM users WHERE is_active=1 ORDER BY name")->fetchAll();
    $roles = $pdo->query("SELECT role_name, role_label FROM custom_roles ORDER BY role_label")->fetchAll();
} catch (Exception $e) { $users=[]; $roles=[]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $subject   = trim($_POST['subject'] ?? '');
        $body      = trim($_POST['body'] ?? '');
        $sendTo    = $_POST['send_to'] ?? 'individual';
        $userId    = (int)($_POST['user_id'] ?? 0);
        $roleTarget= trim($_POST['role_target'] ?? '');
        
        if (empty($subject) || empty($body)) {
            $error = 'Subject and body are required.';
        } else {
            require_once dirname(__DIR__) . '/includes/email.php';
            $recipients = [];
            
            if ($sendTo === 'individual' && $userId > 0) {
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id=?");
                $stmt->execute([$userId]);
                $u = $stmt->fetch();
                if ($u) $recipients[] = $u;
            } elseif ($sendTo === 'role' && !empty($roleTarget)) {
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE role=? AND is_active=1");
                $stmt->execute([$roleTarget]);
                $recipients = $stmt->fetchAll();
            } elseif ($sendTo === 'all') {
                $recipients = $pdo->query("SELECT name, email FROM users WHERE is_active=1")->fetchAll();
            }
            
            $sent = 0; $failed = 0;
            foreach ($recipients as $r) {
                $htmlBody = nl2br(h($body));
                if (sendEmail($pdo, $r['email'], $subject, $htmlBody)) $sent++;
                else $failed++;
            }
            
            if ($sent > 0) $success = "Sent $sent email(s) successfully." . ($failed > 0 ? " $failed failed." : '');
            else $error = "Failed to send emails. Check SMTP settings.";
        }
    }
}
require_once 'includes/header.php';
?>
<div class="page-header"><h1><i class="bi bi-send me-2"></i>Send Email</h1></div>
<div class="container-fluid">
    <div class="row g-4">
        <div class="col-md-8">
            <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo h($success); ?></div><?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Send To</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="send_to" id="toIndividual" value="individual" <?php echo ($_POST['send_to'] ?? 'individual') === 'individual' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="toIndividual">Individual</label>
                                <input type="radio" class="btn-check" name="send_to" id="toRole" value="role" <?php echo ($_POST['send_to'] ?? '') === 'role' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="toRole">By Role</label>
                                <input type="radio" class="btn-check" name="send_to" id="toAll" value="all" <?php echo ($_POST['send_to'] ?? '') === 'all' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-danger" for="toAll">All Users</label>
                            </div>
                        </div>
                        <div class="mb-3" id="individualSelect" <?php echo ($_POST['send_to'] ?? 'individual') !== 'individual' ? 'style="display:none"' : ''; ?>>
                            <label class="form-label fw-semibold">Select User</label>
                            <select name="user_id" class="form-select">
                                <option value="">— Select user —</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo (int)($_POST['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($u['name']); ?> &lt;<?php echo h($u['email']); ?>&gt;
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 <?php echo ($_POST['send_to'] ?? '') !== 'role' ? 'd-none' : ''; ?>" id="roleSelect">
                            <label class="form-label fw-semibold">Select Role</label>
                            <select name="role_target" class="form-select">
                                <option value="">— Select role —</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?php echo h($r['role_name']); ?>" <?php echo ($_POST['role_target'] ?? '') === $r['role_name'] ? 'selected' : ''; ?>><?php echo h($r['role_label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Subject *</label>
                            <input type="text" name="subject" class="form-control" value="<?php echo h($_POST['subject'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message *</label>
                            <textarea name="body" class="form-control" rows="10" required><?php echo h($_POST['body'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-2"></i>Send Email</button>
                        <a href="email_sent.php" class="btn btn-outline-secondary ms-2"><i class="bi bi-clock-history me-1"></i>Sent Emails</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white"><i class="bi bi-info-circle me-2"></i>Tips</div>
                <div class="card-body small text-muted">
                    <ul class="mb-0">
                        <li class="mb-2"><strong>Individual:</strong> Send to one specific user.</li>
                        <li class="mb-2"><strong>By Role:</strong> Send to all users with a selected role.</li>
                        <li class="mb-2"><strong>All Users:</strong> Broadcast to all active users.</li>
                        <li class="mb-2">Configure SMTP in <a href="settings.php">Settings</a> first.</li>
                    </ul>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-file-earmark-text me-2"></i>Quick Templates</div>
                <div class="list-group list-group-flush">
                    <a href="email_templates.php" class="list-group-item list-group-item-action small">Manage Templates</a>
                    <a href="email_sent.php" class="list-group-item list-group-item-action small">View Sent Emails</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('[name="send_to"]').forEach(function(r) {
    r.addEventListener('change', function() {
        document.getElementById('individualSelect').style.display = this.value === 'individual' ? '' : 'none';
        document.getElementById('roleSelect').classList.toggle('d-none', this.value !== 'role');
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
