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
  $historyFilesystemData = $system_statistics_api->getFilesystemsHistory($data);
  if(array_key_exists("data", $historyFilesystemData)){
    $historyFilesystemData = $historyFilesystemData["data"];
  }else{
    $historyFilesystemData = [];
  }

  echo "<script nonce={$ini["nonce_key"]}>
            historyFilesystemData[" . $_GET["nodeid"] . "] = " . json_encode($historyFilesystemData[$_GET["nodeid"]]) .";
        </script>";
?>
    <?php if($alldatastated ){ ?>
        <?php if(count($historyFilesystemData) > 0){ ?>
            <?php foreach($historyFilesystemData AS $nodeid => $filesystemsdata){
                foreach($filesystemsdata AS $mountpoint => $thisfilesystem){
            ?>
<div class="card shadow mb-4">
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <div class="chart-bar" style="min-height: 30vh;">
                <canvas class="sysinfo_filesystem_chart_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></canvas>
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
                    <td id="filesystem_cur_size_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_min_size_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_max_size_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_avg_size_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                </tr>
                <tr>
                    <td>Used</td>
                    <td id="filesystem_cur_used_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_min_used_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_max_used_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_avg_used_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                </tr>
                <tr>
                    <td>Available</td>
                    <td id="filesystem_cur_free_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_min_free_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_max_free_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                    <td id="filesystem_avg_free_<?php echo $_GET["nodeid"]; ?>" data-mounted-on="<?php echo $mountpoint; ?>"></td>
                </tr>
            </tbody>
        </table>
        </li>
    </ul>
</div>
<?php
    }
}
}else{ ?>
<div class="card shadow mb-4">
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
</div>
<?php } ?>