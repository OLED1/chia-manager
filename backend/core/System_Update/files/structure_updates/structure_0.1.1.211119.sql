ALTER TABLE chia_farm DROP COLUMN farming_status; # was varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_farm CHANGE COLUMN estimated_network_space estimated_network_space float DEFAULT NULL; # was varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_farm ADD COLUMN syncstatus tinyint(1) NOT NULL;
ALTER TABLE chia_farm ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_farm_challenges DROP COLUMN hash; # was varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_farm_challenges DROP COLUMN hash_index; # was int NOT NULL
ALTER TABLE chia_farm_challenges ADD COLUMN reward_chain_sp varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN challenge_chain_sp varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN nodeid int NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN difficulty int NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN sub_slot_iters int NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN challenge_hash varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_farm_challenges ADD COLUMN signage_point_index int NOT NULL;
ALTER TABLE chia_farm_challenges ADD UNIQUE challenge_chain_sp (challenge_chain_sp);
ALTER TABLE chia_farm_challenges ADD INDEX nodeid (nodeid);
ALTER TABLE chia_farm_challenges ENGINE=InnoDB AUTO_INCREMENT=2284932 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=641 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_infra_sysinfo DROP COLUMN filesystem; # was json NOT NULL
ALTER TABLE chia_infra_sysinfo ADD INDEX timestamp (timestamp);
ALTER TABLE chia_infra_sysinfo ENGINE=InnoDB AUTO_INCREMENT=26539 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_overall ENGINE=InnoDB AUTO_INCREMENT=15201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=2036 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_plots DROP COLUMN k_size; # was varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_plots DROP COLUMN status; # was int NOT NULL DEFAULT '1'
ALTER TABLE chia_plots CHANGE COLUMN filename filename varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL; # was varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_plots DROP COLUMN plot_key; # was varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots DROP COLUMN plotcreationdate; # was datetime NOT NULL
ALTER TABLE chia_plots DROP COLUMN pool_key; # was varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots DROP COLUMN finalmountid; # was int NOT NULL
ALTER TABLE chia_plots DROP COLUMN nodeid; # was int NOT NULL
ALTER TABLE chia_plots ADD COLUMN plot_seed varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_plots ADD COLUMN last_reported datetime NOT NULL;
ALTER TABLE chia_plots ADD COLUMN pool_contract_puzzle_hash varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_plots ADD COLUMN time_modified datetime NOT NULL;
ALTER TABLE chia_plots ADD COLUMN cpd_id int NOT NULL;
ALTER TABLE chia_plots ADD COLUMN pool_public_key varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL;
ALTER TABLE chia_plots ADD COLUMN plot_public_key varchar(98) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_plots ADD COLUMN plot_id varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE chia_plots ADD COLUMN size int NOT NULL;
ALTER TABLE chia_plots ADD COLUMN file_size bigint NOT NULL;
ALTER TABLE chia_plots DROP INDEX filename; # was UNIQUE (filename)
ALTER TABLE chia_plots ADD INDEX NodeIDPlots (cpd_id);
ALTER TABLE chia_plots ADD UNIQUE plot_id (plot_id);
ALTER TABLE chia_plots ADD UNIQUE plot_seed (plot_seed);
ALTER TABLE chia_plots ENGINE=InnoDB AUTO_INCREMENT=20062 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_plots_directories DROP COLUMN totalused; # was varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots_directories DROP COLUMN finalplotsdir; # was varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_plots_directories DROP COLUMN devname; # was varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots_directories DROP COLUMN totalsize; # was varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots_directories DROP COLUMN totalusedpercent; # was varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
ALTER TABLE chia_plots_directories ADD COLUMN lastupdated datetime NOT NULL;
ALTER TABLE chia_plots_directories ADD COLUMN firstreported datetime NOT NULL;
ALTER TABLE chia_plots_directories DROP INDEX NodeIDPlotsDir; # was INDEX (nodeid)
ALTER TABLE chia_plots_directories ADD INDEX mountpoint (mountpoint);
ALTER TABLE chia_plots_directories ADD INDEX nodeid (nodeid);
ALTER TABLE chia_plots_directories ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_wallets CHANGE COLUMN syncstatus syncstatus tinyint NOT NULL; # was varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_wallets CHANGE COLUMN wallettype wallettype tinyint NOT NULL; # was varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
ALTER TABLE chia_wallets CHANGE COLUMN spendable spendable int NOT NULL; # was float NOT NULL
ALTER TABLE chia_wallets CHANGE COLUMN pendingtotalbalance pendingtotalbalance int NOT NULL; # was float NOT NULL
ALTER TABLE chia_wallets CHANGE COLUMN totalbalance totalbalance int NOT NULL; # was float NOT NULL
ALTER TABLE chia_wallets ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE chia_wallets_transactions ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE exchangerates ENGINE=InnoDB AUTO_INCREMENT=4467467 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=825789 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE nodes ADD INDEX id (id);
ALTER TABLE nodes ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
DROP TABLE nodes_status;

ALTER TABLE nodetype ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE sites ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE sites_pagestoinform ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE system_infos CHANGE COLUMN lastcronrun lastcronrun timestamp NOT NULL DEFAULT '1970-01-01 22:00:00'; # was timestamp NOT NULL DEFAULT '1970-01-01 21:00:00'
ALTER TABLE users_authkeys ENGINE=InnoDB AUTO_INCREMENT=374 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=362 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
ALTER TABLE users_sessions ENGINE=InnoDB AUTO_INCREMENT=448 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; # was ENGINE=InnoDB AUTO_INCREMENT=439 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
CREATE TABLE chia_farm_challanges_proofcount (
  id int NOT NULL AUTO_INCREMENT,
  nodeid int NOT NULL,
  chia_farm_challanges_id int NOT NULL,
  proofcount int NOT NULL,
  PRIMARY KEY (id),
  KEY chia_farm_challanges_id (chia_farm_challanges_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE chia_infra_sysinfo_filesystems (
  id int NOT NULL AUTO_INCREMENT,
  sysinfo_id int NOT NULL,
  device varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  size int NOT NULL,
  used int NOT NULL,
  avail int NOT NULL,
  mountpoint varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (id),
  KEY sysinfo_id (sysinfo_id),
  KEY mountpoint (mountpoint)
) ENGINE=InnoDB AUTO_INCREMENT=178953 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE nodes_services_status (
  id int NOT NULL AUTO_INCREMENT,
  nodeid int NOT NULL,
  serviceid tinyint NOT NULL,
  servicestate tinyint NOT NULL,
  firstreported datetime NOT NULL,
  lastreported datetime NOT NULL,
  PRIMARY KEY (id),
  KEY lastreported (lastreported),
  KEY nodeid (nodeid)
) ENGINE=InnoDB AUTO_INCREMENT=931 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE nodes_up_status (
  id int NOT NULL AUTO_INCREMENT,
  nodeid int NOT NULL,
  onlinestatus int NOT NULL,
  firstreported datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lastreported datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB AUTO_INCREMENT=1531 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `system_infos` SET dbversion = '0.1.1.211119';
