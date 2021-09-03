<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $chia_overall_api = new Chia_Overall_Api();
  $overallData = $chia_overall_api->queryOverallData()["data"];

  $exchangerates_api = new Exchangerates_Api();
  $exchangeData = $exchangerates_api->getUserExchangeData(["userid" => $_COOKIE["user_id"]]);
?>
<div class="card mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
      <h6 class="m-0 font-weight-bold text-primary">Chia overall information</h6>
      <div class='dropdown no-arrow'>
        <a class='dropdown-toggle' href='#' role='button' id='overallMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
          <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
        </a>
        <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='overallMenu'>
          <div class='dropdown-header'>Actions:</div>
          <button id="refreshOverallInfo" class='dropdown-item wsbutton' href='#' onclick='refreshOverallInfo()'>Refresh</button>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $overallData["netspace"]; ?></div>
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
            <div class="card border-left-success shadow h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Current XCH price</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800 text-uppercase"><?php echo "{$exchangeData["defaultCurrency"]}&nbsp;" . number_format(floatval($overallData["price_usd"]) * floatval($exchangeData["exchangerate"]), 2); ?></div>
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
  </div>
</div>
