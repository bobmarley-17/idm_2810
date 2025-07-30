-- Add new columns to defunct_users table
ALTER TABLE `defunct_users` 
ADD COLUMN `user_id` int(11) NOT NULL AFTER `id`,
ADD COLUMN `source_id` int(11) NOT NULL AFTER `user_id`,
MODIFY COLUMN `status` enum('pending','deleted') DEFAULT 'pending',
ADD UNIQUE KEY `user_source_unique` (`user_id`, `source_id`),
ADD KEY `source_id` (`source_id`),
ADD CONSTRAINT `defunct_users_source_fk` FOREIGN KEY (`source_id`) REFERENCES `account_sources` (`id`) ON DELETE CASCADE;
