<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$csrf_token = generateCsrfToken();
$error = '';
$success = '';

// Load user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (Exception $e) { die('Error loading profile.'); }

if (!$user) { session_destroy(); header('Location: /login.php'); exit; }

$profileFields = getRoleProfileFields($pdo, $user['role']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $tab = $_POST['tab'] ?? 'info';

        if ($tab === 'info') {
            $updates = [];
            $params  = [];
            $allowed = ['name','phone','company','country','address','bio','skills','portfolio_url','github_url','linkedin_url','dribbble_url','behance_url','custom_field_1_label','custom_field_1_value','custom_field_2_label','custom_field_2_value'];
            foreach ($allowed as $field) {
                if (in_array($field, $profileFields) || strpos($field, 'custom_field') !== false) {
                    if (isset($_POST[$field])) {
                        $updates[] = "$field = ?";
                        $params[]  = trim($_POST[$field]);
                    }
                }
            }
            // Always allow name update
            if (!in_array('name = ?', $updates) && isset($_POST['name'])) {
                $updates[] = 'name = ?';
                $params[]  = trim($_POST['name']);
            }

            // Handle avatar upload
            if (!empty($_FILES['avatar']['name'])) {
                $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
                $val = validateUploadedFile($_FILES['avatar'], $allowed_mime);
                if ($val['ok']) {
                    $uploadDir = __DIR__ . '/assets/uploads/avatars/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fname = 'avatar_' . $userId . '_' . time() . '.' . $val['ext'];
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $fname)) {
                        $updates[] = 'avatar = ?';
                        $params[]  = 'assets/uploads/avatars/' . $fname;
                    }
                }
            }

            if (!empty($updates)) {
                $params[] = $userId;
                $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
                $_SESSION['user_name'] = $_POST['name'] ?? $_SESSION['user_name'];
                $success = 'Profile updated successfully!';
                // Reload user
                $stmt2 = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt2->execute([$userId]);
                $user = $stmt2->fetch();
            }
        } elseif ($tab === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (empty($current) || empty($new) || empty($confirm)) {
                $error = 'All password fields are required.';
            } elseif (!password_verify($current, $user['password'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($new) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($new !== $confirm) {
                $error = 'New passwords do not match.';
            } else {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
                $success = 'Password changed successfully!';
            }
        }
    }
}

$roleInfo = getRoleLabel($pdo, $user['role']);
$activeTab = $_GET['tab'] ?? 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f0f2f5; }
.profile-avatar { width:100px; height:100px; object-fit:cover; border-radius:50%; border:4px solid #fff; box-shadow:0 4px 15px rgba(0,0,0,.15); }
.profile-card { border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
.role-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:.8rem; font-weight:600; color:#fff; }
</style>
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/index.php"><img src="/assets/img/SoftandPix -LOGO.png" alt="" style="height:35px;"></a>
        <div class="d-flex gap-2">
            <?php if ($user['role'] === 'admin'): ?>
            <a href="/admin/" class="btn btn-sm btn-outline-danger">Admin Panel</a>
            <?php elseif ($user['role'] === 'developer'): ?>
            <a href="/developer/" class="btn btn-sm btn-outline-primary">Dashboard</a>
            <?php elseif ($user['role'] === 'client'): ?>
            <a href="/client/" class="btn btn-sm btn-outline-success">Dashboard</a>
            <?php endif; ?>
            <a href="/logout.php" class="btn btn-sm btn-outline-secondary">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Profile Card -->
        <div class="col-md-3">
            <div class="card profile-card border-0 text-center p-3">
                <?php if (!empty($user['avatar'])): ?>
                <img src="/<?php echo h($user['avatar']); ?>" class="profile-avatar mx-auto d-block mb-3" alt="Avatar">
                <?php else: ?>
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width:100px;height:100px;font-size:2.5rem;">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <?php endif; ?>
                <h5 class="mb-1 fw-bold"><?php echo h($user['name']); ?></h5>
                <span class="role-badge mb-2" style="background:<?php echo h($roleInfo['role_color']); ?>;"><?php echo h($roleInfo['role_label']); ?></span>
                <p class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i><?php echo h($user['email']); ?></p>
                <?php if (!empty($user['phone'])): ?>
                <p class="small text-muted mb-1"><i class="bi bi-phone me-1"></i><?php echo h($user['phone']); ?></p>
                <?php endif; ?>
                <hr>
                <p class="small text-muted mb-0"><i class="bi bi-calendar me-1"></i>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                <?php if (!empty($user['last_login'])): ?>
                <p class="small text-muted mb-0"><i class="bi bi-clock me-1"></i>Last login: <?php echo timeAgo($user['last_login']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main content -->
        <div class="col-md-9">
            <div class="card profile-card border-0">
                <div class="card-header bg-white border-bottom-0 pt-3">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'info' ? 'active' : ''; ?>" href="?tab=info">
                                <i class="bi bi-person me-1"></i>Personal Info
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeTab === 'security' ? 'active' : ''; ?>" href="?tab=security">
                                <i class="bi bi-shield-lock me-1"></i>Security
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <?php if ($activeTab === 'info'): ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="tab" value="info">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo h($user['name']); ?>" required>
                            </div>
                            <?php if (in_array('phone', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo h($user['phone'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('company', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company</label>
                                <input type="text" name="company" class="form-control" value="<?php echo h($user['company'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('country', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Country</label>
                                <input type="text" name="country" class="form-control" value="<?php echo h($user['country'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('address', $profileFields)): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?php echo h($user['address'] ?? ''); ?></textarea>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('bio', $profileFields)): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bio</label>
                                <textarea name="bio" class="form-control" rows="3"><?php echo h($user['bio'] ?? ''); ?></textarea>
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('skills', $profileFields)): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Skills <small class="text-muted">(comma-separated)</small></label>
                                <input type="text" name="skills" class="form-control" value="<?php echo h($user['skills'] ?? ''); ?>" placeholder="PHP, MySQL, JavaScript...">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('portfolio_url', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Portfolio URL</label>
                                <input type="url" name="portfolio_url" class="form-control" value="<?php echo h($user['portfolio_url'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('github_url', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">GitHub URL</label>
                                <input type="url" name="github_url" class="form-control" value="<?php echo h($user['github_url'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('linkedin_url', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">LinkedIn URL</label>
                                <input type="url" name="linkedin_url" class="form-control" value="<?php echo h($user['linkedin_url'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('dribbble_url', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dribbble URL</label>
                                <input type="url" name="dribbble_url" class="form-control" value="<?php echo h($user['dribbble_url'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('behance_url', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Behance URL</label>
                                <input type="url" name="behance_url" class="form-control" value="<?php echo h($user['behance_url'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('custom_field_1', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <?php echo !empty($user['custom_field_1_label']) ? h($user['custom_field_1_label']) : 'Custom Field 1'; ?>
                                </label>
                                <input type="text" name="custom_field_1_value" class="form-control" value="<?php echo h($user['custom_field_1_value'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('custom_field_2', $profileFields)): ?>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    <?php echo !empty($user['custom_field_2_label']) ? h($user['custom_field_2_label']) : 'Custom Field 2'; ?>
                                </label>
                                <input type="text" name="custom_field_2_value" class="form-control" value="<?php echo h($user['custom_field_2_value'] ?? ''); ?>">
                            </div>
                            <?php endif; ?>
                            <?php if (in_array('avatar', $profileFields)): ?>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Profile Photo</label>
                                <input type="file" name="avatar" class="form-control" accept="image/*">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                    <?php elseif ($activeTab === 'security'): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="tab" value="password">
                        <div class="row g-3" style="max-width:450px;">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-warning px-4">
                                <i class="bi bi-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
