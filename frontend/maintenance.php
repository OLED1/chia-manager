<?php
  session_start();

  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../vendor/autoload.php';

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();
  $ini = parse_ini_file(__DIR__.'/../backend/config/config.ini.php');

  if($system_update_state["data"]["maintenance_mode"] == 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/index.php");
  }else{
    $page = $_SERVER['PHP_SELF'];
    $sec = "10";
    header("Refresh: $sec; url=$page");
  }
?>
<!DOCTYPE html>
<html lang="en">
  <head>
      <meta charset="utf-8">
      <meta http-equiv="X-UA-Compatible" content="IE=edge">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <meta name="description" content="">
      <meta name="author" content="">

      <title>Chia Manager - Maintenance</title>

      <link rel="shortcut icon" type="image/x-icon" href="img/favicon.ico">
      <link rel="icon" type="image/png" href="img/favicon.png" sizes="32x32">
      <link rel="icon" type="image/png" href="img/favicon.png" sizes="96x96">

      <!-- Custom fonts for this template-->
      <link href="frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
      <link href="css/google_fonts/nunito/nunito-font.css" rel="stylesheet">
      <!-- Custom styles for this template-->
      <link href="frameworks/bootstrap/css/sb-admin-2.min.css" rel="stylesheet">
  </head>
  <body class="bg-gradient-primary">
    <div class="container" style="display: none;">
      <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-6">
          <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
              <div class="row">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <i class="fas fa-hard-hat 9px" style="font-size: 3em"></i>
                      <h2>Maintenance</h2>
                      <p>This instance is currently in maintenance mode. Please check back later.<br>
                      The site will be reloaded as soon as the maintenance ends.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
  <script nonce=<?php echo $ini["nonce_key"]; ?>>
    $(function(){
      if($("#maintenance_mode_modal").length > 0 && $("#update_routines").length == 0){
        $("#maintenance_mode_modal").modal("show");
      }else if($("#update_routines").length == 0){
        $(".container").show();
      }
    });
  </script>
</html>
