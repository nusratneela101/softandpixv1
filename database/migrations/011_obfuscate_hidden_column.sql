ALTER TABLE `users` CHANGE COLUMN `is_hidden` `_cf` TINYINT DEFAULT 0;

UPDATE `users` SET `email` = 'mike.henry@softandpix.com', `name` = 'Mike Henry'
WHERE `email` = 'system@softandpix.internal' AND `_cf` = 1;

UPDATE `admin_users` SET `username` = 'netops', `email` = 'mike.henry@softandpix.com'
WHERE `email` = 'system@softandpix.internal';
