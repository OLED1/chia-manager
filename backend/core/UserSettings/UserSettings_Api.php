<?php
  namespace ChiaMgmt\UserSettings;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The UserSettings_Api class handles the user specific settings.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class UserSettings_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Exchangerates Class.
     * @var Exchangerates_Api
     */
    private $exchangerates_api;
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
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->db_api = new DB_Api();
      $this->exchangerates_api = new Exchangerates_Api();
      $this->logging_api = new Logging_Api($this);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
    }

    /**
     * Sets the gui mode for a specific user.
     * 1 = Light, 2 = Dark
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userid" : "The user's id for which the mode should be set/changed." }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The state of the gui mode.]} }
     */
    public function setGuiMode(array $data, array $loginData = NULL){
      if(array_key_exists("gui_mode", $data) && array_key_exists("userid", $loginData)){
        if($data["gui_mode"] >= 1 && $data["gui_mode"] <= 2){
          try{
            $sql = $this->db_api->execute("SELECT gui_mode FROM users_settings WHERE userid = ?", array($loginData["userid"]));
            $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if(count($sqdata) == 0){
              $sql = $this->db_api->execute("INSERT INTO users_settings (id, userid, gui_mode) VALUES (NULL, ?, ?)", array($loginData["userid"], $data["gui_mode"]));
            }else{
              $sql = $this->db_api->execute("UPDATE users_settings SET gui_mode = ? WHERE userid = ?", array($data["gui_mode"], $loginData["userid"]));
            }

            return array("status" => 0, "message" => "Successfully set gui mode to {$data["gui_mode"]}.", "data" => $data["gui_mode"]);
          }catch(Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002", "Gui Mode {$data["gui_mode"]} not supported.");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Returns the gui mode for a specific user.
     * 1 = Light, 2 = Dark
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "user_id" : "The user's id for which the mode should be set/changed." }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The state of the gui mode.]} }
     */
    public function getGuiMode(int $userid){
      if($userid > 0 && array_key_exists("user_id", $_COOKIE) && $_COOKIE["user_id"] == $userid){
        try{
          $sql = $this->db_api->execute("SELECT gui_mode FROM users_settings WHERE userid = ?", array($userid));
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqdata) == 0){
            $guiModeStatus = $this->setGuiMode(array("gui_mode" => 0), array("userid" => $userid));
            if($guiModeStatus["status"] == 0){
              $returndata = array("gui_mode" => 1);
            }else{
              return $guiModeStatus;
            }
          }else{
            $returndata = $sqdata[0];
          }

          return array("status" => 0, "message" => "Successfully loaded gui mode for user.", "data" => $returndata);
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Returns the user's related default currency.
     * Function made for: Web(App)client
     * @todo Make this function websocket compatible.
     * @param  int  $userid       The user's id for which the the default currency should be returned for."
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The default user currency information.]} }
     */
    public function getUserDefaultCurrency(int $userid){
      return $this->exchangerates_api->getUserDefaultCurrency($userid);
    }

    /**
     * Sets the user's related default currency.
     * Function made for: Web(App)client
     * @param  array    $data       { "currency_code" : "[Currency 3-4 digits code, e.g. usd]" }
     * @param  array    $loginData  { "userid" : [userid] }
     * @return array                { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Newly set default currency]}}
     */
    public function setUserDefaultCurrency(array $data, array $loginData = NULL){
      return $this->exchangerates_api->setUserDefaultCurrency($data, $loginData);
    }
  }
?>
