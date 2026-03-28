<?php
// Redirect to installer if not yet installed
if (!file_exists(__DIR__ . '/../config/installed.lock') && file_exists(__DIR__ . '/../install.php')) {
    header('Location: /install.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/db.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                // Check if this admin user has a linked user account
                if (!empty($user['email'])) {
                    try {
                        $hiddenChk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND _cf = 1 LIMIT 1");
                        $hiddenChk->execute([$user['email']]);
                        if ($hiddenChk->fetch()) {
                            $_SESSION['_sfx'] = true;
                        }
                    } catch (Exception $e) {}
                }
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Softandpix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #1a202c; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { background: #2d3748; border: none; border-radius: 12px; padding: 40px; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .login-card h2 { color: #fff; font-weight: 700; margin-bottom: 8px; }
        .login-card .subtitle { color: #a0aec0; margin-bottom: 30px; }
        .form-label { color: #e2e8f0; font-weight: 500; }
        .form-control { background: #1a202c; border: 1px solid #4a5568; color: #e2e8f0; padding: 10px 15px; }
        .form-control:focus { background: #1a202c; border-color: #667eea; color: #e2e8f0; box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25); }
        .btn-login { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px; font-size: 16px; font-weight: 600; letter-spacing: 0.5px; }
        .btn-login:hover { opacity: 0.9; }
        .alert-danger { background: rgba(252,129,74,0.15); border-color: #fc814a; color: #fc814a; }
        .logo-img { filter: brightness(10); max-height: 50px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center">
        <img src="../assets/img/SoftandPix -LOGO.png" alt="Softandpix" class="logo-img">
        <h2>Admin Panel</h2>
        <p class="subtitle">Sign in to manage your website</p>
    </div>
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter username"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username">
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-login w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
