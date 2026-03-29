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
    if (!is_string($token) || $token === '') return false;
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

function getSiteUrl($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_url' LIMIT 1");
        $stmt->execute();
        $url = trim($stmt->fetchColumn() ?? '');
        if (!empty($url)) return rtrim($url, '/');
    } catch (Exception $e) {}
    return 'https://softandpix.com';
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

function generateOrderNumber($pdo) {
    $year = date('Y');
    $month = date('m');
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
        $stmt->execute([$year, $month]);
        $count = $stmt->fetchColumn();
    } catch (Exception $e) {
        $count = 0;
    }
    return 'ORD-' . $year . $month . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

function getOrCreateCart($pdo, $userId = null, $sessionId = null) {
    if ($userId) {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $cart = $stmt->fetch();
        if ($cart) return $cart['id'];
        $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        return $pdo->lastInsertId();
    } elseif ($sessionId) {
        $stmt = $pdo->prepare("SELECT id FROM cart WHERE session_id = ? LIMIT 1");
        $stmt->execute([$sessionId]);
        $cart = $stmt->fetch();
        if ($cart) return $cart['id'];
        $stmt = $pdo->prepare("INSERT INTO cart (session_id) VALUES (?)");
        $stmt->execute([$sessionId]);
        return $pdo->lastInsertId();
    }
    return null;
}

function getCartItems($pdo, $cartId) {
    $stmt = $pdo->prepare("SELECT * FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
}

function getCartCount($pdo, $cartId) {
    if (!$cartId) return 0;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?");
    $stmt->execute([$cartId]);
    return (int)$stmt->fetchColumn();
}

function getCartTotal($pdo, $cartId) {
    if (!$cartId) return ['subtotal' => 0, 'discount' => 0, 'total' => 0];
    $items = getCartItems($pdo, $cartId);
    $subtotal = 0;
    $discountTotal = 0;
    foreach ($items as $item) {
        $lineSubtotal = $item['unit_price'] * $item['quantity'];
        $lineDiscount = $lineSubtotal * ($item['discount_percent'] / 100);
        $subtotal += $lineSubtotal;
        $discountTotal += $lineDiscount;
    }
    return [
        'subtotal' => round($subtotal, 2),
        'discount' => round($discountTotal, 2),
        'total'    => round($subtotal - $discountTotal, 2),
    ];
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending'    => 'warning',
        'processing' => 'primary',
        'approved'   => 'success',
        'rejected'   => 'danger',
        'completed'  => 'success',
        'cancelled'  => 'secondary',
        'refunded'   => 'info',
    ];
    return $badges[$status] ?? 'secondary';
}

function getPaymentStatusBadge($status) {
    $badges = [
        'unpaid'   => 'warning',
        'paid'     => 'success',
        'partial'  => 'info',
        'refunded' => 'secondary',
        'failed'   => 'danger',
    ];
    return $badges[$status] ?? 'secondary';
}

function createNotification($pdo, $userId, $type, $title, $message, $link = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $type, $title, $message, $link]);

        // Also dispatch a web push notification if push_helper is available
        if (function_exists('send_push_notification')) {
            @send_push_notification($pdo, (int)$userId, $title, $message, $link ?: null);
        }
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

function updateAdminPassword($pdo, $adminUsername, $newPasswordHash) {
    // Update admin_users table
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
    $stmt->execute([$newPasswordHash, $adminUsername]);

    // Also update users table to keep passwords in sync
    $emailStmt = $pdo->prepare("SELECT email FROM admin_users WHERE username = ? LIMIT 1");
    $emailStmt->execute([$adminUsername]);
    $email = $emailStmt->fetchColumn();

    if ($email) {
        $stmt2 = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
        $stmt2->execute([$newPasswordHash, $email]);
    }
}

/**
 * Get Admin Email SMTP config (info@ Zoho) from database settings.
 * Returns null if not yet configured, so callers can show a warning.
 */
function getAdminSmtpConfig($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('info_smtp_host','info_smtp_port','info_smtp_enc','info_smtp_user','info_smtp_pass')");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (empty($rows['info_smtp_host']) || empty($rows['info_smtp_user'])) {
            return null; // Not yet configured
        }
        return [
            'host'       => $rows['info_smtp_host'] ?? 'smtp.zoho.com',
            'port'       => (int)($rows['info_smtp_port'] ?? 465),
            'username'   => $rows['info_smtp_user'] ?? 'info@softandpix.com',
            'password'   => $rows['info_smtp_pass'] ?? '',
            'encryption' => $rows['info_smtp_enc'] ?? 'ssl',
            'from_name'  => 'SoftandPix',
            'from_email' => $rows['info_smtp_user'] ?? 'info@softandpix.com',
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if Admin Email SMTP (info@ Zoho) is configured in the database.
 */
function isAdminSmtpConfigured($pdo) {
    return getAdminSmtpConfig($pdo) !== null;
}

/**
 * Update user's last active timestamp
 */
function updateUserActivity($pdo, $userId) {
    if (!$userId) return;
    try {
        $pdo->prepare("UPDATE users SET last_active=NOW() WHERE id=?")->execute([$userId]);
    } catch (Exception $e) {}
}

/**
 * Check if a user is currently online (active in last 5 minutes)
 */
function isUserOnline($pdo, $userId) {
    if (!$userId) return false;
    try {
        $stmt = $pdo->prepare("SELECT last_active FROM users WHERE id=?");
        $stmt->execute([$userId]);
        $lastActive = $stmt->fetchColumn();
        if (!$lastActive) return false;
        return (time() - strtotime($lastActive)) < 300; // 5-minute window
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if admin is online (via admin_last_active in site_settings)
 */
function isAdminOnline($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='admin_last_active'");
        $stmt->execute();
        $lastActive = $stmt->fetchColumn();
        if (!$lastActive) return false;
        return (time() - strtotime($lastActive)) < 300; // 5-minute window
    } catch (Exception $e) {
        return false;
    }
}

require_once __DIR__ . '/security.php';
