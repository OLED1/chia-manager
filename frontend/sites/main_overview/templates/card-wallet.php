<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  include_once("functions.php");
  $chia_wallet_api = new Chia_Wallet_Api();
  $walletData = $chia_wallet_api->getWalletData()["data"];

  $chia_overall_api = new Chia_Overall_Api();
  $overallData = $chia_overall_api->queryOverallData()["data"];

  $exchangerates_api = new Exchangerates_Api();
  $exchangeData = $exchangerates_api->getUserExchangeData(["userid" => $_COOKIE["user_id"]]);

  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];
?>
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
      $totalxch = 0.0;
      foreach ($walletData as $nodeid => $nodedata) {
        $serviceStates = getServiceStates($nodes_states, $nodeid, "Wallet");
        $hostchecks .= "{$nodedata[array_key_first($nodedata)]["hostname"]}:&nbsp;<span id='servicestatus_wallet_{$nodeid}' data-nodeid={$nodeid} class='badge nodestatus " . $serviceStates["statusicon"] . "'>" . $serviceStates["statustext"] . "</span><br>";
        foreach($nodedata AS $walletid => $walletdata){
          $walletsyncstatus .= "{$walletdata["hostname"]} - Wallet {$walletid}:&nbsp;<span id='syncstatus_{$nodeid}_{$walletid}' data-nodeid={$nodeid} data-walletid={$walletid} class='badge walletstatus " . ($walletid > 0 && $walletdata["syncstatus"] == "Synced" ? "badge-success" : "badge-danger") . "'>" . ($walletid > 0 ? $walletdata["syncstatus"]."&nbsp;(Height: {$walletdata["walletheight"]})" : "No data found"). "</span></br>";
          $totalxch += floatval($walletdata["totalbalance"]);
        }
      }
      $totalincurr = $totalxch * floatval($overallData["price_usd"]) * floatval($exchangeData["exchangerate"]);
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
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total XCH (all Wallets) in <?php echo $exchangeData["defaultCurrency"]; ?></div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800 text-uppercase"><?php echo "{$exchangeData["defaultCurrency"]} " . rtrim(sprintf('%.9F',$totalincurr), '0'); ?></div>
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
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/main_overview/js/card_wallet.js"?>></script>
