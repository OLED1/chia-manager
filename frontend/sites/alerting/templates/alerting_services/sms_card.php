<?php
  use React\Promise;
  use ChiaMgmt\Login\Login_Api;
  require __DIR__ . '/../../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET) || !array_key_exists("SMS", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $site_data_to_load = [
    React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    /*React\Promise\resolve($chia_farm_api->getFarmData(["nodeid" => $_GET["nodeid"]])),
    React\Promise\resolve($chia_farm_api->getChallenges(["limit" => 50, "nodeid" => $_GET["nodeid"]]))*/
  ];

  $ini = parse_ini_file(__DIR__.'/../../../../../backend/config/config.ini.php');
  React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }
    echo "...";
  });
?>