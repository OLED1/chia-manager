initRefreshWalletInfo();
initRestartWalletService();
createTransactionsTables();
createTransactionsCharts();

Chart.defaults.global.defaultFontColor = (darkmode == 1 ? "#858796" : "#fff");

setTimeout(function(){
  if($(".statusbadge.badge-secondary").length > 0){
    sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
  }
}, 5000);

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaWalletData, function(nodeid, farmdata) {
      queryWalletData(nodeid);
  });
});

function createTransactionsTables(){
  $.each(transactionData, function(nodeid, transactions){
    $.each(transactions, function(walletid, transaction){
      var target = $("#transactions_" + nodeid + "_" + walletid);
      if(target.length > 0){
        var transactiontable = $(target).DataTable({
          data: transaction,
          columns: [
            { data: "id" },
            { data: "created_at_time", render: function(data, type, row){
                const date = new Date(data*1000);
                return date.toLocaleDateString();
              }
            },
            { data: "amount", render: function(data, type, row){
                return data + " mojo(s)";
              }
            },
            { data: "to_address" },
            {
              defaultContent: "<button type='button' class='connection-info btn btn-warning wsbutton'><i class='fas fa-info-circle'></i></button>"
            }
          ]
        });

        $(target).find("tbody").off("click", "button");
        $(target).find("tbody").on("click", "button", function(){
          var data = transactiontable.row( $(this).parents('tr') ).data();
          var this_wallet_adddress = chiaWalletData[nodeid][walletid]["walletaddress"];

          $("#transaction-nodeid").text(nodeid);
          $("#transaction-walletid").text(walletid);

          //Summary -- START --
          $("#transaction-summary #confirmed").html(function(){
            var confirmed = Boolean(Number(data["confirmed"]));
            return "<span class='badge " + (confirmed ? "badge-success" : "badge-warning") + "'>" + (confirmed ? "Confirmed" : "Not confirmed") + "</span>";
          });
          $("#transaction-summary #date").html(function(){
            const date = new Date(data["created_at_time"]*1000);
            return date.toLocaleDateString();
          });

          $("#transaction-summary #direction").html(function(){
            return "<span class='badge " + (this_wallet_adddress == data["to_address"] ? "badge-success" : "badge-warning") + "'>" + (this_wallet_adddress == data["to_address"] ? "Incoming <i class='far fa-arrow-alt-circle-left'></i>" : "Outgoing <i class='far fa-arrow-alt-circle-right'></i>") + "</span>";
          });

          $("#transaction-summary #amount").html(function(){
            return data["amount"] + " mojo(s) / " + (data["amount"] / 1000000000000) + " XCH";
          });

          $("#transaction-summary .currency_code").text(defaultCurrency.toUpperCase());
          $("#transaction-summary #amount_currency").html(function(){
            return (parseFloat(currentxchdefaultprice) * (parseInt(data["amount"]) / 1000000000000)) + "&nbsp" + defaultCurrency.toUpperCase();
          });

          $("#transaction-summary #fee_amount_currency").html(function(){
            return (parseFloat(currentxchdefaultprice) * (parseInt(data["fee_amount"]) / 1000000000000)) + "&nbsp" + defaultCurrency.toUpperCase();
          });

          $("#transaction-summary #fee_amount").text(data["fee_amount"] + " mojo(s)");

          $("#transaction-summary #to_address").text(data["to_address"]);
          $("#transaction-summary #name").text(data["name"]);
          //Summary -- END --
          //Extended (More) -- START
          $("#transaction-more #parent_coin_info").text(data["parent_coin_info"]);
          $("#transaction-more #confirmed_at_height").text(data["confirmed_at_height"]);
          $("#transaction-more #to_puzzle_hash").text(data["to_puzzle_hash"]);
          //Extended (More) -- END
          $("#transactiondetailsmodal").modal("show");
        });
      }
    });
  });
}

function createTransactionsCharts(){
  const nrofdays = 30;
  const thirtyDaysAgo =  moment().subtract(nrofdays, 'days');

  $.each(transactionData, function(nodeid, transactions){
    $.each(transactions, function(walletid, transaction){
      var target = $("#transactions_chart_" + nodeid + "_" + walletid);
      if(target.length > 0){
        var labels = [];
        var data = [];

        for (let i = 0; i < nrofdays; i = i + 1) {
          thirtyDaysAgo.add(1, 'days');
          labels.push(thirtyDaysAgo.format('DD-MMM-YYYY'));
          data.push(0);
        }

        $.each(transaction, function(arrkey, thistransaction){
          var dateString = moment.unix(thistransaction["created_at_time"]).format('DD-MMM-YYYY');
          var valueIndex = labels.indexOf(dateString);
          if(valueIndex > 0){
            data[valueIndex] += (parseInt(thistransaction["amount"]) / 1000000000000);
          }
        });

        var myChart = new Chart(target, {
            type: "line",
            data: {
              labels: labels,
              datasets: [
                {
                  label: "Daily transactions (In-Out) in XCH (" + nrofdays + " days)",
                  data: data,
                  borderColor: "rgba(52, 161, 235, 0)",
                  backgroundColor: "rgba(52, 161, 235, 0.5)",
                }
              ]
            },
            options: {
              responsive: true,
              plugins: {
                legend: {
                  position: 'top',
                },
                title: {
                  display: true,
                  text: 'Transactions Chart'
                }
              },
              scales: {
                yAxes: [{
                  ticks: {
                    maxTicksLimit: 5
                  }
                }]
              }
            }
        });
      }
    });
  });
}

function queryWalletData(nodeid){
  var walletid = $(this).attr("data-wallet-id");
  var authhash = chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"];

  var dataforclient = {
    "nodeid" : nodeid,
    "authhash": authhash
  }

  sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "queryWalletData", dataforclient);
}

function initRefreshWalletInfo(){
  $(".refreshWalletInfo").off("click");
  $(".refreshWalletInfo").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    queryWalletData(nodeid);
  });
}

function initRestartWalletService(){
  $(".restartWalletService").off("click");
  $(".restartWalletService").on("click", function(e){
    e.preventDefault();
    var nodeid = $(this).attr("data-node-id");
    var walletid = $(this).attr("data-wallet-id");
    var authhash = chiaWalletData[nodeid][walletid]["nodeauthhash"];

    var dataforclient = {
      "nodeid" : nodeid,
      "authhash": authhash
    }

    sendToWSS("backendRequest", "ChiaMgmt\\Chia_Wallet\\Chia_Wallet_Api", "Chia_Wallet_Api", "restartWalletService", dataforclient);
  });
}

function queryWalletStatus(nodeid){
  data = [
    {"nodeid" : nodeid, "nodeauthhash" : chiaWalletData[nodeid][Object.keys(chiaWalletData[nodeid])]["nodeauthhash"]}
  ];

  sendToWSS("ownRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryWalletStatus", data);
}

function setWalletBadge(nodeid, status, message){
  var targetbadge = $("#servicestatus_" + nodeid);
  targetbadge.removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger");
  if(status == 0){
    targetbadge.addClass("badge-success");
  }else if(status == 1){
    targetbadge.addClass("badge-danger");
  }else if(status == 2){
    targetbadge.addClass("badge-secondary");
  }
  targetbadge.text(message);
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateWalletData"){
      $('#walletcontainer').load(frontend + "/sites/chia_wallet/templates/cards.php");

      initRefreshWalletInfo();
      initRefreshWalletInfo();
      initRestartWalletService();
    }else if(key == "walletStatus"){
      setTimeout(function(){
        setWalletBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
      }, 1000);
    }else if(key == "walletServiceRestart"){
      setWalletBadge(data[key]["data"]["data"], data[key]["data"]["status"], data[key]["data"]["message"]);
    }else if(key == "connectedNodesChanged"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "queryNodesServicesStatus", {});
    }else if(key == "queryNodesServicesStatus"){
      $.each(data[key]["data"], function(nodeid, condata){
        if(condata["onlinestatus"] == 1){
          setWalletBadge(nodeid, condata["onlinestatus"], "Node not reachable");
        }
      });
    }
  }else if(data[key]["status"] == "014003001"){
    $(".statusbadge").each(function(){
      var thisnodeid = $(this).attr("data-node-id");
      if(($(this).hasClass("badge-secondary") || $(this).hasClass("badge-success")) && $.inArray(data[key]["data"]["informed"],thisnodeid) == -1){
        $(this).removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-danger").addClass("badge-danger").html("Node not reachable");
      }
    });
  }
}
