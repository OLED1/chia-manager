<?php
    use ChiaMgmt\DB\DB_Api;
    use ChiaMgmt\Encryption\Encryption_Api;
    use ChiaMgmt\WebSocket\WebSocket_Api;
    require __DIR__ . '/../vendor/autoload.php';

    $init_chia_manager_docker = new Init_Chia_Manager_Docker();

    if($init_chia_manager_docker->testENVset()){
        $init_chia_manager_docker->setENVvariables();
    }else{
        exit(1);
    }

    if($init_chia_manager_docker->wait_for_database()){
        $init_chia_manager_docker->printEnvConfiguration();
    }else{
        exit(1);
    }

    $install_db = $init_chia_manager_docker->check_db_is_setup();
    if(!$install_db || !filter_var(getenv("CM_INSTALL"), FILTER_VALIDATE_BOOLEAN)){
        echo "  [INFO]Database is existing and setup or installation should be skipped. Skipping some steps.";
    }else{
        echo "  [INFO]Database is not setup and first installation steps should be executed.";

        if(!$init_chia_manager_docker->installChiamgmt()) exit(1);
    }

    if($init_chia_manager_docker->wait_for_websocket()){
;
    }else{
        exit(1);
    }

    echo "\n[STEP]Finished installation and migration steps. Starting server.\n";

    class Init_Chia_Manager_Docker{
        /**
         * Holds an instance to the Database Class.
         * @var DB_Api
         */
        private $db_api;
        /**
         * Database hostname.
         * @var string
         */
        private $db_host;
        /**
         * Database table name.
         * @var string
         */
        private $db_name;
        /**
         * Database user name.
         * @var string
         */
        private $db_user;
        /**
         * Database user password.
         * @var string
         */
        private $db_user_pass;
        /**
         * Database connection.
         * @var DB_Api
         */
        private $db_conn;
        /**
         * Database connection.
         * @var Websocket_Api
         */
        private $websocket_api;

        private $cm_app_root;

        private $cm_version;
        private $cm_update_branch;

        private $cm_domain;
        private $cm_admin_user;
        private $cm_admin_pw;
        private $cm_admin_forename;
        private $cm_admin_lastname;
        private $cm_admin_email;

        private $cm_websocket_docker_host;
        private $cm_websocket_port;
        private $cm_websocket_listener;
        private $cm_websocket_protocol;

        public function testENVset(): bool{
            echo "[STEP]Testing ENV Variables:\n";
            if(getenv('CM_MYSQL_HOST') && getenv('CM_MYSQL_DATABASE') && getenv('CM_MYSQL_USER') && getenv('CM_MYSQL_PASSWORD') && 
                getenv("CM_VERSION") && getenv("CM_UPDATE_BRANCH") && getenv('CM_DOMAIN') && getenv('CM_WEBSOCKET_DOCKER_HOST') && getenv("CM_WEBSOCKET_PORT") && getenv('CM_WEBSOCKET_PORT') && getenv('CM_WEBSOCKET_PROTOCOL') &&
                getenv("CM_ADMIN_FORENAME") && getenv("CM_ADMIN_LASTNAME") && getenv("CM_ADMIN_EMAIL") && getenv('CM_ADMIN_USER') && getenv('CM_ADMIN_PW')){
                
                echo "    [SUC]SUCCESS! All ENV Variables are set.\n";
                return true;
            }

            echo "  [ERR]Some ENV Variables are missing.\n";
            $this->printEnvConfiguration();
            return false;
        }

        public function setENVvariables(){
            $this->db_host = getenv("CM_MYSQL_HOST");
            $this->db_name = getenv("CM_MYSQL_DATABASE");
            $this->db_user = getenv("CM_MYSQL_USER");
            $this->db_user_pass = getenv("CM_MYSQL_PASSWORD");

            $this->cm_version = getenv("CM_VERSION");
            $this->cm_update_branch = getenv("CM_UPDATE_BRANCH");

            $this->cm_app_root = "/var/www/html";
            $this->cm_domain = getenv("CM_DOMAIN");

            $this->cm_admin_user = getenv('CM_ADMIN_USER');
            $this->cm_admin_pw = getenv('CM_ADMIN_PW');
            $this->cm_admin_forename = getenv('CM_ADMIN_FORENAME');
            $this->cm_admin_lastname = getenv('CM_ADMIN_LASTNAME');
            $this->cm_admin_email = getenv('CM_ADMIN_EMAIL');

            $this->cm_websocket_docker_host = getenv("CM_WEBSOCKET_DOCKER_HOST");
            $this->cm_websocket_port = getenv("CM_WEBSOCKET_PORT");
            $this->cm_websocket_listener = getenv("CM_WEBSOCKET_LISTENER");
            $this->cm_websocket_protocol = getenv("CM_WEBSOCKET_PROTOCOL"); //ws or wss
        }

        public function printEnvConfiguration(){
            echo "  [INFO]DB: " . getenv("CM_MYSQL_DATABASE") . " HOST: " . getenv("CM_MYSQL_HOST") . " USER: " . getenv("CM_MYSQL_USER") . " USER_PASS: " . getenv("CM_MYSQL_PASSWORD") . "\n";
            echo "  [INFO]CM_VERSION: " . getenv("CM_VERSION") . " CM_DOMAIN: " . getenv("CM_DOMAIN") . " \n";
            echo "  [INFO]CM_WEBSOCKET_DOCKER_HOST: " . getenv("CM_WEBSOCKET_DOCKER_HOST") . " CM_WEBSOCKET_PORT: " . getenv("CM_WEBSOCKET_PORT") . " CM_WEBSOCKET_LISTENER: " . getenv("CM_WEBSOCKET_LISTENER") . " CM_WEBSOCKET_PROTOCOL: " . getenv("CM_WEBSOCKET_PROTOCOL") . "\n";
        }

        public function wait_for_database(): bool{
            echo "[STEP]Waiting for database to come online:";
            $db_con_succ = false;
            while(!$db_con_succ){
                echo ".";
                try{
                    $dbh = new PDO("mysql:host=$this->db_host", $this->db_user, $this->db_user_pass);
                    $db_con_succ = true;
                    echo "  [SUCC]Starting migration using following parameters:\n";
                }catch(\Exception $e){
                    sleep(1);
                    continue;
                }
            }

            echo "\n";
            return true;
        }

        public function wait_for_websocket(){
            echo "\n[STEP]Waiting for websocket server to come online:";
            $websocket_api = new WebSocket_Api();
            $ws_con_succ = false;
            while(!$ws_con_succ){
                echo ".";
                if($websocket_api->testConnection(getenv("CM_WEBSOCKET_DOCKER_HOST"))["status"] == 0){
                    $ws_con_succ = true;
                }else{
                    sleep(1);
                }
            }

            return true;
        }

        public function check_db_is_setup(){
            $install_db = true;

            $dbh = new PDO("mysql:host=$this->db_host", $this->db_user, $this->db_user_pass);
            $stmt = $dbh->prepare("SHOW DATABASES LIKE '$this->db_name'");
            $stmt->execute();
            $db_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if($db_exists){
                $this->db_conn = new PDO("mysql:host=$this->db_host;dbname=$this->db_name", $this->db_user, $this->db_user_pass);
                $stmt = $this->db_conn->prepare("SHOW TABLES LIKE 'nodetype'");
                $stmt->execute();
                $table_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if($table_exists) $install_db = false;
            }

            return $install_db;
        }

        /**
         * Installs the chiamgmt instance.
         * This method is used during the installation process.
         * This method do not return formatted error messages, because they are not present at this time.
         * Function made for: Web(App)client
         * @throws Exception $e                 Throws an exception on db errors.
         * @return array                        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
         */
        public function installChiamgmt(): bool
        {
            $noncekey = $this->generateRandomString();
            $dbsalt = $this->generateRandomString();
            $serversalt = $this->generateRandomString();
            $web_client_auth_hash = $this->generateRandomString();
            $backend_client_auth_hash = $this->generateRandomString();
            $configdir = "{$this->cm_app_root}/backend/config/";

            //1a. Create config
            echo "[STEP]Creating config.";
            $config = file_get_contents("{$this->cm_app_root}/backend/core/System_Update/installer_templates/config.txt");
            $config = str_replace("[databasename]", $this->db_name, $config);
            $config = str_replace("[mysqluser]", $this->db_user, $config);
            $config = str_replace("[mysqlpassword]", $this->db_user_pass, $config);
            $config = str_replace("[mysqlhost]", $this->db_host, $config);
            $config = str_replace("[app_domain]", $this->cm_domain, $config);
            $config = str_replace("[serversalt]", $serversalt, $config);
            $config = str_replace("[noncekey]", $noncekey, $config);
            $config = str_replace("[version]", $this->cm_version, $config);
            $config = str_replace("[web_client_auth_hash]", $web_client_auth_hash, $config);
            $config = str_replace("[backend_client_auth_hash]", $backend_client_auth_hash, $config);
            $config = str_replace("[socket_protocol]", $this->cm_websocket_protocol, $config);
            $config = str_replace("[socket_listener]", $this->cm_websocket_listener, $config);
            $config = str_replace("[socket_local_port]", $this->cm_websocket_port, $config);
            echo " OK.";

            //1b. Create htaccess
            echo "\n[STEP]Creating htaccess.";
            $htaccess = file_get_contents("{$this->cm_app_root}/backend/core/System_Update/installer_templates/htaccess.txt");
            $htaccess = str_replace("[nonce]", $noncekey, $htaccess);
            echo " OK.\n";

            //1c. Writing config
            echo "[STEP]Writing config.";
            $configfile = fopen("{$configdir}/config.ini.php", "w");
            fwrite($configfile, $config);
            fclose($configfile);
            echo " OK.";

            //1d. Writing new htaccess
            echo "\n[STEP]Writing htaccess.";
            $htaccessfile = fopen("{$this->cm_app_root}/.htaccess", "w");
            fwrite($htaccessfile, $htaccess);
            fclose($htaccessfile);
            echo " OK.";

            //1e. Verifying config
            echo "\n[STEP]Verifying config.";
            $configfile = NULL;
            if(file_exists("{$configdir}/config.ini.php")){
                $configfile = parse_ini_file("{$configdir}/config.ini.php");
            }
            if(is_array($configfile)){
                echo "\n  [SUC]Config file successfully created.\n";
            }else{
                echo "\n  [ERR]The config file was not created successfully.\n";
                return false;
            }
            
            //1f. Verfifying htaccess
            echo "[STEP]Verifying htaccess.";
            if(strpos(file_get_contents("{$this->cm_app_root}/.htaccess"),$noncekey) !== false){
                echo "\n  [SUC]htaccess file adapted successfully.\n";
            }else{
                echo "\n  [ERR]The htaccess file does not contain the new nonce key. Please make sure apache/nginx has rwx file access.\n";
                return false;
            }

            //2. Creating Database
            echo "[STEP]Installing database with default values.";
            try{
                //2a. Instance db connection
                $this->db_api = new DB_Api();

                //2b. Importing structure dump
                $query = '';
                $structuredump = file("{$this->cm_app_root}/backend/core/System_Update/files/chiamgmt-structure.sql");
                foreach ($structuredump as $line) {
                $startWith = substr(trim($line), 0 ,2);
                $endWith = substr(trim($line), -1 ,1);

                if (empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') {
                    continue;
                }

                $query = $query . $line;
                    if($endWith == ';'){
                        //echo "\n  [INFO]Executing: $query.\n";
                        $this->db_api->execute($query,[]);
                        $query= '';
                    }
                }

                echo "\n  [SUC]Table structure created successfully.";
                //2c. Insert default values
                $encryption_api = new Encryption_Api();
                //Default Nodes
                $query = '';
                $web_client_auth_hash = $encryption_api->encryptString($web_client_auth_hash);
                $backend_client_auth_hash = $encryption_api->encryptString($backend_client_auth_hash);
                $query = "INSERT INTO `nodes` VALUES (1,'{$web_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,1,'','',0,NOW()), (2,'{$backend_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,3,'','',0,NOW());";

                //Default system_settings
                $query .= "INSERT INTO `system_settings` VALUES (1,'mailing','{}',0),(2,'security','{\"TOTP\": {\"value\": \"0\"}}',0),(3,'updatechannel','{\"branch\": {\"value\": \"{$this->cm_update_branch}\"}}',0);";

                //Default system_infos
                $query .= "INSERT INTO `system_infos` VALUES (1,'{$this->cm_version}',0,0,NOW(),0, NOW());";

                //Default user (admin) as configured in installer
                $userpassword = hash('sha256',$this->cm_admin_pw.$dbsalt.$serversalt);
                $query .= "INSERT INTO `users` VALUES (1,'{$this->cm_admin_user}','{$this->cm_admin_forename}','{$this->cm_admin_lastname}','{$userpassword}','{$dbsalt}','{$this->cm_admin_email}',NOW(),1);";

                //Default admin user settings
                $query .= "INSERT INTO `users_settings` (`id`, `userid`, `currency_code`, `gui_mode`, `totp_enable`, `totp_secret`, `totp_proofen`) VALUES (NULL, '1', 'usd', '1', '0', NULL, '0');";

                //Project registerred sites
                $query .= "INSERT INTO `sites` VALUES (1,'ChiaMgmt\\\\MainOverview\\\\MainOverview_Api'),(2,'ChiaMgmt\\\\Nodes\\\\Nodes_Api'),(3,'ChiaMgmt\\\\System\\\\System_Api'),(4,'ChiaMgmt\\\\Users\\\\Users_Api'),(5,'ChiaMgmt\\\\Chia_Wallet\\\\Chia_Wallet_Api'),(6,'ChiaMgmt\\\\Chia_Farm\\\\Chia_Farm_Api'),(7,'ChiaMgmt\\\\Chia_Harvester\\\\Chia_Harvester_Api'),(8,'ChiaMgmt\\\\Chia_Infra_Sysinfo\\\\Chia_Infra_Sysinfo_Api'),(9,'ChiaMgmt\\\\Chia_Overall\\\\Chia_Overall_Api'),(10,'ChiaMgmt\\\\System_Update\\\\System_Update_Api'),(11,'ChiaMgmt\\\\Logging\\\\Logging_Api'),(12,'ChiaMgmt\\\\Chia_Statistics\\\\Chia_Statistics_Api'),(13,'ChiaMgmt\\\\System_Statistics\\\\System_Statistics_Api');";
                $query .= "INSERT INTO `sites_pagestoinform` VALUES (1,1,1),(2,2,2),(3,2,1),(4,3,3),(5,4,4),(6,5,5),(7,5,1),(8,6,6),(9,6,1),(10,7,7),(11,7,1),(12,2,5),(13,2,6),(14,2,7),(15,8,8),(16,8,1),(17,2,8),(18,9,1),(19,10,1),(20,11,11),(21,9,12),(22,12,12),(23,13,13),(24,8,13),(25,9,5);";

                //Default nodetypes
                $query .= "INSERT INTO `nodetypes_avail` VALUES (1,'webClient',1,1,1,'app'),(2,'backendClient',2,0,3,'backend'),(3,'Farmer',3,1,2,'chianode'),(4,'Harvester',4,1,2,'chianode'),(5,'Wallet',5,1,2,'chianode'),(6,'Unknown',99,0,2,'');";
                $query .= "INSERT INTO `nodetype` VALUES (1,1,1),(2,2,2);";

                $this->db_api->execute($query,[]);

                echo "\n  [SUC]Default entries inserted successfully.\n";

                //2d. Checking if all entries were inserted
                echo "[STEP]Verifying database installation.";
                $check_array = [
                [ "statement" => "SELECT COUNT(*) AS count FROM nodes;", "count" => 2 , "table" => "nodes" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM system_settings;", "count" => 3 , "table" => "system_settings" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM system_infos;", "count" => 1 , "table" => "system_infos" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM users;", "count" => 1 , "table" => "users" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM users_settings;", "count" => 1 , "table" => "users_settings" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM sites;", "count" => 13 , "table" => "sites" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM sites_pagestoinform;", "count" => 25 , "table" => "sites_pagestoinform" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM nodetypes_avail;", "count" => 6 , "table" => "nodetypes_avail" ],
                [ "statement" => "SELECT COUNT(*) AS count FROM nodetype;", "count" => 2 , "table" => "nodetype" ]
                ];

                foreach($check_array AS $arrkey => $db_check){
                    $sql = $this->db_api->execute($db_check["statement"],[]);
                    $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];
                    if($count ==  $db_check["count"]){
                        echo "\n  [SUC]Table {$db_check["table"]} seems to be correct.";
                    }else{
                        echo "\n  [ERR]Table {$db_check["table"]} seems not to be correct. MySQL returned {$count} rows but it should be {$db_check["count"]}.";
                        return false;
                    }
                }
            }catch(\Exception $e){
                echo "\n  [ERR]An databse error occured. Please check the following message.";
                print_r($e->getMessage());
                return false;
            }

            return true;
        }
    
        /**
         * Generates a random string.
         * @param  integer $length  The length of the string which should be generated.
         * @return string           Some random string.
         */
        private function generateRandomString($length = 50): string
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }
    }