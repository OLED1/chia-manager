var ramswapcharts = {};
var loadCharts = {};
var cpuUsageCharts = {};

setTimeout(function(){
  setServiceBadge();
}, 700);

reloadTables();
reinitQueryAllButton();
initSysinfoRefresh();
initSysinfoNodeActions();
initSetDownTime();

$(".datepicker").datetimepicker({
  format: 'Y-m-d H:i:s'
});

$('#alerting_service_and_type_select').multiselect({
  nonSelectedText: 'Alerting type and service',
  disabledText: "Not needed",
  includeSelectAllOption: true,
  numberDisplayed: 5,
  enableClickableOptGroups: true,
  enableCollapsibleOptGroups: true,
  collapseOptGroupsByDefault: true,
  disableIfEmpty: true,
  enableFiltering: true,
  includeResetOption: true,
  maxHeight: 500,
  onSelectAll: function(element, checked) {
    checkDowntimeInfosStated();
  },
  onChange: function(element, checked) {
    checkDowntimeInfosStated();
  },
  onDeselectAll: function() {
    checkDowntimeInfosStated();
  }
});

$(".downtime_input").on("change", function(){
  checkDowntimeInfosStated();
});

$(".downtime_input").on("input", function(){
  checkDowntimeInfosStated();
});

$("#downtime_save").on("click", function(){
  if(checkDowntimeInfosStated()){
    var nodeid = $("#saveDowntimeModal").attr("data-downtime-for");
    var downtime_values = getSetupDowntimeValues();
    
    $("#saveDowntimeNode").text(configureable_downtimes[nodeid]["hostname"] + " (" + nodeid + ")");
    $("#saveDowntimeTimeRange").text(downtime_values["time_from"] + " - " + downtime_values["time_to"]);
    $("#saveDowntimeComment").text(downtime_values["comment"]);
  
    if(downtime_values["downtime_type"] == 0){
      $("#saveDowntimeTargets").text("Whole node and all services");
      delete downtime_values["selected_services"];
    }else if(downtime_values["downtime_type"] == 1){
      $("#saveDowntimeTargets").html("Only specific services");
      var selected_elements = downtime_values["selected_services"];
      downtime_values["selected_services"] = selected_elements.map(function(i, el) {
        $("#saveDowntimeTargets").append("<li>" + configureable_downtimes[nodeid]["services"][$(el).attr("data-typeid")]["service_type_desc"] + " -> " + $(el).text() + "</li>");
        return { "type_id" : $(el).attr("data-typeid"), "data-service-target" : $(el).attr("data-real-service-target") };
      }).get();
    }
    $("#saveDowntimeModal").modal("show");
  
    $("#createAndSaveDowntime").off("click");
    $("#createAndSaveDowntime").on("click", function(){
      downtime_values["nodeid"] = nodeid;
      downtime_values["created_by"] = userID;
      sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "setUpNewDowntime", downtime_values);
    });
  }
});

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
    initAndDrawCPUUsageChart(nodeid);
  });
}

function initSysinfoRefresh(){
  $(".sysinfo-refresh").off("click");
  $(".sysinfo-refresh").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-nodeid");
    querySystemInfo(nodeid);
  });
}

function querySystemInfo(nodeid){
  var authhash = sysinfodata[nodeid]["node"]["nodeauthhash"];
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

function initSetDownTime(){
  $(".sysinfo-set-downtime").off("click");
  $(".sysinfo-set-downtime").on("click", function(){
    var nodeid = $(this).attr("data-nodeid");
    var sysinfo = sysinfodata[nodeid]["node"];

    $("#saveDowntimeModal").attr("data-downtime-for", nodeid);
    $("#downtimeModalHostname").text(sysinfo["hostname"] + " (ID: " + nodeid + ")");

    //Reset to default
    $("#downtime_select_type").text("Type").attr("data-selected","");
    var target_service_and_type_select = $('#alerting_service_and_type_select');
    target_service_and_type_select.children().remove();

    //Setup functions
    $("#downtime_select_type").parent().find(".dropdown-item").off("click");
    $("#downtime_select_type").parent().find(".dropdown-item").on("click", function(){
      $("#downtime_select_type").text($(this).text());
      downtime_type = $(this).attr("data-value");
      $("#downtime_select_type").attr("data-selected", downtime_type);

      if(downtime_type == 0){ //Downtime whole node
        target_service_and_type_select.children().remove();
      }else{
        if(nodeid in configureable_downtimes){
          $.each(configureable_downtimes[nodeid]["services"], function(type_id, found_types){
            target_service_and_type_select.append("<optgroup label='" + found_types["service_type_desc"] + "'>");
            $.each(found_types["configurable_services"], function(arrkey, configurable_services){
              target_service_and_type_select.append("<option data-typeid='" + type_id + "' data-real-service-target='" + configurable_services["real_service_target"] + "' value='" + type_id + "_" + arrkey + "'>" + configurable_services["service_target"] + "</option>");
            });
            target_service_and_type_select.append("</optgroup>");
          });

        }
      }
      
      $('#alerting_service_and_type_select').multiselect('rebuild');
      checkDowntimeInfosStated();
    });
    
    updateFoundDowntimesInModal(nodeid);
    $('#alerting_service_and_type_select').multiselect('rebuild');
    $("#setDownTimeModal").modal("show");
  });
}

function checkDowntimeInfosStated(){
  var downtime_values = getSetupDowntimeValues();
  var downtime_type = downtime_values["downtime_type"];
  var selected_services = downtime_values["selected_services"];
  var time_from_valid = moment(downtime_values["time_from"],"YYYY-MM-DD HH:mm:ss", true).isValid();
  var time_to_valid = moment(downtime_values["time_to"],"YYYY-MM-DD HH:mm:ss", true).isValid();
  var to_is_after_from = moment(downtime_values["time_to"]).isAfter(downtime_values["time_from"]);
  var comment_valid = (downtime_values["comment"].trim().length > 1 ? true : false);

  if((downtime_type == 0 || (downtime_type == 1 && selected_services.length > 0)) && time_from_valid && time_to_valid && to_is_after_from && comment_valid){
    $("#downtime_save").show();
    return true;
  }else{
    $("#downtime_save").hide();
    return false;
  }
}

function getSetupDowntimeValues(){
  return {
    "downtime_type" : $("#downtime_select_type").attr("data-selected"),
    "selected_services" : $("#alerting_service_and_type_select option:selected"),
    "time_from" : $("#downtime_input_from").val(),
    "time_to" : $("#downtime_input_to").val(),
    "comment" : $("#downtime_input_comment").val()
  };
}

function updateFoundDowntimesInModal(nodeid){
  if(nodeid in found_downtimes){
    console.log(found_downtimes[nodeid]);
    $.each(found_downtimes[nodeid], function(starttype, downtimes){
      var cardclass = "";
      var downtime_container_target = "";
      var downtime_count = 0;
      if(starttype == 0){
        cardclass = "alert-light";
        downtime_container_target = "downTimeModalExpired";
      }else if(starttype == 1){
        cardclass = "alert-success";
        downtime_container_target = "downTimeModalCurrent";
      }else if(starttype == 2){
        cardclass = "alert-primary";
        downtime_container_target = "downTimeModalUpcomming";
      }
      
      $("#" + downtime_container_target).children().remove();
      console.log(downtimes);
      $.each(downtimes, function(downtime_id, this_downtime){
        $("#" + downtime_container_target).append(
          "<div id='downtime_" + downtime_id + "' class='alert " + cardclass + "' role='alert'>" +
            "<p class='dowtime-short-desc-content'><b class='downtime-short-desc-title'>" + this_downtime["downtime_comment"] + "</b><br>" +
            "Created by: " + this_downtime["username"] + "<br>" +
            "Target: " + (this_downtime["downtime_type"] == 0 ? "Whole node" : this_downtime["service_desc"] + " (" + this_downtime["downtime_service_target"] + ")") + "<br>" +
            "Starts: " + this_downtime["downtime_from"] + "<br>" +
            "Ends: " + this_downtime["downtime_to"] + "</p>" +
          "</div>");
          downtime_count += 1;
      });
      $("#" + downtime_container_target + "Count").text("(" + downtime_count + ")");
    });
  }
}

function initAndDrawRAMorSWAPChart(nodeid, type){
  var infodata = sysinfodata[nodeid];

  if("memory" in infodata){
    if(type == "ram"){
      var memorytotal = parseInt(infodata["memory"]["ram"]["memory_total"]);
      var memoryfree = parseInt(infodata["memory"]["ram"]["memory_free"]) + parseInt(infodata["memory"]["ram"]["memory_buffers"]) + parseInt(infodata["memory"]["ram"]["memory_cached"]) - parseInt(infodata["memory"]["ram"]["memory_shared"]);
      var memoryused = ((memorytotal - memoryfree)/1024/1024/1024).toFixed(2);
      memoryfree = (memoryfree/1024/1024/1024).toFixed(2);
  
      var labels = ["RAM used", "RAM free"];
      var data = [memoryused, memoryfree];
    }else if(type == "swap"){
      var used = (parseInt(infodata["memory"]["swap"]["swap_total"]) - parseInt(infodata["memory"]["swap"]["swap_free"]))/1024/1024/1024;
      var free = parseInt(infodata["memory"]["swap"]["swap_free"])/1024/1024/1024;
  
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
}

function initAndDrawLoadChart(nodeid){
  var infodata = sysinfodata[nodeid];

  if("cpu" in infodata && "os" in infodata){
    if(infodata["os"]["os_type"] != "Linux") return;
    var thischartctx = document.getElementById("cpu_load_chart_" + nodeid).getContext("2d");
    if(!(nodeid in loadCharts)) loadCharts[nodeid] = {};
    else if((nodeid in loadCharts)) loadCharts[nodeid]["load"].destroy();

    var max_load = parseInt(infodata["cpu"]["info"]["cpu_count"]) * 2;
    var preferred_max_load = parseInt(infodata["cpu"]["info"]["cpu_count"]);
   
    loadCharts[nodeid]["load"] = new Chart(thischartctx, {
      data: {
        labels: ["Load 1min","Load 5min", "Load 15min"],
        datasets: [{
          type: 'line',
          label: "Max load",
          borderColor: 'rgba(245, 39, 39, 1)',
          borderDash: [5, 5],
          borderwidth: 1,
          data: [max_load,max_load,max_load],
          fill: true
        },{
          type: 'line',
          label: "Preferred max load",
          borderColor: 'rgba(245, 142, 39, 1)',
          borderDash: [5, 5],
          borderwidth: 1,
          data: [preferred_max_load, preferred_max_load, preferred_max_load],
          fill: false
        },{
          type: 'bar',
          label: "Load",
          borderColor: ['rgba(111, 180, 255, 1)','rgba(51, 150, 255, 1)','rgba(0, 123, 255, 1)'],
          backgroundColor: ['rgba(111, 180, 255, 0.5)','rgba(51, 150, 255, 0.5)','rgba(0, 123, 255, 0.5)'],
          data: [infodata["cpu"]["load"]["load_1_min"],infodata["cpu"]["load"]["load_5_min"],infodata["cpu"]["load"]["load_15_min"]],
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
}

function initAndDrawCPUUsageChart(nodeid){
  var infodata = sysinfodata[nodeid];

  if("cpu" in infodata){
    var thischartctx = document.getElementById("cpu_usage_chart_" + nodeid).getContext("2d");
    if(!(nodeid in cpuUsageCharts)) cpuUsageCharts[nodeid] = {};
    else if((nodeid in cpuUsageCharts)) cpuUsageCharts[nodeid].destroy();
  
    var labels = [];
    var data = [];
    var max_count = [];
    $.each(infodata["cpu"]["usage"]["usages"], function(corenumber, usage){
      labels.push("CPU " + corenumber);
      data.push(usage);
      max_count.push(100);
    });
      
    cpuUsageCharts[nodeid] = new Chart(thischartctx, {
      data: {
        labels: labels,
        datasets: [{
          type: 'line',
          label: "Max",
          borderColor: 'rgba(245, 39, 39, 1)',
          borderDash: [5, 5],
          borderwidth: 1,
          data: max_count,
          fill: true
        },{
          type: 'bar',
          label: "CPU usages",
          borderColor: 'rgba(111, 180, 255, 1)',
          backgroundColor: 'rgba(111, 180, 255, 0.5)',
          data: data,
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

  if(data[key]["status"] == 0){
    if(key == "queryNodesServicesStatus" || key == "updateSystemInfo" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      $.get(frontend + "/sites/chia_infra_sysinfo/templates/cards.php", {}, function(response) {
        $('#all_node_sysinfo_container').html(response);
        reloadTables();
        reinitQueryAllButton();
        initSysinfoRefresh();
        initSysinfoNodeActions();
        initSetDownTime();
        sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
      });
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
      }
    }else if(key == "setUpNewDowntime"){
      console.log(data);
      $("#setDownTimeModal").modal("hide");
      $("#saveDowntimeModal").modal("hide");
      if(data[key]["data"].length > 0){
        $.each(found_downtimes, function(nodeid, downtimes){
          found_downtimes[nodeid] = downtimes;
          updateFoundDowntimesInModal(nodeid); 
        });
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
  }else{
    console.log(data[key]["message"]);
  }
}
