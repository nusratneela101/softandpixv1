<?php
/**
 * Payment Cancelled Page
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$invoiceId = (int)($_GET['invoice'] ?? 0);

$invoice = null;
if ($invoiceId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
    } catch (Exception $e) {}
}

$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $e) {}

$siteName = $settings['site_name'] ?? 'SoftandPix';
$currency = strtoupper($settings['payment_currency'] ?? 'USD');
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Payment Cancelled — <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
.cancel-card { max-width: 480px; margin: 60px auto; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
.icon-circle { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; }
</style></head><body>
<div class="container">
<div class="cancel-card card p-5 text-center">
  <div class="icon-circle bg-danger bg-opacity-25 text-danger">
    <i class="fas fa-times-circle"></i>
  </div>
  <h4 class="fw-bold text-danger">Payment Cancelled</h4>
  <p class="text-muted">Your payment was cancelled. No charges have been made to your account.</p>

  <?php if ($invoice): ?>
  <div class="bg-light rounded p-3 text-start mt-3 mb-4">
    <div class="row g-1 small">
      <div class="col-6 text-muted">Invoice #</div>
      <div class="col-6 fw-semibold"><?= e($invoice['invoice_number'] ?? $invoiceId) ?></div>
      <div class="col-6 text-muted">Amount</div>
      <div class="col-6 fw-semibold"><?= e($currency) ?> <?= number_format((float)($invoice['total_amount'] ?? $invoice['amount'] ?? 0), 2) ?></div>
      <div class="col-6 text-muted">Status</div>
      <div class="col-6"><span class="badge bg-secondary"><?= ucfirst(e($invoice['status'])) ?></span></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="d-grid gap-2">
    <?php if ($invoiceId): ?>
    <a href="<?= e(BASE_URL) ?>/payment/checkout.php?invoice=<?= $invoiceId ?>" class="btn btn-primary">
      <i class="fas fa-redo me-2"></i>Try Again
    </a>
    <?php endif; ?>
    <a href="<?= e(BASE_URL) ?>/client/invoices.php" class="btn btn-outline-secondary">
      <i class="fas fa-file-invoice me-2"></i>View My Invoices
    </a>
    <a href="<?= e(BASE_URL) ?>/client/" class="btn btn-outline-secondary">
      <i class="fas fa-home me-2"></i>Dashboard
    </a>
  </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
