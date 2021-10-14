-- MySQL dump 10.13  Distrib 8.0.26, for Linux (x86_64)
--
-- Host: localhost    Database: chiamgmt_edtmair_at
-- ------------------------------------------------------
-- Server version	8.0.26-0ubuntu0.20.04.3

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
  `authstring` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `UserID` (`usrID`),
  CONSTRAINT `UserID` FOREIGN KEY (`usrID`) REFERENCES `users` (`id`)
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
  `farming_status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `total_chia_farmed` float NOT NULL DEFAULT '0',
  `user_transaction_fees` float NOT NULL DEFAULT '0',
  `block_rewards` float NOT NULL DEFAULT '0',
  `last_height_farmed` int DEFAULT '0',
  `plot_count` int NOT NULL DEFAULT '0',
  `total_size_of_plots` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estimated_network_space` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expected_time_to_win` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `NodeIDFarm` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_farm_challenges`
--

DROP TABLE IF EXISTS `chia_farm_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_farm_challenges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hash` varchar(70) COLLATE utf8mb4_general_ci NOT NULL,
  `hash_index` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=641 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `filesystem` json NOT NULL,
  `memory_total` bigint NOT NULL,
  `memory_free` bigint NOT NULL,
  `memory_buffers` bigint NOT NULL,
  `memory_cached` bigint NOT NULL,
  `memory_shared` bigint DEFAULT NULL,
  `swap_total` bigint NOT NULL,
  `swap_free` bigint NOT NULL,
  `cpu_count` int NOT NULL,
  `cpu_cores` int NOT NULL,
  `cpu_model` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `NodeIDSysinfo` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=202 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_overall`
--

DROP TABLE IF EXISTS `chia_overall`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_overall` (
  `id` int NOT NULL AUTO_INCREMENT,
  `daychange_percent` float NOT NULL,
  `netspace` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `netspace_timestamp` datetime NOT NULL,
  `price_usd` float NOT NULL,
  `daymin_24h_usd` float NOT NULL,
  `daymax_24h_usd` float NOT NULL,
  `daychange_24h_percent` float NOT NULL,
  `market_timestamp` datetime NOT NULL,
  `querydate` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=787 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `chia_plots`
--

DROP TABLE IF EXISTS `chia_plots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chia_plots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `finalmountid` int NOT NULL,
  `nodeid` int NOT NULL,
  `k_size` varchar(3) COLLATE utf8mb4_general_ci NOT NULL,
  `plotcreationdate` datetime NOT NULL,
  `plot_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pool_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `filename` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`),
  KEY `NodeIDPlots` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `devname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mountpoint` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `finalplotsdir` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `totalsize` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totalused` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `totalusedpercent` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plotcount` int NOT NULL DEFAULT '0',
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `NodeIDPlotsDir` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `walletaddress` varchar(62) COLLATE utf8mb4_general_ci NOT NULL,
  `walletheight` int NOT NULL,
  `syncstatus` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `wallettype` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `totalbalance` float NOT NULL,
  `pendingtotalbalance` float NOT NULL,
  `spendable` float NOT NULL,
  `querydate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `NodeIDWallet` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  `parent_coin_info` varchar(66) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` bigint NOT NULL,
  `confirmed` tinyint(1) NOT NULL,
  `confirmed_at_height` int NOT NULL,
  `created_at_time` int NOT NULL,
  `fee_amount` int NOT NULL,
  `name` varchar(66) COLLATE utf8mb4_general_ci NOT NULL,
  `removals` json NOT NULL,
  `sent` int NOT NULL,
  `sent_to` varchar(66) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `spend_bundle` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `to_address` varchar(62) COLLATE utf8mb4_general_ci NOT NULL,
  `to_puzzle_hash` varchar(66) COLLATE utf8mb4_general_ci NOT NULL,
  `trade_id` int DEFAULT NULL,
  `type` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `NodeIDWalletTrans` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=476761 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes`
--

DROP TABLE IF EXISTS `nodes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeauthhash` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `hostname` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `scriptversion` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updatechannel` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chiaversion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `chiapath` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `conallow` tinyint(1) NOT NULL,
  `authtype` int NOT NULL,
  `ipaddress` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `changedIP` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `changeable` tinyint(1) NOT NULL DEFAULT '1',
  `lastseen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodes_status`
--

DROP TABLE IF EXISTS `nodes_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodes_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nodeid` int NOT NULL,
  `onlinestatus` int NOT NULL,
  `walletstatus` int NOT NULL,
  `farmerstatus` int NOT NULL,
  `harvesterstatus` int NOT NULL,
  `querytime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nodeid` (`nodeid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  KEY `NotetypesAvail` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nodetypes_avail`
--

DROP TABLE IF EXISTS `nodetypes_avail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nodetypes_avail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `description` varchar(35) COLLATE utf8mb4_general_ci NOT NULL,
  `code` int NOT NULL,
  `selectable` tinyint(1) NOT NULL DEFAULT '1',
  `allowed_authtype` int NOT NULL,
  `nodetype` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
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
  `namespace` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_infos`
--

DROP TABLE IF EXISTS `system_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_infos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dbversion` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `userid_updating` int NOT NULL,
  `lastsucupdate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maintenance_mode` int NOT NULL,
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
  `settingtype` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
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
  `username` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `lastname` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `salt` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
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
  `authkey` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `validuntil` datetime NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `UserIDAuthkeys` (`userid`),
  CONSTRAINT `UserIDAuthkeys` FOREIGN KEY (`userid`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=330 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  CONSTRAINT `UserIDBkpKeys` FOREIGN KEY (`userid`) REFERENCES `users` (`id`)
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
  PRIMARY KEY (`id`)
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
  `sessid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `authkeypassed` tinyint(1) NOT NULL DEFAULT '0',
  `deviceinfo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `validuntil` datetime DEFAULT NULL,
  `invalidated` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `UserIDSessions` (`userid`),
  CONSTRAINT `UserIDSessions` FOREIGN KEY (`userid`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=393 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `userid` (`userid`)
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

-- Dump completed on 2021-10-07 11:15:49
