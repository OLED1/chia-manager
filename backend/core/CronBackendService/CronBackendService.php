<?php
  namespace ChiaMgmt\CronBackendService;

  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Logging\Logging_Api;

  require __DIR__ . '/../../../vendor/autoload.php';

  class CronBackendService{
    private $websocket_api, $logging_api;

    public function __construct(){
      $this->websocket_api = new WebSocket_Api();
      $this->logging_api = new Logging_Api($this);
    }

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
