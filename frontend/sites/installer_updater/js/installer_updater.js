$(".install-step").on("click", function(){
  $("#" + $(this).attr("data-myid")).hide();
  $("#" + $(this).attr("data-target")).show();
  $("#nav-" + $(this).attr("data-myid")).addClass("active");
});

$("#recheck-dependencies").on("click", function(){
  sendData("checkServerDependencies", {});
});

$("#check-db-config").on("click", function(){
  var checkpassed = true;
  var data = {};
  $("#mysql-configuration input").each(function(){
    if($(this).val().trim().length == 0){
      alert($(this).attr("id") + " is not allowed to be emtpy.");
      checkpassed = false;
    }
    data[$(this).attr("id")] = $(this).val();
  });

  if(checkpassed){
    sendData("checkMySQLConfig", data);
  }
});

$("#websocket-configuration input").on("input", function(){
  $("#websocket-configuration-button").prop("disabled",false);
  $("#websocket-configuration input").each(function(){
    if($(this).val().trim().length == 0){
      $("#websocket-configuration-button").prop("disabled",true);
    }
  });
});

$("#validate-user-settings").on("click", function(){
  var valid = true;
  $("#webgui-user-configuration input").each(function(){
    if($(this).val().trim().length == 0){
      valid = false;
      alert($(this).attr("name") + " is not allowed to be emtpy.");
    }
  });

  var pw = $("#gui-password").val();
  var repeatpw = $("#repeat-gui-password").val();

  if(valid && !(pw == repeatpw)){
    valid = false;
    alert("Passwords do not match.");
  }

  if(valid && !pw.match(/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[a-zA-Z]).{8,}$/)){
    valid = false;
    alert("Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.");
  }

  if(valid){
    $("#validate-user-settings").hide();
    $("#webgui-user-button").show();
    $("#webgui-user-configuration input").prop("disabled",true);
  }
});

$("#complete-setup-install").on("click", function(){
  var data = {};

  data["branch"] = $("#branch").val();

  data["db_config"] = {};
  $("#mysql-configuration input").each(function(){
    data["db_config"][$(this).attr("name")] = $(this).val();
  });

  data["websocket_config"] = {};
  $("#websocket-configuration input").each(function(){
    data["websocket_config"][$(this).attr("name")] = $(this).val();
  });

  data["webgui_user_config"] = {};
  $("#webgui-user-configuration input").each(function(){
    data["webgui_user_config"][$(this).attr("name")] = $(this).val();
  });

  sendData("installChiamgmt", data);
});

$("#complete-setup-finish").on("click", function(){
  $(this).hide();
  $("#complete-setup-to-login").show();

  $("#setuplog").hide();
  $("#finish-text").show();
});

$("#process-update").on("click", function(){
  processUpdateSteps();
});

$("#retry-update").on("click", function(){
  $(".update-log").text("");
  $("#retry-update").hide();
  processUpdateSteps();
});

function processUpdateSteps(){
  sendData("checkFilesWritable", {});
}

function sendData(action, data){
  $.ajax({
    url: "/backend/core/System_Update/System_Update_Rest.php",
    type: "POST",
    dataType: 'JSON',
    encode: true,
    data: {
      action: action,
      data: data
    },
    success: function (result, status, xhr) {
      if(result["status"] == 0){
        if(action == "checkServerDependencies"){
          $("#dep-php-version").removeClass("alert-danger").removeClass("alert-success").addClass((result["data"]["php-version"]["status"] == 0 ? "alert-success" : "alert-danger")).text(result["data"]["php-version"]["message"]);
          $("#dep-php-modules").removeClass("alert-danger").removeClass("alert-success").addClass((result["data"]["php-modules"]["status"] == 0 ? "alert-success" : "alert-danger")).text(result["data"]["php-modules"]["message"]);

          if(result["data"]["php-version"]["status"] == 0 && result["data"]["php-modules"]["status"] == 0){
            $("#server-dependencies-button").show();
            $("#recheck-dependencies").hide();
          }else{
            $("#server-dependencies-button").hide();
            $("#recheck-dependencies").show();
            $("#updater-dependencies i").removeClass("fa-spinner").removeClass("fa-spinning").addClass("fa-times").style("color","red");
          }


        }else if(action == "checkMySQLConfig"){
          $("#check-db-config").hide();
          $("#mysql-configuration-button").show();
          $("#mysql-configuration input").prop("disabled",true);
        }else if(action == "installChiamgmt"){
          var overallstatus = true;
          var setupout = "<h6><span class='badge badge-secondary'>1. Creating setup file.</span><br>";

          if(result["data"]["config_file"]["status"] > 0) overallstatus = false;
          $.each(result["data"]["config_file"]["data"], function(step, output){
            setupout += "<h6><span class='badge " + (output["status"] == 0 ? "badge-success" : "badge-danger") + "'>--&nbsp;" + output["message"] + "</span><br>";
          });

          setupout += "<h6><span class='badge badge-secondary'>2. Creating mysql tables with default values.</span><br>";
          $.each(result["data"]["db_config"]["data"], function(step, output){
            setupout += "<h6><span class='badge " + (output["status"] == 0 ? "badge-success" : "badge-danger") + "'>--&nbsp;" + output["message"] + "</span><br>";
          });
          if(result["data"]["db_config"]["status"] > 0) overallstatus = false;

          if(overallstatus){
            $("#complete-setup-install").hide();
            $("#complete-setup-finish").show();
            setupout += "<h6><span class='badge badge-success'>3. Installation finished successfully.</span><br>";
          }else{
            $("#complete-setup-install").text("Try to install again");
            setupout += "<h6><span class='badge badge-success'>3. Installation finished with errors.</span><br>";
          }
          $("#setuplog").html(setupout);
        }else if(action == "checkFilesWritable"){
          $("#updater-writable i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
          $("#updater-writable-log").text(result["message"]);

          $("#updater-maintenance-on i").removeClass("fa-hourglass-start").removeClass("fa-times").removeClass("fa-check").addClass("fa-spinner fa-spin");
          sendData("setMaintenanceMode", { "userID" : userID, "maintenance_mode" : 1 });
        }else if(action == "setMaintenanceMode"){
          if(data["maintenance_mode"] == 1){
            $("#updater-maintenance-on-log").text(result["message"]);
            $("#updater-maintenance-on i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
            sendData("stopWebsocketServer", {});
          }else{
            $("#updater-maintenance-off-log").text(result["message"]);
            $("#updater-maintenance-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
          }
        }else if(action == "stopWebsocketServer"){
          $("#updater-websocket-off-log").text(result["message"]);
          $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-backup i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("createBackups", {});
        }else if(action == "createBackups"){
          $("#updater-backup-log").text(result["message"]);
          $("#updater-backup i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-downloading i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("downloadUpdateFiles", {});
        }else if(action == "downloadUpdateFiles"){
          $("#updater-downloading-log").text(result["message"]);
          $("#updater-downloading i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-extracting-moving i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("extractAndMoveUpdateFiles", {});
        }else if(action == "extractAndMoveUpdateFiles"){
          $("#updater-extracting-moving-log").text(result["message"]);
          $("#updater-extracting-moving i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-adjusting-db i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("checkAndAdjustDatabase", {});
        }else if(action == "checkAndAdjustDatabase"){
          $("#updater-adjusting-db-log").text(result["message"]);
          $("#updater-adjusting-db i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-set-version i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("updateConfigFile", {});
        }else if(action == "updateConfigFile"){
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-websocket-on i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("startWebsocketServer", {});
        }else if(action == "startWebsocketServer"){
          $("#updater-websocket-on-log").text(result["message"]);
          $("#updater-websocket-on i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-maintenance-off i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("setMaintenanceMode", { "userID" : userID, "maintenance_mode" : 0 });
          sendData("setInstanceUpdating", { "userid" : userID, "updatestate" : 0 });

          $("#retry-update").hide();
          $("#update-finish-btn").prop("disabled", false).show();
        }
      }else{
        var showretry = false;
        if(action == "checkFilesWritable"){
          $("#updater-writable i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          $("#updater-writable-log").text(result["message"]);

          showretry = true;
        }else if(action == "setMaintenanceMode"){
          if(data["maintenance_mode"] == 1){
            $("#updater-maintenance-on i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            $("#updater-maintenance-on-log").text(result["message"]);
          }else{
            $("#updater-maintenance-off i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            $("#updater-maintenance-off-log").text(result["message"]);
          }
          showretry = true;
        }else if(action == "stopWebsocketServer"){
          $("#updater-websocket-off-log").text(result["message"]);
          if(result["status"] == "015004002"){
            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
            $("#updater-backup i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
            sendData("createBackups", {});
          }else{
            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            showretry = true;
          }
        }else if(action == "createBackups"){
          $("#updater-backup-log").text(result["message"]);
          $("#updater-backup i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          showretry = true;
        }else if(action == "downloadUpdateFiles"){
          $("#updater-downloading-log").text(result["message"]);
          $("#updater-downloading i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          showretry = true;
        }else if(action == "extractAndMoveUpdateFiles"){
          $("#updater-extracting-moving-log").text(result["message"]);
          $("#updater-extracting-moving i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");

          showretry = true;
        }else if(action == "checkAndAdjustDatabase"){
          $("#updater-adjusting-db-log").text(result["message"]);
          $("#updater-adjusting-db i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");

          showretry = true;
        }else if(action == "updateConfigFile"){
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");

          showretry = true;
        }else if(action == "startWebsocketServer"){
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");

          $("#updater-maintenance-off i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
        }else{
          alert(result["message"]);
        }
        if(showretry) $("#retry-update").show();
      }
    },
    error:function(xhr, status, error){
        alert(error);
    }
  });
}
