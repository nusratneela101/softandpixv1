<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: messages.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$id]);
            flashMessage('success', 'Message marked as read.');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
            flashMessage('success', 'Message deleted.');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    header('Location: messages.php');
    exit;
}

try {
    $messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll();
} catch(Exception $e) { $messages = []; }

$view_msg = null;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id=?");
        $stmt->execute([$view_id]);
        $view_msg = $stmt->fetch();
        // Auto-mark as read when viewing
        if ($view_msg && !$view_msg['is_read']) {
            $pdo->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?")->execute([$view_id]);
            $view_msg['is_read'] = 1;
        }
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-envelope me-2"></i>Contact Messages</h1>
    <p>View and manage contact form submissions</p>
</div>
<div class="container-fluid">
    <?php if ($view_msg): ?>
    <!-- Single Message View -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-envelope-open me-2"></i>Message from <?php echo h($view_msg['name']); ?></span>
            <a href="messages.php" class="btn btn-sm btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4"><strong>Name:</strong> <?php echo h($view_msg['name']); ?></div>
                <div class="col-md-4"><strong>Email:</strong> <a href="mailto:<?php echo h($view_msg['email']); ?>"><?php echo h($view_msg['email']); ?></a></div>
                <div class="col-md-4"><strong>Date:</strong> <?php echo h($view_msg['created_at']); ?></div>
            </div>
            <?php if (!empty($view_msg['subject'])): ?>
            <div class="mb-3"><strong>Subject:</strong> <?php echo h($view_msg['subject']); ?></div>
            <?php endif; ?>
            <div class="mb-4">
                <strong>Message:</strong>
                <div class="mt-2 p-3 bg-light rounded"><?php echo nl2br(h($view_msg['message'])); ?></div>
            </div>
            <div class="d-flex gap-2">
                <?php if (!$view_msg['is_read']): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="mark_read">
                    <input type="hidden" name="id" value="<?php echo (int)$view_msg['id']; ?>">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i>Mark as Read</button>
                </form>
                <?php endif; ?>
                <a href="mailto:<?php echo h($view_msg['email']); ?>" class="btn btn-primary"><i class="bi bi-reply me-1"></i>Reply via Email</a>
                <form method="POST" class="d-inline delete-form">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$view_msg['id']; ?>">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Messages List -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list me-2"></i>All Messages (<?php echo count($messages); ?>)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Name</th><th>Email</th><th>Subject</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                        <tr class="<?php echo !$msg['is_read'] ? 'unread-row' : ''; ?>">
                            <td><?php echo h($msg['name']); ?></td>
                            <td><?php echo h($msg['email']); ?></td>
                            <td><?php echo h(mb_substr($msg['subject'] ?? '(no subject)', 0, 50)); ?></td>
                            <td><?php echo h($msg['created_at']); ?></td>
                            <td>
                                <?php if (!$msg['is_read']): ?>
                                <span class="badge bg-danger">Unread</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Read</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="messages.php?view=<?php echo (int)$msg['id']; ?>" class="btn btn-sm btn-info text-white" title="View"><i class="bi bi-eye"></i></a>
                                <?php if (!$msg['is_read']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?php echo (int)$msg['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Mark Read"><i class="bi bi-check2"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$msg['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($messages)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No messages found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
