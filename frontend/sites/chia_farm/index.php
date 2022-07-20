<?php
  use React\Promise;
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();

  $site_infos_to_load = [
    Promise\resolve($nodes_api->getCurrentChiaNodesUPAndServiceStatus()),
    Promise\resolve($nodes_api->getConfiguredNodes(["nodetypenum" => 3]))
  ];

  Promise\all($site_infos_to_load)->then(function($all_returned) use($ini){
    $services_states = $all_returned[0];
    $all_nodes = $all_returned[1];

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
      var siteID = 6;
      var chiaNodes = " . json_encode($chia_nodes) . ";
      var chiaFarmData = {};
      var services_states = " . json_encode($services_states) . ";
    </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><span style="font-size: 1.5rem">Chia®</span> Farm</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your farm and information about it.
      </div>
    </div>
  </div>
</div>
<h4>My Farm</h4>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary wsbutton">Query farm information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h5>Overview</h5>
<?php 
  $farm_cards = [];
  $browser = new React\Http\Browser();

  foreach($chia_nodes AS $nodeid => $nodeinfo){
    $templates_path = "{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/sites/chia_farm/templates/";
    $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}&nodeid={$nodeid}";

    $farm_cards[$nodeid] = $browser->get("{$templates_path}cards.php{$default_get_params}");
  }

  React\Promise\all($farm_cards)->then(function($all_returned){
    foreach($all_returned AS $nodeid => $this_wallet_node_card){
      echo "<div id='farmercontainer_{$nodeid}'>{$this_wallet_node_card->getBody()}</div>";
    }
  });
?>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_farm/js/chia_farm.js"?>></script>
<?php }); ?>
