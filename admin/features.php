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
        header('Location: features.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Update features section image
    if ($action === 'update_section') {
        $image = trim($_POST['current_image'] ?? '');
        if (!empty($_FILES['features_image']['name'])) {
            $result = handleImageUpload($_FILES['features_image']);
            if ($result['success']) {
                if (!empty($image) && file_exists('../' . $image)) unlink('../' . $image);
                $image = $result['path'];
            }
        }
        try {
            $exists = $pdo->query("SELECT id FROM features LIMIT 1")->fetchColumn();
            if ($exists) {
                $stmt = $pdo->prepare("UPDATE features SET features_image=? WHERE id=?");
                $stmt->execute([$image, $exists]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO features (features_image) VALUES (?)");
                $stmt->execute([$image]);
            }
            flashMessage('success', 'Features image updated!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    if ($action === 'add_item') {
        try {
            $stmt = $pdo->prepare("INSERT INTO feature_items (icon_class, title, description, sort_order) VALUES (?,?,?,?)");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['title']), trim($_POST['description']), (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Feature item added!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    if ($action === 'edit_item') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE feature_items SET icon_class=?, title=?, description=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['icon_class']), trim($_POST['title']), trim($_POST['description']), (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Feature item updated!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    if ($action === 'delete_item') {
        $id = (int)$_POST['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM feature_items WHERE id=?");
            $stmt->execute([$id]);
            flashMessage('success', 'Feature item deleted!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    header('Location: features.php');
    exit;
}

try {
    $features = $pdo->query("SELECT * FROM features LIMIT 1")->fetch();
} catch(Exception $e) { $features = null; }

try {
    $items = $pdo->query("SELECT * FROM feature_items ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) { $items = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM feature_items WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-check2-square me-2"></i>Features Management</h1>
    <p>Manage features section image and feature items</p>
</div>
<div class="container-fluid">
    <!-- Section Image -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <i class="bi bi-image me-2"></i>Features Section Image
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="update_section">
                <input type="hidden" name="current_image" value="<?php echo h($features['features_image'] ?? ''); ?>">
                <div class="row align-items-end">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Features Section Image</label>
                        <input type="file" name="features_image" class="form-control" accept="image/*" data-preview="feat-img-preview">
                        <?php if (!empty($features['features_image'])): ?>
                        <img id="feat-img-preview" src="../<?php echo h($features['features_image']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="feat-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Update Image</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Add / Edit Feature Item -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Feature Item' : 'Add New Feature Item'; ?>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit_item' : 'add_item'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Icon Class *</label>
                        <input type="text" name="icon_class" class="form-control" placeholder="e.g. bi bi-check-circle" value="<?php echo h($edit_item['icon_class'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($edit_item['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo h($edit_item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Item' : 'Add Item'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="features.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Items List -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>Feature Items (<?php echo count($items); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Icon</th><th>Title</th><th>Description</th><th>Order</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo (int)$item['id']; ?></td>
                            <td><i class="<?php echo h($item['icon_class']); ?>" style="font-size:20px;"></i></td>
                            <td><?php echo h($item['title']); ?></td>
                            <td><?php echo h(mb_substr($item['description'] ?? '', 0, 70)); ?>…</td>
                            <td><?php echo (int)$item['sort_order']; ?></td>
                            <td>
                                <a href="features.php?edit=<?php echo (int)$item['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No feature items found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
