initRefreshHarvesterInfos();
initRestartHarvesterService();
initAllDatatables();

setTimeout(function(){
  setServiceBadge();
}, 700);

$(function () {
  $('[data-toggle="tooltip"]').tooltip()
})

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaNodes, function(nodeid, farmdata) {
      queryHarvesterData(nodeid);
  });
});

function initAllDatatables(){
  $.each(chiaHarvesterData, function(nodeid, farmdata){
    initDataTable(nodeid);
  });
}

function initDataTable(nodeid){
  $("#plotstable_" + nodeid).DataTable();
}

function initRefreshHarvesterInfos(){
  $(".refreshHarvesterInfo").off("click");
  $(".refreshHarvesterInfo").on("click", function(e){
    e.preventDefault();
    queryHarvesterData($(this).attr("data-node-id"));
  });
}

function initRestartHarvesterService(){
  $(".restartHarvesterService").off("click");
  $(".restartHarvesterService").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var authhash = chiaNodes[nodeid]["nodeauthhash"];
    var dataforclient = {
      "nodeid" : nodeid,
      "authhash": authhash
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "restartHarvesterService", dataforclient);
  });
}

function queryHarvesterData(nodeid){
  var authhash = chiaNodes[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeinfo" : {
      "nodeid" : nodeid,
      "authhash": authhash
    }
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "queryHarvesterData", dataforclient);
}

function queryHarvesterStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaHarvesterData[nodeid]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", data);
}

function setServiceBadge(){
  $.each(services_states, function(nodeid, nodedata){
    if(nodedata === "undefined" || nodedata["onlinestatus"]["status"] == 0){
      statustext = "Node not reachable";
      statusicon = "badge-danger";
    }else if(nodedata["onlinestatus"]["status"] == 1){
      servicestate = nodedata["services"][4]["servicestate"];
      servicedesc =  nodedata["services"][4]["service_desc"];
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
    if(key == "updateHarvesterData"){
      var nodeid = data[key]["data"]["nodeid"];  
      var carddata = { "nodeid" : nodeid};
      $.get(frontend + "/sites/chia_harvester/templates/cards.php", carddata, function(response) {
        $('#harvestercontainer_' + nodeid).html(response);
        initRefreshHarvesterInfos();
        initRestartHarvesterService();
        initAllDatatables();
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
