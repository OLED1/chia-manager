<?php
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();
  $all_nodes = $nodes_api->getConfiguredNodes(["nodetypenum" => 3]);
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
        </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Farm</h1>
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
<?php foreach($chia_nodes AS $nodeid => $nodeinfo){ ?>
  <div id="farmercontainer_<?php echo $nodeid; ?>">
    <?php
        $_GET['nodeid'] = $nodeid;
        include("templates/cards.php");
    ?> 
  </div>
<?php } ?>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_farm/js/chia_farm.js"?>></script>

