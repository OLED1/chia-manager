initRefreshHarvesterInfos();
initRestartHarvesterService();
initAllDatatables();

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaHarvesterData, function(nodeid, farmdata) {
      queryHarvesterData(nodeid);
  });
});

function initAllDatatables(){
  $.each(chiaHarvesterData, function(nodeid, farmdata) {
    initDataTable(nodeid);
  });
}

function initDataTable(nodeid){
  $("#plotstable_" + nodeid).DataTable();
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
    var dataforclient = {
      "nodeid" : nodeid,
      "authhash": authhash
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "restartHarvesterService", dataforclient);
  });
}

function queryHarvesterData(nodeid){
  var authhash = chiaHarvesterData[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeid" : nodeid,
    "authhash": authhash
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Harvester\\Chia_Harvester_Api", "Chia_Harvester_Api", "queryHarvesterData", dataforclient);
}

function queryHarvesterStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaHarvesterData[nodeid]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryHarvesterStatus", data);
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
      $('#harvesterinfocards').load(frontend + "/sites/chia_harvester/templates/cards.php");

      initRefreshHarvesterInfos();
      initRestartHarvesterService();
      initAllDatatables();
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
