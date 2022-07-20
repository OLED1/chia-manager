<?php
    use React\Promise;
    use ChiaMgmt\Login\Login_Api;
    use ChiaMgmt\System_Statistics\System_Statistics_Api;
    require __DIR__ . '/../../../../vendor/autoload.php';

    if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET) || !array_key_exists("nodeid", $_GET) || !array_key_exists("from", $_GET) || !array_key_exists("to", $_GET)){
        echo "Incomplete Request.";
        die();
    }

    $site_data_to_load = [
        React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
        React\Promise\resolve((new System_Statistics_Api())->getSystemsLoadHistory(["from" => $_GET["from"], "to" => $_GET["to"], "node_ids" => [$_GET["nodeid"]]]))
    ];
    
    $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
    React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini){
        if($all_returned[0]["status"] > 0){
            echo "NOT AUTHENTICATED.";
            exit();
        }

        $historySystemsLoadData = $all_returned[1];
        if(array_key_exists("data", $historySystemsLoadData) && array_key_exists($_GET["nodeid"], $historySystemsLoadData["data"])){
            $historySystemsLoadData = $historySystemsLoadData["data"][$_GET["nodeid"]];
        }else{
            $historySystemsLoadData = [];
        }
    
        echo "<script nonce={$ini["nonce_key"]}>
                historySystemsLoadData[" . $_GET["nodeid"] . "] = " . json_encode($historySystemsLoadData) . ";
            </script>";
?>
<div class="card shadow mb-4">
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
</div>
<?php }); ?>