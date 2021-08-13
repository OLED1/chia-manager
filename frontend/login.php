<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  require __DIR__ . '/../vendor/autoload.php';

  $login_api = new Login_Api();

  $ini = parse_ini_file(__DIR__.'/../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] == 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/index.php");
  }

  echo "<script> var backend = '". $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["backend_url"]."'; </script>";
  echo "<script> var frontend = '". $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."'; </script>";
  echo "<script> var loggedinstatus = '" . $loggedin["status"] . "'; </script>";
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>SB Admin 2 - Login</title>

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
        <!-- Outer Row -->
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
                                          <input type="text" class="form-control form-control-user"
                                              id="inputLogin" placeholder="Username">
                                        </div>
                                        <div class="form-group">
                                            <input type="password" class="form-control form-control-user"
                                                id="inputPassword" placeholder="Password">
                                        </div>
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox small">
                                                <input type="checkbox" class="custom-control-input" id="stayloggedin">
                                                <label class="custom-control-label" for="stayloggedin">Stay logged in</label>
                                            </div>
                                            <div class="custom-control custom-checkbox small">
                                                <input type="checkbox" class="custom-control-input" id="remeberMe">
                                                <label class="custom-control-label" for="remeberMe">Remember Me</label>
                                            </div>
                                        </div>
                                        <a id="loginbutton" href="index.html" class="btn btn-primary btn-user btn-block">
                                            Login
                                        </a>
                                    </form>
                                    <hr>
                                    <div class="text-center">
                                        <a class="small" href="forgot-password.html">Forgot Password?</a>
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
                                    <form class="user">
                                        <div class="form-group">
                                          <input type="text" class="form-control form-control-user"
                                              id="inputAuthkey" placeholder="Authkey">
                                        </div>
                                          <button id="authkeybutton" href="#" class="btn btn-primary btn-user btn-block" disabled>
                                            Check authkey and login
                                          </button>
                                        <hr>
                                        <div class="text-center">
                                            <a id="resend-authkey" href="#" class="small" href="#">Resend authkey</a>
                                        </div>
                                        <hr>
                                        <div class="text-center">
                                            <a id="send-backupkey" href="#" class="small" href="#">Send backup key instead</a>
                                        </div>
                                        <div class="text-center">
                                            <a id="go-back" href="#" class="small" href="#">Go back to login</a>
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
    <script src="frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
    <script src="frameworks/bootstrap/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="frameworks/bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="frameworks/bootstrap/js/sb-admin-2.min.js"></script>

    <script src="js/login/login.js"></script>

</body>

</html>
