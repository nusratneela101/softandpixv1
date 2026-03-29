<?php
/**
 * Admin Payment Gateway Settings
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
update_online_status($pdo, $_SESSION['user_id']);
require_once BASE_PATH . '/includes/payment_helper.php';
ensurePaymentTables($pdo);

$csrf_token = generateCsrfToken();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: payment_settings.php'); exit;
    }

    $fields = [
        'stripe_enabled'        => (int)!empty($_POST['stripe_enabled']),
        'stripe_public_key'     => trim($_POST['stripe_public_key'] ?? ''),
        'stripe_secret_key'     => trim($_POST['stripe_secret_key'] ?? ''),
        'stripe_webhook_secret' => trim($_POST['stripe_webhook_secret'] ?? ''),
        'paypal_enabled'        => (int)!empty($_POST['paypal_enabled']),
        'paypal_client_id'      => trim($_POST['paypal_client_id'] ?? ''),
        'paypal_client_secret'  => trim($_POST['paypal_client_secret'] ?? ''),
        'paypal_mode'           => in_array($_POST['paypal_mode'] ?? '', ['sandbox', 'live']) ? $_POST['paypal_mode'] : 'sandbox',
        'manual_enabled'        => (int)!empty($_POST['manual_enabled']),
        'manual_instructions'   => trim($_POST['manual_instructions'] ?? ''),
        'payment_currency'      => strtoupper(preg_replace('/[^A-Z]/', '', strtoupper($_POST['payment_currency'] ?? 'USD'))),
        'default_gateway'       => in_array($_POST['default_gateway'] ?? '', ['stripe', 'paypal', 'manual'])
            ? $_POST['default_gateway'] : 'manual',
    ];

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value)
             VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        foreach ($fields as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        flashMessage('success', 'Payment settings saved.');
    } catch (Exception $e) {
        flashMessage('error', 'Failed to save settings: ' . $e->getMessage());
    }

    header('Location: payment_settings.php'); exit;
}

// Load current settings
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

function setting(array $s, string $key, $default = ''): string
{
    return htmlspecialchars((string)($s[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Payment Settings — SoftandPix Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_admin.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Payment Gateway Settings</h5></div>
<div class="topbar-right"><a href="payments.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Payments</a></div></div>
<div class="main-content">

<?php include BASE_PATH . '/includes/flash.php'; ?>

<form method="POST" action="payment_settings.php">
  <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
  <div class="row g-4">

    <!-- General -->
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i class="fas fa-cog me-2 text-secondary"></i>General</div>
        <div class="card-body row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Default Gateway</label>
            <select name="default_gateway" class="form-select">
              <option value="stripe"  <?= setting($settings,'default_gateway','manual')==='stripe'  ?'selected':'' ?>>Stripe</option>
              <option value="paypal"  <?= setting($settings,'default_gateway','manual')==='paypal'  ?'selected':'' ?>>PayPal</option>
              <option value="manual"  <?= setting($settings,'default_gateway','manual')==='manual'  ?'selected':'' ?>>Manual / Bank Transfer</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Currency (ISO code)</label>
            <input type="text" name="payment_currency" class="form-control" maxlength="3"
              value="<?= setting($settings,'payment_currency','USD') ?>" placeholder="USD">
          </div>
        </div>
      </div>
    </div>

    <!-- Stripe -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="fab fa-stripe me-2 text-primary"></i>Stripe</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="stripe_enabled" id="stripeEnabled" value="1"
              <?= !empty($settings['stripe_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="stripeEnabled">Enabled</label>
          </div>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Publishable Key</label>
            <input type="text" name="stripe_public_key" class="form-control font-monospace"
              value="<?= setting($settings,'stripe_public_key') ?>" placeholder="pk_live_...">
          </div>
          <div class="mb-3">
            <label class="form-label">Secret Key</label>
            <input type="password" name="stripe_secret_key" class="form-control font-monospace"
              value="<?= setting($settings,'stripe_secret_key') ?>" placeholder="sk_live_..." autocomplete="new-password">
            <div class="form-text">Your secret key is stored encrypted and never displayed.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Webhook Signing Secret</label>
            <input type="password" name="stripe_webhook_secret" class="form-control font-monospace"
              value="<?= setting($settings,'stripe_webhook_secret') ?>" placeholder="whsec_..." autocomplete="new-password">
          </div>
          <div class="alert alert-light border small mb-0">
            <strong>Webhook URL:</strong><br>
            <code><?= e(BASE_URL) ?>/payment/stripe_webhook.php</code>
          </div>
        </div>
      </div>
    </div>

    <!-- PayPal -->
    <div class="col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="fab fa-paypal me-2 text-primary"></i>PayPal</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="paypal_enabled" id="paypalEnabled" value="1"
              <?= !empty($settings['paypal_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="paypalEnabled">Enabled</label>
          </div>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Client ID</label>
            <input type="text" name="paypal_client_id" class="form-control font-monospace"
              value="<?= setting($settings,'paypal_client_id') ?>" placeholder="ARn...">
          </div>
          <div class="mb-3">
            <label class="form-label">Client Secret</label>
            <input type="password" name="paypal_client_secret" class="form-control font-monospace"
              value="<?= setting($settings,'paypal_client_secret') ?>" placeholder="ECL..." autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Mode</label>
            <select name="paypal_mode" class="form-select">
              <option value="sandbox" <?= setting($settings,'paypal_mode','sandbox')==='sandbox'?'selected':'' ?>>Sandbox (Testing)</option>
              <option value="live"    <?= setting($settings,'paypal_mode','sandbox')==='live'   ?'selected':'' ?>>Live</option>
            </select>
          </div>
          <div class="alert alert-light border small mb-0">
            <strong>Webhook URL:</strong><br>
            <code><?= e(BASE_URL) ?>/payment/paypal_webhook.php</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Manual -->
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="fas fa-university me-2 text-secondary"></i>Manual / Bank Transfer</span>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="manual_enabled" id="manualEnabled" value="1"
              <?= ($settings['manual_enabled'] ?? '1') ? 'checked' : '' ?>>
            <label class="form-check-label" for="manualEnabled">Enabled</label>
          </div>
        </div>
        <div class="card-body">
          <label class="form-label fw-semibold">Payment Instructions (shown to client on checkout)</label>
          <textarea name="manual_instructions" class="form-control" rows="4"><?= setting($settings,'manual_instructions','Please contact us for bank transfer details.') ?></textarea>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Save Settings</button>
    </div>
  </div>
</form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
