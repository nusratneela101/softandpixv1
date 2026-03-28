<?php
/**
 * admin/live_contacts.php
 * Admin Live Contact Dashboard — list all live contacts, filter by status, open chat
 */
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();
$flash      = getFlashMessage();

// Handle quick actions (close, re-open)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: live_contacts.php');
        exit;
    }

    $action    = $_POST['action'] ?? '';
    $contactId = (int)($_POST['contact_id'] ?? 0);

    if ($contactId) {
        if ($action === 'close') {
            try {
                $pdo->prepare("UPDATE live_contacts SET status='closed' WHERE id=?")->execute([$contactId]);
                flashMessage('success', 'Contact closed.');
            } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        } elseif ($action === 'reopen') {
            try {
                $pdo->prepare("UPDATE live_contacts SET status='chatting' WHERE id=?")->execute([$contactId]);
                flashMessage('success', 'Contact re-opened.');
            } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        } elseif ($action === 'convert') {
            // Mark as converted
            try {
                $pdo->prepare("UPDATE live_contacts SET status='converted' WHERE id=?")->execute([$contactId]);
                flashMessage('success', 'Contact marked as converted.');
            } catch (Exception $e) { flashMessage('error', 'Database error.'); }
        }
    }

    header('Location: live_contacts.php');
    exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$allowed      = ['new', 'chatting', 'converted', 'closed'];

try {
    if ($statusFilter && in_array($statusFilter, $allowed)) {
        $stmt = $pdo->prepare(
            "SELECT lc.*, 
                    (SELECT COUNT(*) FROM live_contact_messages lcm 
                     WHERE lcm.contact_id = lc.id AND lcm.is_read = 0 AND lcm.sender_type = 'guest') AS unread_count,
                    (SELECT lcm2.message FROM live_contact_messages lcm2 
                     WHERE lcm2.contact_id = lc.id ORDER BY lcm2.created_at DESC LIMIT 1) AS last_message,
                    (SELECT lcm2.created_at FROM live_contact_messages lcm2 
                     WHERE lcm2.contact_id = lc.id ORDER BY lcm2.created_at DESC LIMIT 1) AS last_msg_at
             FROM live_contacts lc
             WHERE lc.status = ?
             ORDER BY lc.updated_at DESC"
        );
        $stmt->execute([$statusFilter]);
    } else {
        $stmt = $pdo->query(
            "SELECT lc.*, 
                    (SELECT COUNT(*) FROM live_contact_messages lcm 
                     WHERE lcm.contact_id = lc.id AND lcm.is_read = 0 AND lcm.sender_type = 'guest') AS unread_count,
                    (SELECT lcm2.message FROM live_contact_messages lcm2 
                     WHERE lcm2.contact_id = lc.id ORDER BY lcm2.created_at DESC LIMIT 1) AS last_message,
                    (SELECT lcm2.created_at FROM live_contact_messages lcm2 
                     WHERE lcm2.contact_id = lc.id ORDER BY lcm2.created_at DESC LIMIT 1) AS last_msg_at
             FROM live_contacts lc
             ORDER BY lc.updated_at DESC"
        );
    }
    $contacts = $stmt->fetchAll();
} catch (Exception $e) {
    $contacts = [];
}

// Status counts for badges
try {
    $counts = [];
    $cRows  = $pdo->query("SELECT status, COUNT(*) AS cnt FROM live_contacts GROUP BY status")->fetchAll();
    foreach ($cRows as $r) $counts[$r['status']] = (int)$r['cnt'];
} catch (Exception $e) { $counts = []; }

// Total unread
try {
    $totalUnread = (int)$pdo->query(
        "SELECT COUNT(*) FROM live_contact_messages WHERE is_read=0 AND sender_type='guest'"
    )->fetchColumn();
} catch (Exception $e) { $totalUnread = 0; }

$statusBadges = [
    'new'       => 'warning',
    'chatting'  => 'primary',
    'converted' => 'success',
    'closed'    => 'secondary',
];

require_once 'includes/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Live Contacts</h4>
        <?php if ($totalUnread > 0): ?>
        <span class="badge bg-danger fs-6"><?php echo $totalUnread; ?> unread</span>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo !$statusFilter ? 'active' : ''; ?>" href="live_contacts.php">
                All <span class="badge bg-secondary ms-1"><?php echo array_sum($counts); ?></span>
            </a>
        </li>
        <?php foreach (['new' => 'warning', 'chatting' => 'primary', 'converted' => 'success', 'closed' => 'secondary'] as $s => $color): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $statusFilter === $s ? 'active' : ''; ?>" href="live_contacts.php?status=<?php echo $s; ?>">
                <?php echo ucfirst($s); ?>
                <span class="badge bg-<?php echo $color; ?> ms-1"><?php echo $counts[$s] ?? 0; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($contacts)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-chat-dots" style="font-size:3rem;opacity:.3;"></i>
        <p class="mt-2">No contacts found.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Name / Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Last Message</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $c): ?>
                <tr>
                    <td><?php echo (int)$c['id']; ?></td>
                    <td>
                        <strong><?php echo h($c['name']); ?></strong>
                        <?php if ($c['unread_count'] > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo (int)$c['unread_count']; ?></span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted"><?php echo h($c['email']); ?></small>
                    </td>
                    <td><?php echo $c['phone'] ? h($c['phone']) : '<span class="text-muted">—</span>'; ?></td>
                    <td>
                        <span class="badge bg-<?php echo $statusBadges[$c['status']] ?? 'secondary'; ?>">
                            <?php echo ucfirst(h($c['status'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($c['last_message']): ?>
                        <span class="text-muted" style="font-size:.82rem;">
                            <?php echo h(mb_strimwidth($c['last_message'], 0, 60, '…')); ?>
                        </span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <small class="text-muted"><?php echo date('M j, Y', strtotime($c['created_at'])); ?></small>
                        <?php if ($c['last_msg_at']): ?>
                        <br><small class="text-muted"><?php echo date('H:i', strtotime($c['last_msg_at'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="live_contact_chat.php?id=<?php echo (int)$c['id']; ?>" class="btn btn-sm btn-primary me-1">
                            <i class="bi bi-chat"></i> Chat
                        </a>
                        <?php if ($c['status'] !== 'closed'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <input type="hidden" name="contact_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="action" value="close">
                            <button type="submit" class="btn btn-sm btn-outline-secondary me-1"
                                    onclick="return confirm('Close this contact?')">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <input type="hidden" name="contact_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="action" value="reopen">
                            <button type="submit" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php if ($c['status'] !== 'converted' && $c['status'] !== 'closed'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                            <input type="hidden" name="contact_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="action" value="convert">
                            <button type="submit" class="btn btn-sm btn-outline-success"
                                    title="Mark as Converted Client"
                                    onclick="return confirm('Mark this contact as converted client?')">
                                <i class="bi bi-person-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-refresh the page every 30s to pick up new contacts
setTimeout(function () {
    if (document.visibilityState === 'visible') location.reload();
}, 30000);
</script>

<?php require_once 'includes/footer.php'; ?>
