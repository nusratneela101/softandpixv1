<?php
/**
 * Security helper aliases and utilities for SoftandPix.
 *
 * This file is auto-included at the end of functions.php and auth.php so that
 * every script in the project has access to both the camelCase originals and
 * the snake_case aliases used throughout the codebase.
 */

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

if (!function_exists('e')) {
    /**
     * Alias for h() — HTML-encode a string for safe output.
     */
    function e($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('time_ago')) {
    /** Alias for timeAgo(). */
    function time_ago($datetime) {
        return timeAgo($datetime);
    }
}

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

if (!function_exists('redirect')) {
    /**
     * Safe redirect: validates the URL is same-origin or a relative path,
     * then issues a Location header and exits.
     */
    function redirect($url) {
        // Strip leading/trailing whitespace and null bytes
        $url = trim(preg_replace('/[\x00-\x1f]/', '', $url));

        // Allow relative paths and same-host absolute URLs only.
        // Use SERVER_NAME (set by web-server config) rather than the user-controlled HTTP_HOST.
        $parsed = parse_url($url);
        if (!empty($parsed['host'])) {
            // SERVER_NAME falls back to HTTP_HOST only when it is not set
            $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
            if ($parsed['host'] !== $serverName) {
                // Fall back to home page if host doesn't match
                $url = '/';
            }
        }

        header('Location: ' . $url, true, 302);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Authentication aliases
// ---------------------------------------------------------------------------

if (!function_exists('require_login')) {
    /** Alias for requireLogin(). */
    function require_login() {
        requireLogin();
    }
}

if (!function_exists('requireAuth')) {
    /** Alias for requireAdmin() — used by admin panel pages. */
    function requireAuth() {
        requireAdmin();
    }
}

// ---------------------------------------------------------------------------
// CSRF aliases
// ---------------------------------------------------------------------------

if (!function_exists('generate_csrf_token')) {
    /** Alias for generateCsrfToken(). */
    function generate_csrf_token() {
        return generateCsrfToken();
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Alias for verifyCsrfToken().  Responds with HTTP 403 and exits on failure
     * so callers don't need to check a return value.
     */
    function verify_csrf_token($token) {
        if (!verifyCsrfToken($token)) {
            http_response_code(403);
            exit('Invalid or missing CSRF token.');
        }
    }
}

// ---------------------------------------------------------------------------
// Flash message aliases
// ---------------------------------------------------------------------------

if (!function_exists('set_flash')) {
    /** Alias for flashMessage(). */
    function set_flash($type, $message) {
        flashMessage($type, $message);
    }
}

if (!function_exists('get_flash')) {
    /** Alias for getFlashMessage(). */
    function get_flash() {
        return getFlashMessage();
    }
}

// ---------------------------------------------------------------------------
// Online status alias
// ---------------------------------------------------------------------------

if (!function_exists('update_online_status')) {
    /** Alias for updateUserActivity(). */
    function update_online_status($pdo, $userId) {
        updateUserActivity($pdo, $userId);
    }
}

// ---------------------------------------------------------------------------
// File upload helpers
// ---------------------------------------------------------------------------

if (!function_exists('validate_file_upload')) {
    /**
     * Thin wrapper around validateUploadedFile().
     * Returns true on success, false on failure.
     */
    function validate_file_upload($file, $allowedTypes = [], $maxSize = 10485760) {
        $result = validateUploadedFile($file, $allowedTypes, $maxSize);
        return $result['ok'];
    }
}

if (!function_exists('upload_file')) {
    /**
     * Validate and move an uploaded file to the uploads directory.
     *
     * @param array  $file    $_FILES entry
     * @param string $subdir  Sub-directory under uploads/ (e.g. "projects/42")
     * @return string|false   Relative path on success, false on failure
     */
    function upload_file($file, $subdir = '') {
        $result = validateUploadedFile($file);
        if (!$result['ok']) {
            return false;
        }

        // Defense-in-depth: ensure the extension is on the safe whitelist
        $safeExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx',
                           'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip', 'svg'];
        if (!in_array($result['ext'], $safeExtensions, true)) {
            return false;
        }

        // Build the target directory, preventing path traversal
        $subdir  = trim(preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $subdir), '/');
        $baseDir = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $destDir = $baseDir . '/uploads/' . ($subdir ? $subdir . '/' : '');

        if (!is_dir($destDir)) {
            if (!mkdir($destDir, 0755, true)) {
                return false;
            }
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $result['ext'];
        $destPath = $destDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return false;
        }

        return 'uploads/' . ($subdir ? $subdir . '/' : '') . $filename;
    }
}

// ---------------------------------------------------------------------------
// User & notification aliases
// ---------------------------------------------------------------------------

if (!function_exists('get_user')) {
    /**
     * Fetch a single user row by primary key.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return array|false
     */
    function get_user($pdo, $userId) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('create_notification')) {
    /** Alias for createNotification(). */
    function create_notification($pdo, $userId, $type, $title, $message, $link = '') {
        createNotification($pdo, $userId, $type, $title, $message, $link);
    }
}

// ---------------------------------------------------------------------------
// Email sending helper
// ---------------------------------------------------------------------------

if (!function_exists('send_email')) {
    /**
     * Send an email via PHPMailer (if available) or PHP mail() fallback.
     *
     * @param string $toEmail    Recipient address
     * @param string $toName     Recipient display name
     * @param string $subject    Subject line
     * @param string $body       HTML body
     * @param string $account    SMTP account key: 'support' or 'info'
     * @return bool
     */
    function send_email($toEmail, $toName, $subject, $body, $account = 'support') {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Try PHPMailer if available
        $phpmailerPath = defined('BASE_PATH')
            ? BASE_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php'
            : dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';

        if (file_exists($phpmailerPath)) {
            try {
                require_once $phpmailerPath;
                require_once dirname($phpmailerPath) . '/SMTP.php';
                require_once dirname($phpmailerPath) . '/Exception.php';

                $smtpConf = null;
                if (isset($GLOBALS['pdo'])) {
                    if ($account === 'info') {
                        $smtpConf = getAdminSmtpConfig($GLOBALS['pdo']);
                    } else {
                        // Read support SMTP from site_settings or smtp.php
                        $smtpFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/config/smtp.php';
                        if (file_exists($smtpFile)) {
                            include_once $smtpFile;
                            $smtpConf = $smtp_config['support'] ?? null;
                        }
                    }
                }

                if ($smtpConf) {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $smtpConf['host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtpConf['username'];
                    $mail->Password   = $smtpConf['password'];
                    $mail->SMTPSecure = ($smtpConf['encryption'] === 'tls')
                        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
                        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = (int)$smtpConf['port'];
                    $mail->setFrom($smtpConf['from_email'], $smtpConf['from_name']);
                    $mail->addAddress($toEmail, $toName);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $body;
                    $mail->send();
                    return true;
                }
            } catch (Exception $e) {
                error_log('send_email PHPMailer error: ' . $e->getMessage());
            }
        }

        // Fallback: PHP mail()
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: SoftandPix <noreply@softandpix.com>' . "\r\n";
        return mail($toEmail, $subject, $body, $headers);
    }
}
