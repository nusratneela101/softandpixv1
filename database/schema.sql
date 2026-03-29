-- SoftandPix Database Schema
-- Complete database structure

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','client','developer') NOT NULL DEFAULT 'client',
    phone VARCHAR(50),
    address TEXT,
    avatar VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    last_activity DATETIME,
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('direct','group') DEFAULT 'direct',
    name VARCHAR(255),
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conversation participants
CREATE TABLE IF NOT EXISTS conversation_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat messages
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT,
    message_type ENUM('text','agreement','system','ai') DEFAULT 'text',
    agreement_id INT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agreements
CREATE TABLE IF NOT EXISTS agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    created_by INT NOT NULL,
    client_id INT NOT NULL,
    project_name VARCHAR(255) NOT NULL,
    project_description TEXT,
    deadline DATE,
    total_budget DECIMAL(10,2),
    requirements TEXT,
    terms_conditions TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agreement_id INT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    client_id INT NOT NULL,
    developer_id INT,
    deadline DATE,
    budget DECIMAL(10,2),
    status ENUM('active','in_progress','completed','on_hold','cancelled') DEFAULT 'active',
    progress INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agreement_id) REFERENCES agreements(id),
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (developer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project files
CREATE TABLE IF NOT EXISTS project_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project progress updates
CREATE TABLE IF NOT EXISTS progress_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    update_text TEXT,
    progress_percent INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    project_id INT,
    subtotal DECIMAL(10,2),
    tax_percent DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    due_date DATE,
    notes TEXT,
    status ENUM('pending','paid','overdue','cancelled') DEFAULT 'pending',
    sent_at DATETIME,
    paid_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (project_id) REFERENCES projects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice items
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(500),
    quantity INT DEFAULT 1,
    rate DECIMAL(10,2),
    amount DECIMAL(10,2),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    client_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    gateway ENUM('square','stripe','paypal') NOT NULL,
    transaction_id VARCHAR(255),
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    gateway_response TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment gateway settings
CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway_name VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 0,
    api_key VARCHAR(500),
    api_secret VARCHAR(500),
    sandbox_mode TINYINT(1) DEFAULT 1,
    extra_config TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Emails (web email system)
CREATE TABLE IF NOT EXISTS emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT,
    from_email VARCHAR(255),
    to_user_id INT,
    to_email VARCHAR(255),
    subject VARCHAR(500),
    body TEXT,
    attachments TEXT,
    is_read TINYINT(1) DEFAULT 0,
    is_starred TINYINT(1) DEFAULT 0,
    is_draft TINYINT(1) DEFAULT 0,
    is_trash TINYINT(1) DEFAULT 0,
    folder ENUM('inbox','sent','draft','trash') DEFAULT 'inbox',
    smtp_account ENUM('info','support') DEFAULT 'support',
    sent_via_smtp TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email attachments
CREATE TABLE IF NOT EXISTS email_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id INT NOT NULL,
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size INT,
    FOREIGN KEY (email_id) REFERENCES emails(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- AI Chatbot rules
CREATE TABLE IF NOT EXISTS chatbot_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keywords VARCHAR(500),
    response TEXT,
    is_active TINYINT(1) DEFAULT 1,
    priority INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(100),
    title VARCHAR(255),
    message TEXT,
    link VARCHAR(500),
    is_read TINYINT(1) DEFAULT 0,
    email_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Site settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default payment gateways
INSERT INTO payment_gateways (gateway_name, is_active) VALUES 
('square', 0), ('stripe', 0), ('paypal', 0);

-- Default chatbot rules
INSERT INTO chatbot_rules (keywords, response, priority) VALUES
('hello,hi,hey,greetings', 'Hi! Welcome to SoftandPix! How can I help you today?', 10),
('price,cost,pricing,budget,how much', 'Our pricing depends on the project scope. An admin will join shortly to discuss your project details and provide a quote.', 8),
('project,work,build,create,develop', 'Great! We would love to help you with your project! Could you tell us more about what you need? An admin will be with you shortly.', 8),
('help,support,issue,problem', 'I am here to help! Please describe your issue and an admin will assist you shortly.', 7),
('thank,thanks,thank you', 'You are welcome! Is there anything else I can help you with?', 5),
('bye,goodbye,see you', 'Thank you for chatting with us! Have a great day!', 5);

-- Default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'SoftandPix'),
('site_url', ''),
('site_logo', ''),
('currency', 'USD'),
('currency_symbol', '$');

-- =====================================================
-- A) Password Reset Table
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- B) Task Management Tables
-- =====================================================
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','in_progress','completed','on_hold') DEFAULT 'pending',
    due_date DATE DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS task_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- D) Activity Log Table
-- =====================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- H) Multi-language: no DB table needed (files only)
-- =====================================================

-- =====================================================
-- K) Time Tracking Tables
-- =====================================================
CREATE TABLE IF NOT EXISTS time_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    project_id INT NOT NULL,
    task_id INT DEFAULT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME DEFAULT NULL,
    duration_minutes INT DEFAULT 0,
    is_manual TINYINT(1) DEFAULT 0,
    is_approved TINYINT(1) DEFAULT 0,
    approved_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_project (user_id, project_id),
    INDEX idx_date (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS active_timers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    project_id INT NOT NULL,
    task_id INT DEFAULT NULL,
    start_time DATETIME NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- M) Recurring Invoices Tables
-- =====================================================
CREATE TABLE IF NOT EXISTS recurring_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    project_id INT DEFAULT NULL,
    frequency ENUM('weekly','monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
    next_generate_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    status ENUM('active','paused','cancelled') DEFAULT 'active',
    line_items JSON NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_next_date (next_generate_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS recurring_invoice_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recurring_invoice_id INT NOT NULL,
    generated_invoice_id INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recurring_invoice_id) REFERENCES recurring_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- N) Theme Settings Table
-- =====================================================
CREATE TABLE IF NOT EXISTS theme_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================
-- O) Rate Limits Table
-- =====================================================
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action VARCHAR(100) NOT NULL,
    attempts INT DEFAULT 1,
    first_attempt DATETIME NOT NULL,
    last_attempt DATETIME NOT NULL,
    INDEX idx_ip_action (ip_address, action),
    INDEX idx_first_attempt (first_attempt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chat message translations (auto-translate feature)
CREATE TABLE IF NOT EXISTS message_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    source_lang VARCHAR(10) DEFAULT 'auto',
    target_lang VARCHAR(10) NOT NULL,
    original_text TEXT NOT NULL,
    translated_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_translation (message_id, target_lang),
    INDEX idx_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
