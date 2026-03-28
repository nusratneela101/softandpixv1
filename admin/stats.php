<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: stats.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO stats (icon_class, icon_color, count_end, label, sort_order) VALUES (?,?,?,?,?)");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['icon_color']), (int)$_POST['count_end'], trim($_POST['label']), (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Stat added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE stats SET icon_class=?, icon_color=?, count_end=?, label=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['icon_color']), (int)$_POST['count_end'], trim($_POST['label']), (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Stat updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM stats WHERE id=?");
            $stmt->execute([$id]);
            flashMessage('success', 'Stat deleted successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    header('Location: stats.php');
    exit;
}

try {
    $stats = $pdo->query("SELECT * FROM stats ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) {
    $stats = [];
}

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM stats WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-bar-chart me-2"></i>Stats Management</h1>
    <p>Manage website statistics counters</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Stat' : 'Add New Stat'; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Icon Class *</label>
                        <input type="text" name="icon_class" class="form-control" placeholder="e.g. bi bi-people" value="<?php echo h($edit_item['icon_class'] ?? ''); ?>" required>
                        <div class="form-text">Bootstrap Icons class name</div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Icon Color</label>
                        <input type="text" name="icon_color" class="form-control" placeholder="e.g. #667eea" value="<?php echo h($edit_item['icon_color'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Count End *</label>
                        <input type="number" name="count_end" class="form-control" value="<?php echo (int)($edit_item['count_end'] ?? 0); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Label *</label>
                        <input type="text" name="label" class="form-control" placeholder="e.g. Happy Clients" value="<?php echo h($edit_item['label'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-1 mb-3">
                        <label class="form-label fw-bold">Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Stat' : 'Add Stat'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="stats.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Stats (<?php echo count($stats); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Icon</th>
                            <th>Color</th>
                            <th>Count</th>
                            <th>Label</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $stat): ?>
                        <tr>
                            <td><?php echo (int)$stat['id']; ?></td>
                            <td><i class="<?php echo h($stat['icon_class']); ?>" style="color:<?php echo h($stat['icon_color']); ?>; font-size:20px;"></i></td>
                            <td><span style="background:<?php echo h($stat['icon_color']); ?>; display:inline-block; width:24px; height:24px; border-radius:50%; border:1px solid #ccc;"></span> <?php echo h($stat['icon_color']); ?></td>
                            <td><?php echo (int)$stat['count_end']; ?></td>
                            <td><?php echo h($stat['label']); ?></td>
                            <td><?php echo (int)$stat['sort_order']; ?></td>
                            <td>
                                <a href="stats.php?edit=<?php echo (int)$stat['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$stat['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($stats)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No stats found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
