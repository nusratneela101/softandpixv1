<?php
/**
 * SoftandPix Install Wizard
 */
session_start();
define('BASE_PATH', dirname(__DIR__));

// If already installed, redirect
if (file_exists(BASE_PATH . '/config/installed.lock')) {
    header('Location: ../login.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$step = max(1, min(5, $step));

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $is_ajax = in_array($action, ['test_db', 'run_install', 'test_smtp'], true);

    // CSRF validation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        } else {
            header('Location: ?step=' . $step);
        }
        exit;
    }

    if ($action === 'test_db') {
        header('Content-Type: application/json');
        try {
            // Sanitize: db_host may only contain alphanumerics, dots, hyphens (IPv4/hostname)
            $db_host = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_POST['db_host'] ?? '');
            $db_name = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'] ?? '');
            // Extra hardening: strip backticks to prevent injection in CREATE DATABASE
            $db_name = str_replace('`', '', $db_name);
            if ($db_host === '' || $db_name === '') {
                echo json_encode(['success' => false, 'message' => 'Invalid database host or name.']);
                exit;
            }
            $dsn = 'mysql:host=' . $db_host . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $db_name . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo json_encode(['success' => true, 'message' => 'Database connection successful!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'test_smtp') {
        header('Content-Type: application/json');
        $host       = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_POST['support_host'] ?? '');
        $port       = (int)($_POST['support_port'] ?? 465);
        $user       = $_POST['support_email'] ?? '';
        $pass       = $_POST['support_pass'] ?? '';
        $encryption = in_array($_POST['support_encryption'] ?? '', ['ssl', 'tls'], true) ? $_POST['support_encryption'] : 'ssl';

        if (!filter_var($user, FILTER_VALIDATE_EMAIL) || $host === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid email or host.']);
            exit;
        }

        // Try PHPMailer if available
        $vendor = BASE_PATH . '/vendor/autoload.php';
        if (file_exists($vendor)) {
            require_once $vendor;
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $host;
                $mail->SMTPAuth   = true;
                $mail->Username   = $user;
                $mail->Password   = $pass;
                $mail->SMTPSecure = $encryption === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $port;
                $mail->SMTPDebug  = 0;
                $mail->Timeout    = 10;
                if ($mail->smtpConnect()) {
                    $mail->smtpClose();
                    echo json_encode(['success' => true, 'message' => 'SMTP connection successful! Credentials verified.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'SMTP connection failed. Check host/port/credentials.']);
                }
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()]);
            }
        } else {
            // Fallback: raw socket connectivity test
            $prefix  = ($encryption === 'ssl') ? 'ssl://' : '';
            $timeout = 10;
            $errno   = 0; $errstr  = '';
            $sock = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
            if ($sock) {
                fclose($sock);
                echo json_encode(['success' => true, 'message' => 'SMTP host reachable on port ' . $port . '. (Full auth test requires PHPMailer.)']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cannot reach SMTP host: ' . $errstr . ' (error ' . $errno . ')']);
            }
        }
        exit;
    }

    if ($action === 'save_step1') {
        $_SESSION['install'] = $_SESSION['install'] ?? [];
        $_SESSION['install']['db'] = [
            'host' => preg_replace('/[^a-zA-Z0-9.\-]/', '', $_POST['db_host'] ?? ''),
            'name' => preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['db_name'] ?? ''),
            'user' => $_POST['db_user'] ?? '',
            'pass' => $_POST['db_pass'] ?? ''
        ];
        header('Location: ?step=2');
        exit;
    }
    
    if ($action === 'save_step2') {
        $support_host       = preg_replace('/[^a-zA-Z0-9.\-]/', '', $_POST['support_host'] ?? '');
        $support_port       = (int)($_POST['support_port'] ?? 465);
        $support_email      = $_POST['support_email'] ?? '';
        $support_encryption = in_array($_POST['support_encryption'] ?? '', ['ssl', 'tls'], true)
                              ? $_POST['support_encryption'] : 'ssl';
        if (!filter_var($support_email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ?step=2');
            exit;
        }
        $_SESSION['install']['smtp'] = [
            'support_host'       => $support_host,
            'support_port'       => $support_port,
            'support_email'      => $support_email,
            'support_pass'       => $_POST['support_pass'] ?? '',
            'support_encryption' => $support_encryption,
        ];
        header('Location: ?step=3');
        exit;
    }
    
    if ($action === 'save_step3') {
        $_SESSION['install']['admin'] = [
            'name' => $_POST['admin_name'],
            'email' => $_POST['admin_email'],
            'password' => $_POST['admin_password'],
        ];
        header('Location: ?step=4');
        exit;
    }
    
    if ($action === 'save_step4') {
        $_SESSION['install']['site'] = [
            'name' => $_POST['site_name'],
            'url' => rtrim($_POST['site_url'], '/'),
        ];
        header('Location: ?step=5');
        exit;
    }
    
    if ($action === 'run_install') {
        header('Content-Type: application/json');

        // Check config/ directory is writable before proceeding
        if (!is_writable(BASE_PATH . '/config/')) {
            echo json_encode(['success' => false, 'message' => 'Config directory is not writable. Please set permissions to 755 or 775 on the config/ folder.']);
            exit;
        }

        $inst = $_SESSION['install'] ?? [];
        $errors = [];
        
        try {
            // 1. Connect to database
            $dsn = 'mysql:host=' . $inst['db']['host'] . ';dbname=' . $inst['db']['name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $inst['db']['user'], $inst['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // 2. Run schema
            $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $sql) {
                if (!empty($sql)) {
                    $pdo->exec($sql);
                }
            }
            
            // 3. Create admin account
            $admin_pass = password_hash($inst['admin']['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, email_verified) VALUES (?, ?, ?, 'admin', 1, 1) ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password)");
            $stmt->execute([$inst['admin']['name'], $inst['admin']['email'], $admin_pass]);
            
            // 4. Generate config/db.php
            // Host and name are pre-sanitised (alphanumerics/dots/hyphens only), so concatenation is safe
            $db_config = "<?php\n/**\n * Database Configuration (auto-generated by installer)\n */\n\n";
            $db_config .= "try {\n";
            $db_config .= "    \$pdo = new PDO(\n";
            $db_config .= "        " . var_export('mysql:host=' . $inst['db']['host'] . ';dbname=' . $inst['db']['name'] . ';charset=utf8mb4', true) . ",\n";
            $db_config .= "        " . var_export($inst['db']['user'], true) . ",\n";
            $db_config .= "        " . var_export($inst['db']['pass'], true) . ",\n";
            $db_config .= "        [\n";
            $db_config .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
            $db_config .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
            $db_config .= "            PDO::ATTR_EMULATE_PREPARES => false\n";
            $db_config .= "        ]\n";
            $db_config .= "    );\n";
            $db_config .= "} catch (PDOException \$e) {\n";
            $db_config .= "    die('Database connection failed.');\n";
            $db_config .= "}\n";
            file_put_contents(BASE_PATH . '/config/db.php', $db_config);
            
            // 5. Generate config/smtp.php (support@ only — info@ Zoho is configured via Admin Panel)
            $smtp_config = "<?php\n/**\n * SMTP Configuration (auto-generated by installer)\n * Only Website SMTP (support@) is stored here.\n * Admin Email SMTP (info@ Zoho) is configured in Admin Panel → Settings.\n */\n\n";
            $smtp_config .= "\$smtp_config = [\n";
            $smtp_config .= "    'support' => [\n";
            $smtp_config .= "        'host'       => " . var_export($inst['smtp']['support_host'], true) . ",\n";
            $smtp_config .= "        'port'       => " . (int)$inst['smtp']['support_port'] . ",\n";
            $smtp_config .= "        'username'   => " . var_export($inst['smtp']['support_email'], true) . ",\n";
            $smtp_config .= "        'password'   => " . var_export($inst['smtp']['support_pass'], true) . ",\n";
            $smtp_config .= "        'encryption' => " . var_export($inst['smtp']['support_encryption'], true) . ",\n";
            $smtp_config .= "        'from_name'  => 'SoftandPix Support',\n";
            $smtp_config .= "        'from_email' => " . var_export($inst['smtp']['support_email'], true) . ",\n";
            $smtp_config .= "    ],\n";
            $smtp_config .= "];\n";
            file_put_contents(BASE_PATH . '/config/smtp.php', $smtp_config);
            
            // 6. Update site settings in DB
            $stmt = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key='site_name'");
            $stmt->execute([$inst['site']['name']]);
            $stmt = $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key='site_url'");
            $stmt->execute([$inst['site']['url']]);
            
            // 7. Create installed.lock
            file_put_contents(BASE_PATH . '/config/installed.lock', date('Y-m-d H:i:s') . "\nInstalled successfully.");
            
            unset($_SESSION['install']);
            echo json_encode(['success' => true, 'message' => 'Installation complete!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Installation error: ' . $e->getMessage()]);
        }
        exit;
    }
}

$db = $_SESSION['install']['db'] ?? [];
$smtp = $_SESSION['install']['smtp'] ?? [];
$admin = $_SESSION['install']['admin'] ?? [];
$site = $_SESSION['install']['site'] ?? [];

// Step validation: ensure required session data from previous steps exists
if ($step >= 2 && empty($_SESSION['install']['db'])) {
    header('Location: ?step=1');
    exit;
}
if ($step >= 3 && empty($_SESSION['install']['smtp'])) {
    header('Location: ?step=2');
    exit;
}
if ($step >= 4 && empty($_SESSION['install']['admin'])) {
    header('Location: ?step=3');
    exit;
}
if ($step >= 5 && empty($_SESSION['install']['site'])) {
    header('Location: ?step=4');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SoftandPix — Install Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; }
        .install-card { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); max-width: 700px; margin: auto; }
        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .step-dot { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem; }
        .step-dot.active { background: #667eea; color: white; }
        .step-dot.done { background: #28a745; color: white; }
        .step-dot.pending { background: #e9ecef; color: #999; }
        .logo-text { font-size: 2rem; font-weight: 800; color: #667eea; text-align: center; margin-bottom: 10px; }
        .btn-install { background: linear-gradient(135deg, #667eea, #764ba2); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-install:hover { color: white; opacity: 0.9; }
    </style>
</head>
<body>
<div class="container">
    <div class="install-card">
        <div class="logo-text"><i class="fas fa-cogs me-2"></i>SoftandPix</div>
        <p class="text-center text-muted mb-4">Installation Wizard</p>
        
        <div class="step-indicator">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <div class="step-dot <?= $i < $step ? 'done' : ($i === $step ? 'active' : 'pending') ?>">
                <?= $i < $step ? '<i class="fas fa-check"></i>' : $i ?>
            </div>
            <?php endfor; ?>
        </div>

        <?php if ($step === 1): ?>
        <!-- Step 1: Database Configuration -->
        <h4 class="mb-3"><i class="fas fa-database me-2 text-primary"></i>Step 1: Database Configuration</h4>
        <form method="POST" id="dbForm">
            <input type="hidden" name="action" value="save_step1">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3"><label class="form-label fw-bold">Database Host</label>
                <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($db['host'] ?? 'localhost') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Database Name</label>
                <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($db['name'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Database Username</label>
                <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($db['user'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Database Password</label>
                <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($db['pass'] ?? '') ?>"></div>
            <div id="testResult"></div>
            <div class="d-flex justify-content-between">
                <button type="button" id="testDbBtn" class="btn btn-outline-primary" onclick="testDB()"><i class="fas fa-plug me-1"></i>Test Connection</button>
                <button type="submit" class="btn btn-install">Next <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </form>

        <?php elseif ($step === 2): ?>
        <!-- Step 2: Website SMTP Configuration -->
        <h4 class="mb-3"><i class="fas fa-envelope me-2 text-primary"></i>Step 2: Website SMTP Configuration</h4>
        <p class="text-muted small mb-3"><i class="fas fa-info-circle me-1"></i>Configure the <strong>support@softandpix.com</strong> email (cPanel hosting) used for all automatic system emails. The Admin Email (info@ Zoho) can be set up later in <em>Admin Panel → Settings</em>.</p>
        <form method="POST" id="smtpForm">
            <input type="hidden" name="action" value="save_step2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <h6 class="text-success"><i class="fas fa-headset me-1"></i>Website SMTP (support@) — System Emails</h6>
            <div class="row g-2 mb-3">
                <div class="col-md-8"><input type="text" name="support_host" class="form-control" placeholder="SMTP Host" value="<?= htmlspecialchars($smtp['support_host'] ?? 'mail.softandpix.com') ?>" required></div>
                <div class="col-md-4"><input type="number" name="support_port" class="form-control" placeholder="Port" value="<?= htmlspecialchars($smtp['support_port'] ?? '465') ?>" required></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6"><input type="email" name="support_email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($smtp['support_email'] ?? 'support@softandpix.com') ?>" required></div>
                <div class="col-md-6"><input type="password" name="support_pass" class="form-control" placeholder="Password" value="<?= htmlspecialchars($smtp['support_pass'] ?? '') ?>" required></div>
            </div>
            <div class="mb-4"><select name="support_encryption" class="form-select"><option value="ssl" <?= ($smtp['support_encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="tls" <?= ($smtp['support_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option></select></div>
            
            <div id="smtpTestResult"></div>
            <div class="d-flex justify-content-between">
                <a href="?step=1" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                <button type="button" id="testSmtpBtn" class="btn btn-outline-success" onclick="testSMTP()"><i class="fas fa-paper-plane me-1"></i>Test Email</button>
                <button type="submit" class="btn btn-install">Next <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </form>

        <?php elseif ($step === 3): ?>
        <!-- Step 3: Admin Account -->
        <h4 class="mb-3"><i class="fas fa-user-shield me-2 text-primary"></i>Step 3: Admin Account</h4>
        <form method="POST" id="adminForm">
            <input type="hidden" name="action" value="save_step3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3"><label class="form-label fw-bold">Full Name</label>
                <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($admin['name'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Email Address</label>
                <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Password</label>
                <input type="password" name="admin_password" id="admin_password" class="form-control" required minlength="6"></div>
            <div class="mb-3"><label class="form-label fw-bold">Confirm Password</label>
                <input type="password" id="admin_confirm" class="form-control" required></div>
            <div class="d-flex justify-content-between">
                <a href="?step=2" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                <button type="submit" class="btn btn-install">Next <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </form>

        <?php elseif ($step === 4): ?>
        <!-- Step 4: Site Settings -->
        <h4 class="mb-3"><i class="fas fa-globe me-2 text-primary"></i>Step 4: Site Settings</h4>
        <form method="POST">
            <input type="hidden" name="action" value="save_step4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="mb-3"><label class="form-label fw-bold">Site Name</label>
                <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($site['name'] ?? 'SoftandPix') ?>" required></div>
            <div class="mb-3"><label class="form-label fw-bold">Site URL</label>
                <input type="url" name="site_url" class="form-control" value="<?= htmlspecialchars($site['url'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) ?>" required></div>
            <div class="d-flex justify-content-between">
                <a href="?step=3" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
                <button type="submit" class="btn btn-install">Next <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </form>

        <?php elseif ($step === 5): ?>
        <!-- Step 5: Run Installation -->
        <h4 class="mb-3"><i class="fas fa-rocket me-2 text-primary"></i>Step 5: Install</h4>
        <div class="text-center mb-4">
            <p class="text-muted">Click the button below to complete the installation.</p>
            <div class="mb-3">
                <small class="d-block"><strong>Database:</strong> <?= htmlspecialchars($db['name'] ?? '—') ?> @ <?= htmlspecialchars($db['host'] ?? '—') ?></small>
                <small class="d-block"><strong>Admin:</strong> <?= htmlspecialchars($admin['email'] ?? '—') ?></small>
                <small class="d-block"><strong>Site:</strong> <?= htmlspecialchars($site['name'] ?? 'SoftandPix') ?></small>
            </div>
        </div>
        <div id="installResult" class="mb-3"></div>
        <div class="d-flex justify-content-between">
            <a href="?step=4" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <button type="button" class="btn btn-install btn-lg" id="installBtn" onclick="runInstall()"><i class="fas fa-rocket me-1"></i>Install Now</button>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
function testDB(){
    var btn=$('#testDbBtn');
    btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin me-1"></i>Testing...');
    var f=$('#dbForm');
    var url=window.location.href.split('?')[0];
    $.post(url,{action:'test_db',csrf_token:csrfToken,db_host:f.find('[name=db_host]').val(),db_name:f.find('[name=db_name]').val(),db_user:f.find('[name=db_user]').val(),db_pass:f.find('[name=db_pass]').val()},function(r){
        var res=typeof r==='string'?JSON.parse(r):r;
        $('#testResult').html('<div class="alert alert-'+(res.success?'success':'danger')+' mt-2">'+res.message+'</div>');
        btn.prop('disabled',false).html('<i class="fas fa-plug me-1"></i>Test Connection');
    }).fail(function(){
        $('#testResult').html('<div class="alert alert-danger mt-2">Network error. Please try again.</div>');
        btn.prop('disabled',false).html('<i class="fas fa-plug me-1"></i>Test Connection');
    });
}
function runInstall(){
    $('#installBtn').prop('disabled',true).html('<i class="fas fa-spinner fa-spin me-1"></i>Installing...');
    var url=window.location.href.split('?')[0];
    $.post(url,{action:'run_install',csrf_token:csrfToken},function(r){
        var res=typeof r==='string'?JSON.parse(r):r;
        if(res.success){
            $('#installResult').html('<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>'+res.message+'</div>');
            setTimeout(function(){window.location.href='../login.php';},2000);
        } else {
            $('#installResult').html('<div class="alert alert-danger">'+res.message+'</div>');
            $('#installBtn').prop('disabled',false).html('<i class="fas fa-rocket me-1"></i>Install Now');
        }
    }).fail(function(){
        $('#installResult').html('<div class="alert alert-danger">Network error. Please try again.</div>');
        $('#installBtn').prop('disabled',false).html('<i class="fas fa-rocket me-1"></i>Install Now');
    });
}
<?php if($step===2):?>
function testSMTP(){
    var btn=$('#testSmtpBtn');
    btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin me-1"></i>Testing...');
    var f=$('#smtpForm');
    var url=window.location.href.split('?')[0];
    $.post(url,{
        action:'test_smtp',
        csrf_token:csrfToken,
        support_host:f.find('[name=support_host]').val(),
        support_port:f.find('[name=support_port]').val(),
        support_email:f.find('[name=support_email]').val(),
        support_pass:f.find('[name=support_pass]').val(),
        support_encryption:f.find('[name=support_encryption]').val()
    },function(r){
        var res=typeof r==='string'?JSON.parse(r):r;
        $('#smtpTestResult').html('<div class="alert alert-'+(res.success?'success':'danger')+' mt-2">'+res.message+'</div>');
        btn.prop('disabled',false).html('<i class="fas fa-paper-plane me-1"></i>Test Email');
    }).fail(function(){
        $('#smtpTestResult').html('<div class="alert alert-danger mt-2">Network error. Please try again.</div>');
        btn.prop('disabled',false).html('<i class="fas fa-paper-plane me-1"></i>Test Email');
    });
}
<?php endif;?>
<?php if($step===3):?>
document.getElementById('adminForm').onsubmit=function(e){
    if(document.getElementById('admin_password').value!==document.getElementById('admin_confirm').value){
        e.preventDefault();alert('Passwords do not match!');return false;
    }
};
<?php endif;?>
</script>
</body>
</html>
