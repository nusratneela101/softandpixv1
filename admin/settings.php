<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

$setting_keys = [
    'site_title', 'meta_description', 'meta_keywords',
    'address', 'phone', 'email', 'open_hours',
    'twitter_url', 'facebook_url', 'instagram_url', 'linkedin_url',
    'footer_copyright', 'footer_powered_by',
    'newsletter_title', 'newsletter_description',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
    'smtp_from_email', 'smtp_from_name', 'admin_notification_email',
    'overdue_reminder_enabled', 'overdue_reminder_interval_days',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: settings.php');
        exit;
    }

    try {
        foreach ($setting_keys as $key) {
            if ($key === 'smtp_password') {
                $value = trim($_POST[$key] ?? '');
                if ($value === '') continue; // skip if blank — don't overwrite stored password
            } else {
                $value = trim($_POST[$key] ?? '');
            }
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
        }
        flashMessage('success', 'Settings saved successfully!');
    } catch(PDOException $e) {
        flashMessage('error', 'Database error. Please try again.');
    }

    header('Location: settings.php');
    exit;
}

$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch(Exception $e) {}

function sv($settings, $key) {
    return htmlspecialchars($settings[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-gear me-2"></i>Site Settings</h1>
    <p>Manage global website settings</p>
</div>
<div class="container-fluid">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <!-- General -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white"><i class="bi bi-globe me-2"></i>General Settings</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Site Title</label>
                        <input type="text" name="site_title" class="form-control" value="<?php echo sv($settings, 'site_title'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control" value="<?php echo sv($settings, 'meta_keywords'); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label fw-bold">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"><?php echo sv($settings, 'meta_description'); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white"><i class="bi bi-telephone me-2"></i>Contact Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo sv($settings, 'address'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo sv($settings, 'phone'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo sv($settings, 'email'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Open Hours</label>
                        <input type="text" name="open_hours" class="form-control" placeholder="Mon-Fri: 9am-6pm" value="<?php echo sv($settings, 'open_hours'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Media -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white"><i class="bi bi-share me-2"></i>Social Media</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-twitter me-1"></i>Twitter URL</label>
                        <input type="url" name="twitter_url" class="form-control" value="<?php echo sv($settings, 'twitter_url'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-facebook me-1"></i>Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-control" value="<?php echo sv($settings, 'facebook_url'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-instagram me-1"></i>Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-control" value="<?php echo sv($settings, 'instagram_url'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><i class="bi bi-linkedin me-1"></i>LinkedIn URL</label>
                        <input type="url" name="linkedin_url" class="form-control" value="<?php echo sv($settings, 'linkedin_url'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="card mb-4">
            <div class="card-header bg-dark text-white"><i class="bi bi-layout-bottom me-2"></i>Footer Settings</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Copyright Text</label>
                        <input type="text" name="footer_copyright" class="form-control" value="<?php echo sv($settings, 'footer_copyright'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Powered By Text</label>
                        <input type="text" name="footer_powered_by" class="form-control" value="<?php echo sv($settings, 'footer_powered_by'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Newsletter -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white"><i class="bi bi-envelope-paper me-2"></i>Newsletter Section</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Newsletter Title</label>
                        <input type="text" name="newsletter_title" class="form-control" value="<?php echo sv($settings, 'newsletter_title'); ?>">
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold">Newsletter Description</label>
                        <input type="text" name="newsletter_description" class="form-control" value="<?php echo sv($settings, 'newsletter_description'); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SMTP Configuration -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark"><i class="bi bi-envelope-at me-2"></i>SMTP Configuration</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" placeholder="softandpix.com" value="<?php echo sv($settings, 'smtp_host'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" placeholder="465" value="<?php echo sv($settings, 'smtp_port'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($settings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">SMTP Username</label>
                        <input type="email" name="smtp_username" class="form-control" placeholder="support@softandpix.com" value="<?php echo sv($settings, 'smtp_username'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="Leave blank to keep current password" autocomplete="new-password">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">From Email</label>
                        <input type="email" name="smtp_from_email" class="form-control" placeholder="support@softandpix.com" value="<?php echo sv($settings, 'smtp_from_email'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" placeholder="Softandpix" value="<?php echo sv($settings, 'smtp_from_name'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Admin Notification Email</label>
                        <input type="email" name="admin_notification_email" class="form-control" placeholder="info@softandpix.com" value="<?php echo sv($settings, 'admin_notification_email'); ?>">
                    </div>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-outline-secondary" id="testSmtpBtn">
                        <i class="bi bi-plug me-1"></i>Test SMTP Connection
                    </button>
                    <span id="smtpTestResult" class="ms-3"></span>
                </div>
            </div>
        </div>

        <!-- Overdue Invoice Reminders -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white"><i class="bi bi-alarm me-2"></i>Overdue Invoice Reminders</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Enable Overdue Reminders</label>
                        <select name="overdue_reminder_enabled" class="form-select">
                            <option value="1" <?php echo (($settings['overdue_reminder_enabled'] ?? '1') === '1') ? 'selected' : ''; ?>>Enabled</option>
                            <option value="0" <?php echo (($settings['overdue_reminder_enabled'] ?? '1') === '0') ? 'selected' : ''; ?>>Disabled</option>
                        </select>
                        <div class="form-text">Automatically email clients when invoices are past due.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Reminder Interval (days)</label>
                        <input type="number" name="overdue_reminder_interval_days" class="form-control" min="1" max="30"
                               value="<?php echo sv($settings, 'overdue_reminder_interval_days') ?: '3'; ?>">
                        <div class="form-text">How many days between repeated reminders (e.g., 3 = remind every 3 days).</div>
                    </div>
                </div>
                <div class="alert alert-info mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Add this cron job to send reminders automatically:<br>
                    <code>0 8 * * * php <?php echo htmlspecialchars(dirname(dirname(__FILE__)), ENT_QUOTES); ?>/cron/overdue_reminders.php</code>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save me-2"></i>Save All Settings
        </button>
    </form>
</div>
<script>
document.getElementById('testSmtpBtn').addEventListener('click', function () {
    var btn = this;
    var result = document.getElementById('smtpTestResult');
    btn.disabled = true;
    result.innerHTML = '<span class="text-muted">Testing...</span>';
    var form = btn.closest('form');
    var data = new FormData();
    data.append('type', 'smtp');
    data.append('host', form.querySelector('[name="smtp_host"]').value);
    data.append('port', form.querySelector('[name="smtp_port"]').value);
    data.append('encryption', form.querySelector('[name="smtp_encryption"]').value);
    data.append('email', form.querySelector('[name="smtp_username"]').value);
    data.append('password', form.querySelector('[name="smtp_password"]').value);
    fetch('../api/email_test.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(function (res) {
            result.innerHTML = res.success
                ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + res.message + '</span>'
                : '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + res.message + '</span>';
        })
        .catch(function () {
            result.innerHTML = '<span class="text-danger">Request failed.</span>';
        })
        .finally(function () { btn.disabled = false; });
});
</script>
<?php require_once 'includes/footer.php'; ?>
