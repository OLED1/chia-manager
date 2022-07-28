<?php
  namespace ChiaMgmt\Second_Factor;

  use React\Promise;
  use React\Promise\Deferred;

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
    public function getTOTPEnabled(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
                $totp_enabled_promise = Promise\resolve((new DB_Api)->execute("SELECT Count(*) AS count FROM users_settings WHERE userid = ? AND totp_proofen = 1 AND totp_enable = 1", array($data["userID"])));
                $totp_enabled_promise->then(function($totpEnabled) use(&$resolve){
                    if($totpEnabled->resultRows[0]["count"] == 1){
                        $resolve(array("status" => 0, "message" => "Totp is currently enabled."));
                    }else{
                        $resolve($this->logging_api->getErrormessage("getTOTPEnabled", "001"));
                    }
                })->otherwise(function(\Exception $e) use(&$resolve){
                    $resolve($this->logging_api->getErrormessage("getTOTPEnabled", "002", $e));
                });
            }else{
                $resolve($this->logging_api->getErrormessage("getTOTPEnabled", "003"));
            }    
        };
    
        $canceller = function () {
            throw new Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller); 
    }

    /**
     * Enables TOTP via mobile app for specific user.
     * Function made for: Web(App)client
     * @throws Exception $e       Throws an exception on db errors.
     * @param array  $data        { userID: [userid] } The user's ID for which the second factor should be enabled.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function enableTOTPmobile(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
                $userID = $data["userID"];
                $userdata = Promise\resolve($this->users_api->getUserData($userID));
                $userdata->then(function($userdata_returned) use(&$resolve, $userID){
                    if($userdata_returned["status"] == 0){
                        $username = $userdata_returned["data"]["username"];
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

                        $enable_totp_mobile = Promise\resolve((new DB_Api())->execute("UPDATE users_settings SET totp_enable = 1, totp_secret = ? WHERE userid = ?", array($secret_encrypted, $userID)));
                        $enable_totp_mobile->then(function($enable_totp_mobile_returned) use(&$resolve, $userID, $secret, $qrCodeUri){
                            $resolve(array("status" => 0, "message" => "Successfully enabled TOTP mobile for user with ID: {$userID}", "data" => array("secret" => $secret, "qrCodeUri" => $qrCodeUri)));
                        })->otherwise(function(\Exception $e) use(&$resolve){
                            $resolve($this->logging_api->getErrormessage("enableTOTPmobile", "001", $e));
                        });
                    }else{
                        $resolve($userdata_returned);
                    }
                });
            }else{
                $resolve($this->logging_api->getErrormessage("enableTOTPmobile", "002"));
            }
        };
    
        $canceller = function () {
            throw new Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller); 
    }

    /**
     * Disables totp for a certain user.
     *
     * @param array $data   { "userID" : [int] }
     * @return array        Returns a status code array.
     */
    public function disableTOTPmobile(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            if(array_key_exists("userID", $data) && is_numeric($data["userID"])){
                $totp_enabled = Promise\resolve($this->getTOTPEnabled($data));
                $totp_enabled->then(function($totp_enabled_returned) use(&$resolve, $data){
                    if($totp_enabled_returned["status"] == 0){
                        $disable_totp = Promise\resolve((new DB_Api)->execute("UPDATE users_settings SET totp_enable = 0, totp_secret = NULL, totp_proofen = 0 WHERE userid = ?", array($data["userID"])));
                        $disable_totp->then(function($disable_totp_returned) use(&$resolve, $data){
                            $resolve(array("status" => 0, "message" => "Successfully disabled TOTP mobile for user with ID: {$data["userID"]}"));
                        })->otherwise(function(\Exception $e) use(&$resolve){
                            $resolve($this->logging_api->getErrormessage("disableTOTPmobile", "001", $e));
                        });
                    }else{
                        $resolve($totp_enabled_returned);
                    }
                });
            }else{
                $resolve($this->logging_api->getErrormessage("disableTOTPmobile", "002"));
            }
        };

        $canceller = function () {
            throw new Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Checks if a given TOTP Key matches the current searched.
     *
     * @param array $data   { "userID" : [int], "totpkey" : [string] }
     * @return array        Returns a status code array.
     */
    public function totpProof(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            if(array_key_exists("userID", $data) && is_numeric($data["userID"]) && array_key_exists("totpkey", $data) && is_numeric($data["totpkey"])){
                $statedkeyValid = Promise\resolve($this->checkKeyValid($data));
                $statedkeyValid->then(function($statedkeyValid_returned) use(&$resolve, $data){
                    if($statedkeyValid_returned["status"] == 0){
                        $update_totp_proof = Promise\resolve((new DB_Api())->execute("UPDATE users_settings SET totp_proofen = 1 WHERE userid = ?", array($data["userID"])));
                        $update_totp_proof->otherwise(function(\Exception $e) use(&$resolve){
                            $resolve($this->logging_api->getErrormessage("totpProof", "001", $e));
                            return;
                        });
                    }
                    $resolve($statedkeyValid_returned);
                });
            }else{
                $resolve($this->logging_api->getErrormessage("totpProof", "002"));
            }
        };

        $canceller = function () {
            throw new Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Veryfies if a given TOTP Key matches the current searched.
     *
     * @param array $data   { "userID" : [int], "totpkey" : [string] }
     * @return array        Returns a status code array.
     */
    public function checkKeyValid(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            if(array_key_exists("userID", $data) && is_numeric($data["userID"]) && array_key_exists("totpkey", $data) && is_numeric($data["totpkey"])){
                $totp_secret = Promise\resolve((new DB_Api())->execute("SELECT totp_secret FROM users_settings WHERE userid = ? AND totp_enable = 1", array($data["userID"])));
                $totp_secret->then(function($totp_secret_returned) use(&$resolve, $data){
                    $totp_secret_returned = $totp_secret_returned->resultRows;

                    if(count($totp_secret_returned) == 1){
                        $totp = TOTP::create($this->encryption_api->decryptString($totp_secret_returned[0]["totp_secret"]));

                        if($totp->verify($data["totpkey"])){
                            $resolve(array("status" => 0, "message" => "Entered key matches current TOTP key."));
                        }else{
                            $resolve($this->logging_api->getErrormessage("checkKeyValid", "001"));
                        }
                    }else{
                        $resolve($this->logging_api->getErrormessage("checkKeyValid", "002"));
                    } 
                });
            }else{
                $resolve($this->logging_api->getErrormessage("checkKeyValid", "004"));
            } 
        };

        $canceller = function () {
            throw new Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }
  }