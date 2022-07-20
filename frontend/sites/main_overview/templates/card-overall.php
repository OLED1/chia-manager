<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || ! array_key_exists("user_id", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $check_login = React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"]));
  $chia_overall = React\Promise\resolve((new Chia_Overall_Api())->getOverallChiaData());
  $exchangeData = React\Promise\resolve((new Exchangerates_Api())->getUserExchangeData(["userid" => $_GET["user_id"]]));

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');

  React\Promise\all([$check_login, $chia_overall, $exchangeData])->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $overallData = $all_returned[1];
    if($overallData["status"] == 0){
      $overallData = $overallData["data"];
    }else{ 
      $overallData = [];
    }

    $exchangeData = $all_returned[2]["data"];
?>
<div class="row">
  <div id="card-overall" class="col">
    <div class="card mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
          <h6 class="m-0 font-weight-bold text-primary"><span style="font-size: 0.9rem">Chia®</span> overall information</h6>
          <div class='dropdown no-arrow'>
            <a class='dropdown-toggle' href='#' role='button' id='overallMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
              <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
            </a>
            <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='overallMenu'>
              <div class='dropdown-header'>Actions:</div>
              <button id="refreshOverallInfo" class='dropdown-item wsbutton' href=''>Refresh</button>
            </div>
          </div>
      </div>
      <div class="card-body">
        <div class="row">
          <?php
            if(count($overallData) > 0 && count($exchangeData) > 0){
          ?>
          <div class="col">
            <div class="row">
              <div class="col-xl-3 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Netspace</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-900"><?php echo $overallData["netspace"]; ?></div>
                                <i class="fas <?php echo (floatval($overallData["daychange_percent"]) > 0 ? "fa-arrow-up" : "fa-arrow-down"); ?>" style="color: <?php echo (floatval($overallData["daychange_percent"]) > 0 ? "green" : "red"); ?>"></i>&nbsp;<?php echo number_format($overallData["daychange_percent"], 2) . "% (24H)"; ?>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-hdd fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
              <div class="col-xl-3 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                  <div class="card-body">
                    <div class="row no-gutters align-items-center">
                      <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Current block height</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-900 text-uppercase"><?php echo $overallData["xch_blockheight"]; ?></div>
                        <div class="text-uppercase">
                          &nbsp;
                        </div>
                      </div>
                      <div class="col-auto">
                        <i class="fas fa-trailer fa-2x text-gray-300"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-xl-3 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                  <div class="card-body">
                    <div class="row no-gutters align-items-center">
                      <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Current XCH price</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-900 text-uppercase"><?php echo "{$exchangeData["defaultCurrency"]}&nbsp;" . number_format(floatval($overallData["price_usd"]) * floatval($exchangeData["exchangerate"]), 2); ?></div>
                        <div class="text-uppercase">
                          <i class="fas fa-arrow-down" style="color: red;"></i>&nbsp;<?php echo "{$exchangeData["defaultCurrency"]}&nbsp;" . number_format(floatval($overallData["daymin_24h_usd"]) * floatval($exchangeData["exchangerate"]), 2); ?>
                          <i class="fas fa-arrow-up" style="color: green;"></i>&nbsp;<?php echo "{$exchangeData["defaultCurrency"]}&nbsp;" . number_format(floatval($overallData["daymax_24h_usd"]) * floatval($exchangeData["exchangerate"]), 2); ?>
                          &nbsp;(24h)
                        </div>
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
          <?php }else{ ?>
          <div class="col">
              <div class="card bg-danger text-white shadow">
                  <div class="card-body">
                      No chia overall data found
                      <div class="text-white-50 small">Something seems not to be working properly. No data has been received from external source.</div>
                  </div>
              </div>
          </div>
          <?php } ?>
        </div>
        <div class="row">
          <div class="col">
            <?php if(array_key_exists("querydate", $overallData)){ ?>
            Data queried: <?php echo $overallData["querydate"]; ?>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php }); ?>