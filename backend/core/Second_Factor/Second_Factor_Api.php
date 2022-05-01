<?php
  namespace ChiaMgmt\Second_Factor;
  use OTPHP\TOTP;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Users\Users_Api;
  use ChiaMgmt\UserSettings\UserSettings_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Second_Factor_Api provides TOTP capabilities to this instance via 30 seconds random codes.
   * This class is only used by the web/app-client.
   * @see https://github.com/Spomky-Labs/otphp
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.1
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Second_Factor_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Users Class.
     * @var Users_Api
     */
    private $users_api;
    /**
     * Holds an instance to the UserSettings_Api Class.
     * @var UserSettings_Api
     */
    private $user_settings_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
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
        $this->users_api = new Users_Api();
        $this->user_settings_api = new UserSettings_Api();
        $this->logging_api = new Logging_Api($this, $server);
        $this->encryption_api = new Encryption_Api();
        $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
        $this->server = $server;
    }

    /**
     * Returns the current totp enabled state for a certain user.
     *
     * @param array $data   { "userID" : [int] }
     * @return array        Returns a status code array.
     */
    public function getTOTPEnabled(array $data): array
    {
        if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
            try{
                $sql = $this->db_api->execute("SELECT Count(*) AS count FROM users_settings WHERE userid = ? AND totp_proofen = 1 AND totp_enable = 1", array($data["userID"]));
                $totpEnabled = $sql->fetchAll(\PDO::FETCH_ASSOC);
    
                if($totpEnabled[0]["count"] == 1){
                    return array("status" => 0, "message" => "Totp is currently enabled.");
                }else{
                    return $this->logging_api->getErrormessage("001");
                }
            }catch(\Exception $e){
                return $this->logging_api->getErrormessage("002", $e);
            }
        }else{
            return $this->logging_api->getErrormessage("003");
        }   
    }

    /**
     * Enables TOTP via mobile app for specific user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param array  $data        { userID: [userid] } The user's ID for which the second factor should be enabled.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function enableTOTPmobile(array $data): array
    {
        if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
            $userID = $data["userID"];
            try{
                $userdata = $this->users_api->getUserData($userID);
                if($userdata["status"] == 0){
                    $username = $userdata["data"]["username"];
                    $app_domain = $this->ini["app_domain"];
                    
                    $totp = TOTP::create();
                    $secret = $totp->getSecret();
                    $secret_encrypted = $this->encryption_api->encryptString($totp->getSecret());

                    $totp->setIssuer("Chia Manager - " . $this->ini["app_domain"]);
                    $totp->setLabel("{$username}@{$app_domain}");
                    
                    $qrCodeUri = $totp->getQrCodeUri(
                        'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M',
                        '[DATA]'
                    );

                    $sql = $this->db_api->execute("UPDATE users_settings SET totp_enable = 1, totp_secret = ? WHERE userid = ?", array($secret_encrypted, $userID));

                    return array("status" => 0, "message" => "Successfully enabled TOTP mobile for user with ID: {$userID}", "data" => array("secret" => $secret, "qrCodeUri" => $qrCodeUri));
                }else{
                    return $userdata;
                }
            }catch(\Exception $e){
                return $this->logging_api->getErrormessage("001", $e);
            }
        }else{
            return $this->logging_api->getErrormessage("002");
        }
    }

    /**
     * Disables totp for a certain user.
     *
     * @param array $data   { "userID" : [int] }
     * @return array        Returns a status code array.
     */
    public function disableTOTPmobile(array $data): array
    {
        if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
            $totp_enabled = $this->getTOTPEnabled($data);
            if($totp_enabled["status"] == 0){
                try{
                    $sql = $this->db_api->execute("UPDATE users_settings SET totp_enable = 0, totp_secret = NULL, totp_proofen = 0 WHERE userid = ?", array($data["userID"]));
        
                    return array("status" => 0, "message" => "Successfully disabled TOTP mobile for user with ID: {$data["userID"]}");
                }catch(\Exception $e){
                    return $this->logging_api->getErrormessage("001", $e);
                }
            }else{
                return $totp_enabled;
            }
        }else{
            return $this->logging_api->getErrormessage("002");
        }
    }

    /**
     * Checks if a given TOTP Key matches the current searched.
     *
     * @param array $data   { "userID" : [int], "totpkey" : [string] }
     * @return array        Returns a status code array.
     */
    public function totpProof(array $data): array
    {
        if(array_key_exists("userID", $data) && is_numeric($data["userID"]) && array_key_exists("totpkey", $data) && is_numeric($data["totpkey"])){
            $statedkeyValid = $this->checkKeyValid($data);
            if($statedkeyValid["status"] == 0){
                try{
                    $sql = $this->db_api->execute("UPDATE users_settings SET totp_proofen = 1 WHERE userid = ?", array($data["userID"]));

                }catch(\Exception $e){
                    return $this->logging_api->getErrormessage("001", $e);
                }
            }
            return $statedkeyValid;
        }else{
            return $this->logging_api->getErrormessage("002");
        } 
    }

    /**
     * Veryfies if a given TOTP Key matches the current searched.
     *
     * @param array $data   { "userID" : [int], "totpkey" : [string] }
     * @return array        Returns a status code array.
     */
    public function checkKeyValid(array $data): array
    {
        if(array_key_exists("userID", $data) && is_numeric($data["userID"]) && array_key_exists("totpkey", $data) && is_numeric($data["totpkey"])){
            try{
                $sql = $this->db_api->execute("SELECT totp_secret FROM users_settings WHERE userid = ? AND totp_enable = 1", array($data["userID"]));
                $totpSecret = $sql->fetchAll(\PDO::FETCH_ASSOC);

                if(count($totpSecret) == 1){
                    $decrypted_totp_secret = $this->encryption_api->decryptString($totpSecret[0]["totp_secret"]);
                    $totp = TOTP::create($decrypted_totp_secret);

                    if($totp->verify($data["totpkey"])){
                        return array("status" => 0, "message" => "Entered key matches current TOTP key."); 
                    }else{
                        return $this->logging_api->getErrormessage("001");
                    }
                }else{
                    return $this->logging_api->getErrormessage("002");
                } 
            }catch(\Exception $e){
                return $this->logging_api->getErrormessage("003", $e);
            }
        }else{
            return $this->logging_api->getErrormessage("004");
        } 
    }
  }