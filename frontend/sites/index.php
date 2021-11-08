<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Users\Users_Api;
  use ChiaMgmt\UserSettings\UserSettings_Api;
  use ChiaMgmt\System_Update\System_Update_Api;

  require __DIR__ . '/../../vendor/autoload.php';

  $system_update_api = new System_Update_Api();
  $system_update_state = $system_update_api->checkUpdateRoutine();

  if((array_key_exists("process_update", $system_update_state["data"]) && $system_update_state["data"]["process_update"]) || array_key_exists("db_install_needed", $system_update_state["data"])){
    echo "Location: http://{$_SERVER['SERVER_NAME']}/frontend/sites/installer_updater/";
    header("Location: http://{$_SERVER['SERVER_NAME']}/frontend/sites/installer_updater/");
  }

  $ini = parse_ini_file(__DIR__.'/../../backend/config/config.ini.php');
  $frontendurl = $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"];
  $login_api = new Login_Api();
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: {$frontendurl}/login.php");
  }

  $users_api = new Users_Api();
  $user_settings_api = new UserSettings_Api();

  $gui_mode = $user_settings_api->getGuiMode($_COOKIE["user_id"])["data"]["gui_mode"];
  $gui_mode_string = ($gui_mode == 1 ? "gui-mode-light" : "gui-mode-dark");

  $userData = array();
  if(array_key_exists("user_id", $_COOKIE)) $userData = $users_api->getOwnUserData($_COOKIE["user_id"]);

  echo "<script nonce={$ini["nonce_key"]}>
  var backend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["backend_url"]}';
  var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
  var websocket = '{$ini["socket_protocol"]}://{$ini["socket_domain"]}{$ini["socket_listener"]}';
  var authhash = '{$ini["web_client_auth_hash"]}';
  var userdata = " . json_encode($userData["data"]) . ";
  var userID = {$_COOKIE["user_id"]};
  var sessid = '{$_COOKIE["PHPSESSID"]}';
  var darkmode = {$gui_mode};
  var intervals = {};
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

    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $ini["frontend_url"]."/img/favicon.ico"?>">
    <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="32x32">
    <link rel="icon" type="image/png" href="<?php echo $ini["frontend_url"]."/img/favicon.png"?>" sizes="96x96">

    <!-- Custom fonts for this template-->
    <link href="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo $ini["frontend_url"]; ?>/css/google_fonts/nunito/nunito-font.css" rel="stylesheet">
    <!-- Custom styles for this template-->
    <link href="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $frontendurl; ?>/frameworks/bootstrap/css/sb-admin-2.min.css" rel="stylesheet">
    <link href="<?php echo $frontendurl; ?>/css/custom.css" rel="stylesheet">
    <link href="<?php echo $frontendurl; ?>/css/gui-modes/dark-mode.css" rel="stylesheet">
    <link href="<?php echo $frontendurl; ?>/frameworks/davidstutz-multiselect/css/bootstrap-multiselect.min.css" rel="stylesheet">
</head>

<body id="page-top" class="gui-mode-elem <?php echo $gui_mode_string; ?>" style="overflow: auto;">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion gui-mode-elem <?php echo $gui_mode_string; ?>" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="#">
              <span class="sidebar-brand-icon projectlogo"></span>
              <!--<div class="sidebar-brand-icon rotate-n-15">
                <i clas="projectlogo"></i>
                <i class="fas fa-project-diagram"></i>
              </div>-->
              <div class="sidebar-brand-text mx-1">Chia Manager</div>
            </a>

            <hr class="sidebar-divider my-0">

            <li id="nav-dasboard" class="nav-item active">
                <a class="nav-link" data-siteid=1 data-nav-target="nav-dasboard" href="/sites/main_overview/">
                  <i class="fas fa-fw fa-tachometer-alt"></i>
                  <span>Dashboard</span>
                </a>
            </li>
            <hr class="sidebar-divider">
            <div class="sidebar-heading">
                My Chia Infra
            </div>
            <li id="nav-nodes" class="nav-item">
              <a class="nav-link" data-siteid=2 data-nav-target="nav-nodes" href="/sites/nodes">
                <i class="fas fa-sitemap"></i>
                <span>Nodes</span>
              </a>
            </li>
            <li id="nav-infra-sysinfo" class="nav-item">
              <a class="nav-link" data-siteid=8 data-nav-target="nav-infra-sysinfo" href="/sites/chia_infra_sysinfo">
                <i class="fas fa-network-wired"></i>
                <span>Infra Sysinfo</span>
              </a>
            </li>
            <li id="nav-wallet" class="nav-item">
              <a class="nav-link" data-siteid=5 data-nav-target="nav-wallet" href="/sites/chia_wallet">
                <i class="fas fa-wallet"></i>
                <span>Wallet</span>
              </a>
            </li>
            <li id="nav-farm" class="nav-item">
              <a class="nav-link" data-siteid=6 data-nav-target="nav-farm" href="/sites/chia_farm">
                <i class="fas fa-industry"></i>
                <span>Farm</span>
              </a>
            </li>
            <li id="nav-harvester" class="nav-item">
              <a class="nav-link" data-siteid=7 data-nav-target="nav-harvester" href="/sites/chia_harvester">
                <i class="fas fa-hdd"></i>
                <span>Harvester</span>
              </a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                System
            </div>
            <li id="nav-users" class="nav-item">
              <a class="nav-link" data-siteid=4 data-nav-target="nav-users" href="/sites/users">
                <i class="fas fa-users-cog"></i>
                <span>Users</span>
              </a>
            </li>
            <li id="nav-system-settings" class="nav-item">
              <a class="nav-link" data-siteid=3 data-nav-target="nav-system-settings"href="/sites/system">
                <i class="fas fa-server"></i>
                <span>Settings</span>
              </a>
            </li>

            <hr class="sidebar-divider">

            <div class="sidebar-heading">
                Personal
            </div>
            <li id="nav-personal-settings" class="nav-item">
              <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePersonal"
                  aria-expanded="true" aria-controls="collapsePersonal">
                  <i class="fas fa-user-cog"></i>
                  <span>Personal</span>
              </a>
              <div id="collapsePersonal" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                  <div class="bg-white py-2 collapse-inner rounded">
                      <a class="collapse-item" data-siteid=5 data-nav-target="nav-personal-settings" href="/sites/usersettings"><span>Profile & Settings</span></a>
                      <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">Logout</a>
                  </div>
              </div>
            </li>

            <hr class="sidebar-divider d-none d-md-block">

            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

        </ul>
        <div id="content-wrapper" class="d-flex flex-column" style="overflow: hidden;">
            <div id="content" class="gui-mode-elem <?php echo $gui_mode_string; ?>">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow gui-mode-elem <?php echo $gui_mode_string; ?>">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <!--<a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>-->
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                              <form class="form-inline mr-auto w-100 navbar-search">
                                <div class="input-group">
                                  <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
                                  <div class="input-group-append">
                                    <button class="btn btn-primary" type="button">
                                        <i class="fas fa-search fa-sm"></i>
                                    </button>
                                  </div>
                                </div>
                              </form>
                            </div>
                        </li>
                        <li class="nav-item no-arrow mx-1">
                          <span id="wssloading" class="nav-link" style="display: none;">
                            <span class="wssloadingcount badge badge-secondary badge-counter" style="z-index: 1;">0</span>
                            <div class="wssloadingspinner spinner-border text-secondary" role="status">
                              <span class="sr-only">Loading...</span>
                            </div>
                          </span>
                        </li>
                        <li class="nav-item no-arrow mx-1">
                          <span class="nav-link">
                            <span id="wsstatus" data-connected="0" class="badge badge-secondary"></span>
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                            </span>
                          </span>
                        </li>
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bell fa-fw"></i>
                                <span id="alerts-counter" class="badge badge-danger badge-counter">0</span>
                            </a>
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="alertsDropdown">
                                <h6 class="dropdown-header">
                                    Alerts Center
                                </h6>
                                <div id="alerts" style="max-height: 20em; overflow: auto;">
                                </div>
                                <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
                            </div>
                        </li>
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="sitewrapperusername" class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo $userData["data"]["name"] . " " . $userData["data"]["lastname"]; ?></span>
                                <img class="img-profile rounded-circle" src="../frameworks/bootstrap/img/undraw_profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" data-siteid=5 data-nav-target="nav-personal-settings"href="/sites/usersettings">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    <span>Profile & Settings</span>
                                </a>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#versionNotesModal">
                                    <i class="fas fa-sticky-note fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Version Notes
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>
                </nav>
                <main>
                  <div id="messagecontainer" style="margin-top: 4em;">
                  </div>
                  <div class="container-fluid" id="sitecontent" style="overflow: auto;">
                  </div>
                </main>
            </div>
            <footer class="sticky-footer bg-white gui-mode-elem <?php echo $gui_mode_string; ?>">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; ChiaMgmt Version <?php echo $ini["versnummer"]; ?>. All rights reserved.</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    <a id="scrolltopagetop" class="scroll-to-top rounded" href="#">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a id="logout" class="btn btn-primary logout" href="#">Logout</a>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="versionNotesModal" tabindex="-1" role="dialog" aria-labelledby="versionNotesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 40em;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="versionNotesModalLabel">Version Notes</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                  <h4 style="text-align: center;">Chia infrastructur management and monitoring tool</h4>
                  <img src="../img/chia_coin_logo.png" alt="Chia Logo" class="chialogo">
                  <h5 style="text-align: center;">Current project version: <?php echo $ini["versnummer"]; ?></h5>
                  <p style="text-align: center;">Idea and programming by <strong>OLED1.</strong><br>
                  Thanks for contribution to <strong>LucaAust.</strong></p>
                  <br>
                  <p>
                    Find source code for the webgui on github:&nbsp;<a class="externallink"  target="_blank" href="https://github.com/OLED1/chia-web-gui">Here</a><br>
                    Find source code for the node client on github:&nbsp;<a class="externallink"  target="_blank" href="https://github.com/OLED1/chia-node-client">Here</a>
                  </p>
                  <p>
                    Backend programmed in PHP (Version 7.4.3) with MySQL (Version 8.0.26).<br>
                    Backend uses following third party software:<br>
                    cboden/ratchet (Version 0.4.3), amphp/websocket-client (Version 1.0), phpmailer/phpmailer (Version 6.4), amphp/amp (Version 2.5) and tiben/crontab-manager (Version 1.0).<br>
                    This project supports and uses composer.<br>
                    Frontend uses following third party software:<br>
                    jQuery (v3.6.0), Bootstrap (5.0.2), ChartJS (v2.9.4), Datatables (1.10.24), FontAwesome (5.15.3), David Stutz Multiselect (Version 2.0), Design SB Admin 2 (v4.1.3).<br>
                    Node client programmed in Python (Version 3.9.6). Thanks to <strong>LucaAust</strong> for supporting.
                  </p>
                  <p>This project is licensed under the <a class="externallink" target="_blank" href="https://www.gnu.org/licenses/gpl-3.0.en.html">GNU General Public License v3.0.</a></p>
                  <p>
                    <strong>Change Notes for this version:</strong><br>
                    This is the first release of the ChiaMgmt tool.<br> At the moment it is readonly for security reasons.<br>
                    - First implementation of everything you can see
                  </p>
                  <p>
                    <strong>Upcomming changes for v0.02:</strong><br>
                    - Pooldata in Version v0.02 by using plotnft command<br>
                    - Some more graphs in v0.02<br>
                    - A third factor per authenticator in v0.02<br><br>
                    - Did you find any bugs? Tell me!<br>
                    - Do you wish more features in v0.02? Tell me!<br>
                    - Do you have some ideas or enhancements? Tell me!<br><br>
                    - After a professional security check, real management will be implemented<br>
                  </p>
                  <p>This project is open source and free and it will be forever.<br>
                  But if you want to support us and this project you can contribute some Mojos to this address: (Coming soon).</p>
                  <p><strong>External data</strong><br>
                    External data like the current chia netspace or dollar price is provided by&nbsp;<a class="externallink"  target="_blank" href="https://xchscan.com/">xchscan.com</a>.
                  </p>
                  <p><strong>Disclaimer</strong><br>
                    CHIA NETWORK INC, CHIA™, the CHIA BLOCKCHAIN™, the CHIA PROTOCOL™, CHIALISP™ and the “leaf Logo” (including the leaf logo alone when it refers to or indicates Chia), are trademarks or registered trademarks of Chia Network, Inc., a Delaware corporation. There is no affiliation between this Chia Mgmt project and the main Chia Network project.
                  </p>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-secondary" type="button" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="maintenance_mode_modal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 30em;">
        <div class="modal-content">
          <div class="modal-body">
            <div class="p-5">
              <div class="text-center">
                <i class="fas fa-hard-hat 9px" style="font-size: 3em"></i>
                <h2>Maintenance</h2>
                <p>This instance is currently in maintenance mode.<br>
                The site will be reloaded as soon as the maintenance ends.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
      <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <div class="modal-content" style="background: transparent; border-color: transparent;">
          <div class="modal-body">
            <div class="text-center">
              <i class="fas fa-spinner fa-spin fa-9x"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/bootstrap/__old/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/davidstutz-multiselect/js/bootstrap-multiselect.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/datatables/jquery.dataTables.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/datatables/dataTables.bootstrap4.min.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/bootstrap/vendor/chart.js/Chart.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/frameworks/momentjs/moment-with-locales.min.js"></script>

    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/js/sitewrapper/load_pages.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/js/sitewrapper/transfer.js"></script>
    <script nonce=<?php echo $ini["nonce_key"]; ?> src="<?php echo $frontendurl; ?>/js/sitewrapper/sitewrapper.js"></script>
</body>

</html>
