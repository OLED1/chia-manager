<?php
    namespace ChiaMgmt\WebSocketClient;
    use React\Promise;
    use ChiaMgmt\Logging\Logging_Api;
    use React\Promise\RejectedPromise;
    use function Amp\Websocket\Client\connect;

    require __DIR__ . '/../../../vendor/autoload.php';

    /**
     * The WebSocketClient_Api class handles the webgui update tasks.
     * @version 0.1.1
     * @author OLED1 - Oliver Edtmair
     * @since 0.1.0
     * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
     */
    class WebSocketClient_Api{
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
       * Instance to websocket server class.
       * @var ChiaWebSocketServerNew
       */
      private $server;

      /**
       * Initialises the needed and above stated private variables.
       */
      public function __construct(object $server = NULL){
        $this->logging_api = new Logging_Api($this, $server);
        $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
        $this->server = $server;
      }

      /**
       * Tests the connection to the websocket server.
       * Function made for: Web(App)client, Backendclient.
       * @param array $loginData   {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
       */
      public function testConnection(): object
      {    
        $wss_online_status = Promise\resolve($this->sendToWSS("wssonlinestatus", array("command" => "onlineStatus")));
        return $wss_online_status->then(function($wss_online_status_returned){
          if(array_key_exists("wssonlinestatus", $wss_online_status_returned) && $wss_online_status_returned["wssonlinestatus"]["status"] == 0) return $wss_online_status_returned["wssonlinestatus"];
          else return $this->logging_api->getErrormessage("testConnection", "001");
        });
      }

      /**
       * Sends a command to the webscoket server.
       * Function made for: Web(App)client, Backendclient.
       * @throws Exception $e           Throws an exception on websocket errors.
       * @param  string $socketaction   The websocket server socketaction.
       * @param  array  $data           The data which should be send to the websocket server.
       * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The returned data if stated.] }
       */
      public function sendToWSS(string $socketaction, array $data): object
      {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($socketaction, $data){
          $data = $this->buildCompleteRequest($socketaction, $data);

          \Ratchet\Client\connect("ws://{$this->ini["local_socket_domain"]}:{$this->ini["socket_local_port"]}")->then(function($conn) use($data, &$resolve){
            $conn->send(json_encode($data));
            
            $conn->on('message', function($msg) use (&$resolve, $conn) {
                //echo "Received: {$msg}\n";
                $resolve(json_decode($msg, true));
                $conn->close();
            });
          }, function ($e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("sendToWSS", "001", $e));
          });
        };
        
        $canceller = function () {
          throw new Exception('Promise cancelled');
        };
  
        return new Promise\Promise($resolver, $canceller);
      }

      /**
       * Sets the websocket server needed paramaters for authentication, etc.
       * @param  string $socketaction   The websocket server socketaction.
       * @param  array  $data           The data which should be send to the websocket server.
       * @return array                  Returns an array with all needed data stated.
       */
      private function buildCompleteRequest(string $socketaction, array $data): array
      {
        $all_data["node"]["nodeinfo"]["hostname"] = "localhost";
        $all_data["node"]["socketaction"] = $socketaction;

        $all_data["request"]["logindata"]["authhash"] = $this->ini["backend_client_auth_hash"];
        $all_data["request"]["data"] = $data;
        $all_data["request"]["backendInfo"]["namespace"] = "";
        $all_data["request"]["backendInfo"]["class"] = "";
        $all_data["request"]["backendInfo"]["method"] = "";

        return $all_data;
      }
    }
?>
