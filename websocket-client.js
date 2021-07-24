$(function(){
  console.log("HI!");

  // connect to chat application on server
  let serverUrl = 'wss://chiamgmt.edtmair.at/chat';
  let socket = new WebSocket(serverUrl);

  // log new messages to console
  socket.onmessage = (msg) => {
     console.log(msg.data);
     $("#messagecontainer").append("Message:" + msg.data + "<br>");
  };

  socket.onopen = function(evt) {
    console.log("Connection established!");
    data = {};
    data["data"] = {};
    data["data"]["action"] = "registerNode";
    data["data"]["nodeinfo"] = {};
    data["data"]["nodeinfo"]["role"] = "webClient";
    data["data"]["nodeinfo"]["hostname"] = "localhost";

    socket.send(JSON.stringify(data));

    data["data"]["action"] = "getRegisteredNodes";
    socket.send(JSON.stringify(data));
  };

  $("#sendToSpecificHarvester").on("click", function(){
    var harvesterID = $("#harvesterID").val();

    data = {};
    data["data"] = {};
    data["data"]["action"] = "informSpecificHarvester";
    data["data"]["nodeinfo"] = {};
    data["data"]["nodeinfo"]["role"] = "webClient";
    data["data"]["nodeinfo"]["hostname"] = "localhost";
    data["data"]["harvester"] = {};
    data["data"]["harvester"]["id"] = harvesterID;
    //data["data"]["harvester"]["action"] = "restartWebsocketClient";
    //data["data"]["harvester"]["action"] = "stopWebsocketClient";
    data["data"]["harvester"]["action"] = "getMountPoints";

    socket.send(JSON.stringify(data));
  });
});
