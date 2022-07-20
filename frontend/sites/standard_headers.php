<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../../vendor/autoload.php';

  header('Cache-Control: no-cache, no-store, must-revalidate');
  header('Pragma: no-cache');
  header('Expires: 0');

  $ini = parse_ini_file(__DIR__.'/../../backend/config/config.ini.php');

  $check_login = React\Promise\resolve((new Login_Api())->checklogin());
  $update_running = React\Promise\resolve((new System_Update_Api())->checkUpdateRoutine());

  React\Promise\all([$check_login, $update_running])->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "<script type='text/javascript' nonce={$ini["nonce_key"]}>
              window.location.href = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/login.php';
            </script>";
    }

    if(array_key_exists("maintenance_mode", $all_returned[1]["data"]) && $all_returned[1]["data"]["maintenance_mode"] == 1){
      echo "<script type='text/javascript' nonce={$ini["nonce_key"]}>
              window.location.href = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/maintenance.php';
            </script>";
    }
  });
?>
