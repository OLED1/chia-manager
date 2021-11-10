var historynetspacechart;
var historyblockheightchart;
var historyxchValuechart;

$(function(){  
    drawHistoryNetspaceChart();
    drawHistoryBlockheightChart();
    drawHistoryXCHValueChart();

    Chart.defaults.global.defaultFontColor = (darkmode == 1 ? "#858796" : "#fff");

    $(".datepicker").datetimepicker({
        format: 'Y-m-d H:i:s'
    });

    $("#filter-apply").on("click", function(){
        var from = $("#filter-from").val();
        var to = $("#filter-to").val();
        var from_date = new Date(from);
        var to_date = new Date(to);
        hourspast = (Math.abs(from_date - to_date) / 36e5).toFixed(2);

        if(from_date < to_date){
            sendToWSS("ownRequest", "ChiaMgmt\\Chia_Statistics\\Chia_Statistics_Api", "Chia_Statistics_Api", "getNetspaceHistory", { "from" : from, "to" : to });
            sendToWSS("ownRequest", "ChiaMgmt\\Chia_Statistics\\Chia_Statistics_Api", "Chia_Statistics_Api", "getBlockheightHistory", { "from" : from, "to" : to });
            sendToWSS("ownRequest", "ChiaMgmt\\Chia_Statistics\\Chia_Statistics_Api", "Chia_Statistics_Api", "getXCHValueHistory", { "from" : from, "to" : to });
        }else{
            showMessage(1, "The from-date must be in the past to to-date.");
        }
    });
});

function drawHistoryNetspaceChart(){
    if(historynetspace.length > 0){ 
        var labels = [];
        var data = [];
        $.each(historynetspace, function(arrkey, netspacedata){
            labels.push(netspacedata["querydate"]);
            data.push(netspacedata["netspace"]);
        });
        
        const historynetspacectx = $("#chia_netspace_history_chart");
        historynetspacechart = new Chart(historynetspacectx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: "History netspace growth (last " + hourspast + " hours)",
                    data: data,
                    backgroundColor: 'rgba(0, 250, 10, 0.5)',
                    borderColor: 'rgba(0, 250, 10, 1)',
                    borderwidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks : {
                            callback: function(value, index, values) {
                                return value + " EiB";
                            } 
                        }
                    }
                },
                tooltips: {
                    callbacks: {
                      label: (item) => `${item.yLabel} EiB`,
                    },
                },
                responsive: true
            }
        });

        $("#netspace_cur").text(data[data.length-1] + " EiB");
        $("#netspace_min").text(Math.min.apply(Math,data) + " EiB");
        $("#netspace_max").text(Math.max.apply(Math,data) + " EiB");
        $("#netspace_avg").text((data.reduce((a,b) => parseInt(a) + parseInt(b), 0) / data.length).toFixed(2) + " EiB");
        $("#netspace_gro").text((parseInt(data[data.length-1])/parseInt(data[0])*100-100).toFixed(2) + "%");
    }
}

function drawHistoryBlockheightChart(){
    if(historyblockheight.length > 0){ 
        var labels = [];
        var data = [];
        $.each(historyblockheight, function(arrkey, blockheightdata){
            labels.push(blockheightdata["querydate"]);
            data.push(blockheightdata["xch_blockheight"]);
        });
        
        const historyblockheightctx = $("#chia_historyblockheight_history_chart");
        historyblockheightchart = new Chart(historyblockheightctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: "History blockheight growth (last " + hourspast + " hours)",
                    data: data,
                    backgroundColor: 'rgba(245, 155, 39, 0.5)',
                    borderColor: 'rgba(245, 155, 39, 1)',
                    borderwidth: 1
                }]
            },
            options: {
                tooltips: {
                    callbacks: {
                      label: (item) => `${item.yLabel}`,
                    },
                },
                responsive: true
            }
        });

        $("#blockheight_cur").text(data[data.length-1]);
        $("#blockheight_inc").text((parseInt(data[data.length-1])/parseInt(data[0])*100-100).toFixed(2) + "%");
    }
}

function drawHistoryXCHValueChart(){
    if(historyXCHValue.length > 0){ 
        var labels = [];
        var data = [];
        $.each(historyXCHValue, function(arrkey, xchValueData){
            labels.push(xchValueData["querydate"]);
            data.push(xchValueData["price_usd"]);
        });
        
        const historyxchValuectx = $("#chia_xchvalue_history_chart");
        historyxchValuechart = new Chart(historyxchValuectx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: "XCH price in " + defaultCurrency.toUpperCase() + " (last " + hourspast + " hours)",
                    data: data,
                    backgroundColor: 'rgba(43, 82, 252, 0.5)',
                    borderColor: 'rgba(43, 82, 252, 1)',
                    borderwidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks : {
                            callback: function(value, index, values) {
                                return value + " " + defaultCurrency.toUpperCase();
                            } 
                        }
                    }
                },
                tooltips: {
                    callbacks: {
                      label: (item) => `${item.yLabel} ${defaultCurrency.toUpperCase()}`,
                    },
                },
                responsive: true
            }
        });

        $("#xchvalue_cur").text(data[data.length-1] + " " + defaultCurrency.toUpperCase());
        $("#xchvalue_min").text(Math.min.apply(Math,data) + " " + defaultCurrency.toUpperCase());
        $("#xchvalue_max").text(Math.max.apply(Math,data) + " " + defaultCurrency.toUpperCase());
        $("#xchvalue_avg").text((data.reduce((a,b) => parseInt(a) + parseInt(b), 0) / data.length).toFixed(2) + " " + defaultCurrency.toUpperCase());
        $("#xchvalue_gro").text((parseInt(data[data.length-1])/parseInt(data[0])*100-100).toFixed(2) + "%");
    }
}

function addNewData(data){
    var querydate = data["querydate"];
    var netspace = data["netspace"].split(" ")[0];
    var xch_blockheight = data["xch_blockheight"];
    var price_usd = (parseInt(data["price_usd"])*exchangerate).toFixed(2);

    if(!$.inArray(querydate, historynetspacechart.data.labels)){
        historynetspacechart.data.labels.push(querydate);
        historyblockheightchart.data.labels.push(querydate);
        historyxchValuechart.data.labels.push(querydate);
    
        historynetspacechart.data.datasets.forEach((dataset) => {
            dataset.data.push(netspace);
        });
    
        historyblockheightchart.data.datasets.forEach((dataset) => {
            dataset.data.push(xch_blockheight);
        });
    
        historyxchValuechart.data.datasets.forEach((dataset) => {
            dataset.data.push(price_usd);
        });
    
        historynetspacechart.update();
        historyblockheightchart.update();
        historyxchValuechart.update();
    }
}

function messagesTrigger(data){
    var key = Object.keys(data);

    if(data[key]["status"] == 0){
        if(key == "getNetspaceHistory"){
            historynetspacechart.destroy();
            historynetspace = data[key]["data"];
            drawHistoryNetspaceChart(); 
        }else if(key == "getBlockheightHistory"){
            historyblockheightchart.destroy();
            historyblockheight = data[key]["data"];
            drawHistoryBlockheightChart(); 
        }else if(key == "getXCHValueHistory"){
            historyxchValuechart.destroy();
            historyXCHValue = data[key]["data"];
            drawHistoryXCHValueChart(); 
        }else if(key == "queryOverallData"){
            addNewData(data[key]["data"]);
        }
    }else{
        showMessage(1, data[key]["message"]);
    }
  }