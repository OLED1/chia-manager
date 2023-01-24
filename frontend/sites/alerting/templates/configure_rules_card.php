<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $alerting_api = new Alerting_Api();

  $site_data_to_load = [
    React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    React\Promise\resolve($alerting_api->getConfiguredRules(["monitor" => 1])),
    React\Promise\resolve($alerting_api->getAvailableRuleTypesAndServices()),
    React\Promise\resolve((new Nodes_Api())->getConfiguredNodes())
  ];

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  React\Promise\all($site_data_to_load)->then(function($all_returned) use($ini){   
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $alerting_rules = $all_returned[1];
    if(array_key_exists("data", $alerting_rules)) $alerting_rules = $alerting_rules["data"];
    else $alerting_rules = [];

    $alerting_custom_rules = $all_returned[2];
    if(array_key_exists("data", $alerting_custom_rules)) $alerting_custom_rules = $alerting_custom_rules["data"];
    else $alerting_custom_rules = [];

    $all_nodes = $all_returned[3];
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

    echo "<script nonce={$ini["nonce_key"]}>
      var alerting_rules = " . json_encode($alerting_rules) . ";
      var available_custom_rules = " . json_encode($alerting_custom_rules) . ";
      var nodes = " . json_encode($chia_nodes) . ";
    </script>";
?>
<div class="card shadow" style="margin: 1em;">
  <div class="card-body">
    <h3>Default rules</h3>
    <p>Define at which time a service should get a specific alerting level. These rules are only defining default rules for all services which are not having custom rules.<br>
    To set up an alerting level valid you need to set at least 1 % or 1 minute.</p>
    <div class="default_rules">
    <?php 
      if(array_key_exists("by_rule_default", $alerting_rules) && array_key_exists(1, $alerting_rules["by_rule_default"]) && count($alerting_rules["by_rule_default"][1]) > 0){ 
        foreach($alerting_rules["by_rule_default"][1] AS $rule_id => $rule){
    ?>
      <div class="input-group mb-3 rule" data-rule-id=<?php echo $rule["id"]; ?> >
        <div class="input-group-prepend">
          <span class="input-group-text service-description"><?php echo $rule["service_desc"]; ?></span>
          <span class="input-group-text bg-warning warn_level"><?php echo ($rule["perc_or_min"] == 0 ? "Warn at" : "Warn after"); ?></span>
        </div>
        <input id="warn_at_after_<?php echo $rule["id"]; ?>" type="number" min=0 class="form-control edit-rules-input warn-input" value="<?php echo $rule["warn_at_after"]; ?>">
        <div class="input-group-append">
          <span class="input-group-text bg-warning warn_perc_min"><?php echo ($rule["perc_or_min"] == 0 ? "% usage" : "minute(s)"); ?></span>
        </div>
        <div class="input-group-append">
          <span class="input-group-text bg-danger crit_level"><?php echo ($rule["perc_or_min"] == 0 ? "CRIT at" : "CRIT after"); ?></span>
        </div>
        <input id="crit_at_after_<?php echo $rule["id"]; ?>" type="number" min=0 class="form-control edit-rules-input crit-input" value="<?php echo $rule["crit_at_after"]; ?>">
        <div class="input-group-append">
          <span class="input-group-text bg-danger crit_perc_min"><?php echo ($rule["perc_or_min"] == 0 ? "% usage" : "minute(s)"); ?></span>
        </div>
        <div class="input-group-append">
          <button id="help-<?php echo $rule["id"]; ?>" class="btn btn-info help-rule fa-solid fa-circle-question" type="button"></button>
          <button id="restore-<?php echo $rule["id"]; ?>" class="btn btn-warning restore-rule wsbutton" type="button" style="display: none;"><i class="fa-solid fa-rotate-left"></i></button>
          <button id="save-<?php echo $rule["id"]; ?>" class="btn btn-success fa-solid fa-floppy-disk save-rule wsbutton" type="button" style="display: none;"></button>
        </div>
      </div>
  <?php
      } 
    }else{ 
  ?>
    <div class="card bg-danger text-white shadow">
      <div class="card-body">
        There is currently no data to show! This should not happen. Please contact us immediately.
      </div>
    </div>
  <?php } ?>
    </div>
  </div>
</div>
<div class="card shadow" style="margin: 1em;">
  <div class="card-body">
    <h5>Custom rules</h5>
    These types of rules allows you to create even more specific rules for all of the known services for your hosts an it's services.<br>
    Create host specific custom rules by clicking one of these tabs.
    <?php if(count($chia_nodes) > 0){ ?>
    <ul class="nav nav-tabs" id="custom-rule-host-tabs" role="tablist">
      <?php foreach($chia_nodes AS $arrkey => $chia_node){ ?>
      <li class="nav-item">
        <a class="nav-link custom-rules-node-tab" id="<?php echo $chia_node["nodeid"]; ?>-tab" data-toggle="tab" href="#host-<?php echo $chia_node["nodeid"]; ?>" role="tab" aria-controls="host-<?php echo $chia_node["nodeid"]; ?>" aria-selected="true"><?php echo $chia_node["hostname"]; ?></a>
      </li>
      <?php } ?>
    </ul>
    <div id="custom-alerting-pane" class="tab-content">
      <?php foreach($chia_nodes AS $arrkey => $chia_node){ ?>
      <div class="tab-pane fade" id="host-<?php echo $chia_node["nodeid"]; ?>" role="tabpanel" aria-labelledby="<?php echo $chia_node["nodeid"]; ?>-tab">
        <div class="card shadow" style="margin: 1em;">
          <div class="card-body">
            <h6>Create custom rule for host <?php echo $chia_node["hostname"]; ?></h6>
            <div id="configurable_services_<?php echo $chia_node["nodeid"]; ?>" class="input-group mb-3 rule">
              <div class="input-group-prepend">
                <div class="dropdown">
                  <button id="types-dropdown-<?php echo $chia_node["nodeid"]; ?>" class="btn btn-secondary dropdown-toggle alerting-types-button" data-node-id=<?php echo $chia_node["nodeid"]; ?> type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Select type</button>
                  <div class="dropdown-menu alerting-types-dropdown" aria-labelledby="types-dropdown-<?php echo $chia_node["nodeid"]; ?>" data-node-id=<?php echo $chia_node["nodeid"]; ?>>
                    <?php 
                      foreach($alerting_custom_rules AS $type_id => $types){
                        if(array_key_exists($chia_node["nodeid"], $alerting_custom_rules[$type_id]["available_services"]) && count($alerting_custom_rules[$type_id]["available_services"][$chia_node["nodeid"]]) > 0 && count($alerting_custom_rules[$type_id]["available_services"][$chia_node["nodeid"]]["configurable_services"]) > 0){ 
                    ?>
                      <a class="dropdown-item" data-node-id=<?php echo $chia_node["nodeid"]; ?> data-type-id=<?php echo $type_id; ?> href="#"><?php echo $types["service_desc"]; ?></a>
                    <?php 
                        }
                      } 
                    ?>
                  </div>
                </div>
                <div class="dropdown">
                  <button id="services-dropdown-<?php echo $chia_node["nodeid"]; ?>" class="btn btn-secondary dropdown-toggle alerting-services-button" data-node-id=<?php echo $chia_node["nodeid"]; ?> type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" disabled>Select service</button>
                  <div class="dropdown-menu alerting-services-dropdown" aria-labelledby="services-dropdown-<?php echo $chia_node["nodeid"]; ?>" data-node-id=<?php echo $chia_node["nodeid"]; ?>>
                  </div>
                </div>
                <span class="input-group-text bg-warning warn_level"></span>
              </div>
              <input type="number" min=0 class="form-control add-rules-input warn-input" value="" disabled>
              <div class="input-group-append">
                <span class="input-group-text bg-warning warn_perc_min"></span>
              </div>
              <div class="input-group-append">
                <span class="input-group-text bg-danger crit_level"></span>
              </div>
              <input type="number" min=0 class="form-control add-rules-input crit-input" value="" disabled>
              <div class="input-group-append">
                <span class="input-group-text bg-danger crit_perc_min"></span>
              </div>
              <div class="input-group-append">
                <button data-node-id="<?php echo $chia_node["nodeid"]; ?>" class="btn btn-success add-custom-rule wsbutton" type="button" style="display: none;"><i class="fa-solid fa-plus"></i></button>
              </div>
            </div>
          </div>
        </div>
        <div class="card shadow" style="margin: 1em;">
          <div class="card-body">
            <h6>Configured custom rules for host <?php echo $chia_node["hostname"]; ?></h6>
            <div id="custom_rules_<?php echo $chia_node["nodeid"]; ?>">
            <?php 
              if(array_key_exists("by_rule_default", $alerting_rules) && array_key_exists(0, $alerting_rules["by_rule_default"]) && count($alerting_rules["by_rule_default"][0]) > 0){ 
                foreach($alerting_rules["by_rule_default"][0] AS $rule_id => $rule){
                  if($rule["node_id"] != $chia_node["nodeid"]) continue;
            ?>
              <div class="input-group mb-3 custom-rule rule" data-rule-id=<?php echo $rule["id"]; ?> >
                <div class="input-group-prepend">
                  <span class="input-group-text service-description"><?php echo $rule["service_desc"]; ?></span>
                  <span class='input-group-text target-service'><?php echo $rule["rule_target"]; ?></span>
                  <span class="input-group-text bg-warning warn_level"><?php echo ($rule["perc_or_min"] == 0 ? "Warn at" : "Warn after"); ?></span>
                </div>
                <input id="warn_at_after_<?php echo $rule["id"]; ?>" type="number" min=0 class="form-control edit-rules-input warn-input" value="<?php echo $rule["warn_at_after"]; ?>">
                <div class="input-group-append">
                  <span class="input-group-text bg-warning warn_perc_min"><?php echo ($rule["perc_or_min"] == 0 ? "% usage" : "minute(s)"); ?></span>
                </div>
                <div class="input-group-append">
                  <span class="input-group-text bg-danger crit_level"><?php echo ($rule["perc_or_min"] == 0 ? "CRIT at" : "CRIT after"); ?></span>
                </div>
                <input id="crit_at_after_<?php echo $rule["id"]; ?>" type="number" min=0 class="form-control edit-rules-input crit-input" value="<?php echo $rule["crit_at_after"]; ?>">
                <div class="input-group-append">
                  <span class="input-group-text bg-danger crit_perc_min"><?php echo ($rule["perc_or_min"] == 0 ? "% usage" : "minute(s)"); ?></span>
                </div>
                <div class="input-group-append">
                  <button id="help-<?php echo $rule["id"]; ?>" class="btn btn-info help-rule fa-solid fa-circle-question" type="button"></button>
                  <button id="remove-<?php echo $rule["id"]; ?>" class='btn btn-danger remove-rule wsbutton' type='button'><i class='fa-solid fa-minus'></i></button>
                  <button id="restore-<?php echo $rule["id"]; ?>" class="btn btn-warning restore-rule wsbutton" type="button" style="display: none;"><i class="fa-solid fa-rotate-left"></i></button>
                  <button id="save-<?php echo $rule["id"]; ?>" class="btn btn-success fa-solid fa-floppy-disk save-rule wsbutton" type="button" style="display: none;"></button>
                </div>
              </div>
          <?php
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
<div id="remove_rule_modal" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-square-minus"></i></span>&nbsp;Remove rule (ID:&nbsp;<span id="remove_rule_id"></span>) for node <strong id="remove_rule_node"></strong></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <p>Do you really want to remove the custom alerting rule of type <strong id="remove_rule_service_desc"></strong> for the service <strong id="remove_rule_target"></strong>?<br><br>
            This service will upon now be alerted using the defined default rule and for this be alerted using the configured alerting services.
            <strong>The alerting service rule will be removed too.</strong>
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger wsbutton" id="remove_rule_modal_button" disabled>Permanently remove (<span id="permanently_remove_timer"></span>)</button>
        <button type="button" class="btn btn-secondary" id="remvoe_rule_modal_cancel" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="rule_help_modal" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fa-solid fa-circle-question"></span>&nbsp;Rule help (Rule ID:&nbsp;<span id="help_modal_rule_id"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <p>
          <strong>Rule system type:</strong>&nbsp<span id="help_modal_rule_system_type" class="badge badge-primary"></span><br>
          <strong>Rule target:</strong>&nbsp<span class="badge badge-primary help_modal_node_target"></span><br>
          <strong>Service type:</strong>&nbsp<span class="badge badge-primary help_modal_service_type"></span><br>
          <strong>Service target:</strong>&nbsp<span id="help_modal_service_target" class="badge badge-primary"></span><br>
          <strong>Rule warning level:</strong>&nbsp<span id="help_modal_rule_warn_level" class="badge badge-warning"></span><br>
          <strong>Rule critical level:</strong>&nbsp<span id="help_modal_rule_crit_level" class="badge badge-danger"></span><br>
        </p>
        <p>
          This service will be alerted when the service "<strong class="help_modal_service_type"></strong>" on the node "<strong class="help_modal_node_target"></strong>" reaches the warn level <strong id="help_modal_text_warn_level"></strong>.
          The service becomes critical <strong id="help_modal_text_crit_level"></strong><br>
          <strong id="help_modal_text_service_target"></strong>.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php }); ?>