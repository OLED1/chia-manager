initRefreshHarvesterInfos();
initRestartHarvesterService();

$("#queryAllNodes").on("click", function(){
  $.each(chiaHarvesterData, function(nodeid, farmdata) {
      queryHarvesterData(nodeid);
  });
});

$.each(chiaHarvesterData, function(nodeid, farmdata) {
  queryHarvesterStatus(nodeid);
  initDataTable(nodeid);
});

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

function createHarvesterCards(data){
  $("#harvesterinfocards").children().remove();
  $.each(data, function(nodeid, harvesterinfos){
    var confplotdirs = "";
    var foundplots = "";
    $.each(harvesterinfos["plotdirs"], function(finalplotsdir, dirinfos){
      confplotdirs+=
        "<h4 class='small font-weight-bold'>" + (dirinfos["devname"] !== null ? dirinfos["devname"]+"&nbsp;->" : "") + "&nbsp;" + dirinfos["finalplotsdir"] + "&nbsp;(Size: " + (dirinfos["totalsize"] !== null ? dirinfos["totalsize"] : "UNKNOWN - Not mounted") + ")<span class='float-right'>" + (dirinfos["totalusedpercent"] !== null ? dirinfos["totalusedpercent"] : "0%") + "</span></h4>"+
        "<div class='progress mb-4'>"+
            "<div class='progress-bar bg-primary' role='progressbar' style='width: " + (dirinfos["totalusedpercent"] !== null ? dirinfos["totalusedpercent"] : "0%") + "' aria-valuenow='20' aria-valuemin='0' aria-valuemax='100'>" + dirinfos["totalused"] + " - " + dirinfos["plotcount"] + " Plots</div>"+
        "</div>";
        if("data" in dirinfos["foundplots"]){
          $.each(dirinfos["foundplots"]["data"], function(arrkey, plotdata){
            foundplots+=
            "<tr>" +
            "<td>" + plotdata["plotcreationdate"] + "</td>" +
            "<td>" + finalplotsdir+ "</td>" +
            "<td>" + plotdata["k_size"]+ "</td>" +
            "<td>" + plotdata["plot_key"]+ "</td>" +
            "<td>" + plotdata["pool_key"]+ "</td>" +
            "<td>" + plotdata["filename"]+ "</td>" +
            "<td>" + plotdata["status"]+ "</td>" +
            "</tr>";
          });
        }
    });
    $("#harvesterinfocards").append(
      "<div class='row'>" +
        "<div class='col'>" +
          "<div class='card shadow mb-4'>" +
            "<div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>" +
              "<h6 class='m-0 font-weight-bold text-primary'>Harvesterdata for host " + harvesterinfos['hostname'] + " with id " + nodeid +"&nbsp;<span id='servicestatus_" + nodeid + "' class='badge statusbadge badge-secondary'>Querying service status</span></h6>" +
              "<div class='dropdown no-arrow'>" +
                "<a id='dropdownMenuLink_" + nodeid +"' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>" +
                    "<i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>" +
                "</a>" +
                "<div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_" + nodeid + "'>" +
                    "<div class='dropdown-header'>Actions:</div>" +
                    "<a data-node-id='" + nodeid +"' class='dropdown-item refreshHarvesterInfo' href='#'>Refresh</a>" +
                    "<a data-node-id='" + nodeid +"' class='dropdown-item refreshHarvesterService' href='#'>Restart harvester service</a>" +
                "</div>" +
              "</div>" +
            "</div>" +
            "<div class='card-body'>" +
              "<div class='row'>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Configured plot directories</h5>" +
                      confplotdirs +
                    "</div>" +
                  "</div>" +
                "</div>" +
              "</div>" +
              "<div class='row'>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Found plots</h5>" +
                      "<div class='table-responsive'>" +
                        "<table class='table table-bordered' id='plotstable_" + nodeid +"' width='100%' cellspacing='0'>" +
                          "<thead>" +
                            "<tr>" +
                              "<th>Creation Date</th>" +
                              "<th>Plotdir</th>" +
                              "<th>K-Size</th>" +
                              "<th>Plot Key</th>" +
                              "<th>Pool Key</th>" +
                              "<th>Filename</th>" +
                              "<th>Status</th>" +
                            "</tr>" +
                          "</thead>" +
                          "<tbody>" +
                            foundplots +
                          "</tbody>" +
                          "<tfoot>" +
                            "<tr>" +
                              "<th>Creation Date</th>" +
                              "<th>Plotdir</th>" +
                              "<th>K-Size</th>" +
                              "<th>Plot Key</th>" +
                              "<th>Pool Key</th>" +
                              "<th>Filename</th>" +
                              "<th>Status</th>" +
                            "</tr>" +
                          "</tfoot>" +
                        "</table>" +
                      "</div>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
              "</div>" +
            "</div>" +
          "</div>" +
        "</div>" +
      "</div>");

    queryHarvesterStatus(nodeid);
    initDataTable(nodeid);
  });
  initRefreshHarvesterInfos();
  initRestartHarvesterService();
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
      chiaHarvesterData = data[key]["data"];
      createHarvesterCards(data[key]["data"]);
      initRefreshHarvesterInfos();
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
