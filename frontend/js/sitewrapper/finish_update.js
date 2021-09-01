$(function(){
  $("#update_routines").modal("show");
  $("#maintenance_mode_modal").modal("hide");

  setTimeout(function(){
    $("#finish_update_btn").show("slow");
  }, 1000);
});

$("#finish_update_btn").on("click", function(){
  $(this).find("i").show();
  //finish update
  /*$("#update_text").hide();
  $("#update_success").show();
  $("#finish_update_btn").hide();
  setTimeout(function() {
    location.reload();
  }, 2000);*/
  //failed update
  //$("#update_text").hide();
  //$("#update_failed").show();
  //$("#finish_update_btn").hide();

  sendToWSS("backendRequest", "ChiaMgmt\\System_Update\\System_Update_Api", "System_Update_Api", "finishUpdate", {});
});

$("#error_retry").on("click", function(){
  location.reload();
});

$("#error_disable_maintenance").on("click", function(){
  sendToWSS("backendRequest", "ChiaMgmt\\System_Update\\System_Update_Api", "System_Update_Api", "disableMaintenanceMode", {});
});

$("#success_reload").on("click", function(){
  location.reload()
});

function messagesTrigger(data){
  var key = Object.keys(data);

  console.log(data);

  if(key == "disableMaintenanceMode"){
    location.reload();
  }else if(key == "finishUpdate"){
    if(data[key]["status"] == 0){
      $("#update_text").hide();
      $("#update_success").show();
      $("#finish_update_btn").hide();
    }else{
      $("#error_message").text(data[key]["message"]);
      $("#update_text").hide();
      $("#update_failed").show();
      $("#finish_update_btn").hide();
    }
  }
}
