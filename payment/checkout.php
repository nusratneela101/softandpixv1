<?php
/**
 * Unified Payment Checkout Page
 * Reads invoice details and presents the appropriate gateway.
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/payment_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

ensurePaymentTables($pdo);

$userId    = (int)$_SESSION['user_id'];
$invoiceId = (int)($_GET['invoice'] ?? 0);

if (!$invoiceId) {
    header('Location: ' . BASE_URL . '/client/invoices.php');
    exit;
}

// Load invoice
try {
    $stmt = $pdo->prepare(
        "SELECT i.*, u.name AS client_name FROM invoices i
         JOIN users u ON u.id = i.client_id
         WHERE i.id = ? LIMIT 1"
    );
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
} catch (Exception $e) {
    $invoice = null;
}

if (!$invoice) {
    header('Location: ' . BASE_URL . '/client/invoices.php');
    exit;
}

// Only the owning client (or admin) may pay
$isAdmin    = ($_SESSION['user_role'] ?? '') === 'admin';
if (!$isAdmin && (int)$invoice['client_id'] !== $userId) {
    header('Location: ' . BASE_URL . '/client/invoices.php');
    exit;
}

// Already paid
if ($invoice['status'] === 'paid') {
    header('Location: ' . BASE_URL . '/payment/success.php?invoice=' . $invoiceId);
    exit;
}

// Load gateway config from DB
$settings     = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $e) {}

$stripeEnabled  = !empty($settings['stripe_enabled'])  && !empty($settings['stripe_public_key']);
$paypalEnabled  = !empty($settings['paypal_enabled'])   && !empty($settings['paypal_client_id']);
$manualEnabled  = $settings['manual_enabled'] ?? '1';
$defaultGateway = $settings['default_gateway'] ?? 'manual';
$currency       = strtoupper($settings['payment_currency'] ?? 'USD');
$siteName       = $settings['site_name'] ?? 'SoftandPix';

$amount   = (float)($invoice['total_amount'] ?? $invoice['amount'] ?? 0);
$csrf     = generateCsrfToken();

// If no gateway active, fall back to manual
if (!$stripeEnabled && !$paypalEnabled) {
    $defaultGateway = 'manual';
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout — <?= e($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
.checkout-card { max-width: 520px; margin: 40px auto; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.10); }
.gateway-tab.active { border-color: #0d6efd; background: #e8f0fe; }
.StripeElement { border: 1px solid #dee2e6; border-radius: 8px; padding: 14px; background: #fff; }
.StripeElement--focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
</style></head><body>
<div class="container py-5">
<div class="checkout-card card p-4">
  <div class="text-center mb-4">
    <h5 class="fw-bold"><?= e($siteName) ?></h5>
    <div class="text-muted small">Invoice #<?= e($invoice['invoice_number'] ?? $invoiceId) ?></div>
    <div class="fs-3 fw-bold text-primary mt-1"><?= e($currency) ?> <?= number_format($amount, 2) ?></div>
  </div>

  <?php if ($stripeEnabled || $paypalEnabled): ?>
  <!-- Gateway selector tabs -->
  <div class="d-flex gap-2 mb-4">
    <?php if ($stripeEnabled): ?>
    <button type="button" class="btn btn-outline-secondary flex-fill gateway-tab <?= $defaultGateway === 'stripe' ? 'active' : '' ?>"
      onclick="showGateway('stripe',this)">
      <i class="fab fa-stripe me-2"></i>Card
    </button>
    <?php endif; ?>
    <?php if ($paypalEnabled): ?>
    <button type="button" class="btn btn-outline-secondary flex-fill gateway-tab <?= $defaultGateway === 'paypal' ? 'active' : '' ?>"
      onclick="showGateway('paypal',this)">
      <i class="fab fa-paypal me-2"></i>PayPal
    </button>
    <?php endif; ?>
    <?php if ($manualEnabled): ?>
    <button type="button" class="btn btn-outline-secondary flex-fill gateway-tab <?= $defaultGateway === 'manual' ? 'active' : '' ?>"
      onclick="showGateway('manual',this)">
      <i class="fas fa-university me-2"></i>Manual
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Stripe panel -->
  <?php if ($stripeEnabled): ?>
  <div id="panel-stripe" class="gateway-panel <?= $defaultGateway !== 'stripe' ? 'd-none' : '' ?>">
    <div id="stripeError" class="alert alert-danger d-none small"></div>
    <form id="stripeForm">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
      <div class="mb-3">
        <label class="form-label fw-semibold small">Card Details</label>
        <div id="card-element" class="StripeElement"></div>
        <div id="card-errors" class="text-danger small mt-1"></div>
      </div>
      <button class="btn btn-primary w-100 py-2" id="stripePayBtn" type="submit">
        <i class="fas fa-lock me-2"></i>Pay <?= e($currency) ?> <?= number_format($amount, 2) ?>
      </button>
    </form>
  </div>
  <?php endif; ?>

  <!-- PayPal panel -->
  <?php if ($paypalEnabled): ?>
  <div id="panel-paypal" class="gateway-panel <?= $defaultGateway !== 'paypal' ? 'd-none' : '' ?>">
    <div id="paypal-button-container"></div>
  </div>
  <?php endif; ?>

  <!-- Manual panel -->
  <div id="panel-manual" class="gateway-panel <?= ($stripeEnabled || $paypalEnabled) && $defaultGateway !== 'manual' ? 'd-none' : '' ?>">
    <div class="alert alert-info small">
      <i class="fas fa-info-circle me-2"></i>
      <?= nl2br(e($settings['manual_instructions'] ?? 'Please contact us for payment instructions.')) ?>
    </div>
    <form method="POST" action="<?= e(BASE_URL) ?>/payment/checkout.php?invoice=<?= $invoiceId ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="gateway" value="manual">
      <button type="submit" class="btn btn-secondary w-100 py-2">
        <i class="fas fa-check me-2"></i>Mark as Pending Manual Payment
      </button>
    </form>
  </div>

  <div class="text-center mt-3">
    <a href="<?= e(BASE_URL) ?>/client/invoices.php" class="text-muted small">
      <i class="fas fa-arrow-left me-1"></i>Back to invoices
    </a>
  </div>
</div>
</div>

<?php
// Handle manual payment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['gateway'] ?? '') === 'manual') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
    try {
        $pdo->prepare(
            "INSERT INTO payment_transactions (invoice_id, user_id, gateway, amount, currency, status)
             VALUES (?, ?, 'manual', ?, ?, 'pending')"
        )->execute([$invoiceId, $userId, $amount, $currency]);
        $pdo->prepare("UPDATE invoices SET status='pending' WHERE id=?")->execute([$invoiceId]);
        header('Location: ' . BASE_URL . '/payment/success.php?invoice=' . $invoiceId . '&gateway=manual');
        exit;
    } catch (Exception $e) {
        // fall through to page
    }
}
?>

<?php if ($stripeEnabled): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
  var stripe = Stripe('<?= e($settings['stripe_public_key'] ?? '') ?>');
  var elements = stripe.elements();
  var card = elements.create('card', {style:{base:{fontFamily:'Segoe UI,sans-serif',fontSize:'16px'}}});
  card.mount('#card-element');
  card.on('change', function(e) {
    document.getElementById('card-errors').textContent = e.error ? e.error.message : '';
  });
  document.getElementById('stripeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var btn = document.getElementById('stripePayBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    const resp = await fetch('/api/payment/create_intent.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({invoice_id: <?= $invoiceId ?>, csrf_token: '<?= e($csrf) ?>'})
    });
    const data = await resp.json();
    if (!data.client_secret) {
      document.getElementById('stripeError').textContent = data.error || 'Payment failed.';
      document.getElementById('stripeError').classList.remove('d-none');
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-lock me-2"></i>Pay <?= e($currency) ?> <?= number_format($amount, 2) ?>';
      return;
    }
    const result = await stripe.confirmCardPayment(data.client_secret, {payment_method: {card: card}});
    if (result.error) {
      document.getElementById('card-errors').textContent = result.error.message;
      btn.disabled = false; btn.innerHTML = '<i class="fas fa-lock me-2"></i>Pay <?= e($currency) ?> <?= number_format($amount, 2) ?>';
    } else if (result.paymentIntent.status === 'succeeded') {
      window.location.href = '/payment/success.php?invoice=<?= $invoiceId ?>&pi=' + result.paymentIntent.id;
    }
  });
})();
</script>
<?php endif; ?>

<?php if ($paypalEnabled): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= e($settings['paypal_client_id'] ?? '') ?>&currency=<?= e($currency) ?>"></script>
<script>
paypal.Buttons({
  createOrder: function(data, actions) {
    return actions.order.create({
      purchase_units: [{amount: {value: '<?= number_format($amount, 2, '.', '') ?>'}, description: 'Invoice #<?= e($invoice['invoice_number'] ?? $invoiceId) ?>'}]
    });
  },
  onApprove: function(data, actions) {
    return actions.order.capture().then(function() {
      window.location.href = '/payment/success.php?invoice=<?= $invoiceId ?>&paypal_order=' + data.orderID + '&gateway=paypal';
    });
  },
  onError: function() {
    window.location.href = '/payment/cancel.php?invoice=<?= $invoiceId ?>';
  }
}).render('#paypal-button-container');
</script>
<?php endif; ?>

<script>
function showGateway(name, btn) {
  document.querySelectorAll('.gateway-panel').forEach(function(p) { p.classList.add('d-none'); });
  var panel = document.getElementById('panel-' + name);
  if (panel) panel.classList.remove('d-none');
  document.querySelectorAll('.gateway-tab').forEach(function(b) { b.classList.remove('active'); });
  btn.classList.add('active');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
