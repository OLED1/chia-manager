<?php
  namespace ChiaMgmt\UserSettings;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;

  class UserSettings_Api{
    private $db_api, $ini, $exchangerates_api;

    public function __construct(){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
      $this->exchangerates_api = new Exchangerates_Api();
    }

    //0 = Auto, 1 = Light, 2 = Dark
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
            //TODO Implement correct status code
            print_r($e);
            return array("status" => 1, "message" => "An error occured.");
          }
        }else{
          //TODO Implement correct status code
          return array("status" => 1, "message" => "Gui Mode {$data["gui_mode"]} not supported.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function getGuiMode(int $userid){
      if($userid > 0 && array_key_exists("user_id", $_COOKIE) && $_COOKIE["user_id"] == $userid){
        try{
          $sql = $this->db_api->execute("SELECT gui_mode FROM users_settings WHERE userid = ?", array($userid));
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqdata) == 0){
            $guiModeStatus = $this->setGuiMode(array("gui_mode" => 0), array("userid" => $userid));
            if($guiModeStatus["status"] == 0){
              $returndata = array("gui_mode" => 0);
            }else{
              return $guiModeStatus;
            }
          }else{
            $returndata = $sqdata[0];
          }

          return array("status" => 0, "message" => "Successfully loaded gui mode for user.", "data" => $returndata);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Wrong userid.");
      }
    }

    public function getUserDefaultCurrency(int $userid){
      return $this->exchangerates_api->getUserDefaultCurrency($userid);
    }

    public function setUserDefaultCurrency(array $data, array $loginData = NULL){
      return $this->exchangerates_api->setUserDefaultCurrency($data, $loginData);
    }
  }
?>
