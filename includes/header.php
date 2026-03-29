<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/theme.php';
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
$site_title  = $site_settings['site_title'] ?? 'Softandpix';
$_dark_mode  = get_theme($pdo ?? null, 'dark_mode', '0');
$_theme_font = get_theme($pdo ?? null, 'font_family', 'Roboto');
$_lang_cur   = current_lang();
?>
<!DOCTYPE html>
<html lang="<?= h($_lang_cur) ?>" dir="<?= text_direction() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title ?? $site_title); ?></title>
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#16213e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SoftandPix">
    <meta name="csrf-token" content="<?= h(generateCsrfToken()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link href="/assets/css/app.css" rel="stylesheet">
    <?php if (isset($pdo)) render_google_font($pdo); ?>
    <?php if (isset($pdo)) render_theme_css($pdo); ?>
</head>
<body class="<?= $_dark_mode === '1' ? 'dark-mode' : '' ?>">

<!-- Mobile sidebar toggle -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
    <i class="bi bi-list"></i>
</button>

<!-- PWA Install Banner -->
<div id="pwa-install-banner" onclick="SP.installPWA()">
    📱 <?= __('install_app') ?>
    <button class="close-banner" aria-label="Close">✕</button>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <?php
            $_logo = get_theme($pdo ?? null, 'logo_url', '');
            if ($_logo): ?>
            <img src="<?= h($_logo) ?>" alt="<?= h($site_title) ?>" style="height:40px;" loading="lazy">
            <?php else: ?>
            <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="height:40px;" loading="lazy">
            <?php endif; ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Language Switcher -->
            <?php $_langs = get_supported_languages(); $_lang_info = $_langs[$_lang_cur] ?? $_langs['en']; ?>
            <div class="dropdown lang-switcher">
                <button class="lang-btn dropdown-toggle" type="button" id="langDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <?= h($_lang_info['flag']) ?> <?= strtoupper(str_replace('_', '-', $_lang_cur)) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end lang-dropdown-menu" aria-labelledby="langDropdown">
                    <?php foreach ($_langs as $_lcode => $_linfo): ?>
                    <li>
                        <button class="dropdown-item <?= $_lang_cur === $_lcode ? 'active' : '' ?>"
                                type="button" data-lang-switch="<?= h($_lcode) ?>">
                            <?= h($_linfo['flag']) ?> <?= h($_linfo['native']) ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Active timer indicator -->
            <?php
            $_timer_active = false;
            if (isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM active_timers WHERE user_id=?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $_timer_active = (bool)$stmt->fetch();
                } catch (Exception $e) {}
            }
            if ($_timer_active): ?>
            <span class="timer-indicator" title="Timer is running">
                <span>●</span> <?= __('timer_running') ?>
            </span>
            <?php endif; ?>
            <a href="/profile.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-circle"></i> <?php echo h($_SESSION['user_name'] ?? 'Profile'); ?>
            </a>
            <a href="/logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> <?= __('logout') ?>
            </a>
            <?php else: ?>
            <a href="/login.php" class="btn btn-outline-primary btn-sm"><?= __('login') ?></a>
            <a href="/register.php" class="btn btn-primary btn-sm"><?= __('register') ?></a>
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
