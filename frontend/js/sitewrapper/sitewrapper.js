$(function(){
  $("#completely-logout").on("click",function(){
    logout();
  });

  $("#logout").on("click",function(){
    logout();
  });

  $("#log-back-in").on("click",function(){
    var password = $("#log-back-in-password").val();

    if(password.trim().length > 0){
      $.ajax({
        url: serverdomain + "/api/core/login/login.php",
        type: "POST",
        dataType: 'JSON',
        encode: true,
        data: {
          action: "login",
          data : {
            username : username,
            password : password
          }
        },
        success: function (result, status, xhr) {
            if(result["status"] == 0){
                $("#log-back-in-password").val("");
                $("#logout-info-dialog").modal("hide");
                showMessage("alert-success", "Successfully logged back in.");
            }else{
                showMessage("alert-danger", result["message"]);
            }
        },
        error:function(xhr, status, error){
            showMessage("alert-danger", error);
        }
      });
    }
  });
});

$("#clear-all-alerts").on("click", function(e){
  e.preventDefault();
  $("#clear-all-alerts").hide();
  $("#alerts").children().hide("slow", function(){ $(this).remove(); });
  $("#alerts-counter").text(0);
});

$("alerts.a").on("click", function(e){
  e.preventDefault();
});

function logout(){
  var url = backend + "/core/Login/Login_Rest.php";
  var action = "logout";
  var type = "POST";

  transferdata(url, type, action, {});
}

function transferdata(url, type, action, data){
  $.ajax({
    url: url,
    type: type,
    dataType: 'JSON',
    encode: true,
    data: {
      action: action,
      data: data
    },
    success: function (result, status, xhr) {
        if(action == "logout"){
          $(location).attr('href',frontend + '/login.php');
        }else{
          showMessage("alert-danger", result["status"] + ": " + result["message"]);
        }
    },
    error:function(xhr, status, error){
        showMessage("alert-danger", error);
    }
  });
}

function getCurrentDate(){
  date = new Date();
  var months=["JAN","FEB","MAR","APR","MAY","JUN","JUL", "AUG","SEP","OCT","NOV","DEC"];
  var time = date.getHours() + ":" + date.getMinutes() + ":" + date.getSeconds();
  return date.getDate()+" "+months[date.getMonth()]+" "+date.getFullYear() + ", " + time;
}

//alert-primary, alert-secondary, alert-success, alert-danger, alert-warning, alert-info, alert-light, alert-dark
//are available options (messagetypes)
function showMessage(messagetype, message){
  if(message == undefined) return;
  if($.isNumeric(messagetype)){
    switch (messagetype) {
      case 0:
        var type = "bg-info";
        var icon = "fas fa-info-circle";
        break;
      case 1:
        var type = "bg-warning";
        var icon = "fas fa-exclamation-circle";
        break;
      case 2:
        var type = "bg-danger";
        var icon = "fas fa-exclamation-triangle";
        break;
      case 3:
        var type = "bg-secondary";
        var icon = "fas fa-info-circle";
        break;
      default:
        var type = "bg-secondary";
        var icon = "fas fa-info-circle";
        break;
    }
  }else{
    var type = messagetype;
  }

  if(typeof type !== undefined && typeof icon !== undefined){
    var tempmsgid = "tmp_" + Math.random().toString(36).substring(4);
    setTimeout(function () {
      $("#messagecontainer").append(
        "<div id='" + tempmsgid + "' class='card " + type + " text-white shadow'>" +
        "<div class='card-body'>" +
        message +
        "</div>" +
        "</div>"
      );
      setTimeout(function () {
        $("#" + tempmsgid).fadeOut().remove(), 10000
      },2000);
    },50);

    if(!$("#clear-all-alerts").is(":visible")){
      $("#clear-all-alerts").show();
    }

    $("#alerts").prepend(
      "<a class='dropdown-item d-flex align-items-center' href='#'>" +
      "<div class='mr-3'>" +
      "<div class='icon-circle " + type + "'>" +
      "<i class='" + icon + " text-white'></i>" +
      "</div>" +
      "</div>" +
      "<div>" +
      "<div class='small text-gray-500'>" + getCurrentDate() + "</div>" +
      "<span class='font-weight-bold'>" + message + "</span>" +
      "</div>" +
      "</a>"
    );
    var newcount = parseInt($("#alerts-counter").text().split("+")[0]) + 1;
    $("#alerts-counter").text(newcount);
  }
}

function getVisible() {
  $("#sitecontent").css("height","100%");
  var $el = $("main"),
    scrollTop = $(this).scrollTop(),
    scrollBot = scrollTop + $(this).height(),
    elTop = $el.offset().top,
    elBottom = elTop + $el.outerHeight(),
    visibleTop = elTop < scrollTop ? scrollTop : elTop,
    visibleBottom = elBottom > scrollBot ? scrollBot : elBottom;

  return visibleBottom - visibleTop - $("footer").outerHeight();
}

function showLoadingModal(){
  $("#loadingModal").modal("show");
}

function hideLoadingModal(){
  $("#loadingModal").modal("hide");
}

function toggleWSSLoading(tasklist){
  var keys = Object.keys(tasklist);

  if(keys.length > 0){
    $("#wssloading .wssloadingcount").text(keys.length);
    if(!$("#wssloading").is(":visible")){
      $("#wssloading").show();
    }
  }else{
    if($("#wssloading").is(":visible")){
      $("#wssloading").hide();
    }
  }
}

function disableWSButtons(){
  $(".wsbutton").attr("disabled","disabled");
}

function enableWSButtons(){
  $(".wsbutton").removeAttr("disabled");
}
