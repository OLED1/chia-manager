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

  $defaultCurrency = $exchangerates_api-> getUserDefaultCurrency($_COOKIE["user_id"]);
  if($defaultCurrency["status"] == 0) $defaultCurrency = $defaultCurrency["data"]["currency_code"];
  else $defaultCurrency = "usd";

  $exchangerate = $exchangerates_api->queryExchangeRatesData($defaultCurrency);
  if($exchangerate["status"] == 0 && array_key_exists($defaultCurrency, $exchangerate["data"])){
    $exchangerate = $exchangerate["data"][$defaultCurrency]["currency_rate"];
  }else{
    $defaultCurrency = "usd";
    $exchangerate = 1;
  }

  echo "<script> var siteID = 1; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
            class="fas fa-download fa-sm text-white-50"></i> Generate Report</a>
</div>
<div class="row">
  <div class="col-lg-2 mb-4">
    <div class="card bg-success text-white shadow">
      <div class="card-body">
        Successfully running services
        <div class="text-white-50 small"><h3>14</h3></div>
      </div>
    </div>
  </div>
  <div class="col-lg-2 mb-4">
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
            $chia_overall_data = $chia_overall_api->queryOverallData();

            if($chia_overall_data["status"] == 0 && count($chia_overall_data["data"]) > 0){
              $chiadata = $chia_overall_data["data"];
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
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $chiadata["netspace"]; ?></div>
                                <i class="fas <?php echo (floatval($chiadata["daychange_percent"]) > 0 ? "fa-arrow-up" : "fa-arrow-down"); ?>" style="color: <?php echo (floatval($chiadata["daychange_percent"]) > 0 ? "green" : "red"); ?>"></i>&nbsp;<?php echo number_format($chiadata["daychange_percent"], 2) . "% (24H)"; ?>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800 text-uppercase"><?php echo "{$defaultCurrency}&nbsp;" . number_format(floatval($chiadata["price_usd"]) * floatval($exchangerate), 2); ?></div>
                        <div class="text-uppercase">
                          <i class="fas fa-arrow-down" style="color: red;"></i>&nbsp;<?php echo "{$defaultCurrency}&nbsp;" . number_format(floatval($chiadata["daymin_24h_usd"]) * floatval($exchangerate), 2); ?>
                          <i class="fas fa-arrow-up" style="color: green;"></i>&nbsp;<?php echo "{$defaultCurrency}&nbsp;" . number_format(floatval($chiadata["daymax_24h_usd"]) * floatval($exchangerate), 2); ?>
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
                        <div class="text-uppercase">
                          hostname:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-secondary'>Querying service status</span></br>
                          hostname1:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-success'>Service running</span></br>
                          hostname2:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Service not running</span></br>
                          hostname3:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Node not reachable</span><br>
                          hostname4:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>No data found</span>
                        </div>
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
                        <div class="text-uppercase">
                          hostname:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>No data found</span></br>
                          hostname1:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-success'>Synced&nbsp;(Height: 12345678)</span></br>
                          hostname2:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Not synced&nbsp;(Height: 12345678)</span></br>
                        </div>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800">XCH 120</div>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total XCH (all Wallets) in USD</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">USD 120</div>
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
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Farm Overview</h6>
      </div>
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
                        <div class="text-uppercase">
                          hostname:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-secondary'>Querying service status</span></br>
                          hostname1:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-success'>Service running</span></br>
                          hostname2:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Service not running</span></br>
                          hostname3:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Node not reachable</span><br>
                          hostname4:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>No data found</span>
                        </div>
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
                        <div class="text-uppercase">
                          hostname:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>No data found</span></br>
                          hostname1:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-success'>Farming</span></br>
                          hostname2:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Not Farming</span></br>
                        </div>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800">1234</div>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-800">123,4 TiB</div>
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
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card mb-4">
      <div class="card-header py-3">
          <h6 class="m-0 font-weight-bold text-primary">Harvester Overview</h6>
      </div>
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
                        <div class="text-uppercase">
                          hostname:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-secondary'>Querying service status</span></br>
                          hostname1:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-success'>Service running</span></br>
                          hostname2:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Service not running</span></br>
                          hostname3:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>Node not reachable</span><br>
                          hostname4:&nbsp;<span id='servicestatus_wallet_123' class='badge badge-danger'>No data found</span>
                        </div>
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
                        <div class="text-uppercase">
                          hostname:<br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/EDOUSB002</span></br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/EDOUSB003</span></br>
                          hostname1:<br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/KUMUSB005</span></br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/KUMUSB006</span></br>
                          hostname2:<br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/KUMUSB005</span></br>
                          <span id='servicestatus_wallet_123' class='badge badge-danger'>/mnt/KUMUSB006</span></br>
                        </div>
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
    </div>
  </div>
</div>

<!-- Page level plugins -->
<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/frameworks/bootstrap/vendor/chart.js/Chart.min.js"?>></script>
