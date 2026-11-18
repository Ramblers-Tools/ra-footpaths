-- 0.2.0: an inspector can have more than one website login.
CREATE TABLE IF NOT EXISTS `#__ra_footpaths_inspector_users` (
  `inspector_id` int unsigned NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`inspector_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__ra_footpaths_inspector_users` (`inspector_id`, `user_id`)
  SELECT `id`, `user_id` FROM `#__ra_footpaths_inspectors` WHERE `user_id` IS NOT NULL AND `user_id` > 0;

ALTER TABLE `#__ra_footpaths_inspectors` DROP INDEX `idx_user`, DROP COLUMN `user_id`;
