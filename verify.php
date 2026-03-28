<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

$token = trim($_GET['token'] ?? '');
$message = '';
$success = false;

if (empty($token)) {
    $message = 'Invalid verification link.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND email_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare("UPDATE users SET email_verified=1, verification_token=NULL WHERE id=?")->execute([$user['id']]);
            $success = true;
            $message = 'Email verified successfully! You can now log in.';
        } else {
            $message = 'Invalid or already used verification link.';
        }
    } catch (PDOException $e) {
        $message = 'Verification failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email Verification - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #1e2a3a 0%, #2d3f55 100%); min-height:100vh; display:flex; align-items:center; }
</style>
</head>
<body>
<div class="container">
<div class="row justify-content-center">
<div class="col-md-5">
    <div class="card border-0 shadow-lg" style="border-radius:16px;">
        <div class="card-body text-center p-5">
            <?php if ($success): ?>
            <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
            <h3 class="mt-3 text-success">Verified!</h3>
            <?php else: ?>
            <i class="bi bi-x-circle-fill text-danger" style="font-size:4rem;"></i>
            <h3 class="mt-3 text-danger">Verification Failed</h3>
            <?php endif; ?>
            <p class="text-muted mt-2"><?php echo h($message); ?></p>
            <a href="/login.php" class="btn btn-primary mt-3">Go to Login</a>
        </div>
    </div>
</div>
</div>
</div>
</body>
</html>
