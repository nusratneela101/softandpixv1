-- File Manager Migration
-- Run this in phpMyAdmin on Namecheap

CREATE TABLE IF NOT EXISTS project_folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_parent (parent_id)
);

CREATE TABLE IF NOT EXISTS project_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    folder_id INT DEFAULT NULL,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    file_extension VARCHAR(20) DEFAULT NULL,
    version INT DEFAULT 1,
    parent_file_id INT DEFAULT NULL COMMENT 'previous version',
    description TEXT,
    download_count INT DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_folder (folder_id),
    INDEX idx_parent_file (parent_file_id)
);

CREATE TABLE IF NOT EXISTS file_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file (file_id)
);
