<?php
  include("../standard_headers.php");
  echo "<script nonce={$ini["nonce_key"]}> var siteID = 5; </script>";
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
<div id="walletcontainer">
<?php include("templates/cards.php"); ?>
</div>
<div class="modal fade" id="transactiondetailsmodal" data-nodeid="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Transaction Info (Nodeid: <span id="transaction-nodeid"></span>, Walletid:  <span id="transaction-walletid"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <nav>
          <div class="nav nav-tabs" id="nav-tab" role="tablist">
            <a class="nav-item nav-link active" id="transaction-summary-tab" data-toggle="tab" href="#transaction-summary" role="tab" aria-controls="transaction-summary" aria-selected="true">Summary</a>
            <a class="nav-item nav-link" id="transaction-extended-tab" data-toggle="tab" href="#transaction-extended" role="tab" aria-controls="transaction-extended" aria-selected="false">More</a>
          </div>
        </nav>
        <div class="tab-content" id="nav-tabContent" style="min-height: 30em; margin-top: 1em;">
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
                    </div>
                    <div id="name" class="col" style="word-wrap: break-word;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>To Wallet:</b>
                    </div>
                    <div id="to_address" class="col infotext" style="word-wrap: break-word;">
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
                    </div>
                    <div id="parent_coin_info" class="col" style="word-wrap: break-word;"></div>
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
                    </div>
                    <div id="to_puzzle_hash" class="col" style="word-wrap: break-word;"></div>
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
