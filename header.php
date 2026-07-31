<?php include("db.php");
if($_SESSION['loginType']==''){
$_SESSION['mess'] = 'Please get login to view this page.';
header('location:signup.php'); exit();
}
/*echo "<pre>";
print_r($_SESSION);
echo "</pre>";*/
if($_SESSION['loginType']=='V'){
	$userId 	= $_SESSION['userDetails']['id'];
	$userName 	= $_SESSION['userDetails']['userName'];
	$userEmail 	= $_SESSION['userDetails']['userEmail'];
	$userMobile = $_SESSION['userDetails']['userMobile'];
	$userGender = $_SESSION['userDetails']['userGender'];
	$userAge 	= $_SESSION['userDetails']['userAge'];
	$userCompany= $_SESSION['userDetails']['userCompany'];
	$userDesig  = $_SESSION['userDetails']['userDesignation'];
} else if($_SESSION['loginType']=='E'){ 

	$userId 	= $_SESSION['userDetails']['empCode'];
	$userName 	= $_SESSION['userDetails']['empName'];
	$userEmail 	= $_SESSION['userDetails']['empBusiEmail'];
	$userMobile = $_SESSION['userDetails']['empBusiMobile'];
	$userGender = $_SESSION['userDetails']['empGender'];
	$userAge 	= $_SESSION['userDetails']['empAge'];
}
 else if($_SESSION['loginType']=='S'){
	$userId 	= $_SESSION['userDetails']['id'];
	$userName 	= $_SESSION['userDetails']['userName'];
	$userEmail 	= $_SESSION['userDetails']['userEmail'];
	$userMobile = $_SESSION['userDetails']['userMobile'];
	$userGender = $_SESSION['userDetails']['userGender'];
	$userAge 	= $_SESSION['userDetails']['userAge'];
	$userCompany= $_SESSION['userDetails']['userCompany'];
	$userDesig  = $_SESSION['userDetails']['userDesignation'];
}




if (!empty($_SESSION['profilePic'])) {  
  $proFilePic = "https://vms.nuvoco.in/vmsdb/serve_image.php?image=".$userId."_profile.webp"; 
} else { 
  
}

autoSetLiveStatus($_SESSION['loginId']);


  $filePath = __DIR__ . "/vmsdb/faces/{$userId}_profile.webp";

  // if the file exists, use its last-modified time as a version;
  // otherwise fall back to the current time (forces a fetch)
  $version = file_exists($filePath)
           ? filemtime($filePath)
           : time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Global site tag (gtag.js) - Google Analytics -->
<?php /*?>    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-90680653-2"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'UA-90680653-2');
    </script>
<?php */?>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Meta -->
    <meta name="description" content="Nuvoco Vistas Corp Ltd">
    <meta name="author" content="Nuvoco Vistas Corp Ltd">

    <title><?php echo $Address;?></title>
	<link rel="shortcut icon" type="image/png" href="images/Nuvoco.ico" />
    <!-- vendor css -->
    <link href="lib/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="lib/ionicons/css/ionicons.min.css" rel="stylesheet">
    <link href="lib/typicons.font/typicons.css" rel="stylesheet">
    <link href="lib/flag-icon-css/css/flag-icon.min.css" rel="stylesheet">

    <!-- azia CSS -->
    <link rel="stylesheet" href="css/azia.css">
	<script type="text/javascript">
	setInterval(function() { 
	$.ajax({ url: 'liveStatus.php' });
	}, 60 * 2000);
	</script>
  </head>
  <body>
    <div class="az-header">
      <div class="container">
        <div class="az-header-left">
          <a href="index.php" class="az-logo"><span></span><img src="images/nuvoco-ori.png" width="80"></a>
          <a href="" id="azMenuShow" class="az-header-menu-icon d-lg-none"><span></span></a>
        </div><!-- az-header-left -->
        <div class="az-header-menu">
          <div class="az-header-menu-header">
            <a href="index.php" class="az-logo"><span></span><img src="images/nuvoco-ori.png" width="80"></a>
            <a href="" class="close">&times;</a>
          </div><!-- az-header-menu-header -->
          <?php /*?><ul class="nav">
            <li class="nav-item active show">
              <a href="index.php" class="nav-link"><i class="typcn typcn-chart-area-outline"></i> Dashboard</a>
            </li>
            <li class="nav-item">
              <a href="" class="nav-link with-sub"><i class="typcn typcn-document"></i> Pages</a>
              <nav class="az-menu-sub">
                <a href="page-signin.html" class="nav-link">Sign In</a>
                <a href="page-signup.html" class="nav-link">Sign Up</a>              </nav>
            </li>
            <li class="nav-item">
              <a href="chart-chartjs.html" class="nav-link"><i class="typcn typcn-chart-bar-outline"></i> Charts</a>
            </li>
            <li class="nav-item">
              <a href="form-elements.html" class="nav-link"><i class="typcn typcn-chart-bar-outline"></i> Forms</a>
            </li>
            <li class="nav-item">
              <a href="" class="nav-link with-sub"><i class="typcn typcn-book"></i> Components</a>
              <div class="az-menu-sub">
                <div class="container">
                  <div>
                    <nav class="nav">
                      <a href="elem-buttons.html" class="nav-link">Buttons</a>
                      <a href="elem-dropdown.html" class="nav-link">Dropdown</a>
                      <a href="elem-icons.html" class="nav-link">Icons</a>
                      <a href="table-basic.html" class="nav-link">Table</a>
                    </nav>
                  </div>
                </div><!-- container -->
              </div>
            </li>
          </ul><?php */?>
        </div><!-- az-header-menu -->
       
          <div class="dropdown az-profile-menu">
              <a href="" class="az-img-user <?php echo getCurrentLive($_SESSION['loginId'])?>"><img 
  src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=<?= $userId; ?>_profile.webp&v=<?= $version; ?>" 
  alt="Profile Photo" 
/>
            </a>
            <div class="dropdown-menu">
              <div class="az-dropdown-header d-sm-none">
                <a href="" class="az-header-arrow"><i class="icon ion-md-arrow-back"></i></a>
              </div>
              <div class="az-header-profile">
                <div class="az-img-user">
               <img 
  src="https://vms.nuvoco.in/vmsdb/serve_image.php?image=<?= $userId; ?>_profile.webp&v=<?= $version; ?>" 
  alt="Profile Photo" 
/>

                </div><!-- az-img-user -->
                <strong style="font-size:16px;"><?php echo $userName?> </strong>
                <?php /*?><span>Premium Member</span><?php */?>
              </div><!-- az-header-profile -->

              <a href="myProfile.php" class="dropdown-item"><i class="typcn typcn-user-outline"></i> My Profile</a>
              <a href="newMeeting2.php" class="dropdown-item"><i class="typcn typcn-user-add-outline"></i> New Meeting</a>
			  <?php if($_SESSION['loginType']=='V'){?>
			  <a href="updatePersonalInfo.php" class="dropdown-item"><i class="typcn typcn-edit"></i> Edit Profile</a>
			  <?php } ?>
              <?php /*?><a href="" class="dropdown-item"><i class="typcn typcn-time"></i> Activity Logs</a>
              <a href="" class="dropdown-item"><i class="typcn typcn-cog-outline"></i> Account Settings</a><?php */?>
              <a href="logout.php" class="dropdown-item"><i class="typcn typcn-power-outline"></i> Sign Out</a>
            </div><!-- dropdown-menu -->
          </div>
        </div><!-- az-header-right -->
      </div><!-- container -->
    </div><!-- az-header -->
<?php if($_SESSION['mess']!=''){?>
<script type="text/javascript">
//$('.alert').animate({ left: this.x, top: this.y },"linear");
//$(".alert").delay(1500).show('slow');
setTimeout(function() { $('.alert').show('slow'); }, 8000);
</script>
<div class="alert alert-primary alert-dismissible fade show" role="alert">
  <strong>Hi <?php echo $userName;?>!</strong> <?php echo $_SESSION['mess']?>
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
</div> 
<?php unset($_SESSION['mess']); }?>  
<script type="text/javascript">
setTimeout(function() { $('.alert').fadeOut('slow'); }, 8000);
</script>
