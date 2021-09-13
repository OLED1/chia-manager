$("#refreshSystemInfo").off("click");
$("#refreshSystemInfo").on("click", function(){
  $("#card-system").load(frontend + "/sites/main_overview/templates/card-system.php");
});
