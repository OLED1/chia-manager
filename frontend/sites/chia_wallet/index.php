<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_wallet_api = new Chia_Wallet_Api();
  $walletdata = $chia_wallet_api->getWalletData();

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
<div id="walletcontainer">
  <?php if(count($walletdata["data"]) == 0) { ?>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-body">
            There are currently no wallets to show.
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
            <h6 class='m-0 font-weight-bold text-primary'>Wallet (ID: {$thiswallet['walletid']}), Type: {$thiswallet['wallettype']}, Status: {$thiswallet['syncstatus']}&nbsp;" . ($thiswallet['syncstatus'] == "Synced" ? "<i class='fas fa-check-circle' style='color: green;'" : "<i class='fas fa-times-circle' style='color: red;'") . "></i>&nbsp;<span id='servicestatus_{$thiswallet['nodeid']}' class='badge badge-secondary'>Querying service status</span></h6>
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
                <div class='col col-xl-5 col-lg-5'>
                  <div class='card shadow mb-4'>
                    <div class='card-header'>
                      Wallet Address
                    </div>
                    <div class='card-body'>
                      {$thiswallet['walletaddress']}
                    </div>
                  </div>
                </div>
                <div class='col col-xl-5 col-lg-5'>
                  <div class='card shadow mb-4'>
                    <div class='card-body'>
                      <h6>Transactions</h6>
                      Here will be a chart about transactions
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
                          <tr><td><strong>Total Balance</strong></td><td>" . number_format($thiswallet['totalbalance'], 1) . " xch (" . (number_format($thiswallet['totalbalance'], 1) * 1000000000000) . " mojo)</td></tr>
                          <tr><td><strong>Pending Total Balance</strong></td><td>" . number_format($thiswallet['pendingtotalbalance'], 1) . " xch  (" . (number_format($thiswallet['pendingtotalbalance'], 1) * 1000000000000) . " mojo)</td></tr>
                          <tr><td><strong>spendable</strong></td><td>" . number_format($thiswallet['spendable'], 1) . " xch (" . (number_format($thiswallet['spendable'], 1) * 1000000000000) . " mojo)</td></tr>
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
