<?php
  session_start();
  use React\Promise;
  use ChiaMgmt\Users\Users_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $users_api = new Users_Api();

  if(isset($_POST["action"]) && $_POST["action"] == "requestUserPasswordReset" && array_key_exists("username", $_POST["data"])){
    $requestUserPasswordReset = Promise\resolve($users_api->requestUserPasswordReset($_POST["data"]["username"]));
    $requestUserPasswordReset->then(function($requestUserPasswordReset_returned){
      echo json_encode($requestUserPasswordReset_returned);
    });
  }else if(isset($_POST["action"]) && $_POST["action"] == "resetPassword" &&
            array_key_exists("resetKey", $_POST["data"]) && array_key_exists("newUserPassword", $_POST["data"])){

    $resetPassword = Promise\resolve($users_api->resetPassword($_POST["data"]["resetKey"], $_POST["data"]["newUserPassword"]));
    $resetPassword->then(function($resetPassword_returned){
      echo json_encode($resetPassword_returned);
    });
  }else{
    echo json_encode(array("status" => 1, "message" => "Action not known or not allowed."));
  }
?>
