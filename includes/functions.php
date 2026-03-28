<?php
/**
 * Helper functions for Softandpix
 */

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function flashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function getRoleProfileFields($pdo, $role) {
    try {
        $stmt = $pdo->prepare("SELECT profile_fields FROM custom_roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $row = $stmt->fetch();
        return $row ? json_decode($row['profile_fields'], true) : ['name','email','password'];
    } catch (Exception $e) {
        return ['name','email','password'];
    }
}

function getRolePermissions($pdo, $role) {
    try {
        $stmt = $pdo->prepare("SELECT permissions FROM custom_roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $row = $stmt->fetch();
        return $row ? json_decode($row['permissions'], true) : [];
    } catch (Exception $e) {
        return [];
    }
}

function hasPermission($pdo, $role, $permission) {
    $perms = getRolePermissions($pdo, $role);
    return isset($perms['all']) || !empty($perms[$permission]);
}

function getRoleColor($role) {
    $colors = [
        'admin'         => '#dc3545',
        'developer'     => '#0d6efd',
        'client'        => '#198754',
        'editor'        => '#6f42c1',
        'ui_designer'   => '#fd7e14',
        'seo_specialist'=> '#20c997',
    ];
    return $colors[$role] ?? '#6c757d';
}

function getRoleLabel($pdo, $role) {
    try {
        $stmt = $pdo->prepare("SELECT role_label, role_color FROM custom_roles WHERE role_name = ?");
        $stmt->execute([$role]);
        $row = $stmt->fetch();
        if ($row) return $row;
    } catch (Exception $e) {}
    return ['role_label' => ucfirst(str_replace('_', ' ', $role)), 'role_color' => '#6c757d'];
}

function getSetting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function generateInvoiceNumber($pdo) {
    $year = date('Y');
    $month = date('m');
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
        $stmt->execute([$year, $month]);
        $count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $count = 0;
    }
    return 'INV-' . $year . $month . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function createNotification($pdo, $userId, $type, $title, $message, $link = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $message, $link]);
    } catch (Exception $e) {}
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 604800) return floor($time/86400) . ' days ago';
    return date('M j, Y', strtotime($datetime));
}

function formatCurrency($amount, $currency = 'USD') {
    return $currency . ' ' . number_format((float)$amount, 2);
}

function validateUploadedFile($file, $allowedTypes = [], $maxSize = 10485760) {
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'Upload error'];
    if ($file['size'] > $maxSize) return ['ok' => false, 'error' => 'File too large (max 10MB)'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
        return ['ok' => false, 'error' => 'File type not allowed'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dangerousExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat'];
    if (in_array($ext, $dangerousExts)) {
        return ['ok' => false, 'error' => 'File type not allowed'];
    }

    return ['ok' => true, 'mime' => $mimeType, 'ext' => $ext];
}

function getStatusBadge($status) {
    $badges = [
        'pending'     => 'warning',
        'in_progress' => 'primary',
        'on_hold'     => 'secondary',
        'completed'   => 'success',
        'cancelled'   => 'danger',
        'draft'       => 'secondary',
        'sent'        => 'info',
        'paid'        => 'success',
        'overdue'     => 'danger',
    ];
    return $badges[$status] ?? 'secondary';
}

function getPriorityBadge($priority) {
    $badges = [
        'low'    => 'success',
        'medium' => 'warning',
        'high'   => 'warning',
        'urgent' => 'danger',
    ];
    return $badges[$priority] ?? 'secondary';
}
