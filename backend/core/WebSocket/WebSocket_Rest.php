<?php
  session_start();
  use ChiaMgmt\WebSocket\WebSocket_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $websocket_api = new WebSocket_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "startWebsocket"){
    echo json_encode($websocket_api->startWSS());
  }else if(isset($_POST["action"]) && $_POST["action"] == "stopWebsocket"){
    echo json_encode($websocket_api->stopWSS());
  }else if(isset($_POST["action"]) && $_POST["action"] == "restartWebsocket"){
    echo json_encode($websocket_api->restartWSS());
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }
?>
