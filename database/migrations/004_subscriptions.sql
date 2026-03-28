-- Subscription Plans
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    billing_cycle ENUM('monthly','quarterly','yearly') DEFAULT 'monthly',
    features TEXT COMMENT 'JSON array of features',
    is_popular TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    stripe_price_id VARCHAR(255) DEFAULT NULL,
    paypal_plan_id VARCHAR(255) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subscriptions
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    payment_gateway ENUM('stripe','paypal','manual') NOT NULL,
    gateway_subscription_id VARCHAR(255) DEFAULT NULL,
    gateway_customer_id VARCHAR(255) DEFAULT NULL,
    status ENUM('active','past_due','cancelled','expired','trialing') DEFAULT 'active',
    current_period_start DATETIME,
    current_period_end DATETIME,
    cancelled_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_gateway_sub (gateway_subscription_id)
);

-- Subscription Payments
CREATE TABLE IF NOT EXISTS subscription_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_gateway ENUM('stripe','paypal','manual') NOT NULL,
    gateway_payment_id VARCHAR(255) DEFAULT NULL,
    status ENUM('succeeded','pending','failed','refunded') DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription (subscription_id),
    INDEX idx_user (user_id)
);
