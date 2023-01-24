<?php
  use React\Promise;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../vendor/autoload.php';
  include("../standard_headers.php");

  $nodes_api = new Nodes_Api();

  $nodes_promises = [
    Promise\resolve($nodes_api->getConfiguredNodes()),
    Promise\resolve($nodes_api->getActiveSubscriptions()),
    Promise\resolve($nodes_api->getActiveRequests()),
    Promise\resolve($nodes_api->getNodeTypes()),
    Promise\resolve($nodes_api->checkUpdatesAndChannels())
  ];

  Promise\all($nodes_promises)->then(function($all_returned) use($ini){
    $configuredNodes = $all_returned[0];
    $activeSubscriptions = (is_Null($all_returned[1]) ? [] : $all_returned[1]);
    $activeRequests = (is_Null($all_returned[1]) ? [] : $all_returned[2]);
    $nodetypes = $all_returned[3];
    $scriptupdatesavail = $all_returned[4];

    print_r($all_returned);


    if(array_key_exists("data", $configuredNodes)) $configuredNodes = $configuredNodes["data"];
    if(array_key_exists("data", $activeSubscriptions)) $activeSubscriptions = $activeSubscriptions["data"];
    if(array_key_exists("data", $activeRequests)) $activeRequests = $activeRequests["data"];
    if(array_key_exists("data", $nodetypes)) $nodetypes = $nodetypes["data"];
    if(array_key_exists("data", $scriptupdatesavail)) $scriptupdatesavail = $scriptupdatesavail["data"];
  
    echo "<script nonce={$ini["nonce_key"]}> var configuredNodes = " . json_encode($configuredNodes) . ";
            var siteID = 2;
            var activeSubscriptions = " . json_encode($activeSubscriptions) . ";
            var activeRequests = " . json_encode($activeRequests) . ";
            var nodetypes = " . json_encode($nodetypes) . ";
            var scriptupdatesavail = " . json_encode($scriptupdatesavail) . ";
          </script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/nodes/css/nodes.css"?>" rel="stylesheet">

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Nodes</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you are able to allow and deny access to the websocket backend of the <span style="font-size: 0.9rem;">Chia®</span> Manager.<br>
        Please be aware of some settings: If the IP of a node changes, so the change must be accepted otherwise all connections will be declined. You can allow the connection by pressing the yellow button under "IP Address" which appears in such case.<br>
        In case of a new connection it can be accepted or denied using the buttons located under "Actions".
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <h5>Node install instructions</h5>
    <div style="margin-bottom: 1em;">
      <p>
        <button class="btn btn-primary" data-toggle="collapse" href="#linuxInstructionCollapse" role="button" aria-expanded="false" aria-controls="linuxInstructionCollapse"><i class="fab fa-linux"></i>&nbsp;Linux</button>
        <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#windowsInstructionCollapse" aria-expanded="false" aria-controls="windowsInstructionCollapse"><i class="fab fa-windows"></i>&nbsp;Windows</button>
      </p>
      <div class="row">
        <div class="col">
          <div class="collapse multi-collapse" id="linuxInstructionCollapse">
            <div class="card card-body">
              <p>To be able to gather live data from your nodes you need to install the python node client on your system(s).</p>
              <ol >
                <li >Navigate to the parent directory where you want to install the node client. e.g. cd /usr/local/bin/</li>
                <li>git clone https://github.com/OLED1/chia-node-client.git</li>
                <li>cd chia-node-client</li>
                <li>chmod +x install.sh</li>
                <li>. install.sh</li>
                <li>
                  If you did not decide to install the node client as service, please run "python3 run_node_client.py" to start it manually,<br>
                  otherwise run: sudo systemctl start chia-node-client.service
                </li>
                <li>Now you need to allow the new client in the table below</li>
              </ol>
              <div class="alert alert-success" role="alert">
                Find further information in our <a target="_blank" href="https://docs.chia-manager.org/user-documentation/node-client-installation">documentation</a>.
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <div class="collapse multi-collapse" id="windowsInstructionCollapse">
            <div class="card card-body">
              The installer is currently not working on Windows because the main target for this project is Linux. So the installer was firstly made for Linux.<br>
              If you want to help me to develop a Windows client, you are welcome! But no worries, it is in planning.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row">
    <div class="col">
        <div class="card shadow mb-4">
            <div
                class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Configured clients</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                  <table class="table table-bordered" id="configuredClients" width="100%" cellspacing="0">
                      <thead>
                          <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Authtype</th>
                            <th>Connection Allowed</th>
                            <th>Hostname</th>
                            <th>Chiaversion</th>
                            <th>Scriptversion</th>
                            <th>IP Address</th>
                            <th>Connections</th>
                            <th>Last seen</th>
                            <th>Actions</th>
                          </tr>
                      </thead>
                      <tbody>
                      </tbody>
                      <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Authtype</th>
                            <th>Connection Allowed</th>
                            <th>Hostname</th>
                            <th>Chiaversion</th>
                            <th>Scriptversion</th>
                            <th>IP Address</th>
                            <th>Connections</th>
                            <th>Last seen</th>
                            <th>Actions</th>
                          </tr>
                      </tfoot>
                  </table>
              </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="allowIPModal" data-mode="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="allowIPModalTitle">Accept IP Change</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to accept the IP change from <strong id="oldip"></strong> to new IP <strong id="newip"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button" id="saveIPChange" class="btn btn-success">Accept change</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="acceptNodeRequestModal" data-authhash="" data-conf-id="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="acceptNodeRequestModalTitle">Accept Node</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Before the api can access the node and reverse, some information is needed.</p>
        <div class="row">
          <div class="col-12">
            <hr>
            <div class="card shadow mb-4">
              <div class="card-body" id="nodeinfo">
                <h5>Node definition</h5>
                <div class="form-check">
                  <input class="form-check-input nodedefinition" type="radio" name="nodedef" id="type_app" value="app">
                  <label class="form-check-label" for="type_app">
                    App (Web + Mobile)
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input nodedefinition" type="radio" name="nodedef" id="type_chianode" value="chianode">
                  <label class="form-check-label" for="type_chianode">
                    Chia Node
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <hr>
            <div class="card shadow mb-4">
              <div class="card-body" id="nodeinfo">
                <h5>Nodetype</h5>
                <div class="input-group mb-3" id="types">
                  <select id="nodetypes-options" multiple="multiple">
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="card shadow mb-4">
              <div class="card-body">
                <h5>Authentication Type</h5>
                <span id="authtype" class="badge bg-info text-dark">Not known</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="acceptNodeRequest" class="btn btn-success" disabled>Accept node</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="declineNodeRequestModal" data-authhash="" data-conf-id="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="declineNodeRequestModalTitle">Decline connection</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to decline the connection for node <strong id="declinemodal-nodeid"></strong> with authhash <strong id="declinemodal-authhash"></strong>?</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="decline-node" class="btn btn-danger">Decline connection</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="removeNodeModal" data-authhash="" data-conf-id="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="removeNodeModalModalTitle">Remove node</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Do you really want to remove the node <strong id="removeNodeModal-nodeid"></strong> with authhash <strong id="removeNodeModal-authhash"></strong>?
        <br>This node and it's data will be inaccassable forever!</p>
      </div>
      <div class="modal-footer">
        <button type="button" id="remove-node" class="btn btn-danger">Remove node</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="nodeactionmodal" data-nodeid="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Node Information</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" id="node-info-navtabs" role="tablist">
          <li class="nav-item">
            <a class="nav-link active" id="node-infos-tab" data-toggle="tab" href="#node-infos" role="tab" aria-controls="node-infos" aria-selected="true">Node Info</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="node-commands-tab" data-toggle="tab" href="#node-commands" role="tab" aria-controls="node-commands" aria-selected="">Update Node</a>
          </li>
        </ul>
        <div class="tab-content" id="nav-tabContent" style="min-height: 30em; margin-top: 1em;">
          <!--Node infos-->
          <div class="tab-pane fade show active" id="node-infos" role="tabpanel" aria-labelledby="node-infos-tab">
            <div class="row">
              <div class="col mb-4">
                <div class="text-center">
                  <i class="fab fa-linux fa-5x"></i>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <div class="container">
                  <div class="row">
                    <div class="col">
                      <b>System:</b>
                    </div>
                    <div id="osinfo" class="col" style="text-align: right;">
                      Linux
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Hostname:</b>
                    </div>
                    <div id="hostname" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Client&nbsp;authhash:</b>
                    </div>
                    <div id="nodeauthhash" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Client Scriptversion:</b>
                    </div>
                    <div id="scriptversion" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Client Node Config:</b>
                    </div>
                    <div id="nodetype" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Chia Version:</b>
                    </div>
                    <div id="chiaversion" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Chia activate path:</b>
                    </div>
                    <div id="chiapath" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>CPU Model:</b>
                    </div>
                    <div id="cpu_model" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>CPU Cores / Threads:</b>
                    </div>
                    <div id="cpu_cores_threads" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Installed RAM / Swap:</b>
                    </div>
                    <div id="ram_swap_size" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Local IP:</b>
                    </div>
                    <div id="localIP" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                  <div class="row">
                    <div class="col">
                      <b>Remote IP:</b>
                    </div>
                    <div id="ipaddress" class="col infotext" style="text-align: right;">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!--Node commands-->
          <div class="tab-pane fade" id="node-commands" role="tabpanel" aria-labelledby="node-commands-tab">
            <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Update Node <strong id="actionmodal-nodename"></strong></h6>
              </div>
              <div id="all_node_sysinfo_container" class="card-body">
                <div class="dropdown">
                  <button class="btn btn-secondary dropdown-toggle" type="button" id="updatechannelsMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></button>
                  <label for="updatechannelsMenu">Update Channel</label>
                  <div id="updatechannels-modal" class="dropdown-menu" aria-labelledby="updatechannelsMenu">
                    <?php foreach($scriptupdatesavail["available_channels"] AS $arrkey => $channelname){
                      echo "<button class='dropdown-item scriptbranchoption wsbutton' data-branch='{$channelname}' href='#'>" . getFullNameFromBranch($channelname) . "</button>";
                    }

                    function getFullNameFromBranch($channelname){
                      switch ($channelname){
                        case "dev":
                          return "Development";
                        case "staging":
                          return "Staging";
                        case "main":
                          return "Productive";
                        default:
                          return "Not set";
                      }
                    }
                    ?>
                  </div>
                </div>
                <hr/>
                <p>Current Version: <span id="current_version" class="infotext">None</span>
                  <br>Remote Version: <span id="remote_version" class="infotext">None</span>
                  <br>Update Available: <span id="update_available" class="infotext">None</span>
                </p>
                <hr />
                <button class="btn btn-secondary wsbutton" type="button" id="check-for-updates">Check for updates</button>
                <button class="btn btn-warning wsbutton" type="button" id="updatenode">Update Node</button>
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
<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/nodes/js/nodes.js"?>></script>
<?php }); ?>