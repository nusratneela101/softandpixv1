<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../client/includes/auth.php';
requireClient();

$planId = (int)($_GET['plan'] ?? 0);
if (!$planId) { header('Location: /pricing.php'); exit; }

try {
    $plan = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
    $plan->execute([$planId]);
    $plan = $plan->fetch();
} catch (Exception $e) { $plan = null; }

if (!$plan) {
    flashMessage('error', 'Plan not found or no longer available.');
    header('Location: /pricing.php'); exit;
}

$features      = json_decode($plan['features'] ?? '[]', true) ?: [];
$stripeKey     = getSetting($pdo, 'stripe_public_key');
$paypalClientId = getSetting($pdo, 'paypal_client_id');
$csrf_token    = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe — <?php echo h($plan['name']); ?> | Softandpix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
    body { background: #f0f2f5; }
    .plan-card { border-radius: 14px; border: 2px solid #e9ecef; }
    .plan-card.popular { border-color: #0d6efd; }
    .method-card { border: 2px solid #e9ecef; border-radius: 12px; cursor: pointer; transition: border-color .2s, box-shadow .2s; padding: 18px; }
    .method-card:hover, .method-card.selected { border-color: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.15); }
    .trust-badge { font-size: .8rem; color: #6c757d; }
    </style>
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand" href="/index.php">
            <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="height:38px;">
        </a>
        <a href="/pricing.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Plans</a>
    </div>
</nav>

<div class="container" style="max-width:900px;">
    <?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Plan Summary -->
        <div class="col-md-5">
            <div class="card plan-card <?php echo $plan['is_popular'] ? 'popular' : ''; ?> shadow-sm h-100">
                <div class="card-body p-4">
                    <?php if ($plan['is_popular']): ?>
                    <span class="badge bg-primary mb-2"><i class="bi bi-star-fill me-1"></i>Most Popular</span>
                    <?php endif; ?>
                    <h4 class="fw-bold"><?php echo h($plan['name']); ?></h4>
                    <?php if (!empty($plan['description'])): ?>
                    <p class="text-muted small"><?php echo h($plan['description']); ?></p>
                    <?php endif; ?>
                    <div class="display-5 fw-bold text-primary mb-0">
                        <?php echo h($plan['currency']); ?> <?php echo number_format((float)$plan['price'], 2); ?>
                    </div>
                    <div class="text-muted small mb-4">/ <?php echo ucfirst($plan['billing_cycle']); ?></div>

                    <?php if (!empty($features)): ?>
                    <ul class="list-unstyled">
                        <?php foreach ($features as $f): ?>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo h($f); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light p-3 text-center trust-badge">
                    <i class="bi bi-shield-lock me-1"></i>Secure &amp; Encrypted &nbsp;
                    <i class="bi bi-arrow-repeat me-1"></i>Cancel Anytime
                </div>
            </div>
        </div>

        <!-- Payment Method Selection -->
        <div class="col-md-7">
            <div class="card shadow-sm" style="border-radius:14px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4"><i class="bi bi-credit-card me-2"></i>Choose Payment Method</h5>

                    <?php if (empty($stripeKey) && empty($paypalClientId)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        No payment methods are configured. Please contact the administrator.
                    </div>
                    <?php else: ?>

                    <div class="row g-3 mb-4" id="methodSelection">
                        <?php if (!empty($stripeKey)): ?>
                        <div class="col-12">
                            <div class="method-card selected" id="stripeOption" onclick="selectMethod('stripe')">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width:44px;height:44px;background:linear-gradient(135deg,#6772e5,#5469d4);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-credit-card text-white fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">Credit / Debit Card</div>
                                        <div class="small text-muted">Powered by Stripe — Visa, Mastercard, Amex</div>
                                    </div>
                                    <i class="bi bi-check-circle-fill text-primary ms-auto fs-5" id="stripeCheck"></i>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($paypalClientId)): ?>
                        <div class="col-12">
                            <div class="method-card" id="paypalOption" onclick="selectMethod('paypal')">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="width:44px;height:44px;background:#003087;border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-paypal text-white fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold">PayPal</div>
                                        <div class="small text-muted">Pay securely with your PayPal account</div>
                                    </div>
                                    <i class="bi bi-circle text-muted ms-auto fs-5" id="paypalCheck"></i>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="/payment/<?php echo !empty($stripeKey) ? 'stripe_subscription' : 'paypal_subscription'; ?>.php" id="checkoutForm">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="plan_id" value="<?php echo (int)$plan['id']; ?>">
                        <input type="hidden" name="gateway" value="stripe" id="gatewayInput">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold py-3" id="proceedBtn">
                                <i class="bi bi-lock me-2"></i>Proceed to Secure Checkout
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3 trust-badge">
                        <i class="bi bi-shield-check me-1"></i>256-bit SSL encryption &nbsp;|&nbsp;
                        <i class="bi bi-arrow-repeat me-1"></i>Cancel anytime
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
var hasStripe  = <?php echo !empty($stripeKey)      ? 'true' : 'false'; ?>;
var hasPaypal  = <?php echo !empty($paypalClientId) ? 'true' : 'false'; ?>;
var selected   = hasStripe ? 'stripe' : 'paypal';

function selectMethod(m) {
    selected = m;
    document.getElementById('gatewayInput').value = m;
    document.getElementById('checkoutForm').action = '/payment/' + m + '_subscription.php';

    var sO = document.getElementById('stripeOption');
    var pO = document.getElementById('paypalOption');
    var sC = document.getElementById('stripeCheck');
    var pC = document.getElementById('paypalCheck');

    if (sO) { sO.classList.toggle('selected', m === 'stripe'); }
    if (pO) { pO.classList.toggle('selected', m === 'paypal'); }
    if (sC) { sC.className = m === 'stripe' ? 'bi bi-check-circle-fill text-primary ms-auto fs-5' : 'bi bi-circle text-muted ms-auto fs-5'; }
    if (pC) { pC.className = m === 'paypal' ? 'bi bi-check-circle-fill text-primary ms-auto fs-5' : 'bi bi-circle text-muted ms-auto fs-5'; }
}

// init
selectMethod(selected);
</script>
</body>
</html>
