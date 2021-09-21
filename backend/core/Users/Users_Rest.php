<?php
  session_start();
  use ChiaMgmt\Users\Users_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $users_api = new Users_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "requestUserPasswordReset" && array_key_exists("username", $_POST["data"])){
    $username = $_POST["data"]["username"];
    echo json_encode($users_api->requestUserPasswordReset($username));
  }else if(isset($_POST["action"]) && $_POST["action"] == "resetPassword" &&
            array_key_exists("resetKey", $_POST["data"]) && array_key_exists("newUserPassword", $_POST["data"])){

    echo json_encode($users_api->resetPassword($_POST["data"]["resetKey"], $_POST["data"]["newUserPassword"]));
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }


?>
