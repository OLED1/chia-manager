<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System_Statistics\System_Statistics_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';
  include(__DIR__ . "/../../standard_headers.php");

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $alldatastated = true;
  if(!array_key_exists("nodeid", $_GET) || !array_key_exists("from", $_GET) || !array_key_exists("to", $_GET)){
    $alldatastated = false;
  }

  $data = [
      "from" => $_GET["from"]->format("Y-m-d H:i:s"),
      "to" => $_GET["to"]->format("Y-m-d H:i:s"),
      "node_ids" => [$_GET["nodeid"]]
  ];

  $system_statistics_api = new System_Statistics_Api();
  $historySystemsLoadData = $system_statistics_api->getSystemsLoadHistory($data);
  if(array_key_exists("data", $historySystemsLoadData)){
    $historySystemsLoadData = $historySystemsLoadData["data"];
  }else{
    $historySystemsLoadData = [];
  }

  /*echo "<pre>";
  echo "ID: " . $_GET["nodeid"] . "<br>";
  print_r($historySystemsLoadData[$_GET["nodeid"]]);
  echo "</pre>";*/

  echo "<script nonce={$ini["nonce_key"]}>
            historySystemsLoadData[" . $_GET["nodeid"] . "] = " . json_encode($historySystemsLoadData[$_GET["nodeid"]]) . ";
        </script>";
?>
<div class="card shadow mb-4">
    <?php if($alldatastated ){ ?>
        <?php if(count($historySystemsLoadData) > 0){ ?>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <div class="chart-bar" style="min-height: 30vh;">
                        <canvas id="sysinfo_loads_chart-<?php echo $_GET["nodeid"]; ?>"></canvas>
                    </div>
                </li>
                <li class="list-group-item">
                <table class="table table-borderless load-table">
                    <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Current</th>
                        <th scope="col">Min</th>
                        <th scope="col">Max</th>
                        <th scope="col">Average</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Load 1min</td>
                        <td id="load1min_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load1min_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load1min_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load1min_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Load 5min</td>
                        <td id="load5min_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load5min_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load5min_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load5min_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Load 15min</td>
                        <td id="load15min_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load15min_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load15min_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="load15min_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    </tbody>
                </table>
                </li>
            </ul>
        <?php }else{ ?>
    <div class="card-body">
        There is currently no history nodedata to show.<br>
        There is either no node configured or you may need to wait for at least 24 hours so the instance can query more data.<br>
        If you think there should be data and this is a system fault, please open a ticket on github.
    </div>
        <?php } ?>
    <?php }else{ ?>
    <div class="card-body">
        Some parameters are missing. You need to state "nodeid", "from"-date and "to"-date. Could not load systems load data.
    </div>
    <?php } ?>
</div>