<?php
  namespace ChiaMgmt\Users;

  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Mailing\Mailing_Api;

  class Users_Api{
    private $db_api, $logging_api, $ini, $ciphering, $iv_length, $options, $encryption_iv, $mailing_api;

    public function __construct(){
      //Variables for pw encrypting and decrypting
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';

      $this->db_api = new DB_Api();
      $this->logging = new Logging_Api($this);
      $this->mailing_api = new Mailing_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');
    }

    public function savePersonalInfo(array $data, array $loginData = NULL){
      if(isset($data["userID"]) && isset($data["name"]) && isset($data["lastname"]) && isset($data["email"]) && isset($data["username"])){
        $checkUserExists = $this->checkUsernameExists($data["username"], $data["userID"]);
        if($checkUserExists["status"] == "0"){
          try{
            $sql = $this->db_api->execute("UPDATE users SET name = ?, lastname = ?, email = ?, username = ? WHERE id = ?",
                    array($data["name"], $data["lastname"], $data["email"], $data["username"], $data["userID"]));

            return array("status" => 0, "message" => "Updated userdata successfully.", "data" => $data);
          }catch(Exeption $e){
            //return $this->logging->getErrormessage("001",$e);
            return array("status" => 1, "message" => "An error occured.");
          }
        }else{
          //$this->logging->getErrormessage("002");
          return $checkUserExists;
        }
      }else{
        //return $this->logging->getErrormessage("003");
        return array("status" => 1, "message" => "Not all information stated.");
      }
    }

    public function addUser(array $data, array $loginData = NULL){
      if(array_key_exists("name", $data) && array_key_exists("lastname", $data) &&
          array_key_exists("email", $data) && array_key_exists("username", $data) && array_key_exists("password", $data)){

        if($this->userInfoNotEmpty($data)){
          $userexists = $this->checkUsernameExists($data["username"]);
          $pwcheck = $this->checkPasswordStrength($data["password"]);

          if($userexists["status"] == 0 && $pwcheck["status"] == 0){
            $salt = bin2hex(random_bytes(30));
            $new_salted_pw = $salted_hash=hash('sha256',$data["password"].$salt.$this->ini['serversalt']);

            try{
              $sql = $this->db_api->execute("INSERT INTO users (id, username, name, lastname, password, salt, email) VALUES (NULL, ?, ?, ?, ?, ?, ?)",
              array($data["username"], $data["name"], $data["lastname"], $new_salted_pw, $salt, $data["email"]));

              $data["username"] = "admin1";

              $sql = $this->db_api->execute("SELECT id, username, name, lastname, email, enabled FROM users WHERE username = ?", array($data["username"]));
              foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $key => $value){
                $newData[$value["id"]] = $value;
              }

              unset($data["password"]);
              return array("status" => 0, "message" => "Updated userdata successfully.", "data" => $newData);
            }catch(Exception $e){
              print_r($e);
              return array("status" => 1, "message" => "An error occured.");
            }
          }else{
            if($userexists["status"] == 1) return $userexists;
            if($pwcheck["status"] == 1) return $pwcheck;
          }

        }else{
          return array("status" => 1, "message" => "One or more values are empty.");
        }

      }else{
        return array("status" => 1, "message" => "Not all information stated.");
      }
    }

    public function editUserInfo(array $data, array $loginData = NULL){
      if(array_key_exists("id", $data) && array_key_exists("name", $data) && array_key_exists("lastname", $data) &&
          array_key_exists("email", $data) && array_key_exists("username", $data)){

        $data["userID"] = $data["id"];
        if($this->userInfoNotEmpty($data)["status"] == 0){
          $savePersInfo = $this->savePersonalInfo($data);
          $pwreset = NULL;
          if(array_key_exists("password", $data)){
            $pwcheck = $this->checkPasswordStrength($data["password"]);
            if($pwcheck["status"] == 0){
              $pwreset = $this->resetUserPassword($data);
            }else{
              return $pwcheck;
            }
          }

          if(($savePersInfo["status"] == 0 && is_null($pwreset)) || ($savePersInfo["status"] == 0 && !is_null($pwreset) && $pwreset["status"] == 0)){
            unset($data["password"]);
            return array("status" => 0, "message" => "Updated userdata successfully.", "data" => $data);
          }else{
            return array("status" => 1, "message" => "Some values could not be safed.");
          }

        }else{
          return array("status" => 1, "message" => "One or more values are empty or password not strong enough.");
        }
        unset($data["password"]);

      }else{
        return array("status" => 1, "message" => "Not all information stated.");
      }
    }

    public function disableUser(array $data, array $loginData = NULL){
      if(array_key_exists("userID", $data)){
        if($loginData["userid"] != $data["userID"] && $data["userID"] > 1){
          try{
            $sql = $this->db_api->execute("UPDATE users SET enabled = 0 WHERE id = ?", array($data["userID"]));
            $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated = 1 WHERE id = ?", array($data["userID"]));

            return array("status" => 0, "message" => "Successfully disabled user with ID {$data["userID"]}.", "data" => $data);
          }catch(Exception $e){
            print_r($e);
            return array("status" => 1, "message" => "An error occured.");
          }
        }else{
          return array("status" => 1, "message" => "You are not allowed to disable this account.");
        }
      }else{
        return array("status" => 1, "message" => "Some data is missing.");
      }
    }

    public function enableUser(array $data, array $loginData = NULL){
      if(array_key_exists("userID", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE users SET enabled = 1 WHERE id = ?", array($data["userID"]));

          return array("status" => 0, "message" => "Successfully enabled user with ID {$data["userID"]}.", "data" => $data);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Some data is missing.");
      }
    }

    private function userInfoNotEmpty(array $data){
      $notempty = true;
      foreach($data AS $key => $value){
        if(strlen(trim($value)) == 0) $notempty = false;
      }

      return array("status" => ($notempty ? "0" : "1"), "message" => "Check processed successfully $notempty.");
    }

    public function getUserData(int $userID = NULL){
      try{
        if(is_Null($userID)){
          $sql = $this->db_api->execute("SELECT id, username, name, lastname, email, enabled FROM users", array());
          foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $key => $value){
            $returndata[$value["id"]] = $value;
          }
        }else{
          $sql = $this->db_api->execute("SELECT username, name, lastname, email FROM users WHERE id = ?", array($userID));
          $returndata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];
        }
        return array("status" => 0, "message" => "Successfully loaded user information.", "data" => $returndata);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Checks if a specific username is already existin
     * @param  string $username The username which should be checked
     * @return array            Returns a statuscode array
     */
    public function checkUsernameExists(string $username, int $userID = NULL){
      try{
        if(is_null($userID)){
          $sql = $this->db_api->execute("SELECT count(*) AS usercount from users where username = ?",
                                        array($username));
        }else{
          $sql = $this->db_api->execute("SELECT count(*) AS usercount from users where username = ? AND id <> ?",
                                        array($username, $userID));
        }

        $usercount = $sql->fetch()["usercount"];

        if($usercount == 0){
          return array("status" => 0, "message" => "User does not exist!");
        }else{
          //return $this->logging->getErrormessage("001");
          return array("status" => 1, "message" => "User exists.");
        }
      }catch(Exception $e){
        //return $this->logging->getErrormessage("002",$e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function getOwnUserData(int $userID){
      return $this->getUserData($userID);
    }

    public function generateNewBackupKey(array $data, array $backendInfo = NULL){
      if(array_key_exists("userID", $data)){
        $backupkey = bin2hex(random_bytes(25));
        $encryptedbackupkey = $this->encrypt($backupkey);

        try{
          $sql = $this->db_api->execute("UPDATE users_backupkeys SET valid = 0 where userid = ?", array($data["userID"]));
          $sql = $this->db_api->execute("INSERT INTO users_backupkeys (id, userid, backupkey) VALUES (NULL, ?, ?)",
                                        array($data["userID"], $encryptedbackupkey));

          return array("status" => 0, "message" => "Generated new backup key for User {$data["userID"]}.", "data" => $backupkey);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }

      }else{
        return array("status" => 1, "message" => "Not all information stated.");
      }
    }

    public function getBackupKey(int $userID){
      try{
        $sql = $this->db_api->execute("SELECT backupkey FROM users_backupkeys WHERE userid = ? AND valid = 1", array($userID));
        $decryptedkey = $this->decrypt($sql->fetchAll(\PDO::FETCH_ASSOC)[0]["backupkey"]);

        return array("status" => 0, "message" => "Successfully loaded user information.", "data" => $decryptedkey);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Checks if the current password matches the password on the db
     * @param  array $data The data array which contains the userID and password
     * @return aray        Returns a statuscode array
     */
    public function checkCurrentPassword(array $data, array $loginData = NULL){
      if(isset($data["userID"]) && isset($data["password"])){
        try{
          $sql = $this->db_api->execute("SELECT password, salt from users where id = ?",
          array($data["userID"]));

          if($sql->rowCount() == 1){
            $sqData = $sql->fetch();
            $salt = $sqData["salt"];
            $current_salted_pw = $sqData["password"];
            $stated_salted_password = hash('sha256',$data["password"].$salt.$this->ini["serversalt"]);

            if($stated_salted_password == $current_salted_pw){
              return array("status" => 0, "message" => "Stated password matches.");
            }else{
              return $this->logging->getErrormessage("001");
            }
          }else{
            return $this->logging->getErrormessage("002");
          }
        }catch(Exeption $e){
          return $this->logging->getErrormessage("003",$e);
        }
      }else{
        return $this->logging->getErrormessage("004");
      }
    }

    private function checkPasswordStrength(string $password){
      $uppercase = preg_match('@[A-Z]@', $password);
      $lowercase = preg_match('@[a-z]@', $password);
      $number    = preg_match('@[0-9]@', $password);
      $specialChars = preg_match('@[^\w]@', $password);

      if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
        return array("status" => 1, "message" => "Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.");
      }else{
        return array("status" => 0, "message" => "Password strong enough.");
      }
    }

    /**
     * Resets the in the data array stated user's password
     * @param  array $data The data containing the userID and the password
     * @return array       Returns a statuscode array
     */
    public function resetUserPassword(array $data, array $loginData = NULL){
      if(isset($data["userID"]) && isset($data["password"])){
        $pwcheck = $this->checkPasswordStrength($data["password"]);
        if($pwcheck["status"] == 0){
          try{
            $sql = $this->db_api->execute("SELECT password, salt from users where id = ?",
            array($data["userID"]));

            if($sql->rowCount() == 1){
              $sqData = $sql->fetch();
              $salt = $sqData["salt"];
              $current_salted_pw = $sqData["password"];
              $new_salted_pw = hash('sha256',$data["password"].$salt.$this->ini['serversalt']);

              if($new_salted_pw != $current_salted_pw){
                $sql = $this->db_api->execute("UPDATE users SET password = ? where id = ?",
                array($new_salted_pw, $data["userID"]));

                return array("status" => 0, "message" => "Password successfully updated.");
              }else{
                return $this->logging->getErrormessage("001");
              }
            }else{
              return $this->logging->getErrormessage("002");
            }
          }catch(Exeption $e){
            return $this->logging->getErrormessage("003",$e);
          }
        }else{
          return $pwcheck;
        }
      }else{
        return $this->logging->getErrormessage("004");
      }
    }

    public function getLoggedInDevices(int $userID = NULL){
      try{
        if(is_null($userID)){
          $sql = $this->db_api->execute("SELECT id, userid, deviceinfo from users_sessions WHERE invalidated = 0", array());
        }else{
          $sql = $this->db_api->execute("SELECT id, userid, deviceinfo from users_sessions WHERE userid = ? AND invalidated = 0 GROUP BY userid", array($userID));
        }

        $sqreturndata = array();
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);
        if(count($sqreturn) > 0){
          $sqreturndata = $sqreturn;
        }

        return array("status" => 0, "message" => "Successfully loaded all logged in devices.", "data" => $sqreturndata);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured");
      }
    }

    public function logoutDevice(array $data, array $loginData = NULL){
      if(array_key_exists("deviceid", $data) && array_key_exists("userid", $data)){
        $deviceID = $data["deviceid"];

        try{
          $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated = ? WHERE userid = ? AND id = ?",
                                        array(1, $data["userid"], $deviceID));

          return array("status" => 0, "message" => "Successfully logged out device.", "data" => array("deviceid" => $deviceID));
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Device ID not stated.");
      }
    }

    public function sendInvitationMail(array $data, array $loginData = NULL){
      if(array_key_exists("userID", $data)){
        if($loginData["userid"] != $data["userID"]){
          $this->users = new Users_Api();
          $userdata = $this->users->getUserData($data["userID"]);

          if($userdata["status"] == 0){
            $userdata = $userdata["data"];
            $subject = "Invitation Mail for Chia Management with love";
            $message = "If you got this message your mail settings are working correctly.<br>Congrats!<br><strong>Note: Please do not reply to this e-mail.</strong>";

            $message = "<h1>Invitation Mail for Chia Management</h1><br>
            Hello {$userdata["name"]} {$userdata["lastname"]},<br><br>
            You were invited to join the Chia Management Software.<br>
            An account was already created for you. See the details below:<br>
            Username: <b>{$userdata["username"]}</b><br>
            E-Mail: <b>{$userdata["email"]}</b><br>
            Login link: <a href='{$this->ini["app_protocol"]}://{$this->ini["app_domain"]}{$this->ini["frontend_url"]}'><b>Click Here</b></a><br>
            To get your login password ask the user who invited you.<br><br>
            Have fun :)<br>
            ";

            $mailingstatus = $this->mailing_api->sendMail(array($userdata["email"]), "Chia Management invitation" , $message);

            return array("status" => 0, "message" => "Successfully sent invitation mail to user with ID {$data["userID"]}.");
          }else{
            return $userdata;
          }
        }else{
          return array("status" => 1, "message" => "It makes no sense to send an invitation to yourself :).");
        }
      }
    }

    private function encrypt(string $string){
      return openssl_encrypt($string, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    private function decrypt(string $encryptedstring){
      return openssl_decrypt ($encryptedstring, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>
