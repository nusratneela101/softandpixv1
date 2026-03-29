<?php
/**
 * Reset Password — validates token and updates the user's password.
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$csrf_token = generateCsrfToken();
$token      = trim($_GET['token'] ?? '');
$error      = '';
$success    = '';
$validToken = false;
$tokenRow   = null;

if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $tokenRow = $stmt->fetch();
        $validToken = (bool)$tokenRow;
    } catch (Exception $e) {
        $error = 'An error occurred. Please request a new reset link.';
    }
} else {
    $error = 'Invalid or missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        // Strong password validation
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password must contain at least one number.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Re-validate token (race condition protection)
                $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
                $stmt->execute([$token]);
                $tokenRow = $stmt->fetch();

                if (!$tokenRow) {
                    $error = 'Reset link has expired. Please request a new one.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")->execute([$hash, $tokenRow['email']]);
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$tokenRow['email']]);
                    $success = 'Your password has been reset successfully! You can now log in.';
                    $validToken = false;

                    // Log activity if possible
                    try {
                        require_once __DIR__ . '/includes/activity_logger.php';
                        $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                        $userStmt->execute([$tokenRow['email']]);
                        $uid = $userStmt->fetchColumn();
                        if ($uid) log_activity($pdo, $uid, 'password_reset', 'Password reset via email link');
                    } catch (Exception $e) {}
                }
            } catch (Exception $e) {
                error_log('reset_password error: ' . $e->getMessage());
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
    <title>Reset Password — SoftandPix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .auth-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); width: 100%; max-width: 420px; padding: 40px; }
        .auth-logo { font-size: 2rem; font-weight: 800; color: #667eea; text-align: center; margin-bottom: 8px; }
        .auth-logo i { margin-right: 8px; }
        .auth-subtitle { text-align: center; color: #888; font-size: 14px; margin-bottom: 28px; }
        .form-label { font-weight: 600; font-size: 14px; }
        .btn-submit { background: linear-gradient(135deg, #667eea, #764ba2); border: none; color: #fff; width: 100%; padding: 12px; font-size: 15px; font-weight: 600; border-radius: 8px; transition: opacity .2s, transform .2s; }
        .btn-submit:hover { opacity: .92; transform: translateY(-1px); color: #fff; }
        .back-link { text-align: center; margin-top: 16px; font-size: 13px; }
        .back-link a { color: #667eea; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        .password-strength { font-size: 12px; margin-top: 4px; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo"><i class="bi bi-key"></i>SoftandPix</div>
    <div class="auth-subtitle">Create a new password for your account</div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="back-link"><a href="/login.php" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-2"></i>Go to Login</a></div>
    <?php elseif ($validToken): ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="input-group">
                <input type="password" id="password" name="password" class="form-control form-control-lg"
                       placeholder="Min 8 chars, upper, lower, number" required>
                <button type="button" class="btn btn-outline-secondary" id="togglePwd">
                    <i class="bi bi-eye" id="toggleIcon"></i>
                </button>
            </div>
            <div class="password-strength text-muted" id="strengthMsg"></div>
        </div>
        <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control form-control-lg"
                   placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn-submit">
            <i class="bi bi-shield-check me-2"></i>Reset Password
        </button>
    </form>
    <?php elseif (!$success): ?>
    <div class="alert alert-warning">
        <i class="bi bi-clock-history me-2"></i>This reset link is invalid or has expired.
    </div>
    <a href="/forgot_password.php" class="btn btn-primary w-100 mt-2">
        <i class="bi bi-envelope me-2"></i>Request New Reset Link
    </a>
    <?php endif; ?>

    <div class="back-link">
        <a href="/login.php"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const pwdInput    = document.getElementById('password');
const strengthMsg = document.getElementById('strengthMsg');
const toggleBtn   = document.getElementById('togglePwd');
const toggleIcon  = document.getElementById('toggleIcon');

if (toggleBtn) {
    toggleBtn.addEventListener('click', function() {
        const type = pwdInput.type === 'password' ? 'text' : 'password';
        pwdInput.type = type;
        toggleIcon.className = type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
}

if (pwdInput && strengthMsg) {
    pwdInput.addEventListener('input', function() {
        const v = pwdInput.value;
        let score = 0;
        let hints = [];
        if (v.length >= 8) score++; else hints.push('at least 8 characters');
        if (/[A-Z]/.test(v)) score++; else hints.push('an uppercase letter');
        if (/[a-z]/.test(v)) score++; else hints.push('a lowercase letter');
        if (/[0-9]/.test(v)) score++; else hints.push('a number');
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const colors = ['#dc3545','#fd7e14','#ffc107','#20c997','#198754'];
        const labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
        strengthMsg.style.color = colors[score] || '#888';
        strengthMsg.textContent = score > 0 ? labels[score - 1] + (hints.length ? ' — needs: ' + hints.join(', ') : ' ✓') : '';
    });
}
</script>
</body>
</html>
