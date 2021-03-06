<?php
  namespace ChiaMgmt\System;
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
    public function setSystemSettings(array $data, array $loginData = NULL): array
    {
      $settingtype = array_key_first($data);

      if(!is_Null($settingtype)){
        foreach($data[$settingtype] AS $settingkey => $settingvalue){
          if(is_array($settingvalue) && array_key_exists("type", $settingvalue) && $settingvalue["type"] == "password")
            $data[$settingtype][$settingkey]["value"] = $this->encryption_api->encryptString($settingvalue["value"]);
        }

        try{
          $sql = $this->db_api->execute("SELECT Count(*) AS Count FROM system_settings WHERE settingtype = ?", array($settingtype));

          if($sql->fetchAll(\PDO::FETCH_ASSOC)[0]["Count"] == 0){
            $sql = $this->db_api->execute("Insert INTO system_settings (id, settingtype, settingvalue) VALUES (NULL, ?, ?)",
                                            array($settingtype, json_encode($data[$settingtype])));
          }else{
            $sql = $this->db_api->execute("UPDATE system_settings SET settingvalue = ?, confirmed = ? WHERE settingtype = ?", array(json_encode($data[$settingtype]), 0, $settingtype));
          }

          return array("status" => 0, "message" => "Successfully updated system settings for settingtype $settingtype.", "data" => $settingtype);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    /**
     * Returns all on database stored system settings.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function getAllSystemSettings(): array
    {
      try{
        $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array());

        return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
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
    public function getSpecificSystemSetting(string $settingtype): array
    {
      try{
        $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings WHERE settingtype = ?", array($settingtype));

        return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Tests the connection to the websocket server.
     * Function made for: Web(App)client
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function testConnection(): array
    {
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->testConnection();
    }

    /**
     * Checks for system updates.
     * Function made for: Web(App)client
     * @param  array  $data       { "updatechannel" : "[main|staging|dev|NULL]" }
     * @param  array $loginData   { NULL } No logindata is needed query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Available updateinformation]}
     */
    public function checkForUpdates(array $data = [], array $loginData = NULL): array
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
    public function getSystemMessages(array $data = [], array $loginData = NULL, $server = NULL): array
    {
      $returndata = [];
      $returndata["found"] = [];
      $returndata["count"] = 0;

      //Checking if updates are available
      $systemupdate = $this->checkForUpdates(["update_data_db" => true])["data"];
      if($systemupdate["updateavail"]){
        $returndata["found"]["updateavail"] = "There is an system update to version {$systemupdate["remoteversion"]} available.";
        $returndata["count"] = $returndata["count"] + 1;
      }
      $nodeupdates = $this->nodes_api->checkUpdatesAndChannels()["data"];
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
      if($this->testConnection()["status"] != 0){
        $returndata["found"]["websocket"] = "Websocket Server not running. Please start it otherwise you cannot use this system proberly.";
        $returndata["count"] = $returndata["count"] + 1;
      }

      //Checking if systems cronjob is enabled
      $cronjobEnabled = $this->getCronjobEnabled();
      if($cronjobEnabled["status"] == "012009001"){
        $returndata["found"]["websocket"] = "The system's automated background task is not enabled. No data can be queried automatically in backbround.";
        $returndata["count"] = $returndata["count"] + 1;
      }else if($cronjobEnabled["status"] == 0){
        $now = new \DateTime("now");
        $lastexecdate = new \DateTime($cronjobEnabled["data"]);
        $interval = $now->diff($lastexecdate);
        $seconds = $interval->s;

        if($seconds > 60){
          $returndata["found"]["websocket"] = "Last background task run more than 1 minute ago. Something seems to be wrong.";
          $returndata["count"] = $returndata["count"] + 1;
        }
      }

      //Checking if TOTP is enabled
      if(array_key_exists("userID", $data)){
        $second_factor_api = new Second_Factor_Api();
        $second_factor_enabled = $second_factor_api->getTOTPEnabled(["userID" => $data["userID"]]);
        if($second_factor_enabled["status"] != 0){
          $returndata["found"]["totpenabled"] = "Second factor via mobile app seems not to be enabled. This is a really important security feature. Please enable it in usersettings.";
          $returndata["count"] = $returndata["count"] + 1;
        }
      }else{
        $returndata["found"]["totpenabled"] = "UserID not set, cannot query TOTP user status.";
        $returndata["count"] = $returndata["count"] + 1;
      }

      //Checking if all system relevant security features are activated
      try{
        $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array());
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        foreach($sqdata AS $arrkey => $setting){
          if($setting["confirmed"] == 0){
            $returndata["found"][$setting["settingtype"]] = "Setting {$setting["settingtype"]} is not set or not confirmed.";
            $returndata["count"] = $returndata["count"] + 1;
          }
        }
      }catch(\Exception $e){
        $returndata = $this->logging_api->getErrormessage("001", $e);
      }
      
      return array("status" => 0, "message" => "Successfully queried system messages.", "data" => $returndata);
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
    public function getCronjobEnabled(): array
    {
      $enabledCronjobs = $this->crontabRepository->findJobByRegex("/ChiaMgmt\ cronjob\ -\ Do\ not\ remove\ this\ comment\ -\ {$this->ini["serversalt"]}/");
      if(count($enabledCronjobs) > 0){
        $sql = $this->db_api->execute("SELECT lastcronrun FROM system_infos", array());
        $lastcronrun = $sql->fetchAll(\PDO::FETCH_ASSOC);
        return array("status" => 0, "message" => "Cronjob exists.", "data" => $lastcronrun[0]["lastcronrun"]);
      }else{
        return $this->logging_api->getErrormessage("001");
      }
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
    public function setCurrentCronjobRunTimestamp(): array
    {
      try{
        $sql = $this->db_api->execute("UPDATE system_infos SET lastcronrun = NOW()", array());
        return array("status" => 0, "message" => "Successfully set new cronjob last run timestamp.");
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
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
