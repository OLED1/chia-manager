$(function(){
  initWsclient();
});

let socket;
let alreadyreconnecting = false;
var tasklist = [];

function initWsclient(){
  try{
    socket = new WebSocket(websocket);

    socket.onmessage = (msg) => {
      data = JSON.parse(msg.data);
      var key = Object.keys(data);

      if(key == "loginStatus"){
        if(data[key]["status"] == 0){
          showMessage(0, "Connected to websocket client.");
          if (typeof siteID === "undefined") {
              siteID = 1;
          }
          var data = {
            userID : userID,
            siteID : siteID
          }
          sendToWSS("updateFrontendViewingSite", "", "", "", data);
        }
      }else if($.isFunction(window.messagesTrigger)){
        window.messagesTrigger(data);

        if("loglevel" in data[key] && "message" in data[key]){
          showMessage(data[key]["loglevel"], data[key]["message"]);
        }
      }

      delete tasklist[key];
      toggleWSSLoading(tasklist);
    };

    socket.onopen = function(evt) {
      $("#wsstatus").text("Socket: Connected.").removeClass("badge-danger").addClass("badge-success").attr("data-connected",1);
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "loginStatus", {});
      enableWSButtons();
    };

    socket.onclose = function(msg){
      $("#wsstatus").text("Socket: Not connected. Trying to reconnect.").removeClass("badge-success").addClass("badge-danger").attr("data-connected",2);
      socket.close();
      if(!alreadyreconnecting){
        alreadyreconnecting = true;
        setTimeout(function() { initWsclient();   alreadyreconnecting = false; }, 1000);
      }
      tasklist = {};
      toggleWSSLoading(tasklist);
      disableWSButtons();
    };

    socket.onerror = (msg) => {
      $("#wsstatus").text("Socket: Not connected. Trying to reconnect.").removeClass("badge-success").addClass("badge-danger").attr("data-connected",2);
      socket.close();
      if(!alreadyreconnecting){
        alreadyreconnecting = true;
        setTimeout(function() { initWsclient();   alreadyreconnecting = false; }, 1000);
      }
      tasklist = {};
      toggleWSSLoading(tasklist);
      disableWSButtons();
    }
  }catch(ex){
    showMessage(2, ex);
  }
}

function getNodeInfo(){
  data = {};
  data["node"] = {};
  data["node"]["nodeinfo"] = {};
  data["node"]["nodeinfo"]["hostname"] = "localhost";

  return data;
}

function sendToWSS(requestType, namespace, classname, method, data){
  reqdata = getNodeInfo();
  reqdata["node"]["socketaction"] = requestType;
  reqdata["request"] = {};
  reqdata["request"]["logindata"] = {};
  reqdata["request"]["logindata"]["userid"] = userID;
  reqdata["request"]["logindata"]["sessionid"] = sessid;
  reqdata["request"]["logindata"]["authhash"] = authhash;
  if(typeof siteID !== "undefined") reqdata["request"]["logindata"]["siteID"] = siteID;
  reqdata["request"]["data"] = data;
  reqdata["request"]["backendInfo"] = {};
  reqdata["request"]["backendInfo"]["namespace"] = namespace;
  reqdata["request"]["backendInfo"]["class"] = classname;
  reqdata["request"]["backendInfo"]["method"] = method;


  if(socket != undefined && socket.readyState != undefined && socket.readyState){
    if(method.trim().length > 0){
      tasklist[method] = reqdata;
      toggleWSSLoading(tasklist);
    }
    socket.send(JSON.stringify(reqdata));
  }else{
    showMessage(2, "Websocket not connected!");
  }
}
