<?php
  use React\Promise;
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  require __DIR__ . '/../../../../../vendor/autoload.php';

  if(!array_key_exists("sess_id", $_GET) || !array_key_exists("user_id", $_GET) || !array_key_exists("Mail", $_GET)){
    echo "Incomplete Request.";
    die();
  }

  $site_data_to_load = [
    Promise\resolve((new Login_Api())->checklogin($_GET["sess_id"], $_GET["user_id"])),
    Promise\resolve((new System_Api())->getSpecificSystemSetting("mailing"))
  ];

  $ini = parse_ini_file(__DIR__.'/../../../../../backend/config/config.ini.php');
  Promise\all($site_data_to_load)->then(function($all_returned) use($ini){
    if($all_returned[0]["status"] > 0){
      echo "NOT AUTHENTICATED.";
      exit();
    }

    $mailing = $all_returned[1]["data"];
    $mailing_confirmed = $mailing["mailing"]["confirmed"];
    $mail_parameters = json_decode($_GET["Mail"], 1);
    $service_enabled = $mail_parameters["enabled"];
  
    echo "<script nonce={$ini["nonce_key"]}>
      var mail_service_id = {$mail_parameters["id"]};
      var service_id_hr = '{$mail_parameters["service_id"]}';
      var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
    </script>";
?>
<div class="row">
  <div class="col">
    <div class="card shadow" style="margin: 1em;">
      <div class="card-body">
        <h5>Setup</h5>
        System mailing enabled: <span class="badge <?php echo ($mailing_confirmed ? "badge-success" : "badge-danger"); ?>"><?php echo ($mailing_confirmed ? "Enabled and confirmed" : "Not enabled. Please enable and configure on the settings page.") ?></span><br>
        Enable alerting service: <input id="enable_mailing" class="wsbutton" data-service-id="<?php echo $_GET["Mail"]["id"]; ?>" type="checkbox" aria-label="Checkbox for following text input" <?php echo ($mailing_confirmed ? "disabled" : ""); echo ($service_enabled == 1 ? " checked" : ""); ?>>
      </div>
    </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/alerting/js/alerting_services/mail.js"?>></script>
<?php }); ?>