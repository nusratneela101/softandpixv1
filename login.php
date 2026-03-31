<?php
// Redirect to installer if not yet installed
if (!file_exists(__DIR__ . '/config/installed.lock') && file_exists(__DIR__ . '/install.php')) {
    header('Location: /install.php');
    exit;
}

session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// Already logged in?
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'admin') header('Location: /admin/');
    elseif ($role === 'developer') header('Location: /developer/');
    elseif ($role === 'client') header('Location: /client/');
    else header('Location: /profile.php');
    exit;
}


$loginError  = '';
$regError    = '';
$regSuccess  = '';
$csrf_token  = generateCsrfToken();
$activeTab   = isset($_GET['tab']) && $_GET['tab'] === 'register' ? 'register' : 'login';

// ---------- Handle login ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $activeTab = 'login';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $loginError = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $loginError = 'Please enter email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
                        $loginError = "Account locked. Try again in $mins minute(s).";
                    } elseif (password_verify($password, $user['password'])) {
                        $pdo->prepare("UPDATE users SET login_attempts=0, locked_until=NULL, last_login=NOW() WHERE id=?")->execute([$user['id']]);
                        session_regenerate_id(true);
                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['user_name']  = $user['name'];
                        $_SESSION['user_role']  = $user['role'];
                        $_SESSION['user_email'] = $user['email'];
                        $role = $user['role'];
                        if ($role === 'admin') header('Location: /admin/');
                        elseif ($role === 'developer') header('Location: /developer/');
                        elseif ($role === 'client') header('Location: /client/');
                        else header('Location: /profile.php');
                        exit;
                    } else {
                        $attempts = ($user['login_attempts'] ?? 0) + 1;
                        if ($attempts >= 5) {
                            $locked = date('Y-m-d H:i:s', time() + 900);
                            $pdo->prepare("UPDATE users SET login_attempts=?, locked_until=? WHERE id=?")->execute([$attempts, $locked, $user['id']]);
                            $loginError = 'Too many failed attempts. Account locked for 15 minutes.';
                        } else {
                            $pdo->prepare("UPDATE users SET login_attempts=? WHERE id=?")->execute([$attempts, $user['id']]);
                            $loginError = 'Invalid email or password. ' . (5 - $attempts) . ' attempts remaining.';
                        }
                    }
                } else {
                    $loginError = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $loginError = 'An error occurred. Please try again.';
            }
        }
    }
}

// ---------- Handle registration ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $activeTab = 'register';
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $regError = 'Invalid request.';
    } else {
        $name    = trim($_POST['name'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $company = trim($_POST['company'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            $regError = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $regError = 'Invalid email address.';
        } elseif (strlen($password) < 6) {
            $regError = 'Password must be at least 6 characters.';
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $check->execute([$email]);
                if ($check->fetch()) {
                    $regError = 'Email already registered.';
                } else {
                    // Ensure required columns exist
                    try {
                        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(255) DEFAULT NULL");
                        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");
                    } catch (Exception $e) {}
                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));
                    $stmt  = $pdo->prepare("INSERT INTO users (name, email, password, role, company, phone, verification_token) VALUES (?, ?, ?, 'client', ?, ?, ?)");
                    $stmt->execute([$name, $email, $hash, $company, $phone, $token]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Auto-create AI chat conversation
                    try {
                        // Ensure sender_role column exists (for older installs)
                        try {
                            $pdo->exec("ALTER TABLE chat_messages ADD COLUMN IF NOT EXISTS sender_role VARCHAR(20) DEFAULT 'user' COMMENT 'user, admin, bot'");
                        } catch (Exception $e) {}

                        $pdo->prepare("INSERT INTO chat_conversations (type, title, created_at, updated_at) VALUES ('support', 'Chat with Support', NOW(), NOW())")->execute();
                        $convId = (int)$pdo->lastInsertId();

                        $pdo->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id, role) VALUES (?, 0, 'admin')")->execute([$convId]);
                        $pdo->prepare("INSERT IGNORE INTO chat_participants (conversation_id, user_id, role) VALUES (?, ?, 'client')")->execute([$convId, $newUserId]);

                        $welcomeMsg = "Hi " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "! 👋 Welcome to Softandpix!\n\nI'm your AI assistant. How can I help you today?\n\nI can help you with:\n• 💡 Our services & expertise\n• 💰 Pricing & hiring models\n• ⏰ Project timelines\n• 🛠️ Technology stack\n• 📞 Getting in touch with our team\n\nJust type your question below!";
                        $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message, message_type, created_at) VALUES (?, 0, 'bot', ?, 'text', NOW())")->execute([$convId, $welcomeMsg]);

                        try {
                            require_once 'includes/email.php';
                            $siteUrl = getSiteUrl($pdo);
                            $adminNotifBody = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;">'
                                . '<div style="background:#0d6efd;padding:20px;text-align:center;border-radius:8px 8px 0 0;">'
                                . '<h2 style="color:#fff;margin:0;">🤖 New Client Chat Started</h2></div>'
                                . '<div style="padding:24px;background:#f8f9fa;border:1px solid #dee2e6;border-top:none;border-radius:0 0 8px 8px;">'
                                . '<p>A new client has registered and started an AI chat:</p>'
                                . '<ul><li><strong>Name:</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</li>'
                                . '<li><strong>Email:</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</li>'
                                . '<li><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</li></ul>'
                                . '<p style="text-align:center;margin-top:20px;">'
                                . '<a href="' . $siteUrl . '/admin/chat.php?conv=' . $convId . '" style="background:#0d6efd;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Join Chat Now</a>'
                                . '</p></div></div>';
                            sendAdminNotification($pdo, '🤖 New Client Registered & AI Chat Started — ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $adminNotifBody);
                        } catch (Exception $e) {}

                        session_regenerate_id(true);
                        $_SESSION['user_id']    = $newUserId;
                        $_SESSION['user_name']  = $name;
                        $_SESSION['user_role']  = 'client';
                        $_SESSION['user_email'] = $email;

                        header('Location: /client/chat.php?conv=' . $convId);
                        exit;

                    } catch (Exception $chatEx) {
                        // Chat creation failed — still auto-login and redirect to client panel
                        session_regenerate_id(true);
                        $_SESSION['user_id']    = $newUserId;
                        $_SESSION['user_name']  = $name;
                        $_SESSION['user_role']  = 'client';
                        $_SESSION['user_email'] = $email;
                        header('Location: /client/');
                        exit;
                    }
                }
            } catch (PDOException $e) {
                $regError = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In / Create Account - Softandpix</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #0f1829 0%, #1a2a4a 50%, #0d3050 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 16px;
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
  }
  body::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(65,84,241,0.15) 0%, transparent 70%);
    top: -100px; left: -100px;
    border-radius: 50%;
    pointer-events: none;
  }
  body::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(46,202,106,0.1) 0%, transparent 70%);
    bottom: -80px; right: -80px;
    border-radius: 50%;
    pointer-events: none;
  }
  .auth-wrapper { width: 100%; max-width: 500px; position: relative; z-index: 1; }
  .logo-wrap { text-align: center; margin-bottom: 24px; }
  .logo-wrap img { max-height: 52px; filter: brightness(0) invert(1); }
  .auth-card {
    background: rgba(255,255,255,0.97);
    border-radius: 20px;
    box-shadow: 0 24px 72px rgba(0,0,0,0.35), 0 0 0 1px rgba(255,255,255,0.08);
    overflow: hidden;
  }
  /* Tab navigation */
  .auth-tabs {
    display: flex;
    background: linear-gradient(135deg, #4154f1, #2eca6a);
  }
  .auth-tab-btn {
    flex: 1;
    padding: 18px 12px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.65);
    font-family: 'Poppins', sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.2s, background 0.2s;
    position: relative;
  }
  .auth-tab-btn.active {
    color: #fff;
    background: rgba(255,255,255,0.15);
  }
  .auth-tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: 0; left: 20%; right: 20%;
    height: 3px;
    background: #fff;
    border-radius: 2px 2px 0 0;
  }
  /* Tab content */
  .auth-tab-pane { display: none; }
  .auth-tab-pane.active { display: block; }
  .auth-card-body { padding: 28px 28px 20px; }
  @media (max-width: 480px) {
    .auth-card-body { padding: 20px 16px 16px; }
  }
  .form-label { font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
  .input-group .input-group-text {
    background: #f0f2ff;
    border: 1.5px solid #dde3ff;
    border-right: none;
    color: #4154f1;
    font-size: 15px;
  }
  .input-group .form-control {
    border: 1.5px solid #dde3ff;
    border-left: none;
    padding: 10px 14px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  .input-group .form-control:focus {
    border-color: #4154f1;
    box-shadow: 0 0 0 3px rgba(65,84,241,0.12);
    z-index: 1;
  }
  .input-group .btn-outline-secondary {
    border: 1.5px solid #dde3ff;
    border-left: none;
    color: #888;
  }
  .input-group .btn-outline-secondary:hover { background: #f0f2ff; color: #4154f1; }
  /* Standalone inputs for register */
  .input-icon-wrap { position: relative; }
  .input-icon-wrap .form-control-r {
    width: 100%;
    border: 1.5px solid #dde3ff;
    border-radius: 8px;
    padding: 10px 14px 10px 38px;
    font-size: 14px;
    font-family: 'Poppins', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  .input-icon-wrap .form-control-r:focus {
    border-color: #4154f1;
    box-shadow: 0 0 0 3px rgba(65,84,241,0.12);
  }
  .input-icon-wrap .field-icon {
    position: absolute;
    left: 12px; top: 50%;
    transform: translateY(-50%);
    color: #4154f1; font-size: 15px;
    pointer-events: none;
  }
  .input-icon-wrap .pwd-eye {
    position: absolute;
    right: 10px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    color: #888; cursor: pointer;
    padding: 0; font-size: 15px; line-height: 1;
  }
  .section-divider {
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 1px;
    color: #aaa; margin: 4px 0 14px;
    display: flex; align-items: center; gap: 10px;
  }
  .section-divider::before, .section-divider::after {
    content: ''; flex: 1; height: 1px; background: #e8ecff;
  }
  .password-strength { height: 4px; border-radius: 2px; margin-top: 6px; background: #e8ecff; overflow: hidden; }
  .password-strength-bar { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }
  .strength-text { font-size: 11px; margin-top: 3px; }
  .btn-submit {
    background: linear-gradient(135deg, #4154f1, #2eca6a);
    border: none; border-radius: 10px; padding: 12px;
    font-size: 15px; font-weight: 600; color: #fff; width: 100%;
    transition: opacity 0.2s, transform 0.15s;
    font-family: 'Poppins', sans-serif; letter-spacing: 0.3px; margin-top: 6px;
  }
  .btn-submit:hover { opacity: 0.92; transform: translateY(-1px); color: #fff; }
  .form-check-label { font-size: 13px; color: #555; }
  .forgot-link { font-size: 13px; color: #4154f1; text-decoration: none; }
  .forgot-link:hover { text-decoration: underline; }
  .switch-link {
    text-align: center; padding: 14px 28px 20px;
    font-size: 13px; color: #777;
  }
  .switch-link a { color: #4154f1; font-weight: 600; text-decoration: none; cursor: pointer; }
  .switch-link a:hover { text-decoration: underline; }
  .alert { border-radius: 10px; font-size: 13.5px; }
  .trust-badges {
    display: flex; flex-wrap: wrap; justify-content: center;
    gap: 14px; margin-top: 18px;
    color: rgba(255,255,255,0.5); font-size: 11px;
  }
  .trust-badges span { display: flex; align-items: center; gap: 4px; }
  .terms-note { font-size: 11.5px; color: #aaa; text-align: center; margin-top: 10px; }
  .terms-note a { color: #4154f1; }
  @media (max-height: 700px) {
    body { align-items: flex-start; padding-top: 20px; padding-bottom: 20px; }
  }
  @media (max-width: 480px) {
    .row.g-2 > .col-6 { flex: 0 0 100%; max-width: 100%; }
  }
</style>
</head>
<body>
<div class="auth-wrapper">
  <div class="logo-wrap">
    <a href="/"><img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix"></a>
  </div>
  <div class="auth-card">
    <!-- Tabs -->
    <div class="auth-tabs">
      <button class="auth-tab-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="tab-login-btn" onclick="switchTab('login')">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
      </button>
      <button class="auth-tab-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="tab-register-btn" onclick="switchTab('register')">
        <i class="bi bi-person-plus me-1"></i> Create Account
      </button>
    </div>

    <!-- Login Tab -->
    <div class="auth-tab-pane <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="tab-login">
      <div class="auth-card-body">
        <?php if ($loginError): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($loginError); ?></div>
        <?php endif; ?>
        <?php if ($regSuccess): ?>
        <div class="alert alert-success py-2"><i class="bi bi-check-circle-fill me-2"></i><?php echo h($regSuccess); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success py-2"><i class="bi bi-check-circle-fill me-2"></i>Account created! Please check your email to verify, then sign in.</div>
        <?php endif; ?>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
          <input type="hidden" name="action" value="login">
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
              <input type="email" name="email" class="form-control" placeholder="your@email.com"
                     value="<?php echo h($_POST['email'] ?? ''); ?>" required autofocus>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
              <input type="password" name="password" id="login-password" class="form-control" placeholder="Enter your password" required>
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('login-password','login-eye')" tabindex="-1">
                <i class="bi bi-eye" id="login-eye"></i>
              </button>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe">
              <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            <a href="/forgot_password.php" class="forgot-link">Forgot password?</a>
          </div>
          <button type="submit" class="btn-submit">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
          </button>
        </form>
      </div>
      <div class="switch-link">
        Don't have an account? <a onclick="switchTab('register')">Register →</a>
      </div>
    </div>

    <!-- Register Tab -->
    <div class="auth-tab-pane <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="tab-register">
      <div class="auth-card-body">
        <?php if ($regError): ?>
        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo h($regError); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
          <input type="hidden" name="action" value="register">

          <div class="section-divider">Personal Info</div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="bi bi-person-fill field-icon"></i>
                <input type="text" name="name" class="form-control-r" placeholder="John Doe"
                       value="<?php echo h($_POST['name'] ?? ''); ?>" required>
              </div>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Email Address <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="bi bi-envelope-fill field-icon"></i>
                <input type="email" name="email" class="form-control-r" placeholder="john@example.com"
                       value="<?php echo h($_POST['email'] ?? ''); ?>" required>
              </div>
            </div>
          </div>

          <div class="section-divider">Business Info <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:10px;color:#bbb">(optional)</span></div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label">Company</label>
              <div class="input-icon-wrap">
                <i class="bi bi-building field-icon"></i>
                <input type="text" name="company" class="form-control-r" placeholder="Your Company"
                       value="<?php echo h($_POST['company'] ?? ''); ?>">
              </div>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label">Phone</label>
              <div class="input-icon-wrap">
                <i class="bi bi-telephone-fill field-icon"></i>
                <input type="text" name="phone" class="form-control-r" placeholder="+1 234 567 8900"
                       value="<?php echo h($_POST['phone'] ?? ''); ?>">
              </div>
            </div>
          </div>

          <div class="section-divider">Security</div>
          <div class="mb-3">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <div class="input-icon-wrap">
              <i class="bi bi-lock-fill field-icon"></i>
              <input type="password" name="password" id="reg-password" class="form-control-r"
                     placeholder="Minimum 6 characters" required oninput="checkStrength(this.value)" style="padding-right:38px">
              <button type="button" class="pwd-eye" onclick="togglePwd('reg-password','reg-eye')" tabindex="-1">
                <i class="bi bi-eye" id="reg-eye"></i>
              </button>
            </div>
            <div class="password-strength mt-2"><div class="password-strength-bar" id="strength-bar"></div></div>
            <div class="strength-text text-muted" id="strength-text"></div>
          </div>

          <button type="submit" class="btn-submit">
            <i class="bi bi-rocket-takeoff me-2"></i>Create My Account
          </button>
          <p class="terms-note mt-3">By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
        </form>
      </div>
      <div class="switch-link">
        Already have an account? <a onclick="switchTab('login')">Sign in →</a>
      </div>
    </div>
  </div>

  <div class="trust-badges">
    <span><i class="bi bi-shield-check"></i> Secure</span>
    <span><i class="bi bi-lock"></i> SSL Encrypted</span>
    <span><i class="bi bi-award"></i> Trusted Service</span>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(tab) {
  document.getElementById('tab-login').classList.toggle('active', tab === 'login');
  document.getElementById('tab-register').classList.toggle('active', tab === 'register');
  document.getElementById('tab-login-btn').classList.toggle('active', tab === 'login');
  document.getElementById('tab-register-btn').classList.toggle('active', tab === 'register');
}
function togglePwd(inputId, iconId) {
  var p = document.getElementById(inputId);
  var i = document.getElementById(iconId);
  if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
function checkStrength(val) {
  var bar = document.getElementById('strength-bar');
  var txt = document.getElementById('strength-text');
  var score = 0;
  if (val.length >= 6) score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  var colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#27ae60'];
  var labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];
  if (val.length === 0) { bar.style.width='0'; txt.textContent=''; return; }
  if (score < 1) score = 1;
  bar.style.width = (score * 20) + '%';
  bar.style.background = colors[score-1] || colors[0];
  txt.textContent = labels[score-1] || labels[0];
  txt.style.color = colors[score-1] || colors[0];
}
</script>
</body>
</html>
