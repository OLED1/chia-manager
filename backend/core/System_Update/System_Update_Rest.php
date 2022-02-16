<?php
  session_start();
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();

  if(!array_key_exists("db_install_needed", $system_update_state["data"]) && !(array_key_exists("process_update", $system_update_state["data"]) && $system_update_state["data"]["process_update"])){
    echo json_encode(array("status" => 1, "message" => "No update process started."));
    die();
  }

  $system_update_api = new System_Update_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "checkServerDependencies"){
    echo json_encode($system_update_api->checkServerDependencies());
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkMySQLConfig" && isset($_POST["data"]) &&
            isset($_POST["data"]["db_name"]) && isset($_POST["data"]["db_host"]) && isset($_POST["data"]["db_user"]) && isset($_POST["data"]["db_password"])){

    echo json_encode($system_update_api->checkMySQLConfig($_POST["data"]["db_name"], $_POST["data"]["db_user"], $_POST["data"]["db_password"], $_POST["data"]["db_host"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkWSSPortAvailable" && isset($_POST["data"]) &&
                  isset($_POST["data"]["wss-port"])){

    echo json_encode($system_update_api->checkWSSPortAvailable($_POST["data"]["wss-port"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "installChiamgmt" &&
          isset($_POST["data"]) && isset($_POST["data"]["db_config"]) && isset($_POST["data"]["websocket_config"]) && isset($_POST["data"]["webgui_user_config"])){

    echo json_encode($system_update_api->installChiamgmt($_POST["data"]["branch"], $_POST["data"]["db_config"], $_POST["data"]["websocket_config"], $_POST["data"]["webgui_user_config"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "cancelUpdate"){
    echo json_encode($system_update_api->cancelUpdate());
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkFilesWritable"){
    echo json_encode($system_update_api->checkFilesWritable());
  }else if(isset($_POST["action"]) && $_POST["action"] == "setMaintenanceMode" && isset($_POST["data"]["userID"]) && isset($_POST["data"]["maintenance_mode"])){
    echo json_encode($system_update_api->setMaintenanceMode($_POST["data"]["userID"], $_POST["data"]["maintenance_mode"]));
  }else if(isset($_POST["action"]) && $_POST["action"] == "stopWebsocketServer"){
    echo json_encode($system_update_api->stopWebsocketServer());
  }else if(isset($_POST["action"]) && $_POST["action"] == "createBackups"){
    echo json_encode($system_update_api->createBackups());
  }else if(isset($_POST["action"]) && $_POST["action"] == "downloadUpdateFiles"){
    echo json_encode($system_update_api->downloadUpdateFiles());
  }else if(isset($_POST["action"]) && $_POST["action"] == "extractAndMoveUpdateFiles"){
    echo json_encode($system_update_api->extractAndMoveUpdateFiles());
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkAndAdjustDatabase"){
    echo json_encode($system_update_api->checkAndAdjustDatabase());
  }else if(isset($_POST["action"]) && $_POST["action"] == "updateConfigFile"){
    echo json_encode($system_update_api->updateConfigFile());
  }else if(isset($_POST["action"]) && $_POST["action"] == "startWebsocketServer"){
    echo json_encode($system_update_api->startWebsocketServer());
  }else if(isset($_POST["action"]) && $_POST["action"] == "setInstanceUpdating" && isset($_POST["data"]["userid"]) && isset($_POST["data"]["updatestate"])){
    echo json_encode($system_update_api->setInstanceUpdating($_POST["data"]));
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }
?>
