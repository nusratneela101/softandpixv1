<?php
require_once dirname(__DIR__) . '/config/db.php';
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
        header('Location: about.php');
        exit;
    }

    $tag         = trim($_POST['tag'] ?? '');
    $heading     = trim($_POST['heading'] ?? '');
    $paragraph1  = trim($_POST['paragraph1'] ?? '');
    $paragraph2  = trim($_POST['paragraph2'] ?? '');
    $btn_text    = trim($_POST['btn_text'] ?? '');
    $image       = trim($_POST['current_image'] ?? '');

    if (!empty($_FILES['about_image']['name'])) {
        $result = handleImageUpload($_FILES['about_image']);
        if ($result['success']) {
            if (!empty($image) && file_exists('../' . $image)) unlink('../' . $image);
            $image = $result['path'];
        } else {
            flashMessage('error', 'Image upload failed: ' . $result['message']);
            header('Location: about.php');
            exit;
        }
    }

    try {
        $exists = $pdo->query("SELECT id FROM about LIMIT 1")->fetchColumn();
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE about SET tag=?, heading=?, paragraph1=?, paragraph2=?, btn_text=?, about_image=? WHERE id=?");
            $stmt->execute([$tag, $heading, $paragraph1, $paragraph2, $btn_text, $image, $exists]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO about (tag, heading, paragraph1, paragraph2, btn_text, about_image) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$tag, $heading, $paragraph1, $paragraph2, $btn_text, $image]);
        }
        flashMessage('success', 'About section updated successfully!');
    } catch (PDOException $e) {
        flashMessage('error', 'Database error. Please try again.');
    }

    header('Location: about.php');
    exit;
}

try {
    $about = $pdo->query("SELECT * FROM about LIMIT 1")->fetch();
} catch(Exception $e) {
    $about = null;
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-info-circle me-2"></i>About Section</h1>
    <p>Manage the about section of your website</p>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-pencil me-2"></i>Edit About Section
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="current_image" value="<?php echo h($about['about_image'] ?? ''); ?>">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Tag / Label</label>
                        <input type="text" name="tag" class="form-control" value="<?php echo h($about['tag'] ?? ''); ?>">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Heading *</label>
                        <input type="text" name="heading" class="form-control" value="<?php echo h($about['heading'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Paragraph 1</label>
                        <textarea name="paragraph1" class="form-control" rows="4"><?php echo h($about['paragraph1'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Paragraph 2</label>
                        <textarea name="paragraph2" class="form-control" rows="4"><?php echo h($about['paragraph2'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Button Text</label>
                        <input type="text" name="btn_text" class="form-control" value="<?php echo h($about['btn_text'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">About Image</label>
                        <input type="file" name="about_image" class="form-control" accept="image/*" data-preview="about-img-preview">
                        <?php if (!empty($about['about_image'])): ?>
                        <img id="about-img-preview" src="../<?php echo h($about['about_image']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="about-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i>Save Changes
                </button>
            </form>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
