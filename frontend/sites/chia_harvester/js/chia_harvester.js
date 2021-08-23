initRefreshHarvesterInfos();
initRestartHarvesterService();
initAllHarvesterStatus();

$("#queryAllNodes").on("click", function(){
  $.each(chiaHarvesterData, function(nodeid, farmdata) {
      queryHarvesterData(nodeid);
  });
});

function initAllHarvesterStatus(){
  $.each(chiaHarvesterData, function(nodeid, farmdata) {
    queryHarvesterStatus(nodeid);
    initDataTable(nodeid);
  });
}

function initDataTable(nodeid){
  $("#plotstable_" + nodeid).DataTable();

  console.log($("#plotstable_" + nodeid));
}

function initRefreshHarvesterInfos(){
  $(".refreshHarvesterInfo").off("click");
  $(".refreshHarvesterInfo").on("click", function(e){
    e.preventDefault();
    queryHarvesterData($(this).attr("data-node-id"));
  });
}

function initRestartHarvesterService(){
  $(".restartHarvesterService").off("click");
  $(".restartHarvesterService").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var authhash = chiaHarvesterData[nodeid]["nodeauthhash"];
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "restartHarvesterService" : {
          "status" : 0,
          "message" : "Restart harvester service.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "restartHarvesterService", datafornode);
  });
}

function queryHarvesterData(nodeid){
  var authhash = chiaHarvesterData[nodeid]["nodeauthhash"];
  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
      "queryHarvesterData" : {
        "status" : 0,
        "message" : "Query Harvester data.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryHarvesterData", datafornode);
}

function queryHarvesterStatus(nodeid){
  var datafornode = {
    "nodeinfo":{
      "authhash": chiaHarvesterData[nodeid]["nodeauthhash"]
    },
    "data" : {
      "queryHarvesterStatus" : {
        "status" : 0,
        "message" : "Query Harvester running status.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryHarvesterStatus", datafornode);
}

function setHarvesterBadge(data){
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
    if(key == "updateHarvesterData"){
      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "getHarvesterData", {});
    }else if(key == "getHarvesterData"){
      $('#harvesterinfocards').load(frontend + "/sites/chia_harvester/templates/cards.php");

      initRefreshHarvesterInfos();
      initRestartHarvesterService();
      initAllHarvesterStatus();
    }else if(key == "harvesterStatus"){
      setHarvesterBadge(data[key]["data"]);
    }else if(key == "harvesterServiceRestart"){
      setHarvesterBadge(data[key]["data"]);
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
