setServiceCount();
queryAllNodeStates();

console.log(overviewInfos);

function setServiceCount(){
  var critServices = $("#sitecontent .badge-danger").length;
  var okServices = $("#sitecontent .badge-success").length;

  $("#ok-service-count").text(okServices);
  $("#crit-service-count").text(critServices);
}

function queryAllNodeStates(){
  $.each(overviewInfos["nodesinfos"], function(nodeid, nodeinfo){
    if(nodeinfo["nodetype"] != "webClient" && nodeinfo["nodetype"] != "backendClient"){
      $.each(nodeinfo["nodetype"].split(", "), function(arrkey, nodetype){
        var datafornode = {
          "nodeinfo":{
            "authhash": nodeinfo["nodeauthhash"]
          },
          "data" : {
          }
        }

        datafornode["data"]["query" + nodetype + "Status"] = {
          "status" : 0,
          "message" : "Query " + nodetype + " Status.",
          "data": {}
        };

        sendToWSS("messageSpecificNode", "", "", "query" + nodetype + "Status", datafornode);
      });
    }
  });
}

function queryNodeData(nodetype, nodeid){
  var authhash = overviewInfos["nodesinfos"][nodeid]["nodeauthhash"];
  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
    }
  }

  datafornode["data"]["query" + nodetype + "Data"] = {
    "status" : 0,
    "message" : "Query " + nodetype + " data.",
    "data": {}
  };

  sendToWSS("messageSpecificNode", "", "", "query" + nodetype + "Data", datafornode);
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

function updateWalletData(){
  console.log("UpdateWallet:");
  console.log(overviewInfos["walletinfos"]);

  var totalxch = 0.0;
  $.each(overviewInfos["walletinfos"], function(nodeid, nodedata){
    $.each(nodedata, function(walletid, walletdata){
      if($.isNumeric(walletdata["totalbalance"])) totalxch += parseFloat(walletdata["totalbalance"]);
    });
  });

  console.log(totalxch);
  var totalincurr = totalxch * parseFloat(overviewInfos["chia-overall"]["price_usd"]) * parseFloat(overviewInfos["currency"]["exchangerate"]);
  console.log(totalincurr);
}

function updateFarmData(){
  console.log(overviewInfos["farminfos"]);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "walletStatus"){
      setServiceBadge("Wallet", data[key]["data"]["data"], data[key]["data"]["status"]);
      queryNodeData("Wallet", data[key]["data"]["data"]);
    }else if(key == "farmerStatus"){
      setServiceBadge("Farmer", data[key]["data"]["data"], data[key]["data"]["status"]);
      queryNodeData("Farm", data[key]["data"]["data"]);
    }else if(key == "harvesterStatus"){
      setServiceBadge("Harvester", data[key]["data"]["data"], data[key]["data"]["status"]);
    }else if(key == "updateWalletData"){
      var nodeid = data[key]["data"]["nodeid"];
      var data = data[key]["data"]["data"][nodeid];
      overviewInfos["walletinfos"][nodeid] = data;

      $.each(data, function(walletid, walletdata){
        $("#syncstatus_" + nodeid + "_" + walletid).removeClass("badge-success").removeClass("badge-danger").addClass((walletdata["syncstatus"] == "Synced" ? "badge-success" : "badge-danger")).text(walletdata["syncstatus"] + " (Height: " + walletdata["walletheight"] + ")");
      });

      updateWalletData();
    }else if(key == "updateFarmData"){
      var nodeid = data[key]["data"]["nodeid"];
      var data = data[key]["data"]["data"][nodeid];
      overviewInfos["farminfos"][nodeid] = data;

      $("#farmingstatus_" + nodeid).removeClass("badge-success").removeClass("badge-danger").addClass((data["farming_status"] == "Farming" ? "badge-success" : "badge-danger")).text(data["farming_status"]);
      updateFarmData();
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
