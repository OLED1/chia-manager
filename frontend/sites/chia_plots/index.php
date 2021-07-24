<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Plots\Chia_Plots_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_plots_api = new Chia_Plots_Api();
  //$farmdata = $chia_farm_api->getFarmData();

  echo "<script> var siteID = 6; </script>";
  //echo "<script> var chiaFarmData = " . json_encode($farmdata["data"]) . "; </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Plots</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your plots and information about it.<br>
        If this project will pass all required security checks we will implement plotting options too.
      </div>
    </div>
  </div>
</div>
<h5>My Plots</h5>
<div id="harvesterplots">
  <div class="row">
    <div class="col">
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
          <h6 class='m-0 font-weight-bold text-primary'>Plots from host blablabla with id 666</h6>
          <div class='dropdown no-arrow'>
            <a id='dropdownMenuLink_<?php echo $nodeid; ?>' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
            </a>
            <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
                <div class='dropdown-header'>Actions:</div>
                <a data-node-id='666' class='dropdown-item refreshFarmInfo' href='#'>Refresh</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
              <table class="table table-bordered" id="plots_666" width="100%" cellspacing="0">
                  <thead>
                      <tr>
                        <th>K-Size</th>
                        <th>Plot Key</th>
                        <th>Pool Key</th>
                        <th>Filename</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                  </thead>
                  <tfoot>
                      <tr>
                        <th>K-Size</th>
                        <th>Plot Key</th>
                        <th>Pool Key</th>
                        <th>Filename</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                  </tfoot>
                  <tbody>
                  </tbody>
              </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_plots/js/chia_plots.js"?>></script>
