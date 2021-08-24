initRefreshFarmInfos();
initRestartFarmerService();
initChallengesTables();

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaFarmData, function(nodeid, farmdata) {
      queryFarmData(nodeid);
  });
});

function initRefreshFarmInfos(){
  $(".refreshFarmInfo").off("click");
  $(".refreshFarmInfo").on("click", function(e){
    e.preventDefault();
    queryFarmData($(this).attr("data-node-id"));
  });
}

function initRestartFarmerService(){
  $(".restartFarmerService").off("click");
  $(".restartFarmerService").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var authhash = chiaFarmData[nodeid]["nodeauthhash"];
    var dataforclient = {
      "nodeid" : nodeid,
      "authhash": authhash
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "restartFarmerService", dataforclient);
  });
}

function initChallengesTables(nodeid){
  $("#challengestable").DataTable();
}

function queryFarmData(nodeid){
  var authhash = chiaFarmData[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeid" : nodeid,
    "authhash": authhash
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "queryFarmData", dataforclient);
}

function queryFarmStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaFarmData[nodeid]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", data);
}

function setFarmerBadge(data){
  var targetbadge = $("#servicestatus_" + data["data"]);
  targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger");
  if(data["status"] == 0){
    targetbadge.addClass("badge-success");
  }else{
    targetbadge.addClass("badge-danger");
  }
  targetbadge.html(data["message"]);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  console.log(data);

  if(data[key]["status"] == 0){
    if(key == "updateFarmData"){
      $('#farminfocards').load(frontend + "/sites/chia_farm/templates/cards.php");

      initRefreshFarmInfos();
      initRestartFarmerService();
      initChallengesTables();
    }else if(key == "farmerStatus"){
      setFarmerBadge(data[key]["data"]);
    }else if(key == "farmerServiceRestart"){
      setFarmerBadge(data[key]["data"]);
    }
  }else if(data[key]["status"] == "014003001"){
    $(".statusbadge").each(function(){
      var thisnodeid = $(this).attr("data-node-id");
      if(($(this).hasClass("badge-secondary") || $(this).hasClass("badge-success")) && $.inArray(data[key]["data"]["informed"],thisnodeid) == -1){
        $(this).removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger").addClass("badge-danger").html("Node not reachable");
      }
    });
  }
}
