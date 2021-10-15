<?php
  use ChiaMgmt\System_Update\System_Update_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');

  $default_nonce = "3LMJm+1llrExr4spfB+DrjbN5ys7gYhj1w=";
  $db_install = false;
  $process_update = false;

  if(array_key_exists("db_install_needed", $system_update_state["data"])){
    $db_install = true;
  }else if(array_key_exists("process_update", $system_update_state["data"]) && $system_update_state["data"]["process_update"]){
    $default_nonce = $ini["nonce_key"];
    $update_infos = $system_update_api->checkForUpdates()["data"];
    $process_update = true;
  }else{
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  echo "<script nonce={$default_nonce}>
          var userID = {$_COOKIE["user_id"]};
        </script>";
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Chia Manager - Dashboard</title>

    <link rel="shortcut icon" type="image/x-icon" href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/img/favicon.ico"?>">
    <link rel="icon" type="image/png" href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/img/favicon.png"?>" sizes="32x32">
    <link rel="icon" type="image/png" href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/img/favicon.png"?>" sizes="96x96">

    <!-- Custom fonts for this template-->
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css"; ?>" rel="stylesheet" type="text/css">
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/css/google_fonts/nunito/nunito-font.css"; ?>" rel="stylesheet">
    <!-- Custom styles for this template-->
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/vendor/bootstrap/css/bootstrap.min.css"; ?>" rel="stylesheet">
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/css/sb-admin-2.min.css"; ?>" rel="stylesheet">
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/css/custom.css"; ?>" rel="stylesheet">
    <link href="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/css/gui-modes/dark-mode.css"; ?>" rel="stylesheet">
</head>

<body id="page-top" style="overflow: auto;">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion gui-mode-elem" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
              <span class="sidebar-brand-icon projectlogo"></span>
              <div class="sidebar-brand-text mx-1">Chia Manager</div>
            </a>

            <hr class="sidebar-divider my-0">
            <hr class="sidebar-divider">
            <?php if($db_install){ ?>
            <div class="sidebar-heading">
                Installation
            </div>
            <li id="nav-welcome-page" class="nav-item active">
              <a class="nav-link" data-siteid=2 data-nav-target="nav-nodes" href="#">
                <i class="fas fa-hand-sparkles"></i>
                <span>Welcome</span>
              </a>
            </li>
            <li id="nav-server-dependencies" class="nav-item">
              <a class="nav-link" data-siteid=8 data-nav-target="nav-infra-sysinfo" href="#">
                <i class="fas fa-cube"></i>
                <span>Server dependencies</span>
              </a>
            </li>
            <li id="nav-mysql-configuration" class="nav-item">
              <a class="nav-link" data-siteid=5 data-nav-target="nav-wallet" href="#">
                <i class="fas fa-database"></i>
                <span>Mysql configuration</span>
              </a>
            </li>
            <li id="nav-websocket-configuration" class="nav-item">
              <a class="nav-link" data-siteid=6 data-nav-target="nav-farm" href="#">
                <i class="fas fa-network-wired"></i>
                <span>Websocket configuration</span>
              </a>
            </li>
            <li id="nav-webgui-user-configuration" class="nav-item">
              <a class="nav-link" data-siteid=7 data-nav-target="nav-harvester" href="#">
                <i class="fas fa-user"></i>
                <span>Webgui user</span>
              </a>
            </li>
            <li id="nav-complete-setup" class="nav-item">
              <a class="nav-link" data-siteid=7 data-nav-target="nav-harvester" href="#">
                <i class="fas fa-play"></i>
                <span>Complete setup</span>
              </a>
            </li>
            <?php }else if($process_update){ ?>
            <div class="sidebar-heading">
              Update
            </div>
            <li id="nav-updater-welcome-page" class="nav-item active">
              <a class="nav-link" data-siteid=2 data-nav-target="nav-nodes" href="#">
                <i class="fas fa-hand-sparkles"></i>
                <span>Overview</span>
              </a>
            </li>
            <li id="nav-updater-processing-page" class="nav-item">
              <a class="nav-link" data-siteid=2 data-nav-target="nav-nodes" href="#">
                <i class="fas fa-list-alt"></i>
                <span>Processing</span>
              </a>
            </li>
            <li id="nav-updater-finishing-page" class="nav-item">
              <a class="nav-link" data-siteid=2 data-nav-target="nav-nodes" href="#">
                <i class="fas fa-dot-circle"></i>
                <span>Finishing</span>
              </a>
            </li>
            <?php } ?>
          <hr class="sidebar-divider d-none d-md-block">
          <div class="text-center d-none d-md-inline">
              <button class="rounded-circle border-0" id="sidebarToggle"></button>
          </div>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column" style="overflow: hidden;">
            <div id="content" class="gui-mode-elem">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow gui-mode-elem">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item no-arrow mx-1">
                          <span class="nav-link">
                            <span id="wsstatus" data-connected="0" class="badge badge-secondary"></span>
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                            </span>
                          </span>
                        </li>
                        <li class="nav-item dropdown no-arrow">
                          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                              data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <span id="sitewrapperusername" class="mr-2 d-none d-lg-inline text-gray-600 small">Admin</span>
                              <img class="img-profile rounded-circle" src="../../frameworks/bootstrap/img/undraw_profile.svg">
                          </a>
                          <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#versionNotesModal">
                              <i class="fas fa-sticky-note fa-sm fa-fw mr-2 text-gray-400"></i>
                              Version Notes
                            </a>
                          </div>
                        </li>
                    </ul>
                </nav>
                <main>
                  <div id="messagecontainer" style="margin-top: 4em;">
                  </div>
                  <div class="container-fluid" id="sitecontent" style="overflow: auto;">
                    <!-- Project installation -->
                    <?php if($db_install){ ?>
                      <div id="welcome-page">
                        <div class="d-sm-flex align-items-center justify-content-between mb-4">
                            <h1 class="h3 mb-0 text-gray-800">Welcome to Chia Manager</h1>
                        </div>
                        <div class="row">
                          <div class="col">
                            <div class="card shadow mb-4">
                              <div class="card-body">
                                <p>It's really nice to see you. We are happy that you decided to use our Chia Infrastructur Management tool.</br>
                                We put much of our freetime into this project and tried to get out the most of your Chia infrastructure.</br>
                                The main goal of this project is to make monitoring and using of Chia and the needed nodes much easier.</p>
                                <p>But enough of big words - convince yourself!</p>
                                <hr>
                                <p>Which branch would you like to use?<p>
                                <select id="branch" name="branch">
                                  <!--<option value="main" selected>Stable</option>-->
                                  <!--<option value="staging">Testing</option>-->
                                  <option value="dev">Development</option>
                                </select>
                                <hr>
                                <button data-target="server-dependencies" data-myid="welcome-page" class="btn btn-success btn-user btn-block install-step" style="width: 25%;">Start installer</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div id="server-dependencies" style="display: none;">
                      <div class="d-sm-flex align-items-center justify-content-between mb-4">
                          <h1 class="h3 mb-0 text-gray-800">Server dependencies</h1>
                      </div>
                      <div class="row">
                        <div class="col">
                          <div class="card shadow mb-4">
                            <div class="card-body">
                              <?php
                                $server_dependencies = $system_update_api->checkServerDependencies();
                                $php_version = $server_dependencies["data"]["php-version"];
                                $php_modules = $server_dependencies["data"]["php-modules"];
                              ?>
                              <div id="dep-php-version" class='alert <?php echo ($php_version["status"] == 0 ? "alert-success" : "alert-danger"); ?>' role='alert'><?php echo $php_version["message"]; ?></div>
                              <div id="dep-php-modules" class='alert <?php echo ($php_modules["status"] == 0 ? "alert-success" : "alert-danger"); ?>' role='alert'><?php echo $php_modules["message"]; ?></div>

                              <button id="server-dependencies-button" data-target="mysql-configuration" data-myid="server-dependencies" class="btn btn-success btn-user btn-block install-step" style="width: 25%;" <?php echo (($php_version["status"] == 0 || $php_modules["status"]) > 0 ? "style='display: none;'" : ""); ?>>Next Step</button>
                              <?php if($php_version["status"] > 0 || $php_modules["status"] > 0){ ?>
                                <button id="recheck-dependencies" class="btn btn-success btn-user btn-block" style="float: right; width: 35%; margin: 0 auto;">Reload Page</button>
                              <?php } ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div id="mysql-configuration" style="display: none;">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Mysql configuration</h1>
                    </div>
                    <div class="row">
                      <div class="col">
                        <div class="card shadow mb-4">
                          <div class="card-body">
                            <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                              <label for="databasename" style="margin: 0;">Databasename</label>
                              <input id="db_name" type="text" name="databasename" class="form-control form-control-user" placeholder="chiamgmt_db" value="">
                            </div>
                            <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                              <label for="mysqluser" style="margin: 0;">Mysql user</label>
                              <input id="db_user" type="text" name="mysqluser" class="form-control form-control-user" placeholder="chiamgmt_user" value="">
                            </div>
                            <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                              <label for="mysqluser" style="margin: 0;">Mysql password</label>
                              <input id="db_password" type="password" name="mysqlpassword" class="form-control form-control-user" placeholder="password" value="">
                            </div>
                            <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                              <label for="mysqlhost" style="margin: 0;">Mysql Host</label>
                              <input id="db_host" type="text" name="mysqlhost" class="form-control form-control-user" placeholder="Mysql Host" value="">
                              <label for="mysqlhost" style="margin: 0;">localhost or ip:port</label>
                            </div>
                            <hr>
                            <button id="mysql-configuration-button" data-target="websocket-configuration" data-myid="mysql-configuration" class="btn btn-success btn-user btn-block install-step" style="width: 25%; display: none;">Next Step</button>
                            <button id="check-db-config" class="btn btn-primary btn-user btn-block" style="width: 25%;">Check</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div id="websocket-configuration" style="display: none;">
                  <div class="d-sm-flex align-items-center justify-content-between mb-4">
                      <h1 class="h3 mb-0 text-gray-800">Websocket configuration</h1>
                  </div>
                  <div class="row">
                    <div class="col">
                      <div class="card shadow mb-4">
                        <div class="card-body">
                          <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                            <label for="socket_protocol" style="margin: 0;">Socket path (Must be same as ProxyPass/ProxyPassReverse)</label>
                            <input id="socket_protocol" type="text" name="socket_protocol" class="form-control form-control-user" placeholder="/chiamgmt" value="/chiamgmt">
                          </div>
                          <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                            <label for="socket_local_port" style="margin: 0;">Socket local port (Must be same as ProxyPass/ProxyPassReverse)</label>
                            <input id="socket_local_port" type="number" name="socket_local_port" class="form-control form-control-user" placeholder="8443" value="8443">
                          </div>
                          <hr>
                          <button id="websocket-configuration-button" data-target="webgui-user-configuration" data-myid="websocket-configuration" class="btn btn-success btn-user btn-block install-step" style="width: 25%;">Next Step</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="webgui-user-configuration" style="display: none;">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                  <h1 class="h3 mb-0 text-gray-800">Webgui admin user</h1>
                </div>
                <div class="row">
                  <div class="col">
                    <div class="card shadow mb-4">
                      <div class="card-body">
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="gui-username" style="margin: 0;">GUI Username</label>
                          <input type="text" name="gui-username" class="form-control form-control-user" placeholder="admin" value="<?php echo (array_key_exists("gui-username", $_POST) ? $_POST["gui-username"] : "admin"); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="gui-username" style="margin: 0;">Your forename</label>
                          <input type="text" name="gui-forename" class="form-control form-control-user" placeholder="Max" value="<?php echo (array_key_exists("gui-forename", $_POST) ? $_POST["gui-forename"] : ""); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="gui-username" style="margin: 0;">Your lastname</label>
                          <input type="text" name="gui-lastname" class="form-control form-control-user" placeholder="mustermann" value="<?php echo (array_key_exists("gui-lastname", $_POST) ? $_POST["gui-lastname"] : ""); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="gui-username" style="margin: 0;">Your email</label>
                          <input type="email" name="gui-email" class="form-control form-control-user" placeholder="max@mustermann.com" value="<?php echo (array_key_exists("gui-email", $_POST) ? $_POST["gui-email"] : ""); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="gui-password" style="margin: 0;">Password</label>
                          <input id="gui-password" type="password" name="gui-password" class="form-control form-control-user" placeholder="Password" value="<?php echo (array_key_exists("gui-password", $_POST) ? $_POST["gui-password"] : ""); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <div class="form-group" style="margin-left: auto; margin-right: auto; width: 50%;">
                          <label for="repeat-gui-password" style="margin: 0;">Repeat Password</label>
                          <input id="repeat-gui-password" type="password" name="repeat-gui-password" class="form-control form-control-user" placeholder="Password" value="<?php echo (array_key_exists("repeat-gui-password", $_POST) ? $_POST["repeat-gui-password"] : ""); ?>" <?php echo ($config_setnextpage ? "disabled" : ""); ?>>
                        </div>
                        <hr>
                        <button id="webgui-user-button" data-target="complete-setup" data-myid="webgui-user-configuration" type="submit" class="btn btn-success btn-user btn-block install-step" style="width: 25%; display: none;">Finish setup</button>
                        <button id="validate-user-settings" type="submit" class="btn btn-success btn-user btn-block" style="width: 25%;">Validate</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div id="complete-setup" style="display: none;">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                  <h1 class="h3 mb-0 text-gray-800">Complete setup</h1>
                </div>
                <div class="row">
                  <div class="col">
                    <div class="card shadow mb-4">
                      <div class="card-body">
                        <div class="card">
                          <div id="setuplog" class="card-body" style="background-color: lightgrey; height: 10em; overflow: auto;">
                          </div>
                        </div>
                        <div id="finish-text" style="display: none;">
                          <h3>Congratulations!</h3>
                          <p>Your instance is now ready to go.<br>
                          Just press the "Finish" button to login to your instance.<br>
                          Have fun! Don't forget the last part:</p>
                          <div class="alert alert-warning" role="alert">
                            1. Copy the noncekey from the config.ini.php file (nonce_key) into the .htaccess file.
                          </div>
                        </div>
                        <hr>
                        <button id="complete-setup-install" type="submit" class="btn btn-primary btn-user btn-block" style="width: 25%;">Install</button>
                        <button id="complete-setup-finish" type="submit" class="btn btn-success btn-user btn-block" style="width: 25%; display: none;">Finish</button>
                        <a id="complete-setup-to-login" href="/" class="btn btn-success btn-user btn-block" style="width: 25%; display: none;">Go to login</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php }else if($process_update){ ?>
              <div id="updater-welcome-page">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                  <h1 class="h3 mb-0 text-gray-800">Chia Update Process</h1>
                </div>
                <div class="row">
                  <div class="col">
                    <div class="card shadow mb-4">
                      <div class="card-body">
                        <p>Welcome to the updater. This instance will be automatically backed up and updated.<br>
                        Just press <b>"Process Update"</b> and lean back.</p>
                        <hr>
                        <h5>Instance and update information</h5>
                        <p>Current version: <b id="instance_version"><?php echo "{$update_infos["localversion"]}"; ?></b><br>
                          Update to version: <b id="update_version"><?php echo "{$update_infos["remoteversion"]}"; ?></b><br>
                          Branch: <b id="instance_branch"><?php echo "{$update_infos["updatechannel"]}"; ?></b></p>
                        <hr>
                        <button id="process-update" data-target="process-update-page" data-myid="updater-welcome-page" class="btn btn-success btn-user btn-block install-step" style="width: 25%;">Process Update</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div id="process-update-page" style="display: none;">
              <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Processing updates</h1>
              </div>
              <div class="row">
                <div class="col">
                  <div class="card shadow mb-4">
                    <div class="card-body">
                      <h5 id="updater-writable"><i class="fas fa-spinner fa-spin"></i>&nbsp;Checking files writable</h5>
                      <p id="updater-writable-log" class="update-log"></p>
                      <h5 id="updater-maintenance-on"><i class="fas fa-hourglass-start"></i>&nbsp;Enabling maintenance mode</h5>
                      <p id="updater-maintenance-on-log" class="update-log"></p>
                      <h5 id="updater-websocket-off"><i class="fas fa-hourglass-start"></i>&nbsp;Stopping websocket server</h5>
                      <p id="updater-websocket-off-log" class="update-log"></p>
                      <h5 id="updater-backup"><i class="fas fa-hourglass-start"></i>&nbsp;Creating backup</h5>
                      <p id="updater-backup-log" class="update-log"></p>
                      <h5 id="updater-downloading"><i class="fas fa-hourglass-start"></i>&nbsp;Downloading files</h5>
                      <p id="updater-downloading-log" class="update-log"></p>
                      <h5 id="updater-extracting-moving"><i class="fas fa-hourglass-start"></i>&nbsp;Extracting and moving files</h5>
                      <p id="updater-extracting-moving-log" class="update-log"></p>
                      <h5 id="updater-adjusting-db"><i class="fas fa-hourglass-start"></i>&nbsp;Checking and adjusting Database</h5>
                      <p id="updater-adjusting-db-log" class="update-log"></p>
                      <h5 id="updater-set-version"><i class="fas fa-hourglass-start"></i>&nbsp;Updating config file</h5>
                      <p id="updater-set-version-log" class="update-log"></p>
                      <h5 id="updater-websocket-on"><i class="fas fa-hourglass-start"></i>&nbsp;Starting websocket server</h5>
                      <p id="updater-websocket-on-log" class="update-log"></p>
                      <h5 id="updater-maintenance-off"><i class="fas fa-hourglass-start"></i>&nbsp;Disable maintenance mode</h5>
                      <p id="updater-maintenance-off-log" class="update-log"></p>
                      <hr>
                      <button id="retry-update" class="btn btn-success btn-user btn-block install-step" style="width: 25%; display: none;">Retry</button>
                      <button id="update-finish-btn" data-target="updater-finish-page" data-myid="process-update-page" class="btn btn-success btn-user btn-block install-step" style="width: 25%;" disabled>Finish</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div id="updater-finish-page" style="display: none;">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Update finished</h1>
            </div>
            <div class="row">
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <p>This instance has been updated successfully. We hope you are happy with the new bug fixes and features.<br>
                    Just press the button "Go to instance" to hang on.<br></p>
                    <hr>
                    <a href="/" class="btn btn-success btn-user btn-block" style="width: 25%;">Go to instance</a>
                </div>
              </div>
            </div>
          </div>
        </div>
            <?php } ?>
            </div>
          </main>
        </div>
        <footer class="sticky-footer bg-white gui-mode-elem">
          <div class="container my-auto">
            <div class="copyright text-center my-auto">
              <span>Copyright &copy; ChiaMgmt Version. All rights reserved.</span>
            </div>
          </div>
        </footer>
      </div>
    </div>
    <a id="scrolltopagetop" class="scroll-to-top rounded" href="#">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Bootstrap core JavaScript-->
    <script nonce=<?php echo $default_nonce; ?> src="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/vendor/jquery/jquery.min.js"; ?>"></script>
    <script nonce=<?php echo $default_nonce; ?> src="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/vendor/bootstrap/__old/js/bootstrap.bundle.min.js"; ?>"></script>

    <!-- Custom scripts for all pages-->
    <script nonce=<?php echo $default_nonce; ?> src="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/frameworks/bootstrap/js/sb-admin-2.min.js"; ?>"></script>
    <script nonce=<?php echo $default_nonce; ?> src="<?php echo "https://{$_SERVER['SERVER_NAME']}/frontend/sites/installer_updater/js/installer_updater.js"; ?>"></script>
</body>

</html>
