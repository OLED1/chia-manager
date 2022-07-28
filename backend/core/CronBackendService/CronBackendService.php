<?php
  namespace ChiaMgmt\CronBackendService;
  use React\Promise;
  use ChiaMgmt\System_Update\System_Update_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Logging\Logging_Api;

  require __DIR__ . '/../../../vendor/autoload.php';

  /**
   * The CronBackendService class will be a class which will be called from the system's cron service.
   * The cron should execute this Class every 5 minutes.
   * Currently not fully implemented. Will be implemented in version 0.2 or 0.3.
   * @todo Implement all functions (v0.2/v0.3)
   */
  class CronBackendService{
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->websocket_api = new WebSocket_Api();
      $this->system_update_api = new System_Update_Api();
      $this->logging_api = new Logging_Api($this);
    }

    /**
     * Queries information from all nodes by calling the websocket's command "queryCronData"
     */
    public function queryData(){      
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $system_update = Promise\resolve($this->system_update_api->checkUpdateRoutine());
        $system_update->then(function($system_update_returned){
          if($system_update_returned["data"]["maintenance_mode"] == 0 && $system_update_returned["data"]["process_update"] == 0){

            $logging_message = Promise\resolve($this->logging_api->getErrormessage("queryData", "001"));
            $logging_message->then(function($logging_message_returned){
              echo "{$this->getDate()}: {$logging_message_returned["message"]}\n";
            });

            $wssstatus = Promise\resolve($this->websocket_api->testConnection());
            $wssstatus->then(function($wssstatus_returned){
              if($wssstatus_returned["status"] == "016001001"){
                $logging_message = Promise\resolve($this->logging_api->getErrormessage("queryData", "002"));
                $logging_message->then(function($logging_message_returned){
                  echo "{$this->getDate()}: {$logging_message_returned["message"]}\n";
                });

                $wss_start = Promise\resolve($this->websocket_api->startWSS());
                $wss_start->then(function($wss_start_returned){
                  echo "{$this->getDate()}: {$wss_start_returned["message"]}\n";
                  return $this->queryData();
                });
              };

              if($wssstatus_returned["status"] == 0){
                $logging_message = Promise\resolve($this->logging_api->getErrormessage("queryData", "003"));
                $logging_message->then(function($logging_message_returned){
                  echo "{$this->getDate()}: {$logging_message_returned["message"]}\n";
                });

                $queryCron = Promise\resolve($this->websocket_api->sendToWSS("queryCronData"));
                $queryCron->then(function($queryCron_returned){
                  $cronExec = $queryCron_returned["cronJobExecution"];
                  $loglevel = ($cronExec["status"] == 0 ? 0 : 2);
                  Promise\resolve($this->logging_api->logtofile($loglevel, 0, $cronExec["message"]));
                  $logging_message = Promise\resolve($this->logging_api->getErrormessage("queryData", "004"));
                  $logging_message->then(function($logging_message_returned){
                    echo "{$this->getDate()}: {$logging_message_returned["message"]}\n";
                  });
                });
              }
            });
          }else{
            $logging_message = Promise\resolve($this->logging_api->getErrormessage("queryData", "005"));
            $logging_message->then(function($logging_message_returned){
              echo "{$this->getDate()}: {$logging_message_returned["message"]}\n";
            });
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Retuns the current formatted date.
     * This function is only meant for server logging in CLI.
     * @return DateTime The current date.
     */
    private function getDate(){
      return date("d.m.Y H:i:s");
    }
  }

  $cronBackendService = Promise\resolve((new CronBackendService())->queryData());
?>
