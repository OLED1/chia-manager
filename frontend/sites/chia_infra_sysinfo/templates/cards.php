<?php
  use React\Promise;
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || ! array_key_exists("user_id", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');

  $alerting_api = new Alerting_Api();
  $site_infos_to_load = [
    Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    Promise\resolve((new Chia_Infra_Sysinfo_Api())->getSystemInfo()),
    Promise\resolve($alerting_api->getConfigurableDowntimeServices(["monitor" => 1])),
    Promise\resolve($alerting_api->getSetupDowntimes()),
    Promise\resolve($alerting_api->getConfigurableDowntimeServices()),
  ];

  Promise\all($site_infos_to_load)->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    
    $sysinfos = $all_returned[1];
    $configureable_downtimes = $all_returned[2];
    $found_downtimes = $all_returned[3];
    $monitored_services = $all_returned[4];

    if(array_key_exists("data", $configureable_downtimes)) $configureable_downtimes = $configureable_downtimes["data"];
    else $configureable_downtimes = [];
    
    if(array_key_exists("data", $found_downtimes)) $found_downtimes = $found_downtimes["data"];
    else $found_downtimes = [];
    
    if(array_key_exists("data", $monitored_services)) $monitored_services = $monitored_services["data"];
    else $monitored_services = [];
      
    if(array_key_exists("data", $sysinfos) && count($sysinfos["data"]) > 0){
      echo 
      "<script nonce={$ini["nonce_key"]}> 
        var sysinfodata = " . json_encode($sysinfos["data"]) . "; 
        var configureable_downtimes = " . json_encode($configureable_downtimes) . ";
        var found_downtimes = " . json_encode($found_downtimes) . ";
        var monitored_services = " . json_encode($monitored_services) . ";
      </script>";

      echo "<pre>";
      print_r($sysinfos["data"][55]);
      echo "</pre>";

      $first = true;
      echo "<ul class='nav nav-tabs' role='tablist'>";
      foreach($sysinfos["data"] AS $nodeid => $sysinfo){
        echo "<li class='nav-item' role='presentation'>
                <a class='nav-link " . ($first ? "active" : "") . " node-tab' id='node-tab-$nodeid' data-toggle='tab' href='#node-$nodeid' role='tab' aria-controls='node-$nodeid' aria-selected='true'>{$sysinfo["node"]["hostname"]}</a>
              </li>";
        $first = false;
      }
      echo "</ul>";
      echo "<div class='tab-content'>";

      $first = true;

      include("functions.php");
      
      foreach($sysinfos["data"] AS $nodeid => $sysinfo){
        /*echo "<pre>";
        print_r($sysinfo);
        echo "</pre>";*/

        if(!array_key_exists("cpu", $sysinfo) && !array_key_exists("memory", $sysinfo) && !array_key_exists("filesystem", $sysinfo)){
?>
  <div class='row tab-pane fade show <?php echo ($first ? "active" : ""); ?>' id="node-<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="node-tab-<?php echo $nodeid; ?>"'>
    <?php $first = false; ?>
    <div class='col'>
      <div class='card shadow mb-4'>
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
          <h6 class="m-0 font-weight-bold text-primary">
            Systeminformation Node <bold><?php echo "{$sysinfo["node"]["hostname"]}"; ?></bold>
            <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
            <?php echo ($sysinfo["node"]["data_current"] ? "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["node"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?> 
          </h6>
        </div>
        <div class="card-body">
          <div class="card shadow mb-4">
            <div class="card-body">
              There is currently no sysinfo data to show. <br>
              Please try to rescan all data on the nodes page by pressing the button "Query all available information from all nodes".
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php 
          continue;
        } 
  ?>
  <div class='row tab-pane fade show <?php echo ($first ? "active" : ""); ?>' id="node-<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="node-tab-<?php echo $nodeid; ?>"'>
    <?php $first = false; ?>
    <div class='col'>
      <div class='card shadow mb-4'>
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
          <h6 class="m-0 font-weight-bold text-primary">Systeminformation Node <bold><?php echo "{$sysinfo["node"]["hostname"]}"; ?></bold>
            <?php if(!array_key_exists("cpu", $sysinfo) || !is_numeric($sysinfo["cpu"]["load"]["load_1_min"])){ ?>
              <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
              <?php
                }else{
              ?>
              <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-secondary'>Processing...</span>
              <?php echo getWARNLevelBadge($sysinfo["node"]["upstatus"], $sysinfo["node"]["downtime_active"], 0); ?>      
            <?php } ?>
            <?php echo (!$sysinfo["node"]["data_current"] ? "<span class='badge statusbadge badge-warning node-details-badge' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["node"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?>
          </h6>
          <div class="dropdown no-arrow">
            <a class="dropdown-toggle" href="#" role="button" id="sysinfodropdown<?php echo "{$nodeid}"; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i></a>
            <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="sysinfodropdown<?php echo "{$nodeid}"; ?>">
              <div class="dropdown-header">Actions:</div>
              <button class="sysinfo-refresh dropdown-item wsbutton" data-nodeid="<?php echo "{$nodeid}"; ?>" href="#">Refresh Data</button>
              <button class="sysinfo-edit-services dropdown-item wsbutton" data-nodeid="<?php echo "{$nodeid}"; ?>" href="#">Edit monitored services</button>
              <button class="sysinfo-set-downtime dropdown-item wsbutton" data-nodeid="<?php echo "{$nodeid}"; ?>" href="#">Downtimes</button>
              <!--<button class="sysinfo-acknowledge-messages dropdown-item wsbutton" data-nodeid="<?php echo "{$nodeid}"; ?>" href="#">Acknowledge services</button>-->
            </div>
          </div>
        </div>
        <div class="card-body">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <a class="nav-link active" id="overview-tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#overview-<?php echo $nodeid; ?>" role="tabpanel" role="tab" aria-controls="overview-<?php echo $nodeid; ?>" aria-selected="true">Overview</a>
            </li>
            <li class="nav-item" role="presentation">
              <a class="nav-link" id="details-tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#details-<?php echo $nodeid; ?>" role="tab" aria-controls="details-<?php echo $nodeid; ?>" aria-selected="false">Details</a>
            </li>
          </ul>
          <div class="tab-content">
            <!-- Overview Pane -->
            <div class="tab-pane fade show node-details-pane active" id="overview-<?php echo $nodeid; ?>" data-node-id="<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="overview-tab-<?php echo $nodeid; ?>">
            <div class="row">
                <div id="services_overview_<?php echo $nodeid; ?>" class="col py-2">
                  <!-- Header -->
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_all" aria-label="Checkbox for following text input">
                      </div>
                      <span class="input-group-text service-state">State</span>
                      <span class="input-group-text service-name">Service</span>
                    </div>
                    <div class="input-group-prepend">
                      <span class="input-group-text service-notes">Notes</span>
                      <span class="input-group-text service-summary">Summary</span>
                      <span class="input-group-text service-state-since">State since</span>
                      <span class="input-group-text service-last-checked">Checked</span>
                      <span class="input-group-text service-workload">Workload</span>
                    </div>
                  </div>
                  <!-- Services -->
                  <!-- Server downtime -->
                  <?php 
                    if(array_key_exists("node", $sysinfo)){
                      $node = $sysinfo["node"];
                  ?>
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $node["service_id"]; ?>" data-service-type="<?php echo $node["service_type"]; ?>">
                      </div>
                      <?php 
                        echo getWARNLevelBadge($node["upstatus"], $node["downtime_active"], 1); 
                      ?>
                      <span class="input-group-text service-name">Server upstatus</span>
                    </div>
                    <div class="input-group-prepend">
                      <span class="input-group-text service-notes">
                        <?php
                          if($node["downtime_active"]) echo "<span class='badge badge-primary' data-toggle='tooltip' data-placement='top' title='This service is currently in downtime.'><i class='fa-solid fa-pause'></i></span>&nbsp;";
                          if(!$node["data_current"]) echo "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$node["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>";
                        ?>
                      </span>
                      <span class="input-group-text service-summary"><?php echo "{$node["service_desc"]}: " . ($node["upstatus"] == 1 ? "Up and running." : "Currently not reachable."); ?></span>
                      <span class="input-group-text service-state-since"><?php echo $node["state_first_reported"]; ?></span>
                      <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($node["state_last_reported"]); ?></span>
                      <span class="input-group-text service-workload">
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo ($node["upstatus"] == 1 ? "100" : "0"); ?>%;" role="progressbar" aria-valuenow="<?php echo ($node["upstatus"] == 1 ? "100" : "0"); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                      </span>
                    </div>
                  </div>
                  <!-- CPU Load -->
                  <?php 
                    }
                    if(array_key_exists("os", $sysinfo) && $sysinfo["os"]["os_type"] == "Linux"){
                      $cpu_load = $sysinfo["cpu"]["load"];
                      $cpu_info = $sysinfo["cpu"]["info"]; 
                  ?>
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $cpu_load["service_id"]; ?>" data-service-type="<?php echo $cpu_load["service_type"]; ?>">
                      </div>
                      <?php 
                        echo getWARNLevelBadge($cpu_load["service_state"], $cpu_load["downtime_active"], 1); 
                      ?>
                      <span class="input-group-text service-name">CPU Load</span>
                    </div>
                    <div class="input-group-prepend">
                      <span class="input-group-text service-notes">
                        <?php
                          if($cpu_load["downtime_active"]) echo "<span class='badge badge-primary' data-toggle='tooltip' data-placement='top' title='This service is currently in downtime.'><i class='fa-solid fa-pause'></i></span>&nbsp;";
                          if(!$cpu_load["data_current"]) echo "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$node["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>";
                        ?>
                      </span>
                      <span class="input-group-text service-summary"><?php echo "{$cpu_load["service_desc"]}: " . number_format($cpu_load["load_15_min"], 2) . " at {$cpu_info["cpu_count"]} Cores (" . number_format($cpu_load["load_15_min"] / $cpu_info["cpu_count"], 2) . " per Core)"; ?></span>
                      <span class="input-group-text service-state-since"><?php echo $cpu_load["state_first_reported"]; ?></span>
                      <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($cpu_load["state_last_reported"]); ?></span>
                      <span class="input-group-text service-workload">
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo $cpu_load["usage_15_min"]; ?>%;" role="progressbar" aria-valuenow="<?php echo $cpu_load["usage_15_min"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                      </span>
                    </div>
                  </div>
                  <!-- CPU Usage/Utilisation -->
                  <?php 
                    } 
                    if(array_key_exists("cpu", $sysinfo)){
                      $cpu_usage_overall = $sysinfo["cpu"]["usage"]["overall"];
                  ?>
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $cpu_usage_overall["service_id"]; ?>" data-service-type="<?php echo $cpu_usage_overall["service_type"]; ?>">
                      </div>
                      <?php echo getWARNLevelBadge($cpu_usage_overall["service_state"], $cpu_usage_overall["downtime_active"], 1); ?>
                      <span class="input-group-text service-name">CPU Utilisation</span>
                    </div>
                    <div class="input-group-prepend">
                      <span class="input-group-text service-notes">
                      </span>
                      <span class="input-group-text service-summary"><?php echo "CPU total usage: {$cpu_usage_overall["total_usage"]}%" ?></span>
                      <span class="input-group-text service-state-since"><?php echo $cpu_usage_overall["state_first_reported"]; ?></span>
                      <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($cpu_usage_overall["state_last_reported"]); ?></span>
                      <span class="input-group-text service-workload">
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo $cpu_usage_overall["total_usage"]; ?>%;" role="progressbar" aria-valuenow="<?php echo $cpu_usage_overall["total_usage"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                      </span>
                    </div>
                  </div>
                  <!-- RAM Usage -->
                  <?php 
                    }
                    if(array_key_exists("memory", $sysinfo) && array_key_exists("ram", $sysinfo["memory"]) && floatval($sysinfo["memory"]["ram"]["memory_total"]) > 0){
                      $memory_ram = $sysinfo["memory"]["ram"];
                  ?>
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $memory_ram["service_id"]; ?>" data-service-type="<?php echo $memory_ram["service_type"]; ?>">
                      </div>
                      <?php echo getWARNLevelBadge($memory_ram["service_status"], $memory_ram["downtime_active"], 1); ?>
                      <span class="input-group-text service-name">RAM usage</span>
                    </div>
                    <div class="input-group-prepend">
                      <?php
                        $memorytotal = intval($memory_ram["memory_total"]);
                        $memoryfree = intval($memory_ram["memory_free"]) + intval($memory_ram["memory_buffers"]) + intval($memory_ram["memory_cached"]) - intval($memory_ram["memory_shared"]);
                        $memoryused = floatval(number_format(($memorytotal - $memoryfree)/1024/1024/1024, 2));
                        $memoryusedperc = number_format(($memoryused / ($memorytotal/1024/1024/1024)) * 100, 2);
                      ?>
                      <span class="input-group-text service-notes">
                      </span>
                      <span class="input-group-text service-summary"><?php echo "{$memory_ram["service_desc"]}: {$memoryused}GB of " . formatkBytes($memorytotal) . " ({$memoryusedperc}%)"; ?></span>
                      <span class="input-group-text service-state-since"><?php echo $memory_ram["state_first_reported"]; ?></span>
                      <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($memory_ram["state_last_reported"]); ?></span>
                      <span class="input-group-text service-workload">
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo $memoryusedperc; ?>%;" role="progressbar" aria-valuenow="<?php echo $memoryusedperc; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                      </span>
                    </div>
                  </div>
                  <!-- SWAP Usage -->
                  <?php
                    }
                    if(array_key_exists("memory", $sysinfo) && array_key_exists("swap", $sysinfo["memory"]) && floatval($sysinfo["memory"]["swap"]["swap_total"]) > 0){
                      $memory_swap = $sysinfo["memory"]["swap"];
                  ?>
                  <div class="input-group input-group">
                    <div class="input-group-prepend">
                      <div class="input-group-text check-service">
                        <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $memory_swap["service_id"]; ?>" data-service-type="<?php echo $memory_swap["service_type"]; ?>">
                      </div>
                      <?php echo getWARNLevelBadge($memory_ram["service_status"], $memory_ram["downtime_active"], 1); ?>
                      <span class="input-group-text service-name">SWAP usage</span>
                    </div>
                    <div class="input-group-prepend">
                      <?php
                        $swapused = number_format((intval($memory_swap["swap_total"]) - intval($memory_swap["swap_free"]))/1024/1024/1024, 2);
                        $swapusedperc = number_format(($swapused / ($memory_swap["swap_total"]/1024/1024/1024)) * 100, 2);
                      ?>
                      <span class="input-group-text service-notes">
                      </span>
                      <span class="input-group-text service-summary"><?php echo "{$memory_swap["service_desc"]}: {$swapused}GB of " . formatkBytes($memory_swap["swap_total"]) . " ({$swapusedperc}%)"; ?></span>
                      <span class="input-group-text service-state-since"><?php echo $memory_swap["state_first_reported"]; ?></span>
                      <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($memory_swap["state_last_reported"]); ?></span>
                      <span class="input-group-text service-workload">
                        <div class="progress">
                          <div class="progress-bar" style="width: <?php echo $swapusedperc; ?>%;" role="progressbar" aria-valuenow="<?php echo $swapusedperc; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                      </span>
                    </div>
                  </div>
                  <!-- Filesystems Usage -->
                  <?php
                    }
                    if(array_key_exists("filesystems", $sysinfo)){
                      foreach($sysinfo["filesystems"] AS $mountpoint => $filesystem){ 
                  ?>
                    <div class="input-group input-group">
                      <div class="input-group-prepend">
                        <div class="input-group-text check-service">
                          <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $filesystem["service_id"]; ?>" data-service-type="<?php echo $filesystem["service_type"]; ?>">
                        </div>
                        <?php echo getWARNLevelBadge($filesystem["service_status"], $filesystem["downtime_active"], 1); ?>
                        <span class="input-group-text service-name"><?php echo "fs_{$filesystem["mountpoint"]}"; ?></span>
                      </div>
                      <div class="input-group-prepend">
                        <span class="input-group-text service-notes">
                        </span>
                        <span class="input-group-text service-summary"><?php echo "{$filesystem["service_desc"]}: " . formatkBytes($filesystem["used"]) . " of " . formatkBytes($filesystem["size"]) . " ({$filesystem["total_usage"]}%)"; ?></span>
                        <span class="input-group-text service-state-since"><?php echo $filesystem["state_first_reported"]; ?></span>
                        <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($filesystem["state_last_reported"]); ?></span>
                        <span class="input-group-text service-workload">
                          <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $filesystem["total_usage"]; ?>%;" role="progressbar" aria-valuenow="<?php echo $filesystem["total_usage"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </span>
                      </div>
                    </div>
                  <?php
                      }
                    }
                    if(array_key_exists("farmer", $sysinfo)){
                      $farmer_service = $sysinfo["farmer"];
                  ?>
                    <div class="input-group input-group">
                      <div class="input-group-prepend">
                        <div class="input-group-text check-service">
                          <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $farmer_service["service_id"]; ?>" data-service-type="<?php echo $farmer_service["service_type"]; ?>">
                        </div>
                        <?php echo getWARNLevelBadge($farmer_service["service_state"], $farmer_service["downtime_active"], 1); ?>
                        <span class="input-group-text service-name"><?php echo "{$farmer_service["service_desc"]}"; ?></span>
                      </div>
                      <div class="input-group-prepend">
                        <span class="input-group-text service-notes">
                        </span>
                        <span class="input-group-text service-summary"><?php echo "{$farmer_service["service_desc"]}: The service is currently " . ($farmer_service["service_state"] == 1 ? "running" : "not running") . "."; ?></span>
                        <span class="input-group-text service-state-since"><?php echo $farmer_service["state_first_reported"]; ?></span>
                        <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($farmer_service["state_last_reported"]); ?></span>
                        <span class="input-group-text service-workload">
                          <div class="progress">
                            <div class="progress-bar" style="width: <?php echo ($farmer_service["service_state"] == 1 ? "100" : 0) ?>%;" role="progressbar" aria-valuenow="<?php echo ($farmer_service["service_state"] == 1 ? "100" : 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </span>
                      </div>
                    </div> 
                  <?php
                    }
                    if(array_key_exists("harvester", $sysinfo)){
                      $harvester_service = $sysinfo["harvester"];
                  ?>
                    <div class="input-group input-group">
                      <div class="input-group-prepend">
                        <div class="input-group-text check-service">
                          <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $harvester_service["service_id"]; ?>" data-service-type="<?php echo $harvester_service["service_type"]; ?>">
                        </div>
                        <?php echo getWARNLevelBadge($harvester_service["service_state"], $harvester_service["downtime_active"], 1); ?>
                        <span class="input-group-text service-name"><?php echo "{$harvester_service["service_desc"]}"; ?></span>
                      </div>
                      <div class="input-group-prepend">
                        <span class="input-group-text service-notes">
                        </span>
                        <span class="input-group-text service-summary"><?php echo "{$harvester_service["service_desc"]}: The service is currently " . ($harvester_service["service_state"] == 1 ? "running" : "not running") . "."; ?></span>
                        <span class="input-group-text service-state-since"><?php echo $harvester_service["state_first_reported"]; ?></span>
                        <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($harvester_service["state_last_reported"]); ?></span>
                        <span class="input-group-text service-workload">
                          <div class="progress">
                            <div class="progress-bar" style="width: <?php echo ($harvester_service["service_state"] == 1 ? "100" : 0) ?>%;" role="progressbar" aria-valuenow="<?php echo ($harvester_service["service_state"] == 1 ? "100" : 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </span>
                      </div>
                    </div> 
                  <?php
                    }
                    if(array_key_exists("wallet", $sysinfo)){
                      $wallet_service = $sysinfo["wallet"];
                  ?>
                    <div class="input-group input-group">
                      <div class="input-group-prepend">
                        <div class="input-group-text check-service">
                          <input type="checkbox" class="quick_select_service" aria-label="Checkbox for following text input" value="<?php echo $wallet_service["service_id"]; ?>" data-service-type="<?php echo $wallet_service["service_type"]; ?>">
                        </div>
                        <?php echo getWARNLevelBadge($wallet_service["service_state"], $wallet_service["downtime_active"], 1); ?>
                        <span class="input-group-text service-name"><?php echo "{$wallet_service["service_desc"]}"; ?></span>
                      </div>
                      <div class="input-group-prepend">
                        <span class="input-group-text service-notes">
                        </span>
                        <span class="input-group-text service-summary"><?php echo "{$wallet_service["service_desc"]}: The service is currently " . ($wallet_service["service_state"] == 1 ? "running" : "not running") . "."; ?></span>
                        <span class="input-group-text service-state-since"><?php echo $wallet_service["state_first_reported"]; ?></span>
                        <span class="input-group-text service-last-checked"><?php echo calculateLastCheckedTime($wallet_service["state_last_reported"]); ?></span>
                        <span class="input-group-text service-workload">
                          <div class="progress">
                            <div class="progress-bar" style="width: <?php echo ($wallet_service["service_state"] == 1 ? "100" : 0) ?>%;" role="progressbar" aria-valuenow="<?php echo ($wallet_service["service_state"] == 1 ? "100" : 0) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                          </div>
                        </span>
                      </div>
                    </div> 
                  <?php
                    }
                  ?>
                </div>
              </div>
            </div>
            <!-- Details Pane -->
            <div class="tab-pane fade node-details-tab" id="details-<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="details-tab-<?php echo $nodeid; ?>">
              <div class="row">
                <?php if(array_key_exists("filesystems", $sysinfo)){ ?>
                <div class="col">
                  <div class="row">
                    <div class="col">
                      <div id="filesystems_container_<?php echo "{$nodeid}"; ?>">
                        <div class="card shadow mb-4">
                          <div id="filesystem_<?php echo "{$nodeid}"; ?>" class="card-body">
                            <h6 class="m-0 font-weight-bold text-primary">Filesystems</h6>
                            <?php 
                              foreach($sysinfo["filesystems"] AS $mountpoint => $filesystem){ 
                            ?>
                            <h4 class="small font-weight-bold">
                              <?php echo "{$filesystem["device"]} => {$filesystem["mountpoint"]}<br>(Size: " . formatkBytes($filesystem["size"]) . ", Used: " . formatkBytes($filesystem["used"]) . " Available: " . formatkBytes($filesystem["avail"]) . ")"; ?><span class="float-right"><?php echo $filesystem["total_usage"]; ?>%</span>
                              <?php echo getWARNLevelBadge($filesystem["service_status"], $filesystem["downtime_active"], 0); ?>
                              <?php echo ($filesystem["data_current"] ? "" : "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$filesystem["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>"); ?>
                            </h4>
                            <div class="progress mb-4">
                              <div class="progress-bar <?php echo ($filesystem["service_status"] == 1 ? "bg-success" : ($filesystem["service_status"] == 2 ? "bg-warning" : "bg-danger")); ?>" role="progressbar" style="width: <?php echo $filesystem["total_usage"]; ?>%" aria-valuenow="<?php echo $filesystem["total_usage"]; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <?php 
                              }
                            ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php 
                  }
                ?>
                <div class="col">
                  <div class="row">
                    <div class="col">
                      <div class="card shadow mb-4">
                        <div class="card-body">
                          <h6 class="m-0 font-weight-bold text-primary">System information</h6>
                          <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                OS info
                                <span class="badge badge-primary badge-pill"><?php echo "{$sysinfo["os"]["os_type"]} ({$sysinfo["os"]["os_name"]})"; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                CPU info
                                <span class="badge badge-primary badge-pill"><?php echo "{$sysinfo["cpu"]["info"]["cpu_model"]} ({$sysinfo["cpu"]["info"]["cpu_cores"]} CPU(s), {$sysinfo["cpu"]["info"]["cpu_count"]} threads)"; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Memory
                                <span class="badge badge-primary badge-pill"><?php echo "RAM: " . number_format(floatval($sysinfo["memory"]["ram"]["memory_total"])/1024/1024/1024, 2) . "GB" . ", SWAP " . number_format((array_key_exists("swap", $sysinfo["memory"]) ? floatval($sysinfo["memory"]["swap"]["swap_total"])/1024/1024/1024 : 0), 2) . "GB"; ?></span>
                            </li>
                          </ul>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php if(array_key_exists("memory", $sysinfo)){ ?>
                  <div class="row">
                    <div class="col">
                        <div id="ram_swap_container_<?php echo "{$nodeid}"; ?>">
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <div class="row">
                                    <?php if(array_key_exists("memory", $sysinfo) && array_key_exists("ram", $sysinfo["memory"]) && floatval($sysinfo["memory"]["ram"]["memory_total"]) > 0){ ?>
                                    <div class="col-6">
                                        <h7 class="m-0 font-weight-bold text-primary">
                                            RAM (Available: <?php echo number_format(floatval($sysinfo["memory"]["ram"]["memory_total"])/1024/1024/1024, 2) . "GB"; ?>)<br><?php echo "Usage: {$sysinfo["memory"]["ram"]["total_usage"]}%&nbsp;" . getWARNLevelBadge($sysinfo["memory"]["ram"]["service_status"], $sysinfo["memory"]["ram"]["downtime_active"], 0); ?>
                                            <?php echo (!$sysinfo["memory"]["ram"]["data_current"] ? "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["memory"]["ram"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?>
                                        </h7>
                                    <div class="chart-pie" style="min-height: 20vh;">
                                        <canvas id="ram_chart_<?php echo "{$nodeid}"; ?>"></canvas>
                                    </div>
                                </div>
                                <?php } ?>
                                <?php if(array_key_exists("memory", $sysinfo) && array_key_exists("swap", $sysinfo["memory"]) && floatval($sysinfo["memory"]["swap"]["swap_total"]) > 0){ ?>
                                <div class="col-6">
                                    <h7 class="m-0 font-weight-bold text-primary">
                                        SWAP (Available: <?php echo number_format(floatval($sysinfo["memory"]["swap"]["swap_total"])/1024/1024/1024, 2) . "GB"; ?>)<br><?php echo "Usage: {$sysinfo["memory"]["swap"]["total_usage"]}%&nbsp;" . getWARNLevelBadge($sysinfo["memory"]["swap"]["service_status"], $sysinfo["memory"]["swap"]["downtime_active"], 0); ?>
                                        <?php echo (!$sysinfo["memory"]["swap"]["data_current"] ? "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["memory"]["swap"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?>
                                    </h7>
                                    <div class="chart-pie" style="min-height: 20vh;">
                                        <canvas id="swap_chart_<?php echo "{$nodeid}"; ?>"></canvas>
                                    </div>
                                </div>
                                <?php } ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php }
                    if(array_key_exists("os", $sysinfo) && $sysinfo["os"]["os_type"] == "Linux"){
                  ?>
                  <div class="row">
                    <div class="col">
                      <div id="cpu_load_container_<?php echo "{$nodeid}"; ?>">
                        <div class="card shadow mb-4">
                          <div class="card-body">
                            <h7 class="m-0 font-weight-bold text-primary">CPU Load
                              <?php echo ",&nbsp;Usage: {$sysinfo["cpu"]["load"]["usage_15_min"]}%&nbsp;" . getWARNLevelBadge($sysinfo["cpu"]["load"]["service_state"], $sysinfo["cpu"]["load"]["downtime_active"], 0); ?>
                              <?php echo (!$sysinfo["cpu"]["load"]["data_current"] ? "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["cpu"]["load"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?>
                            </h7>
                            <div class="chart-bar" style="min-height: 30vh;">
                              <canvas id="cpu_load_chart_<?php echo "{$nodeid}"; ?>"></canvas>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php 
                    } 
                    if(array_key_exists("cpu", $sysinfo)){
                  ?>
                  <div class="row">
                    <div class="col">
                      <div id="cpu_usage_container_<?php echo "{$nodeid}"; ?>">
                        <div class="card shadow mb-4">
                          <div class="card-body">
                            <h7 class="m-0 font-weight-bold text-primary">CPU Usage
                              <?php echo ",&nbsp;Usage: {$sysinfo["cpu"]["usage"]["overall"]["total_usage"]}%&nbsp;" . getWARNLevelBadge($sysinfo["cpu"]["usage"]["overall"]["service_state"], $sysinfo["cpu"]["usage"]["overall"]["downtime_active"], 0); ?>
                              <?php echo (!$sysinfo["cpu"]["usage"]["overall"]["data_current"] ? "<span class='badge statusbadge badge-warning' data-toggle='tooltip' data-placement='top' title='Shown data outdated! {$sysinfo["cpu"]["usage"]["overall"]["state_last_reported"]}'><i class='fa-solid fa-triangle-exclamation'></i></span>" : ""); ?>
                            </h7>
                            <div class="chart-bar" style="min-height: 30vh;">
                              <canvas id="cpu_usage_chart_<?php echo "{$nodeid}"; ?>"></canvas>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php
                    }
                  ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php
      }
    }else{
  ?>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        There is currently no sysinfo data to show. <br>
        Please try to rescan all data on the nodes page by pressing the button "Query all available information from all nodes".
      </div>
    </div>
  </div>
</div>
<?php 
  } 
?>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/js/chia_infra_sysinfo.js"?>></script>
<?php }); ?>