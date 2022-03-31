<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_infra_sysinfo_api = new Chia_Infra_Sysinfo_Api();
  $sysinfos = $chia_infra_sysinfo_api->getSystemInfo();

  if(array_key_exists("data", $sysinfos) && count($sysinfos["data"]) > 0){
    echo "<script nonce={$ini["nonce_key"]}> var sysinfodata = " . json_encode($sysinfos["data"]) . "; </script>";

    foreach($sysinfos["data"] AS $nodeid => $sysinfo){
?>
<div class='row'>
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
          <?php } ?>
        </h6>
        <div class="dropdown no-arrow">
          <a class="dropdown-toggle" href="#" role="button" id="sysinfodropdown<?php echo "{$nodeid}"; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i></a>
          <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="sysinfodropdown<?php echo "{$nodeid}"; ?>">
            <div class="dropdown-header">Actions:</div>
            <button class="sysinfo-refresh dropdown-item wsbutton" data-nodeid="<?php echo "{$nodeid}"; ?>" href="#">Refresh Data</button>
          </div>
        </div>
      </div>
      <div class="card-body">
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
                        <h4 class="small font-weight-bold"><?php echo "{$filesystem["device"]} => {$filesystem["mountpoint"]} (Size: " . formatkBytes($filesystem["size"]) . " Used: " . formatkBytes($filesystem["used"]) . " Available: " . formatkBytes($filesystem["avail"]) . ")"; ?><span class="float-right"><?php echo $filesystem["total_usage"]; ?>%</span></h4>
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
            if(array_key_exists("memory", $sysinfo)){
          ?>
          <div class="col">
            <div class="row">
              <div class="col">
                <div id="ram_swap_container_<?php echo "{$nodeid}"; ?>">
                  <div class="card shadow mb-4">
                    <div class="card-body">
                      <h6 class="m-0 font-weight-bold text-primary">RAM and SWAP</h6>
                      <div class="row">
                        <?php if(array_key_exists("memory", $sysinfo) && array_key_exists("ram", $sysinfo["memory"]) && floatval($sysinfo["memory"]["ram"]["memory_total"]) > 0){ ?>
                        <div class="col-6">
                          <h7 class="m-0 font-weight-bold text-primary">RAM (<?php echo number_format(floatval($sysinfo["memory"]["ram"]["memory_total"])/1024/1024/1024, 2) . "GB"; ?>)</h7>
                          <div class="chart-pie" style="min-height: 20vh;">
                            <canvas id="ram_chart_<?php echo "{$nodeid}"; ?>"></canvas>
                          </div>
                        </div>
                        <?php } ?>
                        <?php if(array_key_exists("memory", $sysinfo) && array_key_exists("swap", $sysinfo["memory"]) && floatval($sysinfo["memory"]["swap"]["swap_total"]) > 0){ ?>
                        <div class="col-6">
                          <h7 class="m-0 font-weight-bold text-primary">SWAP (<?php echo number_format(floatval($sysinfo["memory"]["swap"]["swap_total"])/1024/1024/1024, 2) . "GB"; ?>)</h7>
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
                    <div class="card-header py-3">
                      <h7 class="m-0 font-weight-bold text-primary"><?php echo "CPU {$sysinfo["cpu"]["info"]["cpu_model"]} - {$sysinfo["cpu"]["info"]["cpu_cores"]} Cores, {$sysinfo["cpu"]["info"]["cpu_count"]} Threads"; ?></h7>
                    </div>
                    <div class="card-body">
                      <h7 class="m-0 font-weight-bold text-primary">CPU Load</h7>
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
                    <div class="card-header py-3">
                      <h7 class="m-0 font-weight-bold text-primary">
                        <?php
                          if(array_key_exists("cpu", $sysinfo) && array_key_exists("info", $sysinfo["cpu"])){
                            echo "CPU {$sysinfo["cpu"]["info"]["cpu_model"]} - {$sysinfo["cpu"]["info"]["cpu_cores"]} Cores, {$sysinfo["cpu"]["info"]["cpu_count"]} Threads"; 
                          }else{
                            echo "Used CPU: Unknown";
                          }
                        ?>
                      </h7>
                    </div>
                    <div class="card-body">
                      <h7 class="m-0 font-weight-bold text-primary">CPU Usage</h7>
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
<?php
    }
  }else{
?>
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
<?php } ?>
<?php 
function formatkBytes(int $size, int $precision = 2)
{ 
    if($size == 0) return "0B";
    $base = log($size, 1024);
    $suffixes = array('B','KB', 'MB', 'GB', 'TB');   

    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
}
?>