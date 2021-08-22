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
    echo "<script> var sysinfodata = " . json_encode($sysinfos["data"]) . "; </script>";

    foreach($sysinfos["data"] AS $nodeid => $sysinfo){
?>
<div id="container_<?php echo "{$sysinfo["id"]}"; ?>" class="sysinfocontainer card shadow mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
    <h6 class="m-0 font-weight-bold text-primary">Systeminformation Node <bold><?php echo "{$sysinfo["hostname"]}"; ?></bold></h6>
    <div class="dropdown no-arrow">
      <a class="dropdown-toggle" href="#" role="button" id="sysinfodropdown<?php echo "{$sysinfo["id"]}"; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i></a>
      <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="sysinfodropdown<?php echo "{$sysinfo["id"]}"; ?>">
        <div class="dropdown-header">Actions:</div>
        <a class="sysinfo-refresh dropdown-item" data-nodeid="<?php echo "{$sysinfo["id"]}"; ?>" href="#">Refresh Data</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col">
        <div class="row">
          <div class="col">
            <div id="filesystems_container_<?php echo "{$sysinfo["id"]}"; ?>">
              <div class="card shadow mb-4">
                <div id="filesystem_<?php echo "{$sysinfo["id"]}"; ?>" class="card-body">
                  <h6 class="m-0 font-weight-bold text-primary">Filesystems</h6>
                  <?php foreach(json_decode($sysinfo["filesystem"]) AS $arrkey => $filesystem){ ?>
                    <h4 class="small font-weight-bold"><?php echo "{$filesystem[0]} => {$filesystem[5]} (Size: {$filesystem[1]} Used: {$filesystem[2]} Available: {$filesystem[3]})"; ?><span class="float-right"><?php echo $filesystem[4]; ?></span></h4>
                    <div class="progress mb-4">
                      <div class="progress-bar <?php $percent = explode("%",$filesystem[4])[0]; echo ($percent <= 50 ? "bg-success" : ($percent <= 75 ? "bg-warning" : "bg-danger")); ?>" role="progressbar" style="width: <?php echo $filesystem[4]; ?>" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="row">
          <div class="col">
            <div id="ram_swap_container_<?php echo "{$sysinfo["id"]}"; ?>">
              <div class="card shadow mb-4">
                <div class="card-body">
                  <h6 class="m-0 font-weight-bold text-primary">RAM and SWAP</h6>
                  <div class="row">
                    <div class="col-6">
                      <h7 class="m-0 font-weight-bold text-primary">RAM (<?php echo number_format(floatval($sysinfo["memory_total"])/1024/1024, 2) . "GB"; ?>)</h7>
                      <div class="chart-pie pt-4 pb-2">
                        <canvas id="ram_chart_<?php echo "{$sysinfo["id"]}"; ?>"></canvas>
                      </div>
                      <div class="mt-4 text-center small">
                        <span class="mr-2"><i class="fas fa-circle ram-swap-free"></i> RAM free</span>
                        <span class="mr-2"><i class="fas fa-circle ram-swap-used"></i> RAM used</span>
                      </div>
                    </div>
                    <div class="col-6">
                      <h7 class="m-0 font-weight-bold text-primary">SWAP (<?php echo number_format(floatval($sysinfo["swap_total"])/1024/1024, 2) . "GB"; ?>)</h7>
                      <div class="chart-pie pt-4 pb-2">
                        <canvas id="swap_chart_<?php echo "{$sysinfo["id"]}"; ?>"></canvas>
                      </div>
                      <div class="mt-4 text-center small">
                        <span class="mr-2">  <i class="fas fa-circle ram-swap-free"></i> SWAP free</span>
                        <span class="mr-2"><i class="fas fa-circle ram-swap-used"></i> SWAP used</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-6">
            <div id="cpu_load_container_<?php echo "{$sysinfo["id"]}"; ?>">
              <div class="card shadow mb-4">
                <div class="card-header py-3">
                  <h7 class="m-0 font-weight-bold text-primary"><?php echo "CPU {$sysinfo["cpu_model"]} - {$sysinfo["cpu_count"]} Cores, " . ($sysinfo["cpu_count"]*$sysinfo["cpu_cores"]) . " Threads"; ?></h7>
                </div>
                <div class="card-body">
                  <h7 class="m-0 font-weight-bold text-primary">CPU Load</h7>
                  <div class="chart-bar">
                    <canvas id="cpu_load_chart_<?php echo "{$sysinfo["id"]}"; ?>"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer">Data queried at: <span id="querydate_<?php echo "{$sysinfo["id"]}"; ?>"><?php echo "{$sysinfo["timestamp"]}"; ?></span></div>
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
<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/frameworks/bootstrap/vendor/chart.js/Chart.js"?>></script>
<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/js/chia_infra_sysinfo.js"?>></script>
