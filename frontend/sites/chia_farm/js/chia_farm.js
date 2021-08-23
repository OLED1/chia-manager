initRefreshFarmInfos();
initRestartFarmerService();
initChallengesTables();
initAllFarmStatus();

$("#queryAllNodes").on("click", function(){
  $.each(chiaFarmData, function(nodeid, farmdata) {
      queryFarmData(nodeid);
  });
});

function initAllFarmStatus(){
  $.each(chiaFarmData, function(nodeid, farmdata) {
    queryFarmStatus(nodeid);
  });
}

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
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "restartFarmerService" : {
          "status" : 0,
          "message" : "Restart farmer service.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "restartFarmerService", datafornode);
  });
}

function initChallengesTables(nodeid){
  $("#challengestable").DataTable();
}

function queryFarmData(nodeid){
  var authhash = chiaFarmData[nodeid]["nodeauthhash"];
  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
      "queryFarmData" : {
        "status" : 0,
        "message" : "Query Farm data.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryFarmData", datafornode);
}

function queryFarmStatus(nodeid){
  var datafornode = {
    "nodeinfo":{
      "authhash": chiaFarmData[nodeid]["nodeauthhash"]
    },
    "data" : {
      "queryFarmerStatus" : {
        "status" : 0,
        "message" : "Query Farmer running status.",
        "data": {}
      }
    }
  }

  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaFarmData[nodeid]["nodeauthhash"]}
  ];

  //sendToWSS("messageSpecificNode", "", "", "queryFarmerStatus", datafornode);
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
      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm_Api", "getFarmData", {});
    }else if(key == "getFarmData"){
      $('#walletcontainer').load(frontend + "/sites/chia_farm/templates/cards.php");

      initRefreshFarmInfos();
      initRestartFarmerService();
      initChallengesTables();
      initAllFarmStatus();
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
