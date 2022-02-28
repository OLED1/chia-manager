<?php
  namespace ChiaMgmt\Users;

  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Users_Api class handles the user creation and editing specific functions.
   * @version 0.2
   * @author OLED1 - Oliver Edtmair
   * @since 0.1
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Users_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Mailing_Api
     */
    private $mailing_api;
    /**
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;
    /**
     * Holds a system config json array.
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
      $this->logging_api = new Logging_Api($this, $server);
      $this->mailing_api = new Mailing_Api();
      $this->encryption_api = new Encryption_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * Updates user specific information.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "name" : "The user's forename", "lastname" : "The user's lastname", "email" : "The user's email address", "username" : "The user's username" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The changed data]} }
     */
    public function savePersonalInfo(array $data, array $loginData = NULL): array
    {
      if(isset($data["userID"]) && isset($data["name"]) && isset($data["lastname"]) && isset($data["email"]) && isset($data["username"])){
        $checkUserExists = $this->checkUsernameExists($data["username"], $data["userID"]);
        if($checkUserExists["status"] == "0"){
          try{
            $sql = $this->db_api->execute("UPDATE users SET name = ?, lastname = ?, email = ?, username = ? WHERE id = ?",
                    array($data["name"], $data["lastname"], $data["email"], $data["username"], $data["userID"]));

            return array("status" => 0, "message" => "Updated userdata successfully.", "data" => $data);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $checkUserExists;
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Adds a user to the system.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "name" : "The user's forename", "lastname" : "The user's lastname", "email" : "The user's email address", "username" : "The user's username", "password" : "The user's password in cleartext" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly added data]} }
     */
    public function addUser(array $data, array $loginData = NULL): array
    {
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

              $sql = $this->db_api->execute("SELECT id, username, name, lastname, email, enabled FROM users WHERE username = ?", array($data["username"]));
              foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $key => $value){
                $newData[$value["id"]] = $value;
              }

              unset($data["password"]);
              return array("status" => 0, "message" => "Updated userdata successfully.", "data" => $newData);
            }catch(\Exception $e){
              return $this->logging_api->getErrormessage("001", $e);
            }
          }else{
            if($userexists["status"] == 1) return $userexists;
            if($pwcheck["status"] == 1) return $pwcheck;
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Edit the information regarding an existing system user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "id" : "The user's db id", "name" : "The user's forename", "lastname" : "The user's lastname", "email" : "The user's email address", "username" : "The user's username", "password" : "The password which should be changed. Not mandatory." }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly added data]} }
     */
    public function editUserInfo(array $data, array $loginData = NULL): array
    {
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
            return $this->logging_api->getErrormessage("001");
          }

        }else{
          return $this->logging_api->getErrormessage("002");
        }
        unset($data["password"]);
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Disables an existing system user. The admin account and own account cannot be disabled.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's db id" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The id which was disabled.]} }
     */
    public function disableUser(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("userID", $data)){
        if($loginData["userid"] != $data["userID"] && $data["userID"] > 1){
          try{
            $sql = $this->db_api->execute("UPDATE users SET enabled = 0 WHERE id = ?", array($data["userID"]));
            $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated = 1 WHERE id = ?", array($data["userID"]));

            return array("status" => 0, "message" => "Successfully disabled user with ID {$data["userID"]}.", "data" => $data);
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("001", $e);
          }
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Remove a disabled user from database
     * Function made for: Web(App)client
     * Available since: 0.2.alpha
     * @throws Exception $e       Throws an exception on db errors.
     * @param array $data         { "userID" : "The user's db id" }
     * @param array $loginData    No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The id which was disabled.]} }
     */
    public function removeDisabledUser(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("userID", $data)){
        if($loginData["userid"] != $data["userID"] && $data["userID"] > 1){
          try{
            $sql = $this->db_api->execute("SELECT count(*) AS count FROM users WHERE id = ? AND enabled = ?", array($data["userID"], 0));
            $count = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if(array_key_exists("0", $count) && array_key_exists("count", $count[0])){
              $sql = $this->db_api->execute("DELETE FROM users WHERE id = ? AND enabled = ? AND id > 1", array($data["userID"], 0));
              
              return array("status" => 0, "message" => "Successfully removed user with id {$data["userID"]}.", "data" => ["id" => $data["userID"]]);
            }else{
              return $this->logging_api->getErrormessage("001");
            }
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("002", $e);
          }

        }else{
          if($loginData["userid"] != $data["userID"]) return $this->logging_api->getErrormessage("003");
          if($$data["userID"] == 1) return $this->logging_api->getErrormessage("004");
        }
      }else{
        return $this->logging_api->getErrormessage("005");
      }
    }

    /**
     * Enables an existing system user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's db id" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The id which was enabled.] } }
     */
    public function enableUser(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("userID", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE users SET enabled = 1 WHERE id = ?", array($data["userID"]));

          return array("status" => 0, "message" => "Successfully enabled user with ID {$data["userID"]}.", "data" => $data);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Checks if the stated data is not empty.
     * @param  array  $data       The key-value dataarray which should be checked.
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function userInfoNotEmpty(array $data): array
    {
      $notempty = true;
      foreach($data AS $key => $value){
        if(strlen(trim($value)) == 0) $notempty = false;
      }

      return array("status" => ($notempty ? "0" : "1"), "message" => "Check processed successfully $notempty.");
    }

    /**
     * Returns all or specific user data.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's db id. Not mandatory." }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The found on the db stored data.]} }
     */
    public function getUserData(int $userID = NULL): array
    {
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
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Checks if a specific username is already existing.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  string $username   The username which should be checked
     * @param  int    $userID     The userid for which the username should be checked for.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkUsernameExists(string $username, int $userID = NULL): array
    {
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
          return $this->logging_api->getErrormessage("001");
        }
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002",$e);
      }
    }

    /**
     * Returns the own db stored userdata.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @param  int    $userID   The userid for which the data should be returned for.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The found on the db stored data.]} }
     */
    public function getOwnUserData(int $userID): array
    {
      return $this->getUserData($userID);
    }

    /**
     * Generates a new backup key for a user.
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array  $data         { "userID" : "The user's id." }
     * @param  array $backendInfo   No backendInfo needed to query this function.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly created backup key.]} }
     */
    public function generateNewBackupKey(array $data, array $backendInfo = NULL): array
    {
      if(array_key_exists("userID", $data)){
        $backupkey = bin2hex(random_bytes(25));
        $encryptedbackupkey = $this->encryption_api->encryptString($backupkey);

        try{
          $sql = $this->db_api->execute("UPDATE users_backupkeys SET valid = 0 where userid = ?", array($data["userID"]));
          $sql = $this->db_api->execute("INSERT INTO users_backupkeys (id, userid, backupkey) VALUES (NULL, ?, ?)",
                                        array($data["userID"], $encryptedbackupkey));

          return array("status" => 0, "message" => "Generated new backup key for User {$data["userID"]}.", "data" => $backupkey);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }

      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Returns the current user's setup backup key.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array  $data         { "userID" : "The user's id." }
     * @param  array $backendInfo   No backendInfo needed to query this function.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The currently used backup key.]} }
     */
    public function getBackupKey(int $userID): array
    {
      try{
        $sql = $this->db_api->execute("SELECT backupkey FROM users_backupkeys WHERE userid = ? AND valid = 1", array($userID));
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
        if($sqdata > 0 && array_key_exists(0, $sqdata) && array_key_exists("backupkey", $sqdata[0])){
          $decryptedkey = $this->encryption_api->decryptString($sqdata[0]["backupkey"]);
        }else{
          $decryptedkey = "";
        }

        return array("status" => 0, "message" => "Successfully loaded user information.", "data" => $decryptedkey);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Checks if the current password matches the password on the db
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array $data          { "userID" : "The user's id.", "password" : "The password which should be checked." }
     * @param  array $loginData     No logindata needed to query this function.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkCurrentPassword(array $data, array $loginData = NULL): array
    {
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
              return $this->logging_api->getErrormessage("001");
            }
          }else{
            return $this->logging_api->getErrormessage("002");
          }
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("003",$e);
        }
      }else{
        return $this->logging_api->getErrormessage("004");
      }
    }

    /**
     * Checks if a stated password is strong enough.
     * @param  string $password   The password which should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function checkPasswordStrength(string $password): array
    {
      $uppercase = preg_match('@[A-Z]@', $password);
      $lowercase = preg_match('@[a-z]@', $password);
      $number    = preg_match('@[0-9]@', $password);
      $specialChars = preg_match('@[^\w]@', $password);

      if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
        return $this->logging_api->getErrormessage("001");
      }else{
        return array("status" => 0, "message" => "Password strong enough.");
      }
    }

    /**
     * Resets the in the data array stated user's password
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array $data          { "userID" : "The user's id.", "password" : "The password which should be reset." }
     * @param  array $loginData     No logindata needed to query this function.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function resetUserPassword(array $data, array $loginData = NULL): array
    {
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
                return $this->logging_api->getErrormessage("001");
              }
            }else{
              return $this->logging_api->getErrormessage("002");
            }
          }catch(\Exception $e){
            return $this->logging_api->getErrormessage("003",$e);
          }
        }else{
          return $pwcheck;
        }
      }else{
        return $this->logging_api->getErrormessage("004");
      }
    }

    /**
     * Returns a list logged in devices.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @throws Exception $e     Throws an exception on db errors.
     * @param  int    $userID   The userid for which the data should be returned for.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The found on the db stored data.]} }
     */
    public function getLoggedInDevices(int $userID = NULL): array
    {
      try{
        if(is_null($userID)){
          $sql = $this->db_api->execute("SELECT id, userid, logindate, deviceinfo from users_sessions WHERE invalidated = 0", array());
        }else{
          $sql = $this->db_api->execute("SELECT id, userid, logindate, deviceinfo from users_sessions WHERE userid = ? AND invalidated = 0", array($userID));
        }

        $sqreturndata = array();
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(count($sqreturn) > 0){
          $sqreturndata = $sqreturn;
        }

        return array("status" => 0, "message" => "Successfully loaded all logged in devices.", "data" => $sqreturndata);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Logs out an logged in user specific device.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "deviceid" : "The db device id.", "userid" : "The user's id to where the device belongs to."}
     * @param  array $loginData   No logindata needed to query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { "deviceid" : [A list of logged out device id's.]} }
     */
    public function logoutDevice(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("deviceid", $data) && array_key_exists("userid", $data)){
        $deviceID = $data["deviceid"];

        try{
          $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated = ? WHERE userid = ? AND id = ?",
                                        array(1, $data["userid"], $deviceID));

          return array("status" => 0, "message" => "Successfully logged out device.", "data" => array("deviceid" => $deviceID));
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Sends a invitation mail to a user.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's id where the mail should be send to."}
     * @param  array $loginData   No logindata needed to query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function sendInvitationMail(array $data, array $loginData = NULL): array
    {
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
            To get your login password ask the user who invited you or click <b>'Forgot password'</b> in the login window.<br><br>
            Have fun :)<br>
            ";

            $mailingstatus = $this->mailing_api->sendMail(array($userdata["email"]), "Chia Management invitation" , $message);

            return array("status" => 0, "message" => "Successfully sent invitation mail to user with ID {$data["userID"]}.");
          }else{
            return $userdata;
          }
        }else{
          return $this->logging_api->getErrormessage("001");
        }
      }
    }

    /**
     * Sends an email with an reset link attached to a user if existing.
     * Will always send a success message even the user is not existing.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param string $username  The user's username which wants his password be reset
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function requestUserPasswordReset(string $username): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id, name, lastname, email FROM users WHERE username = ? AND enabled = 1", array($username));
        $userdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(count($userdata) == 1){
          $userdata = $userdata[0];
          $resetLink = bin2hex(random_bytes(35));
          $resetLinkEncrypted = $this->encryption_api->encryptString($resetLink);
          $keyvaliduntil = new \DateTime();
          $keyvaliduntil->modify("+15 minutes");

          $resetPWLink = "{$this->ini["app_protocol"]}://{$this->ini["app_domain"]}{$this->ini["frontend_url"]}/password-reset.php?pw-reset-key={$resetLink}";

          $message = "<h1>Password reset</h1><br>Hello {$userdata["name"]} {$userdata["lastname"]},<br><br>you recently decided to reset your password.<br>Please click <a href='$resetPWLink'>here</a> to comlete the request.<br><br>This link will be valid until {$keyvaliduntil->format("Y-m-d H:i:s")}.";
          $mailingstatus = $this->mailing_api->sendMail(array($userdata["email"]), "Chia Management Password Reset" , $message);

          if($mailingstatus["status"] == 0){
            $sql = $this->db_api->execute("UPDATE users_pwresets SET expired = 1 WHERE userid = ?", array($userdata["id"]));
            $sql = $this->db_api->execute("Insert INTO users_pwresets (id, userid, linkkey, expiration, expired) VALUES (NULL, ?, ?, ?, 0)", array($userdata["id"], $resetLinkEncrypted, $keyvaliduntil->format("Y-m-d H:i:s")));
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }

        return array("status" => 0, "message" => "Successfully sent email with resetlink to user {$username}.");
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }
    }

    /**
     * Checks if a given resetlink is valid.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param string $resetLink   The reset link which should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkResetLinkValid(string $resetLink): array
    {
      try{
        $encryptedResetKey = $this->encryption_api->encryptString($resetLink);
        $sql = $this->db_api->execute("SELECT Count(*) as count FROM users_pwresets WHERE linkkey = ? AND expired = 0 AND expiration >= NOW()", array($encryptedResetKey));

        if($sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"] == 1){
          return array("status" => 0, "message" => "Reset link valid.");
        }else{
          return $this->logging_api->getErrormessage("001");
        }
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }
    }

    /**
     * Resets a user's password to a password of his choice.
     * This is a REST function.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param string $resetLink        The reset link stated in the mail
     * @param string $newUserPassword  The new password which should be set
     * @return array                   {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function resetPassword(string $resetKey, string $newUserPassword): array
    {
      try{
        $encryptedResetKey = $this->encryption_api->encryptString($resetKey);
        $sql = $this->db_api->execute("SELECT userid FROM users_pwresets WHERE linkkey = ? AND expired = 0 AND expiration >= NOW()", array($encryptedResetKey));
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(count($sqreturn) == 1){
          $userid = $sqreturn[0]["userid"];
          $pwreset = $this->resetUserPassword(array("userID" => $userid, "password" => $newUserPassword));

          if($pwreset["status"] == 0){
            $sql = $this->db_api->execute("UPDATE users_pwresets SET expired = 1 WHERE userid = ?", array($userid));
            return array("status" => 0, "message" => "Successfully reset password.");
          }else{
            return $pwreset;
          }
        }else{
          return $this->logging_api->getErrormessage("001");
        }

        return array("status" => 0, "message" => "Successfully set new password.");
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }
    }
  }
?>
