$(function(){
  if(loggedinstatus == "007009002"){
    showAuthKeyWindow();
  }

  if(loggedinstatus == "007009003"){
    showTOTPKeyWindow();
  }

  $("#resend-authkey").on("click", function(e){
    e.preventDefault();
    resendAuthkey();
  });

  $("#inputAuthkey").on("input", function(){
    if($(this).val().trim().length == 50){
      $("#authkeybutton").removeAttr("disabled");
    }else{
      $("#authkeybutton").attr("disabled","disabled");
    }
  });

  $(".go-back").on("click", function(e){
    e.preventDefault();
    var url = backend + "/core/Login/Login_Rest.php";
    var action = "invalidateLogin";
    var type = "POST";

    sendToAPI(url, action, type, {});
  });

  $("#authkeybutton").on("click", function(e){
    e.preventDefault();
    var authkey = $("#inputAuthkey").val().trim();
    if(authkey.length == 50){
      var url = backend + "/core/Login/Login_Rest.php";
      var action = "checkAuthKey";
      var type = "POST";
      var data = {
        "authkey" : authkey
      }
      $(this).attr("disabled","disabled").find("i").show();
      sendToAPI(url, action, type, data);
    }else{
      showMessage("alert-danger", "Authkey not valid or empty.");
    }
  });

  $("#loginbutton").on("click",function(e){
    e.preventDefault();
    var username = $("#inputLogin").val();
    var password = $("#inputPassword").val();
    var stayloggedin = ($("#stayloggedin").prop("checked") ? "1" : "0");
    if(username != "" && password != ""){
      var action = "login";
      var type = "POST";
      var url = backend + "/core/Login/Login_Rest.php";
      var data = {
        username: username,
        password: password,
        stayloggedin: stayloggedin
      };
      beginLogin();
      sendToAPI(url, action, type, data);
    }else{
      if(username == "" && password == ""){
        $("#inputLogin").addClass("error");
        setTimeout(function() {
          $("#inputLogin").removeClass("error");
        },2000);
        showMessage("alert-danger", "Username and Password cannot be emtpy!");
      }else{
        if(username == ""){
          $("#inputLogin").addClass("error");
          setTimeout(function() {
            $("#inputLogin").removeClass("error");
          },2000);
          showMessage("alert-danger", "Username cannot be emtpy!");
        }
        if(password == ""){
          $("#inputPassword").addClass("error");
          setTimeout(function() {
            $("#inputPassword").removeClass("error");
          },2000);
          showMessage("alert-danger", "Password cannot be emtpy!");
        }
      }
    }
  });

  $("#forgot-password").on("click", function(e){
    e.preventDefault();
    showPWResetwindow();
  });

  $("#pwreset-go-back").on("click", function(e){
    e.preventDefault();
    showloginwindow();
  });

  $("#inputPWReset").on("input", function(){
    if($(this).val().trim().length > 0){
      $("#sendResetLinkBtn").removeAttr("disabled");
    }else{
      $("#sendResetLinkBtn").attr("disabled","disabled");
    }
  });

  $("#sendResetLinkBtn").on("click", function(e){
    e.preventDefault();

    var action = "requestUserPasswordReset";
    var type = "POST";
    var url = backend + "/core/Users/Users_Rest.php";
    var data = {
      username: $("#inputPWReset").val()
    };

    $("#sendResetLinkBtn i").show();
    sendToAPI(url, action, type, data);
  });

  $(".totpinput").on("click", function(){
    $(this).select();
  });

  $(".totpinput").on("input", function(){
    var this_elem = $(this);
    var this_index = parseInt($(this).attr("data-input-index"));
    if(this_elem.val().trim().length == 1 && this_index <= 5){
      $(".totpinput[data-input-index='" + (this_index+1) + "']").focus().select();
    }

    if(checkTOTPKeyValid()){
      $("#totpkeybutton").removeAttr("disabled");
    }else{
      $("#totpkeybutton").attr("disabled","disabled");
    }
  });

  $("#totpkeybutton").on("click", function(e){
    e.preventDefault();
    if(checkTOTPKeyValid()){
      var url = backend + "/core/Login/Login_Rest.php";
      var action = "checkTOTPKey";
      var type = "POST";
      var data = {
        "totpkey" : getTOTPKey()
      }
      $(this).attr("disabled","disabled").find("i").show();
      sendToAPI(url, action, type, data);
    }
  });

  $(".send-backupkey").on("click", function(e){
    e.preventDefault();

    console.log("HIER");
  });

  function checkTOTPKeyValid(){
    var inputsvalid = true;
    $.each($(".totpinput"), function(){
      var val = $(this).val();
      if(!$.isNumeric(val)){
        inputsvalid = false;
      }
    });
    return inputsvalid;
  }

  function getTOTPKey(){
    var key = "";
    $.each($(".totpinput"), function(){
      key += $(this).val().toString();
    });
    return key;
  }

  function beginLogin(){
    $("#loginbutton").attr("disabled", "disabled").find("i").show();
    $("#inputLogin").attr("disabled", "disabled");
    $("#inputPassword").attr("disabled", "disabled");
    $("#stayloggedin").attr("disabled", "disabled");
    $("#remeberMe").attr("disabled", "disabled");
  }

  function finishLogin(){
    $("#loginbutton").removeAttr("disabled").find("i").hide();
    $("#inputLogin").removeAttr("disabled");
    $("#inputPassword").removeAttr("disabled");
    $("#stayloggedin").removeAttr("disabled");
    $("#remeberMe").removeAttr("disabled");
  }

  function showMessage(messagetype, message){
    setTimeout(function () {
      $("#messagecontainer").append(
        "<div class='alert " + messagetype + " alert-dismissible desktop mobile'>" +
          "<a href='#' class='close' data-dismiss='alert' aria-label='close' title='close'>×</a>" +
          "<span>" + message + "</span>" +
        "</div>"
      );
      setTimeout(function () {
        $("#messagecontainer").children().fadeOut(), 10000
      },5000);
    },50);
  }

  function showAuthKeyWindow(){
    if($("#authkeywindow").is(":hidden")){
      $("#loginwindow").hide(500);
      $("#authkeywindow").show(500);
      $("#inputAuthkey").val("");
      $("#authkeybutton").attr("disabled","disabled");
    }
  }


  function showTOTPKeyWindow(){
    if($("#secondFactorTotpWindow").is(":hidden")){
      $("#loginwindow").hide(500);
      $("#authkeywindow").hide(500);
      $("#secondFactorTotpWindow").show(500);
      $(".totpinput").val("");
      $("#totpkeybutton").attr("disabled","disabled");
    }
  }

  function showloginwindow(){
    if($("#loginwindow").is(":hidden")){
      $("#authkeywindow").hide(500);
      $("#pwresetwindow").hide(500);
      $("#secondFactorTotpWindow").hide(500);
      $("#loginwindow").show(500);
    }
  }

  function showPWResetwindow(){
    if($("#pwresetwindow").is(":hidden")){
      $("#inputPWReset").val("");
      $("#loginwindow").hide(500);
      $("#pwresetwindow").show(500);
    }
  }

  function resendAuthkey(){
    var url = backend + "/core/Login/Login_Rest.php";
    var action = "generateAndsendAuthKey";
    var type = "POST";

    sendToAPI(url, action, type, {});
  }

  function sendToAPI(url, action, type, data){
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
          if(action == "login"){
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "sendPWResetLink"){
            showMessage("alert-success", result["message"]);
            $("#pwresetform").hide();
            $("#loginform").show("slow");
            $("#resetPWLogin").val("");
          }else if(action == "generateAndsendAuthKey"){
            showMessage("alert-success", result["message"]);
          }else if(action == "invalidateLogin"){
            showloginwindow();
          }else if(action == "checkAuthKey"){
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "checkTOTPKey"){
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "requestUserPasswordReset"){
            $("#pwResetMessage").show();
            $("#pwResetMessage .card-body").text(result["message"]);
            $("#sendResetLinkBtn i").hide();
          }
        }else{
          if(result["status"] == "007001003" || result["status"] == "007001001"){
            showAuthKeyWindow();
          }else if(result["status"] == "007009003" || result["status"] == "007001002"){
            showTOTPKeyWindow();
          }else{
            showMessage("alert-danger", result["message"]);
          }
          finishLogin();
          if(result["status"] != "007001003" && result["status"] != "007009003" && result["status"] != "007001001" && result["status"] != "007001002"){
            $("#authkeybutton").removeAttr("disabled").find("i").hide();
            $("#totpkeybutton").removeAttr("disabled").find("i").hide();
          }
        }
      },
      error:function(xhr, status, error){
        finishLogin();
        $("#authkeybutton").removeAttr("disabled").find("i").hide();
        showMessage("alert-danger", error);
      }
    });
  }
});
