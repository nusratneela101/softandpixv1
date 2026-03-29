<?php
/**
 * Notification Manager — push notification helpers
 */

function _ensure_notifications_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info','warning','success','danger') DEFAULT 'info',
        link VARCHAR(500) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function send_notification(PDO $pdo, int $user_id, string $title, string $message, string $type = 'info', ?string $link = null, ?int $created_by = null): int|false {
    try {
        _ensure_notifications_table($pdo);
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $title, $message, $type, $link, $created_by]);
        $id = (int)$pdo->lastInsertId();

        // Also dispatch a web push notification if push_helper is available
        if (function_exists('send_push_notification')) {
            @send_push_notification($pdo, $user_id, $title, $message, $link);
        }

        return $id;
    } catch (Exception $e) {
        return false;
    }
}

function send_bulk_notification(PDO $pdo, array $user_ids, string $title, string $message, string $type = 'info', ?string $link = null, ?int $created_by = null): int {
    $count = 0;
    foreach ($user_ids as $uid) {
        if (send_notification($pdo, (int)$uid, $title, $message, $type, $link, $created_by) !== false) {
            $count++;
        }
    }
    return $count;
}

function get_unread_count(PDO $pdo, int $user_id): int {
    try {
        _ensure_notifications_table($pdo);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function get_notifications(PDO $pdo, int $user_id, int $limit = 20, int $offset = 0): array {
    try {
        _ensure_notifications_table($pdo);
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function mark_as_read(PDO $pdo, int $notification_id, int $user_id): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function mark_all_read(PDO $pdo, int $user_id): bool {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
