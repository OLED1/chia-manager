var ramswapcharts = {};
var loadCharts = {};

setTimeout(function(){
  setServiceBadge();
}, 700);

reloadTables();
reinitQueryAllButton();
initSysinfoRefresh();
initSysinfoNodeActions();

function reinitQueryAllButton(){
  $("#queryAllNodes").off("click");
  $("#queryAllNodes").on("click", function(){
    $.each(sysinfodata, function(nodeid, farmdata) {
      querySystemInfo(nodeid);
    });
  });
}


function reloadTables(){
  $.each(sysinfodata, function(nodeid, sysinfo){
    initAndDrawRAMorSWAPChart(nodeid, "ram");
    initAndDrawRAMorSWAPChart(nodeid, "swap");
    initAndDrawLoadChart(nodeid);
  });
}

function initSysinfoRefresh(){
  $(".sysinfo-refresh").off("click");
  $(".sysinfo-refresh").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-nodeid");
    querySystemInfo(nodeid)  });
}

function querySystemInfo(nodeid){
  var authhash = sysinfodata[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeinfo" : {
      "nodeid" : nodeid,
      "authhash": authhash
    }
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Infra_Sysinfo\\Chia_Infra_Sysinfo_Api", "Chia_Infra_Sysinfo_Api", "querySystemInfo", dataforclient);
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

function initAndDrawRAMorSWAPChart(nodeid, type){
  var infodata = sysinfodata[nodeid];

  if(type == "ram"){
    var memorytotal = parseInt(infodata["memory_total"]);
    var memoryfree = parseInt(infodata["memory_free"]) + parseInt(infodata["memory_buffers"]) + parseInt(infodata["memory_cached"]) - parseInt(infodata["memory_shared"]);
    var memoryused = ((memorytotal - memoryfree)/1024/1024/1024).toFixed(2);
    memoryfree = (memoryfree/1024/1024/1024).toFixed(2);

    var labels = ["RAM used", "RAM free"];
    var data = [memoryused, memoryfree];
  }else if(type == "swap"){
    var used = (parseInt(infodata["swap_total"]) - parseInt(infodata["swap_free"]))/1024/1024/1024;
    var free = parseInt(infodata["swap_free"])/1024/1024/1024;

    var labels = ["SWAP used", "SWAP free"];
    var data = [used.toFixed(2), free.toFixed(2)];
  }

  var target = $("#" + type + "_chart_" + nodeid);
  if(target.length > 0){
    if(!(nodeid in ramswapcharts)) ramswapcharts[nodeid] = {};
    else if((nodeid in ramswapcharts) && (type in ramswapcharts[nodeid])) ramswapcharts[nodeid][type].destroy();

    var thischartctx = document.getElementById(type + "_chart_" + nodeid).getContext("2d");
    ramswapcharts[nodeid][type] = new Chart(thischartctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: ['rgba(245, 189, 39, 0.5)','rgba(93, 211, 158, 0.5)'],
        }],
      },
      options: {
        maintainAspectRatio: false,
        responsive: true,
        resizeDelay: 50,
        legend: {
          display: false
        },
        plugins: {
          legend: {
            display: true,
            labels: {
                color: chartcolor
            }
          },
          tooltip: {
            callbacks: {
              label: function(context){
                return context.label + ": " + context.formattedValue + " GB";
              }
            }
          }
        }
      },
    });
  }
}

function initAndDrawLoadChart(nodeid){
  var infodata = sysinfodata[nodeid];
  var thischartctx = document.getElementById("cpu_load_chart_" + nodeid).getContext("2d");
  if(!(nodeid in loadCharts)) loadCharts[nodeid] = {};
  else if((nodeid in loadCharts)) loadCharts[nodeid]["load"].destroy();
  
  loadCharts[nodeid]["load"] = new Chart(thischartctx, {
    data: {
      labels: ["Load 1min","Load 5min", "Load 15min"],
      datasets: [{
        type: 'line',
        label: "Max load",
        borderColor: 'rgba(245, 39, 39, 1)',
        borderDash: [5, 5],
        borderwidth: 1,
        data: [(parseInt(infodata["cpu_count"]) * 2),(parseInt(infodata["cpu_count"]) * 2),(parseInt(infodata["cpu_count"]) * 2)],
        fill: true
      },{
        type: 'line',
        label: "Preferred max load",
        borderColor: 'rgba(245, 142, 39, 1)',
        borderDash: [5, 5],
        borderwidth: 1,
        data: [parseInt(infodata["cpu_count"]), parseInt(infodata["cpu_count"]), parseInt(infodata["cpu_count"])],
        fill: false
      },{
        type: 'bar',
        label: "Load",
        borderColor: ['rgba(111, 180, 255, 1)','rgba(51, 150, 255, 1)','rgba(0, 123, 255, 1)'],
        backgroundColor: ['rgba(111, 180, 255, 0.5)','rgba(51, 150, 255, 0.5)','rgba(0, 123, 255, 0.5)'],
        data: [infodata["load_1min"],infodata["load_5min"],infodata["load_15min"]],
        fill: true
      }]
    },
    options: {
      responsive: true,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      maintainAspectRatio: false,
      cutoutPercentage: 0,
      scales: {
        y: {
          beginAtZero: true,
            ticks : {
              color: chartcolor
          }
        },
        x: {
            ticks : {
                color: chartcolor
            },
            gridLines: {
              display: false,
              drawBorder: false
            }
        }
      },
      legend: {
        display: false
      },
      plugins: {
        legend: {
            display: true,
            labels: {
                color: chartcolor
            }
        }
    },
    }
  });
}

function setServiceBadge(){
  $.each(services_states, function(nodeid, nodedata){
    if(nodedata === "undefined" || nodedata["onlinestatus"]["status"] == 0){
      statustext = "Node not reachable";
      statusicon = "badge-danger";
    }else if(nodedata["onlinestatus"]["status"] == 1){
      statustext = "Node connected";
      statusicon = "badge-success";
    }

    $(".statusbadge[data-node-id='" + nodeid + "'").removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass(statusicon).text(statustext);
  });
}

function messagesTrigger(data){
  var key = Object.keys(data);

  console.log(data);

  if(data[key]["status"] == 0){
    if(key == "updateSystemInfo"){
      $.get(frontend + "/sites/chia_infra_sysinfo/templates/cards.php", {}, function(response) {
        $('#all_node_sysinfo_container').html(response);
        reloadTables();
        reinitQueryAllButton();
        initSysinfoRefresh();
        initSysinfoNodeActions();
      });
    }else if(key == "queryNodesServicesStatus" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
      }
    }
    setTimeout(function(){
      setServiceBadge();
    }, 600);
  }else if(data[key]["status"] == "014003001"){
    $(".statusbadge").each(function(){
      var thisnodeid = $(this).attr("data-node-id");
      if(($(this).hasClass("badge-secondary") || $(this).hasClass("badge-success")) && $.inArray(data[key]["data"]["informed"],thisnodeid) == -1){
        $(this).removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger").addClass("badge-danger").html("Node not reachable");
      }
    });
  }
}
