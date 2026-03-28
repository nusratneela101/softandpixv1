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
        header('Location: values.php');
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
            $stmt = $pdo->prepare("INSERT INTO values_section (title, description, image, sort_order) VALUES (?,?,?,?)");
            $stmt->execute([trim($_POST['title']), trim($_POST['description']), $image, (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Value added successfully!');
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
            $stmt = $pdo->prepare("UPDATE values_section SET title=?, description=?, image=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['title']), trim($_POST['description']), $image, (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Value updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $row = $pdo->prepare("SELECT image FROM values_section WHERE id=?");
            $row->execute([$id]);
            $val = $row->fetch();
            if ($val && !empty($val['image']) && file_exists('../' . $val['image'])) unlink('../' . $val['image']);
            $stmt = $pdo->prepare("DELETE FROM values_section WHERE id=?");
            $stmt->execute([$id]);
            flashMessage('success', 'Value deleted successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    header('Location: values.php');
    exit;
}

try {
    $values = $pdo->query("SELECT * FROM values_section ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) {
    $values = [];
}

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM values_section WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-star me-2"></i>Values Management</h1>
    <p>Manage website values section</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Value' : 'Add New Value'; ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <input type="hidden" name="current_image" value="<?php echo h($edit_item['image'] ?? ''); ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($edit_item['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*" data-preview="val-img-preview">
                        <?php if (!empty($edit_item['image'])): ?>
                        <img id="val-img-preview" src="../<?php echo h($edit_item['image']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="val-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo h($edit_item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Value' : 'Add Value'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="values.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Values (<?php echo count($values); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($values as $val): ?>
                        <tr>
                            <td><?php echo (int)$val['id']; ?></td>
                            <td><?php if (!empty($val['image'])): ?><img src="../<?php echo h($val['image']); ?>" class="img-preview"><?php endif; ?></td>
                            <td><?php echo h($val['title']); ?></td>
                            <td><?php echo h(mb_substr($val['description'] ?? '', 0, 80)); ?>…</td>
                            <td><?php echo (int)$val['sort_order']; ?></td>
                            <td>
                                <a href="values.php?edit=<?php echo (int)$val['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$val['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($values)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No values found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
