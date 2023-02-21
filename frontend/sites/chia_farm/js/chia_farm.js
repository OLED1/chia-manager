var challangestables = {};

setTimeout(function(){
  setServiceBadge();
}, 700);

initRefreshFarmInfos();
initRestartFarmerService();
initChallengesTables();

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaNodes, function(nodeid, nodedata) {
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
  if(challangestables[nodeid] !== undefined) challangestables[nodeid].destroy();
  if(!(nodeid in challangestables)) challangestables[nodeid] = {};

  challangestables[nodeid] = $(".challengestables").DataTable({
    "order" : [[ 1, "desc" ]]
  });
}

function queryFarmData(nodeid){
  var authhash = chiaNodes[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeinfo" : {
      "nodeid" : nodeid,
      "authhash": authhash
    }
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "queryFarmData", dataforclient);
}

function queryFarmStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaFarmData[nodeid]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", data);
}

function setServiceBadge(){
  $.each(services_states, function(nodeid, nodedata){
    if(nodedata === "undefined" || nodedata["onlinestatus"]["status"] == 0){
      statustext = "Node not reachable";
      statusicon = "badge-danger";
    }else if(nodedata["onlinestatus"]["status"] == 1){
      servicestate = nodedata["services"][3]["servicestate"];
      servicedesc =  nodedata["services"][3]["service_desc"];
      if(servicestate == 0){
        statustext = servicedesc + " service not running";
        statusicon = "badge-danger";
      }else if(servicestate == 1){
        statustext = servicedesc + " service running";
        statusicon = "badge-success";
      }else{
        statustext = servicedesc + " service state unknown";
        statusicon = "badge-warning";
      }
    }

    $(".statusbadge[data-node-id='" + nodeid + "']").removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass(statusicon).text(statustext);
  });
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateFarmData"){  
      var nodeid = data[key]["data"]["nodeid"];  
      var carddata = { "nodeid" : nodeid};
      $.get(frontend + "/sites/chia_farm/templates/cards.php", carddata, function(response) {
        $('#farmercontainer_' + nodeid).html(response);
        initRefreshFarmInfos();
        initRestartFarmerService();
        initChallengesTables();
      });
    }else if(key == "queryNodesServicesStatus" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
      }
    }
    setTimeout(function(){
      setServiceBadge();
    }, 600);
  }else if(data[key]["status"] == "014003001"){
    $(".statusbadge").each(function(){
      var thisnodeid = $(this).attr("data-node-id");
      if(($(this).hasClass("badge-secondary") || $(this).hasClass("badge-success")) && $.inArray(data[key]["data"]["informed"],thisnodeid) == -1){
        $(this).removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger").addClass("badge-danger").html("Node not reachable");
      }
    });
  }
}
