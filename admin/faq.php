<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: faq.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO faq (question, answer, sort_order) VALUES (?,?,?)");
            $stmt->execute([trim($_POST['question']), trim($_POST['answer']), (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'FAQ added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE faq SET question=?, answer=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['question']), trim($_POST['answer']), (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'FAQ updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM faq WHERE id=?");
            $stmt->execute([$id]);
            flashMessage('success', 'FAQ deleted successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    header('Location: faq.php');
    exit;
}

try {
    $faqs = $pdo->query("SELECT * FROM faq ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) { $faqs = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM faq WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-question-circle me-2"></i>FAQ Management</h1>
    <p>Manage frequently asked questions</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit FAQ' : 'Add New FAQ'; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-10 mb-3">
                        <label class="form-label fw-bold">Question *</label>
                        <input type="text" name="question" class="form-control" value="<?php echo h($edit_item['question'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Answer *</label>
                        <textarea name="answer" class="form-control" rows="4" required><?php echo h($edit_item['answer'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update FAQ' : 'Add FAQ'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="faq.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All FAQs (<?php echo count($faqs); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Question</th><th>Answer</th><th>Order</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs as $faq): ?>
                        <tr>
                            <td><?php echo (int)$faq['id']; ?></td>
                            <td><?php echo h($faq['question']); ?></td>
                            <td><?php echo h(mb_substr($faq['answer'] ?? '', 0, 80)); ?>…</td>
                            <td><?php echo (int)$faq['sort_order']; ?></td>
                            <td>
                                <a href="faq.php?edit=<?php echo (int)$faq['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$faq['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($faqs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No FAQs found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
