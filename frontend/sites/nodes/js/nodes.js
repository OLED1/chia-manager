//INIT ALL CHART ARRAYS
var sysinfodata = {};
var charts = {};
charts["ram"] = {};
charts["ram"]["chart"] = {}
charts["ram"]["ctx"] = {};

charts["swap"] = {};
charts["swap"]["chart"] = {}
charts["swap"]["ctx"] = {};

charts["load"] = {};
charts["load"]["chart"] = {}
charts["load"]["ctx"] = {};

var configuredClients = $("#configuredClients").DataTable();

$("#chia-nodes-select").multiselect({
  disableIfEmpty: true,
  buttonWidth: '20%'
});

$('#nodetypes-options').multiselect({
  disableIfEmpty: true,
  buttonWidth: '100%'
});

recreateConfiguredClients();
initAllowConnect();
initDenyConnect();
initAllowIPChange();
initShowNodeInfo();

var updatechannels = {};

$("#queryAllInfoAllNodes").on("click", function(){
  sendToWSS("queryCronData", "", "", "", {});
});

$(".nodedefinition").on("change", function(){
  $('#nodetypes-options').multiselect("destroy");
  var nodedef = $(this).val();

  $("#nodetypes-options").children().remove();
  $.each(nodetypes["by-id"], function(typeid, typevalue){
    if(typevalue["nodetype"] == nodedef){
      $("#nodetypes-options").append("<option data-allowed-authtype=" + typevalue["allowed_authtype"] + " value='" + typevalue["code"] + "' >" + typevalue["description"] + "</option>");
    }
  });

  $("#authtype").text(getConallowString());
  $('#nodetypes-options').multiselect({
    buttonWidth: '100%',
    includeSelectAllOption: true,
    numberDisplayed: 5,
    onSelectAll: function(element, checked) {
      $("#acceptNodeRequest").removeAttr("disabled");
      $.each($('#nodetypes-options option:selected'), function(){
        $("#authtype").text(getAuthtypeString(nodetypes["by-id"][$(this).val()]["allowed_authtype"]));
        return;
      });
    },
    onChange: function(element, checked) {
      multiselectChanged();
    },
    onDeselectAll: function() {
      $("#acceptNodeRequest").attr("disabled","disabled");
      $("#authtype").text(getAuthtypeString(""));
    }
  });
});

function multiselectChanged(){
  var selectedOptions = $('#nodetypes-options option:selected');

  $.each(selectedOptions, function(){
    if($('#nodetypes-options option:selected').length > 0){
      $("#acceptNodeRequest").removeAttr("disabled");
      $("#authtype").text(getAuthtypeString(nodetypes["by-id"][$(this).val()]["allowed_authtype"]));
    }else{
      $("#acceptNodeRequest").attr("disabled","disabled");
      $("#authtype").text(getAuthtypeString(""));
    }
  });
}

$("#acceptNodeRequest").on("click", function(){
  if($('#nodetypes-options option:selected').length > 0){
    var nodearr = [];
    var confid = $("#acceptNodeRequestModal").attr("data-conf-id");
    $.each($('#nodetypes-options option:selected'), function(){
      nodearr.push($(this).val());
    });
    var data = {
      id : confid,
      nodetypes : nodearr.join()
    }
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "acceptNodeRequest", data);
  }else{
    showMessage(1, "No nodes are selected.");
  }
});

function recreateConfiguredClients(){
  var rows = configuredClients
  .rows()
  .remove()
  .draw();

  $.each(configuredNodes, function(arrkey, value){
    var rowNode = configuredClients
    .row.add( [ value["id"], value["nodetype"], value["nodeauthhash"], getAuthtypeString(value["authtype"]), getConallowString(value["conallow"]), value["hostname"], (value["scriptversion"] != null ? value["scriptversion"] : "-"), formatIP(value["ipaddress"], value["changedIP"], value["id"]), getClientCount(value["nodeauthhash"]), getButtonsConfClients(value["id"], value["conallow"], value["changeable"]) ] )
    .draw()
    .node().id = "confnode_" + value["id"];
  });
}

//Conallow = 0 Node not allowed to connect
//Conallow = 1 Node is allowed to connect
//Conallow = 2 Permission for connecting is pending
function getButtonsConfClients(id, conallow, changeable){
  var button = "";
  if((conallow == 0 || conallow == 2) && changeable == 1){
    button += "<button type='button' data-conf-id=" + id + " class='allow-connect btn btn-success'><i class='far fa-check-circle'></i></button>&nbsp";
  }
  if((conallow == 1 || conallow == 2) && changeable == 1){
    button += "<button type='button' data-conf-id=" + id + " class='decline-connect btn btn-danger'><i class='far fa-times-circle'></i></button>";
  }

  /*if(conallow == 1 && changeable == 1){
    button += "<button type='button' data-conf-id=" + id + " class='connection-info btn btn-warning'><i class='fas fa-info-circle'></i></button>";
  }*/

  return button;
}

function formatIP(ipaddress, changedIP, id){
  button = "";
  if(changedIP == ""){
    return ipaddress;
  }else{
    button += "<button type='button' data-conf-id=" + id + " class='ip-changed-allow btn btn-warning'><i class='far fa-check-circle'></i></button>&nbsp";
    return ipaddress + "<br>IP changed to " + changedIP + "<br>" + button;
  }
}

function getClientCount(currauthhash){
  var count = 0;

  $.each(activeSubscriptions, function(type, conections){
    $.each(conections, function(conid, details){
      if(details["authhash"] == currauthhash) count+=1;
    });
  });

  $.each(activeRequests, function(authhash, reqdata){
    if(authhash == currauthhash) count+=1;
  });

  return count;
}

function getConallowString(conallow){
  var constring = "";
  switch(conallow) {
    case "0":
      constring = "Declined";
      break;
    case "1":
      constring = "Allowed";
      break;
    case "2":
      constring = "Permission pending";
      break;
    default:
      constring = "Not known"
  }

  return constring;
}

function getAuthtypeString(authtype){
  var authtypestring = "";
  switch(authtype) {
    case "0":
      authtypestring = "Authtype not known. Please select";
      break;
    case "1":
      authtypestring = "Username and session";
      break;
    case "2":
      authtypestring = "IP address and authhash";
      break;
    case "3":
      authtypestring = "Authhash only (backendClient)";
      break;
    default:
      authtypestring = "Not known"
  }

  return authtypestring;
}

function initAllowConnect(){
  $(".allow-connect").off("click");
  $(".allow-connect").on("click", function(){
    var nodeid = $(this).attr("data-conf-id");
    var config = configuredNodes[nodeid];
    var authhash = config["nodeauthhash"];
    if(config["changedIP"] == ""){
      if(checkNodeConnected(nodeid, authhash)){
        if(configuredNodes[nodeid]["authtype"] > 0 && configuredNodes[nodeid]["authtype"] <= 2){
          if(configuredNodes[nodeid]["authtype"] == 1){
            $("#type_app").attr("checked", true);
          }else if(configuredNodes[nodeid]["authtype"] == 2){
            $("#type_chianode").attr("checked", true);
          }

          $(".nodedefinition").trigger("change");
          //getNodeTypesInt(configuredNodes[nodeid]["nodetype"]);

          $.each(configuredNodes[nodeid]["nodetype"].split(","), function(key, value){
            var id = nodetypes["by-desc"][value.trim()]["id"];
            $('#nodetypes-options').multiselect('select', [id]);
          });

          $("#acceptNodeRequest").removeAttr("disabled");
          $.each($('#nodetypes-options option:selected'), function(){
            $("#authtype").text(getAuthtypeString(nodetypes["by-id"][$(this).val()]["allowed_authtype"]));
            return;
          });
        }
        $("#acceptNodeRequestModal").attr("data-conf-id", nodeid);
        $("#acceptNodeRequestModal").attr("data-authhash", authhash);
        $("#acceptNodeRequestModal").modal("show");
      }else{
        showMessage(1, "This node is currently not connected.");
      }
    }else{
      showMessage(2, "Please accept IP Change first.");
    }
  });
}

function initDenyConnect(){
  $(".decline-connect").off("click");
  $(".decline-connect").on("click", function(){
    var nodeid = $(this).attr("data-conf-id");
    var config = configuredNodes[nodeid];
    var authhash = config["nodeauthhash"];

    $("#declinemodal-nodeid").text(nodeid);
    $("#declinemodal-authhash").text(authhash);

    $("#declineNodeRequestModal").attr("data-conf-id", nodeid);
    $("#declineNodeRequestModal").attr("data-authhash", authhash);
    $("#declineNodeRequestModal").modal("show");

    $("#decline-node").off("click");
    $("#decline-node").on("click", function(){
      data = {
        id: nodeid
      };

      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "declineNodeRequest", data);
    });
  });
}

function initAllowIPChange(){
  $(".ip-changed-allow").off("click");
  $(".ip-changed-allow").on("click", function(){
    var nodeid = $(this).attr("data-conf-id");
    var oldip = configuredNodes[nodeid]["ipaddress"];
    var newip = configuredNodes[nodeid]["changedIP"];

    $("#oldip").text(oldip);
    $("#newip").text(newip);

    $("#allowIPModal").modal("show");
    $("#saveIPChange").on("click", function(){
      var authhash = configuredNodes[nodeid]["nodeauthhash"];
      var dataforclient = {
        "nodeid" : nodeid
      }

      var datafornode = {
        "nodeinfo":{
          "authhash": authhash
        },
        "data" : {
          "acceptIPChange" : {
            "status" : 0,
            "message" : "IP Address change accepted.",
            "data": {}
          }
        }
      }

      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "acceptIPChange", dataforclient);
      sendToWSS("messageSpecificNode", "", "", "acceptIPChange", datafornode);
    });
  });
}

function initShowNodeInfo(){
  $('#chia-nodes-select').multiselect("destroy");
  $("#chia-nodes-select").children().remove();
  $.each(configuredNodes, function(nodeid, nodeinfos){
    if(jQuery.inArray("webClient", nodeinfos["nodetype"].split(",")) == -1 && jQuery.inArray("backendClient", nodeinfos["nodetype"].split(",")) == -1){
      $("#chia-nodes-select").append("<option value='" + nodeid + "' >" + nodeinfos["hostname"] + " (" + nodeinfos["nodetype"] + ")</option>");
    }
  });

  $('#chia-nodes-select').multiselect({
    buttonWidth: '20%',
    includeSelectAllOption: true,
    numberDisplayed: 5,
    onSelectAll: function(element, checked) {
      $.each($('#chia-nodes-select option:selected'), function(){
        sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getSystemInfo", { "nodeid": $(this).val() });
      });
    },
    onChange: function(element, checked) {
      if(checked && element.val() != undefined && element.val() > 0){
        sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getSystemInfo", { "nodeid": element.val() });
      }else{
        sysinfodata[element.val()] = {};
        charts[element.val()] = {};
        $("#container_" + element.val()).hide("slow").remove();
      }
    },
    onDeselectAll: function() {
      sysinfodata = {};
      charts = {};
      $(".sysinfocontainer").hide("slow").remove();
    }
  });

  $(".connection-info").off("click");
  $(".connection-info").on("click", function(){
    console.log($(this));
  });
}

function initSysinfoRefresh(){
  $(".sysinfo-refresh").off("click");
  $(".sysinfo-refresh").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-nodeid");
    var authhash = configuredNodes[nodeid]["nodeauthhash"];
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "querySystemInfo" : {
          "status" : 0,
          "message" : "Query Sysinfo data.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "querySystemInfo", datafornode);
  });
}

function initSysinfoNodeActions(){
  $(".sysinfo-node-actions").off("click");
  $(".sysinfo-node-actions").on("click", function(e){
    e.preventDefault();
    $("#nodeactionmodal").attr("data-nodeid", $(this).attr("data-nodeid"));
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getUpdateChannels", {});
    $("#updatenode").removeAttr("disabled");
    $("#action_node_log").children().remove();
  });
}

function checkNodeConnected(nodeid, authhash){
  var found = false;

  if(activeRequests[authhash] != undefined){
    found = true;
  }

  if(nodeid != undefined && configuredNodes[nodeid] != undefined && configuredNodes[nodeid]["nodeauthhash"] == authhash){
    found = true;
  }

  return found;
}

function drawSystemInfoCard(nodeid){
  var targetcontainer = $("#container_" + nodeid);
  if(targetcontainer.length == 0){
    drawContainer(nodeid);
  }else{
    drawFilesystemsChart(nodeid);
    initAndDrawRAMorSWAPChart(nodeid, "ram");
    initAndDrawRAMorSWAPChart(nodeid, "swap");
    initAndDrawLoadChart(nodeid);
    $("#querydate_" + nodeid).text(Object.keys(sysinfodata[nodeid])[Object.keys(sysinfodata[nodeid]).length-1]);
  }
  initSysinfoRefresh();
}

function drawContainer(nodeid){
  $("#all_node_sysinfo_container").append(
    "<div id='container_" + nodeid + "' class='sysinfocontainer card shadow mb-4'>" +
      "<div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>" +
          "<h6 class='m-0 font-weight-bold text-primary'>Systeminformation Node <bold>" + configuredNodes[nodeid]["hostname"] + "(" + configuredNodes[nodeid]["nodetype"] + ")</bold></h6>" +
          "<div class='dropdown no-arrow'>" +
              "<a class='dropdown-toggle' href='#' role='button' id='sysinfodropdown" + nodeid + "' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>" +
                  "<i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>" +
              "</a>" +
              "<div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='sysinfodropdown" + nodeid + "'>" +
                  "<div class='dropdown-header'>Actions:</div>" +
                  "<a class='sysinfo-refresh dropdown-item' data-nodeid='" + nodeid + "' href='#'>Refresh Data</a>" +
                  "<a class='sysinfo-node-actions dropdown-item' data-nodeid='" + nodeid + "' href='#'>Send Action</a>" +
              "</div>" +
          "</div>" +
      "</div>" +
      "<div class='card-body'>" +
        "<div class='row'>" +
          "<div id='filesystems_container_" + nodeid + "' class='col-6'>" +
          "</div>" +
          "<div id='ram_swap_container_" + nodeid + "' class='col-6'>" +
            drawRAMSWAPCharts(nodeid) +
          "</div>" +
          "<div class='row'>" +
            "<div id='cpu_load_container_" + nodeid + "' class='col-3'>" +
              drawLoadChart(nodeid) +
            "</div>" +
          "</div>" +
        "</div>" +
        "<div class='card-footer'>" +
          "Data queried at: <span id='querydate_" + nodeid + "'>" + (Object.keys(sysinfodata[nodeid])[0]) + "</span>" +
        "</div>" +
      "</div>" +
    "</div>"
  );

  drawFilesystemsChart(nodeid);
  initAndDrawRAMorSWAPChart(nodeid, "ram");
  initAndDrawRAMorSWAPChart(nodeid, "swap");
  initAndDrawLoadChart(nodeid);
  initSysinfoNodeActions();
}

function drawFilesystemsChart(nodeid){
  $("#filesystem_" + nodeid).remove();
  var filesystemchart = "<div class='card shadow mb-4'><div id='filesystem_" + nodeid + "' class='card-body'><h6 class='m-0 font-weight-bold text-primary'>Filesystems</h6>";
  var lastkey = Object.keys(sysinfodata[nodeid])[Object.keys(sysinfodata[nodeid]).length-1];
  var filesystem = jQuery.parseJSON(sysinfodata[nodeid][lastkey]["filesystem"]);

  $.each(filesystem, function(arrkey, filesysteminfo){
    filesystemchart +=
      "<h4 class='small font-weight-bold'>" + filesysteminfo[0] + " => " + filesysteminfo[5] + " (Size: " + filesysteminfo[1] + " Used: " + filesysteminfo[2] + " Available: " + filesysteminfo[3] + ")<span class='float-right'>" + filesysteminfo[4] + "</span></h4>" +
      "<div class='progress mb-4'>" +
        "<div class='progress-bar " + formatFilesystemProgressBar(filesysteminfo[4].split("%")[0]) + "' role='progressbar' style='width: " + filesysteminfo[4] + "' aria-valuenow='" + (filesysteminfo[4].split("%")[0]) + "' aria-valuemin='0' aria-valuemax='100'></div>" +
      "</div>";
  });

  filesystemchart += "</div></div>";
  $("#filesystems_container_" + nodeid).html(filesystemchart);
}

function formatFilesystemProgressBar(percent){
  if(percent <= 50){
    return "bg-success";
  }else if(percent <= 75){
    return "bg-warning";
  }
  return "bg-danger";
}

function drawRAMSWAPCharts(nodeid){
  var firstkey = Object.keys(sysinfodata[nodeid])[0];
  var memory_total = sysinfodata[nodeid][firstkey]["memory_total"];
  var swap_total = sysinfodata[nodeid][firstkey]["swap_total"]

  return "<div class='card shadow mb-4'>" +
      "<div class='card-body'>" +
        "<h6 class='m-0 font-weight-bold text-primary'>RAM and SWAP</h6>" +
        "<div class='row'>" +
          "<div class='col-6'>" +
            "<h7 class='m-0 font-weight-bold text-primary'>RAM (" + (parseInt(memory_total)/1024/1024).toFixed(2) + "GB)</h7>" +
            "<div class='chart-pie pt-4 pb-2'>" +
              "<canvas id='ram_chart_" + nodeid + "'></canvas>" +
            "</div>" +
            "<div class='mt-4 text-center small'>" +
              "<span class='mr-2'>" +
                "<i class='fas fa-circle ram-swap-free'></i> RAM free" +
              "</span>" +
              "<span class='mr-2'>" +
                "<i class='fas fa-circle ram-swap-used'></i> RAM used" +
              "</span>" +
            "</div>" +
            "</div>" +
            "<div class='col-6'>" +
              "<h7 class='m-0 font-weight-bold text-primary'>SWAP (" + (parseInt(swap_total)/1024/1024).toFixed(2) + "GB)</h7>" +
              "<div class='chart-pie pt-4 pb-2'>" +
                "<canvas id='swap_chart_" + nodeid + "'></canvas>" +
              "</div>" +
              "<div class='mt-4 text-center small'>" +
                "<span class='mr-2'>" +
                "  <i class='fas fa-circle ram-swap-free'></i> SWAP free" +
                "</span>" +
                "<span class='mr-2'>" +
                  "<i class='fas fa-circle ram-swap-used'></i> SWAP used" +
                "</span>" +
              "</div>" +
            "</div>" +
          "</div>" +
        "</div>" +
      "</div>" +
    "</div>";
}

function drawLoadChart(nodeid){
  var lastkey = Object.keys(sysinfodata[nodeid])[Object.keys(sysinfodata[nodeid]).length-1];
  var cpu_cores = sysinfodata[nodeid][lastkey]["cpu_cores"];
  var cpu_count = sysinfodata[nodeid][lastkey]["cpu_count"];
  var cpu_model = sysinfodata[nodeid][lastkey]["cpu_model"];

  return "<div class='card shadow mb-4'>" +
            "<div class='card-header py-3'>" +
              "<h7 class='m-0 font-weight-bold text-primary'>CPU " + cpu_model + " - " + cpu_count + " Cores, " + (cpu_cores*cpu_count) + " Threads</h7>" +
            "</div>" +
            "<div class='card-body'>" +
              "<h7 class='m-0 font-weight-bold text-primary'>CPU Load</h7>" +
              "<div class='chart-bar'>" +
                "<canvas id='cpu_load_chart_" + nodeid + "'></canvas>" +
              "</div>" +
            "</div>" +
          "</div>";
}

function initAndDrawRAMorSWAPChart(nodeid, type){
  var lastkey = Object.keys(sysinfodata[nodeid])[Object.keys(sysinfodata[nodeid]).length-1];
  var infodata = sysinfodata[nodeid][lastkey];

  if(type == "ram"){
    var totalused = (parseInt(infodata["memory_total"]) - parseInt(infodata["memory_free"]));
    var cached = parseInt(infodata["memory_cached"]) + parseInt(infodata["memory_sreclaimable"]) - parseInt(infodata["memory_shmem"]);
    var totalfree = cached + parseInt(infodata["memory_free"]);
    totalfree = totalfree/1024/1024;
    totalused = totalused/1024/1024;
    cached = cached/1024/1024;

    var labels = ["RAM used", "RAM free"];
    var data = [(totalused-cached).toFixed(2), totalfree.toFixed(2)];
  }else if(type == "swap"){
    var available = (parseInt(infodata["swap_total"]) - parseInt(infodata["swap_free"]))/1024/1024;
    var free = parseInt(infodata["swap_free"])/1024/1024;

    var labels = ["SWAP used", "SWAP free"];
    var data = [available.toFixed(2), free.toFixed(2)];
  }

  if(!(nodeid in charts[type]["ctx"])){
    charts[type]["ctx"][nodeid] = document.getElementById(type + "_chart_" + nodeid);
    charts[type]["chart"][nodeid] = new Chart(charts[type]["ctx"][nodeid], {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: ['#428AEC', '#26C59B'],
          hoverBackgroundColor: ['#4F42EC', '#26C54B'],
          hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
      },
      options: {
        maintainAspectRatio: false,
        tooltips: {
          backgroundColor: "rgb(255,255,255)",
          bodyFontColor: "#858796",
          borderColor: '#dddfeb',
          borderWidth: 1,
          xPadding: 15,
          yPadding: 15,
          displayColors: false,
          caretPadding: 10,
          callbacks: {
            label: function(tooltipItem, data){
              var label = data.labels[tooltipItem.index];
              var dataset = data.datasets[tooltipItem.datasetIndex];
              var currentValue = dataset.data[tooltipItem.index];
              return label + ": " + currentValue + "GB";
            }
          }
        },
        legend: {
          display: false
        },
        cutoutPercentage: 80,
      },
    });
  }else{
    charts[type]["chart"][nodeid].data.datasets.forEach((dataset) => {
      dataset.data = [];
    });
    charts[type]["chart"][nodeid].update();

    charts[type]["chart"][nodeid].data.datasets.forEach((dataset) => {
      dataset.data = data;
    });
    charts[type]["chart"][nodeid].update();
  }
}

function initAndDrawLoadChart(nodeid){
  var lastkey = Object.keys(sysinfodata[nodeid])[Object.keys(sysinfodata[nodeid]).length-1];
  var infodata = sysinfodata[nodeid][lastkey];
  var data = [infodata["load_1min"], infodata["load_5min"], infodata["load_15min"]];

  if(!(nodeid in charts["load"]["ctx"])){
    charts["load"]["ctx"][nodeid] = document.getElementById("cpu_load_chart_" + nodeid);
    charts["load"]["chart"][nodeid] = new Chart(charts["load"]["ctx"][nodeid], {
      type: 'bar',
      data: {
        labels: ["1min", "5min", "15min"],
        datasets: [{
          label: "Load",
          backgroundColor: ["#4e73df", "#4e73df", "#4e73df"],
          hoverBackgroundColor: ["#2e59d9", "#2e59d9", "#2e59d9"],
          borderColor: "#4e73df",
          data: data,
        }],
      },
      options: {
        maintainAspectRatio: false,
        layout: {
          padding: {
            left: 20,
            right: 25,
            top: 25,
            bottom: 0
          }
        },
        scales: {
          xAxes: [{
            gridLines: {
              display: false,
              drawBorder: false
            }
          }],
          yAxes: [{
            ticks: {
              min: 0,
              max: (Math.max.apply(Math,data).toFixed(2) * 1.1),
              maxTicksLimit: 2
            }
          }],
        },
        legend: {
          display: false
        },
        tooltips: {
          titleMarginBottom: 10,
          titleFontColor: '#6e707e',
          titleFontSize: 14,
          backgroundColor: "rgb(255,255,255)",
          bodyFontColor: "#858796",
          borderColor: '#dddfeb',
          borderWidth: 1,
          xPadding: 15,
          yPadding: 15,
          displayColors: false,
          caretPadding: 10,
        },
      }
    });
  }else{
    charts["load"]["chart"][nodeid].data.datasets.forEach((dataset) => {
      dataset.data = [];
    });
    charts["load"]["chart"][nodeid].update();

    charts["load"]["chart"][nodeid].data.datasets.forEach((dataset) => {
      dataset.data = data;
    });

    charts["load"]["chart"][nodeid].options.scales.yAxes[0].ticks.max = (Math.max.apply(Math,data).toFixed(2) * 1.1);
    charts["load"]["chart"][nodeid].update();
  }
}

function getStatusIcon(status){
  if(status == 0){
    var icon = "<i class='fas fa-check-circle' style='color: green;'></i>"
  }else if(status == 1){
    var icon = "<i class='fas fa-times-circle' style='color: red;'></i>";
  }else if(status == 2){
    var icon = "<i class='fas fa-sync' style='color: orange;'></i>";
  }

  return icon;
}

function messagesTrigger(data){
  var key = Object.keys(data);
  var reinit = true;

  if(data[key]["status"] == 0){
    if(key == "connectedNodesChanged"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      sendToWSS("getActiveSubscriptions", "", "", "", {});
      sendToWSS("getActiveRequests", "", "", "", {});
    }else if(key == "clientConnectionRequest"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      sendToWSS("getActiveRequests", "", "", "", {});
    }else if(key == "getConfiguredNodes"){
      configuredNodes = data[key]["data"];
    }else if(key == "getActiveRequests"){
      activeRequests = data[key]["data"];
    }else if(key == "getActiveSubscriptions"){
      activeSubscriptions = data[key]["data"];
    }else if(key == "acceptIPChange"){
      $("#allowIPModal").modal("hide");
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      sendToWSS("messageSpecificNode", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
    }else if(key == "acceptNodeRequest"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      data = {
        "nodeinfo" : {
          "authhash" : $("#acceptNodeRequestModal").attr("data-authhash")
        },
        "data" : data
      }
      sendToWSS("messageSpecificNode", "", "", "", data);
      $("#acceptNodeRequestModal").modal("hide");
    }else if(key == "declineNodeRequest"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      data = {
        "nodeinfo" : {
          "authhash" : $("#declineNodeRequestModal").attr("data-authhash")
        },
        "data" : data
      }

      sendToWSS("messageSpecificNode", "", "", "", data);
      $("#declineNodeRequestModal").modal("hide");
    }else if(key == "updateSystemInfo"){
      if(data[key]["data"]["nodeid"] in sysinfodata){
        sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getSystemInfo", { "nodeid": data[key]["data"]["nodeid"] });
      }
      reinit = false;
    }else if(key == "getSystemInfo"){
      if(data[key]["data"].length > 0){
        $.each(data[key]["data"], function(arrkey, systeminfo){
          if(!(systeminfo["nodeid"] in sysinfodata)){
            sysinfodata[systeminfo["nodeid"]] = {};
          }
          sysinfodata[systeminfo["nodeid"]][systeminfo["timestamp"]] = {};
          sysinfodata[systeminfo["nodeid"]][systeminfo["timestamp"]] = systeminfo;
          drawSystemInfoCard(systeminfo["nodeid"]);
        });
      }
      reinit = false;
    }else if(key == "getUpdateChannels"){
      updatechannels = data[key]["data"];

      $("#updatenode").prop("disabled",true);
      $("#selectedchannel").text("None");
      $("#selectedversion").text("None");
      $("#versionfilename").text("None");

      $("#updatechannels-modal").children().remove();
      $.each(updatechannels, function(channelname, versions){
        $("#updatechannels-modal").append("<button class='updatechannel-item dropdown-item' type='button'>" + channelname + "</button>");
      });
      $("#nodeactionmodal").modal("show");

      $(".updatechannel-item").off("click");
      $(".updatechannel-item").on("click", function(){
        $("#selectedchannel").text($(this).text());

        $("#updateversions-modal").children().remove();
        $.each(updatechannels[$(this).text()], function(version, link){
          $("#updateversions-modal").append("<button class='versionchannel-item dropdown-item' data-link='" + link + "' type='button'>" + version + "</button>");
          $("#updateversionMenu").prop("disabled", false);
        });

        $(".versionchannel-item").off("click");
        $(".versionchannel-item").on("click", function(){
          $("#selectedversion").text($(this).text());
          $("#versionfilename").text($(this).attr("data-link"));
          $("#updatenode").prop("disabled",false);
        });
      });

      $("#updatenode").off("click");
      $("#updatenode").on("click", function(){
        var id = $("#nodeactionmodal").attr("data-nodeid");
        var authhash = configuredNodes[id]["nodeauthhash"];
        var link = packageslink + $("#versionfilename").text();

        var data = {
          "nodeinfo":{
            "authhash":authhash
          },
          "data" : {
            "updateNode" : {
              "status" : 0,
              "message" : "Update node with ID: " + id,
              "data": {
                "link" : link,
                "version" : $("#selectedversion").text()
              }
            }
          }
        }

        $("#action_node_status").children().remove();
        sendToWSS("messageSpecificNode", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "updateNode", data);

        setTimeout(function(){
          var data = {
            "nodeinfo":{
              "authhash":authhash
            },
            "data" : {
              "nodeUpdateStatus" : {
                "status" : 0,
                "message" : "Get update status from node: " + id,
                "data": {}
              }
            }
          }
          sendToWSS("messageSpecificNode", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "nodeUpdateStatus", data);
        }, 1000);

        $("#updatenode").attr("disabled","disabled");
        $("#action_node_status").html("Processing&nbsp;" + getStatusIcon(2));
      });
    }else if(key == "nodeUpdateStatus"){
      var updateStatus = data[key]["data"];
      var modalnodeid = $("#nodeactionmodal").attr("data-nodeid");

      if(modalnodeid == data[key]["data"]["nodeid"]){
        $("#action_node_log").children().remove(),
        $.each(updateStatus["status"], function(step, data){
          if($.isNumeric(step)){
            $("#action_node_log").append("<p>" + data["message"] + "<br>Status: " + data["status"] + getStatusIcon(data["status"]) + "</p>");
          }
        });

        if(updateStatus["status"]["overall"] == 2){
          $("#action_node_status").html("Processing&nbsp;" + getStatusIcon(2));
          sendToWSS("messageSpecificNode", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "nodeUpdateStatus", data);
        }else if(updateStatus["status"]["overall"] == 0){
          $("#action_node_status").html("Finished&nbsp;" + getStatusIcon(0));
        }else if(updateStatus["status"]["overall"] == 1){
          $("#action_node_status").html("Finished&nbsp;" + getStatusIcon(1));
        }
      }
    }
    if(reinit){
      recreateConfiguredClients();
      initAllowConnect();
      initDenyConnect();
      initAllowIPChange();
      initShowNodeInfo();
    }
  }
}
