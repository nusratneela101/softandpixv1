<?php
/**
 * Cron: Clean up stale push subscriptions
 * Run daily via cron: 0 3 * * * php /path/to/cron/cleanup_push.php
 *
 * Removes subscriptions with too many delivery errors or that haven't
 * been used in over 90 days.
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/push_helper.php';

$removed = cleanup_stale_subscriptions($pdo, maxErrors: 3);

echo date('Y-m-d H:i:s') . " — Removed {$removed} stale push subscription(s)." . PHP_EOL;
