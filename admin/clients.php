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
        header('Location: clients.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $logo = '';
        if (!empty($_FILES['logo']['name'])) {
            $result = handleImageUpload($_FILES['logo']);
            if ($result['success']) $logo = $result['path'];
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO clients (company_name, logo, sort_order) VALUES (?,?,?)");
            $stmt->execute([trim($_POST['company_name']), $logo, (int)($_POST['sort_order'] ?? 0)]);
            flashMessage('success', 'Client added successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $logo = $_POST['current_logo'] ?? '';
        if (!empty($_FILES['logo']['name'])) {
            $result = handleImageUpload($_FILES['logo']);
            if ($result['success']) {
                if (!empty($logo) && file_exists('../' . $logo)) unlink('../' . $logo);
                $logo = $result['path'];
            }
        }
        try {
            $stmt = $pdo->prepare("UPDATE clients SET company_name=?, logo=?, sort_order=? WHERE id=?");
            $stmt->execute([trim($_POST['company_name']), $logo, (int)($_POST['sort_order'] ?? 0), $id]);
            flashMessage('success', 'Client updated successfully!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error. Please try again.');
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        try {
            $row = $pdo->prepare("SELECT logo FROM clients WHERE id=?");
            $row->execute([$id]);
            $val = $row->fetch();
            if ($val && !empty($val['logo']) && file_exists('../' . $val['logo'])) unlink('../' . $val['logo']);
            $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([$id]);
            flashMessage('success', 'Client deleted!');
        } catch(PDOException $e) {
            flashMessage('error', 'Database error.');
        }
    }

    header('Location: clients.php');
    exit;
}

try {
    $clients = $pdo->query("SELECT * FROM clients ORDER BY sort_order ASC")->fetchAll();
} catch(Exception $e) { $clients = []; }

$edit_item = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id=?");
        $stmt->execute([(int)$_GET['edit']]);
        $edit_item = $stmt->fetch();
    } catch(Exception $e) {}
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-building me-2"></i>Clients Management</h1>
    <p>Manage client logos and companies</p>
</div>
<div class="container-fluid">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-<?php echo $edit_item ? 'pencil' : 'plus-circle'; ?> me-2"></i>
            <?php echo $edit_item ? 'Edit Client' : 'Add New Client'; ?>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
                <input type="hidden" name="current_logo" value="<?php echo h($edit_item['logo'] ?? ''); ?>">
                <?php endif; ?>
                <div class="row align-items-end">
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Company Name *</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo h($edit_item['company_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label fw-bold">Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*" data-preview="client-img-preview">
                        <?php if (!empty($edit_item['logo'])): ?>
                        <img id="client-img-preview" src="../<?php echo h($edit_item['logo']); ?>" class="img-upload-preview">
                        <?php else: ?>
                        <img id="client-img-preview" src="" class="img-upload-preview" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)($edit_item['sort_order'] ?? 0); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-2"></i><?php echo $edit_item ? 'Update Client' : 'Add Client'; ?>
                </button>
                <?php if ($edit_item): ?>
                <a href="clients.php" class="btn btn-secondary ms-2"><i class="bi bi-x me-1"></i>Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-list me-2"></i>All Clients (<?php echo count($clients); ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Logo</th><th>Company Name</th><th>Order</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?php echo (int)$client['id']; ?></td>
                            <td><?php if (!empty($client['logo'])): ?><img src="../<?php echo h($client['logo']); ?>" class="img-preview"><?php endif; ?></td>
                            <td><?php echo h($client['company_name']); ?></td>
                            <td><?php echo (int)$client['sort_order']; ?></td>
                            <td>
                                <a href="clients.php?edit=<?php echo (int)$client['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline delete-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$client['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clients)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No clients found. Add one above.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
