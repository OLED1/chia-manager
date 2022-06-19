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

  if((array_key_exists("db_update_needed", $system_update_state["data"]) && $system_update_state["data"]["db_update_needed"] > 0) || 
      (array_key_exists("maintenance_mode", $system_update_state["data"]) && $system_update_state["data"]["maintenance_mode"] == 1)){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/maintenance.php");
  }

  echo "<script nonce={$ini["nonce_key"]}>
          var backend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["backend_url"]}';
          var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
          var loggedinstatus = '{$loggedin["status"]}';
        </script>";
?>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="robots" content="noindex">

    <title>Chia® Manager - Login</title>

    <link rel="shortcut icon" type="image/x-icon" href="/frontend/img/favicon.ico">
    <link rel="icon" type="image/png" href="/frontend/img/favicon.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/frontend/img/favicon.png" sizes="96x96">

    <!-- Custom fonts for this template-->
    <link href="frameworks/node_modules/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="css/google_fonts/nunito/nunito-font.css" rel="stylesheet">
    <!-- Custom styles for this template-->

    <!-- Tailwind CSS -->
    <link href="css/tailwind.css" rel="stylesheet" type="text/css">
  </head>

  <body class="">
    <div class="min-h-screen bg-gray-100 py-6 flex flex-col justify-center dark:bg-gray-800">
      <div id="messagecontainer" class="">
        <!-- For testing -->
        <div class='alert'>
          <a href='#' class='close' data-dismiss='alert' aria-label='close' title='close'>×</a>
          <span>This is a testmessage :D</span>
        </div>
      </div>
      <div class="relative py-3 sm:max-w-xl sm:mx-auto">
        <div class="">
          <div class="">
            <div class="">
              <div id="loginwindow" class="bg-white shadow-lg px-4 py-3 sm:m-3 dark:bg-slate-300/25 rounded-md">
                <div class="">
                  <div class="max-w-md w-full space-y-8">
                    <div class="">
                      <h1 class="mt-6 text-center text-3xl text-gray-900 dark:text-gray-100 my-6">Welcome!</h1>
                    </div>
                    <form class="user">
                      <div class="rounded-md shadow-sm -space-y-px">
                        <input type="text" class="appearance-none rounded-none relative block w-full px-3 py-2 border dark:bg-gray-200 border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm" id="inputLogin" placeholder="Username">
                      
                        <input type="password" class="appearance-none rounded-none relative block w-full px-3 py-2 border dark:bg-gray-200 border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm" id="inputPassword" placeholder="Password">
                      </div>
                        <div class="flex items-center justify-between space-x-10 my-4">
                          <div class="flex items-center space-x-2">
                            <input type="checkbox" class="custom-control-input" id="stayloggedin">
                            <label class="custom-control-label dark:text-gray-200" for="stayloggedin">Stay logged in (30 days)</label>
                          </div>
                          <div class="">
                            <a id="forgot-password" class="font-medium text-green-600 hover:text-green-500 hover:underline" href="">Forgot Password?</a>
                          </div>
                      </div>
                      <button id="loginbutton" href="index.html" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-25">
                        Login&nbsp;<i class="fa-solid fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                    </form>
                  </div>
                </div>
              </div>
              <div id="authkeywindow" class="bg-white shadow-lg px-4 py-3 sm:m-3 dark:bg-slate-300/25 rounded-md" style="display: none; ">
                  <div class="max-w-md w-full space-y-8">
                  <div class="">
                    <div class="">
                      <h1 class="mt-6 text-center text-3xl text-gray-900 dark:text-gray-100 my-6">Authkey check</h1>
                      <p class="dark:text-gray-200">We have sent you a code to the email configured in your account.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="">
                        <input type="text" class="appearance-none rounded-md relative block w-full px-3 py-2 border dark:bg-gray-200 border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm" id="inputAuthkey" placeholder="Authkey">
                      </div>
                      <div class="flex text-right">
                        <a id="resend-authkey" href="#" class="font-medium text-green-600 hover:text-green-500 hover:underline">Resend authkey</a>
                      </div>
                      <button id="authkeybutton" href="#" class="my-6 group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" disabled="">
                        Check authkey and contine&nbsp;
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      
                      <hr class="my-2">
                      <div class="text-center">
                        <a href="#" class="send-backupkey font-medium text-green-600 hover:text-green-500 hover:underline">Send backup key instead</a>
                      </div>
                      <div class="text-center">
                        <a href="#" class="go-back font-medium text-green-600 hover:text-green-500 hover:underline">Go back to login</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div id="secondFactorTotpWindow" class="bg-white shadow-lg px-4 py-3 sm:m-3 dark:bg-slate-300/25 rounded-md" style="display: none;">
                <div class="col">
                  <div class="p-5">
                    <div class="text-center">
                      <h1 class="h4 text-gray-900 mb-4">Mobile second factor check</h1>
                      <p>Please open your authenticator app and enter the appropriate 6 digits key.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="form-group" id="inputTOTPkey">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="0">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="1">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="2">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="3">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="4">
                        <input type="text" maxlength="1" class="form-control form-control-user totpinput" data-input-index="5">
                      </div>
                      <button id="totpkeybutton" href="#" class="btn btn-primary btn-user btn-block" disabled="">
                        Check key and continue&nbsp;
                        <i class="fa-solid fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      <hr>
                      <div class="text-center">
                        <a href="#" class="small send-backupkey">Send backup key instead</a>
                      </div>
                      <div class="text-center">
                        <a href="#" class="small go-back">Go back to login</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <div id="pwresetwindow" class="bg-white shadow-lg px-4 py-3 sm:m-3 dark:bg-slate-300/25 rounded-md" style="display: none;">
                <div class="">
                  <div class="">
                    <div class="">
                      <h1 class="mt-6 text-center text-3xl text-gray-900 dark:text-gray-100 my-6">Password reset</h1>
                      <p class="dark:text-gray-200">If you forgot your password you can reset it here.</p>
                    </div>
                    <form class="check-authkey">
                      <div class="form-group">
                        <input type="text" class="appearance-none rounded-md relative block w-full px-3 py-2 border dark:bg-gray-200 border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm" id="inputPWReset" placeholder="max.mustermann">
                      </div>
                      <button id="sendResetLinkBtn" href="#" class="my-6 group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" disabled="">
                        Send reset link
                        <i class="fas fa-spinner fa-spin" style="display: none;"></i>
                      </button>
                      <div id="pwResetMessage" class="card bg-success text-white shadow" style="display: none;">
                        <div class="card-body">
                        </div>
                      </div>
                      <hr>
                      <div class="text-center">
                        <a id="pwreset-go-back" href="#" class="font-medium text-green-600 hover:text-green-500 hover:underline">Go back to login</a>
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
  </body>
  
  <script nonce=<?php echo $ini["nonce_key"]; ?> src="frameworks/node_modules/jquery/dist/jquery.min.js"></script>
  <script nonce=<?php echo $ini["nonce_key"]; ?> src="js/login/login.js"></script>
</html>