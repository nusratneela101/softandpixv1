<?php
header('Content-Type: application/json');

require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO newsletter (email) VALUES (?)");
    $stmt->execute([$email]);
    echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'This email is already subscribed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Subscription failed. Please try again.']);
    }
}
