$("#refreshWalletInfo").off("click");
$("#refreshWalletInfo").on("click", function(){
  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "queryWalletData", {});
});
