<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET) || !array_key_exists("nodeid", $_GET) || !array_key_exists("defaultCurrency", $_GET) ||
    !array_key_exists("exchangerate", $_GET) || !array_key_exists("chiapriceindefcurr", $_GET) || !array_key_exists("chia_overall_data", $_GET)
  ){
    echo "Incomplete Request.";
    die();
  }

  $site_data_to_load = [
    React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    React\Promise\resolve((new Chia_Wallet_Api())->getWalletData(["nodeid" => $_GET["nodeid"]]))
  ];

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $all_wallet_data = $all_returned[1];
    $walletdata = [];
    $transactions = [];
    if(array_key_exists("data", $all_wallet_data) && array_key_exists($_GET["nodeid"], $all_wallet_data["data"]) && 
        array_key_exists("walletinfo", $all_wallet_data["data"][$_GET["nodeid"]]) && !is_null($all_wallet_data["data"][$_GET["nodeid"]]["walletinfo"])){
      $walletdata = $all_wallet_data["data"][$_GET["nodeid"]]["walletinfo"];
    }
    if(array_key_exists("data", $all_wallet_data) && array_key_exists($_GET["nodeid"], $all_wallet_data["data"]) && 
      array_key_exists("transactions", $all_wallet_data["data"][$_GET["nodeid"]]) && !is_null($all_wallet_data["data"][$_GET["nodeid"]]["transactions"])){
      $transactions = $all_wallet_data["data"][$_GET["nodeid"]]["transactions"];
    }

    if(array_key_exists("defaultCurrency", $_GET) && !is_null($_GET["defaultCurrency"])) $defaultCurrency = $_GET["defaultCurrency"];
    else $defaultCurrency = "usd";
    if(array_key_exists("exchangerate", $_GET) && !is_null($_GET["exchangerate"])) $exchangerate = $_GET["exchangerate"];
    else $exchangerate = 0;
    if(array_key_exists("chiapriceindefcurr", $_GET) && !is_null($_GET["chiapriceindefcurr"])) $chiapriceindefcurr = $_GET["chiapriceindefcurr"];
    else $chiapriceindefcurr = 0;

    $nodeid = $_GET["nodeid"];
    $hostinfo = $all_wallet_data["data"][$nodeid]["hostinfo"];
    $chia_overall_data = json_decode($_GET["chia_overall_data"], true);

    if(count($walletdata) > 0){
      echo "<script nonce={$ini["nonce_key"]}>
              chiaWalletData[{$nodeid}] = " . json_encode($walletdata) . ";
              transactionData[{$nodeid}] = " . json_encode($transactions) . ";
            </script>";
      foreach($walletdata as $walletid => $thiswallet){
        $wallettype = function($wallettype){
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
        <h6 class='m-0 font-weight-bold text-primary'><?php echo "Host: {$hostinfo['hostname']}, Wallet (ID: {$walletid}), Type: {$wallettype($thiswallet['wallettype'])}"; ?>&nbsp;
        <?php if(!is_numeric($thiswallet['walletid'])){ ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-danger'>No data found</span>
        <?php
            }else{
        ?>
          <span id='servicestatus_<?php echo $nodeid; ?>' data-node-id='<?php echo $nodeid; ?>' class='badge statusbadge badge-secondary'>Processing...</span>
        <?php } ?>
        </h6>
        <div class='dropdown no-arrow'>
          <a class='dropdown-toggle' href='#' role='button' id='dropdownMenuLink_<?php echo $nodeid; ?>' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
            <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_<?php echo $nodeid; ?>'>
            <div class='dropdown-header'>Actions:</div>
            <button data-node-id='<?php echo $nodeid; ?>' data-wallet-id='<?php echo $thiswallet['walletid']; ?>' class='dropdown-item refreshWalletInfo wsbutton' href='#'>Refresh</button>
            <button data-node-id='<?php echo $nodeid; ?>' data-wallet-id='<?php echo $thiswallet['walletid']; ?>' class='dropdown-item restartWalletService wsbutton' href='#'>Restart wallet service</button>
          </div>
        </div>
      </div>
      <?php if(is_numeric($thiswallet['walletid'])){ ?>
      <div class='card-body'>
        <div class='row'>
          <div class='col-12 col-md-6 mb-4'>
            <div id="<?php echo "walletstatus_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='card <?php echo ($thiswallet['syncstatus'] == 2 ? "bg-success" : ($thiswallet['syncstatus'] == 1 ? "bg-warning" : "bg-danger")); ?> text-white shadow'>
              <div class='card-body'>
                Walletstatus: <?php echo ($thiswallet['syncstatus'] == 2 ? "Synced" : ($thiswallet['syncstatus'] == 1 ? "Syncing" : "Not synced")); ?>
                <div class='text-white-50 small'>Height: <?php echo $thiswallet['walletheight']; ?></div>
              </div>
            </div>
          </div>
          <div class='col-12 col-md-6 mb-4'>
            <div class='card text-white shadow'>
              <div class='card-body'>
                <?php $syncpercent = number_format(($thiswallet['walletheight'] / $chia_overall_data["xch_blockheight"] * 100), 2); ?>
                Current blocks synced <?php echo ($syncpercent <= 40 ? "({$syncpercent}% - {$thiswallet['walletheight']}&nbsp;/&nbsp;{$chia_overall_data["xch_blockheight"]})" : ""); ?>
                <div class="progress">
                  <div id="<?php echo "sync_progress_{$nodeid}_{$thiswallet['walletid']}"; ?>" class="progress-bar bg-primary" role="progressbar" style="width: <?php echo "{$syncpercent}"; ?>%;" aria-valuenow="<?php echo "{$syncpercent}"; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo "{$syncpercent}% - {$thiswallet['walletheight']}&nbsp;/&nbsp;{$chia_overall_data["xch_blockheight"]}" ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class='row'>
          <div class='col-12 col-md-6 mb-4'>
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
          <div class='col-12 col-md-6 mb-4'>
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
        <div class="row">
          <div class="col-12 col-md-6 mb-4">
            <div class='card shadow'>
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
          <?php if($thiswallet['wallettype'] < 9){ ?>
          <div class="col-12 col-md-6 mb-4">
            <div class='card shadow'>
              <div class='card-header'>Wallet Address</div>
              <div id="<?php echo "walletaddress_{$nodeid}_{$thiswallet['walletid']}"; ?>" class='card-body'><?php  echo $thiswallet['walletaddress']; ?></div>
            </div>
          </div>
        </div>
        <?php } ?>
        <div class='row'>
          <div class='col-12 col-md-6 mb-4'>
            <div class='card shadow mb-4'>
              <div class='card-body'>
                <h6>Transactions Chart</h6>
                <?php
                  if(array_key_exists($thiswallet['walletid'], $transactions) && count($transactions[$thiswallet['walletid']]) > 0){
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
          <div class='col-12 col-md-6 mb-4'>
            <div class='card shadow mb-4'>
              <div class='card-body'>
                <h6>Transactions Table</h6>
                <?php
                  if(array_key_exists($thiswallet['walletid'], $transactions) && count($transactions[$thiswallet['walletid']]) > 0){
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
                <?php }else{ ?>
                <div class='card bg-warning text-white shadow'>
                  <div class='card-body'>
                    There are currently no transactions to show.
                  </div>
                </div>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="card-footer">
        Data queried at: <span id="querydate_<?php echo "{$nodeid}"; ?>"><?php echo "{$thiswallet["querydate"]}"; ?></span>
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
<?php } 
  });
?>