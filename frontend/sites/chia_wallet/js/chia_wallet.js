initRefreshWalletInfo();
initRestartWalletService();

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaWalletData, function(nodeid, farmdata) {
      queryWalletData(nodeid);
  });
});

function queryWalletData(nodeid){
  var walletid = $(this).attr("data-wallet-id");
  var authhash = chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"];

  var dataforclient = {
    "nodeid" : nodeid,
    "authhash": authhash
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "queryWalletData", dataforclient);
}

function initRefreshWalletInfo(){
  $(".refreshWalletInfo").off("click");
  $(".refreshWalletInfo").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    queryWalletData(nodeid);
  });
}

function initRestartWalletService(){
  $(".restartWalletService").off("click");
  $(".restartWalletService").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var walletid = $(this).attr("data-wallet-id");
    var authhash = chiaWalletData[nodeid][walletid]["nodeauthhash"];

    var dataforclient = {
      "nodeid" : nodeid,
      "authhash": authhash
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "restartWalletService", dataforclient);
  });
}

function queryWalletStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryWalletStatus", data);
}

function setWalletBadge(data){
  var targetbadge = $("#servicestatus_" + data["data"]);

  targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger");
  if(data["status"] == 0){
    targetbadge.addClass("badge-success");
  }else{
    targetbadge.addClass("badge-danger");
  }
  targetbadge.html(data["message"]);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateWalletData"){
      $('#walletcontainer').load(frontend + "/sites/chia_wallet/templates/cards.php");

      initRefreshWalletInfo();
      initRefreshWalletInfo();
      initRestartWalletService();
    }else if(key == "walletStatus"){
      setTimeout(function(){
        setWalletBadge(data[key]["data"]);
      }, 1000);
    }else if(key == "walletServiceRestart"){
      setWalletBadge(data[key]["data"]["data"]);
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
