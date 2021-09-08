<?php
  namespace ChiaMgmt\System;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;

  class System_Api{
    private $db_api, $ini, $ciphering, $iv_length, $options, $encryption_iv, $websocket_api, $system_update_api, $logging_api, $nodes_api;
    public function __construct(){
      //Variables for pw encrypting and decrypting
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
      $this->system_update_api = new System_Update_Api();
      $this->logging_api = new Logging_Api($this);
      $this->nodes_api = new Nodes_Api();
    }

    public function setSystemSettings(array $data, array $loginData = NULL){
      $settingtype = array_key_first($data);

      if(!is_Null($settingtype)){
        foreach($data[$settingtype] AS $settingkey => $settingvalue){
          if(is_array($settingvalue) && array_key_exists("type", $settingvalue) && $settingvalue["type"] == "password")
            $data[$settingtype][$settingkey]["value"] = $this->encryptPassword($settingvalue["value"]);
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
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    public function getAllSystemSettings(){
      try{
        $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array());

        return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function confirmSetting(array $data, array $loginData = NULL){
      if(array_key_exists("settingtype", $data)){
        $settingtype = $data["settingtype"];
        try{
          $sql = $this->db_api->execute("UPDATE system_settings SET confirmed = ? WHERE settingtype = ?", array(1, $settingtype));

          return array("status" => 0, "message" => "Successfully confirmed settingtype $settingtype.", "data" => array("settingtype" => $settingtype));
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function getSpecificSystemSetting(string $settingtype){
      $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings WHERE settingtype = ?", array($settingtype));

      return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
    }

    public function testConnection(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->testConnection();
    }

    public function checkForUpdates(array $data = [], array $loginData = NULL){
      $updatechannel = $this->getSpecificSystemSetting("updatechannel");
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

    public function processUpdate(array $data, array $loginData = NULL, $server = NULL){
      if($this->checkForUpdates()["data"]["updateavail"]){
        return $this->system_update_api->processUpdate($data, $loginData, $server);
      }else{
        return $this->logging_api->getErrormessage("001");
      }
    }

    public function getSystemMessages(array $data = [], array $loginData = NULL, $server = NULL){
      $returndata = [];
      $returndata["found"] = [];
      $returndata["count"] = 0;

      //Checking if install.php is existing
      if(file_exists(__DIR__."/../../../installer.php")){
        $returndata["found"]["installer"] = "The installer file (installer.php) were found. Please remove it as soon as possible.";
        $returndata["count"] = $returndata["count"] + 1;
      }

      //Checking if updates are available
      $systemupdate = $this->checkForUpdates()["data"];
      if($systemupdate["updateavail"]){
        $returndata["found"]["updateavail"] = "There is an system update to version {$systemupdate["remoteversion"]} available.";
        $returndata["count"] = $returndata["count"] + 1;
      }
      $nodeupdates = $this->nodes_api->checkUpdatesAndChannels()["data"];
      $nodesupdatesavail = [];
      foreach($nodeupdates["updateinfos"] AS $arrkey => $nodeupdatedata){
        if($nodeupdatedata["updateavailable"] < 0) array_push($nodesupdatesavail, $nodeupdatedata["hostname"]);
      }

      if(count($nodesupdatesavail) > 0){
        $returndata["found"]["updateavail"] = "There are node updates available for the following nodes: " . implode(", ", $nodesupdatesavail) . ". Please update soon.";
        $returndata["count"] = $returndata["count"] + 1;
      }

      //Checking if websocket server is running
      if($this->testConnection()["status"] != 0){
        $returndata["found"]["websocket"] = "Websocket Server not running. Please start it otherwise you cannot use this system proberly.";
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

        return array("status" => 0, "message" => "Successfully queried system messages.", "data" => $returndata);
      }catch(Exception $e){
        $returndata = $this->logging_api->getErrormessage("001", $e);
      }
    }

    private function formatSetting(array $settings){
      $returndata = [];
      foreach($settings AS $key => $value){
        $returndata[$value["settingtype"]] = json_decode($value["settingvalue"], true);
        foreach($returndata[$value["settingtype"]] AS $settingkey => $settingvalue){
          if(is_array($settingvalue) && array_key_exists("type", $settingvalue) && $settingvalue["type"] == "password")
            $returndata[$value["settingtype"]][$settingkey]["value"] = $this->decryptPassword($settingvalue["value"]);
        }
        $returndata[$value["settingtype"]]["confirmed"] = $value["confirmed"];
      }

      return $returndata;
    }

    private function encryptPassword(string $password){
      return openssl_encrypt($password, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    private function decryptPassword(string $encryptedpw){
      return openssl_decrypt ($encryptedpw, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

  }
?>
