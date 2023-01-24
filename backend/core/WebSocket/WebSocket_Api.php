<?php
  namespace ChiaMgmt\WebSocket;
  use React\Promise;
  use React\ChildProcess;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\WebSocketClient\WebSocketClient_Api;

  /**
   * The WebSocket_Api class handles the start, stop, restart operations of the websocket server.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class WebSocket_Api{
    /**
     * Holds an instance to the Websocket Client Class.
     * @var WebSocketClient_Api
     */
    private $wsclient;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->wsclient = new WebSocketClient_Api();
      $this->logging_api = new Logging_Api($this, $server);
      $this->server = $server;
    }

    /**
     * [testConnection description]
     * Function made for: Web(App)client
     * @param  string $host   Override the websocket host.
     * @throws Exception $e  Throws an exception websocket errors.
     * @return array         {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function testConnection(): object
    {
      return $this->wsclient->testConnection();
    }

    /**
     * Updates the user current viewing site to the websockets internal array.
     * Function made for: Web(App)client
     * @todo Make this function websocket compatible.
     * @return int $siteID  The siteid which the user is currenty viewing.
     * @return array        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function updateSiteID(int $siteID): array
    {
      if($siteID > 0){
        $con_test = $this->testConnection();
        if($con_test["status"] == 0){
          return $this->wsclient->sendToWSS("updateFrontendViewingSite", array("userID" => $_COOKIE["user_id"], "siteID" => $siteID));
        }else{
          return $con_test;
        }
      }else{
        return $this->logging_api->getErrormessage("001");
      }
    }

    /**
     * Sends a command to the websocket server.
     * @param  string $command   The command which should be sent to the websocket server.
     * @param  array  $data      The data which should be sent to the websocket server.
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function sendToWSS(string $command, array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($command, $data){
        $test_wss_conn = Promise\resolve($this->wsclient->testConnection());
        $test_wss_conn->then(function($test_wss_conn_returned) use(&$resolve, $command, $data){
          if($test_wss_conn_returned["status"] == 0){
            $resolve($this->wsclient->sendToWSS($command, $data));
          }else{
            $resolve($test_wss_conn_returned);
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Starts the websocket server if not running.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function startWSS(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $test_wss_conn = Promise\resolve($this->wsclient->testConnection());
        $test_wss_conn->then(function($test_wss_conn_returned) use(&$resolve){
          if($test_wss_conn_returned["status"] == "016001001" || $test_wss_conn_returned["status"] == "016001002"){
            $stop_wss = new ChildProcess\Process("php " . __DIR__ . "/../WebSocketServer/websocket.php >/dev/null 2>/dev/null &");
            $stop_wss->start();
            $stop_wss->on('exit', function($exitCode, $termSignal) use(&$resolve){
              if($exitCode == 0){
                $resolve(array("status" => 0, "message" => "Websocket server started."));
              }else{
                $resolve($this->logging_api->getErrormessage("startWSS", "001", "Process exited with code {$exitCode}."));
              }
            });
          }else{
            $resolve($this->logging_api->getErrormessage("startWSS", "002"));
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Stops the websocket server if not stopped.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function stopWSS(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $wssstatus = Promise\resolve($this->wsclient->testConnection());
        $wssstatus->then(function($wssstatus_returned) use(&$resolve){
          if($wssstatus_returned["status"] == 0){
            $stop_wss = new ChildProcess\Process("kill -9 {$wssstatus_returned["data"]}");
            $stop_wss->start();
            $stop_wss->on('exit', function($exitCode, $termSignal) use(&$resolve){
              $wssstatus = Promise\resolve($this->wsclient->testConnection());
              $wssstatus->then(function($wssstatus_returned) use(&$resolve){
                if($wssstatus_returned["status"] == "016001001" || $wssstatus_returned["status"] == "016001002"){
                  $resolve(array("status" => 0, "message" => "Websocket server stopped."));
                }else{
                  $resolve($this->logging_api->getErrormessage("stopWSS", "001", "Process exited with code {$exitCode}."));
                }
              });
            });
          }else{
            $resolve($this->logging_api->getErrormessage("stopWSS", "002"));
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Restarts the websocket server if running.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
     */
    public function restartWSS(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $stop_wss = Promise\resolve($this->stopWSS());
        $stop_wss->then(function($stop_wss_returned) use(&$resolve){
          if($stop_wss_returned["status"] == 0){
            $resolve(Promise\resolve($this->startWSS()));
          }else{
            $resolve($stop_wss_returned);
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
