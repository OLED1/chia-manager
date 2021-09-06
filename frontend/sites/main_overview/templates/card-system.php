<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\System\System_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $system_api = new System_Api();
  $system_messages = $system_api->getSystemMessages();
?>
<div class="card mb-4">
  <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
      <h6 class="m-0 font-weight-bold text-primary">System and security messages</h6>
      <div class='dropdown no-arrow'>
        <a class='dropdown-toggle' href='#' role='button' id='overallMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
          <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
        </a>
        <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='overallMenu'>
          <div class='dropdown-header'>Actions:</div>
          <button id="refreshSystemInfo" class='dropdown-item wsbutton' href='#' onclick='refreshSystemInfo()'>Refresh</button>
        </div>
      </div>
  </div>
  <div class="card-body">
    <div class="row">
      <?php
        if($system_messages["data"]["count"] > 0){
      ?>
      <div class="col">
        <div class="row">
          <div class="col-xl-6 mb-4">
            <?php foreach($system_messages["data"]["found"] AS $type => $message){ ?>
              <div class="card bg-warning text-white shadow">
                  <div class="card-body">
                      <?php echo $message; ?>
                  </div>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
      <?php }else{ ?>
      <div class="col">
          <div class="card bg-success text-white shadow">
              <div class="card-body">
                  No messages where found. Everythings looking fine.
              </div>
          </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>
