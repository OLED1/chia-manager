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

  $chia_farm_api = new Chia_Farm_Api();
  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];
  $farm_api_data = $chia_farm_api->getFarmData();
  $challenges = $chia_farm_api->getAllChallenges();

  if(array_key_exists("data", $farm_api_data) && count($farm_api_data["data"]) > 0){
    echo "<script nonce={$ini["nonce_key"]}> var chiaFarmData = " . json_encode($farm_api_data["data"]) . "; </script>";
    foreach($farm_api_data["data"] AS $nodeid => $farmdata){
?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class='m-0 font-weight-bold text-primary'>Farmdata for host <?php echo $farmdata["hostname"]; ?> with id <?php echo $nodeid; ?>&nbsp;
        <?php if(is_null($farmdata["farming_status"])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
        <?php
          }else{
            if($nodes_states[$nodeid]["onlinestatus"] == 1){
              $statustext = "Node not reachable.";
              $statusicon = "badge-danger";
            }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
              if($nodes_states[$nodeid][$statusname] == 1){
                $statustext = "Farmer service not running.";
                $statusicon = "badge-danger";
              }else if($nodes_states[$nodeid][$statusname] == 0){
                $statustext = "Farmer service running.";
                $statusicon = "badge-success";
              }else{
                $statustext = "Querying service status";
                $statusicon = "badge-secondary";
              }
            }
        ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge <?php echo $statusicon; ?>'><?php echo $statustext; ?></span>
        <?php } ?>
        </h6>
        <div class='dropdown no-arrow'>
          <a id='dropdownMenuLink_<?php echo $nodeid; ?>' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
              <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
              <div class='dropdown-header'>Actions:</div>
              <button data-node-id='<?php echo $nodeid; ?>' class='dropdown-item refreshFarmInfo wsbutton' href='#'>Refresh</button>
              <button data-node-id='<?php echo $nodeid; ?>' class='dropdown-item restartFarmerService wsbutton' href='#'>Restart farmer service</button>
          </div>
        </div>
      </div>
      <?php if(is_null($farmdata["farming_status"])){ ?>
      <div class="card-body">
        <div class="card bg-danger text-white shadow">
          <div class="card-body">
            There is currently no data to show! Please make a rescan of this system.
          </div>
        </div>
      </div>
      <?php }else{ ?>
      <div class="card-body">
        <div class="row">
          <div class="col">
            <div class="card shadow mb-4">
              <div class="card-body">
                <h5>Farming Status</h5>
                <h4 class='<?php echo ($farmdata["farming_status"] == "Farming" ? "text-success" : "text-danger") ?>'><?php echo $farmdata["farming_status"]; ?><span>&#8226;</span></h4>
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
      </div>
      <div class="card-footer">
        Data queried at: <span id="querydate_<?php echo "{$nodeid}"; ?>"><?php echo "{$farmdata["querydate"]}"; ?></span>
      </div>
      <?php } ?>
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
        There are currently no wallets to show.<br>
        Please try to rescan all data on the nodes page by pressing the button "Query all available information from all nodes".
      </div>
    </div>
  </div>
</div>
<?php } ?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <h5>Latest Challenges</h5>
        <div class="table-responsive">
          <table class="table table-bordered" id="challengestable" width="100%" cellspacing="0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Hash</th>
                <th>Index</th>
              </tr>
            </thead>
            <tbody>
              <?php
                if(array_key_exists("data", $challenges) && count($challenges["data"]) > 0){
                  foreach($challenges["data"] AS $arrkey => $challenge){
              ?>
                <tr>
                  <td><?php echo $challenge["date"]; ?></td>
                  <td><?php echo $challenge["hash"]; ?></td>
                  <td><?php echo $challenge["hash_index"]; ?></td>
                </tr>
              <?php
                  }
                }
              ?>
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

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_farm/js/chia_farm.js"?>></script>
