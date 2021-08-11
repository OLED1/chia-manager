<?php
  session_start();

  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\Users\Users_Api;
  require __DIR__ . '/../../../vendor/autoload.php';

  $login_api = new Login_Api();
  $ini = parse_ini_file(__DIR__.'/../../../backend/config/config.ini.php');
  $loggedin = $login_api->checklogin();

  if($loggedin["status"] > 0){
    header("Location: " . $ini["app_protocol"]."://".$ini["app_domain"].$ini["frontend_url"]."/login.php");
  }

  $users_api = new Users_Api();
  $users = $users_api->getUserData()["data"];

  echo "<script> var userData = " . json_encode($users) . "; </script>";
  echo "<script> var userID = " . $_COOKIE["user_id"] . "; </script>";
  echo "<script> var siteID = 4; </script>";
?>
<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Users</h1>
</div>
<div class="row">
  <div class="col">
    <h5>Explanation</h5>
    <div class="card shadow mb-4">
      <div class="card-body">
        Create, edit and disable users which have access to the management paltform.
      </div>
    </div>
  </div>
</div>
<!-- Content Row -->
<div class="row">
  <div class="col">
    <div class="card shadow mb-4">
      <div class="card-body">
        <button type="button" id="addUser" class="btn btn-success">Add User</button>
      </div>
    </div>
  </div>
</div>

<div class="row">
    <div class="col">
        <div class="card shadow mb-4">
            <div
                class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Enabled Users</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                  <table class="table table-bordered" id="usrDataTable" width="100%" cellspacing="0">
                      <thead>
                          <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>email</th>
                            <th>Actions</th>
                          </tr>
                      </thead>
                      <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>email</th>
                            <th>Actions</th>
                          </tr>
                      </tfoot>
                      <tbody>
                      </tbody>
                  </table>
              </div>
            </div>
        </div>
    </div>
  </div>
  <div class="row">
    <div class="col">
        <div class="card shadow mb-4">
            <div
                class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Disabled Users</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                  <table class="table table-bordered" id="disabledUsrDataTable" width="100%" cellspacing="0">
                      <thead>
                          <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>email</th>
                            <th>Actions</th>
                          </tr>
                      </thead>
                      <tfoot>
                          <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>email</th>
                            <th>Actions</th>
                          </tr>
                      </tfoot>
                      <tbody>
                      </tbody>
                  </table>
              </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userAddEditModal" data-mode="" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true" data-keyboard="false" data-backdrop="static">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEditModalTitle">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col">
              <div class="card shadow mb-4">
                  <div class="card-body" id="personinfo">
                    <div class="input-group mb-3">
                      <div class="input-group-prepend">
                        <span class="input-group-text">User ID</span>
                      </div>
                      <input type="text" id="id" name="id" class="form-control personinput" value="" readonly>
                    </div>
                    <div class="input-group mb-3">
                      <div class="input-group-prepend">
                        <span class="input-group-text">Vorname</span>
                      </div>
                      <input type="text" id="name" name="name" class="form-control personinput" value="" required="required">
                    </div>
                    <div class="input-group mb-3">
                      <div class="input-group-prepend">
                        <span class="input-group-text">Nachname</span>
                      </div>
                      <input type="text" id="lastname" name="lastname" class="form-control personinput" value="" required="required">
                    </div>
                    <div class="input-group mb-3">
                      <div class="input-group-prepend">
                        <span class="input-group-text">E-Mail</span>
                      </div>
                      <input type="email" id="email" name="email" class="form-control personinput" value="" required="required">
                    </div>
                    <div class="input-group mb-3">
                      <div class="input-group-prepend">
                        <span class="input-group-text">Username</span>
                      </div>
                      <input type="text" id="username" name="username" class="form-control personinput" value="" required="required">
                    </div>
                  </div>
              </div>
          </div>
        </div>
        <div class="row">
            <div class="col">
              <div class="card shadow mb-4">
                <div class="card-body">
                  <div class="form-group">
                    <div class="form-label-group">
                      <input type="password" id="newPW" class="form-control pwinput" data-button-id="resetpwbtn" placeholder="New Password" required="required">
                      <p id="passwordhint" style="color: orange;"></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="saveusrchanges" class="btn btn-success">Save changes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src=<?php echo $ini["app_protocol"]."://".$ini["app_domain"]."".$ini["frontend_url"]."/sites/users/js/users.js"?>></script>
