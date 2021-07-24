<?php
  session_start();
  use ChiaMgmt\Login\Login_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();

  if(isset($_POST["data"]["username"]) && isset($_POST["action"]) && $_POST["action"] == "login" &&
    $_POST["data"]["username"] != "" && $_POST["data"]["password"] != "" && $_POST["data"]["stayloggedin"] != ""){

    $username = $_POST["data"]["username"];
    $password = $_POST["data"]["password"];
    $stayloggedin = filter_var($_POST["data"]["stayloggedin"], FILTER_VALIDATE_BOOLEAN);

    echo json_encode($login_api->login($username, $password, $stayloggedin));
  }else if(isset($_POST["action"]) && $_POST["action"] == "checklogin"){
    echo json_encode($login_api->checklogin());
  }else if(isset($_POST["action"]) && $_POST["action"] == "generateAndsendAuthKey"){
    echo json_encode($login_api->generateAndsendAuthKey());
  }else if(isset($_POST["action"]) && $_POST["action"] == "invalidateLogin"){
    echo json_encode($login_api->invalidateLogin());
  }else if(isset($_POST["action"]) && $_POST["action"] == "checkAuthKey"){
    $authkey = $_POST["data"]["authkey"];
    echo json_encode($login_api->checkAuthKey($authkey));
  }else if(isset($_POST["action"]) && $_POST["action"] == "logout"){
    echo json_encode($login_api->invalidateLogin());
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }


?>
