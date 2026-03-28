<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ' . getAdminUrl('login.php'));
        exit;
    }
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

function getAdminUrl($page = '') {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim($base, '/');
    if (empty($page)) return $base . '/';
    return $base . '/' . $page;
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
