-- Progress tracking migration
-- Safe to run multiple times (IF NOT EXISTS / IF NOT EXISTS column)

ALTER TABLE projects ADD COLUMN IF NOT EXISTS progress_percent INT DEFAULT 0;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS progress_auto_calculate TINYINT(1) DEFAULT 1;

CREATE TABLE IF NOT EXISTS project_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE DEFAULT NULL,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    sort_order INT DEFAULT 0,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id)
);

CREATE TABLE IF NOT EXISTS project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    milestone_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('todo','in_progress','review','completed') DEFAULT 'todo',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    estimated_hours DECIMAL(5,1) DEFAULT NULL,
    actual_hours DECIMAL(5,1) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    sort_order INT DEFAULT 0,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_milestone (milestone_id),
    INDEX idx_assigned (assigned_to),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS project_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id, created_at)
);

CREATE TABLE IF NOT EXISTS project_daily_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    log_date DATE NOT NULL,
    hours_worked DECIMAL(5,1) DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_date (project_id, user_id, log_date),
    INDEX idx_project_date (project_id, log_date)
);
