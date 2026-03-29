<?php
/**
 * API v1 — Auth endpoints
 *
 * POST   /api/v1/auth/login
 * POST   /api/v1/auth/register
 * POST   /api/v1/auth/forgot-password
 * GET    /api/v1/auth/me
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

// Find the 'auth' segment index and get the sub-action.
$auth_idx  = array_search('auth', $segments);
$sub_route = $segments[$auth_idx + 1] ?? '';

switch ($method . ':' . $sub_route) {
    case 'POST:login':
        handle_login($pdo);
        break;
    case 'POST:register':
        handle_register($pdo);
        break;
    case 'POST:forgot-password':
        handle_forgot_password($pdo);
        break;
    case 'GET:me':
        handle_me($pdo);
        break;
    default:
        api_error('Auth endpoint not found.', 404);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function handle_login(PDO $pdo): never {
    api_rate_limit($pdo, 'api_login', 10, 60);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        api_error('Email and password are required.');
    }

    $stmt = $pdo->prepare(
        "SELECT id, name, email, password, role, avatar, phone, is_active
         FROM users WHERE email = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        api_error('Invalid email or password.', 401);
    }

    if (!(int)$user['is_active']) {
        api_error('Your account has been deactivated.', 403);
    }

    // Update last_activity.
    $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$user['id']]);

    $token = generate_api_token((int)$user['id'], $user['email']);
    log_activity($pdo, $user['id'], 'api_login', 'User logged in via API');

    json_response([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'id'     => (int)$user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'avatar' => $user['avatar'],
            'phone'  => $user['phone'],
        ],
    ]);
}

function handle_register(PDO $pdo): never {
    api_rate_limit($pdo, 'api_register', 5, 60);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name     = trim($input['name'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $phone    = trim($input['phone'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        api_error('Name, email, and password are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('Invalid email address.');
    }

    if (strlen($password) < 8) {
        api_error('Password must be at least 8 characters.');
    }

    // Check for existing email.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        api_error('An account with this email already exists.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, phone, is_active, created_at)
         VALUES (?, ?, ?, 'client', ?, 1, NOW())"
    );
    $stmt->execute([$name, $email, $hash, $phone ?: null]);
    $user_id = (int)$pdo->lastInsertId();

    $token = generate_api_token($user_id, $email);
    log_activity($pdo, $user_id, 'api_register', 'New user registered via API');

    json_response([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'id'    => $user_id,
            'name'  => $name,
            'email' => $email,
            'role'  => 'client',
            'phone' => $phone ?: null,
        ],
    ], 201);
}

function handle_forgot_password(PDO $pdo): never {
    api_rate_limit($pdo, 'api_forgot_password', 5, 300);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($input['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        api_error('A valid email address is required.');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always return success to avoid email enumeration.
    if ($user) {
        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600);

        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
        $pdo->prepare(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
        )->execute([$email, $token, $expires_at]);

        log_activity($pdo, $user['id'], 'api_forgot_password', 'Password reset requested via API');
    }

    json_response([
        'success' => true,
        'message' => 'If an account exists for that email, a password reset link has been sent.',
    ]);
}

function handle_me(PDO $pdo): never {
    $api_user = get_api_user();

    $stmt = $pdo->prepare(
        "SELECT id, name, email, role, phone, address, avatar, is_active, last_activity, created_at
         FROM users WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$api_user['user_id']]);
    $user = $stmt->fetch();

    if (!$user || !(int)$user['is_active']) {
        api_error('User not found.', 404);
    }

    json_response(['success' => true, 'user' => $user]);
}
