<?php include("db.php"); 
include("emailSMTP.php");
if(isset($_POST['accActiveBtn'])){
if(trim($_POST['activationC'])!=''){

$getCheck = mysqli_query($mysqli,"select id from tbl_logindetail where enCryptUrl = '".$_POST['ch']."' and activationCode = '".$_POST['activationC']."' and activationStatus = 'f' ");
if(mysqli_num_rows($getCheck)>0){
$getId = mysqli_fetch_array($getCheck); 
$doUpdate = mysqli_query($mysqli,"update tbl_logindetail set activationStatus = 't', activationCode = '' where id = '".$getId['id']."' ");
$_SESSION['mess'] = 'Account has been activate successfully.';
header('location:signup.php'); exit();
} else {
$_SESSION['mess'] = 'Account already activated.';
header('location:signup.php'); exit();
}
}}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Twitter -->
    <!-- <meta name="twitter:site" content="@bootstrapdash">
    <meta name="twitter:creator" content="@bootstrapdash">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Azia">
    <meta name="twitter:description" content="Responsive Bootstrap 4 Dashboard Template">
    <meta name="twitter:image" content="https://www.bootstrapdash.com/azia/img/azia-social.png"> -->

    <!-- Facebook -->
    <!-- <meta property="og:url" content="https://www.bootstrapdash.com/azia">
    <meta property="og:title" content="Azia">
    <meta property="og:description" content="Responsive Bootstrap 4 Dashboard Template">

    <meta property="og:image" content="https://www.bootstrapdash.com/azia/img/azia-social.png">
    <meta property="og:image:secure_url" content="https://www.bootstrapdash.com/azia/img/azia-social.png">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="600"> -->

    <!-- Meta -->
    <meta name="description" content="Responsive Bootstrap 4 Dashboard Template">
    <meta name="author" content="BootstrapDash">

    <title><?php echo $Address;?></title>

    <!-- vendor css -->
    <link href="lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="lib/ionicons/css/ionicons.min.css" rel="stylesheet">
    <link href="lib/typicons.font/typicons.css" rel="stylesheet">

    <!-- azia CSS -->
    <link rel="stylesheet" href="css/azia.css">

  </head>
  <body class="az-body">
  <style type="text/css">
  body {
  padding:0px;
  background-image: url('images/topImg.jpg'); z-index: 99999 !important;
  background-position:top !important;
  background-repeat:repeat-x !important;
  }
  </style>
	<div class="az-signup-wrapper1">
	<br />
    <h2 style="padding:25px;">Activate Account</h2>

	<div align="center" class="container-xxl container-p-y">
      <div class="misc-wrapper"><img src="images/logo.png" width="150"><br><br />
        <h5 class="mb-4 mx-2" style="color:#000066" >
		<form action="" method="post">
		<input type="hidden" name="ch" value="<?php echo $_REQUEST['acDet']?>" >
		Activate Code: <input type="tel" required minlength="6" maxlength="6" name="activationC" placeholder="Enter your activation code" ></h5>
        <button class="btn btn-az-primary" name="accActiveBtn">Submit</button> <a href="signup.php" class="btn btn-success">Back to home</a>
		</form>
        <div class="mt-4">
        </div>
      </div>
    </div>
          
		  
		  
      </div>
    </div><!-- az-signup-wrapper -->


  </body>
</html>
