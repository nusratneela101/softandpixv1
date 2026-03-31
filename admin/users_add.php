<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

try {
    $roles = $pdo->query("SELECT * FROM custom_roles WHERE is_active=1 ORDER BY role_label")->fetchAll();
    $roleFieldsMap = [];
    foreach ($roles as $r) {
        $roleFieldsMap[$r['role_name']] = json_decode($r['profile_fields'], true) ?? [];
    }
} catch (Exception $e) { $roles = []; $roleFieldsMap = []; }

$error      = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $pass    = $_POST['password'] ?? '';
        $role    = trim($_POST['role'] ?? 'client');
        $phone   = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $bio     = trim($_POST['bio'] ?? '');
        $skills  = trim($_POST['skills'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $verified = isset($_POST['email_verified']) ? 1 : 0;

        if (empty($name) || empty($email) || empty($pass)) {
            $error = 'Name, email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'Email already exists.';
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, phone, company, bio, skills, is_active, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $role, $phone, $company, $bio, $skills, $active, $verified]);
                    flashMessage('success', 'User added successfully!');
                    header('Location: users.php'); exit;
                }
            } catch (PDOException $e) {
                $error = 'Failed to add user.';
            }
        }
    }
}
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-person-plus me-2"></i>Add User</h1>
    </div>
    <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
                        <input type="text" name="name" class="form-control" value="<?php echo h($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Role *</label>
                        <select name="role" id="roleSelect" class="form-select" onchange="updateFields()">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?php echo h($r['role_name']); ?>" <?php echo ($_POST['role'] ?? 'client') === $r['role_name'] ? 'selected' : ''; ?>>
                                <?php echo h($r['role_label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 field-phone">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 field-company">
                        <label class="form-label fw-semibold">Company</label>
                        <input type="text" name="company" class="form-control" value="<?php echo h($_POST['company'] ?? ''); ?>">
                    </div>
                    <div class="col-12 field-bio">
                        <label class="form-label fw-semibold">Bio</label>
                        <textarea name="bio" class="form-control" rows="3"><?php echo h($_POST['bio'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12 field-skills">
                        <label class="form-label fw-semibold">Skills <span class="text-muted fw-normal">(comma-separated)</span></label>
                        <input type="text" name="skills" class="form-control" value="<?php echo h($_POST['skills'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?php echo (!isset($_POST['csrf_token']) || isset($_POST['is_active'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">Active</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" name="email_verified" id="emailVerified" value="1" <?php echo !empty($_POST['email_verified']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="emailVerified">Email Verified</label>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Add User</button>
                    <a href="users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
var roleFields = <?php echo json_encode($roleFieldsMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
function updateFields() {
    var role   = document.getElementById('roleSelect').value;
    var fields = roleFields[role] || [];
    var fieldMap = { phone: 'field-phone', company: 'field-company', bio: 'field-bio', skills: 'field-skills' };
    Object.keys(fieldMap).forEach(function(f) {
        var el = document.querySelector('.' + fieldMap[f]);
        if (el) el.style.display = (fields.length === 0 || fields.includes(f)) ? '' : 'none';
    });
}
updateFields();
</script>
<?php require_once 'includes/footer.php'; ?>
