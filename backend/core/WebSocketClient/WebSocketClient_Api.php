<?php
    namespace ChiaMgmt\WebSocketClient;
    use ChiaMgmt\Logging\Logging_Api;
    use Amp\Delayed;
    use Amp\Websocket\Client\Connection;
    use Amp\Websocket\Client\Handshake;
    use Amp\Websocket\Message;
    use React\Promise\Deferred;
    use React\Promise\RejectedPromise;
    use function Amp\Websocket\Client\connect;

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
       * Initialises the needed and above stated private variables.
       */
      public function __construct(){
        $this->logging_api = new Logging_Api($this);
        $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      }

      /**
       * Tests the connection to the websocket server.
       * Function made for: Web(App)client, Backendclient.
       * @param array $loginData   {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]" }
       */
      public function testConnection(){
        if (is_resource(@fsockopen("localhost", $this->ini["socket_local_port"]))){
          $result = $this->sendToWSS("wssonlinestatus", array("command" => "onlineStatus"));

          if($result["status"] == 0) return $result;
          else return $this->logging_api->getErrormessage("001");
        }else{
          return $this->logging_api->getErrormessage("002");
        }
      }

      /**
       * Sends a command to the webscoket server.
       * Function made for: Web(App)client, Backendclient.
       * @throws Exception $e           Throws an exception on websocket errors.
       * @param  string $socketaction   The websocket server socketaction.
       * @param  array  $data           The data which should be send to the websocket server.
       * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The returned data if stated.] }
       */
      public function sendToWSS(string $socketaction, array $data){
        try{
          $returndata = new \Amp\Deferred;
          $data = $this->buildCompleteRequest($socketaction, $data);


          \Amp\Loop::run(function () use ($returndata, $data) {
            $handshake = (new Handshake("ws://localhost:{$this->ini['socket_local_port']}"))
            ->withHeader('Origin', "{$this->ini['app_protocol']}://{$this->ini['app_domain']}");

            $connection = yield connect($handshake);
            yield $connection->send(json_encode($data));

            while ($message = yield $connection->receive()) {
              $returnmessage = json_decode(yield $message->buffer(), true);
              $returndata->resolve($returnmessage);
              break;
            }

            \Amp\Loop::stop();
          });
          $promise = \Amp\Promise\wait($returndata->promise());
          if(is_null(\Amp\Promise\wait($returndata->promise()))){
              $promise = array();
          }
          return $promise;
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }

      /**
       * Sets the websocket server needed paramaters for authentication, etc.
       * @param  string $socketaction   The websocket server socketaction.
       * @param  array  $data           The data which should be send to the websocket server.
       * @return array                  Returns an array with all needed data stated.
       */
      private function buildCompleteRequest(string $socketaction, array $data){
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
