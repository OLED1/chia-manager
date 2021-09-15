<?php
  namespace ChiaMgmt\CronBackendService;

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
      $this->logging_api = new Logging_Api($this);
    }

    /**
     * Queries information from all nodes by calling the websocket's command "queryCronData"
     */
    public function queryData(){
      $wssstatus = $this->websocket_api->testConnection();
      if($wssstatus["status"] == 0){
        print_r("Sending Cron Query Request to WSS.\n");
        print_r($this->websocket_api->sendToWSS("queryCronData"));
        print_r("Cron query processed.\n");
      }else{
        //restartWSS
      }
    }
  }

  $cronBackendService = new CronBackendService();
  $cronBackendService->queryData();
?>
