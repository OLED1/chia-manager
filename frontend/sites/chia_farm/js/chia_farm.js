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

function setFarmerBadge(nodeid, status, message){
  var targetbadge = $("#servicestatus_" + nodeid);
  targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger");
  if(status == 0){
    targetbadge.addClass("badge-success");
  }else if(status == 1){
    targetbadge.addClass("badge-danger");
  }else if(status == 2){
    targetbadge.addClass("badge-secondary");
  }
  targetbadge.text(message);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateFarmData"){
      $('#farminfocards').load(frontend + "/sites/chia_farm/templates/cards.php");

      initRefreshFarmInfos();
      initRestartFarmerService();
      initChallengesTables();
    }else if(key == "farmerStatus"){
      setFarmerBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "farmerServiceRestart"){
      setFarmerBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "connectedNodesChanged"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
    }else if(key == "queryNodesServicesStatus"){
      $.each(data[key]["data"], function(nodeid, condata){
        if(condata["onlinestatus"] == 1){
          setFarmerBadge(nodeid, condata["onlinestatus"], "Node not reachable");
        }
      });
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

setTimeout(function(){
  if($(".statusbadge.badge-secondary").length > 0){
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  }
}, 9000);
