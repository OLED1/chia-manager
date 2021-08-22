reloadTables();
initSysinfoRefresh();
initSysinfoNodeActions();

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

function initAndDrawRAMorSWAPChart(nodeid, type){
  var infodata = sysinfodata[nodeid];

  if(type == "ram"){
    var totalused = (parseInt(infodata["memory_total"]) - parseInt(infodata["memory_free"]));
    //var cached = parseInt(infodata["memory_cached"]) + parseInt(infodata["memory_sreclaimable"]) - parseInt(infodata["memory_shmem"]);
    var cached = parseInt(infodata["memory_cached"]);
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

  ctx = document.getElementById(type + "_chart_" + nodeid).getContext("2d");
  new Chart(ctx, {
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
}

function initAndDrawLoadChart(nodeid){
  var infodata = sysinfodata[nodeid];
  var data = [infodata["load_1min"], infodata["load_5min"], infodata["load_15min"]];

  ctx = document.getElementById("cpu_load_chart_" + nodeid).getContext("2d");
  new Chart(ctx, {
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
}

function messagesTrigger(data){
  var key = Object.keys(data);

  /*$('#all_node_sysinfo_container').load(frontend + "/sites/chia_infra_sysinfo/templates/cards.php");
  reloadTables();*/

  if(data[key]["status"] == 0){
  }
}
