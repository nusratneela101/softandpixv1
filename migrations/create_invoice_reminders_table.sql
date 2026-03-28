CREATE TABLE IF NOT EXISTS `invoice_reminders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `sent_to` VARCHAR(255) NOT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoice_id` (`invoice_id`),
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
