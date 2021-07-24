<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Loginstring missing</title>

    <!-- Custom fonts for this template-->
    <link href="frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="frameworks/bootstrap/css/sb-admin-2.min.css" rel="stylesheet">

</head>

<body class="bg-gradient-primary">

    <div class="container">

        <!-- Outer Row -->
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-6 col-md-6">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <!-- Nested Row within Card Body -->
                        <div class="row">
                            <div class="col">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="text-gray-900 mb-4">Loginstring not valid or missing</h1>
                                        <p>The loginpage can only be accessed using a login key.<br>
                                        Please state your personal login string:</p>
                                        <input type="text" class="form-control form-control-user"
                                            id="loginString" placeholder="Loginstring">
                                    </div>
                                    <br>
                                    <a id="proceedtologin" href="login.php" class="btn btn-primary btn-user btn-block">
                                      Proceed to login
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap core JavaScript-->
    <script src="frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
    <script src="frameworks/bootstrap/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="frameworks/bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="frameworks/bootstrap/js/sb-admin-2.min.js"></script>


    <script>
      $(function(){
        $("#proceedtologin").on("click", function(e){
          e.preventDefault();
          var authkey = $("#loginString").val();
          $(location).attr('href','login.php?authkey=' + authkey);
        });
      });
    </script>
</body>

</html>
