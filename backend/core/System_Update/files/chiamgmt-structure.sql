-- MySQL dump 10.13  Distrib 8.0.27, for Linux (x86_64)
--
-- Host: localhost    Database: chiamgmt_edtmair_at
-- ------------------------------------------------------
-- Server version	8.0.27-0ubuntu0.20.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `authkeys`
--

DROP TABLE IF EXISTS `authkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usrID` int NOT NULL,
  `authstring` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `UserID` (`usrID`),
  CONSTRAINT `authkeys_ibfk_1` FOREIGN KEY (`usrID`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_farm`
--

DROP TABLE IF EXISTS `chia_farm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_farm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `syncstatus` tinyint(1) NOT NULL,
  `total_chia_farmed` float NOT NULL DEFAULT '0',
  `user_transaction_fees` float NOT NULL DEFAULT '0',
  `block_rewards` float NOT NULL DEFAULT '0',
  `last_height_farmed` int DEFAULT '0',
  `plot_count` int NOT NULL DEFAULT '0',
  `total_size_of_plots` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estimated_network_space` float DEFAULT NULL,
  `expected_time_to_win` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `NodeIDFarm` (`nodeid`),
  CONSTRAINT `fk_nodes_chia_farm` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_farm_challanges_proofcount`
--

DROP TABLE IF EXISTS `chia_farm_challanges_proofcount`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_farm_challanges_proofcount` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `chia_farm_challanges_id` int NOT NULL,
  `proofcount` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `chia_farm_challanges_id` (`chia_farm_challanges_id`),
  KEY `fk_nodes_chia_farm_challanges_proofcount` (`nodeid`),
  CONSTRAINT `fk_nodes_chia_farm_challanges_proofcount` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_farm_challenges`
--

DROP TABLE IF EXISTS `chia_farm_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_farm_challenges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `challenge_chain_sp` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `challenge_hash` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `difficulty` int NOT NULL,
  `reward_chain_sp` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `signage_point_index` int NOT NULL,
  `sub_slot_iters` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_chain_sp` (`challenge_chain_sp`),
  KEY `nodeid` (`nodeid`),
  CONSTRAINT `fk_nodes_chia_farm_challenges` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2776380 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_infra_sysinfo`
--

DROP TABLE IF EXISTS `chia_infra_sysinfo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_infra_sysinfo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `load_1min` float NOT NULL,
  `load_5min` float NOT NULL,
  `load_15min` float NOT NULL,
  `memory_total` bigint NOT NULL,
  `memory_free` bigint NOT NULL,
  `memory_buffers` bigint NOT NULL,
  `memory_cached` bigint NOT NULL,
  `memory_shared` bigint DEFAULT NULL,
  `swap_total` bigint NOT NULL,
  `swap_free` bigint NOT NULL,
  `cpu_count` int NOT NULL,
  `cpu_cores` int NOT NULL,
  `cpu_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `NodeIDSysinfo` (`nodeid`),
  KEY `timestamp` (`timestamp`),
  CONSTRAINT `fk_nodes_chia_infra_sysinfo` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34578 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_infra_sysinfo_filesystems`
--

DROP TABLE IF EXISTS `chia_infra_sysinfo_filesystems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_infra_sysinfo_filesystems` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sysinfo_id` int NOT NULL,
  `device` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `size` int NOT NULL,
  `used` int NOT NULL,
  `avail` int NOT NULL,
  `mountpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sysinfo_id` (`sysinfo_id`),
  KEY `mountpoint` (`mountpoint`),
  CONSTRAINT `fk_chia_infra_sysinfo_chia_infra_sysinfo_filesystems` FOREIGN KEY (`sysinfo_id`) REFERENCES `chia_infra_sysinfo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=319795 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_overall`
--

DROP TABLE IF EXISTS `chia_overall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_overall` (
  `id` int NOT NULL AUTO_INCREMENT,
  `blockchain_version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `daychange_percent` float NOT NULL,
  `netspace` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `xch_blockheight` int NOT NULL DEFAULT '0',
  `netspace_timestamp` datetime NOT NULL,
  `price_usd` float NOT NULL,
  `daymin_24h_usd` float NOT NULL,
  `daymax_24h_usd` float NOT NULL,
  `daychange_24h_percent` float NOT NULL,
  `market_timestamp` datetime NOT NULL,
  `querydate` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_plots`
--

DROP TABLE IF EXISTS `chia_plots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_plots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cpd_id` int NOT NULL,
  `file_size` bigint NOT NULL,
  `filename` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `plot_seed` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `plot_id` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `plot_public_key` varchar(98) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pool_contract_puzzle_hash` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pool_public_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `size` int NOT NULL,
  `time_modified` datetime NOT NULL,
  `last_reported` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plot_id` (`plot_id`),
  UNIQUE KEY `plot_seed` (`plot_seed`),
  KEY `NodeIDPlots` (`cpd_id`),
  CONSTRAINT `fk_chia_plots_directories_chia_plots` FOREIGN KEY (`cpd_id`) REFERENCES `chia_plots_directories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24790 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_plots_directories`
--

DROP TABLE IF EXISTS `chia_plots_directories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_plots_directories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `mountpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plotcount` int NOT NULL DEFAULT '0',
  `firstreported` datetime NOT NULL,
  `lastupdated` datetime NOT NULL,
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `nodeid` (`nodeid`),
  KEY `mountpoint` (`mountpoint`),
  CONSTRAINT `fk_nodes_chia_plots_directories` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_wallets`
--

DROP TABLE IF EXISTS `chia_wallets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_wallets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `walletid` int NOT NULL,
  `walletaddress` varchar(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `walletheight` int NOT NULL,
  `syncstatus` tinyint NOT NULL,
  `wallettype` tinyint NOT NULL,
  `totalbalance` int NOT NULL,
  `pendingtotalbalance` int NOT NULL,
  `spendable` int NOT NULL,
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `NodeIDWallet` (`nodeid`),
  CONSTRAINT `fk_nodes_chia_wallets` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_wallets_transactions`
--

DROP TABLE IF EXISTS `chia_wallets_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_wallets_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `wallet_id` int NOT NULL,
  `parent_coin_info` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `amount` bigint NOT NULL,
  `confirmed` tinyint(1) NOT NULL,
  `confirmed_at_height` int NOT NULL,
  `created_at_time` int NOT NULL,
  `fee_amount` int NOT NULL,
  `name` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `removals` json NOT NULL,
  `sent` int NOT NULL,
  `sent_to` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spend_bundle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_address` varchar(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `to_puzzle_hash` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `trade_id` int DEFAULT NULL,
  `type` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `NodeIDWalletTrans` (`nodeid`),
  CONSTRAINT `fk_nodes_chia_wallets_transactions` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exchangerates`
--

DROP TABLE IF EXISTS `exchangerates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchangerates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `currency_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `currency_desc` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `currency_rate` float NOT NULL,
  `updatedate` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `currency_code` (`currency_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5581361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes`
--

DROP TABLE IF EXISTS `nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeauthhash` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `hostname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scriptversion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updatechannel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chiaversion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chiapath` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `conallow` tinyint(1) NOT NULL,
  `authtype` int NOT NULL,
  `ipaddress` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `changedIP` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `changeable` tinyint(1) NOT NULL DEFAULT '1',
  `lastseen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes_services_status`
--

DROP TABLE IF EXISTS `nodes_services_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodes_services_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `serviceid` tinyint NOT NULL,
  `servicestate` tinyint NOT NULL,
  `firstreported` datetime NOT NULL,
  `lastreported` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lastreported` (`lastreported`),
  KEY `nodeid` (`nodeid`),
  CONSTRAINT `fk_nodes_nodes_services_status` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1048 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes_up_status`
--

DROP TABLE IF EXISTS `nodes_up_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodes_up_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `onlinestatus` int NOT NULL,
  `firstreported` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastreported` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_nodes_nodes_up_status` (`nodeid`),
  CONSTRAINT `fk_nodes_nodes_up_status` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1990 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodetype`
--

DROP TABLE IF EXISTS `nodetype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodetype` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `code` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `NodeIDNodetype` (`nodeid`),
  KEY `NotetypesAvail` (`code`),
  CONSTRAINT `fk_nodes_nodetype` FOREIGN KEY (`nodeid`) REFERENCES `nodes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nodetype_ibfk_1` FOREIGN KEY (`code`) REFERENCES `nodetypes_avail` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodetypes_avail`
--

DROP TABLE IF EXISTS `nodetypes_avail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodetypes_avail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `description` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `code` int NOT NULL,
  `selectable` tinyint(1) NOT NULL DEFAULT '1',
  `allowed_authtype` int NOT NULL,
  `nodetype` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `namespace` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sites_pagestoinform`
--

DROP TABLE IF EXISTS `sites_pagestoinform`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites_pagestoinform` (
  `id` int NOT NULL AUTO_INCREMENT,
  `siteid` int NOT NULL,
  `sitetoinform` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `siteid` (`siteid`),
  CONSTRAINT `sites_pagestoinform_ibfk_1` FOREIGN KEY (`siteid`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_infos`
--

DROP TABLE IF EXISTS `system_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_infos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dbversion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `userid_updating` int NOT NULL,
  `process_update` tinyint NOT NULL DEFAULT '0',
  `lastsucupdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maintenance_mode` int NOT NULL,
  `lastcronrun` timestamp NOT NULL DEFAULT '1970-01-01 22:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `settingtype` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `settingvalue` json NOT NULL,
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `settingtype` (`settingtype`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lastname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `salt` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `creationdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_authkeys`
--

DROP TABLE IF EXISTS `users_authkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_authkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `authkey` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `validuntil` datetime NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `UserIDAuthkeys` (`userid`),
  CONSTRAINT `users_authkeys_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=382 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_backupkeys`
--

DROP TABLE IF EXISTS `users_backupkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_backupkeys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `backupkey` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `UserIDBkpKeys` (`userid`),
  CONSTRAINT `users_backupkeys_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_pwresets`
--

DROP TABLE IF EXISTS `users_pwresets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_pwresets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `linkkey` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `expiration` datetime NOT NULL,
  `expired` tinyint NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userid` (`userid`),
  CONSTRAINT `users_pwresets_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_sessions`
--

DROP TABLE IF EXISTS `users_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `logindate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `userid` int NOT NULL,
  `sessid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `authkeypassed` tinyint(1) NOT NULL DEFAULT '0',
  `totpmobilepassed` tinyint NOT NULL DEFAULT '0',
  `deviceinfo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `validuntil` datetime DEFAULT NULL,
  `invalidated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `UserIDSessions` (`userid`),
  CONSTRAINT `users_sessions_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=456 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_settings`
--

DROP TABLE IF EXISTS `users_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `currency_code` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'usd',
  `gui_mode` int NOT NULL DEFAULT '1',
  `totp_enable` tinyint NOT NULL DEFAULT '0',
  `totp_secret` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totp_proofen` tinyint DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `userid` (`userid`),
  CONSTRAINT `users_settings_ibfk_1` FOREIGN KEY (`userid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-01-19 15:56:46
