CREATE TABLE `system_updates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
  `remoteversion` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `releasenotes` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `zipball` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `available_since` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_querytime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `last_querytime` (`last_querytime`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;