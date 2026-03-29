<?php
/**
 * API Helper — shared utilities for the REST API.
 */

if (!defined('API_HELPER_LOADED')) {
    define('API_HELPER_LOADED', true);
}

// HMAC key: prefer APP_SECRET constant, fall back to env variable, then a hard-coded fallback.
if (!defined('APP_SECRET')) {
    define('APP_SECRET', getenv('APP_SECRET') ?: 'softandpix-default-secret-change-in-production');
}

define('API_TOKEN_TTL', 86400 * 30); // 30 days

// ---------------------------------------------------------------------------
// CORS
// ---------------------------------------------------------------------------

function set_cors_headers(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}

// ---------------------------------------------------------------------------
// Response helpers
// ---------------------------------------------------------------------------

function json_response(array $data, int $status_code = 200): never {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(string $message, int $status_code = 400): never {
    json_response(['success' => false, 'error' => $message], $status_code);
}

// ---------------------------------------------------------------------------
// Token generation / validation
// ---------------------------------------------------------------------------

function generate_api_token(int $user_id, string $email): string {
    $issued_at  = time();
    $expires_at = $issued_at + API_TOKEN_TTL;

    $payload = json_encode([
        'user_id'    => $user_id,
        'email'      => $email,
        'issued_at'  => $issued_at,
        'expires_at' => $expires_at,
    ]);

    $encoded   = base64url_encode($payload);
    $signature = base64url_encode(hash_hmac('sha256', $encoded, APP_SECRET, true));

    return $encoded . '.' . $signature;
}

/**
 * Validates a token.
 *
 * @return array|false  User data array on success, false on failure.
 */
function validate_api_token(string $token): array|false {
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }

    [$encoded, $provided_sig] = $parts;
    $expected_sig = base64url_encode(hash_hmac('sha256', $encoded, APP_SECRET, true));

    if (!hash_equals($expected_sig, $provided_sig)) {
        return false;
    }

    $payload = json_decode(base64url_decode($encoded), true);
    if (!is_array($payload)) {
        return false;
    }

    if (empty($payload['expires_at']) || time() > $payload['expires_at']) {
        return false;
    }

    return $payload;
}

/**
 * Extracts the Bearer token from the Authorization header and validates it.
 * Calls api_error(401) if the token is missing or invalid.
 *
 * @return array  Validated token payload (user_id, email, issued_at, expires_at).
 */
function get_api_user(): array {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Some SAPI / webservers pass the header differently.
    if (empty($auth_header) && function_exists('apache_request_headers')) {
        $headers     = apache_request_headers();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (empty($auth_header) || !str_starts_with($auth_header, 'Bearer ')) {
        api_error('Authentication required. Provide a valid Bearer token.', 401);
    }

    $token = substr($auth_header, 7);
    $user  = validate_api_token($token);

    if ($user === false) {
        api_error('Invalid or expired token.', 401);
    }

    return $user;
}

// ---------------------------------------------------------------------------
// Rate limiting (DB-backed via rate_limits table)
// ---------------------------------------------------------------------------

/**
 * Simple API rate-limiting wrapper.
 * Calls api_error(429) when the limit is exceeded.
 *
 * @param PDO    $pdo
 * @param string $action  Unique action label (e.g. 'api_login').
 * @param int    $max     Maximum requests allowed in the window.
 * @param int    $window  Window duration in seconds.
 */
function api_rate_limit(PDO $pdo, string $action, int $max = 60, int $window = 60): void {
    $ip           = get_api_client_ip();
    $window_start = date('Y-m-d H:i:s', time() - $window);
    $now          = date('Y-m-d H:i:s');

    try {
        $pdo->prepare("DELETE FROM rate_limits WHERE first_attempt < ?")->execute([$window_start]);

        $stmt = $pdo->prepare(
            "SELECT id, attempts, first_attempt FROM rate_limits
             WHERE ip_address = ? AND action = ? AND first_attempt >= ?"
        );
        $stmt->execute([$ip, $action, $window_start]);
        $row = $stmt->fetch();

        if (!$row) {
            $pdo->prepare(
                "INSERT INTO rate_limits (ip_address, action, attempts, first_attempt, last_attempt)
                 VALUES (?, ?, 1, ?, ?)"
            )->execute([$ip, $action, $now, $now]);
            return;
        }

        if ((int)$row['attempts'] >= $max) {
            $retry_after = $window - (time() - strtotime($row['first_attempt']));
            header('Retry-After: ' . max(0, $retry_after));
            api_error('Rate limit exceeded. Please try again later.', 429);
        }

        $pdo->prepare(
            "UPDATE rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE id = ?"
        )->execute([$now, $row['id']]);
    } catch (Exception $e) {
        error_log('api_rate_limit error: ' . $e->getMessage());
        // Fail open — do not block the request on DB errors.
    }
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function get_api_client_ip(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return strlen($ip) > 45 ? substr($ip, 0, 45) : $ip;
}
