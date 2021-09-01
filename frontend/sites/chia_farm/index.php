<?php
  include("../standard_headers.php");
  echo "<script> var siteID = 6; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Farm</h1>
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
<h4>My Farm</h4>
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button id="queryAllNodes" type="button" class="btn btn-secondary">Query farm information from all nodes</button>
      </div>
    </div>
  </div>
</div>
<h5>Overview</h5>
<div id="farminfocards">
<?php include("templates/cards.php"); ?>
</div>
<!-- <h5>Last Attempted Proof</h5>
<h5>Last Block Challenges</h5>-->
