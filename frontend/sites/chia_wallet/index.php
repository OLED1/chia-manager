<?php
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();
  $all_nodes = $nodes_api->getConfiguredNodes(["nodetypenum" => 5]);
  $services_states = $nodes_api->getCurrentChiaNodesUPAndServiceStatus();
  if(array_key_exists("data", $services_states)){
    $services_states = $services_states["data"];
  }else{
    $services_states = [];
  }

  $chia_nodes = [];
  if(array_key_exists("data", $all_nodes)){
    foreach($all_nodes["data"] AS $nodeid => $nodedata){
      if($nodedata["authtype"] == 2){
        $thishostinfo["hostname"] = $nodedata["hostname"];
        $thishostinfo["nodeid"] = $nodedata["id"];
        $thishostinfo["nodeauthhash"] = $nodedata["nodeauthhash"];
        $chia_nodes[$nodedata["id"]] = $thishostinfo;
      }
    }
  }

  $chia_overall_api = new Chia_Overall_Api();
  $exchangerates_api = new Exchangerates_Api();
  $defaultCurrency = $exchangerates_api-> getUserDefaultCurrency($_COOKIE["user_id"]);
  $chia_overall_data = $chia_overall_api->getOverallChiaData();

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

  echo "<script nonce={$ini["nonce_key"]}>
    var siteID = 5;
    var chiaNodes = " . json_encode($chia_nodes) . ";
    var chiaWalletData = {};
    var transactionData = {};
    var defaultCurrency = '{$defaultCurrency}';
    var exchangerate = {$exchangerate};
    var chiapricedefcurr = {$chiapriceindefcurr};
    var chiaoveralldata = " . json_encode($chia_overall_data["data"]) . ";
    var services_states = " . json_encode($services_states) . ";
  </script>";
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
        <button id="queryAllNodes" type="button" class="btn btn-secondary wsbutton">Query wallet information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<?php foreach($chia_nodes AS $arrkey => $nodeinfo){ ?>
  <div id="walletcontainer_<?php echo $nodeinfo["nodeid"]; ?>">
    <?php
        $_GET['nodeid'] = $nodeinfo["nodeid"];
        $_GET['defaultCurrency'] = $defaultCurrency;
        $_GET['exchangerate'] = $exchangerate;
        $_GET['chiapriceindefcurr'] = $chiapriceindefcurr;
        $_GET['chia_overall_data'] = $chia_overall_data["data"];
        include("templates/cards.php"); 
    ?> 
  </div>
<?php } ?>
<div class="modal fade" id="transactiondetailsmodal" style="text-align: center;" data-nodeid="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document" style="text-align: left; max-width: 100%; width: auto !important; display: inline-block; height: 30em;">
    <div class="modal-content" style="height: 30em;">
      <div class="modal-header">
        <h5 class="modal-title">Transaction Info (Nodeid: <span id="transaction-nodeid"></span>, Walletid:  <span id="transaction-walletid"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style="height: 20em;">
        <nav>
          <div class="nav nav-tabs" id="nav-tab" role="tablist">
            <a class="nav-item nav-link active" id="transaction-summary-tab" data-toggle="tab" href="#transaction-summary" role="tab" aria-controls="transaction-summary" aria-selected="true">Summary</a>
            <a class="nav-item nav-link" id="transaction-extended-tab" data-toggle="tab" href="#transaction-extended" role="tab" aria-controls="transaction-extended" aria-selected="false">More</a>
          </div>
        </nav>
        <div class="tab-content" id="nav-tabContent" style="margin-top: 1em;">
          <div class="tab-pane fade show active" id="transaction-summary" role="tabpanel" aria-labelledby="transaction-summary-tab">
            <div class="row">
              <div class="col">
                <div id="transaction-summary" class="container">
                  <div class="row">
                    <div class="col">
                      <b>Date:</b>
                      <span id="date"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Confirmed:</b>
                      <span id="confirmed"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Transaction direction:</b>
                      <span id="direction"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Transaction ID:</b>
                      <br>
                      <span id="name" style="word-wrap: break-word;"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>To Wallet:</b>
                      <br>
                      <span id="to_address" style="word-wrap: break-word;"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Amount:</b>
                      <span id="amount" style="word-wrap: break-word;"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Amount in <span class="currency_code"></span>:</b>
                      <span id="amount_currency" style="text-align: right;"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Fee amount:</b>
                      <span id="fee_amount"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Fee amount in <span class="currency_code"></span>:</b>
                      <span id="fee_amount_currency"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="transaction-extended" role="tabpanel" aria-labelledby="transaction-extended-tab">
            <div class="row">
              <div class="col">
                <div id="transaction-more" class="container">
                  <div class="row">
                    <div class="col">
                      <b>Parent coin info:</b>
                      <br>
                      <span id="parent_coin_info" style="word-wrap: break-word;"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Confirmed at height:</b>
                      <span id="confirmed_at_height"></span>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>To puzzle hash:</b>
                      <br>
                      <span id="to_puzzle_hash" style="word-wrap: break-word;"></span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_wallet/js/chia_wallet.js"?>></script>

