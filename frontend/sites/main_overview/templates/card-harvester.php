<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Harvester\Chia_Harvester_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';
  include_once("functions.php");

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $servicesStates = $_GET["services_states"];
  $chia_harvester_api = new Chia_Harvester_Api();
  $harvesterData = $chia_harvester_api->getHarvesterData()["data"];
?>
<div class="card mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
      <h6 class="m-0 font-weight-bold text-primary">Harvester Overview</h6>
      <div class='dropdown no-arrow'>
        <a class='dropdown-toggle' href='#' role='button' id='harvesterMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
          <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
        </a>
        <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='harvesterMenu'>
          <div class='dropdown-header'>Actions:</div>
          <button id="refreshHarvesterInfo" class='dropdown-item wsbutton' href=''>Refresh</button>
        </div>
      </div>
  </div>
  <?php
  if(count($harvesterData) > 0){
    $hostchecks = "";
    $criticalmount = "";
    foreach($harvesterData AS $nodeid => $nodedata){
      $serviceStates = getServiceStates($servicesStates[$nodeid], 4);
      $hostchecks .= "{$nodedata["hostname"]}:&nbsp;<span id='servicestatus_harvester_{$nodeid}' data-nodeid={$nodeid} class='badge nodestatus " . $serviceStates["statusicon"] . "'>" . $serviceStates["statustext"] . "</span><br>";
      $nodes = array();
      
      foreach($nodedata["plotdirs"] AS $mountpoint => $plotdir){
        if(!in_array($nodedata["hostname"], $nodes)){
          $criticalmount .= "{$nodedata["hostname"]}:<br>";
          array_push($nodes, $nodedata["hostname"]);
        }
        if(array_key_exists("mount_device", $plotdir) && is_null($plotdir["mount_device"])){
          $criticalmount .= "<span id='plot_crit_{$nodeid}_{$mountpoint}' data-nodeid={$nodeid} class='badge harvesterstatus badge-danger'>{$mountpoint}</span></br>";
        }else{
          $criticalmount .= "<span id='plot_crit_{$nodeid}_no_plot_dirs_found' data-nodeid={$nodeid} class='badge harvesterstatus badge-danger'>No plotdirectories are configured</span></br>";
        }
      }
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
            <div class="card border-left-danger shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Not mounted directories</div>
                    <?php echo $criticalmount; ?>
                  </div>
                  <div class="col-auto">
                    <i class="fas fa-database fa-2x text-gray-300"></i>
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
            No harvester data available. Please configure some nodes.
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>
</div>