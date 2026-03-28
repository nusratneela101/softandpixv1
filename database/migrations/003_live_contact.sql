-- Live Contact System Migration
-- Creates tables for floating chat widget + guest auto-account system

CREATE TABLE IF NOT EXISTS live_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    message TEXT,
    status ENUM('new','chatting','converted','closed') DEFAULT 'new',
    assigned_admin_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL COMMENT 'linked user account after auto-creation',
    session_token VARCHAR(64) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_session (session_token),
    INDEX idx_email (email)
);

CREATE TABLE IF NOT EXISTS live_contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT NOT NULL,
    sender_type ENUM('guest','admin') NOT NULL,
    sender_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact (contact_id, created_at),
    INDEX idx_unread (contact_id, is_read, sender_type)
);
