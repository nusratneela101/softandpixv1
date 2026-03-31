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
        header('Location: hero.php');
        exit;
    }

    $title    = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $btn_text = trim($_POST['btn_text'] ?? '');
    $btn_link = trim($_POST['btn_link'] ?? '');
    $image    = trim($_POST['current_image'] ?? '');

    if (!empty($_FILES['hero_image']['name'])) {
        $result = handleImageUpload($_FILES['hero_image']);
        if ($result['success']) {
            if (!empty($image) && file_exists('../' . $image)) unlink('../' . $image);
            $image = $result['path'];
        } else {
            flashMessage('error', 'Image upload failed: ' . $result['message']);
            header('Location: hero.php');
            exit;
        }
    }

    try {
        $exists = $pdo->query("SELECT id FROM hero LIMIT 1")->fetchColumn();
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE hero SET title=?, subtitle=?, btn_text=?, btn_link=?, hero_image=? WHERE id=?");
            $stmt->execute([$title, $subtitle, $btn_text, $btn_link, $image, $exists]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO hero (title, subtitle, btn_text, btn_link, hero_image) VALUES (?,?,?,?,?)");
            $stmt->execute([$title, $subtitle, $btn_text, $btn_link, $image]);
        }
        flashMessage('success', 'Hero section updated successfully!');
    } catch (PDOException $e) {
        flashMessage('error', 'Database error. Please try again.');
    }

    header('Location: hero.php');
    exit;
}

try {
    $hero = $pdo->query("SELECT * FROM hero LIMIT 1")->fetch();
} catch(Exception $e) {
    $hero = null;
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-image me-2"></i>Hero Section</h1>
    <p>Manage the hero / banner section of your website</p>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-pencil me-2"></i>Edit Hero Section
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="current_image" value="<?php echo h($hero['hero_image'] ?? ''); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($hero['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Subtitle</label>
                        <input type="text" name="subtitle" class="form-control" value="<?php echo h($hero['subtitle'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Button Text</label>
                        <input type="text" name="btn_text" class="form-control" value="<?php echo h($hero['btn_text'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Button Link</label>
                        <input type="text" name="btn_link" class="form-control" value="<?php echo h($hero['btn_link'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Hero Image</label>
                        <input type="file" name="hero_image" class="form-control" accept="image/*" data-preview="hero-img-preview">
                        <?php if (!empty($hero['hero_image'])): ?>
                        <img id="hero-img-preview" src="../<?php echo h($hero['hero_image']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="hero-img-preview" src="" class="img-upload-preview" style="display:none;">
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
