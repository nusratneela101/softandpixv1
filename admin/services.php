<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: services.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO services (icon_class, color_class, title, description, sort_order) VALUES (?,?,?,?,?)");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['color_class']), trim($_POST['title']), trim($_POST['description']), (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Service added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE services SET icon_class=?, color_class=?, title=?, description=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['color_class']), trim($_POST['title']), trim($_POST['description']), (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Service updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM services WHERE id=?");
            $stmt->execute([$id]);
            flashMessage('success', 'Service deleted successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    header('Location: services.php');
    exit;
}

try {
    $services = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) { $services = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

$color_classes = ['blue', 'orange', 'green', 'red', 'purple', 'pink'];

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-grid me-2"></i>Services Management</h1>
    <p>Manage website services section</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Service' : 'Add New Service'; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Icon Class *</label>
                        <input type="text" name="icon_class" class="form-control" placeholder="e.g. bi bi-camera" value="<?php echo h($edit_item['icon_class'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Color Class *</label>
                        <select name="color_class" class="form-select" required>
                            <?php foreach ($color_classes as $c): ?>
                            <option value="<?php echo h($c); ?>" <?php echo ($edit_item['color_class'] ?? '') === $c ? 'selected' : ''; ?>>
                                <?php echo ucfirst(h($c)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($edit_item['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-1 mb-3">
                        <label class="form-label fw-bold">Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo h($edit_item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Service' : 'Add Service'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="services.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Services (<?php echo count($services); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Icon</th><th>Color</th><th>Title</th><th>Description</th><th>Order</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $svc): ?>
                        <tr>
                            <td><?php echo (int)$svc['id']; ?></td>
                            <td><i class="<?php echo h($svc['icon_class']); ?>" style="font-size:20px;"></i></td>
                            <td><span class="badge bg-secondary"><?php echo h($svc['color_class']); ?></span></td>
                            <td><?php echo h($svc['title']); ?></td>
                            <td><?php echo h(mb_substr($svc['description'] ?? '', 0, 70)); ?>…</td>
                            <td><?php echo (int)$svc['sort_order']; ?></td>
                            <td>
                                <a href="services.php?edit=<?php echo (int)$svc['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$svc['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($services)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No services found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
