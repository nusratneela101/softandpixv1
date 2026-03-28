-- Migration: Add demo_has_files column to projects table
-- Tracks whether uploaded demo files exist for a project

ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `demo_has_files` TINYINT DEFAULT 0 AFTER `demo_expires_at`;
