<?php
  namespace ChiaMgmt\System;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;

  class System_Api{
    private $db_api, $ini, $ciphering, $iv_length, $options, $encryption_iv, $websocket_api;
    public function __construct(){
      //Variables for pw encrypting and decrypting
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
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

          return array("status" => 0, "message" => "Successfully updated system settings for settingtype $settingtype.", "data" => $settingkey);
        }catch(Exception $e){
          /*print_r($e);
          return array("status" => 1, "message" => "An error occured.");*/
          return $this->logging->getErrormessage("001", $e);
        }
      }
    }

    public function getAllSystemSettings(){
      try{
        $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings", array());

        return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
      }catch(Exception $e){
        /*print_r($e);
        return array("status" => 1, "message" => "An error occured");*/
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function confirmSetting(array $data, array $loginData = NULL){
      if(array_key_exists("settingtype", $data)){
        $settingtype = $data["settingtype"];
        try{
          $sql = $this->db_api->execute("UPDATE system_settings SET confirmed = ? WHERE settingtype = ?", array(1, $settingtype));

          return array("status" => 0, "message" => "Successfully confirmed settingtype $settingtype.", "data" => array("settingtype" => $settingtype));
        }catch(Exception $e){
          /*print_r($e);
          return array("status" => 1, "message" => "An error occured.");*/
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        //return array("status" => 1, "message" => "No settingtype stated.");
        return $this->logging->getErrormessage("002");
      }
    }

    public function getSpecificSystemSetting(string $settingtype){
      $sql = $this->db_api->execute("SELECT settingtype, settingvalue, confirmed FROM system_settings WHERE settingtype = ?", array($settingtype));

      return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $this->formatSetting($sql->fetchAll(\PDO::FETCH_ASSOC)));
    }

    private function formatSetting(array $settings){
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

    public function testConnection(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->testConnection();
    }

    private function encryptPassword(string $password){
      return openssl_encrypt($password, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    private function decryptPassword(string $encryptedpw){
      return openssl_decrypt ($encryptedpw, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

  }
?>
