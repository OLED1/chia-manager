<?php
  session_start();
  use React\Promise;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $websocket_api = new WebSocket_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "startWebsocket"){
    $start_websocket = Promise\resolve($websocket_api->startWSS());
    $start_websocket->then(function($start_websocket_returned){
      echo json_encode($start_websocket_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "stopWebsocket"){
    $stop_websocket = Promise\resolve($websocket_api->stopWSS());
    $stop_websocket->then(function($stop_websocket_returned){
      echo json_encode($stop_websocket_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "restartWebsocket"){
    $restart_websocket = Promise\resolve($websocket_api->restartWSS());
    $restart_websocket->then(function($restart_websocket_returned){
      echo json_encode($restart_websocket_returned);
    });
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }
?>
