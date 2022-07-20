<?php
  use React\Promise;
  use ChiaMgmt\Alerting\Alerting_Api;
  include("../standard_headers.php");

  $available_services = Promise\resolve((new Alerting_Api())->getAvailableServices());
  $available_services->then(function($available_services_returned) use($ini){
    $alerting_services = $available_services_returned["data"];

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
<?php 
  $alerting_cards = [];
  $browser = new React\Http\Browser();
  $templates_path = "{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}/sites/alerting/templates/";

  foreach($alerting_services AS $service_id => $alerting_service){ 
    $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}&{$alerting_service["service_id"]}=" . json_encode($alerting_service);
    $alerting_cards[$alerting_service["service_id"]] = $browser->get("{$templates_path}alerting_services/" . strtolower($alerting_service["service_id"]) . "_card.php{$default_get_params}");
  }

  $alerting_promise_done = Promise\all($alerting_cards)->then(function($alerting_cards_returned){
    ?><div id="configure-alerting-services-pane" class="tab-content"><?php
    foreach($alerting_cards_returned AS $service_id => $this_alerting_service_card){
      ?><div class="tab-pane fade" id="<?php echo $service_id; ?>" role="tabpanel" aria-labelledby="<?php echo $service_id; ?>-tab"><?php
      echo $this_alerting_service_card->getBody();
      ?></div><?php
    }
    ?>
    </div>
</div>
<?php
  });

  $alerting_promise_done->then(function() use($browser, $templates_path){
?>
<h5>Setup node alerting</h5>
<div class="card shadow mb-4">
  <ul class="nav nav-tabs" id="setup-alerting-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link" id="configure-rules-tab" data-toggle="tab" href="#configure-rules" role="tab" aria-controls="rules" aria-selected="true">Configure rules</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="setup-alerting-tab" data-toggle="tab" href="#setup-alerting" role="tab" aria-controls="rules" aria-selected="true">Setup alerting</a>
    </li>
    <li class="nav-item">
      <a class="nav-link" id="alerting-history-tab" data-toggle="tab" href="#alerting-history" role="tab" aria-controls="rules" aria-selected="true">Alerting history</a>
    </li>
  </ul>
  <?php
    $default_get_params = "?user_id={$_COOKIE['user_id']}&sess_id={$_COOKIE['PHPSESSID']}";

    $configure_alerting_cards = [
      $browser->get("{$templates_path}configure_rules_card.php{$default_get_params}"),
      $browser->get("{$templates_path}setup_alerting_card.php{$default_get_params}")
    ];

    Promise\all($configure_alerting_cards)->then(function($all_returned){
  ?>
  <div id="setup-node-alerting-pane" class="tab-content">
    <div class="tab-pane fade" id="configure-rules" role="tabpanel" aria-labelledby="configure-rules-tab">
    <?php echo $all_returned[0]->getBody(); ?>
    </div>
    <div class="tab-pane fade" id="setup-alerting" role="tabpanel" aria-labelledby="setup-alerting-tab">
    <?php echo $all_returned[1]->getBody(); ?>
    </div>
    <div class="tab-pane fade" id="alerting-history" role="tabpanel" aria-labelledby="alerting-history-tab">
      ...
    </div>
  </div>
</div>
  <?php
    });
  ?>

<?php }); ?>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/alerting/js/alerting.js"?>></script>
<?php }); ?>