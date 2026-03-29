<?php
/**
 * Activity Logger — logs system events to the activity_log table.
 */

/**
 * Log a user action to the activity_log table.
 *
 * @param PDO         $pdo
 * @param int|null    $user_id      The acting user's ID (null for system/anonymous actions)
 * @param string      $action       Short action label, e.g. 'login', 'project_created'
 * @param string      $details      Human-readable details about the action
 * @param string|null $entity_type  The type of entity affected, e.g. 'project', 'invoice'
 * @param int|null    $entity_id    The primary key of the affected entity
 */
function log_activity($pdo, $user_id, $action, $details, $entity_type = null, $entity_id = null) {
    if (!$pdo) return;
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? null;
        // Take only the first IP when behind a proxy
        if ($ip && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        // Truncate to column limit
        if ($ip && strlen($ip) > 45) $ip = substr($ip, 0, 45);
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        $stmt = $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, details, entity_type, entity_id, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id ?: null, $action, $details, $entity_type, $entity_id ?: null, $ip, $ua]);
    } catch (Exception $e) {
        error_log('activity_logger error: ' . $e->getMessage());
    }
}
