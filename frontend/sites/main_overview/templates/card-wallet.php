<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';
  include_once("functions.php");

  if(!array_key_exists("sess_id", $_GET) || ! array_key_exists("user_id", $_GET) || ! array_key_exists("services_states", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $check_login = React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"]));
  $wallet_data = React\Promise\resolve((new Chia_Wallet_Api())->getWalletData());
  $overallData = React\Promise\resolve((new Chia_Overall_Api())->getOverallChiaData());
  $exchangeData = React\Promise\resolve((new Exchangerates_Api())->getUserExchangeData(["userid" => $_GET["user_id"]]));

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');

  React\Promise\all([$check_login, $wallet_data, $overallData, $exchangeData])->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $servicesStates = json_decode($_GET["services_states"], true);
    $walletData = $all_returned[1]["data"];
    if($all_returned[2]["status"] == 0 ) $overallData = $all_returned[2]["data"];
    else $overallData = [];
    $exchangeData = $all_returned[3]["data"];
?>
<div class="row">
  <div id="card-wallet" class="col">
    <div class="card mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Wallet Overview</h6>
        <div class='dropdown no-arrow'>
          <a class='dropdown-toggle' href='#' role='button' id='walletMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
            <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='walletMenu'>
            <div class='dropdown-header'>Actions:</div>
            <button id="refreshWalletInfo" class='dropdown-item wsbutton' href=''>Refresh</button>
          </div>
        </div>
      </div>
      <?php
        if(count($walletData) > 0){
          $hostchecks = "";
          $walletsyncstatus = "";
          $totalmojos = 0;
          foreach ($walletData as $nodeid => $nodedata) {
            $serviceStates = getServiceStates($servicesStates[$nodeid], 5);
            $hostname = $nodedata["hostinfo"]["hostname"];
            $hostchecks .= "{$hostname}:&nbsp;<span id='servicestatus_wallet_{$nodeid}' data-nodeid={$nodeid} class='badge nodestatus " . $serviceStates["statusicon"] . "'>" . $serviceStates["statustext"] . "</span><br>";
            foreach($nodedata["walletinfo"] AS $walletid => $walletdata){
              $walletsyncstatus .= "{$hostname} - Wallet {$walletid}:&nbsp;<span id='syncstatus_{$nodeid}_{$walletid}' data-nodeid={$nodeid} data-walletid={$walletid} class='badge walletstatus " . ($walletid > 0 && $walletdata['syncstatus'] == 2 ? "badge-success" : ($walletdata['syncstatus'] == 1 ? "badge-warning" : "badge-danger")) . "'>" . ($walletid > 0 ? ($walletdata['syncstatus'] == 2 ? "Synced" : ($walletdata['syncstatus'] == 1 ? "Syncing" : "Not synced"))."&nbsp;(Height: {$walletdata["walletheight"]})" : "No data found"). "</span></br>";
              $totalmojos += intval($walletdata["totalbalance"]);
            }
          }

          $totalxch = number_format($totalmojos / 1000000000000, 9);
          if(array_key_exists("price_usd", $overallData) && array_key_exists("exchangerate", $exchangeData)) $totalincurr = number_format($totalxch * floatval($overallData["price_usd"]) * floatval($exchangeData["exchangerate"]), 9);
          else $totalincurr = 0;
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
                <div class="card border-left-success shadow h-100 py-2">
                  <div class="card-body">
                    <div class="row no-gutters align-items-center">
                      <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Wallet sync status</div>
                          <?php echo $walletsyncstatus; ?>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-sync fa-2x text-gray-300"></i>
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
                        <div class="h5 mb-0 font-weight-bold text-gray-900">XCH <?php echo $totalxch; ?></div>
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
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total XCH (all Wallets) in <?php echo $exchangeData["defaultCurrency"]; ?></div>
                        <div class="h5 mb-0 font-weight-bold text-gray-900 text-uppercase"><?php echo "{$exchangeData["defaultCurrency"]} {$totalincurr}"; ?></div>
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
<?php }); ?>