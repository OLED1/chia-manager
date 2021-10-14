<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();

  if(array_key_exists("maintenance_mode", $system_update_state["data"]) && $system_update_state["data"]["maintenance_mode"] == 1){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/maintenance.php");
  }
?>
