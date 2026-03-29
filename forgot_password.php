<?php
/**
 * Forgot Password — sends a password-reset link to the user's email.
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$csrf_token = generateCsrfToken();
$message    = '';
$msgType    = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $msgType = 'danger';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $msgType = 'danger';
        } else {
            // Always show success to prevent email enumeration
            $message = 'If that email is registered, you will receive a password reset link shortly.';

            try {
                // Ensure table exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Delete any old tokens for this email
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                    $token   = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                    $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                        ->execute([$email, $token, $expires]);

                    // Build reset link
                    $siteUrl   = getSiteUrl($pdo);
                    $resetLink = $siteUrl . '/reset_password.php?token=' . urlencode($token);

                    $subject = 'Reset Your SoftandPix Password';
                    $body    = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
                        . '<div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;border-radius:8px 8px 0 0;text-align:center;">'
                        . '<h2 style="color:#fff;margin:0;">Password Reset Request</h2></div>'
                        . '<div style="background:#fff;padding:30px;border:1px solid #dee2e6;border-top:none;border-radius:0 0 8px 8px;">'
                        . '<p>Hi,</p>'
                        . '<p>We received a request to reset the password for your SoftandPix account associated with <strong>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                        . '<p>Click the button below to reset your password. This link will expire in <strong>1 hour</strong>.</p>'
                        . '<p style="text-align:center;margin:28px 0;">'
                        . '<a href="' . $resetLink . '" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Reset My Password</a>'
                        . '</p>'
                        . '<p style="color:#888;font-size:13px;">If you did not request this, you can safely ignore this email. Your password will not change.</p>'
                        . '<p style="color:#888;font-size:12px;">Or copy this link:<br><a href="' . $resetLink . '">' . $resetLink . '</a></p>'
                        . '</div></div>';

                    send_email($email, '', $subject, $body, 'support');
                }
            } catch (Exception $e) {
                error_log('forgot_password error: ' . $e->getMessage());
                // Still show generic success to prevent enumeration
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
    <title>Forgot Password — SoftandPix</title>
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
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo"><i class="bi bi-shield-lock"></i>SoftandPix</div>
    <div class="auth-subtitle">Enter your email to receive a password reset link</div>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($msgType, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!$message || $msgType === 'danger'): ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" id="email" name="email" class="form-control form-control-lg"
                   placeholder="you@example.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-submit">
            <i class="bi bi-envelope me-2"></i>Send Reset Link
        </button>
    </form>
    <?php endif; ?>

    <div class="back-link">
        <a href="/login.php"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
