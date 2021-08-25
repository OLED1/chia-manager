<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Harvester\Chia_Harvester_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_harvester_api = new Chia_Harvester_Api();
  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];
  $harvesterdata = $chia_harvester_api->getHarvesterData();

  echo "<script> var siteID = 7; </script>";

  if(array_key_exists("data", $harvesterdata) && count($harvesterdata["data"]) > 0){
    echo "<script> var chiaHarvesterData = " . json_encode($harvesterdata["data"]) . "; </script>";

    foreach($harvesterdata["data"] AS $nodeid => $harvesterinfos){
?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class='m-0 font-weight-bold text-primary'>Harvesterdata for host <?php echo $harvesterinfos["hostname"]; ?> with id <?php echo $nodeid; ?>&nbsp;
        <?php
        if(is_null($harvesterinfos["plotdirs"][array_key_first($harvesterinfos["plotdirs"])]["finalplotsdir"])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
          <?php
              }else{
                if($nodes_states[$nodeid]["onlinestatus"] == 1){
                  $statustext = "Node not reachable.";
                  $statusicon = "badge-danger";
                }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
                  if($nodes_states[$nodeid][$statusname] == 1){
                    $statustext = "Harvester service not running.";
                    $statusicon = "badge-danger";
                  }else if($nodes_states[$nodeid][$statusname] == 0){
                    $statustext = "Harvester service running.";
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
              <a data-node-id='<?php echo $nodeid; ?>' class='dropdown-item refreshHarvesterInfo' href='#'>Refresh</a>
              <a data-node-id='<?php echo $nodeid; ?>' class='dropdown-item restartHarvesterService' href='#'>Restart harvester service</a>
          </div>
        </div>
      </div>
      <?php if(is_null($harvesterinfos["plotdirs"][array_key_first($harvesterinfos["plotdirs"])]["finalplotsdir"])){ ?>
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
                <h5>Configured plot directories</h5>
                <?php foreach($harvesterinfos["plotdirs"] AS $finalplotsdir => $dirinfos){ ?>
                <h4 class="small font-weight-bold"><?php echo (!is_Null($dirinfos["devname"]) ? "{$dirinfos["devname"]}&nbsp;->" : ""); ?>&nbsp;<?php echo $dirinfos["finalplotsdir"]; ?>&nbsp;(Size: <?php echo (!is_null($dirinfos["totalsize"]) ? $dirinfos["totalsize"] : "UNKNOWN - Not mounted"); ?>)<span class="float-right"><?php echo (!is_Null($dirinfos["totalusedpercent"]) ? $dirinfos["totalusedpercent"] : "0%"); ?></span></h4>
                <div class="progress mb-4">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo (!is_Null($dirinfos["totalusedpercent"]) ? $dirinfos["totalusedpercent"] : "0%"); ?>" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"><?php echo $dirinfos["totalused"]; ?> - <?php echo $dirinfos["plotcount"]; ?> Plots</div>
                </div>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <div class="card shadow mb-4">
              <div class="card-body">
                <h5>Found plots</h5>
                <div class="table-responsive">
                  <table class="table table-bordered" id="plotstable_<?php echo $nodeid; ?>" width="100%" cellspacing="0">
                    <thead>
                      <tr>
                        <th>Creation Date</th>
                        <th>Plotdir</th>
                        <th>K-Size</th>
                        <th>Plot Key</th>
                        <th>Pool Key</th>
                        <th>Filename</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        foreach($harvesterinfos["plotdirs"] AS $finalplotsdir => $dirinfos){
                          if(array_key_exists("data", $dirinfos["foundplots"])){
                            foreach($dirinfos["foundplots"]["data"] AS $arrkey => $plotdata){
                              echo "
                                <tr>
                                  <td>{$plotdata["plotcreationdate"]}</td>
                                  <td>{$finalplotsdir}</td>
                                  <td>{$plotdata["k_size"]}</td>
                                  <td>{$plotdata["plot_key"]}</td>
                                  <td>{$plotdata["pool_key"]}</td>
                                  <td>{$plotdata["filename"]}</td>
                                  <td>{$plotdata["status"]}</td>
                                </tr>
                              ";
                            }
                          }
                        }
                      ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th>Creation Date</th>
                        <th>Plotdir</th>
                        <th>K-Size</th>
                        <th>Plot Key</th>
                        <th>Pool Key</th>
                        <th>Filename</th>
                        <th>Status</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
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

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_harvester/js/chia_harvester.js"?>></script>
