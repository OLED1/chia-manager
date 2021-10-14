<?php
  session_start();
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $system_update_api = new System_Update_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "checkServerDependencies"){
    echo json_encode($system_update_api->checkServerDependencies());
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkMySQLConfig" && isset($_POST["data"]) &&
          isset($_POST["data"]["db_name"]) && isset($_POST["data"]["db_host"]) && isset($_POST["data"]["db_user"]) && isset($_POST["data"]["db_password"])){

    echo json_encode($system_update_api->checkMySQLConfig($_POST["data"]["db_name"], $_POST["data"]["db_user"], $_POST["data"]["db_password"], $_POST["data"]["db_host"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "installChiamgmt" &&
          isset($_POST["data"]) && isset($_POST["data"]["db_config"]) && isset($_POST["data"]["websocket_config"]) && isset($_POST["data"]["webgui_user_config"])){

    echo json_encode($system_update_api->installChiamgmt($_POST["data"]["branch"], $_POST["data"]["db_config"], $_POST["data"]["websocket_config"], $_POST["data"]["webgui_user_config"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkFilesWritable"){
      echo json_encode($system_update_api->checkFilesWritable());
  }else if(isset($_POST["action"]) && $_POST["action"] == "setMaintenanceMode" && isset($_POST["data"]["userID"]) && isset($_POST["data"]["maintenance_mode"])){
      echo json_encode($system_update_api->setMaintenanceMode($_POST["data"]["userID"], $_POST["data"]["maintenance_mode"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "stopWebsocketServer"){
      echo json_encode($system_update_api->stopWebsocketServer());
  }else if(isset($_POST["action"]) && $_POST["action"] == "createBackups"){
      echo json_encode($system_update_api->createBackups());
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }
?>
