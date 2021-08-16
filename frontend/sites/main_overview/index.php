<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\MainOverview\MainOverview_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $main_overview_api = new MainOverview_Api();
  $chia_overall_api = new Chia_Overall_Api();
  $exchangerates_api = new Exchangerates_Api();

  $overviewData = $main_overview_api->getAllOverviewData()["data"];
  /*echo "<pre>";
  print_r($overviewData);
  echo "</pre>";*/

  echo "<script> var siteID = 1; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    <!--<a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
            class="fas fa-download fa-sm text-white-50"></i> Generate Report</a>-->
</div>
<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="card bg-success text-white shadow">
      <div class="card-body">
        Successfully running services
        <div class="text-white-50 small"><h3>14</h3></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 mb-4">
    <div class="card bg-danger text-white shadow">
      <div class="card-body">
        Critical services
        <div class="text-white-50 small"><h3>2</h3></div>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Chia overall information</h6>
      </div>
      <div class="card-body">
        <div class="row">
          <?php
            if(count($overviewData["chia-overall"]) > 0){
          ?>
          <div class="col">
            <div class="row">
              <div class="col-xl-3 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Netspace</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overviewData["chia-overall"]["netspace"]; ?></div>
                                <i class="fas <?php echo (floatval($overviewData["chia-overall"]["daychange_percent"]) > 0 ? "fa-arrow-up" : "fa-arrow-down"); ?>" style="color: <?php echo (floatval($overviewData["chia-overall"]["daychange_percent"]) > 0 ? "green" : "red"); ?>"></i>&nbsp;<?php echo number_format($overviewData["chia-overall"]["daychange_percent"], 2) . "% (24H)"; ?>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-hdd fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
              <div class="col-xl-3 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                  <div class="card-body">
                    <div class="row no-gutters align-items-center">
                      <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Current XCH price</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800 text-uppercase"><?php echo "{$overviewData["currency"]["defaultCurrency"]}&nbsp;" . number_format(floatval($overviewData["chia-overall"]["price_usd"]) * floatval($overviewData["currency"]["exchangerate"]), 2); ?></div>
                        <div class="text-uppercase">
                          <i class="fas fa-arrow-down" style="color: red;"></i>&nbsp;<?php echo "{$overviewData["currency"]["defaultCurrency"]}&nbsp;" . number_format(floatval($overviewData["chia-overall"]["daymin_24h_usd"]) * floatval($overviewData["currency"]["exchangerate"]), 2); ?>
                          <i class="fas fa-arrow-up" style="color: green;"></i>&nbsp;<?php echo "{$overviewData["currency"]["defaultCurrency"]}&nbsp;" . number_format(floatval($overviewData["chia-overall"]["daymax_24h_usd"]) * floatval($overviewData["currency"]["exchangerate"]), 2); ?>
                          &nbsp;(24h)
                        </div>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php }else{ ?>
          <div class="col">
              <div class="card bg-danger text-white shadow">
                  <div class="card-body">
                      No chia overall data found
                      <div class="text-white-50 small">Something seems not to be working properly. No data has been received from external source.</div>
                  </div>
              </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Wallet Overview</h6>
      </div>
      <?php
        if(count($overviewData["walletinfos"]) > 0){
          $hostchecks = "";
          foreach ($overviewData["walletinfos"] as $nodeid => $nodedata) {
            $hostchecks .= "{$nodedata[array_key_first($nodedata)]["hostname"]}:&nbsp;<span id='servicestatus_wallet_{$nodeid}' class='badge " . (array_key_first($nodedata) > 0 ? "badge-secondary" : "badge-danger") . "'>" . (array_key_first($nodedata) > 0 ? "Querying service status" : "No data found") . "</span><br>";
            foreach($nodedata AS $walletid => $walletdata){
              $walletsyncstatus .= "{$walletdata["hostname"]} - Wallet {$walletid}:&nbsp;<span id='syncstatus_{$nodeid}' class='badge " . ($walletid > 0 && $walletdata["syncstatus"] == "Synced" ? "badge-success" : "badge-danger") . "'>" . ($walletid > 0 ? $walletdata["syncstatus"]."&nbsp;(Height: {$walletdata["walletheight"]})" : "No data found"). "</span></br>";
              $totalxch += floatval($walletdata["totalbalance"]);
            }
          }
          $totalincurr = $totalxch * floatval($overviewData["chia-overall"]["price_usd"]) * floatval($overviewData["currency"]["exchangerate"]);
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Wallet sync status</div>
                          <?php echo $walletsyncstatus; ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-sync fa-3x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total XCH (all Wallets)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">XCH <?php echo rtrim(sprintf('%.9F',$totalxch), '0'); ?></div>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-wallet fa-2x text-gray-300"></i>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total XCH (all Wallets) in <?php echo $overviewData["currency"]["defaultCurrency"]; ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800 text-uppercase"><?php echo "{$overviewData["currency"]["defaultCurrency"]} " . rtrim(sprintf('%.9F',$totalincurr), '0'); ?></div>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                No wallet data available. Please configure some nodes.
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Farm Overview</h6>
      </div>
      <?php
        if(count($overviewData["farminfos"]) > 0){
          $sizes = array("" => 1,"MiB" => 1,"GiB" => 1024,"TiB" => pow(1024,2),"PiB" =>  pow(1024,3),"EiB" =>  pow(1024,4));
          $hostchecks = "";

          foreach($overviewData["farminfos"] AS $nodeid => $nodedata){
            $hostchecks .= "{$nodedata["hostname"]}:&nbsp;<span id='servicestatus_farmer_{$nodeid}' class='badge " . (!is_null($nodedata["farming_status"]) ? "badge-secondary" : "badge-danger") . "'>" . (!is_null($nodedata["farming_status"]) ? "Querying service status" : "No data found") . "</span><br>";
            $farmingstatus .= "{$nodedata["hostname"]}:&nbsp;<span id='farmingstatus_{$nodeid}' class='badge " . (!is_null($nodedata["farming_status"]) ? ($nodedata["farming_status"] == "Farming" ? "badge-success" : "badge-danger") : "badge-danger") . "'>" . (is_null($nodedata["farming_status"]) ? "No data found" : $nodedata["farming_status"]) . "</span></br>";
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
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Harvester Overview</h6>
      </div>
      <?php
      if(count($overviewData["harvesterinfos"]) > 0){
        $hostchecks = "";
        foreach($overviewData["harvesterinfos"] AS $nodeid => $nodedata){
          $hostchecks .= "{$nodedata["hostname"]}:&nbsp;<span id='servicestatus_harvester_{$nodeid}' class='badge " . (!array_key_exists("Unknown", $nodedata["plotdirs"]) ? "badge-secondary" : "badge-danger") . "'>" . (!array_key_exists("Unknown", $nodedata["plotdirs"]) ? "Querying service status" : "No data found") . "</span><br>";
          $nodes = array();
          foreach($nodedata["plotdirs"] AS $finalplotsdir => $plotdata){
            if(is_null($plotdata["devname"]) && $finalplotsdir != "Unknown"){
              if(!in_array($nodedata["hostname"], $nodes)){
                $criticalmount .= "{$nodedata["hostname"]}:<br>";
                array_push($nodes, $nodedata["hostname"]);
              }
              $criticalmount .= "<span id='plot_crit_{$plotdata["id"]}' class='badge badge-danger'>{$finalplotsdir}</span></br>";

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
                <div class="card border-left-danger shadow h-100 py-2">
                  <div class="card-body">
                    <div class="row no-gutters align-items-center">
                      <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Not mounted directories</div>
                        <?php echo $criticalmount; ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-database fa-3x text-gray-300"></i>
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
  </div>
</div>

<!-- Page level plugins -->
<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/frameworks/bootstrap/vendor/chart.js/Chart.min.js"?>></script>
