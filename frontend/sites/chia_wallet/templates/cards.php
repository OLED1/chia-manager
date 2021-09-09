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

  $chia_wallet_api = new Chia_Wallet_Api();
  $chia_overall_api = new Chia_Overall_Api();
  $exchangerates_api = new Exchangerates_Api();
  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];

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

  if(array_key_exists("data", $walletdata) && count($walletdata["data"]) > 0){
    echo "<script nonce={$ini["nonce_key"]}> var chiaWalletData = " . json_encode($walletdata["data"]) . "; </script>";
    foreach($walletdata["data"] AS $nodeid => $nodedata){
      foreach($nodedata as $walletid => $thiswallet){
?>
<div class='row'>
  <div class='col'>
    <div class='card shadow mb-4'>
      <div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>
        <h6 class='m-0 font-weight-bold text-primary'><?php echo "Host: {$thiswallet['hostname']}, Wallet (ID: {$walletid}), Type: {$thiswallet['wallettype']}"; ?>&nbsp;
        <?php if(!is_numeric($thiswallet['walletid'])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
        <?php
            }else{
              if($nodes_states[$nodeid]["onlinestatus"] == 1){
                $statustext = "Node not reachable.";
                $statusicon = "badge-danger";
              }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
                if($nodes_states[$nodeid][$statusname] == 1){
                  $statustext = "Wallet service not running.";
                  $statusicon = "badge-danger";
                }else if($nodes_states[$nodeid][$statusname] == 0){
                  $statustext = "Wallet service running.";
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
          <a class='dropdown-toggle' href='#' role='button' id='dropdownMenuLink_<?php echo $thiswallet['nodeid']; ?>' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
            <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $thiswallet['nodeid']; ?>'>
            <div class='dropdown-header'>Actions:</div>
            <button data-node-id='<?php echo $nodeid; ?>' data-wallet-id='<?php echo $thiswallet['walletid']; ?>' class='dropdown-item refreshWalletInfo wsbutton' href='#'>Refresh</button>
            <button data-node-id='<?php echo $nodeid; ?>' data-wallet-id='<?php echo $thiswallet['walletid']; ?>' class='dropdown-item restartWalletService wsbutton' href='#'>Restart wallet service</button>
          </div>
        </div>
      </div>
      <?php if(is_numeric($thiswallet['walletid'])){ ?>
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
                <?php
                  $transactiondta = $chia_wallet_api->getWalletTransactions(["nodeid" => $nodeid, "walletid" => $thiswallet['walletid']]);
                  //print_r($transactiondta);
                  if($transactiondta["status"] == 0){
                    if(count($transactiondta["data"]) > 0){

                    }else{
                      $message = "<div class='card bg-warning text-white shadow'>
                                    <div class='card-body'>
                                      There are currently no transactions to show.
                                    </div>
                                </div>";
                    }
                  }else{
                    $message = "<div class='card bg-warning text-white shadow'>
                                  <div class='card-body'>
                                    {$transactiondta["message"]}
                                  </div>
                              </div>";
                  }
                ?>
                <div class='row'>
                  <div class='col'>
                    <div class='card shadow mb-4'>
                      <div class='card-body'>
                        <h6>Transactions Chart</h6>
                        <?php echo $message; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <div class='row'>
                  <div class='col'>
                    <div class='card shadow mb-4'>
                      <div class='card-body'>
                        <h6>Transactions Table</h6>
                        <?php echo $message; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer">
        Data queried at: <span id="querydate_<?php echo "{$thiswallet["nodeid"]}"; ?>"><?php echo "{$thiswallet["querydate"]}"; ?></span>
      </div>
    <?php }else{ ?>
      <div class="card-body">
        <div class="card bg-danger text-white shadow">
          <div class="card-body">
            There is currently no data to show! Please make a rescan of this system.
          </div>
        </div>
      </div>
    <?php } ?>
    </div>
  </div>
</div>
<?php
        }
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

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_wallet/js/chia_wallet.js"?>></script>
