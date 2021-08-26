<?php
  session_start();
  include_once("login_api.php");

  $login_api = new Login();

  if(isset($_POST["data"]["username"]) && $_POST["action"] && $_POST["action"] == "login" && $_POST["data"]["username"] != "" && $_POST["data"]["password"] != ""){
    $username = $_POST["data"]["username"];
    $password = $_POST["data"]["password"];
    echo json_encode($login_api->login($username, $password));
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }


?>
