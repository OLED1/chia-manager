var from;
var to;
var loadCharts = [];
var filesystemsCharts = [];
var memoryswapCharts = [];

initAndCreateLoadCharts();
initAndCreateRAMCharts();
initAndCreateSWAPCharts();
initAndCreateBuffersCharts();
initAndCreateFilesystemsChart();

$(".datepicker").datetimepicker({
    format: 'Y-m-d H:i:s'
});

$("#filter-apply").on("click", function(){
    from = $("#filter-from").val();
    to = $("#filter-to").val();
    var from_date = new Date(from);
    var to_date = new Date(to);
    hourspast = (Math.abs(from_date - to_date) / 36e5).toFixed(2);
    
    if(from_date < to_date){
        $.each(nodes, function(arrkey, nodedata){
            var nodeid = nodedata["nodeid"];
            var html = 
                "<div class='card shadow mb-4'>" +
                    "<div class='card-body'>" +
                    "There is currently no history nodedata to show.<br>" +
                    "There is either no node configured or you may need to wait for at least 24 hours so the instance can query more data.<br>" +
                    "If you think there should be data and this is a system fault, please open a ticket on github." +
                    "</div>" +
                "</div>";
    
                
            $("#cpuinfo_" + nodeid).html(html);
            $("#meminfoinfo_" + nodeid).html(html);
            $("#filesystems_" + nodeid).html(html);
        });

        sendToWSS("ownRequest", "ChiaMgmt\\System_Statistics\\System_Statistics_Api", "System_Statistics_Api", "getSystemsLoadHistory", { "from" : from, "to" : to });
        sendToWSS("ownRequest", "ChiaMgmt\\System_Statistics\\System_Statistics_Api", "System_Statistics_Api", "getFilesystemsHistory", { "from" : from, "to" : to });
        sendToWSS("ownRequest", "ChiaMgmt\\System_Statistics\\System_Statistics_Api", "System_Statistics_Api", "getRAMSwapHistory", { "from" : from, "to" : to });
        sendToWSS("ownRequest", "ChiaMgmt\\System_Statistics\\System_Statistics_Api", "System_Statistics_Api", "getNodeUPAndServicesHistory", { "from" : from, "to" : to });
    }else{
        showMessage(1, "The from-date must be in the past to to-date.");
    }
});

function initAndCreateLoadCharts(){
    $.each(historySystemsLoadData, function(nodeid, loadData){
        if(loadData.length == 0) return;

        var labels = [];
        var data = [];
        data["load1min"] = [];
        data["load5min"] = [];
        data["load15min"] = [];
        data["max_load"] = [];
        data["preferred_max_load"] = [];
        $.each(loadData, function(arrkey, loadsinfo){
            labels.push(loadsinfo["timestamp"]);
            data["load1min"].push(loadsinfo["load_1min"]);
            data["load5min"].push(loadsinfo["load_5min"]);
            data["load15min"].push(loadsinfo["load_15min"]);
            data["max_load"].push(parseInt(loadsinfo["cpu_count"]) * 2);
            data["preferred_max_load"].push(loadsinfo["cpu_count"]);
        });
        
        var thisloadChartctx = $("#sysinfo_loads_chart-" + nodeid);
        if(thisloadChartctx.length == 0){
            var carddata = { "from" : from, "to" : to, "nodeid" : nodeid};
            $.get(frontend + "/sites/systems_statistics/templates/load_chart_card.php", carddata, function(response) {
              $('#cpuinfo_' + nodeid).html(response);
              thisloadChartctx = $("#sysinfo_loads_chart-" + nodeid);
              setTimeout(function(){
                drawLoadChart(nodeid, thisloadChartctx, labels, data);
              }, 100);
            });
        }else{
            drawLoadChart(nodeid, thisloadChartctx, labels, data);
        }
    });
}

function drawLoadChart(nodeid, thisloadChartctx, labels, data){
    if(!(nodeid in loadCharts)) loadCharts[nodeid] = {};
    loadCharts[nodeid] = new Chart(thisloadChartctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: "Max load",
                data: data["max_load"],
                borderColor: 'rgba(245, 39, 39, 1)',
                borderDash: [5, 5],
                borderwidth: 1,
                fill: true
            },
            {
                label: "Preferred max load",
                data: data["preferred_max_load"],
                borderColor: 'rgba(245, 142, 39, 1)',
                borderDash: [5, 5],
                borderwidth: 1,
                fill: false
            },
            {
                label: "Load 1min",
                data: data["load1min"],
                borderColor: 'rgba(129, 245, 39, 1)',
                borderwidth: 1,
                fill: false
            },
            {
                label: "Load 5min",
                data: data["load5min"],
                borderColor: 'rgba(39, 245, 78, 1)',
                borderwidth: 1,
                fill: false
            },
            {
                label: "Load 15min",
                data: data["load15min"],
                borderColor: 'rgba(39, 245, 156, 1)',
                borderwidth: 1,
                fill: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            resizeDelay: 50,
            interaction: {
              mode: 'index',
              intersect: false,
            },
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: "1min/5min/15min CPU load chart (last " + hourspast + " hours)",
                    color: chartcolor
                },
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
                        color: chartcolor
                    }
                },
                x: {
                    ticks : {
                        color: chartcolor
                    } 
                }
            }
        }
    });
    $("#load1min_cur-" + nodeid).text(data["load1min"][data["load1min"].length-1]);
    $("#load1min_min-" + nodeid).text(Math.min.apply(Math,data["load1min"]));
    $("#load1min_max-" + nodeid).text(Math.max.apply(Math,data["load1min"]));
    $("#load1min_avg-" + nodeid).text((data["load1min"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["load1min"].length).toFixed(2));

    $("#load5min_cur-" + nodeid).text(data["load5min"][data["load5min"].length-1]);
    $("#load5min_min-" + nodeid).text(Math.min.apply(Math,data["load5min"]));
    $("#load5min_max-" + nodeid).text(Math.max.apply(Math,data["load5min"]));
    $("#load5min_avg-" + nodeid).text((data["load5min"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["load5min"].length).toFixed(2));

    $("#load15min_cur-" + nodeid).text(data["load15min"][data["load15min"].length-1]);
    $("#load15min_min-" + nodeid).text(Math.min.apply(Math,data["load15min"]));
    $("#load15min_max-" + nodeid).text(Math.max.apply(Math,data["load15min"]));
    $("#load15min_avg-" + nodeid).text((data["load15min"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["load15min"].length).toFixed(2)); 
}

function initAndCreateRAMCharts(){
    $.each(historyMemoryData, function(nodeid, memorydata){
        if(memorydata.length == 0) return;

        var labels = [];
        var data = [];
        data["total"] = [];
        data["used"] = [];
        $.each(memorydata, function(arrkey, memoryinfo){
            labels.push(memoryinfo["timestamp"]);
            data["total"].push((parseInt(memoryinfo["memory_total"])/1024/1024/1024).toFixed(2));
            var free = parseInt(memoryinfo["memory_free"]) + parseInt(memoryinfo["memory_buffers"]) + parseInt(memoryinfo["memory_cached"]) - parseInt(memoryinfo["memory_shared"]);
            data["used"].push(((parseInt(memoryinfo["memory_total"]) - free)/1024/1024/1024).toFixed(2));
        });

        var thisramChartctx = $("#sysinfo_memory_chart-" + nodeid);
        if(thisramChartctx.length == 0){
            var carddata = { "from" : from, "to" : to, "nodeid" : nodeid};
            $.get(frontend + "/sites/systems_statistics/templates/memory_chart_card.php", carddata, function(response) {
              $('#meminfoinfo_' + nodeid).html(response);
              thisramChartctx = $("#sysinfo_memory_chart-" + nodeid);
              setTimeout(function(){
                drawRAMChart(nodeid, thisramChartctx, labels, data);
              }, 100);
            });
        }else{
            drawRAMChart(nodeid, thisramChartctx, labels, data);
        }
    });
}

function drawRAMChart(nodeid, thisramChartctx, labels, data){
    if(!(nodeid in memoryswapCharts)) memoryswapCharts[nodeid] = {};
    memoryswapCharts[nodeid]["memory"] = new Chart(thisramChartctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
            {
                label: "Total",
                data: data["total"],
                borderColor: 'rgba(255, 39, 39, 0.8)',
                borderwidth: 1,
                fill: true
            },{
                label: "Used",
                data: data["used"],
                borderColor: 'rgba(255, 218, 80, 1)',
                borderwidth: 1,
                fill: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            resizeDelay: 50,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: "Memory usage chart (last " + hourspast + " hours)",
                    color: chartcolor
                },
                legend: {
                    display: true,
                    labels: {
                        color: chartcolor
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context){
                            const label = context.dataset.label || '';
                            return label + ": " + context.formattedValue + " GB";
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks : {
                        callback: function(value, index, values) {
                            return value.toFixed(2) + " GB";
                        },
                        color: chartcolor
                    }
                },
                x: {
                    ticks : {
                        color: chartcolor
                    } 
                }
            }
        }
    });
    $("#total_ram_cur-" + nodeid).text(data["total"][data["total"].length-1] + " GB");
    $("#total_ram_min-" + nodeid).text(Math.min.apply(Math,data["total"]) + " GB");
    $("#total_ram_max-" + nodeid).text(Math.max.apply(Math,data["total"]) + " GB");
    $("#total_ram_avg-" + nodeid).text((data["total"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["total"].length).toFixed(2) + " GB");

    $("#used_ram_cur-" + nodeid).text(data["used"][data["used"].length-1] + " GB");
    $("#used_ram_min-" + nodeid).text(Math.min.apply(Math,data["used"]) + " GB");
    $("#used_ram_max-" + nodeid).text(Math.max.apply(Math,data["used"]) + " GB");
    $("#used_ram_avg-" + nodeid).text((data["used"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["used"].length).toFixed(2) + " GB");
}

function initAndCreateSWAPCharts(){
    $.each(historyMemoryData, function(nodeid, swapdata){
        if(swapdata.length == 0) return;

        var labels = [];
        var data = [];
        data["total"] = [];
        data["used"] = [];
        $.each(swapdata, function(arrkey, memoryinfo){
            labels.push(memoryinfo["timestamp"]);
            data["total"].push((parseInt(memoryinfo["swap_total"])/1024/1024/1024).toFixed(2));
            data["used"].push(((parseInt(memoryinfo["swap_total"]) - parseInt(memoryinfo["swap_free"]))/1024/1024/1024).toFixed(2));
        });

        var thisswapChartctx = $("#sysinfo_swap_chart-" + nodeid);
        if(thisswapChartctx.length == 0){
            setTimeout(function(){
                thisswapChartctx = $("#sysinfo_swap_chart-" + nodeid);
                drawSwapChart(nodeid, thisswapChartctx, labels, data);
            }, 200);
        }else{
            drawSwapChart(nodeid, thisswapChartctx, labels, data);
        }
    });
}

function drawSwapChart(nodeid, thisswapChartctx, labels, data){
    if(!(nodeid in memoryswapCharts)) memoryswapCharts[nodeid] = {};
    memoryswapCharts[nodeid]["swap"] = new Chart(thisswapChartctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
            {
                label: "Total",
                data: data["total"],
                borderColor: 'rgba(255, 39, 39, 0.8)',
                borderwidth: 1,
                fill: true
            },{
                label: "Used",
                data: data["used"],
                borderColor: 'rgba(255, 218, 80, 1)',
                borderwidth: 1,
                fill: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            resizeDelay: 50,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: "SWAP usage chart (last " + hourspast + " hours)",
                    color: chartcolor
                },
                legend: {
                    display: true,
                    labels: {
                        color: chartcolor
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context){
                            const label = context.dataset.label || '';
                            return label + ": " + context.formattedValue + " GB";
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks : {
                        callback: function(value, index, values) {
                            return value.toFixed(2) + " GB";
                        },
                        color: chartcolor
                    }
                },
                x: {
                    ticks : {
                        color: chartcolor
                    } 
                }
            }
        }
    });
    $("#total_swap_cur-" + nodeid).text(data["total"][data["total"].length-1] + " GB");
    $("#total_swap_min-" + nodeid).text(Math.min.apply(Math,data["total"]) + " GB");
    $("#total_swap_max-" + nodeid).text(Math.max.apply(Math,data["total"]) + " GB");
    $("#total_swap_avg-" + nodeid).text((data["total"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["total"].length).toFixed(2) + " GB");

    $("#used_swap_cur-" + nodeid).text(data["used"][data["used"].length-1] + " GB");
    $("#used_swap_min-" + nodeid).text(Math.min.apply(Math,data["used"]) + " GB");
    $("#used_swap_max-" + nodeid).text(Math.max.apply(Math,data["used"]) + " GB");
    $("#used_swap_avg-" + nodeid).text((data["used"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["used"].length).toFixed(2) + " GB");
}

function initAndCreateBuffersCharts(){
    $.each(historyMemoryData, function(nodeid, cachesdata){
        if(cachesdata.length == 0) return;

        var labels = [];
        var data = [];
        data["buffers"] = [];
        data["cached"] = [];
        data["shared"] = [];
        $.each(cachesdata, function(arrkey, memoryinfo){
            labels.push(memoryinfo["timestamp"]);
            data["buffers"].push((parseInt(memoryinfo["memory_buffers"])/1024/1024).toFixed(2));
            data["cached"].push((parseInt(memoryinfo["memory_cached"])/1024/1024).toFixed(2));
            data["shared"].push((parseInt(memoryinfo["memory_shared"])/1024/1024).toFixed(2));
        });

        var thiscacheChartctx = $("#sysinfo_caches_chart-" + nodeid);
        if(thiscacheChartctx.length == 0){
            setTimeout(function(){
                thiscacheChartctx = $("#sysinfo_caches_chart-" + nodeid);
                drawBuffersChart(nodeid, thiscacheChartctx, labels, data);
            }, 200);
        }else{
            drawBuffersChart(nodeid, thiscacheChartctx, labels, data);
        }
    });
}

function drawBuffersChart(nodeid, thiscacheChartctx, labels, data){
    if(!(nodeid in memoryswapCharts)) memoryswapCharts[nodeid] = {};
    memoryswapCharts[nodeid]["caches"] = new Chart(thiscacheChartctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
            {
                label: "Buffers",
                data: data["buffers"],
                borderColor: 'rgba(255, 156, 80, 1)',
                backgroundColor: 'rgba(165, 165, 165, 0.2)',
                borderwidth: 1,
                fill: true
            },{
                label: "Cached",
                data: data["cached"],
                borderColor: 'rgba(0, 255, 133, 1)',
                borderwidth: 1,
                fill: false
            },{
                label: "Shared",
                data: data["shared"],
                borderColor: 'rgba(255, 218, 80, 1)',
                borderwidth: 1,
                fill: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            resizeDelay: 50,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: "Cached Memory chart (last " + hourspast + " hours)",
                    color: chartcolor
                },
                legend: {
                    display: true,
                    labels: {
                        color: chartcolor
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context){
                            const label = context.dataset.label || '';
                            return label + ": " + context.formattedValue + " MB";
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks : {
                        callback: function(value, index, values) {
                            return value.toFixed(2) + " MB";
                        },
                        color: chartcolor
                    }
                },
                x: {
                    ticks : {
                        color: chartcolor
                    } 
                }
            }
        }
    });

    $("#buffers_cur-" + nodeid).text(data["buffers"][data["buffers"].length-1] + " MB");
    $("#buffers_min-" + nodeid).text(Math.min.apply(Math,data["buffers"]) + " MB");
    $("#buffers_max-" + nodeid).text(Math.max.apply(Math,data["buffers"]) + " MB");
    $("#buffers_avg-" + nodeid).text((data["buffers"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["buffers"].length).toFixed(2) + " MB");

    $("#cached_cur-" + nodeid).text(data["cached"][data["cached"].length-1] + " MB");
    $("#cached_min-" + nodeid).text(Math.min.apply(Math,data["cached"]) + " MB");
    $("#cached_max-" + nodeid).text(Math.max.apply(Math,data["cached"]) + " MB");
    $("#cached_avg-" + nodeid).text((data["cached"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["cached"].length).toFixed(2) + " MB");

    $("#shared_cur-" + nodeid).text(data["shared"][data["shared"].length-1] + " MB");
    $("#shared_min-" + nodeid).text(Math.min.apply(Math,data["shared"]) + " MB");
    $("#shared_max-" + nodeid).text(Math.max.apply(Math,data["shared"]) + " MB");
    $("#shared_avg-" + nodeid).text((data["shared"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / data["shared"].length).toFixed(2) + " MB");
}

function initAndCreateFilesystemsChart(){
    $.each(historyFilesystemData, function(nodeid, filesystemdata){
        if(filesystemdata.length == 0) return;
        var redrawn = false;

        var labels = [];
        var data = {};
        var sizes = [];
        $.each(filesystemdata, function(mounted_on, thismountdata){
            $.each(thismountdata, function(arrkey, thisfsdata){
                if(!(mounted_on in data)) data[mounted_on] = {};
                
                if(!("max" in data[mounted_on])) data[mounted_on]["max"] = [];
                var max_formatted = formatKBytes(thisfsdata["size"]);
                data[mounted_on]["max"].push(max_formatted["formatted_size"]);
                
                if(!("used" in data[mounted_on])) data[mounted_on]["used"] = [];
                data[mounted_on]["used"].push(forceFormatTo((thisfsdata["size"] - thisfsdata["avail"]), max_formatted["formatted_string"]));
                
                if(!("unit" in data[mounted_on])) data[mounted_on]["unit"] = [];
                data[mounted_on]["unit"].push(max_formatted["formatted_string"]);

                if(!("filesystem" in data[mounted_on])) data[mounted_on]["filesystem"] = [];
                data[mounted_on]["filesystem"].push(thisfsdata["device"]);

                if(!("labels" in data[mounted_on])) data[mounted_on]["labels"] = [];
                data[mounted_on]["labels"].push(thisfsdata["timestamp"]);
            });
        });

        $.each(data, function(mounted_on, fsdata){
            var thisfilesystemchartctx = $(".sysinfo_filesystem_chart_" + nodeid + "[data-mounted-on='" + mounted_on + "']");
            if(thisfilesystemchartctx.length == 0){
                if(!redrawn){
                    redrawn = true;
                    var carddata = { "from" : from, "to" : to, "nodeid" : nodeid};
                    $.get(frontend + "/sites/systems_statistics/templates/filesystems_chart_card.php", carddata, function(response) {
                      $('#filesystems_' + nodeid).html(response);
                    });
                }
                setTimeout(function(){
                    thisfilesystemchartctx = $(".sysinfo_filesystem_chart_" + nodeid + "[data-mounted-on='" + mounted_on + "']");
                    drawFilesystemChart(nodeid, thisfilesystemchartctx, mounted_on, fsdata);
                }, 200);
            }else{
                drawFilesystemChart(nodeid, thisfilesystemchartctx, mounted_on, fsdata);
            }
        });
    });
}

function drawFilesystemChart(nodeid, thisfilesystemchartctx, mounted_on, fsdata){
    if(!(nodeid in filesystemsCharts)) filesystemsCharts[nodeid] = {};
    filesystemsCharts[nodeid][mounted_on] = new Chart(thisfilesystemchartctx, {
        type: 'line',
        data: {
            labels: fsdata["labels"],
            datasets: [
            {
                label: "Size",
                data: fsdata["max"],
                borderColor: 'rgba(255, 39, 39, 0.8)',
                backgroundColor: 'rgba(165, 165, 165, 0.2)',
                borderwidth: 1,
                fill: true
            },{
                label: "Used",
                data: fsdata["used"],
                borderColor: 'rgba(255, 218, 80, 1)',
                borderwidth: 1,
                fill: false
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            resizeDelay: 50,
            interaction: {
              mode: 'index',
              intersect: false,
            },
            stacked: false,
            plugins: {
                title: {
                    display: true,
                    text: "History of mount " +  fsdata["filesystem"][0] + " => " + mounted_on + " (last " + hourspast + " hours)",
                    color: chartcolor
                },
                legend: {
                    display: true,
                    labels: {
                        color: chartcolor
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context){
                            const label = context.dataset.label || '';
                            return label + ": " + context.formattedValue + " " + fsdata["unit"][0];
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks : {
                        callback: function(value, index, values) {
                            return value.toFixed(2) + " " + fsdata["unit"][0];
                        },
                        color: chartcolor
                    }
                },
                x: {
                    ticks : {
                        color: chartcolor
                    } 
                }
            }
        }
    });

    $("#filesystem_cur_size_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(fsdata["max"][fsdata["max"].length-1] + " " + fsdata["unit"][0]);
    $("#filesystem_min_size_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(Math.min.apply(Math,fsdata["max"]) + " " + fsdata["unit"][0]);
    $("#filesystem_max_size_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(Math.max.apply(Math,fsdata["max"]) + " " + fsdata["unit"][0]);
    $("#filesystem_avg_size_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text((fsdata["max"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / fsdata["max"].length).toFixed(2) + " " + fsdata["unit"][0]);            

    $("#filesystem_cur_used_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(fsdata["used"][fsdata["used"].length-1] + " " + fsdata["unit"][0]);
    $("#filesystem_min_used_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(Math.min.apply(Math,fsdata["used"]) + " " + fsdata["unit"][0]);
    $("#filesystem_max_used_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text(Math.max.apply(Math,fsdata["used"]) + " " + fsdata["unit"][0]);
    $("#filesystem_avg_used_" + nodeid + "[data-mounted-on='" + mounted_on + "']").text((fsdata["used"].reduce((a,b) => parseFloat(a) + parseFloat(b), 0) / fsdata["used"].length).toFixed(2) + " " + fsdata["unit"][0]);  
}

function formatKBytes(kbytes, decimals = 2) {
    var returnval = [];

    if (kbytes === 0){
        returnval["formatted_size"] = 0;
        returnval["formatted_string"] = "KB";
    }else{
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
        const i = Math.floor(Math.log(kbytes) / Math.log(k));
    
        returnval["formatted_size"] = parseFloat((kbytes / Math.pow(k, i)).toFixed(dm));
        returnval["formatted_string"] = sizes[i];
        //return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    return returnval;
}

function forceFormatTo(kbytes, targetsize, decimals = 2){
    if(kbytes == 0) return kbytes;

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    var i = $.inArray(targetsize, sizes);

    return parseFloat((kbytes / Math.pow(k, i)).toFixed(dm));
}

function destroyLoadCharts(){
    $.each(loadCharts, function(nodeid, thischart){
        if(thischart !== undefined) thischart.destroy();
    });
}

function destroyFilesystemsCharts(){
    $.each(filesystemsCharts, function(nodeid, fscharts){
        $.each(fscharts, function(mount_on, thischart){
            if(thischart !== undefined) thischart.destroy();
        });
    }); 
}

function destroyRAMSwapCharts(){
    $.each(memoryswapCharts, function(nodeid, rsccharts){
        $.each(rsccharts, function(chartype, thischart){
            if(thischart !== undefined) thischart.destroy();
        });
    });
}

function messagesTrigger(data){
    var key = Object.keys(data);

    if(data[key]["status"] == 0){
        if(key == "getSystemsLoadHistory"){
            destroyLoadCharts();
            historySystemsLoadData = data[key]["data"];
            setTimeout(function(){
                initAndCreateLoadCharts();
            }, 500);    
        }else if(key == "getFilesystemsHistory"){
            destroyFilesystemsCharts();
            historyFilesystemData = data[key]["data"];
            setTimeout(function(){
                initAndCreateFilesystemsChart();
            }, 700); 
        }else if(key == "getRAMSwapHistory"){
            destroyRAMSwapCharts();
            historyMemoryData = data[key]["data"];
            setTimeout(function(){
                initAndCreateRAMCharts();
                initAndCreateSWAPCharts();
                initAndCreateBuffersCharts();
            }, 500); 
        }else if(key == "getNodeUPAndServicesHistory"){
            $.each(data[key]["data"], function(nodeid, service_data){
                var card_data = { "from" : from, "to" : to, "nodeid" : nodeid };
                $.get(frontend + "/sites/systems_statistics/templates/services_chart_card.php", card_data, function(response) {
                  $('#services_' + nodeid).html(response);
                });
            });
        }
    }else{
        showMessage(1, data[key]["message"]);
    }
  }