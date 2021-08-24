setServiceCount();

function refreshOverallInfo(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Overall\\Chia_Overall_Api", "Chia_Overall_Api", "queryOverallData", {});
}

function refreshWalletInfo(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "queryWalletData", {})
}

function refreshFarmInfo(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "queryFarmData", {});
}

function refreshHarvesterInfo(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "queryHarvesterData", {});
}

function setServiceCount(){
  var critServices = $("#sitecontent .badge-danger").length;
  var okServices = $("#sitecontent .badge-success").length;

  $("#ok-service-count").text(okServices);
  $("#crit-service-count").text(critServices);
}

function setServiceBadge(nodetype, nodeid, code){
  var targetelement = $("#servicestatus_" + nodetype.toLowerCase() + "_" + nodeid);
  targetelement.removeClass("badge-secondary").removeClass("badge-danger").removeClass("badge-success");
  if(code == 0){
    targetelement.addClass("badge-success").text(nodetype + " service running.");
  }else{
    targetelement.addClass("badge-danger").text(nodetype + " service not running.");
  }
}

function messagesTrigger(data){
  var key = Object.keys(data);

  console.log(data);

  if(data[key]["status"] == 0){
    if(key == "walletStatus"){
      setServiceBadge("Wallet", data[key]["data"]["data"], data[key]["data"]["status"]);
    }else if(key == "farmerStatus"){
      setServiceBadge("Farmer", data[key]["data"]["data"], data[key]["data"]["status"]);
    }else if(key == "harvesterStatus"){
      setServiceBadge("Harvester", data[key]["data"]["data"], data[key]["data"]["status"]);
    }else if(key == "updateWalletData"){
      $('#card-wallet').load(frontend + "/sites/main_overview/templates/card-wallet.php");
    }else if(key == "updateFarmData"){
      $('#card-farm').load(frontend + "/sites/main_overview/templates/card-farm.php");
    }else if(key == "updateHarvesterData"){
        $('#card-harvester').load(frontend + "/sites/main_overview/templates/card-harvester.php");
    }else if(key == "queryOverallData"){
      $('#card-overall').load(frontend + "/sites/main_overview/templates/card-overall.php");
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
