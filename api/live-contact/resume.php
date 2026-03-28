<?php
/**
 * api/live-contact/resume.php
 * POST: Resume an existing live contact session using session_token from localStorage
 * Returns contact info and recent messages
 */
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$sessionToken = trim($_POST['session_token'] ?? '');

if (empty($sessionToken) || strlen($sessionToken) !== 64) {
    echo json_encode(['success' => false, 'error' => 'Invalid session token']);
    exit;
}

try {
    // Find the contact by session token
    $stmt = $pdo->prepare("SELECT * FROM live_contacts WHERE session_token = ? LIMIT 1");
    $stmt->execute([$sessionToken]);
    $contact = $stmt->fetch();

    if (!$contact) {
        echo json_encode(['success' => false, 'error' => 'Session not found']);
        exit;
    }

    // Fetch last 50 messages
    $msgStmt = $pdo->prepare(
        "SELECT id, sender_type, sender_id, message, is_read, created_at
         FROM live_contact_messages
         WHERE contact_id = ?
         ORDER BY created_at ASC
         LIMIT 50"
    );
    $msgStmt->execute([$contact['id']]);
    $messages = $msgStmt->fetchAll();

    // Mark admin messages as read since guest is now viewing
    $pdo->prepare(
        "UPDATE live_contact_messages SET is_read=1
         WHERE contact_id=? AND sender_type='admin' AND is_read=0"
    )->execute([$contact['id']]);

    echo json_encode([
        'success'    => true,
        'contact_id' => (int)$contact['id'],
        'name'       => htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'),
        'email'      => htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'),
        'status'     => $contact['status'],
        'messages'   => $messages,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
