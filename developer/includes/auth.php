<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function requireDeveloper() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php?redirect=developer');
        exit;
    }
    $allowedRoles = ['developer', 'admin', 'editor', 'ui_designer', 'seo_specialist'];
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
    }
    return null;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    return floor($time/86400) . ' days ago';
}
