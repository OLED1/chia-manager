<?php
  include("../standard_headers.php");
  echo "<script nonce={$ini["nonce_key"]}> var siteID = 12; </script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/frameworks/jquery-datetimepicker/build/jquery.datetimepicker.min.css"?>" rel="stylesheet">
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/chia_statistics/css/chia_statistics.css"?>" rel="stylesheet">

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Chia Statistics</h1>
</div>

<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        This page shows historical chia data based on values queried from this instance.<br>
        The netspace, blockheight and chia dollar data is powered by <a class="externallink"  target="_blank" href="https://xchscan.com/">xchscan.com</a>.
      </div>
    </div>
  </div>
</div>
<div id="walletcontainer">
<?php include("templates/cards.php"); ?>
</div>
