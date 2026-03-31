<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_all_read') {
            try { $pdo->query("UPDATE notifications SET is_read=1"); flashMessage('success','All marked as read.'); }
            catch (Exception $e) {}
        } elseif ($action === 'delete' && isset($_POST['notif_id'])) {
            $nid = (int)$_POST['notif_id'];
            try { $pdo->prepare("DELETE FROM notifications WHERE id=?")->execute([$nid]); }
            catch (Exception $e) {}
        } elseif ($action === 'clear_all') {
            try { $pdo->query("DELETE FROM notifications WHERE is_read=1"); flashMessage('success','Cleared read notifications.'); }
            catch (Exception $e) {}
        }
    }
    header('Location: notifications.php'); exit;
}

$page = max(1,(int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page-1)*$perPage;

try {
    $total = (int)$pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    $notifs = $pdo->query("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON u.id=n.user_id ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    $unreadCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read=0")->fetchColumn();
} catch (Exception $e) { $notifs=[]; $total=0; $unreadCount=0; }

$totalPages = (int)ceil($total/$perPage);
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-bell me-2"></i>Notifications</h1>
        <?php if ($unreadCount > 0): ?><span class="badge bg-danger"><?php echo $unreadCount; ?> unread</span><?php endif; ?>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check-all me-1"></i>Mark All Read</button>
        </form>
        <form method="POST" class="d-inline" onsubmit="return confirm('Clear all read notifications?')">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Clear Read</button>
        </form>
    </div>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-body p-0">
            <?php foreach ($notifs as $n): ?>
            <div class="d-flex align-items-start p-3 border-bottom <?php echo !$n['is_read'] ? 'bg-light' : ''; ?>">
                <div class="flex-shrink-0 me-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width:40px;height:40px;background:#0d6efd;font-size:1.1rem;">
                        <i class="bi bi-bell"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo h($n['title']); ?></strong>
                        <small class="text-muted"><?php echo date('M j, Y H:i', strtotime($n['created_at'])); ?></small>
                    </div>
                    <p class="mb-1 small text-muted"><?php echo h($n['message']); ?></p>
                    <small class="text-muted">For: <?php echo h($n['user_name'] ?? 'Unknown'); ?></small>
                    <?php if (!empty($n['link'])): ?>
                    <a href="<?php echo h($n['link']); ?>" class="btn btn-xs btn-outline-primary ms-2" style="font-size:.75rem;padding:2px 8px;">View</a>
                    <?php endif; ?>
                </div>
                <div class="flex-shrink-0 ms-2">
                    <?php if (!$n['is_read']): ?>
                    <span class="badge bg-primary">New</span>
                    <?php endif; ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="notif_id" value="<?php echo (int)$n['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="Delete"><i class="bi bi-x"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($notifs)): ?>
            <div class="text-center py-5 text-muted"><i class="bi bi-bell-slash" style="font-size:3rem;"></i><p class="mt-2">No notifications.</p></div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i=1; $i<=$totalPages; $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
