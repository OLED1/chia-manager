queryAllWalletStatus();
initRefreshWalletInfo();
initRestartWalletService();

$("#queryAllNodes").on("click", function(){
  $.each(chiaWalletData, function(nodeid, nodedata) {
    queryWalletData(nodeid);
  });
});

function queryAllWalletStatus(){
  $.each(chiaWalletData, function(nodeid, nodedata) {
    queryWalletStatus(nodeid);
  });
}

function initRefreshWalletInfo(){
  $(".refreshWalletInfo").off("click");
  $(".refreshWalletInfo").on("click", function(e){
    e.preventDefault();
    queryWalletData($(this).attr("data-node-id"));
  });
}

function initRestartWalletService(){
  $(".restartWalletService").off("click");
  $(".restartWalletService").on("click", function(e){
    e.preventDefault();
    var node = $(this).attr("data-node-id");
    var authhash = chiaWalletData[walletid]["nodeauthhash"];
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "restartWalletService" : {
          "status" : 0,
          "message" : "Restart farmer service.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "restartWalletService", datafornode);
  });
}

function queryWalletData(nodeid){
  var authhash = chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"];
  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
      "queryWalletData" : {
        "status" : 0,
        "message" : "Query Wallet data.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryWalletData", datafornode);
}

function queryWalletStatus(nodeid){
  var authhash = chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"];

  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
      "queryWalletStatus" : {
        "status" : 0,
        "message" : "Query Wallet running status.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryWalletStatus", datafornode);
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
      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "getWalletData", {});
    }else if(key == "getWalletData"){
      $('#walletcontainer').load(frontend + "/sites/chia_wallet/templates/cards.php");

      initRefreshWalletInfo();
      queryAllWalletStatus();
      initRefreshWalletInfo();
      initRestartWalletService();
    }else if(key == "walletStatus"){
      setWalletBadge(data[key]["data"]);
    }else if(key == "walletServiceRestart"){
      setWalletBadge(data[key]["data"]);
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
