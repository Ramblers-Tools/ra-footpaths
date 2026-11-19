-- 0.5.0: inspectors edited in the admin keep their name, active flag and parishes when the old system is re-imported.
ALTER TABLE `#__ra_footpaths_inspectors` ADD COLUMN `site_managed` tinyint NOT NULL DEFAULT 0 AFTER `active`;
