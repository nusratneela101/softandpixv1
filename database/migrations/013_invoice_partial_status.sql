-- Migration: Add 'partial' status to invoices table
-- Allows tracking of partially paid invoices

ALTER TABLE `invoices`
    MODIFY COLUMN `status` ENUM('draft','sent','paid','overdue','cancelled','partial') DEFAULT 'draft';
