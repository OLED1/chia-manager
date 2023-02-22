/* ------------------------------------------ */
/* Overall functions and variable definitions */
/* ------------------------------------------ */

var ramswapcharts = {};
var loadCharts = {};
var cpuUsageCharts = {};

setTimeout(function(){
  setServiceBadge();
}, 700);

reinitAll();

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

function reinitAll(){
  reloadTables();
  reinitQueryAllButton();
  initSysinfoRefresh();
  initSysinfoNodeActions();
  initSetDownTime();
  initEditMonitoredServices();
  initRebuildTooltips();
  initQuickCheckboxes();
  initNodeTabChange();
}

function reinitQueryAllButton(){
  $("#queryAllNodes").off("click");
  $("#queryAllNodes").on("click", function(){
    $.each(sysinfodata, function(nodeid, farmdata) {
      querySystemInfo(nodeid);
    });
  });
}

function initRebuildTooltips(){
  $(".badge[data-toggle='tooltip']").tooltip();
}

function initNodeTabChange(){
  $(".node-tab").off("click");
  $(".node-tab").on("click", function(){
    $(".quick_select_all").prop("checked", false);
    $(".quick_select_service").prop("checked", false);
    $("#quick_action_show_info").show();
    $("#quick_action_set_action").hide();
    $(".quick_option_input").attr("disabled","disabled");
    $(".quick_option_radio").prop("checked", false).parent().removeClass("active");
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

/* ------------------------------------------ */
/* All functions regarding Downtimes */
/* ------------------------------------------ */

$("#edit_downtime_services_select").multiselect({
  nonSelectedText: 'Select downtime(s) to edit',
  includeSelectAllOption: true,
  enableFiltering: true,
  includeResetOption: true,
  maxHeight: 500,
  onSelectAll: function(element, checked) {
    editDowntimesCreateDetailList();
  },
  onChange: function(element, checked) {
    editDowntimesCreateDetailList();
  },
  onDeselectAll: function() {
    editDowntimesCreateDetailList();
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

$(".edit-downtimes").on("click", function(){
  var node_id = $("#setDownTimeModal").attr("data-node-id");
  var starttype = $(this).attr("data-starttype");

  $("#edit_downtime_services_select").children().remove();
  if(node_id in found_downtimes && starttype in found_downtimes[node_id]){
    $.each(found_downtimes[node_id][starttype], function(downtime_id, this_downtime){
      $("#edit_downtime_services_select").append("<option value='" + downtime_id + "'>" + this_downtime["downtime_comment"] + " (" + this_downtime["service_desc"] + "/" + this_downtime["downtime_service_target"] + ")</option>");
    });
  }

  if($("#edit_downtime_services_select").children().length > 0){
    $("#editDowntimeServicesDetails").children().remove();
    $("#edit_downtime_comment_input").val("");
    $("#edit_downtime_from_input").val("");
    $("#edit_downtime_from_to").val("");
    $("#remove_selected_downtimes").prop('checked', false);

    $('#edit_downtime_services_select').multiselect('rebuild');
    $("#editDowntimeModal").attr("data-edit-starttype", starttype).modal("show");
  }else{
    showMessage(1, "No downtimes to edit.");
  }
});

$("#saveDowntimeEditChanges").on("click", function(){
  var selected_downtimes = $("#edit_downtime_services_select option:selected");
  var edit_downtime_comment = $("#edit_downtime_comment_input").val();
  var edit_downtime_from = $("#edit_downtime_from_input").val();
  var edit_downtime_to = $("#edit_downtime_to_input").val();
  var time_from_valid = moment(edit_downtime_from,"YYYY-MM-DD HH:mm:ss", true).isValid();
  var time_to_valid = moment(edit_downtime_to,"YYYY-MM-DD HH:mm:ss", true).isValid();
  var to_is_after_from = moment(edit_downtime_to).isAfter(edit_downtime_from);
  var remove_selected = $("#remove_selected_downtimes").is(':checked');

  if(selected_downtimes.length > 0 && edit_downtime_comment.trim().length > 0 || (edit_downtime_from.trim().length > 0 && time_from_valid) || (edit_downtime_to.trim().length > 0 && time_to_valid) || (selected_downtimes.length > 0 && remove_selected)){
    if((edit_downtime_from.trim().length > 0 && time_from_valid) && (edit_downtime_to.trim().length > 0 && time_to_valid)){
      if(!to_is_after_from){
        showMessage(0, "Date 'to' must be after date 'from'.");  
        return;
      }
    }
    $("#saveEditDowntimes").modal("show");
  }else{
    showMessage(0, "Nothing to save.");
  }
});

$("#remove_selected_downtimes").on("click", function(){
  if($(this).is(':checked')){
    $(".edit-downtime-input").prop("disabled", true);
  }else{
    $(".edit-downtime-input").prop("disabled", false);
  }
});

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
    $("#setDownTimeModal").attr("data-node-id", nodeid).modal("show");
  });
}

function editDowntimesCreateDetailList(){
  var nodeid = $("#setDownTimeModal").attr("data-node-id");
  var starttype = $("#editDowntimeModal").attr("data-edit-starttype");
  var target_select = $("#edit_downtime_services_select option:selected");

  $("#editDowntimeServicesDetails").children().remove();
  target_select.map(function(i, el) {
    var this_downtime_info = found_downtimes[nodeid][starttype][$(el).val()];

    $("#editDowntimeServicesDetails").append(
      "<div class='alert alert-info' role='alert'>" +
        "<b>" + this_downtime_info["downtime_comment"] + "</b><br>" +
        this_downtime_info["service_desc"] + " / " + this_downtime_info["downtime_service_target"] + "<br>" +
        this_downtime_info["downtime_from"] + " - " + this_downtime_info["downtime_to"] + "<br>" +
      "</div>")
    }).get();

  $("#saveEditedAndSelectedDowntimes").off("click");
  $("#saveEditedAndSelectedDowntimes").on("click", function(){
    var remove_downtimes = $("#remove_selected_downtimes").is(':checked');
    var edit_downtime_comment = $("#edit_downtime_comment_input").val();
    var edit_downtime_from = $("#edit_downtime_from_input").val();
    var edit_downtime_to = $("#edit_downtime_to_input").val();
    var ids_to_edit = $("#edit_downtime_services_select option:selected").map(function(i, el) {
      return $(el).val();
    }).get();

    data_to_save = {
      "ids_to_edit" : ids_to_edit,
      "edit_downtime_comment" : edit_downtime_comment,
      "edit_downtime_from" : edit_downtime_from,
      "edit_downtime_to" : edit_downtime_to,
      "remove_downtimes" : remove_downtimes   
    };

    sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "editDowntimes", data_to_save);
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
  $.each([0, 1, 2], function(starttype){
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

    if(nodeid in found_downtimes && starttype in found_downtimes[nodeid]){
      $.each(found_downtimes[nodeid][starttype], function(downtime_id, this_downtime){
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
    }

    if(downtime_count == 0){
      $("#" + downtime_container_target).append(
        "<div class='alert " + cardclass + "' role='alert'>" +
          'There are no downtimes to show.' +
        "</div>");
    }

    $("#" + downtime_container_target + "Count").text("(" + downtime_count + ")");
  });
}

/* ------------------------------------------ */
/* Functions regarding quick options */
/* ------------------------------------------ */

$(".quick_option_radio").on("click", function(){
  var type = $(this).val();

  if(type == "dt"){
    $("#quick_option_dt_time_from").show();
    $("#quick_option_dt_time_to").show();
  }else{
    $("#quick_option_dt_time_from").hide();
    $("#quick_option_dt_time_to").hide();
  }

  $(".quick_option_input").removeAttr("disabled");
});

$(".quick_option_input").on("input", function(){
  checkQuickOptionsFilled();
});

$(".quick_option_input").on("change", function(){
  checkQuickOptionsFilled();
});

$(".quick_option_radio").on("click", function(){
  checkQuickOptionsFilled();
});

$("#save_quick_action").on("click", function(){
  if(checkQuickOptionsFilled()){
    var selected_type = $("input[name='quick_option_type_radio']:checked").val();
    var selected_services = $(".quick_select_service:visible:checked");
    var node_id = $(".quick_select_all:visible").closest(".node-details-pane").attr("data-node-id");
    var user_id = userID;

    $("#quickOptionDowntimeIcon").hide();
    $("#quickOptionAcknowledgeIcon").hide();
    $("#quickOptionDTOptions").hide();
    $("#quickOptionHost").text(sysinfodata[node_id]["node"]["hostname"]);
    $("#quickOptionDTACKMessage").text($("#quick_option_comment_input").val());

    var function_to_call = "";
    var class_to_call = "";
    if(selected_type == "dt"){
      $("#quickOptionDowntimeIcon").show();
      $(".quickOptionCreateType").text("downtime");
      $("#quickOptionDTOptions").show();
      $("#quickOptionDTFrom").text($("#quick_option_dt_time_from").val());
      $("#quickOptionDTTo").text($("#quick_option_dt_time_to").val());
      $("#quickOptionSelectedServicesCount").text(selected_services.length);
      $('input[name="quickOptionsdtRange"]').prop("checked", false).last().prop("checked", true);
      function_to_call = "setUpNewDowntime";
    }else if(selected_type == "ack"){
      $("#quickOptionAcknowledgeIcon").show();
      $(".quickOptionCreateType").text("acknowledgement");
      function_to_call = "setUpNewAcknowledgement";
    }

    $("#quickOptionsSaveDTorACK").modal("show");
    $("#saveSetupQuickOption").off("click");
    $("#saveSetupQuickOption").on("click", function(){    
      quick_option_values = getSetupQuickOptionValues();
      quick_option_values["nodeid"] = node_id;
      quick_option_values["created_by"] = user_id;

      var selected_elements = quick_option_values["selected_services"];
      quick_option_values["selected_services"] = selected_elements.map(function(i, el) {
        return { "type_id" : $(el).attr("data-service-type"), "data-service-target" : configureable_downtimes[node_id]["services"][$(el).attr("data-service-type")]["configurable_services"][$(el).val()]["real_service_target"] };
      }).get();
     
      sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", function_to_call, quick_option_values);
    });
  }else{
    showMessage(1, "No data to save.");  
  }
});

function checkQuickOptionsFilled(){
  var all_stated = true;
  $(".quick_option_input:visible").each(function(){
    if($(this).val().trim().length == 0){
      all_stated = false;
    }
  });

  if(all_stated) $("#save_quick_action").removeClass("btn-outline-success").addClass("btn-success").removeAttr("disabled");
  else $("#save_quick_action").removeClass("btn-success").addClass("btn-outline-success").attr("disabled","disabled");

  return all_stated;
}

function initQuickCheckboxes(){
  $(".quick_select_all").off("click");
  $(".quick_select_all").on("click", function(){
    var node_tab = $(this).closest(".node-details-pane");
    if($(this).is(":checked")) node_tab.find(".quick_select_service").prop("checked", true);
    else node_tab.find(".quick_select_service").prop("checked", false);
    checkQuickSelectCount(node_tab);
  });

  $(".quick_select_service").off("click");
  $(".quick_select_service").on("click", function(){
    var node_tab =  $(this).closest(".node-details-pane");
    checkQuickSelectCount(node_tab);
  });
}

function checkQuickSelectCount(node_tab){
  var selected_quick_options = node_tab.find(".quick_select_service:checked");

  if(selected_quick_options.length > 0){
    $("#quick_action_show_info").hide();
    $("#quick_action_set_action").show();
  }else{
    $("#quick_action_show_info").show();
    $("#quick_action_set_action").hide();
  }
}

function checkQuickOptionsInfosStated(){
  var quick_options = getSetupQuickOptionValues();
  var comment_valid = (quick_options["comment"].trim().length > 1 ? true : false);
  var downtime_type = quick_options["downtime_type"];
  var selected_services = quick_options["selected_services"];
  var time_from_valid = moment(quick_options["time_from"],"YYYY-MM-DD HH:mm:ss", true).isValid();
  var time_to_valid = moment(quick_options["time_to"],"YYYY-MM-DD HH:mm:ss", true).isValid();
  var to_is_after_from = moment(quick_options["time_to"]).isAfter(quick_options["time_from"]);

  if(quick_options["dt_or_ack"] == "ack" && comment_valid){
    return true;
  }else if(quick_options["dt_or_ack"] == "dt" && (downtime_type == 0 || (downtime_type == 1 && selected_services.length > 0)) && time_from_valid && time_to_valid && to_is_after_from && comment_valid){
    return true;
  }else{
    return false;
  }
}

function getSetupQuickOptionValues(){
  return {
    "dt_or_ack" : $("input[name='quick_option_type_radio']:checked").val(),
    "downtime_type" : $("input[name='quickOptionsdtRange']:checked").val(),
    "selected_services" : $(".quick_select_service:visible:checked"),
    "time_from" : $("#quick_option_dt_time_from").val(),
    "time_to" : $("#quick_option_dt_time_to").val(),
    "comment" : $("#quick_option_comment_input").val()
  };
}

/* ------------------------------------------ */
/* Functions regarding services */
/* ------------------------------------------ */

function initEditMonitoredServices(){
  $(".sysinfo-edit-services").off("click");
  $(".sysinfo-edit-services").on("click", function(){
    var nodeid = $(this).attr("data-nodeid");
    recreateMonitoredServicesLists(nodeid);
  });
}

function recreateMonitoredServicesLists(nodeid){
  var monitored_target = $("#monitored_services");
  var unmonitored_target = $("#unmonitored_services");

  monitored_target.children().remove();
  unmonitored_target.children().remove();
  if(nodeid in monitored_services){
    $.each(monitored_services[nodeid]["services"], function(type_id, found_types){
      $.each(found_types["configurable_services"], function(arrkey, configurable_services){
        var service = 
          "<div id='service_" + configurable_services["service_id"] + "' class='input-group input-group-sm mb-1'>" +
            "<div class='input-group-prepend' style='width: 90%;'>" +
              "<span class='input-group-text' style='width: 40%;'>" + found_types["service_type_desc"] + "</span>" +
              "<span class='input-group-text' data-toggle='tooltip' data-placement='top' title='" + configurable_services["service_target"] + "' style='width: 60%;'>" + (configurable_services["service_target"].length > 35 ? jQuery.trim(configurable_services["service_target"]).substring(0, 35) + "..." : configurable_services["service_target"]) + "</span>" +
              "</div>";

        if(configurable_services["monitor"] == 1){
          service += "<button type='button' data-node-id='" + nodeid + "' data-service-id='" + configurable_services["service_id"] + "' data-type-id='" + type_id + "' class='btn btn-danger wsbutton enable-disable-service' data-toggle='tooltip' data-placement='top' title='Disable service for active alerting and monitoring.'><i class='fa-solid fa-folder-minus'></i></button></div>";
          monitored_target.append(service);
        }else if(configurable_services["monitor"] == 0){
          service += "<button type='button' data-node-id='" + nodeid + "' data-service-id='" + configurable_services["service_id"] + "' data-type-id='" + type_id + "' class='btn btn-success wsbutton enable-disable-service' data-toggle='tooltip' data-placement='top' title='Enable service for active alerting and monitoring.'><i class='fa-solid fa-folder-plus'></i></button></div>";
          unmonitored_target.append(service);
        }
      });
    });

    $('[data-toggle="tooltip"]').tooltip({container: 'body'});
    initDisableOrEnableService();
    $("#changeMonitoredServices").modal("show");
  }
}

function initDisableOrEnableService(){
  $(".enable-disable-service").off("click");
  $(".enable-disable-service").on("click", function(){
    var node_id = $(this).attr("data-node-id");
    var service_id = $(this).attr("data-service-id");
    var type_id = $(this).attr("data-type-id");
    $(this).tooltip("hide");

    if(node_id in monitored_services && "services" in monitored_services[node_id] &&
      type_id in monitored_services[node_id]["services"] && "configurable_services" in monitored_services[node_id]["services"][type_id] &&
      service_id in monitored_services[node_id]["services"][type_id]["configurable_services"]
    ){     
      var data_to_save = {
        node_id : node_id,
        service_id : service_id,
        monitor : !monitored_services[node_id]["services"][type_id]["configurable_services"][service_id]["monitor"]
      };

      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Infra_Sysinfo\\Chia_Infra_Sysinfo_Api", "Chia_Infra_Sysinfo_Api", "editMonitoredServices", data_to_save);
    }else{
      showMessage(1, "This seems no to be a valid service.");
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

    $(".statusbadge[data-node-id='" + nodeid + "']").removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass(statusicon).text(statustext);
  });
}

/* ------------------------------------------ */
/* Functions regarding chart drawing */
/* ------------------------------------------ */

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
      var used = 0;
      var free = 0;
      if(infodata["memory"]["swap"] !== undefined){
        used = (parseInt(infodata["memory"]["swap"]["swap_total"]) - parseInt(infodata["memory"]["swap"]["swap_free"]))/1024/1024/1024;
        free = parseInt(infodata["memory"]["swap"]["swap_free"])/1024/1024/1024;
      }
  
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

/* ------------------------------------------ */
/* Functions regarding data transmission */
/* ------------------------------------------ */

function messagesTrigger(data){
  var key = Object.keys(data);

  console.log(data);

  if(data[key]["status"] == 0){
    if(key == "queryNodesServicesStatus" || key == "updateSystemInfo" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      $.get(frontend + "/sites/chia_infra_sysinfo/templates/cards.php", {}, function(response) {
        $('#all_node_sysinfo_container').html(response);
        reinitAll();
        sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
      });
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
      }
    }else if(key == "setUpNewDowntime"){
      if($("#saveDowntimeModal").is(":visible")) $("#saveDowntimeModal").modal("hide");

      $.each(data[key]["data"], function(nodeid, downtimes){
        found_downtimes[nodeid] = downtimes;
        if($("#saveDowntimeModal").is(":visible")) updateFoundDowntimesInModal(nodeid);
      });
    }else if(key == "editDowntimes"){
      $("#saveEditDowntimes").modal("hide");
      $("#editDowntimeModal").modal("hide");
      $.each(data[key]["data"], function(nodeid, downtimes){
        found_downtimes[nodeid] = downtimes;
        updateFoundDowntimesInModal(nodeid);
      });
    }else if(key == "editMonitoredServices"){
      $.each(data[key]["data"], function(node_id, available_types){
        monitored_services[node_id]["services"] = available_types["services"];
        recreateMonitoredServicesLists(node_id);
      });
      //monitored_services[node_id]["services"][type_id]["configurable_services"][service_id]["monitor"] = data_to_save["monitor"];
    }
    setTimeout(function(){
      setServiceBadge();
    }, 600);
    //console.log(data[key]["message"]);
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
