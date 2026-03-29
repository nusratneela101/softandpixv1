<?php
/**
 * Push Notification API Endpoints
 *
 * Actions:
 *   subscribe    – Save push subscription
 *   unsubscribe  – Remove push subscription
 *   vapid_key    – Get VAPID public key
 *   status       – Get push status for current user
 *   test         – Send a test push notification
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/push_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// vapid_key endpoint is public (no auth required)
if ($action === 'vapid_key') {
    $config = _load_vapid_config();
    echo json_encode([
        'success'    => true,
        'public_key' => $config['vapid_public_key'],
        'enabled'    => $config['enabled'] && !empty($config['vapid_public_key']),
    ]);
    exit;
}

// All other actions require authentication
require_login();
$userId = (int)$_SESSION['user_id'];

switch ($action) {
    case 'subscribe':
        handleSubscribe($pdo, $userId);
        break;
    case 'unsubscribe':
        handleUnsubscribe($pdo, $userId);
        break;
    case 'status':
        handleStatus($pdo, $userId);
        break;
    case 'test':
        handleTest($pdo, $userId);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// ── Handlers ───────────────────────────────────────────────────────────────────

function handleSubscribe(PDO $pdo, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true);

    $endpoint = $input['endpoint'] ?? '';
    $p256dh   = $input['keys']['p256dh'] ?? '';
    $auth     = $input['keys']['auth'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    if (empty($endpoint) || empty($p256dh) || empty($auth)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing subscription data']);
        return;
    }

    $success = save_push_subscription($pdo, $userId, $endpoint, $p256dh, $auth, $userAgent);
    echo json_encode(['success' => $success]);
}

function handleUnsubscribe(PDO $pdo, int $userId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    $endpoint = $input['endpoint'] ?? '';

    if (empty($endpoint)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing endpoint']);
        return;
    }

    $success = remove_push_subscription($pdo, $userId, $endpoint);
    echo json_encode(['success' => $success]);
}

function handleStatus(PDO $pdo, int $userId): void {
    $config       = _load_vapid_config();
    $subscriptions = get_user_subscriptions($pdo, $userId);

    echo json_encode([
        'success'            => true,
        'enabled'            => $config['enabled'] && !empty($config['vapid_public_key']),
        'subscribed'         => count($subscriptions) > 0,
        'subscription_count' => count($subscriptions),
    ]);
}

function handleTest(PDO $pdo, int $userId): void {
    $sent = send_push_notification(
        $pdo,
        $userId,
        'Test Notification',
        'This is a test push notification from SoftandPix!',
        BASE_URL . '/',
        '/public/assets/icons/icon-192x192.png'
    );

    echo json_encode(['success' => true, 'sent' => $sent]);
}
