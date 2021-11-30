var rowfrom = 0;
var rowto = 0;

var htmlEntityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

var loggingTable = $("#loggingTable").DataTable({
  "initComplete": function(settings, json) {
    var info = $(this).DataTable().page.info();
    rowto = info["length"] * 2;

    sendToWSS("ownRequest", "ChiaMgmt\\Logging\\Logging_Api", "Logging_Api", "getMessagesFromFile", { "fromline": rowfrom, "toline" : rowto });
  },
  "bStateSave": true,
  "columnDefs": [
    { "width": "10%", "targets": [0,1,2] }
  ],
  "order": [[ 0, "desc" ]],
  "createdRow": function( row, data, dataIndex){
    if( data[1] ==  `Info`){
        $(row).addClass('loglevel-info');
    }else if( data[1] ==  `Warning`){
        $(row).addClass('loglevel-warn');
    }else if( data[1] ==  `Fatal`){
        $(row).addClass('loglevel-crit');
    }else if( data[1] ==  `Unknown`){
        $(row).addClass('loglevel-unkn');
    }
  },
  "lengthMenu": [[50, 100], [50, 100]]
}).on("page.dt", function(){
  var info = $(this).DataTable().page.info();
  if(info["page"]+1 == info["pages"]){
    rowfrom = rowfrom + 1;
    rowto = rowto + info["length"];
    sendToWSS("ownRequest", "ChiaMgmt\\Logging\\Logging_Api", "Logging_Api", "getMessagesFromFile", { "fromline": rowfrom, "toline" : rowto });
  }
}).on( 'length.dt', function ( e, settings, len ) {
  rowfrom = rowfrom + 1;
  rowto = rowto + len;
  sendToWSS("ownRequest", "ChiaMgmt\\Logging\\Logging_Api", "Logging_Api", "getMessagesFromFile", { "fromline": rowfrom, "toline" : rowto });
} );


$(".level_check").on("change",function(){
  var loglevels = $(".level_check:checked").map(function(){
    return this.value;
  }).get().join('|');

  loggingTable.column(1).search((loglevels.length == 0 ? "-" : loglevels),true,false).draw();
});

function messagesTrigger(data){
  var key = Object.keys(data);

  if(data[key]["status"] == 0){
    if(key == "getMessagesFromFile"){
      addLogLines(data[key]["data"]);
      rowfrom = rowto;
    }else if(key == "logsChanged"){
      addLogLines(data[key]["data"]);
      rowfrom+=1;
      rowto+=1;
    }
  }
}

function formatLoglevel(loglevel){
  switch(parseInt(loglevel)) {
    case 0:
      return "Info";
    case 1:
      return "Warning";
    case 2:
      return "Fatal";
    case 3:
      return "Unkown";
    default:
      return "Unkown";
  }
}

function escapeHtml(string) {
  return String(string).replace(/[&<>"'`=\/]/g, function (s) {
    return htmlEntityMap[s];
  });
}

function addLogLines(logdata){
  $.each(logdata, function(arrkey, logentry){
    var rowNode = loggingTable
    .row.add( [ logentry[0], formatLoglevel(logentry[1]), logentry[2], escapeHtml(logentry[3]), logentry[4] ] )
    .draw(false)
    .node();
  });
}
