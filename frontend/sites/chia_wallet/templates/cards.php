<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_wallet_api = new Chia_Wallet_Api();
  $nodes_api = new Nodes_Api();
  $nodes_states = $nodes_api->queryNodesServicesStatus()["data"];
  $walletdata = $chia_wallet_api->getWalletData(["nodeid" => $_GET["nodeid"]]);
  $transactiondata = $chia_wallet_api->getWalletTransactions(["nodeid" => $_GET["nodeid"]]);

  if(array_key_exists("defaultCurrency", $_GET) && !is_null($_GET["defaultCurrency"])) $defaultCurrency = $_GET["defaultCurrency"];
  else $defaultCurrency = "usd";
  if(array_key_exists("exchangerate", $_GET) && !is_null($_GET["exchangerate"])) $exchangerate = $_GET["exchangerate"];
  else $exchangerate = 0;
  if(array_key_exists("chiapriceindefcurr", $_GET) && !is_null($_GET["chiapriceindefcurr"])) $chiapriceindefcurr = $_GET["chiapriceindefcurr"];
  else $chiapriceindefcurr = 0;

  if(array_key_exists("data", $walletdata) && count($walletdata["data"]) > 0){
    echo "<script nonce={$ini["nonce_key"]}>
            chiaWalletData[" . $_GET["nodeid"] . "] = " . json_encode($walletdata["data"][$_GET["nodeid"]]) . ";
            transactionData[" . $_GET["nodeid"] . "] = " . json_encode($transactiondata["data"][$_GET["nodeid"]]) . ";
          </script>";
    foreach($walletdata["data"] AS $nodeid => $nodedata){
      foreach($nodedata as $walletid => $thiswallet){
        $wallettype = function(int $wallettype){
          switch($wallettype){
            case 0: return "STANDARD_WALLET";
            case 9: return "POOLING_WALLET";
            default: return "UNKNOWN_WALLET";
          }
        };
?>
<div class='row'>
  <div class='col'>
    <div class='card shadow mb-4'>
      <div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>
        <h6 class='m-0 font-weight-bold text-primary'><?php echo "Host: {$thiswallet['hostname']}, Wallet (ID: {$walletid}), Type: {$wallettype($thiswallet['wallettype'])}"; ?>&nbsp;
        <?php if(!is_numeric($thiswallet['walletid'])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
        <?php
            }else{
              if($nodes_states[$nodeid]["onlinestatus"] == 1){
                $statustext = "Node not reachable.";
                $statusicon = "badge-danger";
              }else if($nodes_states[$nodeid]["onlinestatus"] == 0){
                if($nodes_states[$nodeid]["walletstatus"] == 1){
                  $statustext = "Wallet service not running.";
                  $statusicon = "badge-danger";
                }else if($nodes_states[$nodeid]["walletstatus"] == 0){
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
          <div class='col-5'>
            <div class='row'>
              <div class='col mb-4'>
                <div id="<?php echo "walletstatus_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='card <?php echo ($thiswallet['syncstatus'] == "Synced" ? "bg-success" : "bg-danger"); ?> text-white shadow'>
                  <div class='card-body'>
                    Walletstatus: <?php echo $thiswallet['syncstatus']; ?>
                    <div class='text-white-50 small'>Height: <?php echo $thiswallet['walletheight']; ?></div>
                  </div>
                </div>
              </div>
            </div>
            <div class='row'>
              <div class='col'>
                <div class='row'>
                  <div class='col mb-4'>
                    <div class='card text-white shadow'>
                      <div class='card-body'>
                        Current blocks synced
                        <div class="progress">
                          <?php $syncpercent = number_format(($thiswallet['walletheight'] / $_GET["chia_overall_data"]["xch_blockheight"] * 100), 2); ?>
                          <div id="<?php echo "sync_progress_{$nodeid}_{$thiswallet['walletid']}"; ?>" class="progress-bar bg-primary" role="progressbar" style="width: <?php echo "{$syncpercent}"; ?>%;" aria-valuenow="<?php echo "{$syncpercent}"; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo "{$syncpercent}% - {$thiswallet['walletheight']}&nbsp;/&nbsp;{$_GET["chia_overall_data"]["xch_blockheight"]}" ?></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class='row'>
              <div class='col mb-4'>
                <div class='card border-left-success shadow h-100 py-2'>
                  <div class='card-body'>
                    <div class='row no-gutters align-items-center'>
                      <div class='col mr-2'>
                        <div class='text-xs font-weight-bold text-success text-uppercase mb-1'>Total XCH owning</div>
                        <div id="<?php echo "totalbalance_xch_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='h5 mb-0 font-weight-bold text-gray-800'>XCH <?php echo number_format(($thiswallet['totalbalance'] / 1000000000000), 7); ?></div>
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
                        <div id="<?php echo "totalbalance_def_currency_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='h5 mb-0 font-weight-bold text-gray-800 text-uppercase'><?php echo "{$defaultCurrency}&nbsp;" . number_format($chiapriceindefcurr*($thiswallet['totalbalance'] / 1000000000000), 7); ?></div>
                      </div>
                      <div class='col-auto'>
                        <i class='fas fa-money-bill-wave fa-2x text-gray-300'></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php if($thiswallet['wallettype'] < 9){ ?>
            <div class="row">
              <div class="col">
                <div class='card shadow mb-4'>
                  <div class='card-header'>Wallet Address</div>
                  <div id="<?php echo "walletaddress_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='card-body'><?php  echo $thiswallet['walletaddress']; ?></div>
                </div>
              </div>
            </div>
            <?php } ?>
            <div class="row">
              <div class="col">
                <div class='card shadow mb-4'>
                  <div class='card-header'>Balance</div>
                  <div class='card-body'>
                    <div class='table-responsive'>
                      <table class='table table-bordered' width='100%' cellspacing='0'>
                        <tbody>
                          <tr><td><strong>Total Balance</strong></td><td id="<?php echo "totalbalance_balance_chart_{$nodeid}_{$thiswallet['walletid']}"; ?>"><?php echo number_format(($thiswallet['totalbalance'] / 1000000000000), 7) . " xch ({$thiswallet['totalbalance']} mojo)"; ?></td></tr>
                          <tr><td><strong>Pending Total Balance</strong></td><td id="<?php echo "pendingtotalbalance_balance_chart_{$nodeid}_{$thiswallet['walletid']}"; ?>"><?php echo number_format(($thiswallet['pendingtotalbalance'] / 1000000000000), 7) . " xch ({$thiswallet['pendingtotalbalance']} mojo)"; ?></td></tr>
                          <tr><td><strong>Spendable</strong></td><td id="<?php echo "spendable_balance_chart_{$nodeid}_{$thiswallet['walletid']}"; ?>"><?php echo number_format(($thiswallet['spendable'] / 1000000000000), 7) ." xch ({$thiswallet['spendable']} mojo)"; ?></td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class='col-7'>
            <div class='row'>
              <div class='col'>
                <div class='card shadow mb-4'>
                  <div class='card-body'>
                    <h6>Transactions Chart</h6>
                    <?php
                      if(array_key_exists($nodeid, $transactiondata["data"]) && count($transactiondata["data"][$nodeid][$thiswallet['walletid']]) > 0){
                    ?>
                    <canvas id="<?php echo "transactions_chart_{$nodeid}_{$thiswallet['walletid']}"; ?>" class="transactionchart_<?php echo $nodeid; ?>"></canvas>
                    <?php
                      }else{
                        echo "<div class='card bg-warning text-white shadow'>
                                <div class='card-body'>
                                  There are currently no transactions to show.
                                </div>
                              </div>";
                      }
                    ?>
                  </div>
                </div>
              </div>
            </div>
            <div class='row'>
              <div class='col'>
                <div class='card shadow mb-4'>
                  <div class='card-body'>
                    <h6>Transactions Table</h6>
                    <?php
                      if(array_key_exists($nodeid, $transactiondata["data"]) && count($transactiondata["data"][$nodeid][$thiswallet['walletid']]) > 0){
                    ?>
                    <div class="table-responsive">
                        <table class="table table-bordered dataTable_<?php echo $nodeid; ?>" id="<?php echo "transactions_{$nodeid}_{$thiswallet['walletid']}"; ?>" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                  <th>ID</th>
                                  <th>Date</th>
                                  <th>Amount</th>
                                  <th>Receiver</th>
                                  <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                            <tfoot>
                                <tr>
                                  <th>ID</th>
                                  <th>Date</th>
                                  <th>Amount</th>
                                  <th>Receiver</th>
                                  <th>Actions</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php
                      }else{
                        echo "<div class='card bg-warning text-white shadow'>
                                <div class='card-body'>
                                  There are currently no transactions to show.
                                </div>
                              </div>";
                      }
                    ?>
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