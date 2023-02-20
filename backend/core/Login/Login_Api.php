<?php
  namespace ChiaMgmt\Login;

  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Second_Factor\Second_Factor_Api;
  
  require __DIR__ . '/../../../vendor/autoload.php';

  /**
   * The Login_Api is one of two restfull enabled api classes and handles all webapp login based actions.
   * This class is only used by the webclient to fullfill login/logout and session validation actions.
   * @version 0.2.0
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Login_Api{
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the Second Factor Class.
     * @var Second_Factor_Api
     */
    private $second_factor_api;
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
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->logging_api = new Logging_Api($this, $server);
      $this->second_factor_api = new Second_Factor_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
      $this->encryption_api = new Encryption_Api();
    }

    /**
     * The login method so a user is able to login.
     * Function made for: WebGUI/App
     * @throws Exception $e          Throws an exception on db errors.
     * @param  string $username       The username (or socalled userid)
     * @param  string $password       The user's password
     * @param  bool $stayloggedin     If the user wants to stays logged in (max. 30 days). True = Stay logged in, False = Sessions ends after 30 minutes.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function login(string $username, string $password, bool $stayloggedin)
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($username, $password, $stayloggedin){
        $current_user_infos = Promise\resolve($this->getCurrentUserInfos($username));
        $current_user_infos->then(function($userdata) use(&$resolve, &$reject, $password, $stayloggedin){
          if($userdata["status"] == 0){
            $salted_hash=hash('sha256',$password.$userdata["data"]["salt"].$this->ini['serversalt']);
          }else{
            $resolve($userdata);
          }

          if(isset($userdata["data"]["password"]) && $userdata["data"]["password"] == $salted_hash){
            $set_session = Promise\resolve($this->setSession($userdata["data"]["id"]));
            $set_session->then(function($session_set) use(&$resolve, &$reject, $userdata, $stayloggedin){
              $this->logging_api->logtofile(0, 0, "User with ID " . $userdata["data"]["id"] . " logged in from " . $_SERVER['REMOTE_ADDR'] . ".;");
                         
              if($session_set["status"] == 0){               
                $security_promise = Promise\resolve((new System_Api())->getSpecificSystemSetting("security"));
                $mailing_promise = Promise\resolve((new System_Api())->getSpecificSystemSetting("mailing"));

                $security_promise->then(function($security_returned) use(&$resolve, &$reject, $mailing_promise, $session_set, $stayloggedin){   
                  $mailing_promise->then(function($mailing_returned) use(&$resolve, &$reject, $security_returned, $session_set, $stayloggedin){
                    $authkeypassed = 1;
                    $totpmobilepassed = 1;
                    $sendauthkey = false;
                    $checktotpmobile = false;

                    if($security_returned["status"] == 0 && array_key_exists("security", $security_returned["data"]) &&
                      $security_returned["data"]["security"]["TOTP"]["value"] == 1 && $mailing_returned["status"] == 0 &&
                      array_key_exists("mailing", $mailing_returned["data"]) && $mailing_returned["data"]["mailing"]["confirmed"] == 1
                    ){
                      $authkeypassed = 0;
                      $sendauthkey = true;
                    }

                    $totp_enabled = Promise\resolve($this->second_factor_api->getTOTPEnabled(["userID" => $session_set["data"]["userid"]]));
                    $totp_enabled->then(function($totpenabled_returned) use(&$resolve, $session_set, $stayloggedin, $authkeypassed, $totpmobilepassed, $sendauthkey, $checktotpmobile){
                      if($totpenabled_returned["status"] == 0){
                        $totpmobilepassed = 0;
                        $checktotpmobile = true;
                      }

                      $user_sessions_promise = Promise\resolve((new DB_Api())->execute("Insert INTO users_sessions (id, userid, sessid, authkeypassed, totpmobilepassed, deviceinfo, validuntil) VALUES (NULL, ?, ?, ?, ?, ?, " . ($stayloggedin ? "NULL" : "DATE_ADD(now(), INTERVAL 30 MINUTE)" ) . ")", array($session_set["data"]["userid"], $session_set["data"]["sessid"], $authkeypassed, $totpmobilepassed, $_SERVER['HTTP_USER_AGENT'])));
                      $user_sessions_promise->then(function($user_sessions_promise_returned) use(&$resolve, $session_set, $totpmobilepassed, $sendauthkey, $checktotpmobile){                      
                        if($sendauthkey && !$checktotpmobile){
                          $generate_send_key = Promise\resolve($this->generateAndsendAuthKey($session_set["data"]["userid"], $session_set["data"]["sessid"]));
                          $generate_send_key->then(function($generate_send_key_returned) use(&$resolve){
                            $resolve($this->logging_api->getErrormessage("login", "001"));
                          });
                        }else if(!$sendauthkey && $checktotpmobile){
                          $resolve($this->logging_api->getErrormessage("login", "002"));
                        }else if($sendauthkey && $checktotpmobile){
                          $generate_send_key = Promise\resolve($this->generateAndsendAuthKey($session_set["data"]["userid"], $session_set["data"]["sessid"]));
                          $generate_send_key->then(function($generate_send_key_returned) use(&$resolve){
                            $resolve($this->logging_api->getErrormessage("login", "003"));
                          });
                        }else{
                          $resolve(array("status" => "0", "message" => "Logged in."));
                        }
                      })->otherwise(function (\Exception $e) use(&$resolve){
                        $resolve($this->logging_api->getErrormessage("login", "004", $e));
                      });
                    });
                  });
                });
              }else{
                $resolve($session_set);
              }
            });
          }else{
            $resolve($this->logging_api->getErrormessage("login", "005","User with ID " . $userdata["data"]["id"] . " tried to log in from " . $_SERVER['REMOTE_ADDR'] . "."));
          }
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if the authkey is valid when second factor via e-mail is enabled.
     * Furthermore it will be checked if the user already has valid cookies set. If the session does not exist the user will not be logged in.
     * Function made for: WebGUI/App
     * @throws Exception $e    Throws an exception on db errors.
     * @param  string $authkey The stated outkey.
     * @return array           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkAuthKey(string $authkey): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($authkey){
        if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
          $userid = $_COOKIE['user_id'];
          $sessionid = $_COOKIE['PHPSESSID'];
          $currentdate = new \DateTime();

          $get_current_authkey = Promise\resolve((new DB_Api())->execute("SELECT validuntil FROM users_authkeys WHERE authkey = ? AND validuntil >= ? LIMIT 1",
                                                  array($authkey, $currentdate->format("Y-m-d H:i:s"))));
          $get_current_authkey->then(function($get_current_authkey_returned) use(&$resolve, $userid, $sessionid){ 
            $returned_rows = $get_current_authkey_returned->resultRows;

            if(count($returned_rows) == 1 && array_key_exists("validuntil", $returned_rows[0])){
              $update_authkeys = Promise\resolve((new DB_Api())->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid)));
              $update_sessions = Promise\resolve((new DB_Api())->execute("UPDATE users_sessions SET authkeypassed = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid)));

              Promise\all([$update_authkeys, $update_sessions])->then(function($updates_returned) use(&$resolve, $userid, $sessionid){
                $check_login = Promise\resolve($this->checklogin($sessionid, $userid));
                $check_login->then(function($check_login_returned) use(&$resolve){
                  $resolve($check_login_returned);
                });
              })->otherwise(function (\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("checkAuthKey", "004", $e));
              });
            }else{
              $resolve($this->logging_api->getErrormessage("checkAuthKey", "001"));
            }
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("checkAuthKey", "002", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("checkAuthKey", "003"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if the TOTP mobile key is valid when second factor via TOTP mobile is enabled.
     * Furthermore it will be checked if the user already has valid cookies set. If the session does not exist the user will not be logged in.
     * Function made for: WebGUI/App
     * @throws Exception $e    Throws an exception on db errors.
     * @param  string $authkey The stated outkey.
     * @return array           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkTOTPMobilePassed(string $key): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($key){
        if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
          $userid = $_COOKIE['user_id'];
          $sessionid = $_COOKIE['PHPSESSID'];

          $totp_proof = Promise\resolve($this->second_factor_api->totpProof(["userID" => $userid, "totpkey" => $key]));
          $totp_proof->then(function($totp_proof_returned) use(&$resolve, $userid, $sessionid){
            if($totp_proof_returned["status"] == 0){
              $set_totp_mobile_passed = Promise\resolve((new DB_Api())->execute("UPDATE users_sessions SET totpmobilepassed = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid)));
              $set_totp_mobile_passed->then(function($totp_proof_returned) use(&$resolve, $userid, $sessionid){
                $check_login = Promise\resolve($this->checklogin($sessionid, $userid));
                $check_login->then(function($check_login_returned) use(&$resolve){
                  $resolve($check_login_returned);
                });
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("checkTOTPMobilePassed", "001", $e));
              });
            }else{
              $resolve($totp_proof_returned);
            }
          });
        }else{
          $resolve($this->logging_api->getErrormessage("checkTOTPMobilePassed", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Generates a uniqe outkey which will be directly send to the user which currently wants to login. Requires a valid set-up e-mail address.
     * Function made for: WebGUI/App
     * @throws Exception $e          Throws an exception on db errors.
     * @param  int $userid      The user's id who wants to login.
     * @param  string $sessid   The user's current session id.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function generateAndsendAuthKey(int $userid = NULL, string $sessid = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userid, $sessid){
        $login_status = Promise\resolve($this->checklogin($sessid, $userid));
        $login_status->then(function($login_status_returned) use(&$resolve, $userid){
          $login_status_returned = $login_status_returned["status"];

          if($login_status_returned == 0){
            $resolve(array("status" => 0, "message" => "You are currently logged in. No need to send authkey."));
          }else if($login_status_returned == "007009002"){
            if(array_key_exists("user_id", $_COOKIE) || !is_null($userid)){
              if(array_key_exists('user_id', $_COOKIE)) $userid = $_COOKIE['user_id'];
              $authkey = bin2hex(random_bytes(25));

              $user_mail = Promise\resolve((new DB_Api())->execute("SELECT email FROM users WHERE id = ?", array($userid)));
              $update_authkeys = Promise\resolve((new DB_Api())->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid)));

              $user_mail->then(function($user_mail_returned) use(&$resolve, $update_authkeys, $authkey, $userid){
                $email = $user_mail_returned->resultRows[0]["email"];
                
                $update_authkeys->then(function($update_authkeys_returned) use(&$resolve, $email, $authkey, $userid){
                  $keyvaliduntil = new \DateTime();
                  $keyvaliduntil->modify("+15 minutes");

                  $insert_authkey = Promise\resolve((new DB_Api())->execute("Insert INTO users_authkeys (id, userid, authkey, validuntil) VALUES (NULL, ?, ?, ?)",
                                                                            array($userid, $authkey, $keyvaliduntil->format("Y-m-d H:i:s"))));

                  $insert_authkey->then(function($insert_authkey_returned) use(&$resolve, $email, $authkey, $keyvaliduntil){
                    $message = "<h1>Complete your login</h1><br>To complete your login enter this authkey when prompted:<br>$authkey<br><br>This key will be valid until {$keyvaliduntil->format("Y-m-d H:i:s")}.";

                    $send_mail_authkey = Promise\resolve((new Mailing_Api())->sendMail(array($email), "Chia Management Loginkey" , $message));
                    $send_mail_authkey->then(function($send_mail_authkey_returned) use(&$resolve){
                      $resolve(array("status" => 0, "message" => "Successfully (re)sent authmail."));
                    });
                  })->otherwise(function(\Exception $e) use(&$resolve){
                    $resolve($this->logging_api->getErrormessage("generateAndsendAuthKey", "005", $e));
                  });
                })->otherwise(function(\Exception $e) use(&$resolve){
                  $resolve($this->logging_api->getErrormessage("generateAndsendAuthKey", "004", $e));
                });
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("generateAndsendAuthKey", "001", $e));
              });
              
              $resolve($authkey);
            }else{
              $resolve($this->logging_api->getErrormessage("generateAndsendAuthKey", "002"));
            }
          }else{
            $resolve($this->logging_api->getErrormessage("generateAndsendAuthKey", "003"));
          }
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Invalidates a certain session of a user. Will be called on logout action or when the user is in login screen and cancels authkey check with "go back".
     * Function made for: WebGUI/App
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function invalidateLogin(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
          $userid = $_COOKIE['user_id'];
          $sessionid = $_COOKIE['PHPSESSID'];

          $invalidate_user = Promise\resolve((new DB_Api())->execute("UPDATE users_sessions SET invalidated = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid)));
          $invalidate_user->then(function($invalidate_user_returnded) use(&$resolve){
            setcookie('user_id', null, -1, '/');
            setcookie('PHPSESSID', null, -1, '/');

            $resolve(array("status" => 0, "message" => "Successfully invalidated (pending) login."));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("invalidateLogin", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("invalidateLogin", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Gets some user infos from a particular user ID (not db ID).
     * Function made for: WebGUI/App
     * @param  string $username The username
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" => {[Found db stored userdata]}}
     */
    public function getCurrentUserInfos(string $username): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($username){  
        $user_infos = Promise\resolve(
          (new DB_Api())->execute("SELECT id,name,lastname,email,password,salt FROM users WHERE username = ? AND enabled = 1",
                                  array($username))
        );

        $user_infos->then(function($user_infos_returned) use(&$resolve){
          if(count($user_infos_returned->resultRows) == 0){
            $resolve($this->logging_api->getErrormessage("001","An unknown user tried to log in from " . $_SERVER['REMOTE_ADDR'] . "."));
          }

          $resolve(array("status" => 0,"message" => "Data successfully loaded.","data" => $user_infos_returned->resultRows[0]));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getCurrentUserInfos","001",$e));
        });
      };
      
      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Sets the session after a user logged in successfully.
     * Function made for: WebGUI/App
     * @param int $id The users id which logged in
     * @return array  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : { "userid" : "[userid]", "sessid" : [session ID]}}
     */
    public function setSession(int $userid): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userid){ 
        try{
          if (session_status() != PHP_SESSION_NONE) session_destroy();
  
          $sessionID = session_create_id();
          $currentCookieParams = session_get_cookie_params();
  
          setcookie(
            'PHPSESSID',//name
            $sessionID,//value
            strtotime('+30 days'),//expires at end of session
            "/",//path
            $currentCookieParams['domain'],//domain
            true, //secure
            true//httponly
            );
  
          setcookie(
            'user_id',//name
            $userid,//value
            strtotime('+30 days'),//expires at end of session
            "/",//path
            $currentCookieParams['domain'],//domain
            true, //secure
            true //httponly
          );
  
          $resolve(array("status" => 0, "message" => "Session successfully set!", "data" => array("userid" => $userid, "sessid" => $sessionID)));
        }catch(\Exception $e){
          $resolve($this->logging_api->getErrormessage("setSession", "001",$e));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if a user session is currently valid.
     * Function made for: WebGUI/App
     * @param  string $sessionid  Users's sessionid from which the loginstatus should be checked.
     * @param  int $userid        Users's id from which the loginstatus should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checklogin(string $sessionid = NULL, int $userid = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($sessionid, $userid){       
        if((isset($_COOKIE['user_id']) || !is_null($userid)) &&
        (isset($_COOKIE['PHPSESSID']) || !is_null($sessionid))
        ){
          if(is_null($userid)) $userid = $_COOKIE['user_id'];
          if(is_null($sessionid)) $sessionid = $_COOKIE['PHPSESSID'];

          $invaldite_logins = Promise\resolve($this->invalidateAllNotLoggedin());
          $invaldite_logins->then(function($invalidate_returned) use(&$resolve){
            if($invalidate_returned["status"] != 0) $resolve($invalidate_returned);
          });

          $user_session = Promise\resolve((new DB_Api())->execute("SELECT authkeypassed, validuntil, totpmobilepassed FROM users_sessions WHERE userid = ? AND sessid = ? AND invalidated = 0",
                                          array($userid, $sessionid)));

          $user_session->then(function($session_returned) use(&$resolve, $userid, $sessionid){
            $session_returned = $session_returned->resultRows;

            if(Count($session_returned) > 0){
              $session_returned = $session_returned[0];
              
              if($session_returned["authkeypassed"] == 1 && $session_returned["totpmobilepassed"] == 1){
                if(is_null($session_returned["validuntil"])){
                  $resolve(array("status" => "0", "message" => "User with id {$userid} is logged in."));
                }else{
                  $validuntil = new  \DateTime($session_returned["validuntil"]);
                  $currentdate = new \DateTime();
  
                  if($currentdate <= $validuntil){
                    $currentdate->modify("+30 minutes");

                    $user_session_set = Promise\resolve((new DB_Api())->execute("UPDATE users_sessions SET validuntil = ? WHERE userid = ? AND sessid = ?", array($currentdate->format("Y-m-d H:i:s"), $userid, $sessionid)));
                    $user_session_set->then(function($session_set_returnded) use(&$resolve, $userid){
                      $resolve(array("status" => "0", "message" => "User with id {$userid} is logged in."));
                    })->otherwise(function (\Exception $e) use(&$resolve){
                      $resolve($this->logging_api->getErrormessage("checklogin","008", $e));
                    });
                  }else{
                    $resolve($this->logging_api->getErrormessage("checklogin","001"));
                  }
                }
              }else{
                if($session_returned["authkeypassed"] == 0) $resolve($this->logging_api->getErrormessage("checklogin","002"));
                if($session_returned["totpmobilepassed"] == 0) $resolve($this->logging_api->getErrormessage("checklogin","003"));
                $resolve($this->logging_api->getErrormessage("checklogin","004"));
              }
            }else{
              $resolve($this->logging_api->getErrormessage("checklogin","005"));
            }
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("checklogin","006", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("checklogin","007","", false));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Invalidates all logins where the login process where not finished or the session interval (max 30 days) exceded.
     * Function made for: Api/Backend
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function invalidateAllNotLoggedin(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $invalidate_logins = Promise\resolve(
          (new DB_Api())->execute("UPDATE users_sessions SET invalidated =
                                    CASE
                                      WHEN (invalidated = 0 AND validuntil IS NOT NULL AND validuntil <= DATE_ADD(NOW(), INTERVAL 5 MINUTE)) OR
                                            (authkeypassed = 0 AND NOW() >= DATE_ADD(logindate, INTERVAL 1 HOUR) AND invalidated = 0) OR
                                            (NOW() >= DATE_ADD(logindate, INTERVAL 30 DAY))
                                      THEN 1
                                      ELSE invalidated
                                    END", array())
        );

        $invalidate_logins->then(function($session_returned) use(&$resolve){
          $resolve(array("status" => 0, "message" => "Successfully invalidated not logged in session."));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("invalidateAllNotLoggedin","001",$e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if the stated backup key is valid and sets the user session as logged in. It skips all other TOTP checks.
     * Function made for: WebGUI/App
     * @param  string $backupkey  The user who wants to login and his backup key.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkBackupKeyValid(string $backupkey): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($backupkey){
        if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
          $checklogin = Promise\resolve($this->checklogin());
          $checklogin->then(function($checklogin_returned) use(&$resolve, $backupkey){
            $userid = $_COOKIE['user_id'];
            $sessionid = $_COOKIE['PHPSESSID'];
            
            if($checklogin_returned["status"] == "007009002" || $checklogin_returned["status"] == "007009003"){
              $encrpyted_backupkey = $this->encryption_api->encryptString($backupkey);

              $check_backup_key = Promise\resolve((new DB_Api())->execute("SELECT Count(*) AS valid_count FROM users_backupkeys WHERE userid = ? AND backupkey = ? AND valid = 1", array($userid, $encrpyted_backupkey)));
              $check_backup_key->then(function($check_backup_key_returned) use(&$resolve, $userid, $sessionid){
                if($check_backup_key_returned->resultRows[0]["valid_count"] > 0){
                  $set_user_logged_in = Promise\resolve((new DB_Api())->execute("UPDATE users_sessions SET authkeypassed = ?, totpmobilepassed = ? WHERE userid = ? AND sessid = ?", array(1,1, $userid, $sessionid)));
                  $set_user_logged_in->then(function($set_user_logged_in_returned) use(&$resolve){
                    $resolve(Promise\resolve($this->checklogin()));
                  })->otherwise(function(\Exception $e) use(&$resolve){
                    //TODO Implement correct status code
                    print_r($e);
                    $resolve(array("status" => "1", "message" => "An error occured."));
                  });
                }else{
                  //TODO Implement correct status code
                  $resolve(array("status" => "1", "message" => "Backup key not valid (anymore)."));
                }
              })->otherwise(function(\Exception $e) use(&$resolve){
                //TODO Implement correct status code
                print_r($e);
                //$resolve($this->logging_api->getErrormessage("checkBackupKeyValid", "001", $e));
                $resolve(array("status" => "1", "message" => "An error occured."));
              });
            }else{
              $resolve($checklogin_returned);
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            //TODO Implement correct status code
            print_r($e);
            //$resolve($this->logging_api->getErrormessage("checkBackupKeyValid","001",$e));
            $resolve(array("status" => "1", "message" => "An error occured."));
          });
        }else{
          $resolve(array("status" => "1", "message" => "An error occured."));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
