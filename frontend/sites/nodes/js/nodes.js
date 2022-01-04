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
initRemoveNode();

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

$("#acceptNodeRequest").on("click", function(){
  if($('#nodetypes-options option:selected').length > 0){
    var nodearr = [];
    var nodeid = $("#acceptNodeRequestModal").attr("data-conf-id");
    var authhash = $("#acceptNodeRequestModal").attr("data-authhash");
    $.each($('#nodetypes-options option:selected'), function(){
      nodearr.push($(this).val());
    });
    var data = {
      nodeid : nodeid,
      authhash : authhash,
      nodetypes : nodearr.join()
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "acceptNodeRequest", data);
  }else{
    showMessage(1, "No nodes are selected.");
  }
});

$(".scriptbranchoption").on("click", function(){
  var branch = $(this).attr("data-branch");
  var nodeid = $("#nodeactionmodal").attr("data-nodeid");

  sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "updateNodeBranch", {"nodeid" : nodeid, "branch" : branch});
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

function recreateConfiguredClients(){
  var rows = configuredClients
  .rows()
  .remove()
  .draw();

  $.each(configuredNodes, function(arrkey, value){
    var rowNode = configuredClients
    .row.add( [ value["id"], value["nodetype"], getAuthtypeString(value["authtype"]), getConallowString(value["conallow"]), value["hostname"], formatScriptInfoWithUpdate(value["id"], 1), formatScriptInfoWithUpdate(value["id"], 0), formatIP(value["ipaddress"], value["changedIP"], value["id"]), getClientCount(value["nodeauthhash"]), value["lastseen"], getButtonsConfClients(value["id"], value["conallow"], value["changeable"]) ] )
    .draw()
    .node().id = "confnode_" + value["id"];
  });
}

//Type: 0 = Scriptversion, 1 = Chiaversion
function formatScriptInfoWithUpdate(nodeid, type){
  if(nodeid in scriptupdatesavail["updateinfos"]){
    var nodeupdate = scriptupdatesavail["updateinfos"][nodeid];
    var scriptversion = (nodeupdate["scriptversion"] === undefined || nodeupdate["scriptversion"] === null ? "Not reported." : nodeupdate["scriptversion"]);
    var chiaversion = (nodeupdate["chiaversion"] === undefined || nodeupdate["chiaversion"] === null ? "Not reported." : nodeupdate["chiaversion"]);
    if(type == 0) return scriptversion + "<br><span id='table_script_updateavail_" + nodeid + "'>" + getUpdateAvailableBadge(nodeupdate["updateavailable"], type) + "</span>";
    else if(type == 1) return chiaversion + "<br><span table_chia_updateavail_>" + getUpdateAvailableBadge(nodeupdate["chiaupdateavail"], type) + "</span>";
  }else{
    return "-";
  }
}

//Conallow = 0 Node not allowed to connect
//Conallow = 1 Node is allowed to connect
//Conallow = 2 Permission for connecting is pending
function getButtonsConfClients(id, conallow, changeable){
  var button = "";
  if((conallow == 0 || conallow == 2) && changeable == 1){
    button += "<button type='button' data-conf-id=" + id + " class='allow-connect btn btn-success wsbutton'><i class='far fa-check-circle'></i></button>";
  }
  if((conallow == 1 || conallow == 2) && changeable == 1){
    button += "<button type='button' data-conf-id=" + id + " class='decline-connect btn btn-danger wsbutton'><i class='far fa-times-circle'></i></button>";
  }

  if(changeable == 1){
    button += "&nbsp;<button type='button' data-conf-id=" + id + " class='remove-node btn btn-danger'><i class='fas fa-trash-alt'></i></button>";
  }

  if(conallow == 1 && changeable == 1){
    button += "&nbsp;<button type='button' data-conf-id=" + id + " class='connection-info btn btn-primary'><i class='fas fa-info-circle'></i></button>";
  }


  return button;
}

function formatIP(ipaddress, changedIP, id){
  button = "";
  if(changedIP == ""){
    return ipaddress;
  }else{
    button += "<button type='button' data-conf-id=" + id + " class='ip-changed-allow btn btn-warning wsbutton'><i class='far fa-check-circle'></i></button>&nbsp";
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
        nodeid: nodeid,
        authhash: authhash
      };

      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "declineNodeRequest", data);
    });
  });
}

function initRemoveNode(){
  $(".remove-node").off("click");
  $(".remove-node").on("click", function(){
    var nodeid = $(this).attr("data-conf-id");
    var config = configuredNodes[nodeid];
    var authhash = config["nodeauthhash"];

    $("#removeNodeModal-nodeid").text(nodeid);
    $("#removeNodeModal-authhash").text(authhash);

    $("#removeNodeModal").attr("data-conf-id", nodeid);
    $("#removeNodeModal").attr("data-authhash", authhash);
    $("#removeNodeModal").modal("show");

    $("#remove-node").on("click", function(){
      data = {
        nodeid: nodeid,
        authhash: authhash
      };

      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "removeNodeAndData", data);
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
        "nodeid" : nodeid,
        "authhash": authhash
      }

      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "acceptIPChange", dataforclient);
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

  $(".connection-info").off("click");
  $(".connection-info").on("click", function(){
    $(".infotext").text("N/A");

    var nodeid = $(this).attr("data-conf-id");
    var nodeinfo = configuredNodes[nodeid];
    var available_channels = scriptupdatesavail["available_channels"];
    var updateinfo = scriptupdatesavail["updateinfos"][nodeid];
    $.each(nodeinfo, function(key, value){
      $("#"+key).text(value);
    });

    $("#node-info-navtabs .nav-link").removeClass("active").first().addClass("active");

    if($.isNumeric(nodeinfo["cpu_count"]) && $.isNumeric(nodeinfo["cpu_cores"]))
      $("#cpu_cores_threads").text(nodeinfo["cpu_cores"] + " Core(s) / " + nodeinfo["cpu_count"] + " Thread(s)");

    if($.isNumeric(nodeinfo["memory_total"]) && $.isNumeric(nodeinfo["swap_total"]))
      $("#ram_swap_size").text((nodeinfo["memory_total"]/1024/1024/1024).toFixed(2) + "GB / " + (nodeinfo["swap_total"]/1024/1024/1024).toFixed(2) + "GB");

    $("#updatechannelsMenu").text(getFullNameFromBranch(updateinfo["updatechannel"]));
    $("#current_version").text(updateinfo["scriptversion"]);
    $("#remote_version").text(updateinfo["remoteversion"]);
    $("#updatenode").hide();
    if(updateinfo["scriptversion"] !== null && updateinfo["scriptversion"] !== undefined){
      $("#update_available").html(getUpdateAvailableBadge(updateinfo["updateavailable"], 0));
  
      if(updateinfo["updateavailable"] < 0){
        $("#updatenode").show();
      }
    } 
    $("#nodeactionmodal .tab-pane.active").removeClass("active").removeClass("show");
    $("#nodeactionmodal .nav-item.active").removeClass("active");


    $("#nav-tab .nav-item").first().addClass("active");
    $("#nodeactionmodal .tab-pane").first().addClass("active").addClass("show");

    $("#nodeactionmodal").modal("show").attr("data-nodeid", nodeid);

    $("#check-for-updates").off("click");
    $("#check-for-updates").on("click", function(){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "checkUpdatesAndChannels", {"nodeid" : nodeid});
    });
  });
}

function getFullNameFromBranch(channelname){
  switch (channelname){
    case "dev":
      return "Development";
    case "staging":
      return "Staging";
    case "main":
      return "Productive";
    default:
      return "Not set";
  }
}

function getUpdateAvailableBadge(updateavailable, type){
  if(type == 0){ nodetext = "Node script"; }
  else if(type == 1){ nodetext = "Chia blockchain"; }

  if(updateavailable == 0 || updateavailable == 1){
    return "<span class='badge badge-success'>"+ nodetext + " up to date.</span>";
  }else if(updateavailable < 0){
    return "<span class='badge badge-warning'>"+ nodetext + " update available.</span>";
  }else{
    return "<span class='badge badge-warning'>No updatedata available.</span>";
  }
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
    if(key == "queryNodesServicesStatus" || key == "updateChiaStatus" || key == "setNodeUpDown"){
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
    }else if(key == "acceptNodeRequest"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      $("#acceptNodeRequestModal").modal("hide");
    }else if(key == "declineNodeRequest"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      $("#declineNodeRequestModal").modal("hide");
    }else if(key == "removeNodeAndData"){
      sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getConfiguredNodes", {});
      $("#removeNodeModal").modal("hide");
    }else if(key == "updateSystemInfo"){
      if(data[key]["data"]["nodeid"] in sysinfodata){
        sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getSystemInfo", { "nodeid": data[key]["data"]["nodeid"] });
      }
      reinit = false;
    }else if(key == "updateScriptVersion"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "checkUpdatesAndChannels", {});
    }else if(key == "checkUpdatesAndChannels"){
      var nodeupdatedata = data[key]["data"]["updateinfos"];
      $.each(nodeupdatedata, function(nodeid, updatedata){
        if($("#nodeactionmodal[data-nodeid='" + nodeid + "']").length > 0){
          $("#updatenode").hide();
          $("#remote_version").text(updatedata["remoteversion"]);
          $("#current_version").text(updatedata["scriptversion"]);
          if(updatedata["scriptversion"] !== null && updatedata["scriptversion"] !== undefined){
            $("#update_available").html(getUpdateAvailableBadge(updatedata["updateavailable"], 0));
            if(updatedata["updateavailable"] < 0){
              $("#updatenode").show();
            }
          }
        }
        scriptupdatesavail["updateinfos"][nodeid] = updatedata;
        reinit = true;
      });
    }else if(key == "updateNodeBranch"){
      $("#nodeactionmodal[data-nodeid='" + data[key]["data"]["nodeid"] + "'] #updatechannelsMenu ").text(getFullNameFromBranch(data[key]["data"]["branch"]));
      scriptupdatesavail["updateinfos"][data[key]["data"]["nodeid"]]["updatechannel"] = data[key]["data"]["branch"];
    }
    if(reinit){
      recreateConfiguredClients();
      initAllowConnect();
      initDenyConnect();
      initAllowIPChange();
      initShowNodeInfo();
      initRemoveNode();
    }
  }
}
