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
        header('Location: testimonials.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $photo = '';
        if (!empty($_FILES['photo']['name'])) {
            $result = handleImageUpload($_FILES['photo']);
            if ($result['success']) $photo = $result['path'];
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO testimonials (name, role, message, photo, sort_order) VALUES (?,?,?,?,?)");
            $stmt->execute([trim($_POST['name']), trim($_POST['role']), trim($_POST['message']), $photo, (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Testimonial added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $photo = $_POST['current_photo'] ?? '';
        if (!empty($_FILES['photo']['name'])) {
            $result = handleImageUpload($_FILES['photo']);
            if ($result['success']) {
                if (!empty($photo) && file_exists('../' . $photo)) unlink('../' . $photo);
                $photo = $result['path'];
            }
        }
        try {
            $stmt = $pdo->prepare("UPDATE testimonials SET name=?, role=?, message=?, photo=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['name']), trim($_POST['role']), trim($_POST['message']), $photo, (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Testimonial updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $row = $pdo->prepare("SELECT photo FROM testimonials WHERE id=?");
            $row->execute([$id]);
            $val = $row->fetch();
            if ($val && !empty($val['photo']) && file_exists('../' . $val['photo'])) unlink('../' . $val['photo']);
            $pdo->prepare("DELETE FROM testimonials WHERE id=?")->execute([$id]);
            flashMessage('success', 'Testimonial deleted!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    header('Location: testimonials.php');
    exit;
}

try {
    $testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) { $testimonials = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM testimonials WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-chat-quote me-2"></i>Testimonials Management</h1>
    <p>Manage customer testimonials</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Testimonial' : 'Add New Testimonial'; ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <input type="hidden" name="current_photo" value="<?php echo h($edit_item['photo'] ?? ''); ?>">
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo h($edit_item['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Role / Position</label>
                        <input type="text" name="role" class="form-control" value="<?php echo h($edit_item['role'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" data-preview="test-img-preview">
                        <?php if (!empty($edit_item['photo'])): ?>
                        <img id="test-img-preview" src="../<?php echo h($edit_item['photo']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="test-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Message *</label>
                        <textarea name="message" class="form-control" rows="4" required><?php echo h($edit_item['message'] ?? ''); ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Testimonial' : 'Add Testimonial'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="testimonials.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Testimonials (<?php echo count($testimonials); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Photo</th><th>Name</th><th>Role</th><th>Message</th><th>Order</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td><?php echo (int)$t['id']; ?></td>
                            <td><?php if (!empty($t['photo'])): ?><img src="../<?php echo h($t['photo']); ?>" class="img-preview" style="border-radius:50%;"><?php endif; ?></td>
                            <td><?php echo h($t['name']); ?></td>
                            <td><?php echo h($t['role']); ?></td>
                            <td><?php echo h(mb_substr($t['message'] ?? '', 0, 70)); ?>…</td>
                            <td><?php echo (int)$t['sort_order']; ?></td>
                            <td>
                                <a href="testimonials.php?edit=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($testimonials)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No testimonials found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
