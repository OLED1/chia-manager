<?php
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../vendor/autoload.php';
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();
  $services_states = $nodes_api->getCurrentChiaNodesUPAndServiceStatus();
  if(array_key_exists("data", $services_states)){
    $services_states = $services_states["data"];
  }else{
    $services_states = [];
  }

  echo "<script nonce={$ini["nonce_key"]}> 
          var siteID = 1;
          var services_states = " . json_encode($services_states) . ";
        </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
</div>
<div class="row">
  <div class="col">
    <div class="alert alert-warning" role="alert">
      Please be aware: Some of the shown data may not be real live data.<br>
      E.g. If a service says the node is "farming" and the node state says "node not connected", the last values from the database are shown.
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-3 mb-4">
    <div class="card bg-success text-white shadow">
      <div class="card-body">
        Successfully running services
        <div class="text-white-50">
          <h3 id="ok-service-count">?</h3>
          <div class="text-white-50">No actions required</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 mb-4">
    <div class="card bg-warning text-white shadow">
      <div class="card-body">
        Warning services
        <div class="text-white-50">
          <h3 id="warn-service-count">?</h3>
          <div class="text-white-50">Actions maybe required</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 mb-4">
    <div class="card bg-danger text-white shadow">
      <div class="card-body">
        Critical services
        <div class="text-white-50">
          <h3 id="crit-service-count">?</h3>
          <div class="text-white-50">Urgent actions required</div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="services">
  <div class="row">
    <div id="card-system" class="col">
      <?php
        $_GET["services_states"] = $services_states;
        include("templates/card-system.php");
      ?>
    </div>
  </div>
  <div class="row">
    <div id="card-overall-luca" class="col">
      <?php
        include("templates/card-overall-luca.php");
      ?>
    </div>
  </div>
  <div class="row">
    <div id="card-overall" class="col">
      <?php
        include("templates/card-overall.php");
      ?>
    </div>
  </div>
  <div class="row">
    <div id="card-wallet" class="col">
      <?php
        include("templates/card-wallet.php");
      ?>
    </div>
  </div>
  <div class="row">
    <div id="card-farm" class="col">
      <?php
        include("templates/card-farm.php");
      ?>
    </div>
  </div>
  <div class="row">
    <div id="card-harvester" class="col">
      <?php
        include("templates/card-harvester.php");
      ?>
    </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/main_overview/js/main_overview.js"?>></script>
