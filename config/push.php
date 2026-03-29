<?php
/**
 * Web Push (VAPID) Configuration
 *
 * Generate keys with: php cli/generate_vapid_keys.php
 * Or use an online VAPID key generator
 */
return [
    'vapid_public_key'  => getenv('VAPID_PUBLIC_KEY') ?: '',
    'vapid_private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    'vapid_subject'     => getenv('VAPID_SUBJECT') ?: 'mailto:support@softandpix.com',
    'enabled'           => true,
    'ttl'               => 86400,   // Time to live in seconds (24h)
    'urgency'           => 'normal', // low, normal, high, very-low
    'batch_size'        => 50,      // How many push messages to send per batch
];
