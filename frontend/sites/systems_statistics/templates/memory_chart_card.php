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
  $historyMemoryData = $system_statistics_api->getRAMSwapHistory($data);
  if(array_key_exists("data", $historyMemoryData)){
    $historyMemoryData = $historyMemoryData["data"];
  }else{
    $historyMemoryData = [];
  }

  echo "<script nonce={$ini["nonce_key"]}>
            historyMemoryData[" . $_GET["nodeid"] . "] = " . json_encode($historyMemoryData[$_GET["nodeid"]]) . ";
        </script>";
?>
<div class="card shadow mb-4">
<?php if($alldatastated ){ ?>
    <?php if(count($historyMemoryData) > 0){ ?>
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <div class="chart-bar" style="min-height: 30vh;">
                <canvas id="sysinfo_memory_chart-<?php echo $_GET["nodeid"]; ?>"></canvas>
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
                        <td>Total</td>
                        <td id="total_ram_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_ram_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_ram_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_ram_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Free</td>
                        <td id="free_ram_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_ram_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_ram_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_ram_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Used</td>
                        <td id="used_ram_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_ram_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_ram_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_ram_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                </tbody>
            </table>
        </li>
    </ul>
</div>
<div class="card shadow mb-4">
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <div class="chart-bar" style="min-height: 30vh;">
                <canvas id="sysinfo_swap_chart-<?php echo $_GET["nodeid"]; ?>"></canvas>
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
                        <td>Total</td>
                        <td id="total_swap_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_swap_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_swap_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="total_swap_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Free</td>
                        <td id="free_swap_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_swap_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_swap_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="free_swap_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Used</td>
                        <td id="used_swap_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_swap_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_swap_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="used_swap_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                </tbody>
            </table>
        </li>
    </ul>
</div>
<div class="card shadow mb-4">
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <div class="chart-bar" style="min-height: 30vh;">
                <canvas id="sysinfo_caches_chart-<?php echo $_GET["nodeid"]; ?>"></canvas>
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
                        <td>Total</td>
                        <td id="buffers_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="buffers_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="buffers_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="buffers_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Free</td>
                        <td id="cached_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="cached_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="cached_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="cached_avg-<?php echo $_GET["nodeid"]; ?>"></td>
                    </tr>
                    <tr>
                        <td>Used</td>
                        <td id="shared_cur-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="shared_min-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="shared_max-<?php echo $_GET["nodeid"]; ?>"></td>
                        <td id="shared_avg-<?php echo $_GET["nodeid"]; ?>"></td>
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