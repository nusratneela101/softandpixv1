-- Video Call & Meeting System Tables
-- Run this SQL to create all required tables for the video call feature.

-- Video/Meeting rooms
CREATE TABLE IF NOT EXISTS video_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(20) UNIQUE NOT NULL,
    room_name VARCHAR(255) NOT NULL,
    room_type ENUM('instant','scheduled','recurring') DEFAULT 'instant',
    created_by INT NOT NULL,
    max_participants INT DEFAULT 6,
    status ENUM('waiting','active','ended','cancelled') DEFAULT 'waiting',
    is_recording TINYINT(1) DEFAULT 0,
    scheduled_at DATETIME NULL,
    scheduled_end DATETIME NULL,
    recurrence_rule VARCHAR(100) NULL,
    description TEXT NULL,
    password VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_created_by (created_by)
);

-- Room participants
CREATE TABLE IF NOT EXISTS video_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    is_muted TINYINT(1) DEFAULT 0,
    is_video_on TINYINT(1) DEFAULT 1,
    is_screen_sharing TINYINT(1) DEFAULT 0,
    role ENUM('host','co-host','participant') DEFAULT 'participant',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME NULL,
    FOREIGN KEY (room_id) REFERENCES video_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active (room_id, user_id, left_at),
    INDEX idx_room (room_id),
    INDEX idx_user (user_id)
);

-- WebRTC signaling messages
CREATE TABLE IF NOT EXISTS video_signals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    from_user INT NOT NULL,
    to_user INT NOT NULL,
    signal_type ENUM('offer','answer','ice-candidate','join','leave','mute','unmute','screen-start','screen-stop') NOT NULL,
    signal_data LONGTEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_to_room (to_user, room_id, is_read),
    INDEX idx_created (created_at)
);

-- In-call text chat
CREATE TABLE IF NOT EXISTS video_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES video_rooms(id) ON DELETE CASCADE,
    INDEX idx_room_time (room_id, created_at)
);

-- Meeting invitations (for email notifications)
CREATE TABLE IF NOT EXISTS meeting_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    invited_by INT NOT NULL,
    invited_user_id INT NULL,
    invited_email VARCHAR(255) NULL,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    email_sent TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES video_rooms(id) ON DELETE CASCADE,
    INDEX idx_room (room_id),
    INDEX idx_user (invited_user_id)
);
