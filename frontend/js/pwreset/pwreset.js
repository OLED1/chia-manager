$("#inputPassword").on("input", function(){
  checkPasswordsNotEmpty();
});

$("#inputRepeatPassword").on("input", function(){
  checkPasswordsNotEmpty();
});

function checkPasswordsNotEmpty(){
  if($("#inputPassword").val().trim().length > 0 && $("#inputRepeatPassword").val().trim().length > 0){
    if($("#inputPassword").val().trim() == $("#inputRepeatPassword").val().trim() && checkNewPassword()){
      $("#resetPassword").removeAttr("disabled");
    }else{
      $("#resetPassword").attr("disabled","disabled");
    }
  }else{
    $("#passwordhint").show().find(".card-body").text("Passwords do not match.");
    $("#resetPassword").attr("disabled","disabled");
  }
}

function checkNewPassword(){
  var password = $("#inputPassword").val().trim();
  var regex = "^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})";

  if(password.match(regex)){
    $("#passwordhint").hide().find(".card-body").text("");
    return true;
  }else if(!password.match(regex)){
    $("#passwordhint").show().find(".card-body").text("Password must contain A-Z,a-z,1-9,[!@#\$%\^&\*]]");
    return false;
  }
}

$("#resetPassword").on("click", function(e){
  e.preventDefault();
  if(checkNewPassword()){
    sendToAPI();
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

function sendToAPI(){
  $("#resetPassword").find("i").show();

  $.ajax({
    url: apilink,
    type: "POST",
    dataType: 'JSON',
    encode: true,
    data : {
      "action" : "resetPassword",
      "data" : {
        "resetKey" : resetkey,
        "newUserPassword" : $("#inputPassword").val().trim()
      }
    },
    success: function (result, status, xhr) {
      console.log(result);
      if(result["status"] == 0){
        showMessage("alert-success", result["message"]);
        showMessage("alert-success", "You will be redirected in 3 seconds.");
        setTimeout(function(){
          $(location).attr('href',frontend + '/index.php');
        }, 3000);
      }else{
        showMessage("alert-danger", result["message"]);
      }
      $("#resetPassword").find("i").hide();
    },
    error:function(xhr, status, error){
      $("#resetPassword").find("i").hide();
      showMessage("alert-danger", error);
    }
  });
}
