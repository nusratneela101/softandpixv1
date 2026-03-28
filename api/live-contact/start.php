<?php
/**
 * api/live-contact/start.php
 * POST: Start a new live contact session
 * Creates live_contacts record + auto-creates user account
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/email.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate
if (empty($name) || strlen($name) > 255) {
    echo json_encode(['success' => false, 'error' => 'Name is required (max 255 chars)']);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Valid email address is required']);
    exit;
}

$name    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$phone   = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

try {
    // Check for existing open contact with same session_token in localStorage (handled by resume.php)
    // Check if email already has an open/chatting contact
    $existingStmt = $pdo->prepare(
        "SELECT id, session_token FROM live_contacts WHERE email = ? AND status IN ('new','chatting') ORDER BY created_at DESC LIMIT 1"
    );
    $existingStmt->execute([$email]);
    $existing = $existingStmt->fetch();

    if ($existing) {
        // Return existing session so the widget can resume
        echo json_encode([
            'success'       => true,
            'contact_id'    => (int)$existing['id'],
            'session_token' => $existing['session_token'],
            'resumed'       => true,
        ]);
        exit;
    }

    // Generate a secure session token
    $sessionToken = bin2hex(random_bytes(32));

    // Auto-create user account if not already registered
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $userStmt->execute([$email]);
    $existingUser = $userStmt->fetch();

    $userId = null;
    $rawPassword = null;

    if (!$existingUser) {
        // Generate random 12-char password
        $rawPassword = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 12);
        $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

        $insertUser = $pdo->prepare(
            "INSERT INTO users (name, email, password, role, is_active, email_verified, created_at)
             VALUES (?, ?, ?, 'client', 1, 0, NOW())"
        );
        $insertUser->execute([$name, $email, $hashedPassword]);
        $userId = (int)$pdo->lastInsertId();
    } else {
        $userId = (int)$existingUser['id'];
    }

    // Create live_contacts record
    $insertContact = $pdo->prepare(
        "INSERT INTO live_contacts (name, email, phone, message, status, user_id, session_token, ip_address, user_agent)
         VALUES (?, ?, ?, ?, 'new', ?, ?, ?, ?)"
    );
    $insertContact->execute([$name, $email, $phone, $message, $userId, $sessionToken, $ip, $userAgent]);
    $contactId = (int)$pdo->lastInsertId();

    // Insert opening message if provided
    if (!empty($message)) {
        $pdo->prepare(
            "INSERT INTO live_contact_messages (contact_id, sender_type, sender_id, message) VALUES (?, 'guest', ?, ?)"
        )->execute([$contactId, $userId, $message]);

        // Update status to chatting
        $pdo->prepare("UPDATE live_contacts SET status='chatting' WHERE id=?")->execute([$contactId]);
    }

    // Create notification for admin(s)
    try {
        $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
        foreach ($admins as $admin) {
            createNotification(
                $pdo,
                $admin['id'],
                'live_contact',
                'New Live Chat Request',
                $name . ' (' . $email . ') has started a live chat.',
                '/admin/live_contacts.php?id=' . $contactId
            );
        }
    } catch (Exception $e) {}

    // Send welcome email with auto-generated password (only for new users)
    if ($rawPassword !== null) {
        $emailSubject = 'Your Softandpix Account — Live Chat Started';
        $emailBody = '
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
            <h2 style="color:#0d6efd;">Welcome to Softandpix!</h2>
            <p>Hi <strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p>An account has been created for you so you can continue chatting with our team.</p>
            <table style="background:#f8f9fa;padding:16px;border-radius:8px;width:100%;">
                <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td><strong>Password:</strong></td><td style="font-family:monospace;font-size:1.1em;">' . htmlspecialchars($rawPassword, ENT_QUOTES, 'UTF-8') . '</td></tr>
            </table>
            <p style="margin-top:16px;">You can sign in at any time to view your chat history and project updates.</p>
            <p style="color:#888;font-size:.85em;">If you did not initiate this chat, please ignore this email.</p>
        </div>';
        try {
            sendEmail($pdo, $email, $emailSubject, $emailBody);
        } catch (Exception $e) {}
    }

    echo json_encode([
        'success'       => true,
        'contact_id'    => $contactId,
        'session_token' => $sessionToken,
        'resumed'       => false,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
