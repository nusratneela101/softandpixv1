<?php
/**
 * Admin — Push Notification Settings & Broadcast
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/language.php';
require_once dirname(__DIR__) . '/includes/push_helper.php';
require_once dirname(__DIR__) . '/includes/activity_logger.php';
requireAdmin();

$csrf_token = generateCsrfToken();
$config     = _load_vapid_config();

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: push_settings.php');
        exit;
    }

    $postAction = $_POST['action'] ?? '';

    // ── Broadcast to all users ─────────────────────────────────────────────────
    if ($postAction === 'broadcast') {
        $pushTitle = trim($_POST['push_title'] ?? '');
        $pushBody  = trim($_POST['push_body'] ?? '');
        $pushUrl   = trim($_POST['push_url'] ?? '/');

        if ($pushTitle === '' || $pushBody === '') {
            flashMessage('error', 'Title and message are required.');
        } else {
            $totalSent = 0;
            try {
                // Get all active subscription user IDs (distinct)
                _ensure_push_table($pdo);
                $userIds = $pdo->query(
                    "SELECT DISTINCT user_id FROM push_subscriptions WHERE is_active = 1"
                )->fetchAll(PDO::FETCH_COLUMN);

                foreach ($userIds as $uid) {
                    $totalSent += send_push_notification($pdo, (int)$uid, $pushTitle, $pushBody, $pushUrl ?: '/');
                }
                log_activity($pdo, (int)$_SESSION['user_id'], 'push_broadcast',
                    "Broadcast push to " . count($userIds) . " users ({$totalSent} deliveries): {$pushTitle}",
                    'push', null);
                $deliveryWord = $totalSent === 1 ? 'delivery' : 'deliveries';
                flashMessage('success', "Push broadcast sent to " . count($userIds) . " user(s) with {$totalSent} successful {$deliveryWord}.");
            } catch (Exception $e) {
                flashMessage('error', 'Broadcast failed: ' . $e->getMessage());
            }
        }
        header('Location: push_settings.php');
        exit;
    }

    // ── Send to specific user ──────────────────────────────────────────────────
    if ($postAction === 'send_user') {
        $targetUser = (int)($_POST['target_user_id'] ?? 0);
        $pushTitle  = trim($_POST['push_title'] ?? '');
        $pushBody   = trim($_POST['push_body'] ?? '');
        $pushUrl    = trim($_POST['push_url'] ?? '/');

        if ($targetUser <= 0 || $pushTitle === '' || $pushBody === '') {
            flashMessage('error', 'User, title, and message are required.');
        } else {
            $sent = send_push_notification($pdo, $targetUser, $pushTitle, $pushBody, $pushUrl ?: '/');
            log_activity($pdo, (int)$_SESSION['user_id'], 'push_send_user',
                "Sent push to user #{$targetUser} ({$sent} deliveries): {$pushTitle}", 'push', $targetUser);
            if ($sent > 0) {
                $deliveryWord = $sent === 1 ? 'delivery' : 'deliveries';
                flashMessage('success', "Push notification sent successfully ({$sent} {$deliveryWord}).");
            } else {
                flashMessage('warning', 'No active push subscriptions found for that user.');
            }
        }
        header('Location: push_settings.php');
        exit;
    }
}

// ── Stats ──────────────────────────────────────────────────────────────────────
$totalSubscriptions  = 0;
$activeSubscriptions = 0;
$subscribedUsers     = 0;
$recentSubs          = [];
$allUsers            = [];

try {
    _ensure_push_table($pdo);
    $totalSubscriptions  = (int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();
    $activeSubscriptions = (int)$pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE is_active = 1")->fetchColumn();
    $subscribedUsers     = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions WHERE is_active = 1")->fetchColumn();
    $recentSubs          = $pdo->query(
        "SELECT ps.*, u.name as user_name, u.email as user_email
         FROM push_subscriptions ps
         LEFT JOIN users u ON u.id = ps.user_id
         ORDER BY ps.created_at DESC LIMIT 20"
    )->fetchAll();
} catch (Exception $e) {}

try {
    $allUsers = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
} catch (Exception $e) {}

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="fas fa-bell me-2"></i><?= __('push_notifications') ?></h1>
        <p class="text-muted">Manage web push notification settings and send broadcasts.</p>
    </div>
</div>

<div class="container-fluid">
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 text-primary fw-bold"><?= $activeSubscriptions ?></div>
                    <div class="text-muted small mt-1"><?= __('push_subscriptions') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 text-success fw-bold"><?= $subscribedUsers ?></div>
                    <div class="text-muted small mt-1">Subscribed Users</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6 fw-bold <?= ($config['enabled'] && !empty($config['vapid_public_key'])) ? 'text-success' : 'text-danger' ?>">
                        <?= ($config['enabled'] && !empty($config['vapid_public_key'])) ? 'ON' : 'OFF' ?>
                    </div>
                    <div class="text-muted small mt-1">Push Service Status</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-6">
            <!-- VAPID Config Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-key me-2"></i><?= __('push_vapid_config') ?>
                </div>
                <div class="card-body">
                    <?php if (empty($config['vapid_public_key'])): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            VAPID keys are not configured. Push notifications are disabled.
                        </div>
                        <p class="text-muted small"><?= __('push_keys_instruction') ?></p>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><?= __('push_vapid_public_key') ?></label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace small"
                                    value="<?= h(substr($config['vapid_public_key'], 0, 40)) ?>..."
                                    readonly>
                                <button class="btn btn-outline-secondary btn-sm" type="button"
                                    onclick="navigator.clipboard.writeText('<?= h($config['vapid_public_key']) ?>')"
                                    title="Copy full key"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold"><?= __('push_vapid_subject') ?></label>
                            <input type="text" class="form-control" value="<?= h($config['vapid_subject']) ?>" readonly>
                        </div>
                        <div class="alert alert-info mb-0 small">
                            <i class="fas fa-info-circle me-1"></i>
                            To rotate keys, re-run <code>php cli/generate_vapid_keys.php --write-env</code>
                            and restart your web server.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Broadcast Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-broadcast-tower me-2"></i><?= __('push_broadcast') ?>
                </div>
                <div class="card-body">
                    <?php if (!$config['enabled'] || empty($config['vapid_public_key'])): ?>
                        <div class="alert alert-secondary">Push notifications are not configured.</div>
                    <?php else: ?>
                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" id="broadcastTabs">
                            <li class="nav-item">
                                <a class="nav-link active" href="#" data-bs-toggle="tab" data-bs-target="#tabAll">
                                    <i class="fas fa-users me-1"></i><?= __('push_broadcast_all') ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#" data-bs-toggle="tab" data-bs-target="#tabUser">
                                    <i class="fas fa-user me-1"></i><?= __('push_broadcast_user') ?>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <!-- All Users -->
                            <div class="tab-pane fade show active" id="tabAll">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                    <input type="hidden" name="action" value="broadcast">
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_title') ?> <span class="text-danger">*</span></label>
                                        <input type="text" name="push_title" class="form-control" maxlength="100" required
                                            placeholder="e.g. New Feature Available">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_body') ?> <span class="text-danger">*</span></label>
                                        <textarea name="push_body" class="form-control" rows="3" maxlength="200" required
                                            placeholder="Notification message..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_url') ?></label>
                                        <input type="url" name="push_url" class="form-control" placeholder="https://...">
                                    </div>
                                    <button type="submit" class="btn btn-primary"
                                        onclick="return confirm('Send push to all <?= $subscribedUsers ?> subscribed user(s)?')">
                                        <i class="fas fa-paper-plane me-1"></i>Send to All
                                    </button>
                                </form>
                            </div>
                            <!-- Specific User -->
                            <div class="tab-pane fade" id="tabUser">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                    <input type="hidden" name="action" value="send_user">
                                    <div class="mb-3">
                                        <label class="form-label">Target User <span class="text-danger">*</span></label>
                                        <select name="target_user_id" class="form-select" required>
                                            <option value="">— Select a user —</option>
                                            <?php foreach ($allUsers as $u): ?>
                                                <option value="<?= (int)$u['id'] ?>">
                                                    <?= h($u['name']) ?> &lt;<?= h($u['email']) ?>&gt;
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_title') ?> <span class="text-danger">*</span></label>
                                        <input type="text" name="push_title" class="form-control" maxlength="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_body') ?> <span class="text-danger">*</span></label>
                                        <textarea name="push_body" class="form-control" rows="3" maxlength="200" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><?= __('push_broadcast_url') ?></label>
                                        <input type="url" name="push_url" class="form-control" placeholder="https://...">
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-1"></i>Send
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Subscriptions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i>Recent Subscriptions</span>
                    <span class="badge bg-primary"><?= $activeSubscriptions ?> active / <?= $totalSubscriptions ?> total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentSubs)): ?>
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>
                            No push subscriptions yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Status</th>
                                        <th>Errors</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSubs as $sub): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold small"><?= h($sub['user_name'] ?? 'Unknown') ?></div>
                                                <div class="text-muted" style="font-size:11px;"><?= h($sub['user_agent'] ? substr($sub['user_agent'], 0, 30) . '…' : '—') ?></div>
                                            </td>
                                            <td>
                                                <?php if ($sub['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($sub['error_count'] > 0): ?>
                                                    <span class="badge bg-warning text-dark"><?= (int)$sub['error_count'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= date('M j, Y', strtotime($sub['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
