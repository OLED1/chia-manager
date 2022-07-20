<?php
  use React\Promise;
  use React\Http\Browser;
  use ChiaMgmt\Nodes\Nodes_Api;
  include("../standard_headers.php");
  
  $configuredNodes = Promise\resolve((new Nodes_Api())->getConfiguredNodes());
  $configuredNodes->then(function($configuredNodes_returned) use($ini){
    echo "<script nonce={$ini["nonce_key"]}> var siteID = 13; </script>";
        
    $days_past = 4;
    $to = new \DateTime("now");
    $from = new \DateTime("now");
    $from->modify("-{$days_past} day");
    $data = array("from" => $from->format("Y-m-d H:i:s"), "to" => $to->format("Y-m-d H:i:s"));

    $chia_nodes = [];
    if(array_key_exists("data", $configuredNodes_returned)){
      foreach($configuredNodes_returned["data"] AS $nodeid => $nodedata){
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
                <button id="filter-apply" class="btn btn-secondary wsbutton" type="button">Apply</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="card shadow mb-4">
  <?php 
    if(count($chia_nodes) > 0){ 
 
      $browser = new \React\Http\Browser();
      $statistics_cards = [];
      $resolved_promises = [];
      $templates_path = "{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/sites/systems_statistics/templates/";
      
      foreach($chia_nodes AS $arrkey => $nodeinfo){
        if($chia_nodes[0]["nodeid"] == $nodeinfo["nodeid"]) echo "<ul class='nav nav-tabs' role='tablist'>"; 
    ?>
    <li class="nav-item">
      <a class="nav-link <?php echo ($arrkey == 0 ? "active" : "" ); ?>" id="sysinfo_tab-<?php echo $nodeinfo["nodeid"]; ?>" data-toggle="tab" href="#sysinfo_<?php echo $nodeinfo["nodeid"]; ?>" role="tab" aria-controls="content_<?php echo $arrkey; ?>" aria-selected="<?php echo ($arrkey == 0 ? "true" : "" ); ?>"><?php echo $nodeinfo["hostname"]; ?></a>
    </li>
    <?php
        if($chia_nodes[(count($chia_nodes)-1)]["nodeid"] == $nodeinfo["nodeid"]) echo "</ul>";


        $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}&nodeid={$nodeinfo["nodeid"]}&from={$from->format("Y-m-d H:i:s")}&to={$to->format("Y-m-d H:i:s")}";

        $this_node_cards = [
          $browser->get("{$templates_path}load_chart_card.php{$default_get_params}"),
          $browser->get("{$templates_path}memory_chart_card.php{$default_get_params}"),
          $browser->get("{$templates_path}filesystems_chart_card.php{$default_get_params}"),
          $browser->get("{$templates_path}services_chart_card.php{$default_get_params}")
        ];

        $resolved_promises[$nodeinfo["nodeid"]] = React\Promise\all($this_node_cards)->then(function($all_returned){
          $data_to_return["load_chart_card"] = (string)$all_returned[0]->getBody();
          $data_to_return["memory_chart_card"] = (string)$all_returned[1]->getBody();
          $data_to_return["filesystems_chart_card"] = (string)$all_returned[2]->getBody();
          $data_to_return["services_chart_card"] = (string)$all_returned[3]->getBody();
          return $data_to_return;
        });
      }

      React\Promise\all($resolved_promises)->then(function($resolved_all){
        ?><div class="tab-content"><?php
        foreach($resolved_all AS $nodeid => $tab_contents){
          ?>
      <div class="tab-pane fade <?php echo (array_key_first($resolved_all) == $nodeid ? "show active" : "" ); ?>" id="sysinfo_<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="sysinfo_tab-<?php echo $nodeid; ?>">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
              <a class="nav-link active" id="cpuinfo_tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#cpuinfo_<?php echo $nodeid; ?>" role="tab" aria-controls="content_cpu_<?php echo $nodeid; ?>" aria-selected="true">CPU</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="meminfoinfo_tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#meminfoinfo_<?php echo $nodeid; ?>" role="tab" aria-controls="content_meminfoinfo_<?php echo $nodeid; ?>" aria-selected="true">Memory</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="filesystems_tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#filesystems_<?php echo $nodeid; ?>" role="tab" aria-controls="content_filesystems_<?php echo $nodeid; ?>" aria-selected="true">Filesystems</a>
          </li>
          <li class="nav-item">
              <a class="nav-link" id="services_tab-<?php echo $nodeid; ?>" data-toggle="tab" href="#services_<?php echo $nodeid; ?>" role="tab" aria-controls="content_services_<?php echo $nodeid; ?>" aria-selected="true">Services</a>
          </li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="cpuinfo_<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="cpuinfo_tab-<?php echo $nodeid; ?>">          
            <?php
              echo $tab_contents["load_chart_card"];
            ?>  
          </div>
          <div class="tab-pane fade" id="meminfoinfo_<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="meminfoinfo_tab-<?php echo $nodeid; ?>">
            <?php  
              echo $tab_contents["memory_chart_card"];
            ?>
          </div>
          <div class="tab-pane fade" id="filesystems_<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="filesystems_tab-<?php echo $nodeid; ?>">
            <?php 
              echo $tab_contents["filesystems_chart_card"];
            ?>
          </div>
          <div class="tab-pane fade" id="services_<?php echo $nodeid; ?>" role="tabpanel" aria-labelledby="services_tab-<?php echo $nodeid; ?>">
            <?php 
              echo $tab_contents["services_chart_card"];
            ?>
          </div>
        </div>
      </div>
          <?php
        }
        ?></div><?php
      });
    ?>
<?php 
    }else{ 
?>
  <div class="card-body">
    There are currently no nodes configured.<br>
    If you think there should be data and this is a system fault, please open a ticket on github.
  </div>
</div>
<?php 
    } 
  });
?>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/systems_statistics/js/systems_statistics.js"?>></script>