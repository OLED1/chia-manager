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

$("#check-websocket-config").on("click", function(){
  sendData("checkWSSPortAvailable", { "wss-port" : $("#socket_local_port").val() });
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

  data["branch"] = "main";

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

  $("#complete-setup-install").hide();
  $("#complete-setup").find(".fa-hourglass-start").removeClass("fa-hourglass-start").addClass("fa-spinner fa-spin");
  sendData("installChiamgmt", data);
});

$("#complete-setup-finish").on("click", function(){
  $(this).hide();
  $("#complete-setup-to-login").show();
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
      var showretry = false;
      if(action == "checkServerDependencies"){
        $("#check-files-writeable").removeClass("alert-danger").removeClass("alert-success").addClass((result["data"]["files-writeable"]["status"] == 0 ? "alert-success" : "alert-danger")).text(result["data"]["files-writeable"]["message"]);
        $("#dep-php-version").removeClass("alert-danger").removeClass("alert-success").addClass((result["data"]["php-version"]["status"] == 0 ? "alert-success" : "alert-danger")).text(result["data"]["php-version"]["message"]);
        $("#dep-php-modules").removeClass("alert-danger").removeClass("alert-success").addClass((result["data"]["php-modules"]["status"] == 0 ? "alert-success" : "alert-danger")).text(result["data"]["php-modules"]["message"]);

        if(result["data"]["php-version"]["status"] == 0 && result["data"]["php-modules"]["status"] == 0 && result["data"]["files-writeable"]["status"] == 0){
          $("#server-dependencies-button").show();
          $("#recheck-dependencies").hide();
        }else{
          $("#server-dependencies-button").hide();
          $("#recheck-dependencies").show();
          $("#updater-dependencies i").removeClass("fa-spinner").removeClass("fa-spinning").addClass("fa-times").css("color","red");
          alert(result["message"]);
        }
      }else if(action == "checkMySQLConfig"){
        if(result["status"] == 0){
          $("#check-db-config").hide();
          $("#mysql-configuration-button").show();
          $("#mysql-configuration input").prop("disabled",true);
        }else{
          alert(result["message"]);
        }
      }else if(action == "checkWSSPortAvailable"){
        if(result["status"] == 0){
          $("#socket_protocol").prop("disabled", true);
          $("#socket_local_port").prop("disabled", true);
          $("#check-websocket-config").hide();
          $("#websocket-configuration-button").show();
        }else{
          alert(result["message"]);
        }
      }else if(action == "installChiamgmt"){
        var overallstatus = true;

        //Set status for config file
        $("#create-config-file i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").removeClass("fa-check");
        $("#create-config-file-log").html("").hide();
        if("config_file" in result["data"]){
          if(result["data"]["config_file"]["status"] == 0){
            $("#create-config-file i").addClass("fa-check").css("color","green");
          }else{
            overallstatus = false;
            $("#create-config-file i").addClass("fa-times").css("color","red");
          }
  
          $.each(result["data"]["config_file"]["data"], function(step, output){
            $("#create-config-file-log").html($("#create-config-file-log").html() + " " + output["message"]).show();
          });
        }else{
          $("#create-config-file i").addClass("fa-times").css("color","red");
        }

        //Set status for htaccess file
        $("#create-htaccess-file i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").removeClass("fa-check");
        $("#create-htaccess-file-log").html("").hide();
        if("htaccess_file" in result["data"]){
          if(result["data"]["htaccess_file"]["status"] == 0){
            $("#create-htaccess-file i").addClass("fa-check").css("color","green");
          }else{
            overallstatus = false;
            $("#create-htaccess-file i").addClass("fa-times").css("color","red");
          }
          $.each(result["data"]["htaccess_file"]["data"], function(step, output){
            var message = $("#create-htaccess-file-log").html() + " " + output["message"];
            if((step+1) in result["data"]["htaccess_file"]["data"]) message += "<br>";
            $("#create-htaccess-file-log").html(message).show();
          });
        }else{
          $("#create-htaccess-file i").addClass("fa-times").css("color","red");
        }

        //Set status for db import
        $("#database-setup i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").removeClass("fa-check");
        $("#database-setup-log").html("").hide();
        if("db_config" in result["data"]){  
          if(result["data"]["db_config"]["status"] == 0){
            $("#database-setup i").addClass("fa-check").css("color","green");
          }else{
            overallstatus = false;
            $("#database-setup i").addClass("fa-times").css("color","red");
          }
          $.each(result["data"]["db_config"]["data"], function(step, output){
            var message = $("#database-setup-log").html() + " " + output["message"];
            if((step+1) in result["data"]["db_config"]["data"]) message += "<br>";
            $("#database-setup-log").html(message).show(); 
          });
        }else{
          $("#database-setup i").addClass("fa-times").css("color","red");
        }

        //Set status for websocket fist start
        $("#first-start-websocket i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").removeClass("fa-check");
        $("#first-start-websocket-log").html("").hide();
        if("first_start_websocket" in result["data"]){  
          if(result["data"]["first_start_websocket"]["status"] == 0){
            $("#first-start-websocket i").addClass("fa-check").css("color","green");
          }else{
            overallstatus = false;
            $("#first-start-websocket i").addClass("fa-times").css("color","red");
          }
          $.each(result["data"]["first_start_websocket"]["data"], function(step, output){
            var message = $("#first-start-websocket-log").html() + " " + output["message"];
            if((step+1) in result["data"]["first_start_websocket"]["data"]) message += "<br>";
            $("#first-start-websocket-log").html(message).show(); 
          });
        }else{
          $("#first-start-websocket i").addClass("fa-times").css("color","red");
        } 

        //Set status for installation procedure
        $("#install-success i").removeClass("fa-hourglass-start").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").removeClass("fa-check");
        if(overallstatus){
          $("#complete-setup-install").hide();
          $("#complete-setup-finish").show().prop("disabled", false);
          $("#install-success i").addClass("fa-check").css("color","green");
        }else{
          $("#complete-setup-install").text("Try to install again");
          $("#complete-setup-install").show().prop("disabled", false);
          $("#install-success i").addClass("fa-times").css("color","red");
        }
      }else if(action == "checkFilesWritable"){
        if(result["status"] == 0){
          $("#updater-writable i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
          $("#updater-writable-log").text(result["message"]);
  
          $("#updater-maintenance-on i").removeClass("fa-hourglass-start").removeClass("fa-times").removeClass("fa-check").addClass("fa-spinner fa-spin");
          sendData("setMaintenanceMode", { "userID" : userID, "maintenance_mode" : 1 });
        }else{
          $("#updater-writable i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          $("#updater-writable-log").text(result["message"]);
  
          showretry = true;
        }
      }else if(action == "setMaintenanceMode"){
        if(result["status"] == 0){
          if(data["maintenance_mode"] == 1){
            $("#updater-maintenance-on-log").text(result["message"]);
            $("#updater-maintenance-on i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
  
            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
            sendData("stopWebsocketServer", {});
          }else{
            $("#updater-maintenance-off-log").text(result["message"]);
            $("#updater-maintenance-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
          }
        }else{
          if(data["maintenance_mode"] == 1){
            $("#updater-maintenance-on i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            $("#updater-maintenance-on-log").text(result["message"]);
          }else{
            $("#updater-maintenance-off i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            $("#updater-maintenance-off-log").text(result["message"]);
          }
          showretry = true;
        }
      }else if(action == "stopWebsocketServer"){
        if(result["status"] == 0){
          $("#updater-websocket-off-log").text(result["message"]);
          $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
  
          $("#updater-backup i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("createBackups", {});
        }else{
          $("#updater-websocket-off-log").text(result["message"]);
          if(result["status"] == "015004002"){
            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
            $("#updater-backup i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
            sendData("createBackups", {});
          }else{
            $("#updater-websocket-off i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
            showretry = true;
          }
        }
      }else if(action == "createBackups"){
        if(result["status"] == 0){
          $("#updater-backup-log").text(result["message"]);
          $("#updater-backup i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-downloading i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("downloadUpdateFiles", {});
        }else{
          $("#updater-backup-log").text(result["message"]);
          $("#updater-backup i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          showretry = true;
        }
      }else if(action == "downloadUpdateFiles"){
        if(result["status"] == 0){
          $("#updater-downloading-log").text(result["message"]);
          $("#updater-downloading i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-extracting-moving i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("extractAndMoveUpdateFiles", {});
        }else{
          $("#updater-downloading-log").text(result["message"]);
          $("#updater-downloading i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
          showretry = true;
        }
      }else if(action == "extractAndMoveUpdateFiles"){
        if(result["status"] == 0){
          $("#updater-extracting-moving-log").text(result["message"]);
          $("#updater-extracting-moving i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
  
          $("#updater-adjusting-db i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("checkAndAdjustDatabase", {});
        }else{
          $("#updater-extracting-moving-log").text(result["message"]);
          $("#updater-extracting-moving i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
  
          showretry = true;
        }
      }else if(action == "checkAndAdjustDatabase"){
        if(result["status"] == 0){
          $("#updater-adjusting-db-log").text(result["message"]);
          $("#updater-adjusting-db i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
  
          $("#updater-set-version i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("updateConfigFile", {});
        }else{
          $("#updater-adjusting-db-log").text(result["message"]);
          $("#updater-adjusting-db i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
  
          showretry = true;
        }
      }else if(action == "updateConfigFile"){
        if(result["status"] == 0){
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");
  
          $("#updater-websocket-on i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("startWebsocketServer", {});
        }else{
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
  
          showretry = true;
        }
      }else if(action == "startWebsocketServer"){
        if(result["status"] == 0){
          $("#updater-websocket-on-log").text(result["message"]);
          $("#updater-websocket-on i").removeClass("fa-spinner").removeClass("fa-spin").removeClass("fa-times").addClass("fa-check").css("color","green");

          $("#updater-maintenance-off i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
          sendData("setMaintenanceMode", { "userID" : userID, "maintenance_mode" : 0 });
          sendData("setInstanceUpdating", { "userid" : userID, "updatestate" : 0 });
  
          $("#retry-update").hide();
          $("#update-finish-btn").prop("disabled", false).show();
        }else{
          $("#updater-set-version-log").text(result["message"]);
          $("#updater-set-version i").removeClass("fa-spinner").removeClass("fa-spin").addClass("fa-times").css("color","red");
  
          $("#updater-maintenance-off i").removeClass("fa-hourglass-start").removeClass("fa-times").addClass("fa-spinner fa-spin").css("color","");
        }
      }
      if(showretry) $("#retry-update").show();
    },
    error:function(xhr, status, error){
        alert(error);
    }
  });
}
