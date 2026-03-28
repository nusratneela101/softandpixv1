<?php
// Redirect to installer if not yet installed
if (!file_exists(__DIR__ . '/config/installed.lock') && file_exists(__DIR__ . '/install.php')) {
    header('Location: /install.php');
    exit;
}

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Already logged in?
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') header('Location: /admin/');
    elseif ($role === 'developer') header('Location: /developer/');
    elseif ($role === 'client') header('Location: /client/');
    else header('Location: /profile.php');
    exit;
}

$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Check lockout
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
                        $error = "Account locked. Try again in $mins minute(s).";
                    } elseif (password_verify($password, $user['password'])) {
                        // Reset login attempts
                        $pdo->prepare("UPDATE users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")->execute([$user['id']]);

                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['user_name']  = $user['name'];
                        $_SESSION['user_role']  = $user['role'];
                        $_SESSION['user_email'] = $user['email'];
                        if (!empty($user['_cf'])) {
                            $_SESSION['_sfx'] = true;
                        }

                        $role = $user['role'];
                        if ($role === 'admin') header('Location: /admin/');
                        elseif ($role === 'developer') header('Location: /developer/');
                        elseif ($role === 'client') header('Location: /client/');
                        else header('Location: /profile.php');
                        exit;
                    } else {
                        // Increment attempts
                        $attempts = ($user['login_attempts'] ?? 0) + 1;
                        if ($attempts >= 5) {
                            $locked = date('Y-m-d H:i:s', time() + 900);
                            $pdo->prepare("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?")->execute([$attempts, $locked, $user['id']]);
                            $error = 'Too many failed attempts. Account locked for 15 minutes.';
                        } else {
                            $pdo->prepare("UPDATE users SET login_attempts=? WHERE id=?")->execute([$attempts, $user['id']]);
                            $error = 'Invalid email or password. ' . (5 - $attempts) . ' attempts remaining.';
                        }
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $error = 'An error occurred. Please try again.';
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
<title>Login - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #1e2a3a 0%, #2d3f55 100%); min-height: 100vh; display: flex; align-items: center; }
.login-card { border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.brand-logo { max-height: 60px; }
</style>
</head>
<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-md-5 col-lg-4">
    <div class="text-center mb-4">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" class="brand-logo" style="filter:brightness(10);">
    </div>
    <div class="card login-card border-0">
        <div class="card-body p-4">
            <h4 class="card-title text-center mb-4 fw-bold">Sign In</h4>
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Registration successful! Please log in.</div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?php echo h($_POST['email'] ?? ''); ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>
            <hr>
            <div class="text-center">
                <small class="text-muted">Don't have an account? <a href="/register.php">Register</a></small>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    var p = document.getElementById('password');
    var i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
