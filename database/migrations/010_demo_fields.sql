-- Live Demo Project Checking System Migration
-- Adds subdomain-based demo fields to the projects table

ALTER TABLE `projects` ADD COLUMN `demo_subdomain` VARCHAR(100) NULL AFTER `currency`;
ALTER TABLE `projects` ADD COLUMN `demo_url` VARCHAR(500) NULL AFTER `demo_subdomain`;
ALTER TABLE `projects` ADD COLUMN `demo_enabled` TINYINT DEFAULT 0 AFTER `demo_url`;
ALTER TABLE `projects` ADD COLUMN `demo_password` VARCHAR(255) NULL AFTER `demo_enabled`;
ALTER TABLE `projects` ADD COLUMN `demo_expires_at` DATETIME NULL AFTER `demo_password`;
