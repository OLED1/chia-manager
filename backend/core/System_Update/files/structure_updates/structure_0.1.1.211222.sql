ALTER TABLE `chia_overall` ADD `blockchain_version` VARCHAR(10) NOT NULL AFTER `id`; 
UPDATE `system_infos` SET dbversion = '0.1.1.211222';