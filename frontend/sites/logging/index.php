<?php
  include("../standard_headers.php");
  echo "<script nonce={$ini["nonce_key"]}> var siteID = 11; </script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/logging/css/logging.css"?>" rel="stylesheet">
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Logging</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see what's happening in the backend of the Chia Mgmt tool and it's backend services.<br>
        It includes all logs regarding the websocket service, the api services and the automated task (aka cronjob) service.
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
        <div
            class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">System Logs</h6>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-bordered" id="loggingTable" width="100%" cellspacing="0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Loglevel</th>
                  <th>Status Code</th>
                  <th>Message</th>
                </tr>
              </thead>
              <tbody>
              </tbody>
              <tfoot>
                <tr>
                  <th>Date</th>
                  <th>Loglevel</th>
                  <th>Status Code</th>
                  <th>Message</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/logging/js/logging.js"?>></script>
