<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireClient();

$userId = (int)$_SESSION['user_id'];
$flash  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token. Please try again.');
        header('Location: profile.php');
        exit;
    }

    if ($_POST['action'] === 'delete_avatar') {
        try {
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id=?");
            $stmt->execute([$userId]);
            $oldAvatar = $stmt->fetchColumn();
            if ($oldAvatar) {
                $oldPath = __DIR__ . '/../uploads/avatars/' . basename($oldAvatar);
                if (is_file($oldPath)) unlink($oldPath);
            }
            $pdo->prepare("UPDATE users SET avatar=NULL WHERE id=?")->execute([$userId]);
            flashMessage('success', 'Avatar removed.');
        } catch (Exception $e) {
            flashMessage('error', 'Could not remove avatar.');
        }
        header('Location: profile.php');
        exit;
    }

    if ($_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 8) {
            flashMessage('error', 'New password must be at least 8 characters.');
        } elseif ($newPass !== $confirm) {
            flashMessage('error', 'Passwords do not match.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();
                if (!password_verify($current, $hash)) {
                    flashMessage('error', 'Current password is incorrect.');
                } else {
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                        ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
                    flashMessage('success', 'Password changed successfully.');
                }
            } catch (Exception $e) {
                flashMessage('error', 'Could not update password.');
            }
        }
        header('Location: profile.php');
        exit;
    }

    if ($_POST['action'] === 'update_profile') {
        $name    = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
        $phone   = trim(htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'));
        $bio     = trim(htmlspecialchars($_POST['bio'] ?? '', ENT_QUOTES, 'UTF-8'));
        $skills  = trim(htmlspecialchars($_POST['skills'] ?? '', ENT_QUOTES, 'UTF-8'));
        $company = trim(htmlspecialchars($_POST['company'] ?? '', ENT_QUOTES, 'UTF-8'));
        $website = trim(htmlspecialchars($_POST['website'] ?? '', ENT_QUOTES, 'UTF-8'));
        $address = trim(htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $city    = trim(htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES, 'UTF-8'));
        $country = trim(htmlspecialchars($_POST['country'] ?? '', ENT_QUOTES, 'UTF-8'));
        $timezone = trim(htmlspecialchars($_POST['timezone'] ?? 'UTC', ENT_QUOTES, 'UTF-8'));
        $social_github   = trim(htmlspecialchars($_POST['social_github'] ?? '', ENT_QUOTES, 'UTF-8'));
        $social_linkedin = trim(htmlspecialchars($_POST['social_linkedin'] ?? '', ENT_QUOTES, 'UTF-8'));
        $social_twitter  = trim(htmlspecialchars($_POST['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'));

        if (empty($name)) {
            flashMessage('error', 'Name is required.');
            header('Location: profile.php'); exit;
        }
        if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
            flashMessage('error', 'Invalid website URL.');
            header('Location: profile.php'); exit;
        }

        try {
            // Avatar upload handling
            if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                require_once __DIR__ . '/../includes/functions.php';
                $validation = validateUploadedFile($_FILES['avatar'], ['image/jpeg','image/png','image/gif'], 2097152);
                if (!$validation['ok']) {
                    flashMessage('error', $validation['error']);
                    header('Location: profile.php'); exit;
                }
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id=?");
                $stmt->execute([$userId]);
                $oldAvatar = $stmt->fetchColumn();
                if ($oldAvatar) {
                    $oldPath = $uploadDir . basename($oldAvatar);
                    if (is_file($oldPath)) unlink($oldPath);
                }

                $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $validation['ext'];
                move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $filename);
                $pdo->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$filename, $userId]);
            }

            // Core columns
            $pdo->prepare("UPDATE users SET name=?, phone=?, bio=?, skills=?, company=?, address=?, city=?, country=? WHERE id=?")
                ->execute([$name, $phone, $bio, $skills, $company, $address, $city, $country, $userId]);

            // Optional new columns — graceful fallback (whitelist prevents injection)
            $allowedOptCols = ['timezone', 'website', 'social_github', 'social_linkedin', 'social_twitter'];
            $optColValues   = [$timezone, $website, $social_github, $social_linkedin, $social_twitter];
            foreach ($allowedOptCols as $i => $col) {
                try {
                    $pdo->prepare("UPDATE users SET `$col`=? WHERE id=?")->execute([$optColValues[$i], $userId]);
                } catch (Exception $e) {
                    // Silently ignore unknown column errors (column not yet migrated)
                    if ($e->getCode() !== '42S22') throw $e;
                }
            }

            $_SESSION['user_name'] = $name;
            flashMessage('success', 'Profile updated successfully!');
        } catch (Exception $e) {
            flashMessage('error', 'Could not save profile changes.');
        }
        header('Location: profile.php');
        exit;
    }
}

// Load user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $user = ['id' => $userId, 'name' => $_SESSION['user_name'] ?? '', 'email' => ''];
}
if (!$user) $user = ['id' => $userId, 'name' => $_SESSION['user_name'] ?? '', 'email' => ''];

foreach (['website','city','timezone','social_github','social_linkedin','social_twitter'] as $col) {
    if (!array_key_exists($col, $user)) $user[$col] = '';
}

$flash = getFlashMessage();
$csrfToken = generateCsrfToken();

$totalProjects = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id=? AND status != 'cancelled'");
    $s->execute([$userId]);
    $totalProjects = (int)$s->fetchColumn();
} catch (Exception $e) {}

$avatarUrl  = !empty($user['avatar']) ? '/uploads/avatars/' . rawurlencode(basename($user['avatar'])) : null;
$skillsList = array_filter(array_map('trim', explode(',', $user['skills'] ?? '')));
$initials   = strtoupper(substr($user['name'] ?? 'U', 0, 1));
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
body{background:#f8fafc;}
.sidebar{background:linear-gradient(180deg,#1e3a5f,#2563eb);width:240px;min-height:100vh;position:fixed;top:0;left:0;z-index:100;}
.sidebar .brand{padding:20px;border-bottom:1px solid rgba(255,255,255,.2);}
.sidebar .nav-link{color:rgba(255,255,255,.85);padding:10px 20px;display:flex;align-items:center;gap:10px;text-decoration:none;}
.sidebar .nav-link:hover,.sidebar .nav-link.active{background:rgba(255,255,255,.15);color:#fff;border-radius:8px;margin:2px 8px;padding:10px 12px;}
.main-content{margin-left:240px;padding:24px;}
.avatar-img{width:110px;height:110px;object-fit:cover;border-radius:50%;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.15);}
.avatar-placeholder{width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#fff;border:4px solid #fff;box-shadow:0 4px 16px rgba(0,0,0,.15);}
.profile-card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden;}
.profile-header{background:linear-gradient(135deg,#1e3a5f,#2563eb);padding:32px 24px 20px;text-align:center;color:#fff;}
.skill-badge{display:inline-block;background:#e0e7ff;color:#3730a3;border-radius:20px;padding:3px 12px;font-size:.8rem;margin:2px;}
.section-title{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;font-weight:600;margin-bottom:12px;}
@media(max-width:768px){.sidebar{width:100%;min-height:auto;position:relative;}.main-content{margin-left:0;}}
</style>
</head>
<body>
<div class="sidebar">
    <div class="brand">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:35px;filter:brightness(10);">
    </div>
    <div class="mt-3 px-3 pb-2 small text-white-50">
        <?php echo h($_SESSION['user_name'] ?? ''); ?>
        <span class="badge ms-1" style="background:rgba(255,255,255,.2);">Client</span>
    </div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link" href="/client/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/client/invoices.php"><i class="bi bi-receipt"></i> Invoices</a>
        <a class="nav-link" href="/client/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
        <a class="nav-link" href="/client/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a class="nav-link active" href="/client/profile.php"><i class="bi bi-person-circle"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo h($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="mb-4">
        <h4 class="fw-bold mb-0">My Profile</h4>
        <p class="text-muted mb-0">Manage your personal information and account settings</p>
    </div>

    <div class="row g-4">
        <!-- Profile Summary Card -->
        <div class="col-lg-4">
            <div class="profile-card">
                <div class="profile-header">
                    <?php if ($avatarUrl): ?>
                    <img src="<?php echo $avatarUrl; ?>" class="avatar-img mb-3" alt="Avatar">
                    <?php else: ?>
                    <div class="avatar-placeholder mx-auto mb-3"><?php echo h($initials); ?></div>
                    <?php endif; ?>
                    <h5 class="mb-1 fw-bold"><?php echo h($user['name'] ?? ''); ?></h5>
                    <p class="mb-0 small opacity-75"><?php echo h($user['email'] ?? ''); ?></p>
                    <span class="badge mt-2" style="background:rgba(255,255,255,.2);">Client</span>
                </div>
                <div class="card-body p-4">
                    <div class="section-title">Overview</div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted"><i class="bi bi-kanban me-2"></i>Projects</span>
                        <span class="fw-semibold"><?php echo $totalProjects; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted"><i class="bi bi-calendar me-2"></i>Member Since</span>
                        <span class="fw-semibold"><?php echo isset($user['created_at']) && $user['created_at'] ? date('M Y', strtotime($user['created_at'])) : '—'; ?></span>
                    </div>
                    <?php if (!empty($user['city']) || !empty($user['country'])): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="small text-muted"><i class="bi bi-geo-alt me-2"></i>Location</span>
                        <span class="fw-semibold small"><?php echo h(trim(($user['city'] ?? '') . ($user['city'] && $user['country'] ? ', ' : '') . ($user['country'] ?? ''))); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($skillsList)): ?>
                    <div class="mt-3">
                        <div class="section-title">Skills</div>
                        <?php foreach ($skillsList as $sk): ?>
                        <span class="skill-badge"><?php echo h($sk); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php
                    $gh = $user['social_github'] ?: ($user['github_url'] ?? '');
                    $li = $user['social_linkedin'] ?: ($user['linkedin_url'] ?? '');
                    $tw = $user['social_twitter'] ?? '';
                    if ($gh || $li || $tw):
                    ?>
                    <div class="mt-3">
                        <div class="section-title">Social</div>
                        <?php if ($gh): ?><a href="<?php echo h($gh); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary me-1 mb-1"><i class="bi bi-github"></i></a><?php endif; ?>
                        <?php if ($li): ?><a href="<?php echo h($li); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1 mb-1"><i class="bi bi-linkedin"></i></a><?php endif; ?>
                        <?php if ($tw): ?><a href="<?php echo h($tw); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info me-1 mb-1"><i class="bi bi-twitter"></i></a><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Panels -->
        <div class="col-lg-8">
            <ul class="nav nav-tabs mb-3" id="profileTabs">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabProfile"><i class="bi bi-person me-1"></i>Profile</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabPassword"><i class="bi bi-shield-lock me-1"></i>Password</a></li>
            </ul>

            <div class="tab-content">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="tabProfile">
                    <div class="card border-0 shadow-sm" style="border-radius:12px;">
                        <div class="card-body p-4">
                            <form method="POST" enctype="multipart/form-data" id="profileForm">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

                                <!-- Avatar -->
                                <div class="mb-4">
                                    <div class="section-title">Profile Photo</div>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div>
                                            <?php if ($avatarUrl): ?>
                                            <img src="<?php echo $avatarUrl; ?>" id="formAvatarPreview" style="width:60px;height:60px;border-radius:50%;object-fit:cover;" alt="avatar">
                                            <?php else: ?>
                                            <div id="formAvatarPlaceholder" style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#1d4ed8);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#fff;"><?php echo h($initials); ?></div>
                                            <img src="" id="formAvatarPreview" style="width:60px;height:60px;border-radius:50%;object-fit:cover;display:none;" alt="avatar">
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <label class="btn btn-sm btn-outline-primary mb-1">
                                                <i class="bi bi-upload me-1"></i>Upload Photo
                                                <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif" class="d-none">
                                            </label>
                                            <p class="text-muted mb-0" style="font-size:.75rem;">JPG, PNG, GIF · max 2 MB</p>
                                        </div>
                                        <?php if ($avatarUrl): ?>
                                        <div>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove avatar?')">
                                                <input type="hidden" name="action" value="delete_avatar">
                                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Remove</button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="section-title">Basic Information</div>
                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" value="<?php echo h($user['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" class="form-control bg-light" value="<?php echo h($user['email'] ?? ''); ?>" readonly>
                                        <div class="form-text">Email cannot be changed here.</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Phone</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo h($user['phone'] ?? ''); ?>" placeholder="+1 234 567 8900">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Company</label>
                                        <input type="text" name="company" class="form-control" value="<?php echo h($user['company'] ?? ''); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Bio</label>
                                        <textarea name="bio" class="form-control" rows="3" placeholder="Tell us a bit about yourself…"><?php echo h($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Skills <small class="text-muted">(comma-separated)</small></label>
                                        <input type="text" name="skills" class="form-control" value="<?php echo h($user['skills'] ?? ''); ?>" placeholder="e.g. Project Management, WordPress, SEO">
                                    </div>
                                </div>

                                <div class="section-title mt-3">Location &amp; Web</div>
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Address</label>
                                        <input type="text" name="address" class="form-control" value="<?php echo h($user['address'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">City</label>
                                        <input type="text" name="city" class="form-control" value="<?php echo h($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Country</label>
                                        <input type="text" name="country" class="form-control" value="<?php echo h($user['country'] ?? ''); ?>">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Website</label>
                                        <input type="url" name="website" class="form-control" value="<?php echo h($user['website'] ?? ''); ?>" placeholder="https://example.com">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small fw-semibold">Timezone</label>
                                        <select name="timezone" class="form-select">
                                            <?php
                                            $zones = ['UTC','America/New_York','America/Chicago','America/Denver','America/Los_Angeles',
                                                'Europe/London','Europe/Paris','Europe/Berlin','Asia/Dhaka','Asia/Kolkata',
                                                'Asia/Dubai','Asia/Singapore','Asia/Tokyo','Australia/Sydney','Pacific/Auckland'];
                                            $cur = $user['timezone'] ?: 'UTC';
                                            foreach ($zones as $z): ?>
                                            <option value="<?php echo h($z); ?>" <?php echo $cur === $z ? 'selected' : ''; ?>><?php echo h($z); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="section-title mt-3">Social Links</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-semibold"><i class="bi bi-github me-1"></i>GitHub</label>
                                        <input type="url" name="social_github" class="form-control" value="<?php echo h($user['social_github'] ?: ($user['github_url'] ?? '')); ?>" placeholder="https://github.com/username">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-semibold"><i class="bi bi-linkedin me-1"></i>LinkedIn</label>
                                        <input type="url" name="social_linkedin" class="form-control" value="<?php echo h($user['social_linkedin'] ?: ($user['linkedin_url'] ?? '')); ?>" placeholder="https://linkedin.com/in/username">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label small fw-semibold"><i class="bi bi-twitter me-1"></i>Twitter</label>
                                        <input type="url" name="social_twitter" class="form-control" value="<?php echo h($user['social_twitter'] ?? ''); ?>" placeholder="https://twitter.com/username">
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="saveBtn">
                                    <i class="bi bi-check-circle me-1"></i>Save Changes
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Tab -->
                <div class="tab-pane fade" id="tabPassword">
                    <div class="card border-0 shadow-sm" style="border-radius:12px;">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-1">Change Password</h6>
                            <p class="text-muted small mb-4">Enter your current password to set a new one.</p>
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">New Password</label>
                                    <input type="password" name="new_password" id="newPass" class="form-control" required minlength="8" autocomplete="new-password">
                                    <div class="form-text">Minimum 8 characters.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" id="confirmPass" class="form-control" required minlength="8" autocomplete="new-password">
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-shield-lock me-1"></i>Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('avatarInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2097152) { alert('File too large (max 2 MB)'); this.value = ''; return; }
    const reader = new FileReader();
    reader.onload = function(e) {
        const p2 = document.getElementById('formAvatarPreview');
        const ph = document.getElementById('formAvatarPlaceholder');
        if (p2) { p2.src = e.target.result; p2.style.display = 'block'; }
        if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    if (document.getElementById('newPass').value !== document.getElementById('confirmPass').value) {
        e.preventDefault(); alert('Passwords do not match!');
    }
});

document.getElementById('profileForm').addEventListener('submit', function() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
});
</script>
</body>
</html>
