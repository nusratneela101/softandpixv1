<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireClient() {
    requireLogin();
    if (!in_array($_SESSION['user_role'], ['client', 'admin'])) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function requireDeveloper() {
    requireLogin();
    if (!in_array($_SESSION['user_role'], ['developer', 'admin'])) {
        header('Location: /login.php?error=unauthorized');
        exit;
    }
}

function requireAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /admin/login.php');
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

require_once __DIR__ . '/security.php';
