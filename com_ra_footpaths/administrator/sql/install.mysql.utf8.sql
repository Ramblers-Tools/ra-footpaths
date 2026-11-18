-- RA Footpaths: tables. legacy_id keeps the id from the old Footpath Inspections Database.

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_areas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `state` tinyint NOT NULL DEFAULT 1,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_parishes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `area_id` int unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `boundary` geometry DEFAULT NULL,
  `ce_code` int DEFAULT NULL,
  `pnfs_code` varchar(20) DEFAULT NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`),
  KEY `idx_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_paths` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parish_id` int unsigned DEFAULT NULL,
  `type` varchar(8) NOT NULL DEFAULT '',
  `number` varchar(30) NOT NULL DEFAULT '',
  `sort_key` int NOT NULL DEFAULT 0,
  `route` geometry DEFAULT NULL,
  `ha_code` varchar(20) DEFAULT NULL,
  `pnfs_code` varchar(20) DEFAULT NULL,
  `start_gridref` varchar(12) DEFAULT NULL,
  `end_gridref` varchar(12) DEFAULT NULL,
  `length_m` int DEFAULT NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`),
  KEY `idx_parish` (`parish_id`, `state`, `sort_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_inspectors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `active` tinyint NOT NULL DEFAULT 0,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Website logins for an inspector (some people have more than one, for example a personal and a club address).
CREATE TABLE IF NOT EXISTS `#__ra_footpaths_inspector_users` (
  `inspector_id` int unsigned NOT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`inspector_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_inspector_parishes` (
  `inspector_id` int unsigned NOT NULL,
  `parish_id` int unsigned NOT NULL,
  PRIMARY KEY (`inspector_id`, `parish_id`),
  KEY `idx_parish` (`parish_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_fault_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `state` tinyint NOT NULL DEFAULT 1,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- grade: A to D. For imported rows it is the grade the old system showed: the inspection's own grade
-- (A when it had none) raised to the worst grade of its faults. grade_recorded keeps what was entered.
CREATE TABLE IF NOT EXISTS `#__ra_footpaths_inspections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `path_id` int unsigned NOT NULL,
  `inspector_id` int unsigned DEFAULT NULL,
  `inspection_date` date DEFAULT NULL,
  `grade` char(1) NOT NULL DEFAULT 'A',
  `grade_recorded` char(1) DEFAULT NULL,
  `remarks` text,
  `created` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`),
  KEY `idx_path_date` (`path_id`, `inspection_date`),
  KEY `idx_inspector` (`inspector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_faults` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `inspection_id` int unsigned NOT NULL,
  `fault_type_id` int unsigned DEFAULT NULL,
  `grade` char(1) DEFAULT NULL,
  `remarks` text,
  `gridref` varchar(60) DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`),
  KEY `idx_inspection` (`inspection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photo files live outside the public site (component option photo_path) and are served by the
-- component after a permission check. file is relative to that folder.
CREATE TABLE IF NOT EXISTS `#__ra_footpaths_fault_photos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fault_id` int unsigned NOT NULL,
  `file` varchar(100) NOT NULL DEFAULT '',
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `bytes` int DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fault` (`fault_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ra_footpaths_fault_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fault_id` int unsigned NOT NULL,
  `event_date` date DEFAULT NULL,
  `reporter_id` int unsigned DEFAULT NULL,
  `notes` text,
  `legacy_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_legacy` (`legacy_id`),
  KEY `idx_fault` (`fault_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
