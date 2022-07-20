<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System_Statistics\System_Statistics_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

    if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET) || !array_key_exists("nodeid", $_GET) || !array_key_exists("from", $_GET) || !array_key_exists("to", $_GET)){
        echo "Incomplete Request.";
        die();
    }

    $site_data_to_load = [
        React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
        React\Promise\resolve((new System_Statistics_Api())->getFilesystemsHistory(["from" => $_GET["from"], "to" => $_GET["to"], "node_ids" => [$_GET["nodeid"]]]))
    ];

    $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
    React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini){
        if($all_returned[0]["status"] > 0){
            echo "NOT AUTHENTICATED.";
            exit();
        }

        $historyFilesystemData = $all_returned[1];
        if(array_key_exists("data", $historyFilesystemData) && array_key_exists($_GET["nodeid"], $historyFilesystemData["data"])){
          $historyFilesystemData = $historyFilesystemData["data"][$_GET["nodeid"]];
        }else{
          $historyFilesystemData = [];
        }

        echo "<script nonce={$ini["nonce_key"]}>
            historyFilesystemData[" . $_GET["nodeid"] . "] = " . json_encode($historyFilesystemData) .";
        </script>";

        $nodeid = $_GET["nodeid"];
?>
<div class="card shadow mb-4">
    <div class="card-body">
<?php
    if(count($historyFilesystemData) > 0){
        foreach($historyFilesystemData AS $mountpoint => $thisfilesystem){
?>
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <div class="chart-bar" style="min-height: 30vh;">
                <canvas class="sysinfo_filesystem_chart_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></canvas>
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
                        <td>Size</td>
                        <td id="filesystem_cur_size_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_min_size_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_max_size_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_avg_size_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    </tr>
                    <tr>
                        <td>Used</td>
                        <td id="filesystem_cur_used_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_min_used_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_max_used_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                        <td id="filesystem_avg_used_<?php echo $nodeid ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    </tr>
                </tbody>
            </table>
        </li>
    </ul>
<?php
        }
    }else{ ?>
    There is currently no history nodedata to show.<br>
    There is either no node configured or you may need to wait for at least 24 hours so the instance can query more data.<br>
    If you think there should be data and this is a system fault, please open a ticket on github.
    <?php } ?>
    </div>
</div>
<?php }); ?>