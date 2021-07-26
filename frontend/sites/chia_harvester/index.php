<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Harvester\Chia_Harvester_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_harvester_api = new Chia_Harvester_Api();
  //$farmdata = $chia_farm_api->getFarmData();

  echo "<script> var siteID = 6; </script>";
  //echo "<script> var chiaFarmData = " . json_encode($farmdata["data"]) . "; </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Harvester</h1>
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
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query harvester information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h4>My Plots</h4>
<div id="harvesterplots">
  <div class="row">
    <div class="col">
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
          <h6 class='m-0 font-weight-bold text-primary'>Plots from host blablabla with id 666&nbsp;<span id='servicestatus_666' class='badge badge-secondary'>Querying service status</span></h6>
          <div class='dropdown no-arrow'>
            <a id='dropdownMenuLink_<?php echo $nodeid; ?>' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
            </a>
            <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
                <div class='dropdown-header'>Actions:</div>
                <a data-node-id='666' class='dropdown-item refreshHarvesterInfo' href='#'>Refresh</a>
                <a data-node-id='<?php echo $nodeid; ?>' class='dropdown-item refreshHarvesterService' href='#'>Restart harvester service</a>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col">
              <div class="card shadow mb-4">
                <div class="card-body">
                  <h6>Configured plot directories</h6>
                  <h4 class="small font-weight-bold">/mnt/bla1 (Size: 13TB)<span
                          class="float-right">20%</span></h4>
                  <div class="progress mb-4">
                      <div class="progress-bar bg-success" role="progressbar" style="width: 20%"
                          aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">2,1TB - 21 Plots</div>
                  </div>
                  <h4 class="small font-weight-bold">/mnt/bla2 (Size: 13TB)<span
                          class="float-right">30%</span></h4>
                  <div class="progress mb-4">
                      <div class="progress-bar bg-success" role="progressbar" style="width: 30%"
                          aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">3,1TB - 31 Plots</div>
                  </div>
                  <h4 class="small font-weight-bold">/mnt/bla3 (Size: 13TB)<span
                          class="float-right">40%</span></h4>
                  <div class="progress mb-4">
                      <div class="progress-bar bg-success" role="progressbar" style="width: 40%"
                          aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">4,1TB - 41 Plots</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col">
              <div class="card shadow mb-4">
                <div class="card-body">
                  <h6>Found plots</h6>
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
      </div>
    </div>
  </div>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_plots/js/chia_plots.js"?>></script>
