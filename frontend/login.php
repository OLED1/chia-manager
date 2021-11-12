<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../vendor/autoload.php';

  $login_api = new Login_Api();

  $ini = parse_ini_file(__DIR__.'/../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] == 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/index.php");
  }

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();

  if($system_update_state["data"]["db_update_needed"] > 0 || $system_update_state["data"]["maintenance_mode"] == 1){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/maintenance.php");
  }

  echo "<script nonce={$ini["nonce_key"]}>
          var backend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["backend_url"]}';
          var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
          var loggedinstatus = '{$loggedin["status"]}';
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

  <title>Chia Manager - Login</title>

  <link rel="shortcut icon" type="image/x-icon" href="<?php echo $ini["frontend_url"]."/img/favicon.ico"?>">
  <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="32x32">
  <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="96x96">

  <!-- Custom fonts for this template-->
  <link href="frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
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
                        <h1 class="h4 text-gray-900 mb-4">Welcome!</h1>
                    </div>
                    <form class="user">
                      <div class="form-group">
                        <input type="text" class="form-control form-control-user" id="inputLogin" placeholder="Username">
                      </div>
                      <div class="form-group">
                        <input type="password" class="form-control form-control-user"   id="inputPassword" placeholder="Password">
                      </div>
                        <div class="form-group">
                          <div class="custom-control custom-checkbox small">
                            <input type="checkbox" class="custom-control-input" id="stayloggedin">
                            <label class="custom-control-label" for="stayloggedin">Stay logged in (30 days)</label>
                          </div>
                      </div>
                      <button id="loginbutton" href="index.html" class="btn btn-primary btn-user btn-block">Login&nbsp;<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
                    </form>
                    <hr>
                    <div class="text-center">
                      <a id="forgot-password" class="small" href="">Forgot Password?</a>
                    </div>
                  </div>
                </div>
              </div>
              <div id="authkeywindow" class="row" style="display: none;">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <h1 class="h4 text-gray-900 mb-4">Authkey check</h1>
                      <p>We sent you a authkey to your accounts setup email.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="form-group">
                        <input type="text" class="form-control form-control-user" id="inputAuthkey" placeholder="Authkey">
                      </div>
                      <button id="authkeybutton" href="#" class="btn btn-primary btn-user btn-block" disabled>
                        Check authkey and contine&nbsp;
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      <hr>
                      <div class="text-center">
                        <a id="resend-authkey" href="#" class="small" href="#">Resend authkey</a>
                      </div>
                      <hr>
                      <div class="text-center">
                        <a href="#" class="small send-backupkey" href="#">Send backup key instead</a>
                      </div>
                      <div class="text-center">
                        <a href="#" class="small go-back" href="#">Go back to login</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div id="secondFactorTotpWindow" class="row" style="display: none;">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <h1 class="h4 text-gray-900 mb-4">Mobile second factor check</h1>
                      <p>Please open your authenticator app and enter the stated 6 digits key.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="form-group" id="inputTOTPkey">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="0">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="1">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="2">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="3">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="4">
                        <input type="text" maxlength=1 class="form-control form-control-user totpinput" data-input-index="5">
                      </div>
                      <button id="totpkeybutton" href="#" class="btn btn-primary btn-user btn-block" disabled>
                        Check key and continue&nbsp;
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      <hr>
                      <div class="text-center">
                        <a href="#" class="small send-backupkey" href="#">Send backup key instead</a>
                      </div>
                      <div class="text-center">
                        <a href="#" class="small go-back" href="#">Go back to login</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div id="pwresetwindow" class="row" style="display: none;">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <h1 class="h4 text-gray-900 mb-4">Password reset</h1>
                      <p>If you forgot your password you can reset it here.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="form-group">
                        <input type="text" class="form-control form-control-user" id="inputPWReset" placeholder="max.mustermann">
                      </div>
                      <button id="sendResetLinkBtn" href="#" class="btn btn-primary btn-user btn-block" disabled>
                        Send reset link
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      <div id="pwResetMessage" class='card bg-success text-white shadow' style="display: none;">
                        <div class='card-body'>
                        </div>
                      </div>
                      <hr>
                      <div class="text-center">
                        <a id="pwreset-go-back" href="#" class="small" href="">Go back to login</a>
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

    <!-- Bootstrap core JavaScript-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/bootstrap/js/sb-admin-2.min.js"></script>

    <script nonce=<?php echo $ini["nonce_key"]; ?> src="js/login/login.js"></script>
</body>

</html>
