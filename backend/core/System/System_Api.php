<?php
  namespace ChiaMgmt\System;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Second_Factor\Second_Factor_Api;
  use TiBeN\CrontabManager\CrontabJob;
  use TiBeN\CrontabManager\CrontabRepository;
  use TiBeN\CrontabManager\CrontabAdapter;

  /**
   * The System_Api class manages all settings and system messages.
   * This class is mainly used by the web(/app) client.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class System_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Nodes Class.
     * @var Nodes_Api
     */
    private $nodes_api;
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
    /**
    * Holds an instance to the System Update Class.
    * @var System_Update
    */
    private $system_update_api;
    /**
    * Holds an instance to the Encryption Class.
    * @var Encryption_Api
    */
    private $encryption_api;
    /**
     * Holds an instance to systems cronjob for the apache user.
     * @var CrontabRepository
     */
    private $crontabRepository;
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
      $this->db_api = new DB_Api();
      $this->system_update_api = new System_Update_Api();
      $this->nodes_api = new Nodes_Api();
      $this->encryption_api = new Encryption_Api();
      $this->crontabRepository = new CrontabRepository(new CrontabAdapter());
      $this->logging_api = new Logging_Api($this, $server);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * Updates  a specific system setting on the database.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param array  $data        { ["dynamic settingtype"] : [Settingvalues] }
     * @param array $loginData    { NULL } No logindata is needed to use this method.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function setSystemSettings(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $settingtype = array_key_first($data);

        if(!is_Null($settingtype)){
          foreach($data[$settingtype] AS $settingkey => $settingvalue){
            if(is_array($settingvalue) && array_key_exists("type", $settingvalue) && $settingvalue["type"] == "password")
              $data[$settingtype][$settingkey]["value"] = $this->encryption_api->encryptString($settingvalue["value"]);
          }

          $settings_count = Promise\resolve((new DB_Api())->execute("SELECT Count(*) AS Count FROM system_settings WHERE settingtype = ?", array($settingtype)));
          $settings_count->then(function($settings_count_returned) use(&$resolve, $data, $settingtype){
            if($settings_count_returned->resultRows[0]["Count"] == 0){
              $set_setting = Promise\resolve((new DB_Api())->execute("Insert INTO system_settings (id, settingtype, settingvalue) VALUES (NULL, ?, ?)",
                                              array($settingtype, json_encode($data[$settingtype]))));
            }else{
              $set_setting = Promise\resolve((new DB_Api())->execute("UPDATE system_settings SET settingvalue = ?, confirmed = ? WHERE settingtype = ?", 
                                                                        array(json_encode($data[$settingtype]), 0, $settingtype)));
            }

            $set_setting->then(function($set_setting_returned) use(&$resolve, $settingtype){
              $saved_setting = Promise\resolve($this->getSpecificSystemSetting($settingtype));
              $saved_setting->then(function($saved_setting_returned) use(&$resolve, $settingtype){
                $resolve(array("status" => 0, "message" => "Successfully updated system settings for settingtype $settingtype.", "data" => $saved_setting_returned["data"]));
              });
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("setSystemSettings", "001", $e));
            });

          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("setSystemSettings", "002", $e));
          });
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns all on database stored system settings.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function getAllSystemSettings(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $all_settings = Promise\resolve((new DB_Api())->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array()));
        $all_settings->then(function($all_settings_returned) use(&$resolve){
          $resolve(array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($all_settings_returned->resultRows)));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getAllSystemSettings", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * This method sets some system settings to "confirmed".
     * Some settings like mail settings need to be confirmed bevore they are used.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "settingtype" : "settingtype" }
     * @param  array $loginData   { NULL } No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { "settingtype" : "The settingtype that has been changed" }}
     */
    public function confirmSetting(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("settingtype", $data)){
        $settingtype = $data["settingtype"];
        try{
          $sql = $this->db_api->execute("UPDATE system_settings SET confirmed = ? WHERE settingtype = ?", array(1, $settingtype));

          return array("status" => 0, "message" => "Successfully confirmed settingtype $settingtype.", "data" => array("settingtype" => $settingtype));
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Returns a specific system setting and it's values.
     * Function made for: Web(App)client
     * @throws Exception $e         Throws an exception on db errors.
     * @param  string $settingtype  The settingtype as string.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}}
     */
    public function getSpecificSystemSetting(string $settingtype): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($settingtype){
        $settings_promise = Promise\resolve((new DB_Api())->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings WHERE settingtype = ?", array($settingtype)));
        $settings_promise->then(function($returned_setting) use(&$resolve){
          $resolve(array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($returned_setting->resultRows)));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getSpecificSystemSetting", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Tests the connection to the websocket server.
     * Function made for: Web(App)client
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function testConnection(): object
    {
      return (new WebSocket_Api())->testConnection();
    }

    /**
     * Checks for system updates.
     * Function made for: Web(App)client
     * @param  array  $data       { "updatechannel" : "[main|staging|dev|NULL]" }
     * @param  array $loginData   { NULL } No logindata is needed query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Available updateinformation]}
     */
    public function checkForUpdates(array $data = [], array $loginData = NULL): object
    {
      return $this->system_update_api->checkForUpdates($data, $loginData);
    }

    /**
     * Sets the instance as updateing.
     * Function made for: Web(App)client
     * @param array  $data       { NULL }
     * @param array $loginData   { NULL }
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function setInstanceUpdating(array $data = [], array $loginData = NULL): array
    {
      return $this->system_update_api->setInstanceUpdating($data, $loginData);
    }

    /**
     * Starts the updateprocess if a update is available.
     * Function made for: Web(App)client
     * @param  array  $data                   { NULL } No data is needed query this function.
     * @param  array $loginData               { NULL } No logindata is needed query this function.
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function processUpdate(array $data, array $loginData = NULL, $server = NULL): array
    {
      if($this->checkForUpdates()["data"]["updateavail"]){
        return $this->system_update_api->processUpdate($data, $loginData, $server);
      }else{
        return $this->logging_api->getErrormessage("001");
      }
    }

    /**
     * Returns system messages like warning and criticals.
     * Function made for: Web(App)client
     * @param  array  $data                   { NULL } No data is needed query this function.
     * @param  array $loginData               { NULL } No logindata is needed query this function.
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : "[Found system messages]"}
     */
    public function getSystemMessages(array $data = [], array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){

        $systemupdate = Promise\resolve($this->checkForUpdates(["update_data_db" => true]));
        $nodeupdates = Promise\resolve($this->nodes_api->checkUpdatesAndChannels());
        $wssrunning = Promise\resolve($this->testConnection());
        $cronjobenabled = Promise\resolve($this->getCronjobEnabled());
        $second_factor_enabled = Promise\resolve((new Second_Factor_Api())->getTOTPEnabled(["userID" => $data["userID"]]));
        $security_featres_enabled = Promise\resolve((new DB_Api())->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array()));
        
        Promise\all([$systemupdate, $nodeupdates, $wssrunning, $cronjobenabled, $second_factor_enabled, $security_featres_enabled])->then(function($all_returned) use(&$resolve){
          $returndata = [];
          $returndata["found"] = [];
          $returndata["count"] = 0;

          //Checking if system updates are available
          $systemupdate = $all_returned[0]["data"];
          if($systemupdate["updateavail"]){
            $returndata["found"]["updateavail"] = "There is an system update to version {$systemupdate["remoteversion"]} available.";
            $returndata["count"] = $returndata["count"] + 1;
          }

          //Checking if node updates are available
          $nodeupdates = $all_returned[1]["data"];
          $nodesupdatesavail = [];
          $chiaupdatesavail = [];

          foreach($nodeupdates["updateinfos"] AS $arrkey => $nodeupdatedata){
            if($nodeupdatedata["updateavailable"] < 0) array_push($nodesupdatesavail, $nodeupdatedata["hostname"]);
            if($nodeupdatedata["chiaupdateavail"] < 0) array_push($chiaupdatesavail, $nodeupdatedata["hostname"]);
          }

          if(count($nodesupdatesavail) > 0){
            $returndata["found"]["updateavail"] = "There are node script updates available for the following nodes: " . implode(", ", $nodesupdatesavail) . ". Please update soon.";
            $returndata["count"] = $returndata["count"] + 1;
          }
    
          if(count($chiaupdatesavail) > 0){
            $returndata["found"]["updateavail"] = "There are chia blockchian updates available for the following nodes: " . implode(", ", $chiaupdatesavail) . ". Please update soon.";
            $returndata["count"] = $returndata["count"] + 1;
          }

          //Checking if websocket server is running
          $wssrunning = $all_returned[2];
          if($wssrunning["status"] != 0){
            $returndata["found"]["websocket"] = "Websocket Server not running. Please start it otherwise you cannot use this system proberly.";
            $returndata["count"] = $returndata["count"] + 1;
          }

          //Checking if systems cronjob is enabled
          $cronjobEnabled = $all_returned[3];
          if($cronjobEnabled["status"] == "012009001"){
            $returndata["found"]["cronjob"] = "The system's automated background task is not enabled. No data can be queried automatically in backbround.";
            $returndata["count"] = $returndata["count"] + 1;
          }else if($cronjobEnabled["status"] == 0){
            $now = new \DateTime("now");
            $lastexecdate = new \DateTime($cronjobEnabled["data"]);
            $interval = $now->diff($lastexecdate);
            $seconds = $interval->s;
    
            if($seconds > 60){
              $returndata["found"]["cronjob"] = "Last background task run more than 1 minute ago. Something seems to be wrong.";
              $returndata["count"] = $returndata["count"] + 1;
            }
          }

          //Checking if TOTP is enabled
          $second_factor_enabled = $all_returned[4];
          if($second_factor_enabled["status"] != 0){
            $returndata["found"]["totpenabled"] = "Second factor via mobile app seems not to be enabled. This is a really important security feature. Please enable it in usersettings.";
            $returndata["count"] = $returndata["count"] + 1;
          }

          //Checking if all system relevant security features are activated
          $security_featres_enabled = $all_returned[5]->resultRows;
          foreach($security_featres_enabled AS $arrkey => $setting){
            if($setting["confirmed"] == 0){
              $returndata["found"][$setting["settingtype"]] = "Setting {$setting["settingtype"]} is not set or not confirmed.";
              $returndata["count"] = $returndata["count"] + 1;
            }
          }

          $resolve(array("status" => 0, "message" => "Successfully queried system messages.", "data" => $returndata));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getSystemMessages", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * This function is ment for developers of this project.
     * It sets default values in the project config file and updates the db version. Furhtermore a default entry will be set in the db_update.json.
     * Function made for: Web(App)client
     * @param  array  $data                   { NULL } No data is needed query this function.
     * @param  array $loginData               { NULL } No logindata is needed query this function.
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : "[Found system messages]"}
     */
    public function updateProjectVersion(array $data = [], array $loginData = NULL, $server = NULL): array
    {
      if(array_key_exists("developer_mode", $this->ini) && $this->ini["developer_mode"] == "on"){
        if(array_key_exists("projectversion", $data)){
          $newversion = $data["projectversion"];
          if(preg_match("/^((\d+\.)?(\d+\.)?(\d+\.)?(\d{6}))|((\d+\.)(\d+\.)(\*|\d+))/", $newversion)){
            $currentversion = $this->ini["versnummer"];
            if(version_compare($currentversion, $newversion, "<")){
              $updateconfig = $this->system_update_api->updateConfigFile($newversion);
              if($updateconfig["status"] != 0) return $updateconfig;

              $db_update_file_path = __DIR__."/../System_Update/files/db_update.json";
              $db_update_file = file_get_contents($db_update_file_path);
              $db_update_array = json_decode($db_update_file, True);
              if(!array_key_exists($newversion, $db_update_array)){
                if(is_writable($db_update_file_path)){
                  $db_update_array[$newversion]["system_infos"][0] = "UPDATE `system_infos` SET dbversion = '{$newversion}';";
                  $db_update_file = json_encode($db_update_array, JSON_PRETTY_PRINT);
                  file_put_contents($db_update_file_path, $db_update_file);

                  $this->system_update_api->checkAndAdjustDatabase();
                }else{
                  return $this->logging_api->getErrormessage("001");
                }
              }else{
                return $this->logging_api->getErrormessage("002");
              }
            }else{
              return $this->logging_api->getErrormessage("003");
            }
            return array("status" => 0, "message" => "Successfully updated project version and set default values.");
          }else{
            return $this->logging_api->getErrormessage("004");
          }
        }else{
          return $this->logging_api->getErrormessage("005");
        }
      }else{
        return $this->logging_api->getErrormessage("006");
      }
    }

    /**
     * Checks if the system cronjob is enabled.
     * Function made for: Web(App)client
     * @return array  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function getCronjobEnabled(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $enabledCronjobs = $this->crontabRepository->findJobByRegex("/ChiaMgmt\ cronjob\ -\ Do\ not\ remove\ this\ comment\ -\ {$this->ini["serversalt"]}/");
        if(count($enabledCronjobs) > 0){
          $last_cron_run = Promise\resolve((new DB_Api())->execute("SELECT lastcronrun FROM system_infos", array()));
          $last_cron_run->then(function($last_cron_run_returned) use(&$resolve){
            $resolve(array("status" => 0, "message" => "Cronjob exists.", "data" => $last_cron_run_returned->resultRows[0]["lastcronrun"]));
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getCronjobEnabled", "002", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("getCronjobEnabled", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Enables the systems cronjob if not enabled already.
     * Function made for: Web(App)client
     * @return array  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function enableCronjob(): array
    {
      $cronjobEnbled = $this->getCronjobEnabled();
      if($cronjobEnbled["status"] != 0){
        $pathtocronjob = realpath(__DIR__."/../CronBackendService/CronBackendService.php");
        $crontabJob = new CrontabJob();
        $crontabJob
        ->setMinutes('*')
        ->setHours('*')
        ->setDayOfMonth('*')
        ->setMonths('*')
        ->setDayOfWeek('*')
        ->setTaskCommandLine("php {$pathtocronjob}")
        ->setComments("ChiaMgmt cronjob - Do not remove this comment - {$this->ini["serversalt"]}"); // Comments are persisted in the crontab

        $this->crontabRepository->addJob($crontabJob);
        $this->crontabRepository->persist();

        $cronjobEnbled = $this->getCronjobEnabled();
        if($cronjobEnbled["status"] == 0){
          return array("status" => 0, "message" => "Cronjob Successfully enabled.");
        }else{
          return $this->logging_api->getErrormessage("001");
        }
      }

      return $cronjobEnbled;
    }

    /**
     * Disables the systems cronjob if not enabled already.
     * Function made for: Web(App)client
     * @return array  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function disableCronjob(): array
    {
      $cronjobEnbled = $this->getCronjobEnabled();
      if($cronjobEnbled["status"] == 0){
        $enabledCronjobs = $this->crontabRepository->findJobByRegex("/ChiaMgmt\ cronjob\ -\ Do\ not\ remove\ this\ comment\ -\ {$this->ini["serversalt"]}/");
        $crontabJob = $enabledCronjobs[0];
        $this->crontabRepository->removeJob($crontabJob);
        $this->crontabRepository->persist();

        $cronjobEnbled = $this->getCronjobEnabled();
        if($cronjobEnbled["status"] != 0){
          return array("status" => 0, "message" => "Cronjob Successfully disbled.");
        }else{
          return $this->logging_api->getErrormessage("001");
        }
      }

      return $cronjobEnbled;
    }

    /**
     * Updates the cronjobs last run timestamp.
     * Function made for: Cronjob
     * @throws Exception $e  Throws an exception on db errors.
     * @return array         "status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function setCurrentCronjobRunTimestamp(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){       
        $set_timestamp = Promise\resolve((new DB_Api())->execute("UPDATE system_infos SET lastcronrun = NOW()", array()));
        $set_timestamp->then(function($set_timestamp_returned) use(&$resolve){
          $resolve(array("status" => 0, "message" => "Successfully set new cronjob last run timestamp."));
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("setCurrentCronjobRunTimestamp", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Formats some settings to a api proper format like password decrypting.
     * Function made for: API/Backend
     * @param  array  $settings   An array of all settings
     * @return array              Returns the formatted settings
     */
    private function formatSetting(array $settings): array
    {
      $returndata = [];
      foreach($settings AS $key => $value){
        $returndata[$value["settingtype"]] = json_decode($value["settingvalue"], true);
        foreach($returndata[$value["settingtype"]] AS $settingkey => $settingvalue){
          if(is_array($settingvalue) && array_key_exists("type", $settingvalue) && $settingvalue["type"] == "password")
            $returndata[$value["settingtype"]][$settingkey]["value"] = $this->encryption_api->decryptString($settingvalue["value"]);
        }
        $returndata[$value["settingtype"]]["confirmed"] = $value["confirmed"];
      }

      return $returndata;
    }
  }
?>
