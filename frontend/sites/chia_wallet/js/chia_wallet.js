var transactionsCharts = {};
var transactionsTables = {};

setTimeout(function(){
  setServiceBadge();
}, 700);

initRefreshWalletInfo();
initRestartWalletService();
createTransactionsTables();
createTransactionsCharts();

$("#queryAllNodes").off("click");
$("#queryAllNodes").on("click", function(){
  $.each(chiaNodes, function(nodeid, nodedata) {
      queryWalletData(nodeid);
  });
});

function createTransactionsTables(){
  $.each(transactionData, function(nodeid, transactions){
    $.each(transactions, function(walletid, transaction){
      var target = $("#transactions_" + nodeid + "_" + walletid);
      if(target.length > 0){
        if((nodeid in transactionsTables) && transactionsTables[nodeid] !== undefined && transactionsTables[nodeid][walletid] !== undefined) transactionsTables[nodeid][walletid].destroy();
        if(!(nodeid in transactionsTables)) transactionsTables[nodeid] = {};
        transactionsTables[nodeid][walletid] = $(target).DataTable({
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
          var data = transactionsTables[nodeid][walletid].row( $(this).parents('tr') ).data();
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
            return (parseFloat(chiapricedefcurr) * (parseInt(data["amount"]) / 1000000000000)) + "&nbsp" + defaultCurrency.toUpperCase();
          });

          $("#transaction-summary #fee_amount_currency").html(function(){
            return (parseFloat(chiapricedefcurr) * (parseInt(data["fee_amount"]) / 1000000000000)) + "&nbsp" + defaultCurrency.toUpperCase();
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
  
  $.each(transactionData, function(nodeid, transactions){
    $.each(transactions, function(walletid, transaction){
      const nrofdays = 30;
      const thirtyDaysAgo =  moment().subtract(nrofdays, 'days');
      
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
            data[valueIndex] += (parseInt(thistransaction["amount"]));
          }
        });

        if((nodeid in transactionsCharts) && transactionsCharts[nodeid][walletid] !== undefined) transactionsCharts[nodeid][walletid].destroy();
        if(!(nodeid in transactionsCharts)) transactionsCharts[nodeid] = {};
        transactionsCharts[nodeid][walletid] = new Chart(target, {
            type: "line",
            data: {
              labels: labels,
              datasets: [
                {
                  label: "Daily transactions (In-Out) in MOJO (" + nrofdays + " days)",
                  data: data,
                  borderColor: "rgba(52, 161, 235, 0)",
                  backgroundColor: "rgba(52, 161, 235, 0.5)",
                  fill: true
                }
              ]
            },
            options: {
              responsive: true,
              interaction: {
                mode: 'index',
                intersect: false,
              },
              stacked: false,
              plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: chartcolor
                    }
                }
              },
              scales: {
                  y: {
                    ticks : {
                      color: chartcolor,
                    },
                    beginAtZero: false
                  },
                  x: {
                      ticks : {
                          color: chartcolor
                      } 
                  }
              },
            }
        });
      }
    });
  });
}

function queryWalletData(nodeid){
  var authhash = chiaNodes[nodeid]["nodeauthhash"];
  var dataforclient = {
    "nodeinfo" : {
      "nodeid" : nodeid,
      "authhash": authhash
    }
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
    var authhash = chiaNodes[nodeid]["nodeauthhash"];

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

  sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
}

function setServiceBadge(){
  $.each(services_states, function(nodeid, nodedata){
    if(nodedata === "undefined" || nodedata["onlinestatus"]["status"] == 0){
      statustext = "Node not reachable";
      statusicon = "badge-danger";
    }else if(nodedata["onlinestatus"]["status"] == 1){
      servicestate = nodedata["services"][5]["servicestate"];
      servicedesc =  nodedata["services"][5]["service_desc"];
      if(servicestate == 0){
        statustext = servicedesc + " service not running";
        statusicon = "badge-danger";
      }else if(servicestate == 1){
        statustext = servicedesc + " service running";
        statusicon = "badge-success";
      }else{
        statustext = servicedesc + " service state unknown";
        statusicon = "badge-warning";
      }
    }

    $(".statusbadge[data-node-id='" + nodeid + "'").removeClass("badge-secondary").removeClass("badge-success").removeClass("badge-warning").removeClass("badge-danger").addClass(statusicon).text(statustext);
  });
}

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "updateWalletData"){
      var nodeid = data[key]["data"]["nodeid"];  
      var carddata = { "nodeid" : nodeid, "defaultCurrency" : defaultCurrency, "exchangerate" : exchangerate, "chiapriceindefcurr" : chiapricedefcurr, "chia_overall_data" : chiaoveralldata};
      $.get(frontend + "/sites/chia_wallet/templates/cards.php", carddata, function(response) {
        $('#walletcontainer_' + nodeid).html(response);
        initRefreshWalletInfo();
        initRestartWalletService();
        createTransactionsTables();
        createTransactionsCharts();
      });
    }else if(key == "queryOverallData"){
      chiaoveralldata = data[key]["data"];
    }else if(key == "queryNodesServicesStatus" || key == "updateChiaStatus" || key == "setNodeUpDown"){
      sendToWSS("backendRequest", "ChiaMgmt\\Nodes\\Nodes_Api", "Nodes_Api", "getCurrentChiaNodesUPAndServiceStatus", {});
    }else if(key == "getCurrentChiaNodesUPAndServiceStatus"){
      if("data" in data[key]){
        services_states = data[key]["data"];
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
  }
}
