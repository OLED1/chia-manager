<?php
  use React\Promise;
  use ChiaMgmt\Users\Users_Api;
  use ChiaMgmt\UserSettings\UserSettings_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\Second_Factor\Second_Factor_Api;
  include("../standard_headers.php");

  $users_api = new Users_Api();
  $user_settings_api = new UserSettings_Api();

  $data_promises = [
    Promise\resolve($users_api->getOwnUserData($_COOKIE["user_id"])),
    Promise\resolve($user_settings_api->getGuiMode($_COOKIE["user_id"])),
    Promise\resolve((new Exchangerates_Api())->getAllCurrencies()),
    Promise\resolve($user_settings_api->getUserDefaultCurrency($_COOKIE["user_id"])),
    Promise\resolve((new Second_Factor_Api())->getTOTPEnabled(["userID" => $_COOKIE["user_id"]])),
    Promise\resolve($users_api->getLoggedInDevices($_COOKIE["user_id"])),
    Promise\resolve($users_api->getBackupKey($_COOKIE["user_id"]))
  ];

  Promise\all($data_promises)->then(function($all_returned) use($ini){
    $userData = $all_returned[0]["data"];
    $gui_mode = $all_returned[1]["data"]["gui_mode"];
    $currencies = $all_returned[2];
    $defaultCurrency = $all_returned[3];
    $totp_enabled = $all_returned[4];
    $devices = $all_returned[5];
    $backupkey = $all_returned[6]["data"];

    echo "<script nonce={$ini["nonce_key"]}>
      var siteID = 5;
      var userid = '{$_COOKIE["user_id"]}';
      var sessid = '{$_COOKIE["PHPSESSID"]}';
      var userdata = " . json_encode($userData) . ";" .
    "</script>";
?>
<link href="<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/usersettings/css/usersettings.css"?>" rel="stylesheet">
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">User settings</h1>
</div>

<div class="row">
  <div class="col">
    <div class="row">
      <div class="col">
          <div class="card shadow mb-4">
              <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Reset Password</h6>
              </div>
              <div class="card-body">
                <form id="resetpw">
                  <div class="form-group has-success has-feedback">
                    <div class="form-label-group">
                      <input type="password" id="currPW" class="form-control pwinput" data-button-id="resetpwbtn" placeholder="Password" required="required">
                      <label for="currPW">Current Password</label>
                    </div>
                  </div>
                  <div class="form-group">
                    <div class="form-label-group">
                      <input type="password" id="newPW" class="form-control pwinput" data-button-id="resetpwbtn" placeholder="Password" required="required">
                      <label for="newPW">New Password</label>
                      <div class="invalid-feedback">Passwords does not match</div>
                    </div>
                  </div>
                  <div class="form-group">
                    <div class="form-label-group">
                      <input type="password" id="repeatnewPW" class="form-control pwinput" data-button-id="resetpwbtn" placeholder="Password" required="required">
                      <label for="repeatnewPW">Repeat New Password</label>
                      <div class="invalid-feedback">Passwords does not match</div>
                    </div>
                  </div>
                  <button id="resetpwbtn" class="btn btn-primary btn-block wsbutton" href="#" disabled>Reset Password</button>
                </form>
              </div>
          </div>
      </div>
      <div class="col">
          <div class="card shadow mb-4">
              <div
                  class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                  <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
              </div>
              <div class="card-body" id="personinfo">
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Vorname</span>
                  </div>
                  <input type="text" id="name" name="name" class="form-control personinput" value="<?php echo $userData['name']; ?>" required="required">
                </div>
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Nachname</span>
                  </div>
                  <input type="text" id="lastname" name="lastname" class="form-control personinput" value="<?php echo $userData['lastname']; ?>" required="required">
                </div>
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <span class="input-group-text">E-Mail</span>
                  </div>
                  <input type="email" id="email" name="email" class="form-control personinput" value="<?php echo $userData['email']; ?>" required="required">
                </div>
                <div class="input-group mb-3">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Username</span>
                  </div>
                  <input type="text" id="username" name="username" class="form-control personinput" value="<?php echo $userData['username']; ?>" required="required">
                </div>
                <button id="savepersdata" class="btn btn-primary btn-block wsbutton" href="#" disabled>Save</button>
              </div>
          </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Gui Color Scheme</h6>
          </div>
          <div class="card-body">
            <div class="form-group">
              <select class="form-control wsbutton" id="gui-color-scheme_select">
                <option value=1 <?php echo ($gui_mode == 1 ? "selected" : ""); ?>>Light</option>
                <option value=2 <?php echo ($gui_mode == 2 ? "selected" : ""); ?>>Dark</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Regional Settings</h6>
          </div>
          <div class="card-body" id="regionalsettings">
            <div class="form-group">
              <label for="currency_select">Currency</label>
              <select class="form-control wsbutton" id="currency_select">
                <?php
                  if($currencies["status"] == 0 && count($currencies["data"]) > 0){
                    if($defaultCurrency["status"] == 0) $defaultCurrency = $defaultCurrency["data"]["currency_code"];
                    else $defaultCurrency = "usd";

                    foreach($currencies["data"] AS $arrkey => $thiscurrency){
                      echo "<option value='{$thiscurrency["currency_code"]}' " . ($thiscurrency["currency_code"] == $defaultCurrency ? "selected" : "") . ">(" . strtoupper($thiscurrency["currency_code"]) . ") {$thiscurrency["currency_desc"]}</option>";
                    }
                  }else{
                    echo "<option value='usd'>(USD) United States dollar</option>";
                  }
                ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Security settings</h6>
          </div>
          <div class="card-body">
            <p>To ensure your account is as safe as possible you are able to enable a second login factor via a mobile app.<br>
            This random key will only be questioned when a new browser or device wants to login.</p>
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="enableTOTPmobile" <?php echo( $totp_enabled["status"] == 0 ? "checked" : ""); ?> >
              <label class="custom-control-label" for="enableTOTPmobile">Enable and enforce second factor (TOTP via mobile app)</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Backup Key</h6>
          </div>
          <div class="card-body" id="personinfo">
            <div class="input-group mb-3">
              <input type="text" id="backupkey" name="name" class="form-control personinput" value="<?php echo $backupkey; ?>" readonly>
            </div>
            <button id="generateNewBackupKey" class="btn btn-primary btn-block wsbutton" href="#">Generate New</button>
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
            <h6 class="m-0 font-weight-bold text-primary">Logged in devices</h6>
          </div>
          <div class="card-body" id="devices">
          <?php
            if(array_key_exists("data", $devices)){
          ?>
            <div class="table-responsive">
              <table class="table table-bordered" id="loggedInDevices" width="100%" cellspacing="0">
                <thead>
                  <tr>
                    <th>Logged in</th>
                    <th>Device Info</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                foreach ($devices["data"] as $key => $value) {
                  echo "<tr id='device_{$value["id"]}'>
                          <td>{$value["logindate"]}</td>
                          <td>{$value["deviceinfo"]}</td>
                          <td><button data-device-id='{$value["id"]}' class='logoutdevice btn btn-secondary btn-block wsbutton' href='#'>Logout</button></td>
                        </tr>";
                }
                ?>
                </tbody>
              </table>
          <?php }else{ ?>
              <div class='col'>
                <div class='card bg-warning text-white shadow'>
                  <div class='card-body'>An error occured while getting logged in devices.</div>
                </div>
              </div>
          <?php } ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="totp_enable_dialog" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fas fa-shield-alt"></span>&nbsp;Enable Totp via mobile app</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        <div class="row">
          <div class="col">
            <div class="card mb-4">
              <div class="card-body">
                <div class="row">
                  <div class="col">
                    Please scan the QR Code provided with your prefered TOTP Android or iPhone app.<br>
                    Download the Google Authenticator app here (you can use similar apps too):
                  </div>
                </div>
                <div class="row">
                  <div class="col">
                    <a class="externallink" target="_blank" href='https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2&hl=de_AT&gl=US&pcampaignid=pcampaignidMKT-Other-global-all-co-prtnr-py-PartBadge-Mar2515-1'>
                      <img id="playstore-linklogo" alt='Get it on Google Play' src='https://play.google.com/intl/en_us/badges/static/images/badges/en_badge_web_generic.png'/>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col">
            <div class="card mb-4">
              <div class="card-body">
                <img id="totpQRCode" src=''>
                <p id="totpSecret"></p>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col">
          <div class="card">
              <div class="card-body">
                <p>Please enter the shown authkey in the authenticator app:</p>
                <div class="input-group mb-3">
                  <input type="text" pattern="\d*" maxlength="6" id="totp_key_check" name="name" class="form-control personinput">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button id="checkAndSafeTOTP" type="button" class="btn btn-success" disabled>Check code & activate</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<div id="totp_disable_dialog" data-verified="false" class="modal" tabindex="-1" role="dialog" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><span class="fas fa-shield-alt"></span>&nbsp;Disable Totp via mobile app</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="taskslog">
        Do your really want to disable such an important account security feature?
      </div>
      <div class="modal-footer">
        <button id="disable_totp_btn" type="button" class="btn btn-danger" disabled>Yes, I am really sure! (<span id="totp_disable_timer"></span>)</button>
        <button type="button" class="btn btn-success" data-dismiss="modal">Abort</button>
      </div>
    </div>
  </div>
</div>

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/usersettings/js/usersettings.js"?>></script>
<?php }); ?>