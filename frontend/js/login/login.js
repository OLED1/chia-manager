$(function(){
  if(loggedinstatus == "004008002"){
    showAuthKeyWindow();
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

  $("#go-back").on("click", function(e){
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
    $("#loginwindow").hide(500);
    $("#authkeywindow").show(500);
  }

  function showloginwindow(){
    $("#authkeywindow").hide(500);
    $("#loginwindow").show(500);
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
            $(location).attr('href',frontend + '/index.php');
          }else if(action == "invalidateLogin"){
            showloginwindow();
          }else if(action == "checkAuthKey"){
            $(location).attr('href',frontend + '/index.php');
          }
        }else{
          if(result["status"] == "004001001"){
            resendAuthkey();
            showAuthKeyWindow();
          }else{
            showMessage("alert-danger", result["message"]);
          }
        }
      },
      error:function(xhr, status, error){
        showMessage("alert-danger", error);
      }
    });
  }
});
