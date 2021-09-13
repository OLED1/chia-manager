$("#refreshFarmInfo").off("click");
$("#refreshFarmInfo").on("click", function(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm", "queryFarmData", {});
});
