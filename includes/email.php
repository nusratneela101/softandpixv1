<?php
/**
 * Email functions using PHPMailer or PHP mail() fallback
 */

function sendEmail($pdo, $to, $subject, $htmlBody, $attachments = []) {
    // Load SMTP settings from database
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'admin_%' OR setting_key = 'site_name'")->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        $settings = [];
    }

    $smtpHost     = $settings['smtp_host'] ?? 'softandpix.com';
    $smtpPort     = (int)($settings['smtp_port'] ?? 465);
    $smtpEncrypt  = $settings['smtp_encryption'] ?? 'ssl';
    $smtpUser     = $settings['smtp_username'] ?? 'support@softandpix.com';
    $smtpPass     = $settings['smtp_password'] ?? '';
    $fromEmail    = $settings['smtp_from_email'] ?? 'support@softandpix.com';
    $fromName     = $settings['smtp_from_name'] ?? ($settings['site_name'] ?? 'Softandpix Support');

    // Try PHPMailer if available
    $autoloadPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
    ];
    $phpMailerLoaded = false;
    foreach ($autoloadPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $phpMailerLoaded = true;
            break;
        }
    }

    if ($phpMailerLoaded && class_exists('PHPMailer\\PHPMailer\\PHPMailer') && !empty($smtpHost)) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUser;
            $mail->Password   = $smtpPass;
            $mail->Port       = $smtpPort;

            if ($smtpEncrypt === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtpEncrypt === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromEmail ?: 'support@softandpix.com', $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            foreach ($attachments as $att) {
                if (isset($att['path']) && file_exists($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name'] ?? basename($att['path']));
                }
            }

            $mail->send();
            logEmail($pdo, $to, '', $subject, $htmlBody, 'sent');
            return true;
        } catch (Exception $e) {
            logEmail($pdo, $to, '', $subject, $htmlBody, 'failed', $e->getMessage());
            return false;
        }
    }

    // Fallback: PHP mail()
    if (!empty($fromEmail)) {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        $headers .= "Reply-To: $fromEmail\r\n";

        $result = mail($to, $subject, $htmlBody, $headers);
        logEmail($pdo, $to, '', $subject, $htmlBody, $result ? 'sent' : 'failed', $result ? '' : 'mail() returned false');
        return $result;
    }

    // Final fallback using support@ as sender
    $defaultFrom = 'support@softandpix.com';
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Softandpix <$defaultFrom>\r\n";
    $headers .= "Reply-To: $defaultFrom\r\n";
    $result = mail($to, $subject, $htmlBody, $headers);
    logEmail($pdo, $to, '', $subject, $htmlBody, $result ? 'sent' : 'failed', $result ? '' : 'mail() returned false');
    return $result;
}

function logEmail($pdo, $toEmail, $toName, $subject, $body, $status, $errorMsg = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO email_log (to_email, to_name, subject, body, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$toEmail, $toName, $subject, $body, $status, $errorMsg]);
    } catch (Exception $e) {}
}

function getEmailTemplate($pdo, $name) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Send a notification email to the admin (info@softandpix.com by default).
 * Used whenever a client/developer/visitor submits a form or triggers an event.
 */
function sendAdminNotification($pdo, $subject, $htmlBody) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'admin_notification_email' LIMIT 1");
        $stmt->execute();
        $adminEmail = $stmt->fetchColumn();
    } catch (Exception $e) {
        $adminEmail = '';
    }
    if (empty($adminEmail)) {
        $adminEmail = 'info@softandpix.com';
    }
    return sendEmail($pdo, $adminEmail, $subject, $htmlBody);
}
