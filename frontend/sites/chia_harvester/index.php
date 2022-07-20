<?php
  use React\Promise;
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();

  $site_infos_to_load = [
    Promise\resolve($nodes_api->getConfiguredNodes(["nodetypenum" => 4])),
    Promise\resolve($nodes_api->getCurrentChiaNodesUPAndServiceStatus())
  ];

  Promise\all($site_infos_to_load)->then(function($all_returned) use($ini){
    $all_nodes = $all_returned[0];
    $services_states = $all_returned[1];

    if(array_key_exists("data", $services_states)){
      $services_states = $services_states["data"];
    }else{
      $services_states = [];
    }

    $chia_nodes = [];
    if(array_key_exists("data", $all_nodes)){
      foreach($all_nodes["data"] AS $nodeid => $nodedata){
        if($nodedata["authtype"] == 2){
          $thishostinfo["hostname"] = $nodedata["hostname"];
          $thishostinfo["nodeid"] = $nodedata["id"];
          $thishostinfo["nodeauthhash"] = $nodedata["nodeauthhash"];
          $chia_nodes[$nodedata["id"]] = $thishostinfo;
        }
      }
    }

    echo "<script nonce={$ini["nonce_key"]}> 
      var siteID = 7;
      var chiaNodes = " . json_encode($chia_nodes) . ";
      var chiaHarvesterData = {};
      var services_states = " . json_encode($services_states) . "; 
    </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0 text-gray-800"><span style="font-size: 1.5rem">Chia®</span> Harvester</h1>
</div>
<div class="row">
  <div class="col">
    <h4>Explanation</h4>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your plots and information about it.<br>
        If this project will pass all required security checks we will implement plotting options too.
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="alert alert-warning" role="alert">
      Please make sure every external connected harddrive has its own mountpoint.<br>
      E.g. /dev/sdb1 -> /mnt/final1, /dev/sdc -> /mnt/final2<br>
      Otherwise the client will only query one of two or more directories.
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary wsbutton">Query harvester information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h4>My Plots</h4>
<?php 
  $harvester_cards = [];
  $browser = new React\Http\Browser();

  foreach($chia_nodes AS $nodeid => $nodeinfo){
    $templates_path = "{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/sites/chia_harvester/templates/";
    $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}&nodeid={$nodeid}";

    $harvester_cards[$nodeid] = $browser->get("{$templates_path}cards.php{$default_get_params}");
  }

  React\Promise\all($harvester_cards)->then(function($all_returned){
    foreach($all_returned AS $nodeid => $this_wallet_node_card){
      echo "<div id='harvestercontainer_{$nodeid}'>{$this_wallet_node_card->getBody()}</div>";
    }
  });
?>
<?php 
  if(count($chia_nodes) > 0){
    foreach($chia_nodes AS $nodeid => $nodeinfo){ ?>
    <div id="harvestercontainer_<?php echo $nodeid; ?>">
      <?php
          $_GET['nodeid'] = $nodeid;
          //include("templates/cards.php");
      ?> 
    </div>
<?php 
    }
  }else{ 
?>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        There are currently no harvester nodes configured.<br>
        Please add some on the nodes page.
      </div>
    </div>
  </div>
</div>
<?php } ?>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_harvester/js/chia_harvester.js"?>></script>
<?php }); ?>
