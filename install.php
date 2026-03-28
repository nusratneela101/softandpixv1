<?php
/**
 * Softandpix — Auto Installation Wizard
 * Visit /install.php to set up the application.
 */

// ── Lock file protection ─────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/config/installed.lock')) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Already Installed — Softandpix</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>body{background:#1a202c;min-height:100vh;display:flex;align-items:center;justify-content:center;}</style>
</head>
<body>
<div class="card shadow-lg p-5 text-center" style="max-width:480px;">
    <h3 class="mb-3">🔒 Already Installed</h3>
    <p class="text-muted">Softandpix is already installed.<br>Delete <code>config/installed.lock</code> to reinstall.</p>
    <a href="/" class="btn btn-primary mt-3">Go to Website</a>
    <a href="/admin/login.php" class="btn btn-outline-secondary mt-2">Admin Panel</a>
</div>
</body>
</html>
<?php
    exit;
}

// ── AJAX back-end handlers ────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    // ── CSRF helpers (stored in session) ──────────────────────────────────────
    session_start();

    if ($action === 'get_csrf') {
        if (empty($_SESSION['install_csrf'])) {
            $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
        }
        echo json_encode(['token' => $_SESSION['install_csrf']]);
        exit;
    }

    // All other actions require a valid CSRF token
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $data['csrf_token'] ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['install_csrf']) || !hash_equals($_SESSION['install_csrf'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    // ── Action: test_connection ───────────────────────────────────────────────
    if ($action === 'test_connection') {
        $host = trim($data['db_host'] ?? 'localhost');
        $port = (int)($data['db_port'] ?? 3306);
        $user = trim($data['db_user'] ?? '');
        $pass = $data['db_pass'] ?? '';
        if (empty($user)) {
            echo json_encode(['success' => false, 'message' => 'Database username is required.']);
            exit;
        }
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo json_encode(['success' => true, 'message' => 'Connection successful!']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Connection failed. Please check your credentials.']);
        }
        exit;
    }

    // ── Action: run_step ─────────────────────────────────────────────────────
    if ($action === 'run_step') {
        $step = (int)($data['step'] ?? 0);

        // Retrieve stored install data from session
        if (!empty($data['db_host'])) {
            $_SESSION['install_db'] = [
                'host' => trim($data['db_host'] ?? 'localhost'),
                'port' => (int)($data['db_port'] ?? 3306),
                'name' => trim($data['db_name'] ?? 'softandpix'),
                'user' => trim($data['db_user'] ?? ''),
                'pass' => $data['db_pass'] ?? '',
                'prefix' => trim($data['db_prefix'] ?? ''),
            ];
        }
        if (!empty($data['site_title'])) {
            $_SESSION['install_site'] = [
                'title'          => trim($data['site_title'] ?? 'Softandpix'),
                'url'            => trim($data['site_url'] ?? ''),
                'admin_email'    => trim($data['admin_email'] ?? ''),
                'admin_username' => trim($data['admin_username'] ?? 'admin'),
                'admin_password' => $data['admin_password'] ?? '',
            ];
        }

        $db   = $_SESSION['install_db'] ?? [];
        $site = $_SESSION['install_site'] ?? [];

        // Helper: open PDO connection
        $connect = function($withDb = true) use ($db) {
            $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
            if ($withDb) {
                $dsn .= ";dbname={$db['name']}";
            }
            return new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        };

        try {
            switch ($step) {
                // ── Step 1: Create database ───────────────────────────────────
                case 1:
                    $pdo = $connect(false);
                    $dbName = $db['name'];
                    // Validate DB name — only alphanumeric, underscores and hyphens
                    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) {
                        throw new RuntimeException('Database name contains invalid characters.');
                    }
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    echo json_encode(['success' => true, 'message' => "Database '{$dbName}' created (or already exists)."]);
                    break;

                // ── Step 2: Create tables ─────────────────────────────────────
                case 2:
                    $pdo    = $connect(true);
                    $sqlFile = __DIR__ . '/database/softandpix.sql';
                    if (!file_exists($sqlFile)) {
                        throw new RuntimeException('database/softandpix.sql not found.');
                    }
                    $sql = file_get_contents($sqlFile);

                    // Strip CREATE DATABASE / USE lines — we already created the DB
                    $sql = preg_replace('/CREATE\s+DATABASE[^;]+;/si', '', $sql);
                    $sql = preg_replace('/USE\s+[^;]+;/si', '', $sql);

                    // Split on semicolons
                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        fn($s) => $s !== ''
                    );

                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $tableCount = 0;
                    $errors = [];
                    foreach ($statements as $stmt) {
                        // Skip pure comment blocks
                        $clean = preg_replace('/--[^\n]*\n?/', '', $stmt);
                        $clean = trim($clean);
                        if ($clean === '') continue;
                        // Only execute CREATE TABLE statements in this step
                        if (stripos($clean, 'CREATE TABLE') === false) continue;
                        try {
                            $pdo->exec($stmt);
                            $tableCount++;
                        } catch (PDOException $e) {
                            $errors[] = $e->getMessage();
                        }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    $msg = "Created {$tableCount} table(s).";
                    if (!empty($errors)) {
                        $msg .= ' Some warnings: ' . implode('; ', array_slice($errors, 0, 3));
                    }
                    echo json_encode(['success' => true, 'message' => $msg]);
                    break;

                // ── Step 3: Insert default data ───────────────────────────────
                case 3:
                    $pdo    = $connect(true);
                    $sqlFile = __DIR__ . '/database/softandpix.sql';
                    $sql    = file_get_contents($sqlFile);
                    $sql    = preg_replace('/CREATE\s+DATABASE[^;]+;/si', '', $sql);
                    $sql    = preg_replace('/USE\s+[^;]+;/si', '', $sql);

                    $statements = array_filter(
                        array_map('trim', explode(';', $sql)),
                        fn($s) => $s !== ''
                    );

                    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
                    $insertCount = 0;
                    foreach ($statements as $stmt) {
                        $clean = preg_replace('/--[^\n]*\n?/', '', $stmt);
                        $clean = trim($clean);
                        if ($clean === '') continue;
                        // Only INSERT / SET statements (skip CREATE TABLE)
                        if (stripos($clean, 'CREATE TABLE') !== false) continue;
                        // Skip admin_users inserts — we'll add the admin manually in step 4
                        if (stripos($clean, 'INSERT') !== false &&
                            stripos($clean, 'admin_users') !== false) continue;
                        // Skip users inserts — handled in step 4
                        if (stripos($clean, 'INSERT') !== false &&
                            stripos($clean, "INTO `users`") !== false) continue;
                        try {
                            $pdo->exec($stmt);
                            if (stripos($clean, 'INSERT') !== false) {
                                $insertCount++;
                            }
                        } catch (PDOException $e) {
                            // Silently skip duplicate/non-critical errors
                        }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
                    echo json_encode(['success' => true, 'message' => "Inserted default data ({$insertCount} statements)."]);
                    break;

                // ── Step 4: Create admin account ──────────────────────────────
                case 4:
                    if (empty($site)) {
                        throw new RuntimeException('Site configuration is missing. Please go back to Step 3.');
                    }
                    $pdo      = $connect(true);
                    $username = $site['admin_username'];
                    $email    = $site['admin_email'];
                    $hash     = password_hash($site['admin_password'], PASSWORD_BCRYPT);

                    // admin_users table
                    $stmt = $pdo->prepare(
                        "INSERT INTO `admin_users` (`username`, `password`, `email`)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `email` = VALUES(`email`)"
                    );
                    $stmt->execute([$username, $hash, $email]);

                    // users table (portal login)
                    $stmt2 = $pdo->prepare(
                        "INSERT INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `email_verified`)
                         VALUES (?, ?, ?, 'admin', 1, 1)
                         ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = 'admin'"
                    );
                    $stmt2->execute([$username, $email, $hash]);

                    echo json_encode(['success' => true, 'message' => "Admin account '{$username}' created."]);
                    break;

                // ── Step 5: Update site settings ─────────────────────────────
                case 5:
                    if (empty($site)) {
                        throw new RuntimeException('Site configuration is missing.');
                    }
                    $pdo = $connect(true);
                    $updates = [
                        'site_name'  => $site['title'],
                        'site_url'   => $site['url'],
                        'site_email' => $site['admin_email'],
                    ];
                    $stmt = $pdo->prepare(
                        "INSERT INTO `site_settings` (`setting_key`, `setting_value`)
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)"
                    );
                    foreach ($updates as $k => $v) {
                        $stmt->execute([$k, $v]);
                    }
                    echo json_encode(['success' => true, 'message' => 'Site settings updated.']);
                    break;

                // ── Step 6: Generate config file ─────────────────────────────
                case 6:
                    if (empty($db)) {
                        throw new RuntimeException('Database configuration is missing.');
                    }
                    // Use var_export() so any special characters are safely escaped
                    // as valid PHP string literals (no injection possible).
                    $hostExport   = var_export($db['host'], true);
                    $dbNameExport = var_export($db['name'], true);
                    $dbUserExport = var_export($db['user'], true);
                    $dbPassExport = var_export($db['pass'], true);
                    $port         = (int)$db['port'];
                    $portStr      = ($port !== 3306) ? ";port={$port}" : '';

                    $configContent = "<?php\n"
                        . "define('DB_HOST', {$hostExport});\n"
                        . "define('DB_PORT', {$port});\n"
                        . "define('DB_NAME', {$dbNameExport});\n"
                        . "define('DB_USER', {$dbUserExport});\n"
                        . "define('DB_PASS', {$dbPassExport});\n\n"
                        . "try {\n"
                        . "    \$pdo = new PDO(\n"
                        . "        \"mysql:host=\".DB_HOST.\"{$portStr};dbname=\".DB_NAME.\";charset=utf8mb4\",\n"
                        . "        DB_USER,\n"
                        . "        DB_PASS,\n"
                        . "        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
                        . "         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]\n"
                        . "    );\n"
                        . "} catch(PDOException \$e) {\n"
                        . "    die(\"Connection failed: \" . \$e->getMessage());\n"
                        . "}\n";
                    $configPath = __DIR__ . '/config/db.php';
                    if (!is_writable(dirname($configPath))) {
                        throw new RuntimeException('config/ directory is not writable.');
                    }
                    file_put_contents($configPath, $configContent);
                    echo json_encode(['success' => true, 'message' => 'config/db.php generated.']);
                    break;

                // ── Step 7: Set file permissions ─────────────────────────────
                case 7:
                    $uploadsDir = __DIR__ . '/uploads';
                    if (is_dir($uploadsDir)) {
                        @chmod($uploadsDir, 0755);
                    }
                    echo json_encode(['success' => true, 'message' => 'File permissions set.']);
                    break;

                // ── Step 8: Create lock file ──────────────────────────────────
                case 8:
                    $lockFile = __DIR__ . '/config/installed.lock';
                    file_put_contents($lockFile, date('Y-m-d H:i:s'));
                    // Clear install session data
                    unset($_SESSION['install_db'], $_SESSION['install_site'], $_SESSION['install_csrf']);
                    echo json_encode(['success' => true, 'message' => 'Lock file created. Installation complete!']);
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Unknown step.']);
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ── Front-end (wizard UI) ─────────────────────────────────────────────────────
session_start();
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$detectedUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install — Softandpix</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root {--accent:#667eea;}
  body{background:linear-gradient(135deg,#1a202c 0%,#2d3748 100%);min-height:100vh;font-family:'Segoe UI',sans-serif;}
  .wizard-card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);max-width:680px;margin:40px auto;padding:0;overflow:hidden;}
  .wizard-header{background:linear-gradient(135deg,var(--accent),#764ba2);padding:32px 36px 24px;color:#fff;}
  .wizard-header h1{font-size:1.6rem;font-weight:700;margin:0;}
  .wizard-header p{margin:4px 0 0;opacity:.85;font-size:.95rem;}
  .step-indicators{display:flex;gap:8px;margin-top:20px;}
  .step-dot{width:32px;height:32px;border-radius:50%;border:2px solid rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.7);transition:all .3s;}
  .step-dot.active{background:#fff;color:var(--accent);border-color:#fff;}
  .step-dot.done{background:rgba(255,255,255,.3);border-color:#fff;color:#fff;}
  .wizard-body{padding:36px;}
  .req-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f0f0f0;}
  .req-item:last-child{border-bottom:none;}
  .req-icon{font-size:1.2rem;}
  .pass{color:#28a745;} .fail{color:#dc3545;}
  .progress-step{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f5f5f5;}
  .progress-step:last-child{border-bottom:none;}
  .progress-step .icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
  .icon-pending{background:#e9ecef;color:#6c757d;}
  .icon-running{background:#fff3cd;color:#856404;}
  .icon-done{background:#d1e7dd;color:#0f5132;}
  .icon-error{background:#f8d7da;color:#842029;}
  .strength-bar{height:6px;border-radius:3px;transition:all .3s;}
  .cred-box{background:#f8f9fa;border:1px solid #e9ecef;border-radius:10px;padding:20px;}
  .cred-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #e9ecef;}
  .cred-row:last-child{border-bottom:none;}
  .btn-accent{background:var(--accent);border-color:var(--accent);color:#fff;}
  .btn-accent:hover{background:#5a6fd6;border-color:#5a6fd6;color:#fff;}
  #step-container .step-panel{display:none;} 
  #step-container .step-panel.active{display:block;}
  .spinner-sm{width:16px;height:16px;}
  @media(max-width:576px){.wizard-body{padding:20px;} .wizard-header{padding:20px;}}
</style>
</head>
<body>
<div class="wizard-card">
  <!-- Header -->
  <div class="wizard-header">
    <h1><i class="bi bi-box-seam me-2"></i>Softandpix Installation Wizard</h1>
    <p>Follow the steps below to set up your application.</p>
    <div class="step-indicators">
      <?php for ($i = 1; $i <= 5; $i++): ?>
      <div class="step-dot" id="dot-<?= $i ?>"><?= $i ?></div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Body -->
  <div class="wizard-body" id="step-container">

    <!-- ── STEP 1: Requirements ──────────────────────────────────────────── -->
    <div class="step-panel active" id="panel-1">
      <h4 class="mb-3"><i class="bi bi-clipboard-check text-primary me-2"></i>Step 1 of 5 — Requirements Check</h4>
      <div id="req-list">
        <?php
        $checks = [];

        // PHP version
        $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
        $checks[] = ['label' => 'PHP Version &ge; 7.4 (current: ' . PHP_VERSION . ')', 'ok' => $phpOk];

        // Extensions
        foreach (['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'openssl', 'fileinfo'] as $ext) {
            $checks[] = ['label' => "PHP extension: <code>{$ext}</code>", 'ok' => extension_loaded($ext)];
        }

        // Writable dirs
        $configDir  = __DIR__ . '/config';
        $uploadsDir = __DIR__ . '/uploads';
        $checks[] = ['label' => 'Directory <code>config/</code> is writable', 'ok' => is_writable($configDir)];
        $checks[] = ['label' => 'Directory <code>uploads/</code> is writable', 'ok' => is_dir($uploadsDir) && is_writable($uploadsDir)];

        // mod_rewrite (best-effort detection)
        $modRewrite = false;
        if (function_exists('apache_get_modules')) {
            $modRewrite = in_array('mod_rewrite', apache_get_modules());
        } elseif (isset($_SERVER['HTTP_MOD_REWRITE'])) {
            $modRewrite = strtolower($_SERVER['HTTP_MOD_REWRITE']) === 'on';
        } else {
            $modRewrite = true; // assume OK on nginx/other
        }
        $checks[] = ['label' => 'Apache <code>mod_rewrite</code> enabled (or Nginx equivalent)', 'ok' => $modRewrite];

        // SQL file present
        $sqlOk = file_exists(__DIR__ . '/database/softandpix.sql');
        $checks[] = ['label' => 'database/softandpix.sql found', 'ok' => $sqlOk];

        $allOk = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);

        foreach ($checks as $c):
        ?>
        <div class="req-item">
          <span class="req-icon <?= $c['ok'] ? 'pass' : 'fail' ?>">
            <i class="bi <?= $c['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
          </span>
          <span><?= $c['label'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$allOk): ?>
      <div class="alert alert-danger mt-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Please fix the requirements above before continuing.
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-end mt-4">
        <button class="btn btn-accent" id="btn-step1-next" <?= $allOk ? '' : 'disabled' ?>>
          Next <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </div>
    </div>

    <!-- ── STEP 2: Database Configuration ───────────────────────────────── -->
    <div class="step-panel" id="panel-2">
      <h4 class="mb-3"><i class="bi bi-database text-primary me-2"></i>Step 2 of 5 — Database Configuration</h4>
      <form id="form-db" novalidate>
        <div class="row g-3">
          <div class="col-sm-8">
            <label class="form-label fw-semibold">Database Host</label>
            <input type="text" class="form-control" id="db_host" value="localhost" required>
          </div>
          <div class="col-sm-4">
            <label class="form-label fw-semibold">Port</label>
            <input type="number" class="form-control" id="db_port" value="3306" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Database Name</label>
            <input type="text" class="form-control" id="db_name" value="softandpix" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Table Prefix <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" class="form-control" id="db_prefix" placeholder="e.g. spx_">
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Database Username</label>
            <input type="text" class="form-control" id="db_user" placeholder="e.g. root" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Database Password</label>
            <input type="password" class="form-control" id="db_pass" placeholder="Leave empty if none">
          </div>
        </div>
        <div id="conn-result" class="mt-3" style="display:none"></div>
        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" id="btn-step2-back">
            <i class="bi bi-arrow-left me-1"></i>Back
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="btn-test-conn">
              <i class="bi bi-plug me-1"></i>Test Connection
            </button>
            <button type="button" class="btn btn-accent" id="btn-step2-next" disabled>
              Next <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- ── STEP 3: Site Configuration ───────────────────────────────────── -->
    <div class="step-panel" id="panel-3">
      <h4 class="mb-3"><i class="bi bi-gear text-primary me-2"></i>Step 3 of 5 — Site Configuration</h4>
      <form id="form-site" novalidate>
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Site Title</label>
            <input type="text" class="form-control" id="site_title" value="Softandpix" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Site URL</label>
            <input type="url" class="form-control" id="site_url" value="<?= htmlspecialchars($detectedUrl) ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Admin Email</label>
            <input type="email" class="form-control" id="admin_email" placeholder="admin@example.com" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Admin Username</label>
            <input type="text" class="form-control" id="admin_username" value="admin" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Admin Password</label>
            <input type="password" class="form-control" id="admin_password" placeholder="Min 8 characters" required>
            <div class="mt-1">
              <div class="strength-bar w-100 bg-light" style="border:1px solid #dee2e6;">
                <div id="strength-fill" class="strength-bar" style="width:0%;background:#dc3545;"></div>
              </div>
              <small id="strength-label" class="text-muted">Password strength</small>
            </div>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Confirm Password</label>
            <input type="password" class="form-control" id="admin_password_confirm" required>
            <div id="pass-match" class="form-text"></div>
          </div>
        </div>
        <div class="d-flex justify-content-between mt-4">
          <button type="button" class="btn btn-outline-secondary" id="btn-step3-back">
            <i class="bi bi-arrow-left me-1"></i>Back
          </button>
          <button type="button" class="btn btn-accent" id="btn-step3-next">
            Install Now <i class="bi bi-rocket-takeoff ms-1"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- ── STEP 4: Installation Progress ────────────────────────────────── -->
    <div class="step-panel" id="panel-4">
      <h4 class="mb-3"><i class="bi bi-gear-fill text-primary me-2 spin"></i>Step 4 of 5 — Installing...</h4>
      <div class="progress mb-4" style="height:18px;border-radius:9px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated btn-accent"
             id="install-progress" role="progressbar" style="width:0%">0%</div>
      </div>
      <div id="install-steps">
        <?php
        $installSteps = [
            1 => 'Creating database...',
            2 => 'Creating tables...',
            3 => 'Inserting default data...',
            4 => 'Creating admin account...',
            5 => 'Configuring site settings...',
            6 => 'Generating config file...',
            7 => 'Setting file permissions...',
            8 => 'Creating lock file...',
        ];
        foreach ($installSteps as $n => $label):
        ?>
        <div class="progress-step" id="pstep-<?= $n ?>">
          <div class="icon icon-pending" id="picon-<?= $n ?>">
            <i class="bi bi-circle" id="picon-i-<?= $n ?>"></i>
          </div>
          <span id="ptext-<?= $n ?>"><?= htmlspecialchars($label) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── STEP 5: Complete ──────────────────────────────────────────────── -->
    <div class="step-panel" id="panel-5">
      <div class="text-center mb-4">
        <div style="font-size:4rem;">🎉</div>
        <h3 class="fw-bold text-success mt-2">Installation Complete!</h3>
        <p class="text-muted">Softandpix is ready. Here are your credentials:</p>
      </div>
      <div class="cred-box mb-4">
        <div class="cred-row">
          <span class="text-muted">Admin Panel URL</span>
          <a id="cred-admin-url" href="/admin/login.php" target="_blank">/admin/login.php</a>
        </div>
        <div class="cred-row">
          <span class="text-muted">Admin Username</span>
          <strong id="cred-username">admin</strong>
        </div>
        <div class="cred-row">
          <span class="text-muted">Admin Password</span>
          <div class="d-flex align-items-center gap-2">
            <span id="cred-password" style="font-family:monospace;letter-spacing:2px">••••••••</span>
            <button class="btn btn-sm btn-outline-secondary" id="btn-toggle-pass" title="Show/hide password">
              <i class="bi bi-eye"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="btn-copy-pass" title="Copy password">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>
        <div class="cred-row">
          <span class="text-muted">Site URL</span>
          <a id="cred-site-url" href="/" target="_blank">/</a>
        </div>
      </div>
      <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-shield-exclamation fs-5"></i>
        <div><strong>Security Warning:</strong> Delete <code>install.php</code> from your server to prevent re-installation.</div>
      </div>
      <div class="d-flex gap-3 justify-content-center mt-4">
        <a href="/admin/login.php" class="btn btn-accent btn-lg">
          <i class="bi bi-speedometer2 me-2"></i>Go to Admin Panel
        </a>
        <a href="/" class="btn btn-outline-secondary btn-lg" id="btn-visit-site">
          <i class="bi bi-globe me-2"></i>Visit Website
        </a>
      </div>
    </div>

  </div><!-- .wizard-body -->
</div><!-- .wizard-card -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  let currentStep = 1;
  let csrfToken   = '';
  let dbConfig    = {};
  let siteConfig  = {};
  let adminPass   = '';
  let passVisible = false;

  // ── Helpers ────────────────────────────────────────────────────────────────
  async function fetchCsrf() {
    const r = await fetch('?action=get_csrf');
    const d = await r.json();
    csrfToken = d.token;
  }

  async function apiPost(action, payload) {
    const r = await fetch('?action=' + action, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({...payload, csrf_token: csrfToken})
    });
    return r.json();
  }

  function showStep(n) {
    document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + n).classList.add('active');
    document.querySelectorAll('.step-dot').forEach((d, i) => {
      d.classList.remove('active', 'done');
      if (i + 1 < n)       d.classList.add('done');
      else if (i + 1 === n) d.classList.add('active');
    });
    currentStep = n;
  }

  function alert2(el, type, msg) {
    el.style.display = '';
    el.className = 'mt-3 alert alert-' + type;
    el.innerHTML = msg;
  }

  // ── Password strength ──────────────────────────────────────────────────────
  document.getElementById('admin_password').addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const pcts  = [0, 20, 40, 65, 85, 100];
    const cols  = ['#dc3545','#dc3545','#fd7e14','#ffc107','#28a745','#198754'];
    const lbls  = ['','Weak','Fair','Good','Strong','Very Strong'];
    document.getElementById('strength-fill').style.width      = pcts[score] + '%';
    document.getElementById('strength-fill').style.background = cols[score];
    document.getElementById('strength-label').textContent     = lbls[score];
    checkPassMatch();
  });

  function checkPassMatch() {
    const p1 = document.getElementById('admin_password').value;
    const p2 = document.getElementById('admin_password_confirm').value;
    const el = document.getElementById('pass-match');
    if (!p2) { el.textContent = ''; return; }
    if (p1 === p2) {
      el.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Passwords match</span>';
    } else {
      el.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle"></i> Passwords do not match</span>';
    }
  }
  document.getElementById('admin_password_confirm').addEventListener('input', checkPassMatch);

  // ── Step 1 → 2 ─────────────────────────────────────────────────────────────
  document.getElementById('btn-step1-next').addEventListener('click', function () {
    showStep(2);
  });

  // ── Step 2: Test connection ────────────────────────────────────────────────
  document.getElementById('btn-test-conn').addEventListener('click', async function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-sm me-1"></span>Testing...';
    const result = document.getElementById('conn-result');

    dbConfig = {
      db_host:   document.getElementById('db_host').value.trim(),
      db_port:   document.getElementById('db_port').value,
      db_name:   document.getElementById('db_name').value.trim(),
      db_user:   document.getElementById('db_user').value.trim(),
      db_pass:   document.getElementById('db_pass').value,
      db_prefix: document.getElementById('db_prefix').value.trim(),
    };

    try {
      const d = await apiPost('test_connection', dbConfig);
      if (d.success) {
        alert2(result, 'success', '<i class="bi bi-check-circle-fill me-1"></i>' + d.message);
        document.getElementById('btn-step2-next').disabled = false;
      } else {
        alert2(result, 'danger', '<i class="bi bi-x-circle-fill me-1"></i>' + d.message);
        document.getElementById('btn-step2-next').disabled = true;
      }
    } catch(e) {
      alert2(result, 'danger', 'Request failed. Please try again.');
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plug me-1"></i>Test Connection';
  });

  document.getElementById('btn-step2-back').addEventListener('click', () => showStep(1));
  document.getElementById('btn-step2-next').addEventListener('click', () => showStep(3));
  document.getElementById('btn-step3-back').addEventListener('click', () => showStep(2));

  // ── Step 3 → 4 (Start Install) ─────────────────────────────────────────────
  document.getElementById('btn-step3-next').addEventListener('click', function () {
    const email = document.getElementById('admin_email').value.trim();
    const uname = document.getElementById('admin_username').value.trim();
    const pass  = document.getElementById('admin_password').value;
    const conf  = document.getElementById('admin_password_confirm').value;

    if (!email || !uname || !pass) {
      alert('Please fill in all required fields.');
      return;
    }
    if (pass.length < 8) {
      alert('Password must be at least 8 characters.');
      return;
    }
    if (pass !== conf) {
      alert('Passwords do not match.');
      return;
    }

    siteConfig = {
      site_title:       document.getElementById('site_title').value.trim(),
      site_url:         document.getElementById('site_url').value.trim(),
      admin_email:      email,
      admin_username:   uname,
      admin_password:   pass,
    };
    adminPass = pass;

    showStep(4);
    runInstallation();
  });

  // ── Installation runner ─────────────────────────────────────────────────────
  const installLabels = {
    1: 'Creating database...',
    2: 'Creating tables...',
    3: 'Inserting default data...',
    4: 'Creating admin account...',
    5: 'Configuring site settings...',
    6: 'Generating config file...',
    7: 'Setting file permissions...',
    8: 'Creating lock file...',
  };
  const totalSteps = Object.keys(installLabels).length;

  function setStepStatus(n, status, msg) {
    const iconEl  = document.getElementById('picon-' + n);
    const iconI   = document.getElementById('picon-i-' + n);
    const textEl  = document.getElementById('ptext-' + n);
    iconEl.className = 'icon icon-' + status;
    if (status === 'running') {
      iconI.className = 'bi bi-arrow-repeat';
    } else if (status === 'done') {
      iconI.className = 'bi bi-check-lg';
    } else if (status === 'error') {
      iconI.className = 'bi bi-x-lg';
    }
    if (msg) textEl.textContent = msg;
    const pct = Math.round((n / totalSteps) * 100);
    document.getElementById('install-progress').style.width = pct + '%';
    document.getElementById('install-progress').textContent = pct + '%';
  }

  async function runInstallation() {
    const payload = {...dbConfig, ...siteConfig};

    for (let s = 1; s <= totalSteps; s++) {
      setStepStatus(s, 'running', installLabels[s]);
      try {
        const d = await apiPost('run_step', {step: s, ...payload});
        if (d.success) {
          setStepStatus(s, 'done', '✅ ' + d.message);
        } else {
          setStepStatus(s, 'error', '❌ ' + d.message);
          // Stop on critical errors (DB / config), continue on permission warnings
          if (s <= 6) return;
        }
      } catch(e) {
        setStepStatus(s, 'error', '❌ Network error.');
        if (s <= 6) return;
      }
    }

    // All done — show step 5
    setTimeout(() => {
      populateStep5();
      showStep(5);
    }, 600);
  }

  function populateStep5() {
    const siteUrl = siteConfig.site_url || window.location.origin;
    document.getElementById('cred-admin-url').href = siteUrl + '/admin/login.php';
    document.getElementById('cred-admin-url').textContent = siteUrl + '/admin/login.php';
    document.getElementById('cred-username').textContent  = siteConfig.admin_username;
    document.getElementById('cred-password').dataset.pass = adminPass;
    document.getElementById('cred-site-url').href         = siteUrl;
    document.getElementById('cred-site-url').textContent  = siteUrl;
    document.getElementById('btn-visit-site').href        = siteUrl;
  }

  // ── Toggle / copy password ─────────────────────────────────────────────────
  document.getElementById('btn-toggle-pass').addEventListener('click', function () {
    passVisible = !passVisible;
    const el = document.getElementById('cred-password');
    el.textContent = passVisible ? el.dataset.pass : '••••••••';
    this.innerHTML = passVisible ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });

  document.getElementById('btn-copy-pass').addEventListener('click', function () {
    const pass = document.getElementById('cred-password').dataset.pass;
    if (pass && navigator.clipboard) {
      navigator.clipboard.writeText(pass).then(() => {
        this.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(() => this.innerHTML = '<i class="bi bi-clipboard"></i>', 1500);
      });
    }
  });

  // ── Init ───────────────────────────────────────────────────────────────────
  fetchCsrf();
  showStep(1);
})();
</script>
</body>
</html>
