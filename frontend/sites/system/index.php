<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $system_api = new System_Api();

  $all_settings = $system_api->getAllSystemSettings()["data"];
  $mailsettings = $all_settings["mailing"];
  $security = $all_settings["security"];
  if(array_key_exists("TOTP", $security) && array_key_exists("value", $security["TOTP"])) $security = filter_var($security["TOTP"]["value"], FILTER_VALIDATE_BOOLEAN);
  else $security = false;

  echo "<script> var siteID = 3; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
</div>

<div class="row">
  <div class="col">
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Websocket Server</h6>
          </div>
          <div class="card-body">
            <p>Get a brief overview about the websocket server status. You also can execute actions in case of malfunction.<p>
            <h6>Server Status</h6>
            <div id="wssstatus" class="col-lg-6 mb-4">
            <?php
              $connection = $system_api->testConnection();
              if($connection["status"] == 0){
                echo "<div class='card bg-success text-white shadow'>
                          <div class='card-body'>
                              Status: Running (PID: {$connection["data"]})
                              <div class='text-white-50 small'>All websocket services are good</div>
                          </div>
                      </div>";
              }else{
                echo "<div class='card bg-danger text-white shadow'>
                          <div class='card-body'>
                              Status: Not Running
                              <div class='text-white-50 small'>Live data transmition not possible</div>
                          </div>
                      </div>";
              }
            ?>
            </div>
            <h6>Server Actions</h6>
            <button id="startWSS" href="#" class="btn btn-success btn-icon-split btn-lg" <?php echo ($connection["status"] == 0 ? "disabled" : ""); ?>>
              <span class="icon text-white-50">
                <i class="fas fa-play"></i>
              </span>
              <span class="text">Start</span>
            </button>
            <button id="stopWSS" href="#" class="btn btn-danger btn-icon-split btn-lg" <?php echo ($connection["status"] > 0 ? "disabled" : ""); ?>>
              <span class="icon text-white-50">
                <i class="fas fa-stop"></i>
              </span>
              <span class="text">Stop</span>
            </button>
            <button id="restartWSS" href="#" class="btn btn-warning btn-icon-split btn-lg" <?php echo ($connection["status"] > 0 ? "disabled" : ""); ?>>
              <span class="icon text-white-50">
                <i class="fas fa-retweet"></i>
              </span>
              <span class="text">Restart</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Server Security</h6>
          </div>
          <div class="card-body">
            <p>To ensure your server is as secure as possible you have the following options. Please be aware of some of this settings. For example: Enabling TOTP requires your mailsettings to be checked, tested and confirmed.<p>
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="enableTOTP" <?php echo( $security ? "checked" : ""); ?> >
                <label class="custom-control-label" for="enableTOTP">Enable and enforce TOTP via E-Mail</label>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col">
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">E-Mail Server Settings</h6>
          </div>
          <div class="card-body">
            <p>If you want to be able to send e-mails via this instance like password resets or (alert) messages, you can set it up here.<p>
            <form id="mailsetupform">
              <div class="row">
                <div class="col col col-sm-3 col-md-2 col-lg-2 col-xl-6">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Send Method</span>
                      <select class="form-control" name="sendmethod" id="sendmethod">
                        <option id="smtp" value="smtp" <?php if($mailsettings["sendmethod"]["value"] == "smtp" || $mailsettings["sendmethod"]["value"] == NULL) echo "selected"; ?>>SMTP</option>
                        <option id="sendmail" value="sendmail" <?php if($mailsettings["sendmethod"]["value"] == "sendmail") echo "selected"; ?>>Sendmail</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col col-sm col-md col-lg col-xl smtp">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Security</span>
                      <select class="form-control" name="security" id="security">
                        <option value="" <?php if($mailsettings["security"]["value"] == NULL) echo "selected"; ?>>None</option>
                        <option value="ssl_tls" <?php if($mailsettings["security"]["value"] == "ssl_tls") echo "selected"; ?>>SSL/TLS</option>
                        <option value="starttls" <?php if($mailsettings["security"]["value"] == "starttls") echo "selected"; ?>>STARTTLS</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col col-sm col-md col-lg col-xl sendmail" <?php if($mailsettings["sendmethod"]["value"] != "sendmail") echo "style='display: none;'"; ?>>
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Sendmail mode</span>
                      <select class="form-control" name="sendmailmode" id="sendmailmode">
                        <option selected>None</option>
                        <option value="smtp-bs">smtp (-bs)</option>
                        <option value="pipe-t">pipe (-t)</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp sendmail">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">From address</span>
                      <input id="fromuser" name="fromuser" type="text" class="form-control" aria-label="user" placeholder="user" value="<?php echo $mailsettings["fromuser"]["value"]; ?>">
                      <span class="input-group-text">@</span>
                      <input id="domain" name="domain" type="text" class="form-control" aria-label="example.com" placeholder="example.com" value="<?php echo $mailsettings["domain"]["value"]; ?>">
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Auth Method</span>
                      <select class="form-control" name="authmethod" id="authmethod">
                        <option value="" <?php if($mailsettings["authmethod"]["value"] == NULL) echo "selected"; ?>>None</option>
                        <option value="login" <?php if($mailsettings["authmethod"]["value"] == "login") echo "selected"; ?>>Login</option>
                        <option value="plain" <?php if($mailsettings["authmethod"]["value"] == "plain") echo "selected"; ?>>Plain</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Server address</span>
                      <input id="mailserverdomain" name="mailserverdomain" type="text" class="form-control" aria-label="mail.example.com" placeholder="mail.example.com" value="<?php echo $mailsettings["mailserverdomain"]["value"]; ?>">
                      <span class="input-group-text">:</span>
                      <input id="mailserverport" name="mailserverport" type="number" class="form-control" aria-label="465"  placeholder="465" value="<?php echo $mailsettings["mailserverport"]["value"]; ?>">
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Credentials</span>
                      <input id="loginname" name="loginname" type="text" class="form-control" aria-label="user" placeholder="username" value="<?php echo $mailsettings["loginname"]["value"]; ?>">
                      <span class="input-group-text">//</span>
                      <input id="loginpassword" name="loginpassword" type="password" class="form-control" aria-label="example.com" placeholder="password" value="*******">
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp">
                  <div class="alert alert-danger" role="alert" id="mailsetuperror" style="display: none;"></div>
                </div>
              </div>
            </form>
            <div class="row">
              <div id="settingtype_mailing" class="col mb-4">
                <?php if(!is_null($mailsettings) && count($mailsettings) > 0){ ?>
                  <div class="card <?php echo ($mailsettings["confirmed"] ? "bg-success" : "bg-warning"); ?> text-white shadow">
                      <div class="card-body">
                        Your setting are currently <?php echo ($mailsettings["confirmed"] ? "confirmed" : "not confirmed"); ?>.
                        <br>
                        <?php if(!$mailsettings["confirmed"]) echo "<button type='button' class='btn btn-secondary setting-confirm' data-settingtype='mailing'>Confirm</button>"; ?>
                      </div>
                  </div>
                <?php } ?>
              </div>
            </div>
            <div class="row">
              <div class="col smtp">
                <button type="button" class="btn btn-primary" id="save-mail-settings">Save settings<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
                <button type="button" class="btn btn-success" id="send-testmail" <?php if($mailsettings["sendmethod"]["value"] == NULL) echo "disabled"; ?>>Send Testmail</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="send_testmail_dialog" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fas fa-exclamation-triangle"></span>Module verify log</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <p>Send Testmail to</p>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="gridRadios" id="this-mail" value="this-mail" checked>
          <label class="form-check-label" for="this-mail">your accounts setup mail address (<strong id="this-account-mail"></strong>)</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="gridRadios" id="custom-mail" value="custom-mail">
          <label class="form-check-label" for="custom-mail">custom mail address</label>
        </div>
        <div class="form-group row">
          <div class="col-sm-10">
            <input type="email" class="form-control" id="custom-mail-address" placeholder="Your mail address" style="display:none;">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="confirm-testmail-option">Send Testmail<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/system/js/system.js"?>></script>
