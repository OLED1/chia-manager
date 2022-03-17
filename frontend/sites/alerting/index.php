<?php
  include("../standard_headers.php");
  use ChiaMgmt\Alerting\Alerting_Api;

  $alerting_api = new Alerting_Api();
  $alerting_services = $alerting_api->getAvailableServices()["data"];
  //print_r($alerting_services);

  echo "<script nonce={$ini["nonce_key"]}>
          var siteID = 14;
          var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
        </script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/alerting/css/alerting.css"?>" rel="stylesheet">

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Alerting</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        Alerting enables you to configure some further options to stay informed if something not expected is happening to your system or your connected nodes.<br>
        By default there are some rules predefined, so alerting will be able to operate after enableing these options in the setting page.<br>
        Available alerting platforms: Mail and Gotify. SMS and Matrix will follow soon.<br>
        Got to settings and enable the options you want to use. After that you will be able to install needed services right from here.
      </div>
    </div>
  </div>
</div>
<h5>Configure alerting services</h5>
<div class="card shadow mb-4">
  <ul class="nav nav-tabs" id="configure-alerting-services-tabs" role="tablist">
    <?php foreach($alerting_services AS $service_id => $alerting_service){ ?>
    <li class="nav-item">
      <a class="nav-link" id="<?php echo $alerting_service["service_id"]; ?>-tab" data-toggle="tab" href="#<?php echo $alerting_service["service_id"]; ?>" role="tab" aria-controls="<?php echo $alerting_service["service_id"]; ?>" aria-selected="true"><?php echo $alerting_service["service_name"]; ?></a>
    </li>
    <?php } ?>
  </ul>
  <div id="configure-alerting-services-pane" class="tab-content">
    <?php foreach($alerting_services AS $service_id => $alerting_service){ ?>
    <div class="tab-pane fade" id="<?php echo $alerting_service["service_id"]; ?>" role="tabpanel" aria-labelledby="<?php echo $alerting_service["service_id"]; ?>-tab">
      <?php
        $_GET[$alerting_service["service_id"]] = $alerting_service; 
        include("templates/alerting_services/{$alerting_service["service_id"]}_card.php"); 
      ?>
    </div>
    <?php } ?>
  </div>
</div>

<h5>Setup node alerting</h5>
<div class="card shadow mb-4">
  <ul class="nav nav-tabs" id="setup-alerting-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link" id="configure-rules-tab" data-toggle="tab" href="#configure-rules" role="tab" aria-controls="rules" aria-selected="true">Configure rules</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="setup-alerting-tab" data-toggle="tab" href="#setup-alerting" role="tab" aria-controls="rules" aria-selected="true">Setup alerting</a>
    </li>
  </ul>
  <div id="setup-node-alerting-pane" class="tab-content">
    <div class="tab-pane fade" id="configure-rules" role="tabpanel" aria-labelledby="configure-rules-tab">
      <?php include("templates/configure_rules_card.php"); ?>
    </div>
    <div class="tab-pane fade" id="setup-alerting" role="tabpanel" aria-labelledby="setup-alerting-tab">
    <?php include("templates/setup_alerting_card.php"); ?>
    </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/alerting/js/alerting.js"?>></script>