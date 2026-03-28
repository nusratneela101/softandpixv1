<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /client/'); exit;
}

$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $company  = trim($_POST['company'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $error = 'Email already registered.';
                } else {
                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));
                    $stmt  = $pdo->prepare("INSERT INTO users (name, email, password, role, company, phone, verification_token) VALUES (?, ?, ?, 'client', ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $company, $phone, $token]);

                    // Try to send verification email
                    try {
                        require_once 'includes/email.php';
                        $verifyLink = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . $token;
                        $body = "<h2>Welcome to Softandpix!</h2><p>Hi $name,</p><p>Please verify your email by clicking the link below:</p><p><a href='$verifyLink'>$verifyLink</a></p>";
                        sendEmail($pdo, $email, 'Verify Your Email - Softandpix', $body);
                        // Notify admin of new registration
                        $adminBody = "<p><strong>New user registered:</strong></p><ul><li><strong>Name:</strong> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</li><li><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</li><li><strong>Role:</strong> client</li></ul>";
                        sendAdminNotification($pdo, 'New User Registration - Softandpix', $adminBody);
                    } catch (Exception $e) {
                        // Email failed silently
                    }

                    header('Location: /login.php?registered=1'); exit;
                }
            } catch (PDOException $e) {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #1e2a3a 0%, #2d3f55 100%); min-height: 100vh; display:flex; align-items:center; padding: 30px 0; }
.reg-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
</style>
</head>
<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">
    <div class="text-center mb-4">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:50px; filter:brightness(10);">
    </div>
    <div class="card reg-card border-0">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4 fw-bold">Create Account</h4>
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="John Doe" value="<?php echo h($_POST['name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="john@example.com" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Company</label>
                        <input type="text" name="company" class="form-control" placeholder="Your Company" value="<?php echo h($_POST['company'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1 234 567 8900" value="<?php echo h($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>
            </form>
            <hr>
            <div class="text-center">
                <small class="text-muted">Already have an account? <a href="/login.php">Sign In</a></small>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
