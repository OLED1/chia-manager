<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Harvester\Chia_Harvester_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';
  include_once(__DIR__ . '/functions.php');

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_harvester_api = new Chia_Harvester_Api();
  $harvesterdata = $chia_harvester_api->getHarvesterData(["nodeid" => $_GET["nodeid"]]);

  if(array_key_exists("data", $harvesterdata) && count($harvesterdata["data"]) > 0 && array_key_exists($_GET["nodeid"], $harvesterdata["data"])){
    $harvesterdata = $harvesterdata["data"][$_GET["nodeid"]];
  }else{
    $harvesterdata = [];
  }

  $nodeid = $_GET["nodeid"];

  echo "<script nonce={$ini["nonce_key"]}> 
          var siteID = 7;
          chiaHarvesterData[{$_GET["nodeid"]}] = " .  json_encode((array_key_exists($_GET["nodeid"], $harvesterdata) ? $harvesterdata[$_GET["nodeid"]] : [])) . "; 
        </script>";
?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class='m-0 font-weight-bold text-primary'>Harvesterdata for host <?php echo $harvesterdata["hostname"]; ?> with id <?php echo $_GET["nodeid"]; ?>&nbsp;
          <?php if(!array_key_exists(array_key_first($harvesterdata["plotdirs"]), $harvesterdata["plotdirs"]) || is_null($harvesterdata["plotdirs"][array_key_first($harvesterdata["plotdirs"])])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
          <?php
            }else{
          ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-secondary'>Processing...</span>
          <?php } ?>
        </h6>
        <div class='dropdown no-arrow'>
          <a id='dropdownMenuLink_<?php echo $nodeid; ?>' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
              <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
              <div class='dropdown-header'>Actions:</div>
              <button data-node-id='<?php echo $nodeid; ?>' class='dropdown-item refreshHarvesterInfo wsbutton' href='#'>Refresh</button>
              <button data-node-id='<?php echo $nodeid; ?>' class='dropdown-item restartHarvesterService wsbutton' href='#'>Restart harvester service</button>
          </div>
        </div>
      </div>
<?php 
  if(array_key_exists("plotdirs", $harvesterdata) && is_array($harvesterdata["plotdirs"]) && count($harvesterdata["plotdirs"]) > 0){ 
?>
      <div class="card-body">
        <div class="row">
          <div class="col">
            <div class="card shadow mb-4">
              <div class="card-body">
                <h5>Configured plot directories</h5>
                <?php foreach($harvesterdata["plotdirs"] AS $mountpoint => $dirinfos){ ?>
                <h4 class="small font-weight-bold"><?php echo (!is_null($dirinfos["mount_device"]) ? "{$dirinfos["mount_device"]}" : "?"); ?>&nbsp;->&nbsp;<?php echo $mountpoint; ?>&nbsp;(<?php echo (!is_null($dirinfos["mount_size"]) && !is_null($dirinfos["mount_avail"]) ? format_spaces($dirinfos["mount_size"], "KiB") . " total / " . format_spaces($dirinfos["mount_avail"], "KiB") . " available" : "UNKNOWN - Not mounted"); ?>)<span class="float-right"><?php echo (!is_null($dirinfos["mount_used"]) ? number_format($dirinfos["mount_used"] / $dirinfos["mount_size"] * 100, 2)."%": "?%"); ?></span></h4>
                <div class="progress mb-4">
                  <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo (!is_null($dirinfos["mount_used"]) ? ($dirinfos["mount_used"] / $dirinfos["mount_size"] * 100 )."%" : "0%"); ?>" aria-valuenow="<?php echo (!is_null($dirinfos["mount_used"]) ? ($dirinfos["mount_used"] / $dirinfos["mount_size"] * 100 ) : "0"); ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $dirinfos["plotcount"]; ?> valid plots found</div>
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
                        <th>Plotfilesize</th>
                        <th>K-Size</th>
                        <th>Filename</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        foreach($dirinfos["plots"] AS $arrkey => $plotinfo){
                          echo "
                            <tr>
                              <td>{$plotinfo["plot_time_modified"]}</td>
                              <td>{$mountpoint}</td>
                              <td> " . format_spaces($plotinfo["plot_file_size"], "Byte") . "</td>
                              <td>{$plotinfo["plot_size"]}</td>
                              <td>{$plotinfo["plot_filename"]}</td>
                              <td>" . format_plot_status($plotinfo["plot_last_reported"]) . "</td>
                            </tr>
                          ";
                        }
                      ?>
                    </tbody>
                    <tfoot>
                      <tr>
                        <th>Creation Date</th>
                        <th>Plotdir</th>
                        <th>Plotfilesize</th>
                        <th>K-Size</th>
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
      <?php
        }else{
      ?>
      <div class="card-body">
        <div class="card bg-danger text-white shadow">
          <div class="card-body">
            There is currently no data to show! Please make a rescan of this system.
          </div>
        </div>
      </div>
      <?php } ?>
      <div class="card-footer">
        Data updadated on: <span id="querydate_<?php echo "{$_GET["nodeid"]}"; ?>"><?php echo (!array_key_exists(array_key_first($harvesterdata["plotdirs"]), $harvesterdata["plotdirs"]) || is_null($harvesterdata["plotdirs"][array_key_first($harvesterdata["plotdirs"])]["mount_lastupdated"]) ? "Never" : $harvesterdata["plotdirs"][array_key_first($harvesterdata["plotdirs"])]["mount_lastupdated"]); ?></span>
      </div>
    </div>
  </div>
</div>