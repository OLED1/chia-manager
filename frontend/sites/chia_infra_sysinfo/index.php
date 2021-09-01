<?php
  include("../standard_headers.php");
  echo "<script> var siteID = 8; </script>";
  echo "<script> var frontend_url = '" . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"] . "'; </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0 text-gray-800">Chia Infra Sysinfo</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your farm and information about it.
      </div>
    </div>
  </div>
</div>
<h4>Chia Infrastructure System Information</h4>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query system information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<div id="all_node_sysinfo_container">
<?php include("templates/cards.php"); ?>
</div>
