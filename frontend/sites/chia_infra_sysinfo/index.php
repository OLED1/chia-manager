<?php
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();
  $services_states = $nodes_api->getCurrentChiaNodesUPAndServiceStatus();
  if(array_key_exists("data", $services_states)){
    $services_states = $services_states["data"];
  }else{
    $services_states = [];
  }

  echo "<script nonce={$ini["nonce_key"]}>
          var siteID = 8;
          var frontend_url = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
          var services_states = " . json_encode($services_states) . ";
        </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0 text-gray-800"><span style="font-size: 1.5rem">Chia®</span> Infra Sysinfo</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your set-up nodes and information about it like used filesystem space, used and configured ram and swap and the current system load.
      </div>
    </div>
  </div>
</div>
<h4>Chia Infrastructure System Information</h4>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary wsbutton">Query system information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<div id="all_node_sysinfo_container">
<?php

  include("templates/cards.php"); 
?>
</div>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/js/chia_infra_sysinfo.js"?>></script>
