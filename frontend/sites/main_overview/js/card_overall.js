$("#refreshOverallInfo").off("click");
$("#refreshOverallInfo").on("click", function(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Overall\\Chia_Overall_Api", "Chia_Overall_Api", "queryOverallData", {});
});
