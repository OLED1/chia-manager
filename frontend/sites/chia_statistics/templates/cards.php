<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Statistics\Chia_Statistics_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $days_past = 4;
  $to = new \DateTime("now");
  $from = new \DateTime("now");
  $from->modify("-{$days_past} day");
  $data = array("from" => $from->format("Y-m-d H:i:s"), "to" => $to->format("Y-m-d H:i:s"));

  $chia_statistics_api = new Chia_Statistics_Api();
  $exchangerates_api = new Exchangerates_Api();

  $site_data_to_load = [
    React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    React\Promise\resolve($chia_statistics_api->getNetspaceHistory($data)),
    React\Promise\resolve($chia_statistics_api->getBlockheightHistory($data)),
    React\Promise\resolve($chia_statistics_api->getXCHValueHistory($data)),
    React\Promise\resolve($exchangerates_api->getUserExchangeData(["userid" => $_GET["user_id"]]))
  ];

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini, $days_past, $to, $from){

    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $historynetspace = $all_returned[1];
    if(array_key_exists("data", $historynetspace)) $historynetspace = $historynetspace["data"];
    else $historynetspace = [];

    $historyblockheight = $all_returned[2];
    if(array_key_exists("data", $historyblockheight)) $historyblockheight = $historyblockheight["data"];
    else $historyblockheight = [];

    $historyxchvalue = $all_returned[3];
    if(array_key_exists("data", $historyxchvalue)) $historyxchvalue = $historyxchvalue["data"];
    else $historyxchvalue = [];

    $exchangerate = $all_returned[4];
    if($exchangerate["status"] == 0 && array_key_exists("data", $exchangerate) && 
      array_key_exists("defaultCurrency", $exchangerate["data"]) && array_key_exists("exchangerate", $exchangerate["data"]))
    {
      $defaultCurrency = $exchangerate["data"]["defaultCurrency"];
      $exchangerate = $exchangerate["data"]["exchangerate"];
    }else{
      $defaultCurrency = "usd";
      $exchangerate = 1;
    }

    if(count($historyxchvalue) > 0 && $defaultCurrency != "usd"){
      foreach($historyxchvalue AS $arrkey => $historyvalue){
        $historyxchvalue[$arrkey]["price_usd"] = number_format(floatval($historyvalue["price_usd"]) * floatval($exchangerate), 2);
      }
    }

    $hourspast = $days_past*24;
    echo "<script nonce={$ini["nonce_key"]}>
            var hourspast = {$hourspast};
            var historynetspace = " . json_encode($historynetspace) . ";
            var historyblockheight = " . json_encode($historyblockheight) . ";
            var defaultCurrency = '{$defaultCurrency}';
            var exchangerate = {$exchangerate};
            var historyXCHValue = " . json_encode($historyxchvalue) . ";
          </script>";
?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-5">
            Show data:
            <div class="input-group mb-3">
              <input id="filter-from" type="text" class="form-control datepicker" value="<?php echo $from->format("Y-m-d H:i:s"); ?>" aria-label="fromdate" aria-describedby="basic-addon1">
              <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">between</span>
              </div>
              <input id="filter-to" type="text" class="form-control datepicker" value="<?php echo $to->format("Y-m-d H:i:s"); ?>" aria-label="todate" aria-describedby="basic-addon1">
              <div class="input-group-prepend">
                <button id="filter-apply" class="btn btn-secondary wsbutton" type="button">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <?php if(count($historynetspace) > 0){ ?>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <div class="chart-bar" style="min-height: 40vh;">
              <canvas id="chia_netspace_history_chart"></canvas>
            </div>
          </li>
          <li class="list-group-item">
            <table class="table table-borderless">
              <thead>
                <tr>
                  <th scope="col">Current</th>
                  <th scope="col">Min</th>
                  <th scope="col">Max</th>
                  <th scope="col">Average</th>
                  <th scope="col">Growth</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td id="netspace_cur"></td>
                  <td id="netspace_min"></td>
                  <td id="netspace_max"></td>
                  <td id="netspace_avg"></td>
                  <td id="netspace_gro"></td>
                </tr>
              </tbody>
            </table>
          </li>
        </ul>
        <?php }else{ ?>
          <div class="card-body">
            There is currently no history netspacedata to show.<br>
            You may need to wait for at least 24 hours so the instance can query more data.
          </div>
        <?php } ?>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <?php if(count($historyblockheight) > 0){ ?>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <div class="chart-bar" style="min-height: 40vh;">
              <canvas id="chia_historyblockheight_history_chart"></canvas>
            </div>
          </li>
          <li class="list-group-item">
          <table class="table table-borderless">
            <thead>
              <tr>
                <th scope="col">Current</th>
                <th scope="col">Increase</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td id="blockheight_cur"></td>
                <td id="blockheight_inc"></td>
              </tr>
            </tbody>
          </table>
          </li>
        </ul>
        <?php }else{ ?>
          <div class="card-body">
            There is currently no historyblockheight netspacedata to show.<br>
            You may need to wait for at least 24 hours so the instance can query more data.
          </div>
        <?php } ?>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <?php if(count($historyxchvalue) > 0){ ?>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <div class="chart-bar" style="min-height: 40vh;">
              <canvas id="chia_xchvalue_history_chart"></canvas>
            </div>
          </li>
          <li class="list-group-item">
          <table class="table table-borderless">
            <thead>
              <tr>
                <th scope="col">Current</th>
                <th scope="col">Min</th>
                <th scope="col">Max</th>
                <th scope="col">Average</th>
                <th scope="col">Growth</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td id="xchvalue_cur"></td>
                <td id="xchvalue_min"></td>
                <td id="xchvalue_max"></td>
                <td id="xchvalue_avg"></td>
                <td id="xchvalue_gro"></td>
              </tr>
            </tbody>
          </table>
          </li>
        </ul>
        <?php }else{ ?>
          <div class="card-body">
            There is currently no history netspacedata to show.<br>
            You may need to wait for at least 24 hours so the instance can query more data.
          </div>
        <?php } ?>
    </div>
  </div>
</div>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_statistics/js/chia_statistics.js"?>></script>
<?php }); ?>