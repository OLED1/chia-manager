<?php
  session_start();

  use ChiaMgmt\Users\Users_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../vendor/autoload.php';

  $users_api = new Users_Api();
  $ini = parse_ini_file(__DIR__.'/../backend/config/config.ini.php');

  if(array_key_exists("pw-reset-key", $_GET) && $users_api->checkResetLinkValid($_GET["pw-reset-key"])["status"] == 0){
    $system_update_api = new System_Update_Api();
    $system_update_state = $system_update_api->checkUpdateRoutine();

    if($system_update_state["data"]["db_update_needed"] > 0 || $system_update_state["data"]["maintenance_mode"] == 1){
      header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/maintenance.php");
    }
  }else{
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]);
  }

  echo "<script nonce={$ini["nonce_key"]}>
          var resetkey = '{$_GET["pw-reset-key"]}';
          var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
          var apilink = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["backend_url"]}/core/Users/Users_Rest.php';
        </script>";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <meta name="robots" content="noindex">

  <title>Chia® Manager - Reset Password</title>

  <link rel="shortcut icon" type="image/x-icon" href="<?php echo $ini["frontend_url"]."/img/favicon.ico"?>">
  <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="32x32">
  <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="96x96">

  <!-- Custom fonts for this template-->
  <link href="frameworks/node_modules/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
  <link href="css/google_fonts/nunito/nunito-font.css" rel="stylesheet">
  <!-- Custom styles for this template-->
  <link href="frameworks/bootstrap/css/sb-admin-2.min.css" rel="stylesheet">
  <link href="css/custom.css" rel="stylesheet">
</head>

<body class="bg-gradient-primary">
  <div class="container">
    <div id="messagecontainer">
    </div>
      <div class="row justify-content-center">
        <div class="col-xl-5 col-lg-6 col-md-6">
          <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
              <div id="loginwindow" class="row">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <h1 class="h4 text-gray-900 mb-4">Reset your password</h1>
                    </div>
                    <form class="user">
                      <div class="form-group">
                        <input type="password" class="form-control form-control-user" id="inputPassword" placeholder="Password">
                        <label for="inputPassword">New password</label>
                      </div>
                      <div class="form-group">
                        <input type="password" class="form-control form-control-user" id="inputRepeatPassword" placeholder="Repeat Password">
                        <label for="inputRepeatPassword">Repeat new password</label>
                      </div>
                      <button id="resetPassword" href="" class="btn btn-primary btn-user btn-block" disabled>Reset password&nbsp;<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
                      <div id="passwordhint" class='card bg-warning text-white shadow' style="display: none;">
                        <div class='card-body'></div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/node_modules/jquery/dist/jquery.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/vendor/bootstrap/__old/js/bootstrap.bundle.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/node_modules/jquery.easing/jquery.easing.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/js/sb-admin-2.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="js/pwreset/pwreset.js"></script>
</body>
</html>
