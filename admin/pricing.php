<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024;
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'message' => 'Upload error.'];
    if (!in_array($file['type'], $allowed_types)) return ['success' => false, 'message' => 'Invalid file type.'];
    if ($file['size'] > $max_size) return ['success' => false, 'message' => 'File too large (max 5MB).'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) return ['success' => false, 'message' => 'Invalid extension.'];

    $upload_dir = '../assets/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $filename = uniqid('img_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        return ['success' => true, 'path' => 'assets/uploads/' . $filename];
    }
    return ['success' => false, 'message' => 'Failed to save file.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: pricing.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $result = handleImageUpload($_FILES['image']);
            if ($result['success']) $image = $result['path'];
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO pricing (title, title_color, image, is_featured) VALUES (?,?,?,?)");
            $stmt->execute([trim($_POST['title']), trim($_POST['title_color']), $image, isset($_POST['is_featured']) ? 1 : 0]);
            flashMessage('success', 'Pricing plan added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $image = $_POST['current_image'] ?? '';
        if (!empty($_FILES['image']['name'])) {
            $result = handleImageUpload($_FILES['image']);
            if ($result['success']) {
                if (!empty($image) && file_exists('../' . $image)) unlink('../' . $image);
                $image = $result['path'];
            }
        }
        try {
            $stmt = $pdo->prepare("UPDATE pricing SET title=?, title_color=?, image=?, is_featured=? WHERE id=?");
            $stmt->execute([trim($_POST['title']), trim($_POST['title_color']), $image, isset($_POST['is_featured']) ? 1 : 0, $id]);
            flashMessage('success', 'Pricing plan updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $row = $pdo->prepare("SELECT image FROM pricing WHERE id=?");
            $row->execute([$id]);
            $val = $row->fetch();
            if ($val && !empty($val['image']) && file_exists('../' . $val['image'])) unlink('../' . $val['image']);
            $pdo->prepare("DELETE FROM pricing_items WHERE pricing_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM pricing WHERE id=?")->execute([$id]);
            flashMessage('success', 'Pricing plan deleted!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    // Sub-items
    if ($action === 'add_item') {
        $pricing_id = (int)$_POST['pricing_id'];
        try {
            $stmt = $pdo->prepare("INSERT INTO pricing_items (pricing_id, item_text, sort_order) VALUES (?,?,?)");
            $stmt->execute([$pricing_id, trim($_POST['item_text']), (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Item added!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
        header('Location: pricing.php?plan=' . $pricing_id);
        exit;
    }

    if ($action === 'delete_item') {
        $item_id = (int)$_POST['item_id'];
        $pricing_id = (int)$_POST['pricing_id'];
        try {
            $pdo->prepare("DELETE FROM pricing_items WHERE id=?")->execute([$item_id]);
            flashMessage('success', 'Item deleted!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
        header('Location: pricing.php?plan=' . $pricing_id);
        exit;
    }

    header('Location: pricing.php');
    exit;
}

try {
    $plans = $pdo->query("SELECT * FROM pricing ORDER BY id ASC")->fetchAll();
} catch(Exception $e) { $plans = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pricing WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

$view_plan = null;
$plan_items = [];
if (isset($_GET['plan'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pricing WHERE id=?");
        $stmt->execute([(int)$_GET['plan']]);
        $view_plan = $stmt->fetch();
        if ($view_plan) {
            $stmt2 = $pdo->prepare("SELECT * FROM pricing_items WHERE pricing_id=? ORDER BY sort_order ASC");
            $stmt2->execute([$view_plan['id']]);
            $plan_items = $stmt2->fetchAll();
        }
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-tag me-2"></i>Pricing Management</h1>
    <p>Manage pricing plans and their items</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Plan' : 'Add New Plan'; ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <input type="hidden" name="current_image" value="<?php echo h($edit_item['image'] ?? ''); ?>">
                <?php endif; ?>
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Plan Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($edit_item['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Title Color</label>
                        <input type="text" name="title_color" class="form-control" placeholder="#667eea" value="<?php echo h($edit_item['title_color'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Plan Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*" data-preview="plan-img-preview">
                        <?php if (!empty($edit_item['image'])): ?>
                        <img id="plan-img-preview" src="../<?php echo h($edit_item['image']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="plan-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 mb-3">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo !empty($edit_item['is_featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="is_featured">Featured</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Plan' : 'Add Plan'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="pricing.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($view_plan): ?>
    <!-- Plan Items -->
    <div class="card mb-4">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-2"></i>Items for: <?php echo h($view_plan['title']); ?></span>
            <a href="pricing.php" class="btn btn-sm btn-outline-light">Back to Plans</a>
        </div>
        <div class="card-body">
            <form method="POST" class="mb-4">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="pricing_id" value="<?php echo (int)$view_plan['id']; ?>">
                <div class="row align-items-end">
                    <div class="col-md-8 mb-2">
                        <label class="form-label fw-bold">Item Text *</label>
                        <input type="text" name="item_text" class="form-control" placeholder="e.g. 10 Projects" required>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label fw-bold">Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus me-1"></i>Add</button>
                    </div>
                </div>
            </form>
            <table class="table table-sm">
                <thead><tr><th>Text</th><th>Order</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($plan_items as $pi): ?>
                    <tr>
                        <td><?php echo h($pi['item_text']); ?></td>
                        <td><?php echo (int)$pi['sort_order']; ?></td>
                        <td>
                            <form method="POST" class="d-inline delete-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?php echo (int)$pi['id']; ?>">
                                <input type="hidden" name="pricing_id" value="<?php echo (int)$view_plan['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($plan_items)): ?>
                    <tr><td colspan="3" class="text-muted text-center py-3">No items yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Plans List -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Plans (<?php echo count($plans); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Image</th><th>Title</th><th>Color</th><th>Featured</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><?php echo (int)$plan['id']; ?></td>
                            <td><?php if (!empty($plan['image'])): ?><img src="../<?php echo h($plan['image']); ?>" class="img-preview"><?php endif; ?></td>
                            <td><?php echo h($plan['title']); ?></td>
                            <td><span style="color:<?php echo h($plan['title_color'] ?? '#000'); ?>"><?php echo h($plan['title_color'] ?? '–'); ?></span></td>
                            <td><?php echo $plan['is_featured'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                            <td>
                                <a href="pricing.php?plan=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-info text-white" title="Manage Items"><i class="bi bi-list-check"></i></a>
                                <a href="pricing.php?edit=<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$plan['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($plans)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No pricing plans found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
