<?php
  namespace ChiaMgmt\CronBackendService;

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
      $system_update_state = $this->system_update_api->checkUpdateRoutine();
      if($system_update_state["data"]["maintenance_mode"] == 0 && $system_update_state["data"]["process_update"] == 0){
        echo "{$this->getDate()}: {$this->logging_api->getErrormessage("001")["message"]}\n";
        $wssstatus = $this->websocket_api->testConnection();
        echo "{$this->getDate()}: {$wssstatus["message"]}\n";
  
        if($wssstatus["status"] == "016001002"){
          echo "{$this->getDate()}: {$this->logging_api->getErrormessage("002")["message"]}\n";
          $wssstatus = $this->websocket_api->startWSS();
          echo "{$this->getDate()}: {$wssstatus["message"]}\n";
        };
  
        if($wssstatus["status"] == 0){
          echo "{$this->getDate()}: {$this->logging_api->getErrormessage("003")["message"]}\n";
          $cronExec = $this->websocket_api->sendToWSS("queryCronData")["cronJobExecution"];
          $loglevel = ($cronExec["status"] == 0 ? 0 : 2);
          $this->logging_api->logtofile($loglevel, 0, $cronExec["message"]);
          echo "{$this->getDate()}: {$this->logging_api->getErrormessage("004")["message"]}\n";
        }
      }else{
        echo "{$this->getDate()}: {$this->logging_api->getErrormessage("005")["message"]}\n";
      }
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

  $cronBackendService = new CronBackendService();
  $cronBackendService->queryData();
?>
