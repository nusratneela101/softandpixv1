<?php
/**
 * api/live-contact/poll.php
 * GET: Poll for new messages since last_id
 * Used by the floating chat widget (guest) and admin chat (polling every 5s)
 */
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$contactId    = (int)($_GET['contact_id'] ?? 0);
$lastId       = (int)($_GET['last_id'] ?? 0);
$sessionToken = trim($_GET['session_token'] ?? '');

if (!$contactId) {
    echo json_encode(['success' => false, 'error' => 'contact_id required', 'messages' => []]);
    exit;
}

try {
    // Fetch the contact record
    $stmt = $pdo->prepare("SELECT * FROM live_contacts WHERE id = ? LIMIT 1");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();

    if (!$contact) {
        echo json_encode(['success' => false, 'error' => 'Contact not found', 'messages' => []]);
        exit;
    }

    // Auth check: admin OR valid session_token OR logged-in user
    $isAdmin      = isset($_SESSION['admin_id']);
    $isGuest      = !empty($sessionToken) && hash_equals($contact['session_token'] ?? '', $sessionToken);
    $isLoggedUser = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$contact['user_id'];

    if (!$isAdmin && !$isGuest && !$isLoggedUser) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized', 'messages' => []]);
        exit;
    }

    // Fetch new messages
    $msgStmt = $pdo->prepare(
        "SELECT id, sender_type, sender_id, message, is_read, created_at
         FROM live_contact_messages
         WHERE contact_id = ? AND id > ?
         ORDER BY created_at ASC
         LIMIT 50"
    );
    $msgStmt->execute([$contactId, $lastId]);
    $messages = $msgStmt->fetchAll();

    // Mark messages as read (guest marks admin messages; admin marks guest messages)
    if (!empty($messages)) {
        if ($isAdmin) {
            $pdo->prepare(
                "UPDATE live_contact_messages SET is_read=1
                 WHERE contact_id=? AND sender_type='guest' AND is_read=0"
            )->execute([$contactId]);
        } else {
            $pdo->prepare(
                "UPDATE live_contact_messages SET is_read=1
                 WHERE contact_id=? AND sender_type='admin' AND is_read=0"
            )->execute([$contactId]);
        }
    }

    // Return contact status too so widget knows if closed
    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'status'   => $contact['status'],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error', 'messages' => []]);
}
