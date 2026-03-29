<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$orderId = (int)($_GET['order'] ?? 0);

if (!$orderId) {
    header('Location: /client/orders.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Load order
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
} catch (Exception $e) {
    $order = null;
}

if (!$order) {
    header('Location: /client/orders.php');
    exit;
}

// Load payment settings
$stripePublicKey = getSetting($pdo, 'stripe_public_key', '');
$stripeSecretKey = getSetting($pdo, 'stripe_secret_key', '');
$paypalClientId  = getSetting($pdo, 'paypal_client_id', '');
$squareAppId     = getSetting($pdo, 'square_app_id', '');
$squareLocation  = getSetting($pdo, 'square_location_id', '');

$paymentMethod = $order['payment_method'];
$totalAmount   = $order['total_amount'];
$currency      = $order['currency'] ?: 'USD';
$csrf_token    = generateCsrfToken();

$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (Exception $e) {}
$siteName = $settings['site_name'] ?? 'Softandpix';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment - <?php echo h($siteName); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
.payment-card { border-radius: 16px; border: none; box-shadow: 0 4px 24px rgba(0,0,0,.10); max-width: 480px; margin: 0 auto; }
.StripeElement { border: 1px solid #dee2e6; border-radius: 10px; padding: 14px; background: #fff; }
.StripeElement--focus { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
</style>
</head>
<body>
<div class="container py-5">
<div class="payment-card card p-4">
  <div class="text-center mb-4">
    <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:40px;">
    <h5 class="fw-bold mt-3">Complete Your Payment</h5>
    <div class="text-muted small">Order: <strong><?php echo h($order['order_number']); ?></strong></div>
    <div class="fs-4 fw-bold text-primary mt-2"><?php echo h($currency); ?> <?php echo number_format($totalAmount, 2); ?></div>
  </div>

  <?php if ($order['payment_status'] === 'paid'): ?>
  <div class="alert alert-success text-center">
    <i class="bi bi-check-circle-fill me-2"></i>This order has already been paid.
    <div class="mt-2"><a href="/payment/success.php?order=<?php echo $orderId; ?>" class="btn btn-success btn-sm">View Confirmation</a></div>
  </div>

  <?php elseif ($paymentMethod === 'stripe' && !empty($stripePublicKey)): ?>
  <div id="stripeError" class="alert alert-danger d-none mb-3"></div>
  <form id="stripeForm">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
    <div class="mb-3">
      <label class="form-label fw-semibold">Card Details</label>
      <div id="card-element" class="StripeElement"></div>
      <div id="card-errors" class="text-danger small mt-1"></div>
    </div>
    <button class="btn btn-primary w-100 py-2 fw-semibold" id="stripePayBtn" type="submit">
      <i class="bi bi-lock me-2"></i>Pay <?php echo h($currency); ?> <?php echo number_format($totalAmount, 2); ?>
    </button>
  </form>

  <?php elseif ($paymentMethod === 'paypal' && !empty($paypalClientId)): ?>
  <div id="paypal-button-container"></div>

  <?php elseif ($paymentMethod === 'square' && !empty($squareAppId)): ?>
  <div id="card-container"></div>
  <div id="sq-errors" class="text-danger small mt-1 d-none"></div>
  <button id="card-button" class="btn btn-dark w-100 mt-3 py-2 fw-semibold">
    <i class="bi bi-square me-2"></i>Pay with Square
  </button>

  <?php else: ?>
  <div class="alert alert-warning text-center">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Payment gateway not configured. Your order has been placed and an admin will process your payment manually.
    <div class="mt-2">
      <a href="/payment/success.php?order=<?php echo $orderId; ?>" class="btn btn-warning btn-sm">View Order</a>
    </div>
  </div>
  <?php endif; ?>

  <div class="text-center mt-3">
    <a href="/client/orders.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Back to Orders</a>
  </div>
</div>
</div>

<?php if ($paymentMethod === 'stripe' && !empty($stripePublicKey)): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
var stripe   = Stripe('<?php echo h($stripePublicKey); ?>');
var elements = stripe.elements();
var card     = elements.create('card', {style:{base:{fontFamily:'Poppins,sans-serif',fontSize:'16px'}}});
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
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({order_id: <?php echo $orderId; ?>, csrf_token: '<?php echo h($csrf_token); ?>'})
    });
    const data = await resp.json();
    if (!data.client_secret) {
        document.getElementById('stripeError').textContent = data.error || 'Payment failed.';
        document.getElementById('stripeError').classList.remove('d-none');
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock me-2"></i>Pay <?php echo h($currency); ?> <?php echo number_format($totalAmount, 2); ?>';
        return;
    }
    const result = await stripe.confirmCardPayment(data.client_secret, {
        payment_method: {card: card}
    });
    if (result.error) {
        document.getElementById('card-errors').textContent = result.error.message;
        btn.disabled = false; btn.innerHTML = '<i class="bi bi-lock me-2"></i>Pay <?php echo h($currency); ?> <?php echo number_format($totalAmount, 2); ?>';
    } else if (result.paymentIntent.status === 'succeeded') {
        window.location.href = '/payment/success.php?order=<?php echo $orderId; ?>&pi=' + result.paymentIntent.id;
    }
});
</script>
<?php endif; ?>

<?php if ($paymentMethod === 'paypal' && !empty($paypalClientId)): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo h($paypalClientId); ?>&currency=<?php echo h($currency); ?>"></script>
<script>
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{amount: {value: '<?php echo number_format($totalAmount, 2, '.', ''); ?>'}, description: 'Order <?php echo h($order['order_number']); ?>'}]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function() {
            window.location.href = '/payment/callback.php?gateway=paypal&order=<?php echo $orderId; ?>&paypal_order_id=' + data.orderID;
        });
    },
    onError: function() {
        window.location.href = '/payment/cancel.php?order=<?php echo $orderId; ?>';
    }
}).render('#paypal-button-container');
</script>
<?php endif; ?>

<?php if ($paymentMethod === 'square' && !empty($squareAppId)): ?>
<script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
<script>
async function initSquare() {
    const payments = Square.payments('<?php echo h($squareAppId); ?>', '<?php echo h($squareLocation); ?>');
    const card = await payments.card();
    await card.attach('#card-container');
    document.getElementById('card-button').addEventListener('click', async function() {
        this.disabled = true; this.textContent = 'Processing...';
        try {
            const result = await card.tokenize();
            if (result.status === 'OK') {
                const fd = new FormData();
                fd.append('order_id', <?php echo $orderId; ?>);
                fd.append('token', result.token);
                fd.append('csrf_token', '<?php echo h($csrf_token); ?>');
                const resp = await fetch('/api/payment/square_charge.php', {method:'POST', body:fd});
                const data = await resp.json();
                if (data.success) {
                    window.location.href = '/payment/success.php?order=<?php echo $orderId; ?>';
                } else {
                    document.getElementById('sq-errors').textContent = data.error || 'Payment failed';
                    document.getElementById('sq-errors').classList.remove('d-none');
                    this.disabled = false; this.textContent = 'Pay with Square';
                }
            }
        } catch(err) {
            this.disabled = false; this.textContent = 'Pay with Square';
        }
    });
}
initSquare();
</script>
<?php endif; ?>
</body>
</html>
