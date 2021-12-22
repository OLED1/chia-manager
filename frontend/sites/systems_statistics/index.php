<?php
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");
  echo "<script nonce={$ini["nonce_key"]}> var siteID = 13; </script>";

  $days_past = 4;
  $to = new \DateTime("now");
  $from = new \DateTime("now");
  $from->modify("-{$days_past} day");
  $data = array("from" => $from->format("Y-m-d H:i:s"), "to" => $to->format("Y-m-d H:i:s"));#

  $nodes_api = new Nodes_Api();
  $all_nodes = $nodes_api->getConfiguredNodes();
  $chia_nodes = [];
  if(array_key_exists("data", $all_nodes)){
    foreach($all_nodes["data"] AS $nodeid => $nodedata){
      if($nodedata["authtype"] == 2){
        $thishostinfo["hostname"] = $nodedata["hostname"];
        $thishostinfo["nodeid"] = $nodedata["id"];
        array_push($chia_nodes, $thishostinfo);
      }
    }
  }

  $hourspast = $days_past*24;
  echo "<script nonce={$ini["nonce_key"]}>
    var hourspast = {$hourspast};
    var nodes = " . json_encode($chia_nodes) . ";
    var historySystemsLoadData = {};
    var historyMemoryData = {};
    var historyFilesystemData = {};
    var historyServicesData = {};
  </script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/systems_statistics/css/systems_statistics.css"?>" rel="stylesheet">

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Systems Statistics</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        This page shows historical systems data based on values queried from this instance like cpu load, memory and filesystem workload.
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <div class="row">
          <div class="col-5">
            Show data:
            <div class="input-group mb-3">
              <input id="filter-from" type="text" class="form-control datepicker" value="<?php echo $from->format("Y-m-d H:i:s"); ?>" aria-label="fromdate" aria-describedby="basic-addon1">
              <div class="input-group-prepend">
                <span class="input-group-text" id="basic-addon1">between</span>
              </div>
              <input id="filter-to" type="text" class="form-control datepicker" value="<?php echo $to->format("Y-m-d H:i:s"); ?>" aria-label="todate" aria-describedby="basic-addon1">
              <div class="input-group-prepend">
                <button id="filter-apply" class="btn btn-secondary" type="button">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="card shadow mb-4">
  <?php if(count($chia_nodes) > 0){ ?>
  <ul class="nav nav-tabs" role="tablist">
    <?php foreach($chia_nodes AS $arrkey => $nodeinfo){ ?>
    <li class="nav-item">
        <a class="nav-link <?php echo ($arrkey == 0 ? "active" : "" ); ?>" id="sysinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#sysinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_<?php echo $arrkey; ?>" aria-selected="<?php echo ($arrkey == 0 ? "true" : "" ); ?>"><?php echo $nodeinfo["hostname"]; ?></a>
    </li>
    <?php } ?>
  </ul>
  <div class="tab-content">
    <?php foreach($chia_nodes AS $arrkey => $nodeinfo){ ?>
      <div class="tab-pane fade <?php echo ($arrkey == 0 ? "show active" : "" ); ?>" id="sysinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tabpanel" aria-labelledby="sysinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
              <a class="nav-link active" id="cpuinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#cpuinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_cpu_<?php echo $arrkey; ?>" aria-selected="true">CPU</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="meminfoinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#meminfoinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_meminfoinfo_<?php echo $arrkey; ?>" aria-selected="true">Memory</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="filesystems_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#filesystems_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_filesystems_<?php echo $arrkey; ?>" aria-selected="true">Filesystems</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="services_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#services_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_services_<?php echo $arrkey; ?>" aria-selected="true">Services</a>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="cpuinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tabpanel" aria-labelledby="cpuinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>">
            <?php
              $_GET['nodeid'] = $nodeinfo["nodeid"];
              $_GET['from'] = $from->format("Y-m-d H:i:s");
              $_GET['to'] = $to->format("Y-m-d H:i:s");;
              include("templates/load_chart_card.php"); 
            ?>  
          </div>
          <div class="tab-pane fade" id="meminfoinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tabpanel" aria-labelledby="meminfoinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>">
            <?php 
              include("templates/memory_chart_card.php"); 
            ?>
          </div>
          <div class="tab-pane fade" id="filesystems_<?php echo $nodeinfo["nodeid"]; ?>" role="tabpanel" aria-labelledby="filesystems_tab-<?php echo $nodeinfo["nodeid"]; ?>">
            <?php 
              include("templates/filesystems_chart_card.php"); 
            ?>
          </div>
          <div class="tab-pane fade" id="services_<?php echo $nodeinfo["nodeid"]; ?>" role="tabpanel" aria-labelledby="services_tab-<?php echo $nodeinfo["nodeid"]; ?>">
            <?php 
              include("templates/services_chart_card.php"); 
            ?>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
  <?php }else{ ?>
  <div class="card-body">
    There are currently no nodes configured.<br>
    If you think there should be data and this is a system fault, please open a ticket on github.
  </div>
  <?php } ?>
</div>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/systems_statistics/js/systems_statistics.js"?>></script>
