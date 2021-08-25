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

function setWalletBadge(nodeid, status, message){
  var targetbadge = $("#servicestatus_" + nodeid);
  targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger");
  if(status == 0){
    targetbadge.addClass("badge-success");
  }else if(status == 1){
    targetbadge.addClass("badge-danger");
  }else if(status == 2){
    targetbadge.addClass("badge-secondary");
  }
  targetbadge.text(message);
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
        setWalletBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
      }, 1000);
    }else if(key == "walletServiceRestart"){
      setWalletBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "connectedNodesChanged"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
    }else if(key == "queryNodesServicesStatus"){
      $.each(data[key]["data"], function(nodeid, condata){
        if(condata["onlinestatus"] == 1){
          setFarmerBadge(nodeid, condata["onlinestatus"], "Node not reachable");
        }
      });
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
