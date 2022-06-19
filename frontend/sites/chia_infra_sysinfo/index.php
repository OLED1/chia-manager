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
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/css/chia_infra_sysinfo.css"?>" rel="stylesheet">
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
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <h4>Quick actions for selected services (overview)</h4>
        <div id="quick_action_set_action" style="display: none;">
          <div class="input-group mb-3">
            <div class="input-group-prepend">
              <div class="btn-group btn-group-toggle" data-toggle="buttons">
                <label class="btn btn-outline-primary">
                  <input type="radio" class="quick_option_radio" value="ack" name="quick_option_type_radio">Acknowledge
                </label>
                <label class="btn btn-outline-primary">
                  <input type="radio" class="quick_option_radio" value="dt" name="quick_option_type_radio">Downtime
                </label>
              </div>
            </div>
            <input type="text" id="quick_option_dt_time_from" class="form-control datepicker quick_option_input" placeholder="Downtime from" style="width: 5em; display: none;" disabled>
            <input type="text" id="quick_option_dt_time_to" class="form-control datepicker quick_option_input" placeholder="Downtime to" style="width: 5em; display: none;" disabled>
            <input type="text" id="quick_option_comment_input" class="form-control quick_option_input" placeholder="Acknowledge or Downtime text" disabled>
            <div class="input-group-append">
              <button id="save_quick_action" class="btn btn-outline-success" type="button" disabled><i class="fa-solid fa-floppy-disk"></i></button>
            </div>
          </div>
        </div>
        <div id="quick_action_show_info" class="alert alert-secondary" role="alert">
          Select the checkboxes of the services you want to set up a downtime or an acknowledgement for.
        </div>
      </div>
    </div>
  </div>
</div>
<div id="all_node_sysinfo_container">
<?php
  include("templates/cards.php"); 
?>
</div>
<div class="modal fade" id="setDownTimeModal" data-node-id="" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">Downtime for node <span id="downtimeModalHostname"><span></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="card shadow mb-4">
          <div class="card-body">
            <h5>Setup downtime for this host</h5>
            <p>A dowtime mutes a certain service for alerting. So if a service reaches an alerting level it will not be alerted during your selected downtime period.<br>
              If you want that no service will be alerted you can setup a downtime for the whole node by selecting "Node" in the downtime type selection.
            </p>
            <div class="input-group mb-3">
              <div class="input-group-prepend">
                <div class="dropdown">
                  <button id="downtime_select_type" data-selected="" class="btn btn-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Type</button>
                  <div class="dropdown-menu" aria-labelledby="downtime_select_type">
                    <a class="dropdown-item" href="#" data-value="0">Node</a>
                    <a class="dropdown-item" href="#" data-value="1">Service</a>
                  </div>
                </div>
              </div>
              <select id="alerting_service_and_type_select" multiple="multiple">
              </select>
              <div class="input-group-prepend">
                <span class="input-group-text">from</span>
              </div>
              <input id="downtime_input_from" type="text" class="form-control datepicker downtime_input" placeholder="Time from" aria-label="Time from" aria-describedby="basic-addon1" style="max-width: 15em;">
              <div class="input-group-append">
                <span class="input-group-text">to</span>
              </div>
              <input id="downtime_input_to" type="text" class="form-control datepicker downtime_input" placeholder="Time to" aria-label="Time to" aria-describedby="basic-addon1" style="max-width: 15em;">
              <input id="downtime_input_comment" type="text" class="form-control downtime_input" placeholder="Comment (max 100)" aria-label="Comment" aria-describedby="basic-addon1" maxlength="100" style="max-width: 15em;">
              <button id="downtime_save" class="btn btn-success fa-solid fa-floppy-disk wsbutton" type="button" style="display: none;"></button>
            </div>
          </div>
        </div>
        <div class="card shadow">
          <div class="card-body">
            <h5>Downtime history (24 hours ago to near future)</h5>
            <div class="row">
              <div class="col">
                <div class="card border-secondary">
                  <div class="card-body">
                    <h6>
                      Upcomming&nbsp;
                      <span id="downTimeModalUpcommingCount">(?)</span>&nbsp;
                      <button type="button" class="btn btn-warning btn-sm  edit-downtimes" data-starttype=2 data-toggle="tooltip" data-placement="top" title="Edit (all) services"><i class="fa-regular fa-pen-to-square"></i></button>
                    </h6>
                    <div id="downTimeModalUpcomming" class="downtimeModalContainer">
                    </div>
                  </div>
                </div> 
              </div>
              <div class="col">
                <div class="card border-secondary">
                  <div class="card-body">
                    <h6>
                      Current&nbsp;
                      <span id="downTimeModalCurrentCount">(?)</span>&nbsp;
                      <button type="button" class="btn btn-warning btn-sm edit-downtimes" data-starttype=1 data-toggle="tooltip" data-placement="top" title="Edit (all) services"><i class="fa-regular fa-pen-to-square"></i></button>
                    </h6>
                    <div id="downTimeModalCurrent" class="downtimeModalContainer">
                    </div>
                  </div>
                </div> 
              </div>
              <div class="col">
                <div class="card border-secondary">
                  <div class="card-body">
                    <h6>Expired&nbsp;<span id="downTimeModalExpiredCount">(?)</span></h6>
                    <div id="downTimeModalExpired" class="downtimeModalContainer">
                    </div>
                  </div>
                </div> 
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="saveDowntimeModal" data-downtime-for="" data-authhash="" data-conf-id="" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">Save downtime?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to save the following downtime? It will mute all stated services in case of alerting.</p>
        <p><b>Node:</b> <span id="saveDowntimeNode"></span><br>
        <b>Target(s):</b> <span id="saveDowntimeTargets"></span>
        <b>Timespan:</b> <span id="saveDowntimeTimeRange"></span><br>
        <b>Comment:</b> <span id="saveDowntimeComment"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" id="createAndSaveDowntime" class="btn btn-success wsbutton">Create downtime</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Continue editing</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="editDowntimeModal" data-edit-starttype="" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">Edit downtimes</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h4>Select downtime(s) to edit</h4>
        <div class="input-group mb-3">
          <select id="edit_downtime_services_select" multiple="multiple">
          </select>
        </div>
        <h4>Downtime(s) that will be edited</h4>
        <div id="editDowntimeServicesDetails" style="overflow: auto; height: 25em;">
        </div>
        <h4>Edit or remove selected downtimes(s)</h4>
        <p>All empty boxes will not edit this specific setting to the selected downtimes.</p>
        <div>
        <div class="input-group mb-3">
            <span class="input-group-text" id="edit_downtime_comment">Remove selected</span>
            <div class="input-group-prepend">
              <div class="input-group-text">
                <input id="remove_selected_downtimes" type="checkbox" aria-label="Checkbox for following text input">
              </div>
            </div>
          </div>
          <div class="input-group mb-3">
            <div class="input-group-prepend">
              <span class="input-group-text" id="edit_downtime_comment">Comment</span>
            </div>
            <input id="edit_downtime_comment_input" type="text" class="form-control edit-downtime-input" placeholder="Comment" aria-label="Comment" aria-describedby="edit_downtime_comment">
          </div>
          <div class="input-group mb-3">
            <div class="input-group-prepend">
              <span class="input-group-text" id="edit_downtime_from">Start time (from)</span>
            </div>
            <input id="edit_downtime_from_input" type="text" class="form-control datepicker  edit-downtime-input" placeholder="Start time" aria-label="Start time" aria-describedby="edit_downtime_from">
          </div>
          <div class="input-group mb-3">
            <div class="input-group-prepend">
              <span class="input-group-text" id="edit_downtime_to">End time (to)</span>
            </div>
            <input id="edit_downtime_to_input" type="text" class="form-control datepicker  edit-downtime-input" placeholder="End time" aria-label="End time" aria-describedby="edit_downtime_to">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="saveDowntimeEditChanges" class="btn btn-success">Save changes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="saveEditDowntimes" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">Edit selected downtime(s)?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to edit the following downtime(s)? The changes will take affect to all selected downtime(s).</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="saveEditedAndSelectedDowntimes" class="btn btn-danger wsbutton">Edit selected downtime(s)</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Recheck</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="changeMonitoredServices" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle"><i class="fa-solid fa-puzzle-piece"></i> Enable or disable service monitoring</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <p>Decide whether a service should be monitored (will be activated automatically when detected) or unmonitored if not necessarry.</p>
        </div>
        <div class="row">
          <div class="col">
            <h4>Monitored services</h4>
            <div id="monitored_services" style="height: 40em; overflow: auto;">
            </div>
          </div>
          <div class="col">
            <h4>Unmonitored services</h4>
            <div id="unmonitored_services" style="height: 40em; overflow: auto;">
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="quickOptionsSaveDTorACK" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">
          <i id="quickOptionDowntimeIcon" class="fa-solid fa-pause fa-lg"></i>
          <i id="quickOptionAcknowledgeIcon" class="fa-solid fa-note-sticky fa-lg"></i>
          Create <span class="quickOptionCreateType"></span>
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to create the following <span class="quickOptionCreateType"></span>(s) for host <span id="quickOptionHost"></span>?
        </p>
        <div id="quickOptionDTOptions">
          <p>Downtime from: <span id="quickOptionDTFrom"></span><br>
          Downtime to: <span id="quickOptionDTTo"></span>
          </p>
          <div class="custom-control custom-radio">
            <input type="radio" id="dt_whole_node" name="quickOptionsdtRange" class="custom-control-input" value=0>
            <label class="custom-control-label" for="dt_whole_node">Set downtime for the whole node</label>
          </div>
          <div class="custom-control custom-radio">
            <input type="radio" id="dt_only_selected" name="quickOptionsdtRange" class="custom-control-input" value=1>
            <label class="custom-control-label" for="dt_only_selected">Set downtime for selected <span id="quickOptionSelectedServicesCount"></span> services</label>
          </div>
        </div>
        <p>Comment: <span id="quickOptionDTACKMessage"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" id="saveSetupQuickOption" class="btn btn-success wsbutton">Set&nbsp;<span class="quickOptionCreateType"></span></button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/js/chia_infra_sysinfo.js"?>></script>
