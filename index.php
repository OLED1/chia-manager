<?php
  $config_file_path = __DIR__.'/backend/config/config.ini.php';
  if(file_exists($config_file_path)){
    $ini = parse_ini_file($config_file_path);

    if(array_key_exists("app_domain", $ini)){
      header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/sites/index.php");
    }
  }else{
    header("Location: http://{$_SERVER['SERVER_NAME']}/frontend/sites/installer_updater");
  }
?>
