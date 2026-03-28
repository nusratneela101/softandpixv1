<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function requireAnyRole($roles) {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin' && !in_array($_SESSION['user_role'], $roles)) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'email'=> $_SESSION['user_email'] ?? '',
    ];
}
