<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
$flash = getFlashMessage();
try {
    if (!isset($pdo)) throw new Exception('PDO not available');
    $settings_rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    $site_settings = [];
    foreach ($settings_rows as $row) {
        $site_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $site_settings = [];
}
$site_title = $site_settings['site_title'] ?? 'Softandpix';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title ?? $site_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="height:40px;">
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/profile.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-circle"></i> <?php echo h($_SESSION['user_name'] ?? 'Profile'); ?>
            </a>
            <a href="/logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
            <?php else: ?>
            <a href="/login.php" class="btn btn-outline-primary btn-sm">Login</a>
            <a href="/register.php" class="btn btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php if ($flash): ?>
<div class="container mt-2">
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <?php echo h($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
