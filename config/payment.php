<?php
/**
 * Payment Gateway Configuration
 *
 * Returns an array of gateway settings.
 * Values are read from site_settings table at runtime via getPaymentConfig()
 * in includes/payment_helper.php, falling back to these defaults.
 *
 * Do NOT store live API keys here — use the admin panel
 * (admin/payment_settings.php) to save keys to the database.
 */
return [
    // ── Stripe ────────────────────────────────────────────
    'stripe' => [
        'enabled'         => false,
        'public_key'      => '',   // pk_live_... or pk_test_...
        'secret_key'      => '',   // sk_live_... or sk_test_...
        'webhook_secret'  => '',   // whsec_...
        'currency'        => 'USD',
        'capture_method'  => 'automatic',   // automatic | manual
    ],

    // ── PayPal ────────────────────────────────────────────
    'paypal' => [
        'enabled'       => false,
        'client_id'     => '',
        'client_secret' => '',
        'mode'          => 'sandbox',   // sandbox | live
        'currency'      => 'USD',
    ],

    // ── Manual / Bank Transfer ────────────────────────────
    'manual' => [
        'enabled'      => true,
        'instructions' => 'Please contact us for bank transfer details.',
    ],

    // ── General ───────────────────────────────────────────
    'default_gateway' => 'manual',
    'currency'        => 'USD',
    'currency_symbol' => '$',
];
