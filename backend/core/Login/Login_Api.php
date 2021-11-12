<?php
  namespace ChiaMgmt\Login;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Second_Factor\Second_Factor_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Users\Users_Api;

  /**
   * The Login_Api is one of two restfull enabled api classes and handles all webapp login based actions.
   * This class is only used by the webclient to fullfill login/logout and session validation actions.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Login_Api{
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
     * Holds an instance to the Mailing Class.
     * @var Mailing_Api
     */
    private $mailing_api;
    /**
     * Holds an instance to the System Class.
     * @var System_Api
     */
    private $system_api;
    /**
     * Holds an instance to the Second Factor Class.
     * @var Second_Factor_Api
     */
    private $second_factor_api;
    /**
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;
    /**
     * Holds an instance to the Users Class.
     * @var Users_Api
     */
    private $users_api;
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
      $this->system_api = new System_Api();
      $this->second_factor_api = new Second_Factor_Api();
      $this->encryption_api = new Encryption_Api();
      $this->users_api = new Users_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
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
    public function login(string $username, string $password, bool $stayloggedin){
      $userdata = $this->getCurrentUserInfos($username);

      if($userdata["status"] == 0){
        $salted_hash=hash('sha256',$password.$userdata["data"]["salt"].$this->ini['serversalt']);
      }else{
        return $userdata;
      }

      if(isset($userdata["data"]["password"]) && $userdata["data"]["password"] == $salted_hash){
        $session = $this->setSession($userdata["data"]["id"]);

        $this->logging_api->logtofile(0, 0, "User with ID " . $userdata["data"]["id"] . " logged in from " . $_SERVER['REMOTE_ADDR'] . ".;");
        if($session["status"] == 0){
          try{
            $security = $this->system_api->getSpecificSystemSetting("security");
            $mailing = $this->system_api->getSpecificSystemSetting("mailing");
            $authkeypassed = 1;
            $totpmobilepassed = 1;
            $sendauthkey = false;
            $checktotpmobile = false;

            if($security["status"] == 0 && array_key_exists("security", $security["data"]) &&
                $security["data"]["security"]["TOTP"]["value"] == 1 && $mailing["status"] == 0 &&
                array_key_exists("mailing", $mailing["data"]) && $mailing["data"]["mailing"]["confirmed"] == 1
              ){
                $authkeypassed = 0;
                $sendauthkey = true;
            }

            if($this->second_factor_api->getTOTPEnabled(["userID" => $session["data"]["userid"]])["status"] == 0){
              $totpmobilepassed = 0;
              $checktotpmobile = true;
            }

            $sql = $this->db_api->execute("Insert INTO users_sessions (id, userid, sessid, authkeypassed, totpmobilepassed, deviceinfo, validuntil) VALUES (NULL, ?, ?, ?, ?, ?, " . ($stayloggedin ? "NULL" : "DATE_ADD(now(), INTERVAL 30 MINUTE)" ) . ")",
                                          array($session["data"]["userid"], $session["data"]["sessid"], $authkeypassed, $totpmobilepassed, $_SERVER['HTTP_USER_AGENT']));
            
            if($sendauthkey && !$checktotpmobile){
              $this->generateAndsendAuthKey($session["data"]["userid"], $session["data"]["sessid"]);
              return $this->logging_api->getErrormessage("001");
            }else if(!$sendauthkey && $checktotpmobile){
              return $this->logging_api->getErrormessage("002");
            }else if($sendauthkey && $checktotpmobile){
              $this->generateAndsendAuthKey($session["data"]["userid"], $session["data"]["sessid"]);
              return $this->logging_api->getErrormessage("003");
            }

            return array("status" => "0", "message" => "Logged in.");
          }catch(Exception $e){
            return $this->logging_api->getErrormessage("004", $e);
          }
        }
      }else{
        return $this->logging_api->getErrormessage("005","User with ID " . $userdata["data"]["id"] . " tried to log in from " . $_SERVER['REMOTE_ADDR'] . ".");
      }
    }

    /**
     * Checks if the authkey is valid when second factor via e-mail is enabled.
     * Furthermore it will be checked if the user already has valid cookies set. If the session does not exist the user will not be logged in.
     * Function made for: WebGUI/App
     * @throws Exception $e    Throws an exception on db errors.
     * @param  string $authkey The stated outkey.
     * @return array           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkAuthKey(string $authkey){
      if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
        $userid = $_COOKIE['user_id'];
        $sessionid = $_COOKIE['PHPSESSID'];
        $currentdate = new \DateTime();

        try{
          $sql = $this->db_api->execute("SELECT validuntil FROM users_authkeys WHERE authkey = ? AND validuntil >= ? LIMIT 1",
                  array($authkey, $currentdate->format("Y-m-d H:i:s")));

          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];

          if(array_key_exists("validuntil", $sqreturn)){
            $sql = $this->db_api->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid));
            $sql = $this->db_api->execute("UPDATE users_sessions SET authkeypassed = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid));

            //return array("status" => 0, "message" => "Successfully checked authkey.");
            return $this->checklogin($sessid, $userid);
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Checks if the TOTP mobile key is valid when second factor via TOTP mobile is enabled.
     * Furthermore it will be checked if the user already has valid cookies set. If the session does not exist the user will not be logged in.
     * Function made for: WebGUI/App
     * @throws Exception $e    Throws an exception on db errors.
     * @param  string $authkey The stated outkey.
     * @return array           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checkTOTPMobilePassed(string $key){
      if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
        $userid = $_COOKIE['user_id'];
        $sessionid = $_COOKIE['PHPSESSID'];
        $currentdate = new \DateTime();

        try{
          $totpkeyvalid = $this->second_factor_api->totpProof(["userID" => $userid, "totpkey" => $key]);

          if($totpkeyvalid["status"] == 0){
            $sql = $this->db_api->execute("UPDATE users_sessions SET totpmobilepassed = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid));
            return $this->checklogin($sessid, $userid);
          }else{
            return $totpkeyvalid;
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Generates a uniqe outkey which will be directly send to the user which currently wants to login. Requires a valid set-up e-mail address.
     * Function made for: WebGUI/App
     * @throws Exception $e          Throws an exception on db errors.
     * @param  int $userid      The user's id who wants to login.
     * @param  string $sessid   The user's current session id.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function generateAndsendAuthKey(int $userid = NULL, string $sessid = NULL){
      $loginstatus = $this->checklogin($sessid, $userid)["status"];

      if($loginstatus == 0){
        return array("status" => 0, "message" => "You are currently logged in. No need to send authkey.");

      }else if($loginstatus == "007009002"){

        if(array_key_exists("user_id", $_COOKIE) || !is_null($userid)){
          if(array_key_exists('user_id', $_COOKIE)) $userid = $_COOKIE['user_id'];
          $authkey = bin2hex(random_bytes(25));

          try{
            $sql = $this->db_api->execute("SELECT email FROM users WHERE id = ?", array($userid));
            $email = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["email"];

            $sql = $this->db_api->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid));

            $keyvaliduntil = new \DateTime();
            $keyvaliduntil->modify("+15 minutes");

            $sql = $this->db_api->execute("Insert INTO users_authkeys (id, userid, authkey, validuntil) VALUES (NULL, ?, ?, ?)",
                                          array($userid, $authkey, $keyvaliduntil->format("Y-m-d H:i:s")));

            $message = "<h1>Complete your login</h1><br>To complete your login enter this authkey when prompted:<br>$authkey<br><br>This key will be valid until {$keyvaliduntil->format("Y-m-d H:i:s")}.";

            $mailingstatus = $this->mailing_api->sendMail(array($email), "Chia Management Loginkey" , $message);

            return array("status" => 0, "message" => "Successfully (re)sent authmail.");
          }catch(Exception $e){
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
     * Invalidates a certain session of a user. Will be called on logout action or when the user is in login screen and cancels authkey check with "go back".
     * Function made for: WebGUI/App
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function invalidateLogin(){
      if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
        $userid = $_COOKIE['user_id'];
        $sessionid = $_COOKIE['PHPSESSID'];

        try{
          $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid));
          setcookie('user_id', null, -1, '/');
          setcookie('PHPSESSID', null, -1, '/');

          return array("status" => 0, "message" => "Successfully invalidated (pending) login.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }

      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Gets some user infos from a particular user ID (not db ID).
     * Function made for: WebGUI/App
     * @param  string $username The username
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" => {[Found db stored userdata]}}
     */
    public function getCurrentUserInfos(string $username){
      try{
        $sql = $this->db_api->execute("SELECT id,name,lastname,email,password,salt FROM users WHERE username = ? AND enabled = 1",
                                      array($username));

        if($sql->rowCount() == 0){
          return $this->logging_api->getErrormessage("001","An unknown user tried to log in from " . $_SERVER['REMOTE_ADDR'] . ".");
        }

        $data = $sql->fetch(\PDO::FETCH_ASSOC);

        return array("status" => 0,"message" => "Data successfully loaded.","data" => $data);
      }catch(Exception $e){
        //return array("status" => 0,"message" => "Data successfully loaded.","data" => $data);
        return $this->logging_api->getErrormessage("002", $e);
      }
    }

    /**
     * Sets the session after a user logged in successfully.
     * Function made for: WebGUI/App
     * @param int $id The users id which logged in
     * @return array  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : { "userid" : "[userid]", "sessid" : [session ID]}}
     */
    public function setSession(int $userid){
      try{
        session_destroy();
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



        return array("status" => 0, "message" => "Session successfully set!", "data" => array("userid" => $userid, "sessid" => $sessionID));
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001",$e);
      }
    }

    /**
     * Logs a user out and removes his session ID from db.
     * Function made for: WebGUI/App
     * @param  int $userid The user which should be logged out
     * @return array       {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function logout(int $userid){
      try{
        $sql = $this->db_api->execute("UPDATE users SET loginDate = NULL, sessionString = NULL, ipaddr = NULL WHERE id = ?",
        array($userid));

        session_destroy();
        return $this->logging_api->getErrormessage("001","User with ID " . $userid . " logged out.");
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("002",$e);
      }
    }

    /**
     * Checks if a user session is currently valid.
     * Function made for: WebGUI/App
     * @param  string $sessionid  Users's sessionid from which the loginstatus should be checked.
     * @param  int $userid        Users's id from which the loginstatus should be checked.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function checklogin(string $sessionid = NULL, int $userid = NULL){
      if((isset($_COOKIE['user_id']) || !is_null($userid)) &&
        (isset($_COOKIE['PHPSESSID']) || !is_null($sessionid))
      ){
        if(is_null($userid)) $userid = $_COOKIE['user_id'];
        if(is_null($sessionid)) $sessionid = $_COOKIE['PHPSESSID'];

        $this->invalidateAllNotLoggedin();

        try{
          $sql = $this->db_api->execute("SELECT authkeypassed, validuntil, totpmobilepassed FROM users_sessions WHERE userid = ? AND sessid = ? AND invalidated = 0",
          array($userid, $sessionid));

          $returneddata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(Count($returneddata) > 0){
            $returneddata = $returneddata[0];


            if($returneddata["authkeypassed"] == 1 && $returneddata["totpmobilepassed"] == 1){
              if(is_null($returneddata["validuntil"])){
                return array("status" => "0", "message" => "User with id {$userid} is logged in.");
              }else{
                $validuntil = new  \DateTime($returneddata["validuntil"]);
                $currentdate = new \DateTime();

                if($currentdate <= $validuntil){
                  $currentdate->modify("+30 minutes");
                  $sql = $this->db_api->execute("UPDATE users_sessions SET validuntil = ? WHERE userid = ? AND sessid = ?",
                                                array($currentdate->format("Y-m-d H:i:s"), $userid, $sessionid));

                  return array("status" => "0", "message" => "User with id {$userid} is logged in.");
                }else{
                  return $this->logging_api->getErrormessage("001");
                }
              }
            }else{
              if($returneddata["authkeypassed"] == 0) return $this->logging_api->getErrormessage("002");
              if($returneddata["totpmobilepassed"] == 0) return $this->logging_api->getErrormessage("003");
              return $this->logging_api->getErrormessage("004");
            }
          }else{
              return $this->logging_api->getErrormessage("005");
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("006",$e);
        }
      }else{
        return $this->logging_api->getErrormessage("007","", false);
      }
    }

    /**
     * Invalidates all logins where the login process where not finished or the session interval (max 30 days) exceded.
     * Function made for: Api/Backend
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function invalidateAllNotLoggedin(){
      try{
        $sql = $this->db_api->execute("UPDATE users_sessions SET invalidated =
                                        CASE
                                          WHEN (invalidated = 0 AND validuntil IS NOT NULL AND validuntil <= DATE_ADD(NOW(), INTERVAL 5 MINUTE)) OR
                                                (authkeypassed = 0 AND NOW() >= DATE_ADD(logindate, INTERVAL 1 HOUR) AND invalidated = 0) OR
                                                (NOW() >= DATE_ADD(logindate, INTERVAL 30 DAY))
                                          THEN 1
                                          ELSE invalidated
                                        END", array());

        return array("status" => 0, "message" => "Successfully invalidated not logged in session.");
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001",$e);
      }
    }
  }
?>
