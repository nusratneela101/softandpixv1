-- Migration 002: Conversation-based Live Chat System
-- Replaces the room-based chat with a full conversation/participant model.
-- WARNING: This drops the old room-based tables. Back up data first if needed.

-- Remove old room-based tables
DROP TABLE IF EXISTS `chat_room_members`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_rooms`;

-- New messages table (conversation-based)
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `sender_id` INT NOT NULL COMMENT '0 = admin',
    `message` TEXT,
    `message_type` ENUM('text','file','image','link','system') DEFAULT 'text',
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `is_deleted` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_conv_created` (`conversation_id`, `created_at`),
    INDEX `idx_sender` (`sender_id`),
    INDEX `idx_unread` (`conversation_id`, `is_read`, `sender_id`)
);

-- New conversation table (replaces chat_rooms)
CREATE TABLE IF NOT EXISTS `chat_conversations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT DEFAULT NULL,
    `type` ENUM('project','direct','support') DEFAULT 'project',
    `title` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_project` (`project_id`)
);

-- Participants per conversation (replaces chat_room_members)
CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `conversation_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT '0 = admin',
    `role` ENUM('admin','developer','client') DEFAULT 'client',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_read_at` TIMESTAMP NULL,
    `is_typing` TINYINT(1) DEFAULT 0,
    `typing_updated_at` TIMESTAMP NULL,
    UNIQUE KEY `unique_conv_user` (`conversation_id`, `user_id`),
    INDEX `idx_user` (`user_id`)
);
