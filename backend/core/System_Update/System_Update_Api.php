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
     * The server configuration file.
     * @var array
     */
    private $ini;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $config_file = __DIR__.'/../../config/config.ini.php';
      if(file_exists($config_file)){
        $this->ini = parse_ini_file($config_file);
        if(array_key_exists("db_name", $this->ini)){
          $this->db_api = new DB_Api();
          $this->websocket_api = new WebSocket_Api();
          $this->logging_api = new Logging_Api($this, $server);
        }
      }
    }

    /**
     * Marks this instance as updating. This will allow to open the installer/updater.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param array  $data       { userid: [userid], updatestate: [1 = Updating, 0 = Not updating]}
     * @param array $loginData   {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function setInstanceUpdating(array $data = [], array $loginData = NULL): array
    {
      if(array_key_exists("userid", $data) && array_key_exists("updatestate", $data)){
        try{
          $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, process_update = ?", array($data["userid"],$data["updatestate"]));

          return array("status" => 0, "message" => "Successfully set updater mode.");
        }catch(\Exception $e){
          $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Checks for system updates. Returns update specific data.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @param  array  $data       { "updatechannel" : "[main|staging|dev|NULL]" }
     * @param  array $loginData   { NULL } No logindata is needed query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Available updateinformation]}
     */
    public function checkForUpdates(array $data = [], array $loginData = NULL): array
    {
      if(!array_key_exists("updatechannel", $data)){
        $system_api = new System_Api();
        $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");

        if(array_key_exists("updatechannel", $updatechannel["data"])){
          $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
        }else{ $updatechannel = "main"; }
      }

      $url = "https://files.chiamgmt.edtmair.at/server/versions.json";
      $json = file_get_contents($url);
      $json_data = json_decode($json, true);

      if(!is_null($updatechannel) && !is_null($json_data) && array_key_exists($updatechannel, $json_data)){
        if(array_key_exists("0", array_keys($json_data[$updatechannel]))){
          $remoteversion = array_keys($json_data[$updatechannel])[0];
          $myversion = $this->ini["versnummer"];

          if(version_compare($myversion, trim($remoteversion)) < 0) $updateavailable = true;
          else $updateavailable = false;

          return array("status" => 0, "message" => "Successfully loaded updatedata and versions.", "data" => array("localversion" => $myversion, "remoteversion" => $remoteversion, "updateavail" => $updateavailable, "updatechannel" => $updatechannel));
        }else{
          $returndata = $this->logging_api->getErrormessage("002");
          $returndata["data"] = array("localversion" => $this->ini["versnummer"], "updatechannel" => $updatechannel);
        }
        return $returndata;
      }else{
        return $this->logging_api->getErrormessage("003", "Updatechannel {$updatechannel} not found.");
      }
    }

    /**
     * Checks if this instance needs to be installed or updated.
     * This method is used during the update and installation process.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : { "db_install_needed" : 1 / "process_update" : 1 // NULL }}
     */
    public function checkUpdateRoutine(): array
    {
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
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Checks if all PHP server dependencies are present.
     * This method is used during the installation process.
     * This method do not return formatted error messages, because they are not present at this time.
     * Function made for: Web(App)client
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkServerDependencies(): array
    {
      $php_required = "7.4.0";
      $phpversion = phpversion();
      $versioncompare = version_compare($phpversion, $php_required, ">=");

      $needed_modules = ["standard", "session", "json", "Core", "date", "hash", "filter", "pcre", "curl", "openssl", "mbstring", "iconv", "SPL", "igbinary", "tokenizer", "apcu", "readline", "sockets", "zlib", "intl", "posix", "sysvmsg", "ctype"];
      $diff = array_diff($needed_modules, get_loaded_extensions());

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

    /**
     * Checks if a database configuration is working.
     * This method is used during the installation process.
     * This method do not return formatted error messages, because they are not present at this time.
     * Function made for: Web(App)client
     * @throws Exception $e          Throws an exception on db errors.
     * @param  string $db_name       The databasename to which it should be connected.
     * @param  string $db_user       The database user which should be used.
     * @param  string $db_password   The database access password for the connection.
     * @param  string $db_host       The host where the instance is running. Either localhost or IP:PORT.
     * @return array                 {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkMySQLConfig(string $db_name, string $db_user, string $db_password, string $db_host): array
    {
        try{
          $db_api = new DB_Api();
          return $try_con = $db_api->testConnection($db_name, $db_host, $db_user, $db_password);
        }catch(\Exception $e){
          return array("status" => 1, "message" => $e->getMessage());
        }
    }

    /**
     * Installs the chiamgmt instance.
     * This method is used during the installation process.
     * This method do not return formatted error messages, because they are not present at this time.
     * Function made for: Web(App)client
     * @throws Exception $e                 Throws an exception on db errors.
     * @param  string $branch               Either dev, staging or main. Must be the same names as the github branches.
     * @param  array  $db_config            The complete databaseconfiguration. { "databasename" : [DBNAME], "mysqluser" : [LOGIN USER], "mysqlpassword" : [LOGIN PW], "mysqlhost" : [localhost or IP:PORT]}
     * @param  array  $websocket_config     The websocket configuration. { "socket_protocol" : [ws/wss], "socket_local_port" : [Default: 8443] }
     * @param  array  $webgui_user_config   The webgui admin user config. { "gui-username" : [adminusername], "gui-forename" : [Admin's Name],"gui-lastname" : [Admin's lastname], "gui-password" : [The login password], "gui-email" : [Admin's login email]}
     * @return array                        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function installChiamgmt(string $branch, array $db_config, array $websocket_config, array $webgui_user_config): array
    {
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

      $version = array_keys($version_file_data[$branch])[0];
      if(is_null($version)){
        $returnarray["data"]["config_file"] = array("status" => 1, "message" => "Error during version number query.");
        array_push($returnarray["data"]["config_file"]["data"], array("status" => 1, "message" => "Could not load latest version number from {$updatepackagepath}/versions.json."));
        return $returnarray;
      }
      //1b. Create config
      $config = file_get_contents(__DIR__."/installer_templates/config.txt");
      $config = str_replace("[databasename]", $db_config["databasename"], $config);
      $config = str_replace("[mysqluser]", $db_config["mysqluser"], $config);
      $config = str_replace("[mysqlpassword]", $db_config["mysqlpassword"], $config);
      $config = str_replace("[mysqlhost]", $db_config["mysqlhost"], $config);
      $config = str_replace("[app_domain]", $_SERVER["HTTP_HOST"], $config);
      $config = str_replace("[serversalt]", $serversalt, $config);
      $config = str_replace("[noncekey]", $noncekey, $config);
      $config = str_replace("[version]", $version, $config);
      $config = str_replace("[web_client_auth_hash]", $web_client_auth_hash, $config);
      $config = str_replace("[backend_client_auth_hash]", $backend_client_auth_hash, $config);
      $config = str_replace("[socket_listener]", $websocket_config["socket_protocol"], $config);
      $config = str_replace("[socket_local_port]", $websocket_config["socket_local_port"], $config);

      //1c. Create htaccess
      $htaccess = file_get_contents(__DIR__."/installer_templates/htaccess.txt");
      $htaccess = str_replace("[nonce]", $noncekey, $htaccess);

      //1d. Writing config
      $configfile = fopen("{$configdir}/config.ini.php", "w");
      fwrite($configfile, $config);
      fclose($configfile);

      //1e. Writing new htaccess
      $htaccessfile = fopen("{$_SERVER["DOCUMENT_ROOT"]}/.htaccess", "w");
      fwrite($htaccessfile, $htaccess);
      fclose($htaccessfile);

      //1f. Verifying config
      $configfile = parse_ini_file("{$configdir}/config.ini.php");
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
        $db_update_raw = file_get_contents("files/db_update.json");
        $db_update_json = json_decode($db_update_raw, true);
        $this->encryption_api = new Encryption_Api();
        //Default Nodes
        $query = '';
        $web_client_auth_hash = $this->encryption_api->encryptString($web_client_auth_hash);
        $backend_client_auth_hash = $this->encryption_api->encryptString($backend_client_auth_hash);
        $query = "INSERT INTO `nodes` VALUES (1,'{$web_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,1,'','',0,NOW()), (2,'{$backend_client_auth_hash}','localhost',NULL,NULL,NULL,NULL,1,3,'','',0,NOW());";

        //Default system_settings
        $query .= "INSERT INTO `system_settings` VALUES (1,'mailing','{}',0),(2,'security','{\"TOTP\": {\"value\": \"0\"}}',0),(3,'updatechannel','{\"branch\": {\"value\": \"{$branch}\"}}',0);";

        //Default system_infos
        $query .= "INSERT INTO `system_infos` VALUES (1,'{$version}',0,0,NOW(),0, NOW());";

        //Default user (admin) as configured in installer
        $userpassword = hash('sha256',$webgui_user_config["gui-password"].$dbsalt.$serversalt);
        $query .= "INSERT INTO `users` VALUES (1,'{$webgui_user_config["gui-username"]}','{$webgui_user_config["gui-forename"]}','{$webgui_user_config["gui-lastname"]}','{$userpassword}','{$dbsalt}','{$webgui_user_config["gui-email"]}',NOW(),1);";

        //Default admin user settings
        $query .= "INSERT INTO `users_settings` (`id`, `userid`, `currency_code`, `gui_mode`, `totp_enable`, `totp_secret`, `totp_proofen`) VALUES (NULL, '1', 'usd', '1', '0', NULL, '0');";

        $this->db_api->execute($query,[]);

        //Importing changes from version newer than 0.1.1
        foreach($db_update_json AS $versnummer => $statements){
          foreach($statements["statements"] AS $arrkey => $query){
            if(!str_contains($query,"ALTER")){
              $this->db_api->execute($query,[]);
            }
          }
        }


        array_push($returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Default entries inserted successfully."));
      }catch(\Exception $e){
        $returnarray["data"]["db_config"]["status"] = 1;
        $returnarray["data"]["db_config"]["message"] = "Error during database configuration.";
        array_push($returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => $e->getMessage()));
        return $returnarray;
      }

      $returnarray["status"] = 0;
      $returnarray["message"] = "Finished installation.";
      return $returnarray;
    }

    /**
     * Checks if all files in the directory are writable to the apache user.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @return array    {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkFilesWritable(): array
    {
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
        return $this->logging_api->getErrormessage("001");
      }else{
        return array("status" => 0, "message" => "All neded files are fully accessable.");
      }
    }

    /**
     * Enables or disables the maintenance mode.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param int $userid            The userid which is currently updating the instance.
     * @param int $maintenance_mode  { 0 = "Not updating" / 1 = "Updating" }
     * @return array                 {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function setMaintenanceMode(int $userid, int $maintenance_mode): array
    {
      if($userid > 0 && $maintenance_mode == 0 || $maintenance_mode == 1){
        try{
          $sql = $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, maintenance_mode = ?",
          array($userid, $maintenance_mode));

          return array("status" => 0, "message" => "Successfully set maintenance mode to {$maintenance_mode}.");
        }catch(\Throwable $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Stop the websocket server
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function stopWebsocketServer(): array
    {
      return $this->websocket_api->stopWSS();
    }

    /**
     * Creates backups of the instance.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function createBackups(): array
    {
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

    /**
     * Creates the directories needed for saving the backups.
     * Returns only 0 or 1 for errors because of specific error messages.
     * @param  string $timestamp  The timestmap for the backup.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function createBackupdirs(string $timestamp): array
    {
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

    /**
     * Creates a backup of the system database.
     * @param  string $timestamp  The timestmap for the backup.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function backupSystemDatabase(string $timestamp): array
    {
      $targetdir = "../../..{$this->ini["backup_root"]}/{$timestamp}/db/mysql_backup_{$timestamp}.sql";
      exec("mysqldump --user={$this->ini["db_user"]} --password={$this->ini["db_password"]} --host={$this->ini["db_host"]} {$this->ini["db_name"]} --result-file='{$targetdir}' 2>&1", $output, $exitCode);

      if($exitCode == 0) return array("status" => 0, "message" => "Successfully backup up system database.");
      return $this->logging_api->getErrormessage("001");
    }

    /**
     * Creates a backup of the instance filesystem.
     * @param  string $timestamp  The timestmap for the backup.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function backupSystemData(string $timestamp): array
    {
      $source = "../../..{$this->ini["system_root"]}";
      $dest = "../../..{$this->ini["backup_root"]}/{$timestamp}/files/";
      $this->zipBackup($source, $dest, $timestamp);
      return $this->checkZipValid("{$dest}/backup-{$timestamp}.zip");
    }

    /**
     * Creates a zip of the backed up instance files.
     * @param  string $source     The root directory of this instance.
     * @param  string $dest       The backup directory of this instance.
     * @param  string $timestamp  The timestmap for the backup.
     */
    private function zipBackup(string $source, string $dest, string $timestamp){
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

    /**
     * Checks if a zipfile is valid or corrupted.
     * @param  string $zipfile  The path to the zipfile which should be checked.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function checkZipValid(string $zipfile): array
    {
      if(file_exists($zipfile)){
        $zip = new \ZipArchive();
        $res = $zip->open($zipfile, \ZipArchive::CHECKCONS);
        if ($res !== TRUE) {
          switch($res) {
            case \ZipArchive::ER_NOZIP:
              return $this->logging_api->getErrormessage("001");
            case \ZipArchive::ER_INCONS :
              return $this->logging_api->getErrormessage("002");
            case \ZipArchive::ER_CRC :
              return $this->logging_api->getErrormessage("003");
          }
        }
        return array("status" => 0, "message" => "Zip file valid.");
      }else{
        return $this->logging_api->getErrormessage("004");
      }
    }

    /**
     * Download the files for the update and saves them to temporary storage (/tmp).
     * Returns only 0 or 1 for errors because of specific error messages.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function downloadUpdateFiles(): array
    {
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

    /**
     * Extracts the update files and move them in place.
     * Returns only 0 or 1 for errors because of specific error messages.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function extractAndMoveUpdateFiles(): array
    {
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

    /**
     * Alters the table after the update and sets the new version.
     * Returns only 0 or 1 for errors because of specific error messages.
     * @throws Exception $e       Throws an exception on db errors.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkAndAdjustDatabase(): array
    {
      $config_file = __DIR__.'/../../config/config.ini.php';
      $config_data = parse_ini_file($config_file, true);
      $db_update_json = file_get_contents(__DIR__."/files/db_update.json");
      $db_update_array = json_decode($db_update_json, true);
      $alteredtables = [];

      foreach($db_update_array AS $version => $strucuture_filepath){
        if(version_compare($config_data["application"]["versnummer"], $version, "<")){
          if(file_exists(__DIR__."/files/{$strucuture_filepath}")){
            $query = '';
            $strucuture_file = file(__DIR__."/files/{$strucuture_filepath}");
            foreach($strucuture_file as $line)	{
              $startWith = substr(trim($line), 0 ,2);
              $endWith = substr(trim($line), -1 ,1);

              if(empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') {
                continue;
              }

              $query = $query . $line;
              if($endWith == ';'){
                try{
                  $sql = $this->db_api->execute($query, array());
                  $query= '';
                }catch(\Exception $e){
                  $this->logging_api->getErrormessage("001", $e);
                  continue;
                }
              }
            }
          }
        }
      }

      return array("status" => 0, "message" => "Altered database successfully. DB version updated successfully.");
    }

    /**
     * Starts the websocket server.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function startWebsocketServer(): array
    {
      return $this->websocket_api->startWSS();
    }

    /**
     * Updates the config files with new values.
     * @param  string $newversion  The new version which should be set. When NULL the data from the $version_file_data will be set.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function updateConfigFile(string $newversion = NULL): array
    {
      $config_file = __DIR__.'/../../config/config.ini.php';
      $config_data = parse_ini_file($config_file, true);

      if(!is_writable($config_file)) return $this->logging_api->getErrormessage("001");

      if(is_null($newversion)){
        $version_file_data = $this->getVersionFileData();
        $updatechannel = $version_file_data["updatechannel"];
        $version_file_data = $version_file_data["versionfiledata"];
        $newversion = $version_file_data[$updatechannel][0]["version"];
      }

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
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Returns version and update specific values.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function getVersionFileData(): array
    {
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

    /**
     * Copies update files to the final destination.
     * @param  string $source The filesource which should be copied.
     * @param  string $dest   The destination where the files should be move to.
     */
    private function full_copy(string $source, string $dest){
      $blacklist = [".htaccess"];

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
            @mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }else{
            if(in_array($item->getFilename(), $blacklist)) continue;
            @copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }
        }
      }
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
?>
