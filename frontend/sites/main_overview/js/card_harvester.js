$("#refreshHarvesterInfo").off("click");
$("#refreshHarvesterInfo").on("click", function(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "queryHarvesterData", {});
});
