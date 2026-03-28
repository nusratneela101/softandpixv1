<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

$account_keys = [
    'email_account_1_email',
    'email_account_1_password',
    'email_account_1_imap_host',
    'email_account_1_imap_port',
    'email_account_1_smtp_host',
    'email_account_1_smtp_port',
    'email_account_1_smtp_encryption',
    'email_account_1_label',
    'email_account_2_email',
    'email_account_2_password',
    'email_account_2_imap_host',
    'email_account_2_imap_port',
    'email_account_2_smtp_host',
    'email_account_2_smtp_port',
    'email_account_2_smtp_encryption',
    'email_account_2_label',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: email_accounts.php');
        exit;
    }
    try {
        foreach ($account_keys as $key) {
            // Skip blank password fields — don't overwrite stored password
            if (str_ends_with($key, '_password')) {
                $value = trim($_POST[$key] ?? '');
                if ($value === '') continue;
            } else {
                $value = trim($_POST[$key] ?? '');
            }
            $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$key, $value]);
        }
        flashMessage('success', 'Email account settings saved successfully!');
    } catch (PDOException $e) {
        flashMessage('error', 'Database error. Please try again.');
    }
    header('Location: email_accounts.php');
    exit;
}

$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

function sv($s, $k) {
    return htmlspecialchars($s[$k] ?? '', ENT_QUOTES, 'UTF-8');
}

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="bi bi-at me-2"></i>Email Accounts</h1>
    <p>Configure dual email accounts for the webmail and notification system</p>
</div>
<div class="container-fluid">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

        <!-- Account 1: info@softandpix.com (Zoho) -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-envelope-at me-2"></i>Account 1 — Info (Zoho)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Label</label>
                        <input type="text" name="email_account_1_label" class="form-control" placeholder="Info (Zoho)" value="<?php echo sv($settings, 'email_account_1_label'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email_account_1_email" class="form-control" placeholder="info@softandpix.com" value="<?php echo sv($settings, 'email_account_1_email'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" name="email_account_1_password" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">IMAP Host</label>
                        <input type="text" name="email_account_1_imap_host" class="form-control" placeholder="imap.zoho.com" value="<?php echo sv($settings, 'email_account_1_imap_host'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">IMAP Port</label>
                        <input type="number" name="email_account_1_imap_port" class="form-control" placeholder="993" value="<?php echo sv($settings, 'email_account_1_imap_port'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">SMTP Host</label>
                        <input type="text" name="email_account_1_smtp_host" class="form-control" placeholder="smtp.zoho.com" value="<?php echo sv($settings, 'email_account_1_smtp_host'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">SMTP Port</label>
                        <input type="number" name="email_account_1_smtp_port" class="form-control" placeholder="465" value="<?php echo sv($settings, 'email_account_1_smtp_port'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Encryption</label>
                        <select name="email_account_1_smtp_encryption" class="form-select">
                            <option value="ssl" <?php echo ($settings['email_account_1_smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($settings['email_account_1_smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="none" <?php echo ($settings['email_account_1_smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm test-imap-btn" data-account="1">
                        <i class="bi bi-plug me-1"></i>Test IMAP
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm test-smtp-btn" data-account="1">
                        <i class="bi bi-send me-1"></i>Test SMTP
                    </button>
                    <span class="test-result-1 ms-2 align-self-center"></span>
                </div>
            </div>
        </div>

        <!-- Account 2: support@softandpix.com (Hosting) -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-envelope-at me-2"></i>Account 2 — Support (Hosting)
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Label</label>
                        <input type="text" name="email_account_2_label" class="form-control" placeholder="Support (Hosting)" value="<?php echo sv($settings, 'email_account_2_label'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email_account_2_email" class="form-control" placeholder="support@softandpix.com" value="<?php echo sv($settings, 'email_account_2_email'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" name="email_account_2_password" class="form-control" placeholder="Leave blank to keep current" autocomplete="new-password">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">IMAP Host</label>
                        <input type="text" name="email_account_2_imap_host" class="form-control" placeholder="softandpix.com" value="<?php echo sv($settings, 'email_account_2_imap_host'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">IMAP Port</label>
                        <input type="number" name="email_account_2_imap_port" class="form-control" placeholder="993" value="<?php echo sv($settings, 'email_account_2_imap_port'); ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">SMTP Host</label>
                        <input type="text" name="email_account_2_smtp_host" class="form-control" placeholder="softandpix.com" value="<?php echo sv($settings, 'email_account_2_smtp_host'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">SMTP Port</label>
                        <input type="number" name="email_account_2_smtp_port" class="form-control" placeholder="465" value="<?php echo sv($settings, 'email_account_2_smtp_port'); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Encryption</label>
                        <select name="email_account_2_smtp_encryption" class="form-select">
                            <option value="ssl" <?php echo ($settings['email_account_2_smtp_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($settings['email_account_2_smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="none" <?php echo ($settings['email_account_2_smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm test-imap-btn" data-account="2">
                        <i class="bi bi-plug me-1"></i>Test IMAP
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm test-smtp-btn" data-account="2">
                        <i class="bi bi-send me-1"></i>Test SMTP
                    </button>
                    <span class="test-result-2 ms-2 align-self-center"></span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-save me-2"></i>Save Email Account Settings
        </button>
    </form>
</div>
<script>
function testConnection(type, account, resultEl) {
    var acct = account;
    var pfx = 'email_account_' + acct + '_';
    var form = document.querySelector('form');
    var host = type === 'imap'
        ? form.querySelector('[name="' + pfx + 'imap_host"]').value
        : form.querySelector('[name="' + pfx + 'smtp_host"]').value;
    var port = type === 'imap'
        ? form.querySelector('[name="' + pfx + 'imap_port"]').value
        : form.querySelector('[name="' + pfx + 'smtp_port"]').value;
    var enc  = form.querySelector('[name="' + pfx + 'smtp_encryption"]').value;
    var email = form.querySelector('[name="' + pfx + 'email"]').value;
    var pass  = form.querySelector('[name="' + pfx + 'password"]').value;

    resultEl.innerHTML = '<span class="text-muted">Testing...</span>';
    var data = new FormData();
    data.append('type', type);
    data.append('account', acct);
    data.append('host', host);
    data.append('port', port);
    data.append('encryption', enc);
    data.append('email', email);
    data.append('password', pass);

    fetch('../api/email_test.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(function(res) {
            resultEl.innerHTML = res.success
                ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + res.message + '</span>'
                : '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + res.message + '</span>';
        })
        .catch(function() {
            resultEl.innerHTML = '<span class="text-danger">Request failed.</span>';
        });
}

document.querySelectorAll('.test-imap-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var acct = this.dataset.account;
        var res = document.querySelector('.test-result-' + acct);
        testConnection('imap', acct, res);
    });
});

document.querySelectorAll('.test-smtp-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var acct = this.dataset.account;
        var res = document.querySelector('.test-result-' + acct);
        testConnection('smtp', acct, res);
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
