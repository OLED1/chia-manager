setServiceCount();
reInitRefreshOverallInfo();
reInitRefreshFarmInfo();
reInitRefreshHarvesterInfo();
reInitRefreshSystemInfo();
reInitRefreshWalletInfo();

setTimeout(function(){
  setServiceBadge();
}, 600);

function reInitRefreshOverallInfo(){
  $("#refreshOverallInfo").off("click");
  $("#refreshOverallInfo").on("click", function(){
    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Overall\\Chia_Overall_Api", "Chia_Overall_Api", "queryOverallData", {});
  });   
}

function reInitRefreshFarmInfo(){
  $("#refreshFarmInfo").off("click");
  $("#refreshFarmInfo").on("click", function(){
    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "queryFarmData", {});
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  });
}

function reInitRefreshHarvesterInfo(){
  $("#refreshHarvesterInfo").off("click");
  $("#refreshHarvesterInfo").on("click", function(){
    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "queryHarvesterData", {});
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  });
}

function reInitRefreshSystemInfo(){
  $("#refreshSystemInfo").off("click");
  $("#refreshSystemInfo").on("click", function(){
    $("#card-system").load(frontend + "/sites/main_overview/templates/card-system.php");
  });
}

function reInitRefreshWalletInfo(){
  $("#refreshWalletInfo").off("click");
  $("#refreshWalletInfo").on("click", function(){
    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "queryWalletData", {});
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  });
}


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

function setServiceBadge(){
  $.each(services_states, function(nodeid, nodedata){
    if(nodedata === "undefined" || nodedata["onlinestatus"]["status"] == 0){
      statustext = "Node not reachable";
      statusicon = "badge-danger";
  
      $(".nodestatus[data-nodeid='" + nodeid + "']").removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass("badge-danger").text("Node not reachable");
    }else if(nodedata["onlinestatus"]["status"] == 1){
      $.each(nodedata["services"], function(serviceid, servicestates){
        servicestate = servicestates["servicestate"];
        servicedesc = servicestates["service_desc"];
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
        $("#servicestatus_" + servicestates["service_desc"].toLowerCase() + "_" + nodeid).removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass(statusicon).text(statustext);
      });
    }
  });
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateWalletData"){
      var card_data = { "services_states" : services_states };
      $.get(frontend + "/sites/main_overview/templates/card-wallet.php", card_data, function(response) {
        $('#card-wallet').html(response);
        reInitRefreshWalletInfo();
      });
    }else if(key == "updateFarmData"){
      var card_data = { "services_states" : services_states };
      $.get(frontend + "/sites/main_overview/templates/card-farm.php", card_data, function(response) {
        $('#card-farm').html(response);
        reInitRefreshFarmInfo();
      });
    }else if(key == "updateHarvesterData"){
      var card_data = { "services_states" : services_states };
      $.get(frontend + "/sites/main_overview/templates/card-harvester.php", card_data, function(response) {
        $('#card-harvester').html(response);
        reInitRefreshHarvesterInfo();
      });
    }else if(key == "queryOverallData"){
      $.get(frontend + "/sites/main_overview/templates/card-overall.php", {}, function(response) {
        $('#card-overall').html(response);
        reInitRefreshOverallInfo();
      });
      /*$.get(frontend + "/sites/main_overview/templates/card-overall-luca.php", {}, function(response) {
        $('#card-overall-luca').html(response);
      });*/
    }else if(key == "checkUpdatesAndChannels"){
      $.get(frontend + "/sites/main_overview/templates/card-system.php", {}, function(response) {
        $('#card-system').html(response);
        reInitRefreshSystemInfo();
      });
    }else if(key == "queryNodesServicesStatus" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
        setTimeout(function(){
          setServiceBadge();
        }, 600);
      }
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
