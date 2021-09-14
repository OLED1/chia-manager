$(function(){
  initLoggedInDevicesTable();

  $("#resetpwbtn").on("click",function(e){
    e.preventDefault();
    if(checkFieldsNotEmpty("resetpw") && checkNewPassword()){
      checkAndResetCurrentPassword();
    }
  });

  $(".pwinput").on("input",function(){
    var buttonid = $(this).attr("data-button-id");
    if(checkFieldsNotEmpty("resetpw")){
      enableButton(buttonid);
    }else{
      disableButton(buttonid);
    }
  });

  $(".personinput").on("input",function(){
    if(persDataChanged() && checkFieldsNotEmpty("personinfo")){
      enableButton("savepersdata");
    }else{
      disableButton("savepersdata");
    }
  });

  $("#generateNewBackupKey").on("click", function(){
    var data = {
      userID: userid
    };
    sendToWSS("ownRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "generateNewBackupKey", data);
  });

  $(".logoutdevice").on("click", function(){
    var data = {
      userid: userid,
      deviceid : $(this).attr("data-device-id")
    }

    sendToWSS("ownRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "logoutDevice", data);
  });

  $("#currency_select").on("change", function(){
    var data = {
      userid: userid,
      currency_code : $(this).val()
    }

    sendToWSS("ownRequest", "ChiaMgmt\\UserSettings\\UserSettings_Api", "UserSettings_Api", "setUserDefaultCurrency", data);
  });

  $("#gui-color-scheme_select").on("change", function(){
    var data = {
      userid: userid,
      gui_mode : $(this).val()
    }

    sendToWSS("ownRequest", "ChiaMgmt\\UserSettings\\UserSettings_Api", "UserSettings_Api", "setGuiMode", data);
  });

  function initLoggedInDevicesTable(){
    $("#loggedInDevices").DataTable();
  }

  function checkNewPassword(){
    var regex = "^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})";
    if($("#newPW").val() == $("#repeatnewPW").val() && $("#newPW").val().match(regex)){
      return true;
    }else{
      if($("#newPW").val() != $("#repeatnewPW").val()){
        showMessage(2, "New passwords does not match.");
      }
      if(!$("#newPW").val().match(regex)){
        showMessage(2, "New password not strong enough (Min. length: 9, A-Z,a-z,1-9,[special signs]).");
      }
      return false;
    }
  }

  function persDataChanged(){
    var changed = false;
    $.each($("#personinfo input"),function(){
      if($(this).val().trim() != userdata[$(this).attr("id")]){
        changed = true;
      }
    });

    return changed;
  }

  function checkFieldsNotEmpty(rootelem){
     var empty = false;
     $.each($("#"+rootelem+" input"),function(){
       if($(this).val().trim().length == 0){
         empty = true;
       }
     });
     return !empty;
  }

  function enableButton(buttonid){
    $("#"+buttonid).removeAttr("disabled");
  }

  function disableButton(buttonid){
    $("#"+buttonid).attr("disabled",true);
  }

  function checkEMail(mailstring){
    var regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/;
    if(mailstring.match(regex)){
      return true;
    }else{
      showMessage(2, "This E-Mail (" + mailstring + ") address is not valid.");
      return false;
    }
  }

  function checkAndResetCurrentPassword(){
    var currentPassword = $("#currPW").val();

    if(currentPassword.trim() != ""){
      var data = {
        userID: userid,
        password: currentPassword
      };

      sendToWSS("ownRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "checkCurrentPassword", data);
    }
  }

  $("#savepersdata").on("click",function(e){
    e.preventDefault();

    if(persDataChanged() && checkFieldsNotEmpty("personinfo") && checkEMail($("#email").val())){
      var data = {};
      $.each($("#personinfo input"),function(){
        var elem = $(this);
        data[elem.attr("id")] = elem.val();
      });

      data["userID"] = userid;
      sendToWSS("ownRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "savePersonalInfo", data);
    }
  });
});

function resetUserPassword(){
  var newPW = $("#newPW").val();
  var data = {
    userID: userid,
    password: newPW
  };

  sendToWSS("ownRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "resetUserPassword", data);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if (key == "savePersonalInfo"){
      $("#sitewrapperusername").text(data[key]["data"]["name"] + " " + data[key]["data"]["lastname"]);
      userdata = data[key]["data"];
    }else if(key == "generateNewBackupKey"){
      $("#backupkey").val(data[key]["data"]);
    }else if(key == "checkCurrentPassword"){
      resetUserPassword();
    }else if(key == "resetUserPassword"){
      $("#resetpw").find("input").val("");
    }else if(key == "logoutDevice"){
      $("#loggedInDevices").DataTable()
        .row($("#device_" + data[key]["data"]["deviceid"]))
        .remove()
        .draw();
    }else if(key == "setGuiMode"){
      darkmode = data[key]["data"];
      if(data[key]["data"] == 1){
        $(".gui-mode-elem").removeClass("gui-mode-dark").addClass("gui-mode-light");
      }else if(data[key]["data"] == 2){
        $(".gui-mode-elem").removeClass("gui-mode-light").addClass("gui-mode-dark");
      }
    }
  }
}
