<?php
  include("../standard_headers.php");
  use ChiaMgmt\System\System_Api;

  $system_api = new System_Api();
  $all_settings = $system_api->getAllSystemSettings()["data"];
  $mailsettings = $all_settings["mailing"];
  $security = $all_settings["security"];
  if(array_key_exists("TOTP", $security) && array_key_exists("value", $security["TOTP"])) $security = filter_var($security["TOTP"]["value"], FILTER_VALIDATE_BOOLEAN);
  else $security = false;

  $updates = $system_api->checkForUpdates(["update_data_db" => true]);

  if($updates["data"]["channel"] == "dev"){
    $updatechannelname = "Development";
  }else if($updates["data"]["channel"] == "staging"){
    $updatechannelname = "Staging";
  }else{
    $updatechannelname = "Stable";
  }

  echo "<script nonce={$ini["nonce_key"]}>
          var siteID = 3;
          var frontend = '{$ini["app_protocol"]}://{$ini["app_domain"]}{$ini["frontend_url"]}';
          var updatedata =  " . json_encode($updates["data"]) . ";
        </script>";
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
            <p>Get a brief overview about the websocket server status. You also can execute actions in case of malfunction.</p>
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
            <p>To ensure your server is as secure as possible you have the following options. Please be aware of some of this settings. For example: Enabling TOTP requires your mailsettings to be checked, tested and confirmed.</p>
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="enableTOTP" <?php echo( $security ? "checked" : ""); ?> >
              <label class="custom-control-label" for="enableTOTP">Enable and enforce TOTP via E-Mail</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Instance maintenance
          </div>
          <div class="card-body">
            <h4><span style="font-size: 1.3rem">Chia®</span> Manager <?php echo $updates["data"]["localversion"]; ?></h4>
            <?php if(array_key_exists("updateavail", $updates["data"]) && $updates["data"]["updateavail"]) { ?>
            <h5><span id="updateversionbadge" class="badge badge-warning">Your version is out of date. Version <?php echo $updates["data"]["remoteversion"]; ?> is available. Please update soon.</span></h5>
          <?php }else if(array_key_exists("updateavail", $updates["data"])){ ?>
            <h5><span id="updateversionbadge" class="badge badge-success">Your version is up to date.</span></h5>
          <?php }else{ ?>
            <h5><span id="updateversionbadge" class="badge badge-warning"><?php echo $updates["message"]; ?></span></h5>
          <?php }?>
            <h4>Update Channel</h4>
            <div class="row">
              <div class="col mb-4">
                <div class="dropdown">
                  <a class="btn btn-secondary dropdown-toggle" href="#" role="button" id="updateDropdownMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <?php echo $updatechannelname; ?>
                  </a>
                  <div class="dropdown-menu" aria-labelledby="updateDropdownMenu">
                    <button class="dropdown-item updatechannel wsbutton" data-branch="main" href="#">Stable</button>
                    <!--Staging will be enabled as soon as our first version 1.0.alpha will be release, because it will not show any valid versions availabel. The github repo for that isn't existing anyway...-->
                    <!--<button class="dropdown-item updatechannel wsbutton" data-branch="staging" href="#">Staging</button>-->
                    <?php if(array_key_exists("developer_mode", $ini) && $ini["developer_mode"]){ ?>
                      <button class="dropdown-item updatechannel wsbutton" data-branch="dev" href="#">Development</button>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <button type="button" class="btn btn-secondary wsbutton" id="check-for-updates">Check for updates<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
                <button type="button" class="btn btn-success wsbutton" id="open-release-notes">Show release notes and trigger update</button>
              </div>
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
            <p>If you want to be able to send e-mails via this instance like password resets or (alert) messages, you can set it up here.<br>
            Please note: Sendmail is currenty not working.</p>
            <form id="mailsetupform">
              <div class="row">
                <div class="col col col-sm-3 col-md-2 col-lg-2 col-xl-6">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Send Method</span>
                      <select class="form-control" name="sendmethod" id="sendmethod">
                        <option id="smtp" value="smtp" <?php if(array_key_exists("sendmethod", $mailsettings) && ($mailsettings["sendmethod"]["value"] == "smtp" || $mailsettings["sendmethod"]["value"] == NULL)) echo "selected"; ?>>SMTP</option>
                        <option id="sendmail" value="sendmail" <?php if(array_key_exists("sendmethod", $mailsettings) && $mailsettings["sendmethod"]["value"] == "sendmail") echo "selected"; ?>>Sendmail</option>
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
                        <option value="" <?php if(array_key_exists("security", $mailsettings) && $mailsettings["security"]["value"] == NULL) echo "selected"; ?>>None</option>
                        <option value="ssl_tls" <?php if(array_key_exists("security", $mailsettings) && $mailsettings["security"]["value"] == "ssl_tls") echo "selected"; ?>>SSL/TLS</option>
                        <option value="starttls" <?php if(array_key_exists("security", $mailsettings) && $mailsettings["security"]["value"] == "starttls") echo "selected"; ?>>STARTTLS</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col col-sm col-md col-lg col-xl sendmail" <?php if(!array_key_exists("sendmethod", $mailsettings) || (array_key_exists("sendmethod", $mailsettings) && $mailsettings["sendmethod"]["value"] != "sendmail") ) echo "style='display: none;'"; ?>>
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
                      <input id="fromuser" name="fromuser" type="text" class="form-control" aria-label="user" placeholder="user" value="<?php if(array_key_exists("fromuser", $mailsettings)) echo $mailsettings["fromuser"]["value"]; ?>">
                      <span class="input-group-text">@</span>
                      <input id="domain" name="domain" type="text" class="form-control" aria-label="example.com" placeholder="example.com" value="<?php if(array_key_exists("domain", $mailsettings)) echo $mailsettings["domain"]["value"]; ?>">
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
                        <option value="" <?php if(!array_key_exists("authmethod", $mailsettings) || $mailsettings["authmethod"]["value"] == NULL) echo "selected"; ?>>None</option>
                        <option value="login" <?php if(array_key_exists("authmethod", $mailsettings) && $mailsettings["authmethod"]["value"] == "login") echo "selected"; ?>>Login</option>
                        <option value="plain" <?php if(array_key_exists("authmethod", $mailsettings) && $mailsettings["authmethod"]["value"] == "plain") echo "selected"; ?>>Plain</option>
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
                      <input id="mailserverdomain" name="mailserverdomain" type="text" class="form-control" aria-label="mail.example.com" placeholder="mail.example.com" value="<?php if(array_key_exists("mailserverdomain", $mailsettings)) echo $mailsettings["mailserverdomain"]["value"]; ?>">
                      <span class="input-group-text">:</span>
                      <input id="mailserverport" name="mailserverport" type="number" class="form-control" aria-label="465"  placeholder="465" value="<?php if(array_key_exists("mailserverport", $mailsettings)) echo $mailsettings["mailserverport"]["value"]; ?>">
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col smtp">
                  <div class="input-group mb-3">
                    <div class="input-group-prepend">
                      <span class="input-group-text">Credentials</span>
                      <input id="loginname" name="loginname" type="text" class="form-control" aria-label="user" placeholder="username" value="<?php if(array_key_exists("loginname", $mailsettings)) echo $mailsettings["loginname"]["value"]; ?>">
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
                <?php if(count($mailsettings) == 11){ ?>
                  <div class="card <?php echo ($mailsettings["confirmed"] ? "bg-success" : "bg-warning"); ?> text-white shadow">
                      <div class="card-body">
                        Your setting are currently <?php echo ($mailsettings["confirmed"] ? "confirmed" : "not confirmed"); ?>.
                        <br>
                        <?php if(!$mailsettings["confirmed"]) echo "<button type='button' class='btn btn-secondary setting-confirm wsbutton' data-settingtype='mailing'>Confirm</button>"; ?>
                      </div>
                  </div>
                <?php } ?>
              </div>
            </div>
            <div class="row">
              <div class="col smtp">
                <button type="button" class="btn btn-primary wsbutton" id="save-mail-settings">Save settings<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
                <button type="button" class="btn btn-success wsbutton" id="send-testmail" <?php if(array_key_exists("sendmethod", $mailsettings) && $mailsettings["sendmethod"]["value"] == NULL) echo "disabled"; ?>>Send Testmail</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Automated background tasks</h6>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col mb-4">
                <?php
                  $cronjobEnabled = $system_api->getCronjobEnabled();
                  if($cronjobEnabled["status"] == 0){
                    $now = new \DateTime("now");
                    $lastexecdate = new \DateTime($cronjobEnabled["data"]);
                    $interval = $now->diff($lastexecdate);
                    $seconds = $interval->s;
                  }
                ?>
                <p>The automated background tasks are needed to query current data in the background.<br>
                Therefore the system's socalled cronjob for the apache user will be used.<br>
                The job will executed every minute. To enable it, just hit the checkbox.</p>
                <?php if($cronjobEnabled["status"] == "012009001"){ ?>
                  <h5><span id="cronjobbadge" class="badge badge-danger">Cronjob not enabled.</span></h5>
                <?php }else if($cronjobEnabled["status"] == 0 && $seconds < 60){ ?>
                  <h5><span id="cronjobbadge" class="badge badge-success">Last Cronjob run <span id="lastcronrun"><?php echo $seconds; ?></span> seconds ago.</span></h5>
                <?php }else if($cronjobEnabled["status"] == 0 && $seconds >= 60){ ?>
                  <h5><span id="cronjobbadge" class="badge badge-danger">Last Cronjob run more than 1 minutes ago.</span></h5>
                <?php } ?>
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="enableSystemCronjob" <?php echo( $cronjobEnabled["status"] == 0 ? "checked" : ""); ?> >
                  <label class="custom-control-label" for="enableSystemCronjob">Enable automated background tasks</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php if(array_key_exists("developer_mode", $ini) && $ini["developer_mode"]){ ?>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Developer Settings</h6>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col mb-4">
                <p>Change newest project version, update dbversion and set default values for the db_update.json.</p>
                <label for="new-project-version">New project version (e.g. x.y.z.[YYMMDD] or x.y.z)</label>
                <input id="new-project-version" class="form-control" type="text" placeholder="0.1.alpha.1">
              </div>
            </div>
            <div class="row">
              <div class="col">
                <button type="button" class="btn btn-primary wsbutton" id="save-new-project-version">Save changes<i class="fas fa-spinner fa-spin" style="display: none;"></i></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php } ?>
  </div>
</div>
<div id="updater_release_notes" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fas fa-paper-plane"></span>&nbsp;Release Notes (Version:&nbsp;<span id="release-version"></span>)</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <p><strong>Updatechannel:</strong>&nbsp<span id="updatechannel"></span><br>
        <strong>Versionnote:</strong>&nbsp;<span id="updatefromto"></span>
        <strong>Releasenotes:</strong><br><span id="releasenotes"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="start-update">Start update</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="send_testmail_dialog" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fas fa-paper-plane"></span>&nbsp;Send Testmail</h5>
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

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/system/js/system.js"?>></script>
