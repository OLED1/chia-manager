$(function(){
  var action = "login";
  var type = "POST";
  var url = backend + "/core/WebSocket/WebSocket_Rest.php";

  settingConfirmHandler();
  reloadCronJobExecTimer();

  $("#sendmail").on("click",function(){
    $(".smtp").hide();
    showErrorMessage("mailsetuperror","This option is currently not implemented.");
  });

  $("#smtp").on("click",function(){
    $(".sendmail").hide();
    $(".smtp").show();
  });

  $("#this-mail").on("click",function(){
    $("#custom-mail-address").hide();
  });

  $("#custom-mail").on("click",function(){
    $("#custom-mail-address").show();
  });

  $("#send-testmail").on("click", function(){
    $("#send_testmail_dialog").modal("show");
    useremail = userdata["email"];
    $("#this-account-mail").text(useremail);
  });

  $("#confirm-testmail-option").on("click",function(){
    var data = {};
    if($("#this-mail").is(":checked")){
      data["receipients"] = [useremail];
    }else{
      var targetmail = $("#custom-mail-address").val();
      if(targetmail.trim().length > 0){
        data["receipients"] = [targetmail];
      }else{
        showMessage(2, "The custom mail address field is not allowed to be empty.");
      }
    }

    $("#confirm-testmail-option i").show();
    sendToWSS("ownRequest", "ChiaMgmt\\Mailing\\Mailing_Api", "Mailing_Api", "sendTestMail", data);
  });

  $("#startWSS").on("click", function(){
    var data = {};
    sendToAPI("startWebsocket", data);
  });

  $("#stopWSS").on("click", function(){
    var data = {};
    sendToAPI("stopWebsocket", data);
  });

  $("#restartWSS").on("click", function(){
    var data = {};
    sendToAPI("restartWebsocket", data);
  });

  $("#save-mail-settings").on("click",function(){
    var data = {};
    var error = 0;
    $("#mailsetuperror").hide();

    $('#mailsetupform *').filter(':input:visible').each(function(){
      var currentelement = $(this);

      if(currentelement.val().trim().length > 0 && currentelement.val().trim() != "*******"){
        data[currentelement.attr("name")] = {};
        data[currentelement.attr("name")]["type"] = currentelement.attr("type");
        data[currentelement.attr("name")]["value"] = currentelement.val();
      }else{
        showErrorMessage("mailsetuperror","All fields must not empty (" + currentelement.attr("name") + ").");
        error = 1;
      }
    });
    data["settingtype"] = "mailing";
    if(error == 0){
      $("#save-mail-settings i").show();
      var datatosend = {};
      datatosend["mailing"] = data;

      $("#send-testmail").removeAttr("disabled");
      window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "setSystemSettings", datatosend);
    }
  });

  $("#enableTOTP").on("change", function(e){
    e.preventDefault();

    datatosend = {};
    datatosend["security"] = {};
    datatosend["security"]["TOTP"] = {};
    datatosend["security"]["TOTP"]["value"] = $(this).prop("checked") ? "1" : "0";

    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "setSystemSettings", datatosend);

    if($(this).prop("checked") == 1){
      setTimeout(function() {
        window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "confirmSetting", {"settingtype" : "security"});
      }, 500);
    }
  });

  $(".updatechannel").on("click", function(e){
    e.preventDefault();

    var branch = $(this).attr("data-branch");
    $("#updateDropdownMenu").text($(this).text());
    datatosend = {};
    datatosend["updatechannel"] = {};
    datatosend["updatechannel"]["branch"] = {};
    datatosend["updatechannel"]["branch"]["value"] = branch;

    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "setSystemSettings", datatosend);
    setTimeout(function() {
      window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "confirmSetting", {"settingtype" : "updatechannel"});
    }, 500);
  });

  $("#check-for-updates").on("click", function(e){
    e.preventDefault();
    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "checkForUpdates", {"update_data_db" : true });
  });

  $("#start-update").on("click", function(e){
    e.preventDefault();
    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "setInstanceUpdating", { "userid" : userID, "updatestate" : 1 });
  });

  $("#open-release-notes").on("click", function(e){
    $("#updater_release_notes").modal("show");
    $("#release-version").text(updatedata["remoteversion"]);
    $("#updatechannel").text(updatedata["channel"]);
    $("#releasenotes").html(marked.parse(updatedata["releasenotes"]));
    if(updatedata["updateavail"]){
      $("#updatefromto").html("Update from version <strong>" + updatedata["localversion"] + "</strong> to <strong>" + updatedata["remoteversion"] + "</strong><br>");
      $("#start-update").show();
    }else{
      $("#updatefromto").html("You are using the latest version <strong>" + updatedata["localversion"] + "</strong><br>");
      $("#start-update").hide();
    }
  });

  $("#confirm-update-process").on("click", function(e){
    e.preventDefault();
    $(".update-close-button").hide();
    $("#confirm-update-process").attr("disabled","disabled").find("i").show();
    $("#updatelogcontainer").children().hide("slow").remove();
    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "processUpdate", {});
  });

  $("#proceed-update-routine").on("click", function(e){
    location.reload();
  });

  $("#save-new-project-version").on("click", function(e){
    var newprojectversion = $("#new-project-version").val().trim();
    if(newprojectversion.length > 0){
      window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "updateProjectVersion", { "projectversion" : newprojectversion });
    }else{
      showMessage(1, "Project version must not empty.");
    }
  });

  $("#enableSystemCronjob").on("click", function(e){
    if($("#enableSystemCronjob").prop('checked')){
      window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "enableCronjob", {});
    }else{
      window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "disableCronjob", {});
    }
  });

  function showErrorMessage(messageid,message){
    $("#"+messageid).text(message).show();
    setInterval(function() {
      $("#"+messageid).hide();
    }, 5000);
  }


  function sendToAPI(action, data){
    $.ajax({
      url: url,
      type: type,
      dataType: 'JSON',
      encode: true,
      data : {
        "action" : action,
        "data" : data
      },
      success: function (result, status, xhr) {
        if(result["status"] == 0){
          showMessage("bg-info", result["message"]);
          if(action == "stopWebsocket"){
            setWSSStopped();
          }else if(action == "startWebsocket"){
            setWSSRunning(result["data"]);
          }else if(action == "restartWebsocket"){
            setWSSRunning(result["data"]);
          }
        }else{
          showMessage("bg-danger", result["message"]);
          if(action == "restartWebsocket"){
            setWSSStopped();
          }
        }
      },
      error:function(xhr, status, error){
        showMessage("bg-danger", error);
      }
    });
  }

  function setWSSRunning(pid){
    $("#wssstatus").children().remove();
    $("#wssstatus").append(
      "<div class='card bg-success text-white shadow'>" +
          "<div class='card-body'>" +
            "Status: Running (PID: " + pid + ")" +
            "<div class='text-white-50 small'>All websocket services are good</div>" +
          "</div>" +
      "</div>"
    );
    $("#startWSS").attr("disabled", "disabled");
    $("#stopWSS").removeAttr("disabled");
    $("#restartWSS").removeAttr("disabled");
  }

  function setWSSStopped(){
    $("#wssstatus").children().remove();
    $("#wssstatus").append(
      "<div class='card bg-danger text-white shadow'>" +
          "<div class='card-body'>" +
              "Status: Not Running" +
              "<div class='text-white-50 small'>Status: Not Running</div>" +
          "</div>" +
      "</div>"
    );
    $("#startWSS").removeAttr("disabled");
    $("#stopWSS").attr("disabled", "disabled");
    $("#restartWSS").attr("disabled", "disabled");
  }
});

function settingConfirmHandler(){
  $(".setting-confirm").off("click");
  $(".setting-confirm").on("click", function(){
    var data = {};
    data["settingtype"] = $(this).attr("data-settingtype");
    window.sendToWSS("backendRequest", "ChiaMgmt\\System\\System_Api", "System_Api", "confirmSetting", data);
  });
}

function reloadCronJobExecTimer(){
  if("system" in intervals && "cron" in intervals["system"]){
    clearTimeout(intervals["system"]["cron"]);
  }

  if($("#lastcronrun").length > 0){
    intervals["system"] = {};
    intervals["system"]["cron"] = setInterval(function () {
      var currentseconds = parseInt($("#lastcronrun").text());
      $("#lastcronrun").text(currentseconds + 1);
    }, 1000);
  }
}

function setWebsocketRunningStatus(status, pid){
  if(status == 0){
    $("#wssstatus").html("<div class='card bg-success text-white shadow'>" +
                            "<div class='card-body'>" +
                              "Status: Running (PID: " + pid + ")" +
                              "<div class='text-white-50 small'>All websocket services are good</div>" +
                            "</div>" +
                          "</div>");
    $("#startWSS").prop("disabled", "disabled");
    $("#stopWSS").removeAttr("disabled");
    $("#restartWSS").removeAttr("disabled");
  }else{
    $("#wssstatus").html("<div class='card bg-danger text-white shadow'>" +
                          "<div class='card-body'>" +
                            "Status: Not Running" +
                            "<div class='text-white-50 small'>Live data transmition not possible</div>" +
                          "</div>" +
                        "</div>");
    $("#startWSS").removeAttr("disabled");
    $("#stopWSS").prop("disabled", "disabled");
    $("#restartWSS").prop("disabled", "disabled");
  }
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if (key == "setSystemSettings"){
      if(data[key]["data"] == "mailing"){
        $("#save-mail-settings i").hide();
        $("#settingtype_mailing").html(
          "<div class='card bg-warning text-white shadow'>" +
          "<div class='card-body'>" +
          "Your setting are currently not confirmed." +
          "<br>" +
          "<button type='button' class='btn btn-secondary setting-confirm' data-settingtype='mailing'>Confirm</button>" +
          "</div>" +
          "</div>"
        );
        $("#send-testmail").removeAttr("disabled");
        settingConfirmHandler();
      }
    }else if(key == "sendTestMail"){
      $("#confirm-testmail-option i").hide();
      $("#send_testmail_dialog").modal("hide");
    }else if(key == "confirmSetting"){
      $("#settingtype_" + data[key]["data"]["settingtype"]).html(
        "<div class='card bg-success text-white shadow'>" +
            "<div class='card-body'>" +
              "Your setting are currently confirmed." +
            "</div>" +
        "</div>"
      );
    }else if(key == "checkForUpdates"){
      $("#updateversionbadge").removeClass("badge-success").removeClass("badge-warning");
      if(data[key]["data"]["updateavail"]){
        $("#updateversionbadge").addClass("badge-warning").text("Your version is out of date. Version " + data[key]["data"]["remoteversion"] + " is available. Please update soon.");
      }else{
        $("#updateversionbadge").addClass("badge-success").text("Your version is up to date.");
      }
      updatedata = data[key]["data"];
    }else if(key == "processUpdate"){
      $("#confirm-update-process").hide();
      $("#proceed-update-routine").show();
    }else if(key == "setInstanceUpdating"){
      $(location).attr('href',frontend + '/sites/installer_updater/');
    }else if(key == "updateProjectVersion"){
      location.reload();
    }else if(key == "enableCronjob"){
      $("#cronjobbadge").removeClass("badge-success").removeClass("badge-danger").addClass("badge-success").text("Cronjob enabled. Next run in some seconds.");
    }else if(key == "disableCronjob"){
      $("#cronjobbadge").removeClass("badge-success").removeClass("badge-danger").addClass("badge-danger").text("Cronjob not enabled.");
    }else if(key == "cronJobExecution"){
      $("#cronjobbadge").removeClass("badge-success").removeClass("badge-danger").addClass("badge-success").html("Last Cronjob run <span id='lastcronrun'>0</span> seconds ago.</span>");
      reloadCronJobExecTimer();
    }else if(key == "socketConnected"){
      if(data[key]["data"]){
        window.sendToWSS("wssonlinestatus", "", "", "", {});
      }else{
        setWebsocketRunningStatus(1);
      }
    }else if(key == "wssonlinestatus"){
      setWebsocketRunningStatus(data[key]["status"], data[key]["data"]);
    }
  }else{
    showMessage(2, data["message"]);
    if (key == "setSystemSettings"){
      showErrorMessage("mailsetuperror",data[key]["message"]);
    }else if(key == "checkForUpdates"){
      $("#updateversionbadge").removeClass("badge-success").removeClass("badge-warning");
      $("#updateversionbadge").addClass("badge-warning").text(data[key]["message"]);
    }else if(key == "processUpdate"){
      $("#confirm-update-process").removeAttr("disabled").find("i").hide();
      $(".update-close-button").show();
      $(".updatelogcontainer").append("<span><i class='fas fa-times-circle text-danger'></i>Update failed. Please resolve the issues and redo the update.</span>");
    }else if(key == "enableCronjob"){
      $("#enableSystemCronjob").prop( "checked", false );
    }else if(key == "disableCronjob"){
      $("#enableSystemCronjob").prop( "checked", true );
    }
  }

  if(key == "processingUpdate"){
    if(data[key]["step"] != undefined){
      var message = "<i class='fas " + (data[key]["processing-status"] == 0 ? "fa-check-circle text-success" : (data[key]["processing-status"] == 1 ? "fa-times-circle text-danger" : "fa-circle text-secondary")) + "'></i>&nbsp;" + data[key]["message"];
      $("#updatelogcontainer").append("<span id='step-" + data[key]["step"] + "'>" + message + "</span><br>").show('slow');
    }
  }
}
