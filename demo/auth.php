<?php
/**
 * demo/auth.php
 * Password-protection handler for live demo pages.
 * Included by demo/index.php when the project has a demo_password set.
 */

// $project and $sessionKey are already set by demo/index.php

$authError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demo_password'])) {
    $entered = $_POST['demo_password'] ?? '';
    if (!empty($entered) && password_verify($entered, $project['demo_password'])) {
        $_SESSION[$sessionKey] = true;
        // Redirect to the validated subdomain (avoids Host header injection)
        $scheme      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $redirectUrl = $scheme . '://' . htmlspecialchars($subdomain, ENT_QUOTES, 'UTF-8') . '.softandpix.com/';
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $authError = 'Incorrect password. Please try again.';
    }
}

$title = htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8');
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $title; ?> — Demo Password | Softandpix</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:linear-gradient(135deg,#1e3a5f,#2563eb); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.card { background:#fff; border-radius:16px; padding:48px 40px; max-width:420px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.logo { text-align:center; margin-bottom:24px; font-size:1.1rem; font-weight:700; color:#1e3a5f; }
.logo span { color:#2563eb; }
h2 { font-size:1.25rem; font-weight:700; color:#1e3a5f; margin-bottom:6px; }
.subtitle { color:#6b7280; font-size:.88rem; margin-bottom:24px; }
.lock-icon { font-size:2.5rem; text-align:center; margin-bottom:16px; }
label { display:block; font-size:.85rem; font-weight:600; color:#374151; margin-bottom:6px; }
input[type=password] { width:100%; padding:10px 14px; border:1.5px solid #d1d5db; border-radius:8px; font-size:.95rem; outline:none; transition:border .2s; }
input[type=password]:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
.btn { width:100%; background:#2563eb; color:#fff; border:none; padding:11px; border-radius:8px; font-size:.95rem; font-weight:600; cursor:pointer; margin-top:14px; transition:background .2s; }
.btn:hover { background:#1d4ed8; }
.error { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; font-size:.87rem; margin-bottom:16px; }
.brand { text-align:center; margin-top:24px; font-size:.78rem; color:#9ca3af; }
.brand a { color:#2563eb; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">Soft<span>and</span>Pix</div>
    <div class="lock-icon">🔒</div>
    <h2><?php echo $title; ?></h2>
    <p class="subtitle">This live demo is password protected. Enter the password to continue.</p>

    <?php if ($authError): ?>
    <div class="error"><?php echo htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <label for="demo_password">Demo Password</label>
        <input type="password" id="demo_password" name="demo_password" placeholder="Enter password..." required autofocus>
        <button class="btn" type="submit">🚀 View Demo</button>
    </form>

    <div class="brand">Powered by <a href="https://softandpix.com" target="_blank">Softandpix</a></div>
</div>
</body>
</html>
