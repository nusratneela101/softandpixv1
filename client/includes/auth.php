<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('requireClient')) {
function requireClient() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php?redirect=client');
        exit;
    }
    if (!in_array($_SESSION['user_role'], ['client', 'admin'])) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}
}

if (!function_exists('h')) {
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
}

if (!function_exists('generateCsrfToken')) {
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
}

if (!function_exists('verifyCsrfToken')) {
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
}

if (!function_exists('flashMessage')) {
function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
}

if (!function_exists('getFlashMessage')) {
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f;
    }
    return null;
}
}
