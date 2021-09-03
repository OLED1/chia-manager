$(function(){
  var cntrlIsPressed = false;
  var href = document.location.href;
  var subpage = href.split("index.php")[1];

  if(subpage == "" || subpage == "/" || subpage == undefined){
    loadPage("/sites/main_overview/","Dashboard");
  }else{
    loadPage(subpage,"");
  }

  $(document).keydown(function(event){
    if(event.which=="17") cntrlIsPressed = true;
  });

  $(document).keyup(function(){
    cntrlIsPressed = false;
  });

  //$(".nav a").on("click",function(e){
  $("a").on("click",function(e){
    e.preventDefault();
    var link = $(this).attr("href");

    if($(this).hasClass("externallink")){
      window.open($(this).attr("href"));
      return false;
    }

    if(cntrlIsPressed){
      var win = window.open(frontend+"/index.php"+link, '_blank');
      if (win) {
        win.focus();
      }else {
        showMessage(0, "Please allow popups to be able to open new tabs.");
      }
      cntrlIsPressed = false;
    }else{
      var pname = $(this).find("span").text();
      var clickeditem = $(this);

      if(link != "#" && link != "") loadPage(link, pname, clickeditem);
      $("#messagecontainer").children().fadeOut();
    }
  });

  /*$(window).on('popstate', function() {
    var pathname = window.location.pathname;
    pathname = pathname.split("index.php")[1];
    loadPage(pathname);
  });*/

  function loadPage(href, sitename, clickeditem){
    if(clickeditem != undefined){
      var siteID = clickeditem.attr("data-siteid");
    }else{
      var siteID = 1;
    }

    //showLoadingDialog();
    $("#sitecontent").children().remove();
    $(".breadcrumb-item a").text(sitename);

    if(!history.pushState){
      document.location.href = frontend + "/index.php" + href + "/";
    }

    var url = backend + '/core/Login/Login_Rest.php';
    var type = "POST";
    var action = "checklogin";

    $.ajax({
      url: url,
      type: type,
      dataType: 'JSON',
      encode: true,
      data: {
        action: action
      },
      beforeSend: function(){
        showLoadingModal();
      },
      success: function (result, status, xhr) {
        if(result["status"] == 0){
          $("#sitecontent").load(frontend+href, function(response, status, xhr) {
            if(status == "error"){
              showMessage(2, "Site " + href + " is not existing or has an error.");
              loadPage("/sites/notfound/","Site not found");
            }else if(result["status"] == "001005005"){
              $(location).attr('href',frontend + '/login.php');
            }else{
              $("#accordionSidebar .active").removeClass("active");
              if(clickeditem != undefined){
                clickeditem.closest(".nav-item").addClass("active");
              }else{
                $(".nav-item").first().addClass("active");
              }

              $('head title', window.parent.document).text("Chia Manager - " + sitename);
              if (history.pushState) window.history.pushState("", "Chia Manager - " + sitename, frontend + "/index.php" + href);
            }

            var data = {
              userID : userID,
              siteID : siteID
            }

            sendToWSS("updateFrontendViewingSite", "", "", "", data);
            setTimeout( function(){ hideLoadingModal(); }, 600);
            if($("#wsstatus").attr("data-connected") == 1){
              enableWSButtons();
            }else{
              disableWSButtons();
            }
          });
        }
      },
      error: function(xhr, status, error){
        showMessage(2, error);
        hideLoadingModal();
      }
    });
  }

  function showLoadingDialog(){
    $('#laoadingDialog').modal('show');
  }

  function hideLoadingDialog(){
    $('#laoadingDialog').modal('hide');
  }
});
