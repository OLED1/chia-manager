<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_wallet_api = new Chia_Wallet_Api();
  $exchangerates_api = new Exchangerates_Api();

  $walletdata = $chia_wallet_api->getWalletData();
  print_r($exchangerates_api->queryExchangeRatesData("eur"));

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
      if($thiswallet['syncstatus'] == "Synced"){
        $synccard = "
          <div class='row'>
            <div class='col-lg-2 mb-4'>
              <div class='card bg-success text-white shadow'>
                <div class='card-body'>
                    Walletstatus: {$thiswallet['syncstatus']}
                    <div class='text-white-50 small'>Height: {$thiswallet['walletheight']}</div>
                </div>
              </div>
            </div>
          </div>
        ";
      }else{
        $synccard = "
          <div class='row'>
            <div class='col-lg-2 mb-4'>
              <div class='card bg-danger text-white shadow'>
                <div class='card-body'>
                    Walletstatus: {$thiswallet['syncstatus']}
                    <div class='text-white-50 small'>Height: {$thiswallet['walletheight']}</div>
                </div>
              </div>
            </div>
          </div>
        ";
      }
      echo "
      <div class='row'>
        <div class='col'>
          <div class='card shadow mb-4'>
            <div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>
              <h6 class='m-0 font-weight-bold text-primary'>Wallet (ID: {$thiswallet['walletid']}), Type: {$thiswallet['wallettype']}&nbsp;<span id='servicestatus_{$thiswallet['nodeid']}' class='badge statusbadge badge-secondary'>Querying service status</span></h6>
              <div class='dropdown no-arrow'>
                  <a class='dropdown-toggle' href='#' role='button' id='dropdownMenuLink_{$thiswallet['walletid']}'
                      data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                      <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
                  </a>
                  <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in'
                      aria-labelledby='dropdownMenuLink_{$thiswallet['walletid']}'>
                      <div class='dropdown-header'>Actions:</div>
                      <a data-wallet-id='{$thiswallet['walletid']}' class='dropdown-item refreshWalletInfo' href='#'>Refresh</a>
                      <a data-wallet-id='{$thiswallet['walletid']}' class='dropdown-item restartWalletService' href='#'>Restart wallet service</a>
                  </div>
              </div>
            </div>
            <div class='card-body'>
              {$synccard}
                <div class='row'>
                  <div class='col'>
                    <div class='card shadow mb-4'>
                      <div class='card-header'>
                        Wallet Address
                      </div>
                      <div class='card-body'>
                        {$thiswallet['walletaddress']}
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
                                              <div class='text-xs font-weight-bold text-success text-uppercase mb-1'>
                                                  Total XCH owning</div>
                                              <div class='h5 mb-0 font-weight-bold text-gray-800'>XCH " . $thiswallet['totalbalance'] . "</div>
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
                                              <div class='text-xs font-weight-bold text-primary text-uppercase mb-1'>
                                                  Total XCH in USD</div>
                                              <div class='h5 mb-0 font-weight-bold text-gray-800'>USD 40,000</div>
                                          </div>
                                          <div class='col-auto'>
                                              <i class='fas fa-dollar-sign fa-2x text-gray-300'></i>
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
                <div class='row'>
                <div class='col col-xl-5 col-lg-5'>
                  <div class='card shadow mb-4'>
                    <div class='card-header'>
                      Balance
                    </div>
                    <div class='card-body'>
                      <div class='table-responsive'>
                        <table class='table table-bordered' width='100%' cellspacing='0'>
                          <tbody>
                            <tr><td><strong>Total Balance</strong></td><td>" . $thiswallet['totalbalance'] . " xch (" . ($thiswallet['totalbalance'] * 1000000000000) . " mojo)</td></tr>
                            <tr><td><strong>Pending Total Balance</strong></td><td>" . $thiswallet['pendingtotalbalance'] . " xch  (" . ($thiswallet['pendingtotalbalance'] * 1000000000000) . " mojo)</td></tr>
                            <tr><td><strong>Spendable</strong></td><td>" . $thiswallet['spendable'] . " xch (" . ($thiswallet['spendable'] * 1000000000000) . " mojo)</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>";
    }
  }
  ?>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_wallet/js/chia_wallet.js"?>></script>
