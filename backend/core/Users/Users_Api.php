<?php
  namespace ChiaMgmt\Users;
  use React\Promise;
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
    public function savePersonalInfo(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(isset($data["userID"]) && isset($data["name"]) && isset($data["lastname"]) && isset($data["email"]) && isset($data["username"])){
          $checkUserExists = Promise\resolve($this->checkUsernameExists($data["username"], $data["userID"]));
          $checkUserExists->then(function($checkUserExists_returned) use(&$resolve, $data){
            if($checkUserExists_returned["status"] == "0"){
              $update_personal_info = Promise\resolve((new DB_Api())->execute("UPDATE users SET name = ?, lastname = ?, email = ?, username = ? WHERE id = ?",
                                                      array($data["name"], $data["lastname"], $data["email"], $data["username"], $data["userID"])));

              $update_personal_info->then(function($update_personal_info_returned) use(&$resolve, $data){
                $resolve(array("status" => 0, "message" => "Updated userdata successfully.", "data" => $data));
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("savePersonalInfo", "001", $e));
              });
            }else{
              $resolve($checkUserExists_returned);
            }
          });
        }else{
          $resolve($this->logging_api->getErrormessage("savePersonalInfo", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Adds a user to the system.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "name" : "The user's forename", "lastname" : "The user's lastname", "email" : "The user's email address", "username" : "The user's username", "password" : "The user's password in cleartext" }
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly added data]} }
     */
    public function addUser(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("name", $data) && array_key_exists("lastname", $data) &&
          array_key_exists("email", $data) && array_key_exists("username", $data) && array_key_exists("password", $data)
        ){        
          $data["id"] = 0;
          if($this->userInfoNotEmpty($data)["status"] == 0){
            $data_to_check = [
              Promise\resolve($this->checkUsernameExists($data["username"])),
              Promise\resolve($this->checkPasswordStrength($data["password"]))
            ];

            Promise\all($data_to_check)->then(function($data_to_check_returned) use(&$resolve, $data){
              $userexists = $data_to_check_returned[0];
              $pwcheck = $data_to_check_returned[1];

              if($userexists["status"] == 0 && $pwcheck["status"] == 0){
                $salt = bin2hex(random_bytes(30));
                $new_salted_pw = $salted_hash=hash('sha256',$data["password"].$salt.$this->ini['serversalt']);

                $insert_new_user = Promise\resolve((new DB_Api())->execute("INSERT INTO users (id, username, name, lastname, password, salt, email) VALUES (NULL, ?, ?, ?, ?, ?, ?)",
                                                    array($data["username"], $data["name"], $data["lastname"], $new_salted_pw, $salt, $data["email"])));

                $insert_new_user->then(function($insert_new_user_returned) use(&$resolve, $data){
                  unset($data["password"]);
                  $data["id"] = $insert_new_user_returned->insertId;
                  $resolve(array("status" => 0, "message" => "Updated userdata successfully.", "data" => [$data["id"] => $data]));
                })->otherwise(function(\Exception $e) use (&$resolve){
                  $resolve($this->logging_api->getErrormessage("addUser", "001", $e));
                });
              }else{
                if($userexists["status"] == 1) return $resolve($userexists);
                if($pwcheck["status"] == 1) return $resolve($pwcheck);
              }
            });
          }else{
            $resolve($this->logging_api->getErrormessage("addUser", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("addUser", "003"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Edit the information regarding an existing system user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "id" : "The user's db id", "name" : "The user's forename", "lastname" : "The user's lastname", "email" : "The user's email address", "username" : "The user's username", "password" : "The password which should be changed. Not mandatory." }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly added data]} }
     */
    public function editUserInfo(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("id", $data) && array_key_exists("name", $data) && array_key_exists("lastname", $data) &&
          array_key_exists("email", $data) && array_key_exists("username", $data)
        ){
          $data["userID"] = $data["id"];
          if($this->userInfoNotEmpty($data)["status"] == 0){
            $infos_to_load[] = Promise\resolve($this->savePersonalInfo($data));
            if(array_key_exists("password", $data)) $infos_to_load[] = Promise\resolve($this->checkPasswordStrength($data["password"]));

            Promise\all($infos_to_load)->then(function($all_returned) use(&$resolve, $data){
              $pwreset = Promise\resolve(NULL);
              if(array_key_exists("password", $data)){
                $pwcheck = $all_returned[1];
                if($pwcheck["status"] == 0){
                  $pwreset_set = Promise\resolve($this->resetUserPassword($data));
                  $pwreset = $pwreset_set->then(function($pwreset_returned){
                    if($pwreset_returned["status"] != 0){
                      return $pwreset_returned;
                    }
                  });
                }else{
                  $resolve($pwcheck);
                }
              }

              $pwreset->then(function($pwreset_returned) use(&$resolve, $all_returned, $data){
                if(($all_returned[0]["status"] == 0 && is_null($pwreset_returned)) || ($all_returned[0]["status"] == 0 && !is_null($pwreset_returned) && $pwreset_returned["status"] == 0)){
                  unset($data["password"]);
                  $resolve(array("status" => 0, "message" => "Updated userdata successfully.", "data" => $data));
                }else{
                  $resolve($this->logging_api->getErrormessage("editUserInfo", "001"));
                }
              });
            });
          }else{
            $resolve($this->logging_api->getErrormessage("editUserInfo", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("editUserInfo", "003"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Disables an existing system user. The admin account and own account cannot be disabled.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's db id" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The id which was disabled.]} }
     */
    public function disableUser(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("userID", $data)){
          if($loginData["userid"] != $data["userID"] && $data["userID"] > 1){
            $disable_user = Promise\resolve((new DB_Api())->execute("UPDATE users SET enabled = 0 WHERE id = ?", array($data["userID"])));
            $disable_user->then(function($disable_user_returned) use(&$resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully disabled user with ID {$data["userID"]}.", "data" => $data));
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("disableUser", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("disableUser", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("disableUser", "003"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function removeDisabledUser(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("userID", $data)){
          if($loginData["userid"] != $data["userID"] && $data["userID"] > 1){
            $delete_user = Promise\resolve((new DB_Api)->execute("DELETE FROM users WHERE id = ? AND enabled = ? AND id > 1", array($data["userID"], 0)));
            $delete_user->then(function($delete_user_returned) use(&$resolve, $data){
              if($delete_user_returned->affectedRows == 1){
                $resolve(array("status" => 0, "message" => "Successfully removed user with id {$data["userID"]}.", "data" => ["id" => $data["userID"]]));
              }else{
                $resolve($this->logging_api->getErrormessage("removeDisabledUser", "001"));
              }
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("removeDisabledUser", "002", $e));
            });
          }else{
            if($loginData["userid"] != $data["userID"]) return $resolve($this->logging_api->getErrormessage("removeDisabledUser", "003"));
            if($$data["userID"] == 1) return $resolve($this->logging_api->getErrormessage("removeDisabledUser", "004"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("removeDisabledUser", "005"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Enables an existing system user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's db id" }
     * @param  array $loginData   No logindata needed to use this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The id which was enabled.] } }
     */
    public function enableUser(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("userID", $data)){
          $disable_user = Promise\resolve((new DB_Api())->execute("UPDATE users SET enabled = 1 WHERE id = ?", array($data["userID"])));
          $disable_user->then(function($disable_user_returned) use(&$resolve, $data){
            $resolve(array("status" => 0, "message" => "Successfully enabled user with ID {$data["userID"]}.", "data" => $data));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("enableUser", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("enableUser", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function getUserData(int $userID = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userID){
        if(is_Null($userID)){
          $all_userdata = Promise\resolve((new DB_Api())->execute("SELECT id, username, name, lastname, email, enabled FROM users", array()));
          $all_userdata->then(function($all_userdata_returned) use(&$resolve){
            foreach($all_userdata_returned->resultRows AS $key => $value){
              $returndata[$value["id"]] = $value;
            }
            $resolve(array("status" => 0, "message" => "Successfully loaded user information.", "data" => $returndata));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getUserData", "001", $e));
          });
        }else{
          $some_userdata = Promise\resolve((new DB_Api())->execute("SELECT id, username, name, lastname, email, enabled FROM users", array()));
          $some_userdata->then(function($some_userdata_returned) use(&$resolve){
            $resolve(array("status" => 0, "message" => "Successfully loaded user information.", "data" => $some_userdata_returned->resultRows[0]));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getUserData", "002", $e));
          });
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function checkUsernameExists(string $username, int $userID = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($username, $userID){
        if(is_null($userID)){
          $usercount = Promise\resolve((new DB_Api)->execute("SELECT count(*) AS usercount from users where username = ?", array($username)));
        }else{
          $usercount = Promise\resolve((new DB_Api)->execute("SELECT count(*) AS usercount from users where username = ? AND id <> ?", array($username, $userID)));
        }

        $usercount->then(function($usercount_returned) use(&$resolve){
          if($usercount_returned->resultRows[0]["usercount"] == 0){
            $resolve(array("status" => 0, "message" => "User does not exist!"));
          }else{
            $resolve($this->logging_api->getErrormessage("checkUsernameExists", "001"));
          }
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("checkUsernameExists", "002",$e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the own db stored userdata.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @param  int    $userID   The userid for which the data should be returned for.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The found on the db stored data.]} }
     */
    public function getOwnUserData(int $userID): object
    {
      return $this->getUserData($userID);
    }

    /**
     * Generates a new backup key for a user.
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array  $data         { "userID" : "The user's id." }
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The newly created backup key.]} }
     */
    public function generateNewBackupKey(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("userID", $data)){
          $backupkey = bin2hex(random_bytes(25));
          $encryptedbackupkey = $this->encryption_api->encryptString($backupkey);
          $backup_key_tasks = [
            Promise\resolve((new DB_Api)->execute("UPDATE users_backupkeys SET valid = 0 where userid = ?", array($data["userID"]))),
            Promise\resolve((new DB_Api)->execute("INSERT INTO users_backupkeys (id, userid, backupkey) VALUES (NULL, ?, ?)", array($data["userID"], $encryptedbackupkey)))
          ];

          Promise\all($backup_key_tasks)->then(function($backup_key_tasks_returned) use(&$resolve, $data, $backupkey){
            $resolve(array("status" => 0, "message" => "Generated new backup key for User {$data["userID"]}.", "data" => $backupkey));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("generateNewBackupKey", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("generateNewBackupKey", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function getBackupKey(int $userID): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userID){
        $backup_key = Promise\resolve((new DB_Api())->execute("SELECT backupkey FROM users_backupkeys WHERE userid = ? AND valid = 1", array($userID)));
        $backup_key->then(function($backup_key_returned) use(&$resolve){

          $backup_key_returned = $backup_key_returned->resultRows;

          if($backup_key_returned > 0 && array_key_exists(0, $backup_key_returned) && array_key_exists("backupkey", $backup_key_returned[0])){
            $decryptedkey = $this->encryption_api->decryptString($backup_key_returned[0]["backupkey"]);
          }else{
            $decryptedkey = "";
          }

          $resolve(array("status" => 0, "message" => "Successfully loaded user information.", "data" => $decryptedkey));
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getBackupKey", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if the current password matches the password on the db
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array $data          { "userID" : "The user's id.", "password" : "The password which should be checked." }
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function checkCurrentPassword(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(isset($data["userID"]) && isset($data["password"])){
          $current_pw = Promise\resolve((new DB_Api)->execute("SELECT password, salt from users where id = ?", array($data["userID"])));
          $current_pw->then(function($current_pw_returned) use(&$resolve, $data){
            if(count($current_pw_returned->resultRows) == 1){
              $current_pw_returned = $current_pw_returned->resultRows[0];
              $salt = $current_pw_returned["salt"];

              $current_salted_pw = $current_pw_returned["password"];
              $stated_salted_password = hash('sha256',$data["password"].$salt.$this->ini["serversalt"]);

              if($stated_salted_password == $current_salted_pw){
                $resolve(array("status" => 0, "message" => "Stated password matches current password."));
              }else{
                $resolve($this->logging_api->getErrormessage("checkCurrentPassword", "001"));
              }
            }else{
              $resolve($this->logging_api->getErrormessage("checkCurrentPassword", "002"));
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("checkCurrentPassword", "003",$e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("checkCurrentPassword", "004"));
        }
      };
      
      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if a stated password is strong enough.
     * @param  string $password   The password which should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    private function checkPasswordStrength(string $password): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($password){
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);
  
        if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
          $resolve($this->logging_api->getErrormessage("checkPasswordStrength", "001"));
        }else{
          $resolve(array("status" => 0, "message" => "Password strong enough."));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Resets the in the data array stated user's password
     * Function made for: Web(App)client.
     * @throws Exception $e         Throws an exception on db errors.
     * @param  array $data          { "userID" : "The user's id.", "password" : "The password which should be reset." }
     * @param  array $loginData     No logindata needed to query this function.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function resetUserPassword(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(isset($data["userID"]) && isset($data["password"])){
          $pwcheck = Promise\resolve($this->checkPasswordStrength($data["password"]));
          $pwcheck->then(function($pwcheck_returned) use(&$resolve, $data){
            if($pwcheck_returned["status"] == 0){
              $get_password = Promise\resolve((new DB_Api)->execute("SELECT password, salt from users where id = ?", array($data["userID"])));
              $get_password->then(function($get_password_returned) use(&$resolve, $data){ 
                if(count($get_password_returned->resultRows) == 1){
                  $get_password_returned = $get_password_returned->resultRows[0];
                  $salt = $get_password_returned["salt"];
                  $current_salted_pw = $get_password_returned["password"];
                  $new_salted_pw = hash('sha256',$data["password"].$salt.$this->ini['serversalt']);

                  if($new_salted_pw != $current_salted_pw){
                    $update_password = Promise\resolve((new DB_Api)->execute("UPDATE users SET password = ? where id = ?", array($new_salted_pw, $data["userID"])));
                    $update_password->then(function($update_password_returned) use(&$resolve){
                      $resolve(array("status" => 0, "message" => "Password successfully updated."));
                    })->otherwise(function(\Exception $e) use(&$resolve){
                      $resolve($this->logging_api->getErrormessage("resetUserPassword", "005",$e));
                    });
                  }else{
                    $resolve($this->logging_api->getErrormessage("resetUserPassword", "001"));
                  }
                }else{
                  $resolve($this->logging_api->getErrormessage("resetUserPassword", "002"));
                }
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("resetUserPassword", "003",$e));
              });
            }else{
              $resolve($pwcheck_returned);
            }
          });
        }else{
          $resolve($this->logging_api->getErrormessage("resetUserPassword", "004"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns a list logged in devices.
     * Function made for: Web(App)client.
     * @todo Make this function websocket compatible.
     * @throws Exception $e     Throws an exception on db errors.
     * @param  int    $userID   The userid for which the data should be returned for.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The found on the db stored data.]} }
     */
    public function getLoggedInDevices(int $userID = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userID){
        if(is_null($userID)){
          $logged_in_devices = Promise\resolve((new DB_Api())->execute("SELECT id, userid, logindate, deviceinfo from users_sessions WHERE invalidated = 0", array()));
        }else{
          $logged_in_devices = Promise\resolve((new DB_Api())->execute("SELECT id, userid, logindate, deviceinfo from users_sessions WHERE userid = ? AND invalidated = 0", array($userID)));
        }

        $logged_in_devices->then(function($logged_in_devices_returned) use(&$resolve){
          $resolve(array("status" => 0, "message" => "Successfully loaded all logged in devices.", "data" => $logged_in_devices_returned->resultRows));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getLoggedInDevices", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Logs out an logged in user specific device.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "deviceid" : "The db device id.", "userid" : "The user's id to where the device belongs to."}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { "deviceid" : [A list of logged out device id's.]} }
     */
    public function logoutDevice(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("deviceid", $data) && array_key_exists("userid", $data)){
          $deviceID = $data["deviceid"];
          $logout_device = Promise\resolve((new DB_Api)->execute("UPDATE users_sessions SET invalidated = ? WHERE userid = ? AND id = ?", array(1, $data["userid"], $deviceID)));
          $logout_device->then(function($logout_device_returned) use(&$resolve, $deviceID){
            $resolve(array("status" => 0, "message" => "Successfully logged out device.", "data" => array("deviceid" => $deviceID)));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("logoutDevice", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("logoutDevice", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Sends a invitation mail to a user.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "userID" : "The user's id where the mail should be send to."}
     * @param  array $loginData   No logindata needed to query this function.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function sendInvitationMail(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("userID", $data)){
          if($loginData["userid"] != $data["userID"]){
            $userdata = Promise\resolve($this->getUserData($data["userID"]));
            $userdata->then(function($userdata_returned) use(&$resolve, $data){
              if($userdata_returned["status"] == 0){
                $userdata = $userdata_returned["data"];
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

                $mailingstatus = Promise\resolve($this->mailing_api->sendMail(array($userdata["email"]), "Chia Management invitation" , $message));
                $mailingstatus->then(function($mailingstatus_returned) use(&$resolve, $data){
                  $resolve(array("status" => 0, "message" => "Successfully sent invitation mail to user with ID {$data["userID"]}."));
                });
              }else{
                $resolve($userdata_returned);
              }
            });
          }else{
            $resolve($this->logging_api->getErrormessage("sendInvitationMail", "001"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("sendInvitationMail", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Sends an email with an reset link attached to a user if existing.
     * Will always send a success message even the user is not existing.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param string $username  The user's username which wants his password be reset
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function requestUserPasswordReset(string $username): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($username){
        $user_infos = Promise\resolve((new DB_Api)->execute("SELECT id, name, lastname, email FROM users WHERE username = ? AND enabled = 1", array($username)));
        $user_infos->then(function($user_infos_returned) use(&$resolve, $username){
          $userdata = $user_infos_returned->resultRows;

          if(count($userdata) == 1){
            $userdata = $userdata[0];
            $resetLink = bin2hex(random_bytes(35));
            $resetLinkEncrypted = $this->encryption_api->encryptString($resetLink);
            $keyvaliduntil = new \DateTime();
            $keyvaliduntil->modify("+15 minutes");
  
            $resetPWLink = "{$this->ini["app_protocol"]}://{$this->ini["app_domain"]}{$this->ini["frontend_url"]}/password-reset.php?pw-reset-key={$resetLink}";
  
            $message = "<h1>Password reset</h1><br>Hello {$userdata["name"]} {$userdata["lastname"]},<br><br>you recently decided to reset your password.<br>Please click <a href='$resetPWLink'>here</a> to complete the request.<br><br>This link will be valid until {$keyvaliduntil->format("Y-m-d H:i:s")}.";
            $mailingstatus = Promise\resolve($this->mailing_api->sendMail(array($userdata["email"]), "Chia Management Password Reset" , $message));
            $mailingstatus->then(function($mailingstatus_returned) use(&$resolve, $username, $userdata, $resetLinkEncrypted, $keyvaliduntil){             
              if($mailingstatus_returned["status"] == 0){
                $expire_old_keys = Promise\resolve((new DB_Api)->execute("UPDATE users_pwresets SET expired = 1 WHERE userid = ?", array($userdata["id"])));
                $expire_old_keys->then(function($expire_old_keys_returned) use($userdata, $resetLinkEncrypted, $keyvaliduntil){
                  $add_new_entry = Promise\resolve((new DB_Api)->execute("INSERT INTO users_pwresets (id, userid, linkkey, expiration, expired) VALUES (NULL, ?, ?, ?, 0)", array($userdata["id"], $resetLinkEncrypted, $keyvaliduntil->format("Y-m-d H:i:s"))));
                  $add_new_entry->otherwise(function(\Exception $e) use(&$resolve){
                    $resolve(Promise\resolve($this->logging_api->getErrormessage("requestUserPasswordReset","003", $e)));
                  });
                })->otherwise(function(\Exception $e) use(&$resolve){
                  $resolve(Promise\resolve($this->logging_api->getErrormessage("requestUserPasswordReset","004", $e)));
                });
                $resolve(array("status" => 0, "message" => "Successfully sent email with resetlink to user {$username}."));
              }else{
                $resolve(Promise\resolve($this->logging_api->getErrormessage("requestUserPasswordReset","001")));
              }
            });
          }
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve(Promise\resolve($this->logging_api->getErrormessage("requestUserPasswordReset","002", $e)));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if a given resetlink is valid.
     * Function made for: Web(App)client.
     * @throws Exception $e       Throws an exception on db errors.
     * @param string $resetLink   The reset link which should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkResetLinkValid(string $resetLink): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($resetLink){
        $encryptedResetKey = $this->encryption_api->encryptString($resetLink);
        $check_reset_link = Promise\resolve((new DB_Api)->execute("SELECT Count(*) as count FROM users_pwresets WHERE linkkey = ? AND expired = 0 AND expiration >= NOW()", array($encryptedResetKey)));
        $check_reset_link->then(function($check_reset_link_returned) use(&$resolve){
          if($check_reset_link_returned->resultRows[0]["count"] == 1){
            $resolve(array("status" => 0, "message" => "Reset link valid."));
          }else{
            $resolve(Promise\resolve($this->logging_api->getErrormessage("checkResetLinkValid","001")));
          }
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve(Promise\resolve($this->logging_api->getErrormessage("checkResetLinkValid","002", $e)));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function resetPassword(string $resetKey, string $newUserPassword): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($resetKey, $newUserPassword){
        $encryptedResetKey = $this->encryption_api->encryptString($resetKey);
        $user_data = Promise\resolve((new DB_Api)->execute("SELECT userid FROM users_pwresets WHERE linkkey = ? AND expired = 0 AND expiration >= NOW()", array($encryptedResetKey)));
        $user_data->then(function($user_data_returned) use(&$resolve, $newUserPassword){
          $sqreturn = $user_data_returned->resultRows;

          if(count($sqreturn) == 1){
            $userid = $sqreturn[0]["userid"];

            $pwreset = Promise\resolve($this->resetUserPassword(array("userID" => $userid, "password" => $newUserPassword)));
            $pwreset->then(function($pwreset_returned) use(&$resolve, $userid){
              if($pwreset_returned["status"] == 0){
                $invalidate_key = Promise\resolve((new DB_Api)->execute("UPDATE users_pwresets SET expired = 1 WHERE userid = ?", array($userid)));
                $invalidate_key->then(function($invalidate_key_returned) use(&$resolve){
                  $resolve(array("status" => 0, "message" => "Successfully reset password."));
                })->otherwise(function(\Exception $e) use(&$resolve){
                  $resolve(Promise\resolve($this->logging_api->getErrormessage("resetPassword","003", $e)));
                });
              }else{
                $resolve($pwreset_returned);
              }
            });
          }else{
            $resolve(Promise\resolve($this->logging_api->getErrormessage("resetPassword","001")));
          }

        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve(Promise\resolve($this->logging_api->getErrormessage("resetPassword","002", $e)));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>