<?php
  use React\Promise;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../vendor/autoload.php';
  include("../standard_headers.php");


  $services_states = Promise\resolve((new Nodes_Api())->getCurrentChiaNodesUPAndServiceStatus());
  $services_states->then(function($services_states_returned) use($ini){
    if(array_key_exists("data", $services_states_returned)){
      $services_states_returned = $services_states_returned["data"];
    }else{
      $services_states_returned = [];
    }

    echo "<script nonce={$ini["nonce_key"]}> 
            var siteID = 1;
            var services_states = " . json_encode($services_states_returned) . ";
            var userID = {$_COOKIE['user_id']};
            var sessID = '{$_COOKIE['PHPSESSID']}';
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
  <?php
    $templates_path = "{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/sites/main_overview/templates/";
    $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}";
    $services_states = "&services_states=" . json_encode($services_states_returned);

    $browser = new React\Http\Browser();

    $all_cards = array(
      $browser->get("{$templates_path}card-system.php{$default_get_params}"),
      $browser->get("{$templates_path}card-overall.php{$default_get_params}"),
      $browser->get("{$templates_path}card-wallet.php{$default_get_params}{$services_states}"),
      $browser->get("{$templates_path}card-farm.php{$default_get_params}{$services_states}"),
      $browser->get("{$templates_path}card-harvester.php{$default_get_params}{$services_states}")
    );

    React\Promise\all($all_cards)->then(function($all_returned){
      echo "<div id='services'>";
      foreach($all_returned AS $arrkey => $returned_page){
        echo $returned_page->getBody();
      }
      echo "</div>";
    });
  ?>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/main_overview/js/main_overview.js"?>></script>

<?php }); ?>
