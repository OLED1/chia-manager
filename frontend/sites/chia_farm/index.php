<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Farm\Chia_Farm_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_farm_api = new Chia_Farm_Api();
  $farmdata = $chia_farm_api->getFarmData();

  echo "<script> var siteID = 6; </script>";
  echo "<script> var chiaFarmData = " . json_encode($farmdata["data"]) . "; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Farm</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your farm and information about it.
      </div>
    </div>
  </div>
</div>
<h4>My Farm</h4>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query farm information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h5>Overview</h5>
<div id="farminfocards">
<?php if(count($farmdata["data"]) == 0) { ?>
  <div class="row">
    <div class="col">
      <div class="card shadow mb-4">
        <div class="card-body">
          There is currently no farm data to show.<br>
          Please try to rescan all data on the nodes page by pressing the button "Query all available information from all nodes".
        </div>
      </div>
    </div>
  </div>
<?php
  }else{
    foreach($farmdata["data"] AS $nodeid => $farmdata){
?>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class='m-0 font-weight-bold text-primary'>Farmdata for host <?php echo $farmdata["hostname"]; ?> with id <?php echo $nodeid; ?>&nbsp;<span id='servicestatus_<?php echo $nodeid; ?>' class='badge statusbadge badge-secondary'>Querying service status</span></h6>
            <div class='dropdown no-arrow'>
              <a id='dropdownMenuLink_<?php echo $nodeid; ?>' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                  <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
              </a>
              <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
                  <div class='dropdown-header'>Actions:</div>
                  <a data-node-id='<?php echo $nodeid; ?>' class='dropdown-item refreshFarmInfo' href='#'>Refresh</a>
                  <a data-node-id='<?php echo $nodeid; ?>' class='dropdown-item restartFarmerService' href='#'>Restart farmer service</a>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Farming Status</h5>
                    <h4 style='<?php echo ($farmdata["farming_status"] == "Farming" ? "color: green;" : "color: red;") ?>'><?php echo $farmdata["farming_status"]; ?><span style="font-size: 1.2em;">&#8226;</span></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>XCH Total Chia Farmed</h5>
                    <h4><?php echo ($farmdata["total_chia_farmed"]); ?></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>XCH Block Rewards</h5>
                    <h4><?php echo ($farmdata["block_rewards"]); ?></h4>
                    <h7>Without fees</h7>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>XCH User Transaction Fees</h5>
                    <h4><?php echo ($farmdata["user_transaction_fees"]); ?></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Last Height Farmed</h5>
                    <h4><?php echo ($farmdata["last_height_farmed"]); ?></h4>
                    <h7>No blocks farmed yet</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Plot Count</h5>
                    <h4><?php echo ($farmdata["plot_count"]); ?></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Total Size of Plots</h5>
                    <h4><?php echo ($farmdata["total_size_of_plots"]); ?></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Total Network Space</h5>
                    <h4><?php echo ($farmdata["estimated_network_space"]); ?></h4>
                    <h7>Best estimate over last 24 hours</h7>
                  </div>
                </div>
              </div>
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Estimated time to Win</h5>
                    <h4><?php echo ($farmdata["expected_time_to_win"]); ?></h4>
                    <h7>&nbsp;</h7>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <div class="card shadow mb-4">
                  <div class="card-body">
                    <h5>Latest Challenges</h5>
                    <div class="table-responsive">
                      <table class="table table-bordered" id="challengestable_<?php echo $nodeid; ?>" width="100%" cellspacing="0">
                        <thead>
                          <tr>
                            <th>Date</th>
                            <th>Hash</th>
                            <th>Index</th>
                          </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                          <tr>
                            <th>Date</th>
                            <th>Hash</th>
                            <th>Index</th>
                          </tr>
                        </tfoot>
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
<?php
    }
  }
?>
</div>
<!-- <h5>Last Attempted Proof</h5>
<h5>Last Block Challenges</h5>-->

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_farm/js/chia_farm.js"?>></script>
