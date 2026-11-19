ALTER TABLE `#__ra_footpaths_inspections` ADD COLUMN `modified` datetime DEFAULT NULL AFTER `created_by`, ADD COLUMN `modified_by` int DEFAULT NULL AFTER `modified`;
ALTER TABLE `#__ra_footpaths_fault_events` ADD COLUMN `created` datetime DEFAULT NULL AFTER `notes`, ADD COLUMN `created_by` int DEFAULT NULL AFTER `created`, ADD COLUMN `modified` datetime DEFAULT NULL AFTER `created_by`;
CREATE TABLE IF NOT EXISTS `#__ra_footpaths_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(20) NOT NULL DEFAULT '',
  `item_type` varchar(20) NOT NULL DEFAULT '',
  `item_id` int unsigned DEFAULT NULL,
  `path_id` int unsigned DEFAULT NULL,
  `details` mediumtext,
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_type`, `item_id`),
  KEY `idx_path` (`path_id`, `created`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
