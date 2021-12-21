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
  $overallData = $chia_overall_api->getOverallChiaData();
  if($overallData["status"] == 0 ) $overallData = $overallData["data"];
  else $overallData = [];

  $exchangerates_api = new Exchangerates_Api();
  $exchangeData = $exchangerates_api->getUserExchangeData(["userid" => $_COOKIE["user_id"]]);
?>
<style>
  .fs-2{
    font-size: 1.4rem;
  }
  .section-cmngt{
    border-radius: 0;
    border: none;
  }

  .section-cmngt h6{
    padding: 0.5rem;
  }

  .card-cmngt{
    background-color: #1d2832;
    padding: 1rem;
  }

  .percentage-change{
    margin-left: .8rem;
    font-size: .8rem;
  }
</style>
<div class="section-cmngt mb-4">
  <h6 class="font-weight-bold">Chia network</h6>
  <div class="row">
    <div class="col col-md-4">
    <div class="col mr-2 card-cmngt">
          <div class="font-weight-bold text-uppercase mb-1">
              Total Netspace
          </div>
          <div class="h5 mb-0 font-weight-bold mr-1">
            <?php echo $overallData["netspace"]; ?>
            <div class="float percentage-change">
              <i class="fas <?php echo (floatval($overallData["daychange_percent"]) > 0 ? "fa-arrow-up" : "fa-arrow-down"); ?>" style="color: <?php echo (floatval($overallData["daychange_percent"]) > 0 ? "green" : "red"); ?>"></i>&nbsp;<?php echo number_format($overallData["daychange_percent"], 2) . "% (24H)"; ?>
            </div>
            </div>
          <div class="col-auto">
            <i class="fas fa-hdd fa-2x text-gray-300"></i>
          </div>
        </div>

    </div>
    <div class="col col-md-4"></div>
    <div class="col col-md-4"></div>
  </div>
</div>





<div class="card mb-4">

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