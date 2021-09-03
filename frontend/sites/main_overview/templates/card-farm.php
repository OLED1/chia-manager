<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Farm\Chia_Farm_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  include_once("functions.php");
  $chia_farm_api = new Chia_Farm_Api();
  $farmData = $chia_farm_api->getFarmData()["data"];

  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];
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
        <button id="refreshFarmInfo" class='dropdown-item wsbutton' href='#' onclick="refreshFarmInfo()">Refresh</button>
      </div>
    </div>
  </div>
  <?php
    if(count($farmData) > 0){
      $sizes = array("" => 1,"MiB" => 1,"GiB" => 1024,"TiB" => pow(1024,2),"PiB" =>  pow(1024,3),"EiB" =>  pow(1024,4));
      $hostchecks = "";
      $farmingstatus = "";
      $totalplotcount = 0;
      $totalsizeofplots = 0;

      foreach($farmData AS $nodeid => $nodedata){
        $serviceStates = getServiceStates($nodes_states, $nodeid, "Farmer");
        $hostchecks .= "{$nodedata["hostname"]}:&nbsp;<span id='servicestatus_farmer_{$nodeid}' data-nodeid={$nodeid} class='badge nodestatus " . $serviceStates["statusicon"] . "'>" . $serviceStates["statustext"] . "</span><br>";
        $farmingstatus .= "{$nodedata["hostname"]}:&nbsp;<span id='farmingstatus_{$nodeid}' data-nodeid={$nodeid} class='badge farmerstatus " . (!is_null($nodedata["farming_status"]) ? ($nodedata["farming_status"] == "Farming" ? "badge-success" : "badge-danger") : "badge-danger") . "'>" . (is_null($nodedata["farming_status"]) ? "No data found" : $nodedata["farming_status"]) . "</span></br>";
        $totalplotcount += intval($nodedata["plot_count"]);
        $plotinfoexpl = explode(" ",$nodedata["total_size_of_plots"]);
        $totalsizeofplots += floatval($plotinfoexpl[0]) * intval($sizes[$plotinfoexpl[1]]);
      }

      if($totalsizeofplots >= 1099511627776){
        $totalsizeofplots = $totalsizeofplots/pow(1024,4);
        $size = "EiB";
      }else if($totalsizeofplots >= 1073741824){
        $totalsizeofplots = $totalsizeofplots/pow(1024,3);
        $size = "PiB";
      }else if($totalsizeofplots >= 1048576){
        $totalsizeofplots = $totalsizeofplots/pow(1024,2);
        $size = "TiB";
      }else{
        $totalsizeofplots = $totalsizeofplots/(1024);
        $size = "GiB";
      }
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
                    <i class="fas fa-network-wired fa-3x text-gray-300"></i>
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
                    <i class="fas fa-tasks fa-3x text-gray-300"></i>
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
            <div class="card border-left-secondary shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Plot count (all Farmer)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalplotcount; ?></div>
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
            <div class="card border-left-dark shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total size of Plots (all Farmer)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo floatval($totalsizeofplots) . " {$size}";?></div>
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
