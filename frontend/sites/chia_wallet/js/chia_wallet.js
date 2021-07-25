initRefreshWalletInfo();

$.each(chiaWalletData, function(walletid, walletdata) {
  queryWalletStatus(walletid);
});

function initRefreshWalletInfo(){
  $(".refreshWalletInfo").off("click");
  $(".refreshWalletInfo").on("click", function(e){
    e.preventDefault();
    var walletid = $(this).attr("data-wallet-id");
    var authhash = chiaWalletData[walletid]["nodeauthhash"];
    var datafornode = {
      "nodeinfo":{
        "authhash": authhash
      },
      "data" : {
        "queryWalletData" : {
          "status" : 0,
          "message" : "Query Wallet data.",
          "data": {}
        }
      }
    }

    sendToWSS("messageSpecificNode", "", "", "queryWalletData", datafornode);
  });
}

function queryWalletStatus(walletid){
  var authhash = chiaWalletData[walletid]["nodeauthhash"];

  var datafornode = {
    "nodeinfo":{
      "authhash": authhash
    },
    "data" : {
      "queryWalletStatus" : {
        "status" : 0,
        "message" : "Query Wallet running status.",
        "data": {}
      }
    }
  }

  sendToWSS("messageSpecificNode", "", "", "queryWalletStatus", datafornode);
}

function generateWalletCards(data){
  $("#walletcontainer").children().remove();
  $.each(data, function(walletid, walletdata){
    var synccard =
      "<div class='row'>" +
        "<div class='col-lg-2 mb-4'>" +
          "<div class='card " + (walletdata['syncstatus'] == "Synced" ? "bg-success" : "bg-danger") + " text-white shadow'>" +
            "<div class='card-body'>" +
                "Walletstatus: " + walletdata['syncstatus'] +
                "<div class='text-white-50 small'>Height: " + walletdata['walletheight'] + "</div>" +
            "</div>" +
          "</div>" +
        "</div>" +
      "</div>";

    $("#walletcontainer").append(
      "<div class='row'>" +
        "<div class='col'>" +
          "<div class='card shadow mb-4'>" +
            "<div class='card-header py-3 d-flex flex-row align-items-center justify-content-between'>" +
              "<h6 class='m-0 font-weight-bold text-primary'>Wallet (ID: " + walletdata['walletid'] + "), Type: " + walletdata['wallettype'] + ", Status: " + walletdata['syncstatus'] + "&nbsp;" + (" + walletdata['syncstatus'] + " == "Synced" ? "<i class='fas fa-check-circle' style='color: green;'" : "<i class='fas fa-times-circle' style='color: red;'") + "></i>&nbsp;<span id='servicestatus_" + walletdata['nodeid'] + "' class='badge badge-secondary'>Querying service status</span></h6>" +
              "<div class='dropdown no-arrow'>" +
                  "<a class='dropdown-toggle' href='#' role='button' id='dropdownMenuLink' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>" +
                      "<i class='fas fa-ellipsis-v fa-sm fa-fw text-gray-400'></i>" +
                  "</a>" +
                  "<div class='dropdown-menu dropdown-menu-right shadow animated--fade-in' aria-labelledby='dropdownMenuLink'>" +
                      "<div class='dropdown-header'>Actions:</div>" +
                      "<a data-wallet-id='" + walletdata['walletid'] + "' class='dropdown-item refreshWalletInfo' href='#'>Refresh</a>" +
                  "</div>" +
              "</div>" +
            "</div>" +
            "<div class='card-body'>" +
              synccard +
                "<div class='row'>" +
                  "<div class='col col-xl-5 col-lg-5'>" +
                    "<div class='card shadow mb-4'>" +
                      "<div class='card-header'>" +
                        "Wallet Address" +
                      "</div>" +
                      "<div class='card-body'>" +
                          walletdata['walletaddress'] +
                      "</div>" +
                    "</div>" +
                  "</div>" +
                "</div>" +
                "<div class='row'>" +
                "<div class='col col-xl-5 col-lg-5'>" +
                  "<div class='card shadow mb-4'>" +
                    "<div class='card-header'>" +
                      "Balance" +
                    "</div>" +
                    "<div class='card-body'>" +
                      "<div class='table-responsive'>" +
                        "<table class='table table-bordered' width='100%' cellspacing='0'>" +
                          "<tbody>" +
                            "<tr><td><strong>Total Balance</strong></td><td>" + parseFloat(walletdata['totalbalance']).toFixed(2) + " xch (" + (parseFloat(walletdata['totalbalance']).toFixed(2) * 1000000000000) + " mojo)</td></tr>" +
                            "<tr><td><strong>Pending Total Balance</strong></td><td>" + parseFloat(walletdata['pendingtotalbalance']).toFixed(2) + " xch  (" + (parseFloat(walletdata['pendingtotalbalance']).toFixed(2) * 1000000000000) + " mojo)</td></tr>" +
                            "<tr><td><strong>spendable</strong></td><td>" + parseFloat(walletdata['spendable']).toFixed(2) + " xch (" + (parseFloat(walletdata['spendable']).toFixed(2) * 1000000000000) + " mojo)</td></tr>" +
                          "</tbody>" +
                        "</table>" +
                      "</div>" +
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
    if(key == "updateWalletData"){
      sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "getWalletData", {});
    }else if(key == "getWalletData"){
      generateWalletCards(data[key]["data"]);
      initRefreshWalletInfo();
    }else if(key == "walletStatus"){
      console.log(data);
      var targetbadge = $("#servicestatus_" + data[key]["data"]["data"]);
      targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-alert");
      if(data[key]["data"]["status"] == 0){
        targetbadge.addClass("badge-success");
      }else{
        targetbadge.addClass("badge-danger");
      }
      targetbadge.html(data[key]["data"]["message"]);
    }
  }
}
