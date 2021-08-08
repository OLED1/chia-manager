<?php
  $ini = parse_ini_file(__DIR__.'/../backend/config/config.ini.php');
  header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/sites/index.php");
?>
