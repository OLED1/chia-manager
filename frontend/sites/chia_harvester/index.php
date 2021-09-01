<?php
  include("../standard_headers.php");
  echo "<script> var siteID = 7; </script>";
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
  <h1 class="h3 mb-0 text-gray-800">Chia Harvester</h1>
</div>
<div class="row">
  <div class="col">
    <h4>Explanation</h4>
    <div class="card shadow mb-4">
      <div class="card-body">
        On this page you see an overview about your plots and information about it.<br>
        If this project will pass all required security checks we will implement plotting options too.
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="alert alert-warning" role="alert">
      Please make sure every external connected harddrive has its own mountpoint.<br>
      E.g. /dev/sdb1 -> /mnt/final1, /dev/sdc -> /mnt/final2<br>
      Otherwise the client will only query one of two or more directories.
    </div>
  </div>
</div>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query harvester information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h4>My Plots</h4>
<div id="harvesterinfocards">
<?php include("templates/cards.php"); ?>
</div>
