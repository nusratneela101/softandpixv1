<?php
/**
 * Rate Limiter for SoftandPix
 * Tracks attempts by IP + action in database
 */

/**
 * Check rate limit for a given action and IP.
 *
 * @param PDO    $pdo
 * @param string $action          e.g. 'login', 'password_reset', 'api'
 * @param int    $max_attempts    Max allowed attempts in the window
 * @param int    $window_minutes  Time window in minutes
 * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int (seconds)]
 */
function check_rate_limit(PDO $pdo, string $action, int $max_attempts = 5, int $window_minutes = 15): array {
    $ip = get_client_ip();
    $now = date('Y-m-d H:i:s');
    $window_start = date('Y-m-d H:i:s', time() - ($window_minutes * 60));

    try {
        // Clean up old records
        $pdo->prepare("DELETE FROM rate_limits WHERE first_attempt < ?")->execute([$window_start]);

        $stmt = $pdo->prepare(
            "SELECT id, attempts, first_attempt FROM rate_limits WHERE ip_address = ? AND action = ? AND first_attempt >= ?"
        );
        $stmt->execute([$ip, $action, $window_start]);
        $row = $stmt->fetch();

        if (!$row) {
            // First attempt
            $pdo->prepare(
                "INSERT INTO rate_limits (ip_address, action, attempts, first_attempt, last_attempt) VALUES (?, ?, 1, ?, ?)"
            )->execute([$ip, $action, $now, $now]);
            return ['allowed' => true, 'remaining' => $max_attempts - 1, 'retry_after' => 0];
        }

        $attempts = (int)$row['attempts'];

        if ($attempts >= $max_attempts) {
            $retry_after = ($window_minutes * 60) - (time() - strtotime($row['first_attempt']));
            return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(0, $retry_after)];
        }

        // Increment attempts
        $pdo->prepare(
            "UPDATE rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE id = ?"
        )->execute([$now, $row['id']]);

        return ['allowed' => true, 'remaining' => $max_attempts - $attempts - 1, 'retry_after' => 0];
    } catch (Exception $e) {
        error_log('rate_limiter error: ' . $e->getMessage());
        // On error, allow the request (fail open)
        return ['allowed' => true, 'remaining' => $max_attempts, 'retry_after' => 0];
    }
}

/**
 * Reset rate limit for an IP + action (e.g., on successful login).
 */
function reset_rate_limit(PDO $pdo, string $action): void {
    $ip = get_client_ip();
    try {
        $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action = ?")->execute([$ip, $action]);
    } catch (Exception $e) {
        error_log('rate_limiter reset error: ' . $e->getMessage());
    }
}

/**
 * Get the client's IP address.
 */
function get_client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    if (strlen($ip) > 45) $ip = substr($ip, 0, 45);
    return $ip;
}
