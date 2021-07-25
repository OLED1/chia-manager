initRefreshFarmInfos();

function initRefreshFarmInfos(){
  $(".refreshFarmInfo").off("click");
  $(".refreshFarmInfo").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var authhash = chiaFarmData[nodeid]["nodeauthhash"];
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "queryFarmData" : {
          "status" : 0,
          "message" : "Query Farm data.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "queryFarmData", datafornode);
  });
}

function createFarmdataCards(data){
  $("#farminfocards").children().remove();
  $.each(data, function(nodeid, farmdata){
    console.log(farmdata);
    $("#farminfocards").append(
      "<div class='row'>" +
        "<div class='col'>" +
          "<div class='card shadow mb-4'>" +
            "<div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>" +
              "<h6 class='m-0 font-weight-bold text-primary'>Farmdata for host " + farmdata["hostname"] + " with id " + nodeid + "</h6>" +
              "<div class='dropdown no-arrow'>" +
              "  <a id='dropdownMenuLink_" + nodeid + "' class='dropdown-toggle' href='#' role='button' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>" +
                    "<i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>" +
                "</a>" +
                "<div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink_" + nodeid + "'>" +
                    "<div class='dropdown-header'>Actions:</div>" +
                    "<a data-node-id='" + nodeid + "' class='dropdown-item refreshFarmInfo' href='#'>Refresh</a>" +
                "</div>" +
              "</div>" +
            "</div>" +
            "<div class='card-body'>" +
              "<div class='row'>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Farming Status</h5>" +
                      "<h4 style='" + (farmdata['farming_status'] == 'Farming' ? 'color: green;' : 'color: red;') +"'>" + farmdata["farming_status"] + "<span style='font-size: 1.2em;'>&#8226;</span></h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>XCH Total Chia Farmed</h5>" +
                      "<h4>" + farmdata["total_chia_farmed"] + "</h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>XCH Block Rewards</h5>" +
                      "<h4>" + farmdata["block_rewards"] + "</h4>" +
                      "<h7>Without fees</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
              "</div>" +
              "<div class='row'>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>XCH User Transaction Fees</h5>" +
                      "<h4>" + farmdata["user_transaction_fees"] + "</h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Last Height Farmed</h5>" +
                      "<h4>" + farmdata["last_height_farmed"] + "</h4>" +
                      "<h7>No blocks farmed yet</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Plot Count</h5>" +
                      "<h4>" + farmdata["plot_count"] + "</h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
              "</div>" +
              "<div class='row'>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Total Size of Plots</h5>" +
                      "<h4>" + farmdata["total_size_of_plots"] + "</h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Total Network Space</h5>" +
                      "<h4>" + farmdata["estimated_network_space"] + "</h4>" +
                      "<h7>Best estimate over last 24 hours</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='col'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                      "<h5>Estimated time to Win</h5>" +
                      "<h4>" + farmdata["expected_time_to_win"] + "</h4>" +
                      "<h7>&nbsp;</h7>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
              "</div>" +
            "</div>" +
          "</div>" +
        "</div>" +
      "</div>");
  });
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateFarmData"){
      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Farm\\Chia_Farm_Api", "Chia_Farm_Api", "getFarmData", {});
    }else if(key == "getFarmData"){
      createFarmdataCards(data[key]["data"]);
      initRefreshWalletInfo();
    }
  }
}