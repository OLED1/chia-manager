<?php
  namespace ChiaMgmt\WebSocket;

  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\WebSocketClient\WebSocketClient_Api;

  class WebSocket_Api{
    private $wsclient, $logging;

    public function __construct(){
      $this->wsclient = new WebSocketClient_Api();
      $this->logging = new Logging_Api($this);
    }

    public function testConnection(){
      try{
        return $this->wsclient->testConnection();
      }catch(Exception $e){
        /*print_r($e);
        return array("status" => 1, "An error occured");*/
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function updateSiteID(int $siteID){
      if($siteID > 0){
        $con_test = $this->testConnection();
        if($con_test["status"] == 0){
          return $this->wsclient->sendToWSS("updateFrontendViewingSite", array("userID" => $_COOKIE["user_id"], "siteID" => $siteID));
        }else{
          return $con_test;
        }
      }else{
        //return array("status" => 1, "message" => "SiteID not valid.");
        return $this->logging->getErrormessage("001");
      }
    }

    public function sendToWSS(string $command, array $data = []){
      $con_test = $this->testConnection();
      if($con_test["status"] == 0){
        return $this->wsclient->sendToWSS($command, $data);
      }else{
        return $con_test;
      }
    }

    public function startWSS(){
      $wssstatus = $this->wsclient->testConnection();
      if($wssstatus["status"] == 1){
        exec("php " . __DIR__ . "/websocket.php > /dev/null &");
        sleep(1);
        $wssstatus = $this->wsclient->testConnection();
        if($wssstatus["status"] == 0){
          return $wssstatus;
        }else{
          //return array("status" => 1, "message" => "Cannot start websocket server.");
          return $this->logging->getErrormessage("001");
        }
      }else{
        //return array("status" => 1, "message" => "Websocket server running. Cannot start.");
        return $this->logging->getErrormessage("002");
      }
    }

    public function stopWSS(){
      $wssstatus = $this->wsclient->testConnection();
      if($wssstatus["status"] == 0){
        exec("kill -9 {$wssstatus["data"]}");
        sleep(1);

        if($this->wsclient->testConnection()["status"] == 1){
          return array("status" => 0, "message" => "Websocket server stopped.");
        }else{
          //return array("status" => 1, "message" => "Cannot stop websocket server.");
          return $this->logging->getErrormessage("001");
        }
      }else{
        //return array("status" => 1, "message" => "Websocket server not running. Cannot stop.");
        return $this->logging->getErrormessage("002");
      }
    }

    public function restartWSS(){
      $stop = $this->stopWSS();
      if($stop["status"] == 0){
        $start = $this->startWSS();
        return $start;
      }else{
        return $stop;
      }
    }


  }
?>
