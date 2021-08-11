<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_wallet_api = new Chia_Wallet_Api();
  $chia_overall_api = new Chia_Overall_Api();
  $exchangerates_api = new Exchangerates_Api();

  $walletdata = $chia_wallet_api->getWalletData();
  $chia_overall_data = $chia_overall_api->queryOverallData();

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

  $chiapriceindefcurr = number_format(floatval($chia_overall_data["data"]["price_usd"]) * floatval($exchangerate), 2);

  echo "<script> var siteID = 5; </script>";
  echo "<script> var chiaWalletData = " . json_encode($walletdata["data"]) . "; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Wallets</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your wallets. This site is only readonly for security reasons.
      </div>
    </div>
  </div>
</div>
<h5>My Wallets</h5>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query wallet information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<div id="walletcontainer">
  <?php if(count($walletdata["data"]) == 0) { ?>
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
  <?php }else{
    foreach ($walletdata["data"] as $arrkey => $thiswallet){
  ?>
  <div class='row'>
    <div class='col'>
      <div class='card shadow mb-4'>
        <div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>
          <h6 class='m-0 font-weight-bold text-primary'><?php echo "Wallet (ID: {$thiswallet['walletid']}), Type: {$thiswallet['wallettype']}"; ?>&nbsp;<span id='servicestatus_<?php echo $thiswallet['nodeid']; ?>' class='badge statusbadge badge-secondary'>Querying service status</span></h6>
          <div class='dropdown no-arrow'>
            <a class='dropdown-toggle' href='#' role='button' id='dropdownMenuLink_<?php echo $thiswallet['nodeid']; ?>' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
              <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
            </a>
            <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $thiswallet['nodeid']; ?>'>
              <div class='dropdown-header'>Actions:</div>
              <a data-wallet-id='<?php echo $thiswallet['nodeid']; ?>' class='dropdown-item refreshWalletInfo' href='#'>Refresh</a>
              <a data-wallet-id='<?php echo $thiswallet['nodeid']; ?>' class='dropdown-item restartWalletService' href='#'>Restart wallet service</a>
            </div>
          </div>
        </div>
        <div class='card-body'>
          <div class='row'>
            <div class='col'>
              <div class='row'>
                <div class='col-5 mb-4'>
                  <div class='card <?php echo ($thiswallet['syncstatus'] == "Synced" ? "bg-success" : "bg-danger"); ?> text-white shadow'>
                    <div class='card-body'>
                      Walletstatus: <?php echo $thiswallet['syncstatus']; ?>
                      <div class='text-white-50 small'>Height: <?php echo $thiswallet['walletheight']; ?></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <div class='card shadow mb-4'>
                    <div class='card-header'>Wallet Address</div>
                    <div class='card-body'><?php echo $thiswallet['walletaddress']; ?></div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col">
                  <div class='card shadow mb-4'>
                    <div class='card-header'>Balance</div>
                    <div class='card-body'>
                      <div class='table-responsive'>
                        <table class='table table-bordered' width='100%' cellspacing='0'>
                          <tbody>
                            <tr><td><strong>Total Balance</strong></td><td><?php echo "{$thiswallet['totalbalance']} xch (" . ($thiswallet['totalbalance'] * 1000000000000) . " mojo)"; ?></td></tr>
                            <tr><td><strong>Pending Total Balance</strong></td><td><?php echo "{$thiswallet['pendingtotalbalance']} xch  (" . ($thiswallet['pendingtotalbalance'] * 1000000000000) . " mojo)"; ?></td></tr>
                            <tr><td><strong>Spendable</strong></td><td><?php echo "{$thiswallet['spendable']} xch (" . ($thiswallet['spendable'] * 1000000000000) . " mojo)"; ?></td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class='col'>
              <div class='card shadow mb-4'>
                <div class='card-body'>
                  <div class='row'>
                    <div class='col mb-4'>
                      <div class='card border-left-success shadow h-100 py-2'>
                        <div class='card-body'>
                          <div class='row no-gutters align-items-center'>
                            <div class='col mr-2'>
                              <div class='text-xs font-weight-bold text-success text-uppercase mb-1'>Total XCH owning</div>
                              <div class='h5 mb-0 font-weight-bold text-gray-800'>XCH <?php echo $thiswallet['totalbalance']; ?></div>
                            </div>
                            <div class='col-auto'>
                              <i class='fas fa-wallet fa-2x text-gray-300'></i>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class='row'>
                    <div class='col mb-4'>
                      <div class='card border-left-primary shadow h-100 py-2'>
                        <div class='card-body'>
                          <div class='row no-gutters align-items-center'>
                            <div class='col mr-2'>
                              <div class='text-xs font-weight-bold text-primary text-uppercase mb-1'>Total XCH in <?php echo $defaultCurrency; ?></div>
                              <div class='h5 mb-0 font-weight-bold text-gray-800 text-uppercase'><?php echo "{$defaultCurrency}&nbsp;" . ($chiapriceindefcurr*$thiswallet['totalbalance']); ?></div>
                            </div>
                            <div class='col-auto'>
                              <i class='fas fa-money-bill-wave fa-2x text-gray-300'></i>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class='row'>
                    <div class='col'>
                      <div class='card shadow mb-4'>
                        <div class='card-body'>
                          <h6>Transactions Chart</h6>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class='row'>
                    <div class='col'>
                      <div class='card shadow mb-4'>
                        <div class='card-body'>
                          <h6>Transactions Table</h6>
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
  <?php
      }
    }
  ?>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_wallet/js/chia_wallet.js"?>></script>
