<?php
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  require __DIR__ . '/../../../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $system_api = new System_Api();
  $mailing = $system_api->getSpecificSystemSetting("mailing")["data"];
  $mailing_confirmed = $mailing["mailing"]["confirmed"];
  $service_enabled = $_GET["mail"]["enabled"];

  echo "<script nonce={$ini["nonce_key"]}>
    var mail_service_id = {$_GET["mail"]["id"]};
    var service_id_hr = '{$_GET["mail"]["service_id"]}';
    var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
  </script>";
?>
<div class="row">
  <div class="col">
    <div class="card shadow" style="margin: 1em;">
      <div class="card-body">
        <h5>Setup</h5>
        System mailing enabled: <span class="badge <?php echo ($mailing_confirmed ? "badge-success" : "badge-danger"); ?>"><?php echo ($mailing_confirmed ? "Enabled and confirmed" : "Not enabled. Please setup on the settings page.") ?></span><br>
        Enable alerting service: <input id="enable_mailing" class="wsbutton" data-service-id="<?php echo $_GET["mail"]["id"]; ?>" type="checkbox" aria-label="Checkbox for following text input" <?php echo ($mailing_confirmed ? "disabled" : ""); echo ($service_enabled == 1 ? " checked" : ""); ?>>
      </div>
    </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/alerting/js/alerting_services/mail.js"?>></script>