<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: users.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND (_cf = 0 OR _cf IS NULL)");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    $roles = $pdo->query("SELECT * FROM custom_roles WHERE is_active=1 ORDER BY role_label")->fetchAll();
} catch (Exception $e) { $user = null; $roles = []; }

if (!$user) { flashMessage('error', 'User not found.'); header('Location: users.php'); exit; }

$error      = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $role    = trim($_POST['role'] ?? 'client');
        $phone   = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $bio     = trim($_POST['bio'] ?? '');
        $skills  = trim($_POST['skills'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $verified = isset($_POST['email_verified']) ? 1 : 0;
        $newPass = $_POST['new_password'] ?? '';

        if (empty($name) || empty($email)) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (!empty($newPass) && strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $id]);
                if ($check->fetch()) {
                    $error = 'Email already in use by another account.';
                } else {
                    if (!empty($newPass)) {
                        $hash = password_hash($newPass, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role=?, phone=?, company=?, bio=?, skills=?, is_active=?, email_verified=? WHERE id=?")
                            ->execute([$name, $email, $hash, $role, $phone, $company, $bio, $skills, $active, $verified, $id]);
                    } else {
                        $pdo->prepare("UPDATE users SET name=?, email=?, role=?, phone=?, company=?, bio=?, skills=?, is_active=?, email_verified=? WHERE id=?")
                            ->execute([$name, $email, $role, $phone, $company, $bio, $skills, $active, $verified, $id]);
                    }
                    flashMessage('success', 'User updated successfully!');
                    header('Location: users.php'); exit;
                }
            } catch (PDOException $e) {
                $error = 'Update failed.';
            }
        }
    }
}
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-pencil me-2"></i>Edit User</h1></div>
    <div>
        <a href="user_view.php?id=<?php echo $id; ?>" class="btn btn-outline-info me-2"><i class="bi bi-eye me-1"></i>View</a>
        <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo h($_POST['name'] ?? $user['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo h($_POST['email'] ?? $user['email']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Role</label>
                        <select name="role" class="form-select">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?php echo h($r['role_name']); ?>" <?php echo ($user['role'] ?? 'client') === $r['role_name'] ? 'selected' : ''; ?>>
                                <?php echo h($r['role_label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo h($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Company</label>
                        <input type="text" name="company" class="form-control" value="<?php echo h($_POST['company'] ?? $user['company'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">New Password <small class="text-muted fw-normal">(leave blank to keep current)</small></label>
                        <input type="password" name="new_password" class="form-control" minlength="6" autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Bio</label>
                        <textarea name="bio" class="form-control" rows="3"><?php echo h($_POST['bio'] ?? $user['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Skills <span class="text-muted fw-normal">(comma-separated)</span></label>
                        <input type="text" name="skills" class="form-control" value="<?php echo h($_POST['skills'] ?? $user['skills'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="email_verified" id="emailVerified" value="1" <?php echo ($user['email_verified'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="emailVerified">Email Verified</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Changes</button>
                    <a href="users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
