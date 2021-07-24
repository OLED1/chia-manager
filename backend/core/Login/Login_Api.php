<?php
  namespace ChiaMgmt\Login;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\System\System_Api;

  class Login_Api{
    private $dbcon, $config, $logging, $mailing_api, $system_api;

    public function __construct(){
      $this->dbcon = new DB_Api();
      $this->logging = new Logging_Api($this);
      $this->mailing_api = new Mailing_Api();
      $this->system_api = new System_Api();
      $this->config = parse_ini_file(__DIR__.'/../../config/config.ini');
    }

    /**
     * The login method so a user is able to login
     * @param  string $username The username (or socalled userid)
     * @param  string $password The user's password
     * @return array            Returns a statuscode array
     */
    public function login(string $username, string $password, string $stayloggedin){
      $userdata = $this->getCurrentUserInfos($username);

      if($userdata["status"] == 0){
        $salted_hash=hash('sha256',$password.$userdata["data"]["salt"].$this->config['serversalt']);
      }else{
        return $userdata;
      }

      if(isset($userdata["data"]["password"]) && $userdata["data"]["password"] == $salted_hash){
        $session = $this->setSession($userdata["data"]["id"]);

        $validuntil = NULL;
        if(!$stayloggedin){
          $validuntil = new \DateTime();
          $validuntil->modify("+30 minutes");
          $validuntil = $validuntil->format("Y-m-d H:i:s");
        }

        $this->logging->logtofile(0, 0, "User with ID " . $userdata["data"]["id"] . " logged in from " . $_SERVER['REMOTE_ADDR'] . ".;");
        if($session["status"] == 0){
          try{
            $security = $this->system_api->getSpecificSystemSetting("security");
            $authkeypassed = 1;

            if($security["status"] == 0 && array_key_exists("security", $security["data"]) &&
                $security["data"]["security"]["TOTP"]["value"] == 1){

                $authkeypassed = 0;
                $this->generateAndsendAuthKey();
            }

            $sql = $this->dbcon->execute("Insert INTO users_sessions (id, userid, sessid, authkeypassed, deviceinfo, validuntil) VALUES (NULL, ?, ?, ?, ?, ?)",
                                          array($session["data"]["userid"], $session["data"]["sessid"], $authkeypassed, $_SERVER['HTTP_USER_AGENT'], $validuntil));

            return $this->logging->getErrormessage("001");
          }catch(Exception $e){
            return $this->logging->getErrormessage("002", $e);
          }
        }
      }else{
        return $this->logging->getErrormessage("003","User with ID " . $userdata["data"]["id"] . " tried to log in from " . $_SERVER['REMOTE_ADDR'] . ".");
      }
    }

    public function checkAuthKey(string $authkey){
      if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
        $userid = $_COOKIE['user_id'];
        $sessionid = $_COOKIE['PHPSESSID'];
        $currentdate = new \DateTime();

        try{
          $sql = $this->dbcon->execute("SELECT validuntil FROM users_authkeys WHERE authkey = ? AND validuntil >= ? LIMIT 1",
                  array($authkey, $currentdate->format("Y-m-d H:i:s")));

          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];

          if(array_key_exists("validuntil", $sqreturn)){
            $sql = $this->dbcon->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid));
            $sql = $this->dbcon->execute("UPDATE users_sessions SET authkeypassed = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid));

            return array("status" => 0, "message" => "Successfully logged in.");
          }else{
            return array("status" => 1, "message" => "Authkey not found or not valid (anymore).");
          }
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "You are not authenticated.");
      }
    }

    public function generateAndsendAuthKey(){
      $loginstatus = $this->checklogin()["status"];

      if($loginstatus == 0){
        return array("status" => 0, "message" => "You are currently logged in. No need to send authkey.");
      }else if($loginstatus == "001005002"){
        if(array_key_exists("user_id", $_COOKIE)){
          $userid = $_COOKIE['user_id'];
          $authkey = bin2hex(random_bytes(25));

          try{
            $sql = $this->dbcon->execute("SELECT email FROM users WHERE id = ?", array($userid));
            $email = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["email"];

            $sql = $this->dbcon->execute("UPDATE users_authkeys SET valid = 0 WHERE userid = ?", array($userid));

            $keyvaliduntil = new \DateTime();
            $keyvaliduntil->modify("+15 minutes");

            $sql = $this->dbcon->execute("Insert INTO users_authkeys (id, userid, authkey, validuntil) VALUES (NULL, ?, ?, ?)",
                                          array($userid, $authkey, $keyvaliduntil->format("Y-m-d H:i:s")));

            $message = "<h1>Complete your login</h1><br>To complete your login enter this authkey when prompted:<br>$authkey<br><br>This key will be valid until {$keyvaliduntil->format("Y-m-d H:i:s")}.";

            $mailingstatus = $this->mailing_api->sendMail(array($email), "Chia Management Loginkey" , $message);

            return array("status" => 0, "message" => "Successfully (re)sent authmail.");
          }catch(Exception $e){
            print_r($e);
            return array("status" => 1, "message" => "An error occured.");
          }
        }else{
          return array("status" => 1, "Your are not authenticated.");
        }
      }else{
        return array("status" => 1, "An error occured, statuscode not known.");
      }
    }

    public function invalidateLogin(){
      if(array_key_exists('user_id', $_COOKIE) && array_key_exists('PHPSESSID', $_COOKIE)){
        $userid = $_COOKIE['user_id'];
        $sessionid = $_COOKIE['PHPSESSID'];

        try{
          $sql = $this->dbcon->execute("UPDATE users_sessions SET invalidated = 1 WHERE userid = ? AND sessid = ?", array($userid, $sessionid));
          return array("status" => 0, "message" => "Successfully invalidated (pending) login.");
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }

      }else{
        return array("status" => 1, "message" => "Cannot invalidate. You are not authenticated.");
      }
    }

    /**
     * Gets some user infos from a particular user ID (not db ID)
     * @param  string $username The username
     * @return array           Returns a status code array with the needed data
     */
    public function getCurrentUserInfos(string $username){
      try{
        $sql = $this->dbcon->execute("SELECT id,name,lastname,email,password,salt FROM users WHERE username = ?",
                                      array($username));

        if($sql->rowCount() == 0){
          return $this->logging->getErrormessage("001","An unknown user tried to log in from " . $_SERVER['REMOTE_ADDR'] . ".");
        }

        $data = $sql->fetch(\PDO::FETCH_ASSOC);

        return array("status" => 0,"message" => "Data successfully loaded.","data" => $data);
      }catch(Exception $e){
        return array("status" => 0,"message" => "Data successfully loaded.","data" => $data);
      }
    }

    /**
     * Sets the session after a user logged in successfully
     * @param int $id The users id which logged in
     * @return array  Return a status code array
     */
    public function setSession(int $id){
      try{
        session_destroy();
        $sessionID = session_create_id();
        $currentCookieParams = session_get_cookie_params();

        setcookie(
          'PHPSESSID',//name
          $sessionID,//value
          0,//expires at end of session
          $currentCookieParams['path'],//path
          $currentCookieParams['domain'],//domain
          true, //secure
          true //httponly
          );

          setcookie(
          'user_id',//name
          $id,//value
          0,//expires at end of session
          $currentCookieParams['path'],//path
          $currentCookieParams['domain'],//domain
          true, //secure
          true //httponly
          );

          return array("status" => 0, "message" => "Session successfully set!", "data" => array("userid" => $id, "sessid" => $sessionID));
        }catch(Exception $e){
          return $this->logging->getErrormessage("001",$e);
        }
      }

      /**
       * Logs a user out and removes his session ID from db
       * @param  int $userid The user which should be logged out
       * @return array       Returns a status code array
       */
      public function logout(int $userid){
        try{
          $sql = $this->dbcon->execute("UPDATE users SET loginDate = NULL, sessionString = NULL, ipaddr = NULL WHERE id = ?",
          array($userid));

          session_destroy();
          return $this->logging->getErrormessage("001","User with ID " . $userid . " logged out.");
        }catch(Exception $e){
          return $this->logging->getErrormessage("002",$e);
        }
      }

      public function checklogin(string $sessionid = NULL, int $userid = NULL){
        if((isset($_COOKIE['user_id']) || !is_null($userid)) &&
          (isset($_COOKIE['PHPSESSID']) || !is_null($sessionid))
        ){
          if(is_null($userid)) $userid = $_COOKIE['user_id'];
          if(is_null($sessionid)) $sessionid = $_COOKIE['PHPSESSID'];

          try{
            $sql = $this->dbcon->execute("SELECT authkeypassed, validuntil FROM users_sessions WHERE userid = ? AND sessid = ? AND invalidated = 0",
            array($userid, $sessionid));

            $returneddata = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if(Count($returneddata) > 0){
              $returneddata = $returneddata[0];

              if($returneddata["authkeypassed"] == 1){
                if(is_null($returneddata["validuntil"])){
                  return array("status" => "0", "message" => "You are logged in.");
                }else{
                  $validuntil = new  \DateTime($returneddata["validuntil"]);
                  $currentdate = new \DateTime();

                  if($currentdate <= $validuntil){
                    $currentdate->modify("+30 minutes");
                    $sql = $this->dbcon->execute("UPDATE users_sessions SET validuntil = ? WHERE userid = ? AND sessid = ?",
                                                  array($currentdate->format("Y-m-d H:i:s"), $userid, $sessionid));

                    return array("status" => "0", "message" => "You are logged in.");
                  }else{
                    return array("status" => "1", "message" => "You are not logged in (anymore).");
                  }
                }
              }else{
                return $this->logging->getErrormessage("002");
              }
            }else{
                return $this->logging->getErrormessage("005");
            }
          }catch(Exception $e){
            return $this->logging->getErrormessage("003",$e);
          }
        }else{
          return $this->logging->getErrormessage("004","", false);
        }
      }
  }
?>
