<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Farm\Chia_Farm_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';
  include_once("functions.php");

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $servicesStates = $_GET["services_states"];
  $chia_farm_api = new Chia_Farm_Api();
  $farmData = $chia_farm_api->getFarmData()["data"];
?>
<div class="card mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary">Farm Overview</h6>
    <div class='dropdown no-arrow'>
      <a class='dropdown-toggle' href='#' role='button' id='farmMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
        <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
      </a>
      <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='farmMenu'>
        <div class='dropdown-header'>Actions:</div>
        <button id="refreshFarmInfo" class='dropdown-item wsbutton' href=''>Refresh</button>
      </div>
    </div>
  </div>
  <?php
    if(count($farmData) > 0){
      //$sizes = array("" => 1,"MiB" => 1,"GiB" => 1024,"TiB" => pow(1024,2),"PiB" =>  pow(1024,3),"EiB" =>  pow(1024,4));
      $hostchecks = "";
      $farmingstatus = "";
      $totalplotcount = 0;
      $totalsizeofplots = 0;

      foreach($farmData AS $nodeid => $nodedata){
        $serviceStates = getServiceStates($servicesStates[$nodeid], 3);
        $hostchecks .= "{$nodedata["hostname"]}:&nbsp;<span id='servicestatus_farmer_{$nodeid}' data-nodeid={$nodeid} class='badge nodestatus " . $serviceStates["statusicon"] . "'>" . $serviceStates["statustext"] . "</span><br>";
        $farmingstatus .= "{$nodedata["hostname"]}:&nbsp;<span id='farmingstatus_{$nodeid}' data-nodeid={$nodeid} class='badge farmerstatus " . ($nodedata['syncstatus'] == 2 ? "badge-success" : ($nodedata['syncstatus'] == 1 ? "badge-warning" : "badge-danger")) . "'>" . ($nodeid > 0 ? ($nodedata['syncstatus'] == 2 ? "Synced" : ($nodedata['syncstatus'] == 1 ? "Syncing" : "Not synced"))."&nbsp;" : "No data found") . "</span></br>";
        $totalplotcount += intval($nodedata["plot_count"]);
        $plotinfoexpl = explode(" ",$nodedata["total_size_of_plots"]);
        $totalsizeofplots += $nodedata["total_size_of_plots"];
      }

      $totalsizeofplots = format_spaces($totalsizeofplots);
  ?>
  <div class="card-body">
    <div class="row">
      <div class="col-xl-4 mb-4">
        <div class="row">
          <div class="col">
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Service / Node States</div>
                    <?php echo $hostchecks; ?>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-network-wired fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-4 mb-4">
        <div class="row">
          <div class="col">
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Farming status</div>
                    <?php echo $farmingstatus; ?>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-tasks fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6 mb-4">
        <div class="row">
          <div class="col">
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Plot count (all Farmer)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-900"><?php echo $totalplotcount; ?></div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-sitemap fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <div class="card border-left-primary shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total size of Plots (all Farmer)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-900"><?php echo $totalsizeofplots; ?></div>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-save fa-2x text-gray-300"></i>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
    }else{
  ?>
  <div class="card-body">
    <div class="row">
      <div class="col">
        <div class="card bg-warning text-white shadow">
          <div class="card-body">
            No farm data available. Please configure some nodes.
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>
</div>