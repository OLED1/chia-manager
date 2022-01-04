<?php
    use ChiaMgmt\Login\Login_Api;
    use ChiaMgmt\System_Statistics\System_Statistics_Api;
    require __DIR__ . '/../../../../vendor/autoload.php';
    include_once(__DIR__ . '/functions.php');
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
        "from" => $_GET["from"],
        "to" => $_GET["to"],
        "node_ids" => [$_GET["nodeid"]]
    ];

    $system_statistics_api = new System_Statistics_Api();
    $historyServicesData = $system_statistics_api->getNodeUPAndServicesHistory($data);
    if(array_key_exists("data", $historyServicesData) && array_key_exists($_GET["nodeid"], $historyServicesData["data"])){
        $historyServicesData = $historyServicesData["data"][$_GET["nodeid"]];
    }else{
        $historyServicesData = [];
    }

    echo "<script nonce={$ini["nonce_key"]}>
            historyServicesData[" . $_GET["nodeid"] . "] = " . json_encode($historyServicesData) . ";
        </script>";
?>
<div class="card shadow mb-4">
<?php if($alldatastated ){ ?>
    <?php if(count($historyServicesData["onlinestatus"]) > 0 || count($historyServicesData["services"]) > 0){ ?>
    <ul class="list-group list-group-flush">
        <li class="list-group-item">Node up and down history
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="card bg-success text-white shadow">
                        <div class="card-body">
                            Total up time
                            <div class="text-white-50">
                                <h3><?php echo "{$historyServicesData["statistics"]["node"]["upInPercent"]}%"; ?></h3>
                                <div class="text-white-50"><?php echo calc_time($historyServicesData["statistics"]["node"]["upInSeconds"]); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 mb-4">
                    <div class="card bg-danger text-white shadow">
                        <div class="card-body">
                            Total down time
                            <div class="text-white-50">
                            <h3><?php echo "{$historyServicesData["statistics"]["node"]["downInPercent"]}%"; ?></h3>
                                <div class="text-white-50"><?php echo calc_time($historyServicesData["statistics"]["node"]["downInSeconds"]); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </li>
        <?php 
            $service_string = [3 => "Farmer", 4 => "Harvester", 5 => "Wallet"];
            foreach($historyServicesData["services"] AS $serviceID => $services){
                if(count($services) > 0){
        ?>
            <li class="list-group-item"><?php echo "{$service_string[$serviceID]} running history"; ?>
                <div class="row">
                    <div class="col-lg-3 mb-4">
                        <div class="card bg-success text-white shadow">
                            <div class="card-body">
                                Total up time
                                <div class="text-white-50">
                                    <h3><?php echo "{$historyServicesData["statistics"]["services"][$serviceID]["upInPercent"]}%"; ?></h3>
                                    <div class="text-white-50"><?php echo calc_time($historyServicesData["statistics"]["services"][$serviceID]["upInSeconds"]); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 mb-4">
                        <div class="card bg-danger text-white shadow">
                            <div class="card-body">
                                Total down time
                                <div class="text-white-50">
                                <h3><?php echo "{$historyServicesData["statistics"]["services"][$serviceID]["downInPercent"]}%"; ?></h3>
                                    <div class="text-white-50"><?php echo calc_time($historyServicesData["statistics"]["services"][$serviceID]["downInSeconds"]); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        <?php
                }
            } 
        ?>
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