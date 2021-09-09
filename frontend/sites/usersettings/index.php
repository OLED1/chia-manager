<?php
  include("../standard_headers.php");

  use ChiaMgmt\Users\Users_Api;
  use ChiaMgmt\UserSettings\UserSettings_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;

  $users_api = new Users_Api();
  $user_settings_api = new UserSettings_Api();
  $exchangerates_api = new Exchangerates_Api();

  $userData = array();
  if(array_key_exists("user_id", $_COOKIE)) $userData = $users_api->getOwnUserData($_COOKIE["user_id"]);
  if(array_key_exists("data", $userData)) $userData = $userData["data"];

  echo "<script nonce={$ini["nonce_key"]}>
              var userid = '" . $_COOKIE["user_id"] . "';
              var sessid = '" . $_COOKIE["PHPSESSID"] . "';
              var userdata = " . json_encode($userData) . ";" .
     "</script>";
     echo "<script nonce={$ini["nonce_key"]}> var siteID = 5; </script>";
?>
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
            <?php $gui_mode = $user_settings_api->getGuiMode($_COOKIE["user_id"])["data"]["gui_mode"]; ?>
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
                  $currencies = $exchangerates_api->getAllCurrencies();
                  if($currencies["status"] == 0 && count($currencies["data"]) > 0){
                    $defaultCurrency = $user_settings_api->getUserDefaultCurrency($_COOKIE["user_id"]);
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
            <h6 class="m-0 font-weight-bold text-primary">Backup Key</h6>
          </div>
          <div class="card-body" id="personinfo">
            <div class="input-group mb-3">
              <input type="text" id="backupkey" name="name" class="form-control personinput" value="<?php echo $users_api->getBackupKey($_COOKIE["user_id"])["data"]; ?>" readonly>
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
            $devices = $users_api->getLoggedInDevices($_COOKIE["user_id"]);
            if(array_key_exists("data", $devices)){
          ?>
            <div class="table-responsive">
              <table class="table table-bordered" id="loggedInDevices" width="100%" cellspacing="0">
                <thead>
                  <tr>
                    <th>Device Info</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php
                foreach ($devices["data"] as $key => $value) {
                  echo "<tr id='device_{$value["id"]}'>
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

<script nonce=<?php echo $ini["nonce_key"]; ?> src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/usersettings/js/usersettings.js"?>></script>
