<?php
/**
 * API — Extend Session
 * Resets the session idle timer by touching the session.
 */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

// Touch the session to reset the idle timer
$_SESSION['last_extended'] = time();

echo json_encode(['success' => true, 'extended_at' => date('Y-m-d H:i:s')]);
