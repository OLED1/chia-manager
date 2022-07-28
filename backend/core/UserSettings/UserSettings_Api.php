<?php
  namespace ChiaMgmt\UserSettings;
  use React\Promise;
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
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->exchangerates_api = new Exchangerates_Api();
      $this->logging_api = new Logging_Api($this, $server);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
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
    public function setGuiMode(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("gui_mode", $data) && array_key_exists("userid", $loginData)){
          if($data["gui_mode"] >= 1 && $data["gui_mode"] <= 2){
            $gui_mode = Promise\resolve((new DB_Api())->execute("SELECT gui_mode FROM users_settings WHERE userid = ?", array($loginData["userid"])));
            $gui_mode->then(function($gui_mode_returned) use(&$resolve, $data, $loginData){
              if(count($gui_mode_returned->resultRows) == 0){
                $set_new_mode = Promise\resolve((new DB_Api())->execute("INSERT INTO users_settings (id, userid, gui_mode) VALUES (NULL, ?, ?)", array($loginData["userid"], $data["gui_mode"])));
              }else{
                $set_new_mode = Promise\resolve((new DB_Api())->execute("UPDATE users_settings SET gui_mode = ? WHERE userid = ?", array($data["gui_mode"], $loginData["userid"])));
              }

              $set_new_mode->then(function($set_new_mode_returned) use(&$resolve, $data){
                $resolve(array("status" => 0, "message" => "Successfully set gui mode to {$data["gui_mode"]}.", "data" => $data["gui_mode"]));
              })->othwerwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("setGuiMode", "005", $e));
              });
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("setGuiMode", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("setGuiMode", "002", "Gui Mode {$data["gui_mode"]} not supported."));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("setGuiMode", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function getGuiMode(int $userid): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userid){
        if($userid > 0 && array_key_exists("user_id", $_COOKIE) && $_COOKIE["user_id"] == $userid){
          $gui_mode = Promise\resolve((new DB_Api())->execute("SELECT gui_mode FROM users_settings WHERE userid = ?", array($userid)));
          $gui_mode->then(function($gui_mode_returned) use(&$resolve, $userid){
            if(count($gui_mode_returned->resultRows) == 0){
              $set_new_mode = Promise\resolve($this->setGuiMode(array("gui_mode" => 0), array("userid" => $userid)));
              $set_new_mode->then(function($set_new_mode_returned) use(&$resolve){
                if($set_new_mode_returned["status"] == 0){
                  $resolve(array("status" => 0, "message" => "Successfully loaded gui mode for user.", "data" => array("gui_mode" => 1)));
                }else{
                  $resolve($guiModeStatus);
                }
              });
            }else{
              $resolve(array("status" => 0, "message" => "Successfully loaded gui mode for user.", "data" => $gui_mode_returned->resultRows[0]));
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getGuiMode", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("getGuiMode", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the user's related default currency.
     * Function made for: Web(App)client
     * @todo Make this function websocket compatible.
     * @param  int  $userid       The user's id for which the the default currency should be returned for."
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[The default user currency information.]} }
     */
    public function getUserDefaultCurrency(int $userid): object
    {
      return $this->exchangerates_api->getUserDefaultCurrency($userid);
    }

    /**
     * Sets the user's related default currency.
     * Function made for: Web(App)client
     * @param  array    $data       { "currency_code" : "[Currency 3-4 digits code, e.g. usd]" }
     * @param  array    $loginData  { "userid" : [userid] }
     * @return array                { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Newly set default currency]}}
     */
    public function setUserDefaultCurrency(array $data, array $loginData = NULL): object
    {
      return $this->exchangerates_api->setUserDefaultCurrency($data, $loginData);
    }
  }
?>
