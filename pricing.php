<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Pricing Plans - Softandpix';

// Fetch active plans
try {
    $plans = $pdo->query(
        "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC"
    )->fetchAll();
} catch (Exception $e) {
    $plans = [];
}

// billing cycle toggle from query param
$cycle = in_array($_GET['cycle'] ?? '', ['monthly','quarterly','yearly']) ? $_GET['cycle'] : 'monthly';

require_once __DIR__ . '/includes/header.php';
?>
<style>
.pricing-section { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 80vh; padding: 60px 0 80px; }
.pricing-card { border-radius: 16px; border: 2px solid #e9ecef; transition: transform .2s, box-shadow .2s; background: #fff; overflow: hidden; }
.pricing-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0,0,0,.12); }
.pricing-card.popular { border-color: #0d6efd; transform: scale(1.04); box-shadow: 0 12px 40px rgba(13,110,253,.2); }
.pricing-card.popular:hover { transform: scale(1.04) translateY(-6px); }
.pricing-header { padding: 30px 24px 24px; text-align: center; }
.pricing-header.popular-hdr { background: linear-gradient(135deg, #0d6efd, #6610f2); color: #fff; }
.pricing-price { font-size: 2.6rem; font-weight: 800; line-height: 1; }
.pricing-cycle { font-size: .85rem; opacity: .7; }
.popular-badge { background: #fff; color: #0d6efd; font-size: .72rem; font-weight: 700;
    padding: 3px 12px; border-radius: 20px; display: inline-block; margin-bottom: 10px;
    text-transform: uppercase; letter-spacing: .5px; }
.pricing-features { padding: 20px 24px; list-style: none; margin: 0; }
.pricing-features li { padding: 7px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; gap: 10px; font-size: .93rem; }
.pricing-features li:last-child { border-bottom: none; }
.pricing-features .check { color: #198754; font-size: 1rem; }
.pricing-footer { padding: 20px 24px 28px; text-align: center; }
.cycle-toggle .btn { border-radius: 20px; padding: 6px 22px; font-weight: 600; }
</style>

<section class="pricing-section">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="fw-bold mb-2" style="font-size:2.4rem;">Simple, Transparent Pricing</h1>
            <p class="text-muted fs-5">Choose the plan that fits your needs. Upgrade or cancel anytime.</p>

            <!-- Billing Cycle Toggle -->
            <div class="d-inline-flex gap-1 mt-3 cycle-toggle">
                <a href="?cycle=monthly"   class="btn <?php echo $cycle === 'monthly'   ? 'btn-primary'         : 'btn-outline-secondary'; ?>">Monthly</a>
                <a href="?cycle=quarterly" class="btn <?php echo $cycle === 'quarterly' ? 'btn-primary'         : 'btn-outline-secondary'; ?>">Quarterly</a>
                <a href="?cycle=yearly"    class="btn <?php echo $cycle === 'yearly'    ? 'btn-primary'         : 'btn-outline-secondary'; ?>">
                    Yearly <span class="badge bg-success ms-1" style="font-size:.7rem;">Save 20%</span>
                </a>
            </div>
        </div>

        <?php
        // Filter plans by selected cycle (show all if no match)
        $filtered = array_filter($plans, fn($p) => $p['billing_cycle'] === $cycle);
        if (empty($filtered)) $filtered = $plans; // fallback: show all
        $filtered = array_values($filtered);
        ?>

        <?php if (empty($filtered)): ?>
        <div class="alert alert-info text-center">No plans available yet. Please check back soon!</div>
        <?php else: ?>
        <div class="row justify-content-center g-4">
            <?php foreach ($filtered as $plan): ?>
            <?php
                $features  = json_decode($plan['features'] ?? '[]', true) ?: [];
                $isPopular = (bool)$plan['is_popular'];
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="pricing-card <?php echo $isPopular ? 'popular' : ''; ?> h-100 d-flex flex-column">
                    <div class="pricing-header <?php echo $isPopular ? 'popular-hdr' : ''; ?>">
                        <?php if ($isPopular): ?>
                        <div class="popular-badge"><i class="bi bi-star-fill me-1"></i>Most Popular</div>
                        <?php endif; ?>
                        <h3 class="fw-bold mb-1"><?php echo h($plan['name']); ?></h3>
                        <?php if (!empty($plan['description'])): ?>
                        <p class="small mb-3 <?php echo $isPopular ? 'text-white-50' : 'text-muted'; ?>"><?php echo h($plan['description']); ?></p>
                        <?php endif; ?>
                        <div class="pricing-price">
                            <span style="font-size:1.1rem;vertical-align:top;margin-top:10px;display:inline-block;"><?php echo h($plan['currency']); ?></span><?php echo number_format((float)$plan['price'], 0); ?>
                        </div>
                        <div class="pricing-cycle mt-1">/ <?php echo ucfirst($plan['billing_cycle']); ?></div>
                    </div>

                    <?php if (!empty($features)): ?>
                    <ul class="pricing-features flex-grow-1">
                        <?php foreach ($features as $feature): ?>
                        <li><i class="bi bi-check-circle-fill check"></i><?php echo h($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="flex-grow-1"></div>
                    <?php endif; ?>

                    <div class="pricing-footer">
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/client/subscribe.php?plan=<?php echo (int)$plan['id']; ?>"
                           class="btn <?php echo $isPopular ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg w-100 fw-bold">
                            Get Started <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <?php else: ?>
                        <a href="/login.php?redirect=/client/subscribe.php?plan=<?php echo (int)$plan['id']; ?>"
                           class="btn <?php echo $isPopular ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg w-100 fw-bold">
                            Get Started <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                        <small class="text-muted d-block mt-2">
                            <a href="/register.php" class="text-decoration-none">New? Create account →</a>
                        </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Trust badges -->
        <div class="text-center mt-5 text-muted small">
            <i class="bi bi-shield-lock me-1"></i>Secure payments via Stripe &amp; PayPal &nbsp;|&nbsp;
            <i class="bi bi-arrow-repeat me-1"></i>Cancel anytime &nbsp;|&nbsp;
            <i class="bi bi-headset me-1"></i>24/7 Support
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
