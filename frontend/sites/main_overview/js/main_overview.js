setServiceCount();

function setServiceCount(){
  var critServices = $("#services .badge-danger").length + $("#services .bg-danger").length;
  var warnServices = $("#services .badge-warning").length + $("#services .bg-warning").length;
  var okServices = $("#services .badge-success").length + $("#services .bg-success").length;

  $("#ok-service-count").text(okServices);
  $("#warn-service-count").text(warnServices);
  $("#crit-service-count").text(critServices);
}

function setServiceBadge(nodetype, nodeid, code, message){
  var targetelement = $("#servicestatus_" + nodetype.toLowerCase() + "_" + nodeid);
  targetelement.removeClass("badge-secondary").removeClass("badge-danger").removeClass("badge-success");

  if(code == 0){
    targetelement.addClass("badge-success").text(message);
  }else if(code == 1){
    targetelement.addClass("badge-danger").text(message);
  }else if(code == 2){
    targetelement.addClass("badge-secondary").text(message);
  }
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "walletStatus"){
      setServiceBadge("Wallet", data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "farmerStatus"){
      setServiceBadge("Farmer", data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "harvesterStatus"){
      setServiceBadge("Harvester", data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "updateWalletData"){
      $('#card-wallet').load(frontend + "/sites/main_overview/templates/card-wallet.php");
    }else if(key == "updateFarmData"){
      $('#card-farm').load(frontend + "/sites/main_overview/templates/card-farm.php");
    }else if(key == "updateHarvesterData"){
        $('#card-harvester').load(frontend + "/sites/main_overview/templates/card-harvester.php");
    }else if(key == "queryOverallData"){
      $('#card-overall').load(frontend + "/sites/main_overview/templates/card-overall.php");
    }else if(key == "connectedNodesChanged"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
    }else if(key == "queryNodesServicesStatus"){
      $.each(data[key]["data"], function(nodeid, condata){
        if(condata["onlinestatus"] == 1){
          setServiceBadge("Wallet", nodeid, condata["walletstatus"], "Node not reachable");
          setServiceBadge("Farmer", nodeid, condata["farmerstatus"], "Node not reachable");
          setServiceBadge("Harvester", nodeid, condata["harvesterstatus"], "Node not reachable");
        }
      });
    }else if(key == "checkUpdatesAndChannels"){
      $('#card-system').load(frontend + "/sites/main_overview/templates/card-system.php");
    }
  }else{
    if(data[key]["status"] == "014003001"){
      $.each($(".nodestatus"), function(){
        if(jQuery.inArray($(this).attr("data-nodeid"),data[key]["data"]) && !$(this).hasClass("badge-danger")){
          $(this).removeClass("badge-secondary").removeClass("badge-success").addClass("badge-danger").text("Node not connected");
        }
      });
    }
  }

  setServiceCount();
}

setTimeout(function(){
  if($(".nodestatus.badge-secondary").length > 0){
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  }
}, 10000);
