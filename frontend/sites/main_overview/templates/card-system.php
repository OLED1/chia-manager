<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\System\System_Api;
  require __DIR__ . '/../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || ! array_key_exists("user_id", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $check_login = React\Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"]));
  $system_messages = React\Promise\resolve((new System_Api())->getSystemMessages(["userID" => $_GET["user_id"]]));

  $ini = parse_ini_file(__DIR__.'/../../../../backend/config/config.ini.php');

  return React\Promise\all([$check_login, $system_messages])->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $system_messages = $all_returned[1];
?>
<div class="row">
  <div id="card-system" class="col">
    <div class="card mb-4">
      <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">System and security messages</h6>
        <div class='dropdown no-arrow'>
          <a class='dropdown-toggle' href='#' role='button' id='overallMenu' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
            <i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>
          </a>
          <div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='overallMenu'>
            <div class='dropdown-header'>Actions:</div>
            <button id="refreshSystemInfo" class='dropdown-item wsbutton' href=''>Refresh</button>
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
              <div class="col">
                <?php foreach($system_messages["data"]["found"] AS $type => $message){ ?>
                  <div class="card bg-warning text-white shadow mb-2">
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
                      No messages found. Everything is looking fine.
                  </div>
              </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php }); ?>