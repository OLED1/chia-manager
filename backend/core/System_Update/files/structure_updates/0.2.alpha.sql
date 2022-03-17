/* Just a templatetest file for future db structure releases */;
/* Paste MySQL Statements after this comment */;
INSERT INTO `sites` (`id`, `namespace`) VALUES ('14', 'ChiaMgmt\\Alerting\\Alerting_Api');
INSERT INTO `sites_pagestoinform` (`id`, `siteid`, `sitetoinform`) VALUES ('26', '14', '14');
ALTER TABLE `chia_infra_sysinfo_filesystems` CHANGE `size` `size` BIGINT NOT NULL;
ALTER TABLE `chia_infra_sysinfo_filesystems` CHANGE `used` `used` BIGINT NOT NULL;
ALTER TABLE `chia_infra_sysinfo_filesystems` CHANGE `avail` `avail` BIGINT NOT NULL;
INSERT INTO `alerting_rules` VALUES (1,1,1,'',1,1,1,2),(2,1,2,'',1,1,2,5),(3,1,3,'',1,1,2,5),(4,1,4,'',1,1,2,5),(5,1,5,'',1,0,90,95),(6,1,6,'',1,0,90,95),(7,1,7,'',1,0,90,95),(8,1,8,'',1,0,90,95),(9,1,9,'',1,0,90,95);
ALTER TABLE `chia_infra_sysinfo` ADD `os_type` VARCHAR(100) NOT NULL AFTER `cpu_model`, ADD `os_name` VARCHAR(100) NOT NULL AFTER `os_type`; 