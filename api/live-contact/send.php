<?php
/**
 * api/live-contact/send.php
 * POST: Send a message in a live contact session
 * Guest authenticates via session_token; admin via $_SESSION['admin_id']
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$contactId    = (int)($_POST['contact_id'] ?? 0);
$message      = trim($_POST['message'] ?? '');
$sessionToken = trim($_POST['session_token'] ?? '');

if (!$contactId) {
    echo json_encode(['success' => false, 'error' => 'contact_id required']);
    exit;
}
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
    exit;
}
if (strlen($message) > 5000) {
    echo json_encode(['success' => false, 'error' => 'Message too long (max 5000 chars)']);
    exit;
}

$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

try {
    // Fetch the contact record
    $stmt = $pdo->prepare("SELECT * FROM live_contacts WHERE id = ? AND status != 'closed' LIMIT 1");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch();

    if (!$contact) {
        echo json_encode(['success' => false, 'error' => 'Contact session not found or closed']);
        exit;
    }

    // Determine sender
    $isAdmin      = isset($_SESSION['admin_id']);
    $isGuest      = !empty($sessionToken) && hash_equals($contact['session_token'] ?? '', $sessionToken);
    $isLoggedUser = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$contact['user_id'];

    if (!$isAdmin && !$isGuest && !$isLoggedUser) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // Rate limiting for guests: max 1 message per 3 seconds
    if (!$isAdmin) {
        $recentStmt = $pdo->prepare(
            "SELECT created_at FROM live_contact_messages
             WHERE contact_id = ? AND sender_type = 'guest'
             ORDER BY created_at DESC LIMIT 1"
        );
        $recentStmt->execute([$contactId]);
        $recentMsg = $recentStmt->fetch();
        if ($recentMsg) {
            $elapsed = time() - strtotime($recentMsg['created_at']);
            if ($elapsed < 3) {
                echo json_encode(['success' => false, 'error' => 'Please wait a moment before sending another message']);
                exit;
            }
        }
    }

    $senderType = $isAdmin ? 'admin' : 'guest';
    $senderId   = $isAdmin ? (int)$_SESSION['admin_id'] : ((int)($contact['user_id'] ?? 0) ?: null);

    // Insert message
    $insertStmt = $pdo->prepare(
        "INSERT INTO live_contact_messages (contact_id, sender_type, sender_id, message)
         VALUES (?, ?, ?, ?)"
    );
    $insertStmt->execute([$contactId, $senderType, $senderId, $message]);
    $messageId = (int)$pdo->lastInsertId();

    // Update contact status to chatting (if new) and updated_at
    $pdo->prepare(
        "UPDATE live_contacts SET status = IF(status='new','chatting',status), updated_at = NOW() WHERE id = ?"
    )->execute([$contactId]);

    // Create notification for the other party
    if ($isAdmin) {
        // Notify the client user
        if ($contact['user_id']) {
            createNotification(
                $pdo,
                (int)$contact['user_id'],
                'live_chat',
                'New message from Support',
                'You have a new message in your live chat.',
                '/client/chat.php'
            );
        }
    } else {
        // Notify admin
        try {
            $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            foreach ($admins as $admin) {
                createNotification(
                    $pdo,
                    $admin['id'],
                    'live_contact',
                    'New message from ' . htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'),
                    substr($message, 0, 100),
                    '/admin/live_contact_chat.php?id=' . $contactId
                );
            }
        } catch (Exception $e) {}
    }

    echo json_encode(['success' => true, 'message_id' => $messageId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
