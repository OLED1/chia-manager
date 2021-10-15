<?php
  namespace ChiaMgmt\System_Update;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\System\System_Api;

  /**
   * The System_Update_Api class handles the webgui update tasks.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class System_Update_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Variable for handling and detecting previous errors in update process.
     * @var boolean
     */
    private $preverror;
    /**
     * The server configuration file.
     * @var array
     */
    private $ini;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $config_file = __DIR__.'/../../config/config.ini.php';
      if(file_exists($config_file)){
        $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
        if(array_key_exists("db_name", $this->ini)){
          $this->db_api = new DB_Api();
          $this->websocket_api = new WebSocket_Api();
          $this->logging_api = new Logging_Api($this);
          $this->server = NULL;
          $this->preverror = false;
        }
      }
    }

    public function setInstanceUpdating(array $data = [], array $loginData = NULL){
      if(array_key_exists("userid", $data) && array_key_exists("updatestate", $data)){
        try{
          $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, process_update = ?", array($data["userid"],$data["updatestate"]));

          return array("status" => 0, "message" => "Successfully set updater mode.");
        }catch(\Throwable $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "No all data stated.");
      }
    }

    /**
     * Checks for system updates.
     * Function made for: Web(App)client
     * @param  array  $data       { "updatechannel" : "[main|staging|dev|NULL]" }
     * @param  array $loginData   { NULL } No logindata is needed query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Available updateinformation]}
     */
    public function checkForUpdates(array $data = [], array $loginData = NULL, array $updatechannel = NULL){
      if(is_Null($updatechannel)){
        $system_api = new System_Api();
        $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");
      }

      if(array_key_exists("updatechannel", $updatechannel["data"])){
        $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
      }else{ $updatechannel = "main"; }

      $url = "https://files.chiamgmt.edtmair.at/server/versions.json";
      $json = file_get_contents($url);
      $json_data = json_decode($json, true);

      if(array_key_exists($updatechannel, $json_data)){
        if(array_key_exists("0", $json_data[$updatechannel])){
          $myversion = $this->ini["versnummer"];
          $remoteversion = $json_data[$updatechannel][0]["version"];

          if(version_compare($myversion, $remoteversion) < 0) $updateavailable = true;
          else $updateavailable = false;

          return array("status" => 0, "message" => "Successfully loaded updatedata and versions.", "data" => array("localversion" => $myversion, "remoteversion" => $remoteversion, "updateavail" => $updateavailable, "updatechannel" => $updatechannel));
        }else{
          $returndata = $this->logging_api->getErrormessage("001");
          $returndata["data"] = array("localversion" => $this->ini["versnummer"], "updatechannel" => $updatechannel);
          return $returndata;
        }
      }else{
        return $this->logging_api->getErrormessage("002", "Updatechannel {$updatechannel} not found.");
      }
    }

    public function checkUpdateRoutine(){
      try{
        if(is_null($this->ini) && !array_key_exists("db_name", $this->ini)) return array("status" => 0, "message" => "Successfully queried system update state.", "data" => array("db_install_needed" => true));

        $sql = $this->db_api->execute("SHOW TABLES LIKE 'system_infos'", array());
        $tablefound = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(count($tablefound) == 0){
          $returndata["db_install_needed"] = true;
        }else{
          $sql = $this->db_api->execute("SELECT dbversion, userid_updating, process_update, lastsucupdate, maintenance_mode FROM system_infos", array());
          $returndata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];
          if(version_compare($returndata["dbversion"], $this->ini["versnummer"]) < 0 || $returndata["process_update"] == 1){
            $returndata["process_update"] = true;
          }
        }

        return array("status" => 0, "message" => "Successfully queried system update state.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function checkServerDependencies(){
      $php_required = "7.4.0";
      $phpversion = phpversion();
      $versioncompare = version_compare($phpversion, $php_required, ">=");

      $needed_modules = ["Core", "date", "libxml", "openssl", "pcre", "zlib", "filter", "hash", "Reflection", "SPL", "session", "standard", "sodium", "cgi-fcgi", "mysqlnd", "PDO", "xml", "apcu", "bcmath", "bz2", "calendar", "ctype", "curl", "dom", "mbstring", "FFI", "fileinfo", "ftp", "gd", "gettext", "gmp", "iconv", "igbinary", "imagick", "intl", "json", "exif", "msgpack", "mysqli", "pdo_mysql", "apc", "posix", "readline", "redis", "shmop", "SimpleXML", "sockets", "sysvmsg", "sysvsem", "sysvshm", "tidy", "tokenizer", "xmlreader", "xmlrpc", "xmlwriter", "xsl", "zip", "Phar", "memcached", "Zend OPcache"];
      $diff = array_diff(get_loaded_extensions(), $needed_modules);

      $returndata = [];
      if(!$versioncompare){
        $returndata["php-version"]["status"] = 1;
        $returndata["php-version"]["message"] = "Installed PHP Version {$phpversion} does not meet requirement {$php_required}.";
      }else{
        $returndata["php-version"]["status"] = 0;
        $returndata["php-version"]["message"] = "Installed PHP Version {$phpversion} meets requirement {$php_required}.";
      }

      if(count($diff) > 0){
        foreach($diff AS $arrkey => $modulename){
          $modules_missing .= $modulename . " ";
        }
        $returndata["php-modules"]["status"] = 1;
        $returndata["php-modules"]["message"] = "The following PHP modules were not found {$modules_missing}.";
      }else{
        $returndata["php-modules"]["status"] = 0;
        $returndata["php-modules"]["message"] = "All needed PHP modules are installed.";
      }

      return array("status" => 0, "message" => "Successfully loaded dependencies.", "data" => $returndata);
    }

    public function checkMySQLConfig(string $db_name, string $db_user, string $db_password, string $db_host){
        try{
          $db_api = new DB_Api();
          return $try_con = $db_api->testConnection($db_name, $db_host, $db_user, $db_password);
        }catch(\Throwable $e){
          return array("status" => 1, "message" => $e->getMessage());
        }
    }

    public function installChiamgmt(string $branch, array $db_config, array $websocket_config, array $webgui_user_config){
      //Default the returnvalues to error
      $returnarray["status"] = 1;
      $returnarray["message"] = "An error occured during installation process.";

      $updatepackagepath = "https://files.chiamgmt.edtmair.at/server/";

      $returnarray = [];
      $noncekey = $this->generateRandomString();
      $dbsalt = $this->generateRandomString();
      $serversalt = $this->generateRandomString();
      $web_client_auth_hash = $this->generateRandomString();
      $backend_client_auth_hash = $this->generateRandomString();
      $configdir = "{$_SERVER["DOCUMENT_ROOT"]}/backend/config/";
      $tmpdir = "/tmp";

      //1. Create Config File
      $returnarray["data"]["config_file"] = array("status" => 0, "message" => "Config file created. Default values are: ");
      $returnarray["data"]["config_file"]["data"] = [];
      //1a. Downloading latest version info from used branch
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);

      $version = $version_file_data[$branch][0]["version"];
      if(is_null($version)){
        $returnarray["data"]["config_file"] = array("status" => 1, "message" => "Error during version number query.");
        array_push($returnarray["data"]["config_file"]["data"], array("status" => 1, "message" => "Could not load latest version number from {$updatepackagepath}/versions.json."));
        return $returnarray;
      }
      //1b. Create config
      $config =
        ";<?php\n" .
        ";die(); // For further security\n" .
        ";/*\n" .
        "[database]\n" .
        "db_name     = '{$db_config["databasename"]}'\n" .
        "db_user     = '{$db_config["mysqluser"]}'\n" .
        "db_password = '{$db_config["mysqlpassword"]}'\n" .
        "db_host = '{$db_config["mysqlhost"]}'\n" .
        "\n" .
        "[application]\n" .
        "app_protocol = 'https'\n" .
        "app_domain = '{$_SERVER["HTTP_HOST"]}'\n" .
        "system_root = '/'\n" .
        "backend_url = '/backend'\n" .
        "frontend_url = '/frontend'\n" .
        "backup_root = '/backup'\n" .
        "serversalt = '{$serversalt}'\n" .
        "nonce_key = '{$noncekey}'\n" .
        "versnummer = '{$version}'\n" .
        "\n" .
        "[websocket]\n" .
        "web_client_auth_hash = '{$web_client_auth_hash}'\n" .
        "backend_client_auth_hash = '{$backend_client_auth_hash}'\n" .
        "socket_protocol = 'wss'\n" .
        "socket_domain = '{$_SERVER["HTTP_HOST"]}'\n" .
        "socket_listener = '{$websocket_config["socket_protocol"]}'\n" .
        "socket_local_port = '{$websocket_config["socket_local_port"]}'\n" .
        "\n" .
        "[extapis]\n" .
        "netspace_api = 'https://api.chiaprofitability.com/netspace'\n" .
        "market_api = 'https://api.chiaprofitability.com/market'\n" .
        "exchangerate_api_codes = 'https://cdn.jsdelivr.net/gh/fawazahmed0/currency-api@1/latest/currencies.json'\n" .
        "exchangerate_api_rates = 'https://cdn.jsdelivr.net/gh/fawazahmed0/currency-api@1/latest/currencies/usd.json'\n" .
        ";*/";

      //1c. Writing config
      $configfile = fopen("{$configdir}/config.ini.php", "w");
      fwrite($configfile, $config);
      fclose($configfile);

      //1d. Verifying config
      $configfile = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      if(!is_array($configfile)){
        $returnarray["data"]["config_file"] = array("status" => 1, "message" => "Error during config file creation.");
        array_push($returnarray["data"]["config_file"]["data"], array("status" => 0, "message" => "The config file were not created successfully."));
        return $returnarray;
      }
      array_push($returnarray["data"]["config_file"]["data"], array("status" => 0, "message" => "Successfully created config file. Nonce_key for secure js loading:&nbsp;{$noncekey}."));

      //2. Creating Database
      $returnarray["data"]["db_config"] = array("status" => 0, "message" => "Database imported and installed default values.");
      $returnarray["data"]["db_config"]["data"] = [];
      try{
        //2a. Instance db connection
        $this->db_api = new DB_Api();

        //2b. Importing structure dump
        $query = '';
        $structuredump = file("files/chiamgmt-structure.sql");
        foreach ($structuredump as $line) {
          $startWith = substr(trim($line), 0 ,2);
          $endWith = substr(trim($line), -1 ,1);

          if (empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') {
            continue;
          }

          $query = $query . $line;
          if($endWith == ';'){
            $this->db_api->execute($query,[]);
            $query= '';
          }
        }
        array_push($returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Table structure created successfully."));
        //2c. Insert default values
        $this->encryption_api = new Encryption_Api();
        //Default Nodes
        $query = '';
        $web_client_auth_hash = $this->encryption_api->encryptString($web_client_auth_hash);
        $backend_client_auth_hash = $this->encryption_api->encryptString($backend_client_auth_hash);
        $query = "INSERT INTO `nodes` VALUES (1,'{$web_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,1,'','',0,NOW()), (2,'{$backend_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,3,'','',0,NOW());";
        //Default Nodetypes
        $query .= "INSERT INTO `nodetype` VALUES (1,1,1),(2,2,2);";
        //Default nodetypes available
        $query .= "INSERT INTO `nodetypes_avail` VALUES (1,'webClient',1,1,1,'app'),
                                                  (2,'backendClient',2,0,3,'backend'),
                                                  (3,'Farmer',3,1,2,'chianode'),
                                                  (4,'Harvester',4,1,2,'chianode'),
                                                  (5,'Wallet',5,1,2,'chianode'),
                                                  (6,'Unknown',99,0,2,'');";
        //Default sites
        $query .= "INSERT INTO `sites` VALUES (1,'ChiaMgmt\\\\MainOverview\\\\MainOverview_Api'),
                                        (2,'ChiaMgmt\\\\Nodes\\\\Nodes_Api'),
                                        (3,'ChiaMgmt\\\\System\\\\System_Api'),
                                        (4,'ChiaMgmt\\\\Users\\\\Users_Api'),
                                        (5,'ChiaMgmt\\\\Chia_Wallet\\\\Chia_Wallet_Api'),
                                        (6,'ChiaMgmt\\\\Chia_Farm\\\\Chia_Farm_Api'),
                                        (7,'ChiaMgmt\\\\Chia_Harvester\\\\Chia_Harvester_Api'),
                                        (8,'ChiaMgmt\\\\Chia_Infra_Sysinfo\\\\Chia_Infra_Sysinfo_Api'),
                                        (9,'ChiaMgmt\\\\Chia_Overall\\\\Chia_Overall_Api'),
                                        (10,'ChiaMgmt\\\\System_Update\\\\System_Update_Api');";

        //Default sites_pagestoinform
        $query .= "INSERT INTO `sites_pagestoinform` VALUES (1,1,1),(2,2,2),(3,2,1),(4,3,3),(5,4,4),
                                                      (6,5,5),(7,5,1),(8,6,6),(9,6,1),(10,7,7),
                                                      (11,7,1),(12,2,5),(13,2,6),(14,2,7),(15,8,8),
                                                      (16,8,1),(17,2,8),(18,9,1),(19,10,1);";

        //Default system_settings
        $query .= "INSERT INTO `system_settings` VALUES (1,'mailing','{}',0),(2,'security','{\"TOTP\": {\"value\": \"0\"}}',0),(3,'updatechannel','{\"branch\": {\"value\": \"{$branch}\"}}',0);";

        //Default system_infos
        $query .= "INSERT INTO `system_infos` VALUES (1,'{$version}',0,NOW(),0);";

        //Set default user settings
        $query .= "INSERT INTO `users_settings` VALUES (1,1,'usd',2);";

        //Default user (admin) as configured in installer
        $userpassword = hash('sha256',$webgui_user_config["gui-password"].$dbsalt.$serversalt);
        $query .= "INSERT INTO `users` VALUES (1,'{$webgui_user_config["gui-username"]}','{$webgui_user_config["gui-forename"]}','{$webgui_user_config["gui-lastname"]}','{$userpassword}','{$dbsalt}','{$webgui_user_config["gui-email"]}',NOW(),1);";

        $this->db_api->execute($query,[]);
        array_push($returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Default entries inserted successfully."));
      }catch(\Throwable $e){
        $returnarray["data"]["db_config"]["status"] = 1;
        $returnarray["data"]["db_config"]["message"] = "Error during database configuration.";
        array_push($returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => $e->getMessage()));
        return $returnarray;
      }

      $returnarray["status"] = 0;
      $returnarray["message"] = "Finished installation.";
      return $returnarray;
    }

    public function checkFilesWritable(){
      $not_accessable = [];
      $whitelist = [".htaccess", "config.ini.php.example"];

      foreach(
       $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator("../../..{$this->ini["system_root"]}/", \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST) as $item
      ){
        if(str_contains($iterator->getSubPathname(), "backup" )){
          continue;
        }else{
          if(in_array($item->getFilename(), $whitelist)) continue;

          if(!(is_readable($item) && is_writeable($item))){
            array_push($not_accessable, "/{$iterator->getSubPathname()}");
          }
        }
      }

      if(count($not_accessable) > 0){
        $apacheuser = exec('whoami');
        return array("status" => 1, "message" => "Some files are not fully accessable. Please adjust the file owner and group to {$apacheuser} and set 750 as file rights.", "data" => $not_accessable);
      }else{
        return array("status" => 0, "message" => "All neded files are fully accessable.");
      }
    }

    public function processUpdate(array $data, array $loginData = NULL, $server = NULL){
      $this->server = $server;
      $now = new \DateTime();
      $now = $now->format("Y-m-d H:i:s");

      $this->sendStatus(0, 0, 2, "Starting update");
      $this->enableUpdateMode($loginData["userid"]);
      $this->createBackupdirs("Creating backup directories", $now);
      if(!$this->preverror) $this->backupSystemData("Backing up system data", $now);
      if(!$this->preverror) $this->backupSystemDatabase("Backing up databasedata", $now);
      if(!$this->preverror) $this->downloadUpdateData("Downloading and installing update");

      if($this->preverror){
        return $this->logging_api->getErrormessage("001");
      }else{
        return array("status" => 0, "message" => "Update process success.");
      }
    }

    public function setMaintenanceMode(int $userid, int $maintenance_mode){
      if($userid > 0 && $maintenance_mode == 0 || $maintenance_mode == 1){
        try{
          $sql = $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, maintenance_mode = ?",
          array($userid, $maintenance_mode));

          return array("status" => 0, "message" => "Successfully set maintenance mode to {$maintenance_mode}.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return array("status" => 1, "message" => "Could not set maintenance mode to {$maintenance_mode}. Some data is missing.");
      }
    }

    public function stopWebsocketServer(){
      return $this->websocket_api->stopWSS();
    }

    public function createBackups(){
      $now = new \DateTime();
      $now = $now->format("Y-m-d H:i:s");

      $createBackupDirs = $this->createBackupdirs($now);
      if($createBackupDirs["status"] == 0){
        $backupSystemDB = $this->backupSystemDatabase($now);
        if($backupSystemDB["status"] == 0){
          $backupSystemData = $this->backupSystemData($now);
          if($backupSystemData["status"] == 0){
            return array("status" => 0, "message" => "Successfully created backup structure and backed up mysql databse and file structure.");
          }else{
            return $backupSystemData;
          }
        }else{
          return $backupSystemDB;
        }
      }else{
        return $createBackupDirs;
      }
    }

    public function finishUpdate(array $data, array $loginData = NULL){
      $db_update_file = file_get_contents(__DIR__ . "/db_update.json");
      $db_update_json = json_decode($db_update_file, true);

      if(array_key_exists("table_structures", $db_update_json)){
        try{
          $db_system = $this->checkUpdateRoutine();

          if($db_system["data"]["db_update_needed"] < 0){
            foreach($db_update_json["table_structures"] AS $tablename => $tablechecks){
              $sql = $this->db_api->execute("SHOW TABLES LIKE '{$tablename}'", array());

              if(count($sql->fetchAll(\PDO::FETCH_ASSOC)) > 0){
                foreach($tablechecks["existing"] AS $columnname => $columndata){
                  $sql = $this->db_api->execute("SHOW COLUMNS FROM {$tablename} WHERE Field = ?", array($columnname));
                  if(count($sql->fetchAll(\PDO::FETCH_ASSOC)) == 0){
                    $this->db_api->execute("ALTER TABLE {$tablename} ADD {$columnname} {$columndata}", array());
                  }
                }
              }else{
                foreach($tablechecks["notexisting"] AS $arrkey => $statement){
                  $this->db_api->execute("{$statement}", array());
                }
              }
            }
          }

          $now = new \DateTime("now");
          $sql = $this->db_api->execute("UPDATE system_infos SET dbversion = ?, userid_updating = ?, lastsucupdate = ?, maintenance_mode = ?",
                                        array($this->ini["versnummer"], 0, $now->format("Y-m-d H:i:s"), 0));
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully finished update to version {$this->ini["versnummer"]}.");
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function disableMaintenanceMode(array $data, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("UPDATE system_infos SET maintenance_mode = ?",
                                      array(0));

        return array("status" => 0, "message" => "Successfully disabled maintenance mode.");
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    private function createBackupdirs(string $timestamp){
      $backupdir = "../../..{$this->ini["backup_root"]}";

      if(!file_exists($backupdir)) {
        if(!mkdir($backupdir, 0777, true)){
          return array("status" => 1, "message" => "Backup directory {$backupdir} could not be created.");
        }
      }

      $thisbackupdir = "{$backupdir}/{$timestamp}";
      if(!file_exists($thisbackupdir)) {
        if(!mkdir($thisbackupdir, 0777, true)){
          return array("status" => 1, "message" => "Backup directory {$thisbackupdir} could not be created.");
        }
      }

      $filesdir = "{$thisbackupdir}/files";
      if(!file_exists($filesdir)) {
        if(!mkdir($filesdir, 0777, true)){
          return array("status" => 1, "message" => "Backup directory {$filesdir} could not be created.");
        }
      }

      $mysqldir = "{$thisbackupdir}/db";
      if(!file_exists($mysqldir)) {
        if(!mkdir($mysqldir, 0777, true)){
          return array("status" => 1, "message" => "Backup directory {$mysqldir} could not be created.");
        }
      }

      return array("status" => 0, "message" => "All directories successfully created.");
    }

    private function backupSystemDatabase(string $timestamp){
      $targetdir = "../../..{$this->ini["backup_root"]}/{$timestamp}/db/mysq_backup.sql";
      exec("mysqldump --user={$this->ini["db_user"]} --password={$this->ini["db_password"]} --host={$this->ini["db_host"]} {$this->ini["db_name"]} --result-file='{$targetdir}' 2>&1", $output, $exitCode);

      if($exitCode == 0) return array("status" => 0, "message" => "Successfully backup up system database.");
      return array("status" => 1, "message" => "An error occured backing up system database.");
    }

    private function backupSystemData(string $timestamp){
      $source = "../../..{$this->ini["system_root"]}";
      $dest = "../../..{$this->ini["backup_root"]}/{$timestamp}/files/";
      $this->zipBackup($source, $dest, $timestamp);
      return $this->checkZipValid("{$dest}/backup-{$timestamp}.zip");
    }

    private function zipBackup($source, $dest, $timestamp){
      $zip = new \ZipArchive();
      $zip->open("{$dest}/backup-{$timestamp}.zip", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

      /** @var \SplFileInfo[] $files */
      $files = new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
          \RecursiveIteratorIterator::LEAVES_ONLY
      );

      foreach ($files as $name => $file){
          if(!$file->isDir() && !str_contains($file->getRealPath(), "backup")){
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);
            $zip->addFile($filePath, $relativePath);
          }
      }
      $zip->close();
    }

    private function checkZipValid(string $zipfile){
      if(file_exists($zipfile)){
        $zip = new \ZipArchive();
        $res = $zip->open($zipfile, \ZipArchive::CHECKCONS);
        if ($res !== TRUE) {
          switch($res) {
            case \ZipArchive::ER_NOZIP:
              return array("status" => 1, "message" => "An error occured. File is not a zip.");
            case \ZipArchive::ER_INCONS :
              return array("status" => 1, "message" => "An error occured. Zip consistency check failed.");
            case \ZipArchive::ER_CRC :
              return array("status" => 1, "message" => "An error occured. Zip checksum failed.");
            default:
              return array("status" => 0, "message" => "Zip file valid.");
          }
        }
      }else{
        return array("status" => 1, "message" => "An error occured. Zip not found.");
      }
    }

    public function downloadUpdateFiles(){
      $version_file_data = $this->getVersionFileData();
      $updatechannel = $version_file_data["updatechannel"];
      $version_file_data = $version_file_data["versionfiledata"];
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/server/";

      if(!is_null($version_file_data) && array_key_exists($updatechannel, $version_file_data)){
        $updateurl = $version_file_data[$updatechannel][0]["link"];
        $tmpdir = "/tmp";
        if(is_dir($tmpdir)){
          $packagepath = "{$updatepackagepath}{$updateurl}";
          $tmpfiledir = "{$tmpdir}/chiamgmt_update.zip";

          file_put_contents($tmpfiledir,file_get_contents($packagepath));

          $zipcheck = $this->checkZipValid($tmpfiledir);
          if($zipcheck["status"] == 0){
            return array("status" => 0, "message" => "Successfully downloaded update {$packagepath} to {$tmpfiledir}.");
          }else{
            return $zipcheck;
          }
        }else{
          return array("status" => 1, "message" => "Temporary directory {$tmpdir} for update downloading not found or not accessable.");
        }
      }else if(is_null($version_file_data)){
        return array("status" => 1, "message" => "Versionfile could not be downloaded from {$versionfilepath}.");
      }else if(!array_key_exists($updatechannel, $version_file_data)){
        return array("status" => 1, "message" => "Configured updatechannel {$updatechannel} was not found in version file.");
      }
    }

    public function extractAndMoveUpdateFiles(){
      $tmpdir = "/tmp";
      $tmpfiledir = "{$tmpdir}/chiamgmt_update.zip";
      $zipcheck = $this->checkZipValid($tmpfiledir);

      if($zipcheck["status"] == 0){
        $version_file_data = $this->getVersionFileData();
        $updatechannel = $version_file_data["updatechannel"];
        $version_file_data = $version_file_data["versionfiledata"];

        $zip = new \ZipArchive;
        $res = $zip->open($tmpfiledir);
        if ($res === TRUE) {
          $zip->extractTo($tmpdir);
          $zip->close();

          $this->full_copy("{$tmpdir}/chia-web-gui-{$updatechannel}/", "../../..{$this->ini["system_root"]}/");
          return array("status" => 0, "message" => "Successfully moved new files in place.");
        }else{
          return array("status" => 1, "message" => "Could not open {$tmpfiledir}.");
        }
      }else{
        return $zipcheck;
      }
    }

    public function checkAndAdjustDatabase(){
      $config_file = __DIR__.'/../../config/config.ini.php';
      $config_data = parse_ini_file($config_file, true);
      $db_update_json = file_get_contents("files/db_update.json");
      $db_update_array = json_decode($db_update_json, true);
      $alteredtables = [];

      try{
        foreach($db_update_array AS $version => $tables){
          if(version_compare($config_data["application"]["versnummer"], $version)){
            foreach($tables AS $tablename => $statements){
              foreach($statements AS $arrkey => $statement){
                $sql = $this->db_api->execute($statement, array());
              }
            }
            array_push($alteredtables, $tablename);
          }
        }
      }catch(\Throwable $e){
        return array("status" => 1, "message" => "An error occured. DB Message: {$e->getMessage()}");
      }

      return array("status" => 0, "message" => "Altered tables " . implode(",", $alteredtables) . " successfully. DB version updated successfully.");
    }

    public function startWebsocketServer(){
      return $this->websocket_api->startWSS();
    }

    public function updateConfigFile(){
      $config_file = __DIR__.'/../../config/config.ini.php';
      $config_data = parse_ini_file($config_file, true);

      $version_file_data = $this->getVersionFileData();
      $updatechannel = $version_file_data["updatechannel"];
      $version_file_data = $version_file_data["versionfiledata"];
      $newversion = $version_file_data[$updatechannel][0]["version"];

      $key = "application";
      $section = "versnummer";
      $value = $newversion;

      $config_data[$key][$section] = $value;
      $new_content = '';
      foreach ($config_data as $section => $section_content) {
          $section_content = array_map(function($value, $key) {
              return "$key='$value'";
          }, array_values($section_content), array_keys($section_content));
          $section_content = implode("\n", $section_content);
          $new_content .= "[$section]\n$section_content\n\n";
      }

      $new_content = ";<?php\n;die(); // For further security;\n;/*\n{$new_content};*/";
      file_put_contents($config_file, $new_content);

      $tempini = parse_ini_file($config_file);
      if(array_key_exists("versnummer", $tempini) && $tempini["versnummer"] == $newversion){
        return array("status" => 0, "message" => "Successfully set new version to {$newversion}.");
      }else {
        return array("status" => 1, "message" => "Could not set version in config.ini.php file.");
      }
    }

    private function getVersionFileData(){
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/server/";
      $versionfilepath = "{$updatepackagepath}/versions.json";
      $version_file_json = file_get_contents($versionfilepath);
      $version_file_data = json_decode($version_file_json, true);

      $system_api = new System_Api();
      $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");
      if(array_key_exists("updatechannel", $updatechannel["data"])){
        $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
      }else{ $updatechannel = "main"; }

      return array("versionfiledata" => $version_file_data, "updatechannel" => $updatechannel);
    }

    private function downloadUpdateData(){
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/server/";
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);

      $system_api = new System_Api();
      $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");
      if(array_key_exists("updatechannel", $updatechannel["data"])){
        $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
      }else{ $updatechannel = "main"; }

      if(!is_null($version_file_data) && array_key_exists($updatechannel, $version_file_data)){
        $updateurl = $version_file_data[$updatechannel][0]["link"];
        $tmpdir = "/tmp";
        if(is_dir($tmpdir)){
          $packagepath = "{$updatepackagepath}{$updateurl}";
          $tmpfiledir = "{$tmpdir}/chiamgmt_update.zip";

          file_put_contents($tmpfiledir,file_get_contents($packagepath));

          $zip = new \ZipArchive;
          $res = $zip->open($tmpfiledir);
          if ($res === TRUE) {
            $zip->extractTo($tmpdir);
            $zip->close();

            $this->full_copy("{$tmpdir}/chia-web-gui-{$updatechannel}/", "../../..{$this->ini["system_root"]}/");
            $newversion = $version_file_data[$updatechannel][0]["version"];
            if($this->updateConfigFile($newversion)){
              $this->sendStatus(0, 4, 0, $message);
            }else{
              $this->sendStatus(0, 4, 1, "{$message}. Could not set new version. Please enter it manually: {$newversion}");
            }
          }else{
            $this->preverror = true;
            $this->sendStatus(0, 4, 1, "{$message}. Could not open {$tmpfiledir}.");
            return;
          }
        }else{
          $this->preverror = true;
          $this->sendStatus(0, 4, 1, "{$message}. Temporary dir /tmp not found.");
          return;
        }
      }else{
        $this->preverror = true;
        $this->sendStatus(0, 4, 1, $message);
      }
    }

    private function full_copy($source, $dest){
      $blacklist = [".htaccess", "System_Update_Api.php", "System_Update_Rest.php","db_update.json"];

      if(!file_exists($dest)) mkdir($dest, 0755);
      foreach(
       $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST) as $item
      ){
        if(str_contains($iterator->getSubPathname(), "backup" )){
          continue;
        }else{
          if($item->isDir()){
            mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }else{
            if(in_array($item->getFilename(), $blacklist)) continue;
            //echo "Copying {$item} -> " . $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname() ."\n";
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }
        }
      }
    }

    private function generateRandomString($length = 50) {
      $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < $length; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
      }
      return $randomString;
    }

    //processing-status: 0 Success, 1 Failed, 2 Processing
    private function sendStatus(int $status, int $step, int $processing_status, string $message){
      $now = new \DateTime("now");
      $time = $now->format("Y-m-d H:i:s");
      $this->server->messageFrontendClients(array("siteID" => 3), array("processingUpdate" => array("status" => $status, "step" => $step, "processing-status" => $processing_status, "message" => "{$message}: {$time}")));
    }
  }
?>
