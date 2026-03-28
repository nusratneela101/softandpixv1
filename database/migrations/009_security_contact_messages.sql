-- Migration 009: Security, Contact Messages, Internal Messages, Invoice Emails, Rate Limits

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_replied TINYINT(1) DEFAULT 0,
    replied_at DATETIME DEFAULT NULL,
    replied_by INT DEFAULT NULL,
    reply_message TEXT DEFAULT NULL,
    status ENUM('new','read','replied','archived') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_date (created_at)
);

CREATE TABLE IF NOT EXISTS internal_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    recipient_id INT NOT NULL,
    project_id INT DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    parent_id INT DEFAULT NULL COMMENT 'for threaded replies',
    is_deleted_sender TINYINT(1) DEFAULT 0,
    is_deleted_recipient TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id, is_read),
    INDEX idx_project (project_id),
    INDEX idx_parent (parent_id)
);

CREATE TABLE IF NOT EXISTS invoice_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    sent_to VARCHAR(255) NOT NULL,
    sent_by INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT,
    pdf_path VARCHAR(500) DEFAULT NULL,
    status ENUM('sent','failed') DEFAULT 'sent',
    error_message TEXT DEFAULT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice (invoice_id)
);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip_action (ip_address, action),
    INDEX idx_ip (ip_address)
);
