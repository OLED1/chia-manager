<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $nodes_api = new Nodes_Api();
  $configuredNodes = $nodes_api->getConfiguredNodes();
  $activeSubscriptions = $nodes_api->getActiveSubscriptions();
  $activeRequests = $nodes_api->getActiveRequests();
  $nodetypes = $nodes_api->getNodeTypes();

  //echo "<pre>";
  //print_r($configuredNodes);
  //echo "</pre>";

  if(array_key_exists("data", $configuredNodes)) $configuredNodes = $configuredNodes["data"];
  if(array_key_exists("data", $activeSubscriptions)) $activeSubscriptions = $activeSubscriptions["data"];
  if(array_key_exists("data", $activeRequests)) $activeRequests = $activeRequests["data"];
  if(array_key_exists("data", $nodetypes)) $nodetypes = $nodetypes["data"];

  echo "<script> var configuredNodes = " . json_encode($configuredNodes) . "; </script>";
  echo "<script> var activeSubscriptions = " . json_encode($activeSubscriptions) . "; </script>";
  echo "<script> var activeRequests = " . json_encode($activeRequests) . "; </script>";
  echo "<script> var nodetypes = " . json_encode($nodetypes) . "; </script>";
  echo "<script> var siteID = 2; </script>";
  echo "<script> var packageslink = '" . $ini["app_protocol"]."://".$ini["app_domain"].$ini["packages_url"] . "'; </script>";
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
        On this page you are able to allow and deny access to websocket backend of the Chia Manager.<br>
        Please be aware of some settings: If the IP of a node changes, so the change must be accepted otherwise all connections will be declined. You cann allow the connection by pressing the yellow button under "IP Address" which appears in such case.<br>
        In case of a new connection it can be accepted or denied using the buttons located under "Actions".
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <h5>Install instructions</h5>
    <div style="margin-bottom: 1em;">
      <p>
        <button class="btn btn-primary" data-toggle="collapse" href="#linuxInstructionCollapse" role="button" aria-expanded="false" aria-controls="linuxInstructionCollapse"><i class="fab fa-linux"></i>&nbsp;Install python node client on Linux</button>
        <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#windowsInstructionCollapse" aria-expanded="false" aria-controls="windowsInstructionCollapse"><i class="fab fa-windows"></i>&nbsp;Install python node client on Windows</button>
      </p>
      <div class="row">
        <div class="col">
          <div class="collapse multi-collapse" id="linuxInstructionCollapse">
            <div class="card card-body">
              <p>To be able to gather live data from your nodes you need to install the python node client on your system(s).</p>
              <ul class="list-group">
                <li class="list-group-item">
                    <strong>Navigate to a directory where you want to install the node client.</strong><br>
                    mkdir /your/desired/path
                </li>
                <li class="list-group-item">
                    <strong>Download and install the client:</strong><br>
                    cd /path/to/your/installation<br>
                    wget <?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["packages_url"]."/packages/chia_python_client_install.zip"?><br>
                    unzip chia_python_client_install.zip
                </li>
                <li class="list-group-item">
                    <strong>Execute the python node</strong><br>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py<br>
                    The client will abort the first startup because the default files are not created yet.<br>
                    The client will create these files which you have to setup.
                </li>
                <li class="list-group-item">
                    <strong>Setup your client config</strong><br>
                    Locate the config file, normally under /path/to/your/installatin/config/chia-client.ini<br>
                    Examle:<br>
                    [ScriptInfo]<br>
                    version = 0.1.1-alpha #Will be generated autmatically<br>
                    <br>
                    [Connection]<br>
                    server = chiamgmt.example.com #Setup your servers domain here<br>
                    port = 443 #Setup your servers secure port here<br>
                    socketdir = /chiamgmt #Default is /chiamgmt. Needed for the websocket connection<br>
                    type = wss #Default is wss. Please do not communication unsecured to your server<br>
                    <br>
                    [NodeInfo]<br>
                    authhash = [somehash] #Will be generated.<br>
                    <br>
                    [Chia]<br>
                    chiaactivatepath = /path/to/chia-blockchain/installation #Exclude 'activate' in string
                </li>
                <li class="list-group-item">
                    <strong>Execute the python node again</strong><br>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py
                </li>
                <li class="list-group-item">
                    <strong>Follow the on screen instructions.</strong><br>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py
                </li>
                <li class="list-group-item">
                    <strong>You are done.</strong>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <div class="collapse multi-collapse" id="windowsInstructionCollapse">
            <div class="card card-body">
              The installer is currently not working on Windows because the main target for this project is Linux. So the installer was firstly made for Linux.<br>
              If you want to help me to develop a Windows client, you are welcome! But no worries, it is in planning.            </div>
          </div>
        </div>
      </div>
    </div>
    <!--
    <div class="card shadow mb-4">
      <div class="accordion" id="nodeInstallInstructions">
        <div class="accordion-item">
          <h2 class="accordion-header" id="linuxInstructionHeading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#linuxInstructionCollapse" aria-expanded="false" aria-controls="linuxInstructionCollapse">
              <i class="fab fa-linux"></i>&nbsp;Install python node client on Linux
            </button>
          </h2>
          <div id="linuxInstructionCollapse" class="accordion-collapse collapse" aria-labelledby="linuxInstructionHeading" data-bs-parent="#nodeInstallInstructions">
            <div class="accordion-body">
              <p>To be able to gather live data from your nodes you need to install the python node client on your system(s).</p>
              <ol class="list-group list-group-numbered">
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Navigate to a directory where you want to install the node client.</div>
                    mkdir /your/desired/path
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Download and install the client:</div>
                    cd /path/to/your/installation<br>
                    wget <?php //echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["packages_url"]."/packages/chia_python_client_install.zip"?><br>
                    unzip chia_python_client_install.zip
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Execute the python node</div>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py<br>
                    The client will abort the first startup because the default files are not created yet.<br>
                    The client will create these files which you have to setup.
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Setup your client config</div>
                    Locate the config file, normally under /path/to/your/installatin/config/chia-client.ini<br>
                    Examle:<br>
                    [ScriptInfo]<br>
                    version = 0.1.1-alpha #Will be generated autmatically<br>
                    <br>
                    [Connection]<br>
                    server = chiamgmt.example.com #Setup your servers domain here<br>
                    port = 443 #Setup your servers secure port here<br>
                    socketdir = /chat #Default is /chiamgmt. Needed for the websocket connection<br>
                    type = wss #Default is wss. Please do not communication unsecured to your server<br>
                    <br>
                    [NodeInfo]<br>
                    authhash = [somehash] #Will be generated.<br>
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Execute the python node again</div>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">Follow the on screen instructions.</div>
                    cd /path/to/your/installation<br>
                    pyhton chia_mgmt_node.py
                  </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                  <div class="ms-2 me-auto">
                    <div class="fw-bold">You are done.</div>
                  </div>
                </li>
              </ol>
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h2 class="accordion-header" id="windowsInstructionHeading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#windowsInstructionCollapse" aria-expanded="false" aria-controls="windowsInstructionCollapse">
              <i class="fab fa-windows"></i>&nbsp;Install python node client on Windows
            </button>
          </h2>
          <div id="windowsInstructionCollapse" class="accordion-collapse collapse" aria-labelledby="windowsInstructionHeading" data-bs-parent="#nodeInstallInstructions">
            <div class="accordion-body">
              The installer is currently not working on Windows because the main target for this project is Linux. So the installer was firstly made for Linux.<br>
              If you want to help me to develop a Windows client, you are welcome! But no worries, it is in planning.
            </div>
          </div>
        </div>
      </div>
    </div>
  -->
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
                            <th>Authhash</th>
                            <th>Authtype</th>
                            <th>Connection Allowed</th>
                            <th>Hostname</th>
                            <th>Scriptversion</th>
                            <th>IP Address</th>
                            <th>Connections</th>
                            <th>Actions</th>
                          </tr>
                      </thead>
                      <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Authhash</th>
                            <th>Authtype</th>
                            <th>Connection Allowed</th>
                            <th>Hostname</th>
                            <th>Scriptversion</th>
                            <th>IP Address</th>
                            <th>Connections</th>
                            <th>Actions</th>
                          </tr>
                      </tfoot>
                      <tbody>
                      </tbody>
                  </table>
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
          <h6 class="m-0 font-weight-bold text-primary">Chia Node Information</h6>
      </div>
      <div id="all_node_sysinfo_container" class="card-body">
        <div class="input-group mb-3">
          <select id="chia-nodes-select" multiple="multiple">
          </select>
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
        <p>Do you really want to decline the connection for node <strong id="declinemodal-nodeid"></strong> with authhash <strong id="declinemodal-authhash"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button" id="decline-node" class="btn btn-danger">Decline connection</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="nodeactionmodal" data-nodeid="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Send Action</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="card shadow mb-4">
          <div
              class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
              <h6 class="m-0 font-weight-bold text-primary">Update Node <strong id="actionmodal-nodename"></strong></h6>
          </div>
          <div id="all_node_sysinfo_container" class="card-body">
            <div class="dropdown">
              <button class="btn btn-secondary dropdown-toggle" type="button" id="updatechannelsMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                Update Channel
              </button>
              <div id="updatechannels-modal" class="dropdown-menu" aria-labelledby="updatechannelsMenu">
              </div>
            </div>
            <hr/>
            <div class="dropdown">
              <button class="btn btn-secondary dropdown-toggle" type="button" id="updateversionMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" disabled>
                Available Versions
              </button>
              <div id="updateversions-modal" class="dropdown-menu" aria-labelledby="updateversionMenu">
              </div>
            </div>
            <hr />
            <p>Update Channel: <span id="selectedchannel">None</span>
            <br>Version: <span id="selectedversion">None</span>
            <br>Filename: <span id="versionfilename">None</span></p>
            <hr />
            <button class="btn btn-success" type="button" id="updatenode" disabled>Update Node</button>
          </div>
        </div>
        <div class="card shadow mb-4">
          <div
              class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
              <h6 class="m-0 font-weight-bold text-primary">Node Command Log</h6>
          </div>
          <div id="action_node_log" class="card-body">

          </div>
          <div class='card-footer'>
            Status: <span id='action_node_status'></span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/nodes/js/nodes.js"?>></script>
