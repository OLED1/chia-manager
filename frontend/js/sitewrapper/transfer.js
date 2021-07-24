$(function(){
  initWsclient();
});

let socket;
let alreadyreconnecting = false;

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

        if("status" in data && data["status"] == 0){
          showMessage(0, data["message"]);
        }else if(key in data && "status" in data[key] && data[key]["status"] == 0){
          showMessage(0, data[key]["message"]);
        }else if("status" in data && data["status"] != 0){
          showMessage(2, data["message"]);
        }else if(key in data && "status" in data[key] && data["status"] != 0){
          showMessage(2, data[key]["message"]);
        }
      }
    };

    socket.onopen = function(evt) {
      $("#wsstatus").text("Socket: Connected.").css("color","green");
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "loginStatus", {});
    };

    socket.onclose = function(msg){
      $("#wsstatus").html("Socket: Not connected. Trying to reconnect.<br>Some features will not work.").css("color","red");
      //showMessage(1, "Lost connection to wss server. Trying to reconnect.");
      socket.close();
      if(!alreadyreconnecting){
        alreadyreconnecting = true;
        setTimeout(function() { initWsclient();   alreadyreconnecting = false; }, 1000);
      }
    };

    socket.onerror = (msg) => {
      $("#wsstatus").html("Socket: Not connected. Trying to reconnect.<br>Some features will not work.").css("color","red");
      //showMessage(2, "Not connected to websocket server. No live data will be available.");
      socket.close();
      if(!alreadyreconnecting){
        alreadyreconnecting = true;
        setTimeout(function() { initWsclient();   alreadyreconnecting = false; }, 1000);
      }
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

  if(socket != undefined && socket.readyState != undefined && socket.readyState) socket.send(JSON.stringify(reqdata));
}
