<?php
  session_start();

  use React\Promise;
  use ChiaMgmt\Login\Login_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();

  if(isset($_POST["data"]["username"]) && isset($_POST["action"]) && $_POST["action"] == "login" &&
    $_POST["data"]["username"] != "" && $_POST["data"]["password"] != "" && $_POST["data"]["stayloggedin"] != ""){

    $username = $_POST["data"]["username"];
    $password = $_POST["data"]["password"];
    $stayloggedin = filter_var($_POST["data"]["stayloggedin"], FILTER_VALIDATE_BOOLEAN);

    $function_promise = Promise\resolve($login_api->login($username, $password, $stayloggedin));
    $function_promise->then(function($function_return){
      echo json_encode($function_return);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "checklogin"){
    $login_status = Promise\resolve($login_api->checklogin());
    $login_status->then(function($login_status_returned){
      echo json_encode($login_status_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "generateAndsendAuthKey"){
    $generate_send_key = Promise\resolve($login_api->generateAndsendAuthKey());
    $generate_send_key->then(function($generate_send_key_returned){
      echo json_encode($generate_send_key_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "invalidateLogin"){
    $invalidate_login = Promise\resolve($login_api->invalidateLogin());
    $invalidate_login->then(function($invalidate_login_returned){
      echo json_encode($invalidate_login_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkAuthKey"){
    $check_auth_key = Promise\resolve($login_api->checkAuthKey($_POST["data"]["authkey"]));
    $check_auth_key->then(function($generate_send_key_returned){
      echo json_encode($generate_send_key_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkTOTPKey"){
    $totp_key_passed = Promise\resolve($login_api->checkTOTPMobilePassed($_POST["data"]["totpkey"]));
    $totp_key_passed->then(function($totp_key_passed_returned){
      echo json_encode($totp_key_passed_returned);
    });

  }else if(isset($_POST["action"]) && $_POST["action"] == "logout"){
    $invalidate_login = Promise\resolve($login_api->invalidateLogin());
    $invalidate_login->then(function($invalidate_login_returned){
      echo json_encode($invalidate_login_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkBackupKeyValid"){
    $checkBackupKeyValid = Promise\resolve($login_api->checkBackupKeyValid($_POST["data"]["backupkey"]));
    $checkBackupKeyValid->then(function($checkBackupKeyValid_returned){
      echo json_encode($checkBackupKeyValid_returned);
    });
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }


?>
