var usrdatatable = $('#usrDataTable').DataTable();
var disabledusrdatatable = $('#disabledUsrDataTable').DataTable();

$.each(userData, function(key, value){
  if(value["enabled"] == 1){
    var rowNode = usrdatatable
    .row.add( [ value["id"], value["username"], value["name"] + " " + value["lastname"], value["email"] , getButtons(value["id"]) ] )
    .draw()
    .node().id = "user_" + value["id"];
  }else if(value["enabled"] == 0){
    var rowNode = disabledusrdatatable
    .row.add( [ value["id"], value["username"], value["name"] + " " + value["lastname"], value["email"] , getEnableButton(value["id"]) ] )
    .draw()
    .node().id = "user_" + value["id"];
  }
});

function getButtons(usrid){
  return "<button type='button' data-usrid=" + usrid + " class='edit-user btn btn-primary wsbutton'><i class='fas fa-edit'></i></button>&nbsp" +
         (usrid != userID ? "<button type='button' data-usrid=" + usrid + " class='disable-user btn btn-warning wsbutton'><i class='fas fa-user-minus'></i></button>" : "" ) + "&nbsp" +
         (usrid != userID ? "<button type='button' data-usrid=" + usrid + " class='send-invitation-mail btn btn-secondary wsbutton'><i class='fas fa-paper-plane'></i></button>" : "" );
}

function getEnableButton(usrid){
  return  "<button type='button' data-usrid=" + usrid + " class='enable-user btn btn-secondary wsbutton'><i class='fas fas fa-user-plus'></i></button>";
}

initEditUser();
initDisableUser();
initEnableUser();
initSendInvitationMail();

function initEditUser(){
  $(".edit-user").off("click");
  $(".edit-user").on("click", function(){
    var usrid = $(this).attr("data-usrid");
    $.each(userData[usrid], function(key, value){
      $("#" + key).val(value);
    });

    $("#addEditModalTitle").text("Edit User (ID: " + usrid + ")");
    $("#userAddEditModal").attr("data-mode","edit");
    $("#userAddEditModal").modal("show");
    $("#saveusrchanges").attr("disabled","disabled");
    $("#newPW").val("");
    $("#passwordhint").text("");
  });
}

function initDisableUser(){
  $(".disable-user").off("click");
  $(".disable-user").on("click", function(){
    var data = {
      "userID" : $(this).attr("data-usrid")
    }
    sendToWSS("backendRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "disableUser", data);
  });
}

function initEnableUser(){
  $(".enable-user").off("click");
  $(".enable-user").on("click", function(){
    var data = {
      "userID" : $(this).attr("data-usrid")
    }
    sendToWSS("backendRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "enableUser", data);
  });
}

function initSendInvitationMail(){
  $(".send-invitation-mail").off("click");
  $(".send-invitation-mail").on("click", function(){
    data = {
      "userID" : $(this).attr("data-usrid")
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "sendInvitationMail", data);
  });
}

$(".personinput").on("input", function(){
  if($("#userAddEditModal").attr("data-mode") == "edit"){
    if(dataChanged($("#id").val()) && fieldsNotEmpty()){
      $("#saveusrchanges").removeAttr("disabled");
    }else{
      $("#saveusrchanges").attr("disabled","disabled");
    }
  }else if($("#userAddEditModal").attr("data-mode") == "add"){
    if(fieldsNotEmpty() && checkNewPassword()){
      $("#saveusrchanges").removeAttr("disabled");
    }else{
      $("#saveusrchanges").attr("disabled","disabled");
    }
  }
});

$("#newPW").on("input", function(){
  var pw = $(this).val().trim();
  if(checkNewPassword(pw)){
    $("#saveusrchanges").removeAttr("disabled");
  }else{
    $("#saveusrchanges").attr("disabled","disabled");
  }
});

$("#addUser").on("click", function(){
  $.each($(".personinput"), function(){
    $(this).val("");
  });
  $("#newPW").val("");
  $("#addEditModalTitle").text("Add User");
  $("#userAddEditModal").attr("data-mode","add");
  $("#userAddEditModal").modal("show");
  $("#saveusrchanges").attr("disabled","disabled");
});

function checkNewPassword(){
  var password = $("#newPW").val().trim();
  var regex = "^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})";
  if(password.match(regex)){
    $("#passwordhint").text("");
    return true;
  }else if(!password.match(regex)){
    $("#passwordhint").text("Password must contain A-Z,a-z,1-9,[!@#\$%\^&\*]]");
    return false;
  }
}

$("#saveusrchanges").on("click", function(){
  var usrid = $("#id").val();
  if(dataChanged(usrid) || $("#newPW").val().trim().length > 0){
    var data = {};
    $.each($(".personinput"), function(){
      data[$(this).attr("name")] = $(this).val();
    });

    if($("#newPW").val().trim().length > 0) data["password"] = $("#newPW").val().trim();
    if($("#userAddEditModal").attr("data-mode") == "edit") sendToWSS("backendRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "editUserInfo", data);
    else if($("#userAddEditModal").attr("data-mode") == "add") sendToWSS("backendRequest", "ChiaMgmt\\Users\\Users_Api", "Users_Api", "addUser", data);
    else showMessage(1, "Type not valid.");
  }else{
    showMessage(1, "Data did not change.");
  }
});

function fieldsNotEmpty(usrid, mode){
  var notempty = true;
  $.each($(".personinput"), function(){
    if($(this).val().trim().length == 0 && $(this).attr("name") != "id") notempty = false;
  });

  return notempty;
}

function dataChanged(usrid){
  var changed = false;

  $.each(userData[usrid], function(key, value){
    thisval = $("#"+key).val();
    if(thisval != undefined){
      var val = thisval.trim();
      var safedval = value;

      if(val != safedval) changed = true;
    }
  });

  return changed;
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "editUserInfo"){
      var id = data[key]["data"]["id"];
      var tableRow = usrdatatable.row($("#user_" + id));
      usrdatatable
        .row( tableRow )
        .data([ data[key]["data"]["id"], data[key]["data"]["username"], data[key]["data"]["name"] + " " + data[key]["data"]["lastname"], data[key]["data"]["email"], getButtons(data[key]["data"]["id"]) ])
        .draw();

      $("#userAddEditModal").modal("hide");
      userData = data[key]["data"];

      if(id == userID) $("#sitewrapperusername").text(data[key]["data"]["name"] + " " + data[key]["data"]["lastname"]);
    }else if(key == "addUser"){
      $.each(data[key]["data"], function(arrkey, value){
        var rowNode = usrdatatable
        .row.add( [ value["id"], value["username"], value["name"] + " " + value["lastname"], value["email"], getButtons(value["id"]) ] )
        .draw()
        .node().id = "user_" + value["id"];

        userData[value["id"]] = data[key]["data"][value["id"]];
      });

      $("#userAddEditModal").modal("hide");
    }else if(key == "disableUser"){
      var id = data[key]["data"]["userID"];
      var tableRow = usrdatatable.row($("#user_" + id));

      var rowNode = usrdatatable
      .row(tableRow)
      .remove($("#user_" + id))
      .draw();

      var rowNode = disabledusrdatatable
      .row.add( [ userData[id]["id"], userData[id]["username"], userData[id]["name"] + " " + userData[id]["lastname"], userData[id]["email"], getEnableButton(userData[id]["id"]) ] )
      .draw()
      .node().id = "user_" + userData[id]["id"];

      userData[id]["enabled"] = 0;
    }else if(key == "enableUser"){
      var id = data[key]["data"]["userID"];
      var tableRow = disabledusrdatatable.row($("#user_" + id));

      var rowNode = disabledusrdatatable
      .row(tableRow)
      .remove($("#user_" + id))
      .draw();

      var rowNode = usrdatatable
      .row.add( [ userData[id]["id"], userData[id]["username"], userData[id]["name"] + " " + userData[id]["lastname"], userData[id]["email"], getButtons(userData[id]["id"]) ] )
      .draw()
      .node().id = "user_" + userData[id]["id"];

      userData[id]["enabled"] = 1;
    }
  }
  initEditUser();
  initDisableUser();
  initEnableUser();
  initSendInvitationMail();
}
