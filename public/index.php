<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

if (session_status() === PHP_SESSION_NONE) session_start();

// Check if installed
if (!file_exists(BASE_PATH . '/config/installed.lock')) {
    header('Location: ' . BASE_URL . '/install/');
    exit;
}

// If logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/' . $_SESSION['user_role'] . '/');
    exit;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SoftandPix — Project Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body{margin:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
.hero{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;min-height:100vh;display:flex;align-items:center}
.hero h1{font-size:3.5rem;font-weight:800} .hero p{font-size:1.2rem;opacity:0.9}
.btn-hero{padding:15px 40px;border-radius:50px;font-size:1.1rem;font-weight:600}
.feature-card{background:white;border-radius:15px;padding:30px;box-shadow:0 5px 20px rgba(0,0,0,0.1);transition:transform 0.3s;text-align:center}
.feature-card:hover{transform:translateY(-5px)}
.feature-card i{font-size:2.5rem;color:#667eea;margin-bottom:15px}
</style></head><body>
<section class="hero">
<div class="container"><div class="row align-items-center">
<div class="col-lg-6">
<h1>SoftandPix</h1>
<p class="mb-4">Complete Project Management System with Real-time Chat, Invoicing, Payment Processing, and Multi-role Access Control.</p>
<a href="<?= BASE_URL ?>/login.php" class="btn btn-light btn-hero me-3"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
<a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-light btn-hero"><i class="fas fa-user-plus me-2"></i>Register</a>
</div>
<div class="col-lg-6 text-center"><i class="fas fa-project-diagram" style="font-size:15rem;opacity:0.2"></i></div>
</div></div>
</section>
<section class="py-5" style="background:#f8f9fa">
<div class="container">
<h2 class="text-center mb-5">Features</h2>
<div class="row g-4">
<div class="col-md-4"><div class="feature-card"><i class="fas fa-comments"></i><h5>Real-time Chat</h5><p class="text-muted">Chat with clients and developers with AI-powered assistance</p></div></div>
<div class="col-md-4"><div class="feature-card"><i class="fas fa-file-invoice-dollar"></i><h5>Invoicing</h5><p class="text-muted">Create and send professional invoices with multiple payment options</p></div></div>
<div class="col-md-4"><div class="feature-card"><i class="fas fa-credit-card"></i><h5>Payments</h5><p class="text-muted">Accept payments via Square, Stripe, and PayPal</p></div></div>
<div class="col-md-4"><div class="feature-card"><i class="fas fa-tasks"></i><h5>Project Management</h5><p class="text-muted">Track projects, deadlines, and progress in real-time</p></div></div>
<div class="col-md-4"><div class="feature-card"><i class="fas fa-envelope"></i><h5>Email System</h5><p class="text-muted">Full email with rich text editor, attachments, and folders</p></div></div>
<div class="col-md-4"><div class="feature-card"><i class="fas fa-shield-alt"></i><h5>Multi-role Access</h5><p class="text-muted">Admin, Client, and Developer roles with granular permissions</p></div></div>
</div></div></section>
<footer class="bg-dark text-white text-center py-4"><p class="mb-0">&copy; <?= date('Y') ?> SoftandPix. All rights reserved.</p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
