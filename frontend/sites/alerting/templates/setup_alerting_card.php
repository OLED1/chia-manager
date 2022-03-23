<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Users\Users_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  $alerting_api = new Alerting_Api();
  $alerting_rules = $alerting_api->getConfiguredRules();
  if(array_key_exists("data", $alerting_rules)) $alerting_rules = $alerting_rules["data"];
  else $alerting_rules = [];

  $alerting_custom_rules = $alerting_api->getAvailableRuleTypesAndServices();
  if(array_key_exists("data", $alerting_custom_rules)) $alerting_custom_rules = $alerting_custom_rules["data"];
  else $alerting_custom_rules = [];

  $configured_alertings = $alerting_api->getConfiguredAlertingRules();
  if(array_key_exists("data", $configured_alertings)) $configured_alertings = $configured_alertings["data"];
  else $configured_alertings = [];

  $alerting_services = $alerting_api->getAvailableServices()["data"];
  
  $users_api = new Users_Api();
  $users = $users_api->getUserData()["data"];


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

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  echo "<script nonce={$ini["nonce_key"]}>
    var alerting_rules = " . json_encode($alerting_rules) . ";
    var alerting_services = " . json_encode($alerting_services) . ";
    var available_custom_rules = " . json_encode($alerting_custom_rules) . ";
    var available_setup_alertings = " . json_encode($configured_alertings) . ";
    var users = " . json_encode($users) . "
    var nodes = " . json_encode($chia_nodes) . ";
  </script>";
?>
<div class="card shadow" style="margin: 1em;">
  <div class="card-body">
    <h5>Available rules</h5>
    In this section you are able to define which services you want to become alerted.<br>
    Define for every created rule how you want to become alerted.
    <?php if(count($chia_nodes) > 0){ ?>
    <ul class="nav nav-tabs" id="setup-alerting-node-tabs" role="tablist">
      <?php foreach($chia_nodes AS $arrkey => $chia_node){ ?>
      <li class="nav-item">
        <a class="nav-link setup-alerting-node-tab" id="setup-alerting-node-<?php echo $chia_node["nodeid"]; ?>-tab" data-toggle="tab" href="#setup-alerting-node-content-<?php echo $chia_node["nodeid"]; ?>" role="tab" aria-controls="setup-alerting-node-content-<?php echo $chia_node["nodeid"]; ?>" aria-selected="true"><?php echo $chia_node["hostname"]; ?></a>
      </li>
      <?php } ?>
    </ul>
    <div id="setup-alerting-node-pane" class="tab-content">
      <?php foreach($chia_nodes AS $arrkey => $chia_node){ ?>
      <div class="tab-pane fade" id="setup-alerting-node-content-<?php echo $chia_node["nodeid"]; ?>" role="tabpanel" aria-labelledby="setup-alerting-node-<?php echo $chia_node["nodeid"]; ?>-tab">
        <div class="card shadow" style="margin: 1em;">
          <div class="card-body">
            <div id="alerting_custom_rules_<?php echo $chia_node["nodeid"]; ?>">
            <?php 
              if(array_key_exists("by_rule_default", $alerting_rules)){
                foreach($alerting_rules["by_rule_default"] AS $rule_default => $found_rules){
                  if($rule_default == 1) echo "<h5>Default rules</h5><hr>";
                  else echo "<h5>Custom rules</h5><hr>";
                  foreach($found_rules AS $rule_id => $rule){
                    if($rule_default == 0 && $rule["node_id"] != $chia_node["nodeid"]) continue;
            ?>
              <div class="input-group mb-3 alerting-rule rule" data-rule-id=<?php echo $rule["id"]; ?> >
                <div class="input-group-prepend">
                  <span class="input-group-text service-description"><?php echo $rule["service_desc"]; ?></span>
                  <?php if($rule_default == 0){ ?><span class='input-group-text target-service'><?php echo $rule["rule_target"]; ?></span><?php } ?>
                  <span class="input-group-text bg-warning warn_level"><?php echo ($rule["perc_or_min"] == 0 ? "Warn at {$rule["warn_at_after"]} % usage" : "Warn after {$rule["warn_at_after"]} minutes"); ?></span>
                  <span class="input-group-text bg-danger crit_level"><?php echo ($rule["perc_or_min"] == 0 ? "CRIT at {$rule["crit_at_after"]} % usage" : "CRIT after {$rule["crit_at_after"]} minutes"); ?></span>
                  <span class="input-group-text">Alerting enabled:</span>
                </div>
                <?php 
                  foreach($alerting_services AS $service_id => $alerting_service){
                    $bg_color = "bg-secondary";
                    if(array_key_exists($chia_node["nodeid"], $configured_alertings["by_rule_node_target"]) &&
                        array_key_exists($rule["id"], $configured_alertings["by_rule_node_target"][$chia_node["nodeid"]]) &&
                        array_key_exists($alerting_service["id"], $configured_alertings["by_rule_node_target"][$chia_node["nodeid"]][$rule["id"]]) &&
                        ($configured_alertings["by_rule_node_target"][$chia_node["nodeid"]][$rule["id"]][$alerting_service["id"]]["warn_alert_after"] >= 0 ||
                        $configured_alertings["by_rule_node_target"][$chia_node["nodeid"]][$rule["id"]][$alerting_service["id"]]["crit_alert_after"] >= 0)
                    ){
                      if(count($configured_alertings["by_rule_node_target"][$chia_node["nodeid"]][$rule["id"]][$alerting_service["id"]]["alerting_user_ids"]) > 0) $bg_color = "bg-success";
                      else $bg_color = "bg-warning";
                    }  
                ?>
                <div class="input-group-append">
                  <span class="input-group-text alerting-service-check <?php echo $bg_color; ?>" data-alerting-service-id=<?php echo $alerting_service["id"]; ?>><?php echo $alerting_service["service_name"]; ?></span>
                </div>
                <?php } ?>
                <div class="input-group-append">
                  <button id="edit-alerting-all-<?php echo $rule["id"]; ?>" data-rule-id=<?php echo $rule["id"]; ?> data-node-id=<?php echo $chia_node["nodeid"]; ?> class='btn btn-warning edit-alerting-rule wsbutton' type='button'><i class='fa-solid fa-pencil'></i></button>
                  <button id="help-alerting-<?php echo $rule["id"]; ?>" data-rule-id=<?php echo $rule["id"]; ?> data-node-id=<?php echo $chia_node["nodeid"]; ?> class="btn btn-info help-alerting-rule fa-solid fa-circle-question" type="button"></button>
                </div>
              </div>
            <?php
                  }
                } 
              }
            ?>   
            </div>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
    <?php }else{ ?>
      <div class="card bg-warning text-white shadow">
        <div class="card-body">
          There are currently no nodes set up. Please configure one by installing the agent (NodeClient) on one of your systems.
        </div>
      </div>
    <?php } ?>
  </div>
</div>
<div id="configure_rule_alerting_modal" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fa-solid fa-pencil"></span>&nbsp;Configure alerting (Rule ID:&nbsp;<span id="configure_rule_alerting_rule_id"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <p>
          <strong>Rule system type:</strong>&nbsp<span id="configure_rule_alerting_system_type" class="badge badge-primary"></span><br>
          <strong>Rule target:</strong>&nbsp<span id="configure_rule_alerting_target" class="badge badge-primary"></span><br>
          <strong>Service type:</strong>&nbsp<span id="configure_rule_alerting_service_type" class="badge badge-primary"></span><br>
          <strong>Service target:</strong>&nbsp<span id="configure_rule_alerting_service_target" class="badge badge-primary"></span><br>
          <strong>Rule warning level:</strong>&nbsp<span id="configure_rule_alerting_warn_level" class="badge badge-warning"></span><br>
          <strong>Rule critical level:</strong>&nbsp<span id="configure_rule_alerting_crit_level" class="badge badge-danger"></span><br>
        </p>
        <ul class="nav nav-tabs" id="edit-rule-alerting-services-tabs" role="tablist">
          <?php foreach($alerting_services AS $service_id => $alerting_service){ ?>
          <li class="nav-item">
            <a class="nav-link" id="edit_alerting_rule_<?php echo $alerting_service["id"]; ?>-tab" data-toggle="tab" href="#edit_alerting_rule_<?php echo $alerting_service["id"]; ?>" role="tab" aria-controls="edit_alerting_rule_<?php echo $alerting_service["id"]; ?>" aria-selected="true"><?php echo $alerting_service["service_name"]; ?></a>
          </li>
          <?php } ?>
        </ul>
        <div id="edit-rule-alerting-services-pane" class="tab-content">
          <?php foreach($alerting_services AS $service_id => $alerting_service){ ?>
          <div class="tab-pane fade" id="edit_alerting_rule_<?php echo $alerting_service["id"]; ?>" role="tabpanel" aria-labelledby="edit_alerting_<?php echo $alerting_service["id"]; ?>-tab">
            <h5>Setup alerting for service <?php echo $alerting_service["service_name"]; ?></h5>
            <p>Alert the service as stated below</p>
            <div class="input-group mb-3">
              <div class="input-group-prepend">
                <span class="input-group-text bg-warning alerting-service-check" style="width: 14em;">Alert WARN immediately</span>
                <div class="input-group-text">
                  <input type="checkbox" class="edit-rule-alerting-warn-immediately" data-service-id=<?php echo $alerting_service["id"]; ?>>
                </div>
              </div>
            </div>
            <div class="input-group mb-3">
              <div class="input-group-prepend">
                <span class="input-group-text bg-warning" style="width: 14em;">Alert when WARN exceeds</span>
              </div>
              <input type="text" class="form-control edit-rule-alerting-warn-custom" data-service-id=<?php echo $alerting_service["id"]; ?>>
              <span class="input-group-text">minutes</span>
            </div>
            <div class="input-group mb-3">
              <div class="input-group-prepend">
                <span class="input-group-text bg-danger alerting-service-check" style="width: 14em;">Alert CRIT immediately</span>
                <div class="input-group-text">
                  <input type="checkbox" class="edit-rule-alerting-crit-immediately" data-service-id=<?php echo $alerting_service["id"]; ?>>
                </div>
              </div>
            </div>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text bg-danger" style="width: 14em;">Alert when CRIT exceeds</span>
              </div>
              <input type="text" class="form-control edit-rule-alerting-crit-custom" data-service-id=<?php echo $alerting_service["id"]; ?>>
              <span class="input-group-text">minutes</span>
            </div>
            <p>Send alerting to following users</p>
            <div class="input-group mb-3" id="types">
              <select class="edit-rule-alerting-contacts" data-service-id=<?php echo $alerting_service["id"]; ?> multiple="multiple">
                <?php foreach($users AS $userID => $userData){ ?>
                  <option value=<?php echo $userID; ?>><?php echo $userData["username"] . " - " . $userData["name"] . " " . $userData["lastname"]; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="save-rule-alerting" class="btn btn-success wsbutton">Save changes and close</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close and discard changes</button>
      </div>
    </div>
  </div>
</div>
<div id="configured_rule_alerting_help_modal" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fa-solid fa-circle-info"></span>&nbsp;Rule alerting details (Rule ID:&nbsp;<span id="configured_rule_alerting_rule_id"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <h5>Rule overall information</h5>
        <p>
          <strong>Rule system type:</strong>&nbsp<span id="configured_rule_alerting_system_type" class="badge badge-primary"></span><br>
          <strong>Rule target:</strong>&nbsp<span id="configured_rule_alerting_target" class="badge badge-primary"></span><br>
          <strong>Service type:</strong>&nbsp<span id="configured_rule_alerting_service_type" class="badge badge-primary"></span><br>
          <strong>Service target:</strong>&nbsp<span id="configured_rule_alerting_service_target" class="badge badge-primary"></span><br>
          <strong>Rule warning level:</strong>&nbsp<span id="configured_rule_alerting_warn_level" class="badge badge-warning"></span><br>
          <strong>Rule critical level:</strong>&nbsp<span id="configured_rule_alerting_crit_level" class="badge badge-danger"></span><br>
        </p>
        <h5>Rule alerting information</h5>
        <div id="alerting-services-info-container" style="max-height: 20em; overflow: auto;">
        <?php foreach($alerting_services AS $service_id => $alerting_service){ ?>
          <div data-alerting-service-id=<?php echo $alerting_service["id"]; ?> class="alerting-service-information alert alert-dark" role="alert">
            <h5 class="fg-primary">Alerting for service <?php echo $alerting_service["service_name"]; ?></h5>
            <p>
              <ul class="list-unstyled">
                <li><strong>Alerts warn after:</strong>&nbsp<span class="alerting-info-alerts-warn badge badge-success">Immediately</span></li>
                <li><strong>Alerts crit after:</strong>&nbsp<span class="alerting-info-alerts-crit badge badge-danger">Never</span></li>
                <li><strong>Alerts to: <span class="alerting-info-alerts-name badge badge-danger"></span></strong>
                  <ul class="alerting-info-alerts-to-recepients">
                  </ul>
                </li>
              </ul>
            </p>
          </div>
        <?php } ?>
        </div>
        <div id="alerting-services-info-none-configured" class="alert alert-warning" style="display: none;">
          This service is currently not configured for alerting.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>