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
<div id="all_node_sysinfo_container">
<?php
  include("templates/cards.php"); 
?>
</div>
<div class="modal fade" id="setDownTimeModal" data-authhash="" data-conf-id="" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
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
                      <button type="button" class="btn btn-warning btn-sm" data-toggle="tooltip" data-placement="top" title="Edit (all) services"><i class="fa-regular fa-pen-to-square"></i></button>
                      <button type="button" class="btn btn-primary btn-sm" data-toggle="tooltip" data-placement="top" title="View details (all)"><i class="fa-regular fa-eye"></i></button>
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
                      <button type="button" class="btn btn-warning btn-sm" data-toggle="tooltip" data-placement="top" title="Edit (all) services"><i class="fa-regular fa-pen-to-square"></i></button>
                      <button type="button" class="btn btn-primary btn-sm" data-toggle="tooltip" data-placement="top" title="View details (all)"><i class="fa-regular fa-eye"></i></button>
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
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_infra_sysinfo/js/chia_infra_sysinfo.js"?>></script>
