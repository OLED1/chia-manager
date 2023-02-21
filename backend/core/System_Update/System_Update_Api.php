<?php
  namespace ChiaMgmt\System_Update;

  use React\Promise;
  use React\Http\Browser;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\System\System_Api;

  /*ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);*/

  require __DIR__ . '/../../../vendor/autoload.php';

  /**
   * The System_Update_Api class handles the webgui update tasks.
   * @version 0.1.2
   * @author OLED1 - Oliver Edtmair
   * @since 0.1
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
      $this->ini = NULL;
      $this->logging_api = NULL;
      if(file_exists($config_file)){
        $this->ini = parse_ini_file($config_file);
        if(array_key_exists("db_name", $this->ini)){
          $this->db_api = new DB_Api();
          $this->logging_api = new Logging_Api($this, $server);
          $this->websocket_api = new WebSocket_Api();
        }
      }
    }

    /**
     * Marks this instance as updating. This will allow to open the installer/updater.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param array  $data       { userid: [userid], updatestate: [1 = Updating, 0 = Not updating]}
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function setInstanceUpdating(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("userid", $data) && array_key_exists("updatestate", $data) && is_numeric($data["userid"]) && $data["userid"] > 0){
          $set_instance_updating = Promise\resolve((new DB_Api())->execute("UPDATE system_infos SET userid_updating = ?, process_update = ?", array($data["userid"],$data["updatestate"])));
          $set_instance_updating->then(function($set_instance_updating_returned) use(&$resolve, $data){
            $resolve(array("status" => 0, "message" => "Successfully set updater mode."));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("setInstanceUpdating", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("setInstanceUpdating", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks for system updates. Returns update specific data.
     * This method is used during the update process.
     * Function made for: Web(App)client
     * @param  array  $data       { "update_data_db" : [When set, data will be updated on db. Any value is valid.] }
     * @param  array $loginData   { NULL } No logindata is needed query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Available updateinformation]}
     */
    public function checkForUpdates(array $data = [], array $loginData = NULL): object
    {         
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(!array_key_exists("updatechannel", $data)){
          $updatechannel = Promise\resolve((new System_Api())->getSpecificSystemSetting("updatechannel"));
          $updatechannel->then(function($updatechannel_returned) use(&$resolve, $data){ 
            if(array_key_exists("updatechannel", $updatechannel_returned["data"])){
              $updatechannel_returned = $updatechannel_returned["data"]["updatechannel"]["branch"]["value"];
            }else{ 
              $updatechannel_returned = "main"; 
            }

            $updatedata = Promise\resolve((new DB_Api())->execute("SELECT id, channel, remoteversion, releasenotes, last_querytime as last_querytime, zipball FROM system_updates WHERE channel = ? AND last_querytime = (SELECT max(last_querytime) FROM system_updates) LIMIT 1", array($updatechannel_returned)));
            $updatedata->then(function($updatedata_returned) use(&$resolve, $updatechannel_returned, $data){
              $found_data = $updatedata_returned->resultRows;
              $query_every_minutes = 1;
              $now = new \DateTime("now");
              $last_query = $now;

              if(array_key_exists(0, $found_data) && array_key_exists("last_querytime", $found_data[0])){
                $found_data = $found_data[0];
                $last_query = new \DateTime($found_data["last_querytime"]);
                $last_query->modify("+" . $query_every_minutes . " minutes");
              }
              
              $updatedata_changed = false;
              if($now >= $last_query){
                $chia_manager_versionspath = "https://api.github.com/repos/OLED1/chia-manager/releases";

                $browser = new Browser();
                $browser_promise = $browser->get($chia_manager_versionspath)->then(
                  function ($chia_manager_version_result) use(&$resolve, $updatechannel_returned, $data, $updatedata_changed, $found_data){

                    $chia_manager_version_result = json_decode($chia_manager_version_result->getBody(), true);

                    $updatefile_arraykey = array_search($updatechannel_returned, array_column($chia_manager_version_result, 'target_commitish'));
                    if(!is_null($updatechannel_returned) && array_key_exists("0", $chia_manager_version_result) && is_numeric($updatefile_arraykey) && $updatefile_arraykey >= 0 && !array_key_exists("update_data", $data)){
                      if(array_key_exists("name", $chia_manager_version_result[$updatefile_arraykey]) && array_key_exists("update_data_db", $data)){
                        $remoteversion = $chia_manager_version_result[$updatefile_arraykey]["name"];
                        $myversion = $this->ini["versnummer"];
                        $releasenotes = $chia_manager_version_result[$updatefile_arraykey]["body"];
                        $zipball = $chia_manager_version_result[$updatefile_arraykey]["zipball_url"];
                        $querytime = new \DateTime("now");

                        if((array_key_exists("remoteversion", $found_data) && version_compare($found_data["remoteversion"], $remoteversion) > 0) || !array_key_exists(0, $found_data)){
                          $set_update_info = Promise\resolve((new DB_Api())->execute("INSERT INTO system_updates (id, channel, remoteversion, releasenotes, zipball, available_since, last_querytime) VALUES (NULL, ?, ?, ?, ?, NOW(), NOW())", array($updatechannel_returned, trim($remoteversion), $releasenotes, $zipball)));
                        }else{
                          $set_update_info = Promise\resolve((new DB_Api())->execute("UPDATE system_updates SET last_querytime = NOW(), releasenotes = ?, zipball = ? WHERE id = ?", array($releasenotes, $zipball, $found_data["id"])));
                        }

                        $set_update_info->otherwise(function (\Exception $e) use(&$resolve){
                          return $resolve($this->logging_api->getErrormessage("checkForUpdates", "005", $e));
                        });
                      }
                      $updatedata_changed = true;
                    }else{
                      $this->logging_api->getErrormessage("checkForUpdates", "004", "There are no releases with your selected updatechannel {$updatechannel} found.");
                    }

                    return $updatedata_changed;
                  },
                  function (\Exception $e) use(&$resolve, $chia_manager_versionspath, $updatedata_changed){
                    $this->logging_api->getErrormessage("checkForUpdates", "001", "The Chia-Manager github version file ({$chia_manager_versionspath}) could not be loaded. Message: " . json_encode($e->getMessage()));
                    return $updatedata_changed;
                  }
                );
              }else{
                $browser_promise = Promise\resolve($updatedata_changed);
              }

              $browser_promise->then(function($updatedata_changed) use(&$resolve, $found_data, $updatechannel_returned){
                if($updatedata_changed){
                  $new_updatedata = Promise\resolve((new DB_Api())->execute("SELECT id, channel, remoteversion, releasenotes, last_querytime as last_querytime, zipball FROM system_updates WHERE channel = ? AND last_querytime = (SELECT max(last_querytime) FROM system_updates) LIMIT 1", array($updatechannel_returned)));
                  $current_update_data = $new_updatedata->then(function($new_updatedata_returned){
                    return $new_updatedata_returned->resultRows[0];
                  })->otherwise(function (\Exception $e) use(&$resolve){
                    return $resolve($this->logging_api->getErrormessage("checkForUpdates", "006", $e));
                  });
                }else{
                  $current_update_data = Promise\resolve($found_data);
                }

                $current_update_data->then(function($current_update_data_returned) use(&$resolve){
                  $found_data = $current_update_data_returned;
                  $found_data["localversion"] = $this->ini["versnummer"];
                  if(version_compare($found_data["localversion"], trim($found_data["remoteversion"])) < 0) $found_data["updateavail"] = true;
                  else $found_data["updateavail"] = false;
          
                  $resolve(array("status" => 0, "message" => "Successfully queried last update data.", "data" => $found_data));
                });
              });
            })->otherwise(function (\Exception $e) use(&$resolve){
              return $resolve($this->logging_api->getErrormessage("checkForUpdates", "003", $e));
            });
          });
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
      
      try{
        if(!array_key_exists("updatechannel", $data)){
          $system_api = new System_Api();
          $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");
          
          if(array_key_exists("updatechannel", $updatechannel["data"])){
            $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
          }else{ $updatechannel = "main"; }
        }

        $sql = $this->db_api->execute("SELECT id, channel, remoteversion, releasenotes, last_querytime as last_querytime, zipball FROM system_updates WHERE channel = ? AND last_querytime = (SELECT max(last_querytime) FROM system_updates) LIMIT 1", array($updatechannel));
        $found_data = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/;
        $query_every_minutes = 1;
        $now = new \DateTime("now");
        $last_query = $now;

        if(array_key_exists(0, $found_data) && array_key_exists("last_querytime", $found_data[0])){
          $found_data = $found_data[0];
          $last_query = new \DateTime($found_data["last_querytime"]);
          $last_query->modify("+" . $query_every_minutes . " minutes");
        }

       
        $updatedata_changed = false;
        if($now >= $last_query){
          //Chia-Manager Github Versionfile
          $curl = curl_init();
          $chia_manager_versionspath = "https://api.github.com/repos/OLED1/chia-manager/releases";
          curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_URL, $chia_manager_versionspath);
          //We need to use curl, because Amazon AWS wants a user Agent set to be able to download the chia release file
          curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0');
          $chia_manager_version_result = json_decode(curl_exec($curl), true);
          curl_close($curl);

          if(is_null($chia_manager_version_result) || !array_key_exists(0, $chia_manager_version_result) || !array_key_exists("name", $chia_manager_version_result[0])){
            $overall = false;
            $this->logging_api->getErrormessage("001", "The Chia-Manager github version file ({$chia_manager_versionspath}) could not be loaded. Message: " . json_encode($chia_manager_version_result));
          }

          $updatefile_arraykey = array_search($updatechannel, array_column($chia_manager_version_result, 'target_commitish'));
          if(!is_null($updatechannel) && array_key_exists("0", $chia_manager_version_result) && is_numeric($updatefile_arraykey) && $updatefile_arraykey >= 0 && !array_key_exists("update_data", $data)){
            if(array_key_exists("name", $chia_manager_version_result[$updatefile_arraykey]) && array_key_exists("update_data_db", $data)){
              $remoteversion = $chia_manager_version_result[$updatefile_arraykey]["name"];
              $myversion = $this->ini["versnummer"];
              $releasenotes = $chia_manager_version_result[$updatefile_arraykey]["body"];
              $zipball = $chia_manager_version_result[$updatefile_arraykey]["zipball_url"];
              $querytime = new \DateTime("now");

              if((array_key_exists("remoteversion", $found_data) && version_compare($found_data["remoteversion"], $remoteversion) > 0) || !array_key_exists(0, $found_data)){
                $this->db_api->execute("INSERT INTO system_updates (id, channel, remoteversion, releasenotes, zipball, available_since, last_querytime) VALUES (NULL, ?, ?, ?, ?, NOW(), NOW())", array($updatechannel, trim($remoteversion), $releasenotes, $zipball));
              }else{
                $this->db_api->execute("UPDATE system_updates SET last_querytime = NOW(), releasenotes = ?, zipball = ? WHERE id = ?", array($releasenotes, $zipball, $found_data["id"]));
              }
            }
            $updatedata_changed = true;
          }else{
            $returndata = $this->logging_api->getErrormessage("003", "There are no releases with your selected updatechannel {$updatechannel} found.");
          }
        }

        if($updatedata_changed){
          $sql = $this->db_api->execute("SELECT id, channel, remoteversion, releasenotes, last_querytime as last_querytime, zipball FROM system_updates WHERE channel = ? AND last_querytime = (SELECT max(last_querytime) FROM system_updates) LIMIT 1", array($updatechannel));
          $found_data = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/[0];
        }

        $found_data["localversion"] = $this->ini["versnummer"];
        if(version_compare($found_data["localversion"], trim($found_data["remoteversion"])) < 0) $found_data["updateavail"] = true;
        else $found_data["updateavail"] = false;


        return array("status" => 0, "message" => "Successfully queried last update data.", "data" => $found_data);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("003", $e);
      }
    }

    /**
     * Checks if this instance needs to be installed or updated.
     * This method is used during the update and installation process.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : { "db_install_needed" : 1 / "process_update" : 1 // NULL }}
     */
    public function checkUpdateRoutine(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        if(is_null($this->ini)) $resolve(array("status" => 0, "message" => "Successfully queried system update state.", "data" => array("db_install_needed" => true)));
      
        $check_table = Promise\resolve((new DB_Api())->execute("SHOW TABLES LIKE 'system_infos'", array()));
        $check_table->then(function($check_table_returned) use(&$resolve){
          if(count($check_table_returned->resultRows) == 0){
            $returndata["db_install_needed"] = true;
            $resolve($returndata);
          }else{
            $update_executing = Promise\resolve((new DB_Api())->execute("SELECT dbversion, userid_updating, process_update, lastsucupdate, maintenance_mode FROM system_infos", array()));
            $update_executing->then(function($update_executing_returned) use(&$resolve){
              $returndata = $update_executing_returned->resultRows[0];

              if(version_compare($returndata["dbversion"], $this->ini["versnummer"]) < 0 || $returndata["process_update"] == 1){
                $returndata["process_update"] = true;
              }

              $resolve(array("status" => 0, "message" => "Successfully loaded update data.", "data" => $returndata));
            })->otherwise(function (\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("checkUpdateRoutine", "001", $e));
            });
          }
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("checkUpdateRoutine", "002", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);

      try{
        if(is_null($this->ini)) return array("status" => 0, "message" => "Successfully queried system update state.", "data" => array("db_install_needed" => true));

        $sql = $this->db_api->execute("SHOW TABLES LIKE 'system_infos'", array());
        $tablefound = $sql;

        if(count($tablefound) == 0){
          $returndata["db_install_needed"] = true;
        }else{
          $sql = $this->db_api->execute("SELECT dbversion, userid_updating, process_update, lastsucupdate, maintenance_mode FROM system_infos", array());
          $returndata = $sql[0];

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

      $returndata["files-writeable"] = $this->checkFilesWritable();

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
     * Checks if the configured WSS Port is not used on the system and is a high port.
     *
     * @param integer $port           The port which the user wants to use.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkWSSPortAvailable(int $port): array{
      if($port > 1024){
        if(@fsockopen("localhost", $port)){
          return array("status" => 1, "message" => "Port {$port} already in use.");
        }else{
          return array("status" => 0, "message" => "This port is not in use.");
        }
      }else{
        return array("status" => 1, "message" => "Port must be higher than 1024.");
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
      $this->returnarray = [];
      $this->returnarray["status"] = 1;
      $this->returnarray["message"] = "An error occured during installation process.";
      $this->returnarray["data"] = [];
      $this->returnarray["data"]["config_file"] = ["data" => []];

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
      //1a. Downloading latest version info from used branch
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);

      $version = array_keys($version_file_data[$branch])[0];
      if(is_null($version)){
        array_push($this->returnarray["data"]["config_file"]["data"], array("status" => 1, "message" => "Error during version number query."));
        array_push($this->returnarray["data"]["config_file"]["data"], array("status" => 1, "message" => "Could not load latest version number from {$updatepackagepath}/versions.json."));
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
      $config = str_replace("[socket_protocol]", "wss", $config);
      $config = str_replace("[socket_local_domain]", "localhost", $config);
      $config = str_replace("[socket_local_port]", $websocket_config["socket_local_port"], $config);
      $config = str_replace("[socket_listener]", $websocket_config["socket_protocol"], $config);

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
      $configfile = NULL;
      if(file_exists("{$configdir}/config.ini.php")){
        $configfile = parse_ini_file("{$configdir}/config.ini.php");
      }
      if(is_array($configfile)){
        $this->returnarray["data"]["config_file"] = array("status" => 0, "message" => "Config file successfully created.", "data" => []);
        array_push($this->returnarray["data"]["config_file"]["data"], array("status" => 0, "message" => "The config file were created successfully."));
      }else{
        $this->returnarray["data"]["config_file"] = array("status" => 1, "message" => "Error during config file creation.", "data" => []);
        array_push($this->returnarray["data"]["config_file"]["data"], array("status" => 0, "message" => "The config file were not created successfully."));
        return $this->returnarray;
      }
      
      //1g. Verfifying htaccess
      $this->returnarray["data"]["htaccess_file"] = ["data" => []];
      if(strpos(file_get_contents("{$_SERVER["DOCUMENT_ROOT"]}/.htaccess"),$noncekey) !== false){
        $this->returnarray["data"]["htaccess_file"] = array("status" => 0, "message" => "htaccess file adapted successfully.", "data" => []);
        array_push($this->returnarray["data"]["htaccess_file"]["data"], array("status" => 0, "message" => "The htaccess file were successfully adapted."));
      }else{
        $this->returnarray["data"]["htaccess_file"] = array("status" => 1, "message" => "Error during htaccess file writing.", "data" => []);
        array_push($this->returnarray["data"]["htaccess_file"]["data"], array("status" => 1, "message" => "The htaccess file does not contain the new nonce key. Please make sure apache/nginx has rwx file access. You can change it later."));
        return $this->returnarray;
      }

      //2. Creating Database
      $this->returnarray["data"]["db_config"] = array("status" => 0, "message" => "Database imported and installed default values.", "data" => []);
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
        array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Table structure created successfully."));
        //2c. Insert default values
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

        //Project registerred sites
        $query .= "INSERT INTO `sites` VALUES (1,'ChiaMgmt\\\\MainOverview\\\\MainOverview_Api'),(2,'ChiaMgmt\\\\Nodes\\\\Nodes_Api'),(3,'ChiaMgmt\\\\System\\\\System_Api'),(4,'ChiaMgmt\\\\Users\\\\Users_Api'),(5,'ChiaMgmt\\\\Chia_Wallet\\\\Chia_Wallet_Api'),(6,'ChiaMgmt\\\\Chia_Farm\\\\Chia_Farm_Api'),(7,'ChiaMgmt\\\\Chia_Harvester\\\\Chia_Harvester_Api'),(8,'ChiaMgmt\\\\Chia_Infra_Sysinfo\\\\Chia_Infra_Sysinfo_Api'),(9,'ChiaMgmt\\\\Chia_Overall\\\\Chia_Overall_Api'),(10,'ChiaMgmt\\\\System_Update\\\\System_Update_Api'),(11,'ChiaMgmt\\\\Logging\\\\Logging_Api'),(12,'ChiaMgmt\\\\Chia_Statistics\\\\Chia_Statistics_Api'),(13,'ChiaMgmt\\\\System_Statistics\\\\System_Statistics_Api');";
        $query .= "INSERT INTO `sites_pagestoinform` VALUES (1,1,1),(2,2,2),(3,2,1),(4,3,3),(5,4,4),(6,5,5),(7,5,1),(8,6,6),(9,6,1),(10,7,7),(11,7,1),(12,2,5),(13,2,6),(14,2,7),(15,8,8),(16,8,1),(17,2,8),(18,9,1),(19,10,1),(20,11,11),(21,9,12),(22,12,12),(23,13,13),(24,8,13),(25,9,5);";

        //Default nodetypes
        $query .= "INSERT INTO `nodetypes_avail` VALUES (1,'webClient',1,1,1,'app'),(2,'backendClient',2,0,3,'backend'),(3,'Farmer',3,1,2,'chianode'),(4,'Harvester',4,1,2,'chianode'),(5,'Wallet',5,1,2,'chianode'),(6,'Unknown',99,0,2,'');";
        $query .= "INSERT INTO `nodetype` VALUES (1,1,1),(2,2,2);";

        $this->db_api->execute($query,[]);

        array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Default entries inserted successfully."));

        //2d. Checking if all entries were inserted
        array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Checking count of inserted entries."));
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
          $count = $sql[0]["count"];
          if($count ==  $db_check["count"]){
            array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => "Table {$db_check["table"]} seems to be correct."));
          }else{
            $this->returnarray["data"]["db_config"]["status"] = 1;
            array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 1, "message" => "Error during database import check."));
            array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 1, "message" => "Table {$db_check["table"]} seems not to be correct. MySQL returned {$count} rows but it should be {$db_check["count"]}."));
            return $this->returnarray;
          }
        }
      }catch(\Exception $e){
        $this->returnarray["data"]["db_config"]["status"] = 1;
        $this->returnarray["data"]["db_config"]["message"] = "Error during database configuration or check.";
        array_push($this->returnarray["data"]["db_config"]["data"], array("status" => 0, "message" => $e->getMessage()));
        return $this->returnarray;
      }

      //3. Starting websocket server
      $this->websocket_api = new WebSocket_Api();
      $this->returnarray["data"]["first_start_websocket"] = ["data" => []];
      $websocket_status = $this->startWebsocketServer();

      $this->returnarray["data"]["first_start_websocket"] = $websocket_status;

      //Debugging Only
      $websocket_status["status"] = 0;

      if($websocket_status["status"] > 0){
        $this->returnarray["data"]["first_start_websocket"] = array("status" => 1, "message" => "Error during websocket server start. The project seems not to be installed correctly.", "data" => []);
        array_push($this->returnarray["data"]["first_start_websocket"]["data"], array("status" => 0, "message" => "Sucessfully started websocket server."));
        return $this->returnarray;
      }else{
        $this->returnarray["data"]["first_start_websocket"] = array("status" => 0, "message" => "Started websocket server.", "data" => []);
        array_push($this->returnarray["data"]["first_start_websocket"]["data"], array("status" => 0, "message" => "Sucessfully started websocket server."));
      }

      $this->returnarray["status"] = 0;
      $this->returnarray["message"] = "Finished installation.";
      return $this->returnarray;
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
      $whitelist = [".htaccess", "config.ini.php.example", ".git", ".gitignore"]; //Root folders or files, Subpaths are not possible currently

      foreach(
       $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator("../../..{$this->ini["system_root"]}/", \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST) as $item
      ){
        if(str_contains($iterator->getSubPathname(), "backup" )){
          continue;
        }else{
          $root_folder = explode("/", $iterator->getSubPathname())[0];
          if(in_array($item->getFilename(), $whitelist) || in_array($root_folder, $whitelist)) continue;

          if(!(is_readable($item) && is_writeable($item))){
            array_push($not_accessable, "/{$iterator->getSubPathname()}");
          }
        }
      }

      if(count($not_accessable) > 0){
        if(!is_null($this->logging_api)){
          $returnmessage = $this->logging_api->getErrormessage("001");
          $returnmessage["data"] = $not_accessable;
          return $returnmessage;
        }else{
          return array("status" => 1, "message" => "Some files are not fully accessable. Please adjust the file owner and group to the apache user and set 750 as file rights.", "data" => $not_accessable);
        } 
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
      $update_data = $this->checkForUpdates();
      if(!is_null($update_data) && array_key_exists("data", $update_data) && array_key_exists("remoteversion", $update_data["data"]) && array_key_exists("zipball", $update_data["data"])){
        $target_version = $update_data["data"]["remoteversion"];
        $updateurl = $update_data["data"]["zipball"];
        $tmpdir = "/tmp";

        if(is_dir($tmpdir)){
          $tmpfiledir = "{$tmpdir}/chiamgmt_update.zip";

          $curl = curl_init();
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
          curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
          curl_setopt($curl, CURLOPT_URL, $updateurl);
          curl_setopt($curl, CURLOPT_USERAGENT, 'Chrome/64.0.3282.186 Safari/537.36');
          $content = curl_exec($curl);
          $err = curl_error($curl);
          curl_close($curl);

          if ($err) {
            return array("status" => 0, "message" => "Could not download zip update file from github.");
          }

          $fp = fopen($tmpfiledir,"wb");
          fwrite($fp,$content);
          fclose($fp);
          
          $zipcheck = $this->checkZipValid($tmpfiledir);
          if($zipcheck["status"] == 0){
            return array("status" => 0, "message" => "Successfully downloaded update {$updateurl} to {$tmpfiledir}.");
          }else{
            return $zipcheck;
          }
        }else{
          return array("status" => 1, "message" => "Temporary directory {$tmpdir} for update downloading not found or not accessable.");
        }
      }else{
        return array("status" => 1, "message" => "Important update data is not stated.");
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
        $update_data = $this->checkForUpdates();
        $target_version = $update_data["data"]["remoteversion"];
        $updateurl = $update_data["data"]["zipball"];

        $zip = new \ZipArchive;
        $res = $zip->open($tmpfiledir);
        if ($res === TRUE) {
          $subfoldername = trim($zip->getNameIndex(0), '/');
          $zip->extractTo($tmpdir);
          $zip->close();

          $this->full_copy("{$tmpdir}/$subfoldername/", "{$_SERVER["DOCUMENT_ROOT"]}{$this->ini["system_root"]}");
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
          }else{
            return $this->logging_api->getErrormessage("002");
          }
        }
        try{
          $this->db_api->execute("UPDATE system_infos SET dbversion = ?;", array($version));
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("003", $e);
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
        $update_data = $this->checkForUpdates();
        $newversion = $update_data["data"]["remoteversion"];
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

      $tempini = parse_ini_file($config_file, true);
      if(array_key_exists("application", $tempini) && array_key_exists("versnummer", $tempini["application"]) && $tempini["application"]["versnummer"] == $newversion){
        return array("status" => 0, "message" => "Successfully set new version to {$newversion}.");
      }else {
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Aborts the current update by setting the databases column "process_update" to false (0)
     *
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function cancelUpdate(): array
    {
      try{
        $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, process_update = ?, maintenance_mode = ?", array(0,0,0));

        return array("status" => 0, "message" => "Successfully disabled updatemod.");
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
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
