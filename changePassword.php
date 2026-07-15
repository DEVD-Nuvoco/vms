<?php include("header.php");
if($_SESSION['loginType']=='E'){ 
$_SESSION['mess'] = 'You are not allowed to change your official details.';
header('location:index.php'); exit();
}

if(isset($_POST['SaveUpdate'])){
$getLogin = mysqli_query($mysqli,"select id from tbl_logindetail where id = '".$_SESSION['loginId']."' and userPassword = '".trim($_POST['cuPass'])."'");
if(mysqli_num_rows($getLogin)>0){
if(trim($_POST['nwPass'])==(trim($_POST['coPass']))){
$getLogin = mysqli_query($mysqli,"update tbl_logindetail set userPassword = '".trim($_POST['nwPass'])."' where id = '".$_SESSION['loginId']."' and userPassword = '".trim($_POST['cuPass'])."'");
$_SESSION['mess'] = 'Password has been change successfully.';
header('location:changePassword.php'); exit();
} else {
$_SESSION['mess'] = 'Confim password value is not matched with New Password value, please check again.';
header('location:changePassword.php'); exit();
}

} else {
$_SESSION['mess'] = 'Old password details not match within database, please check again.';
header('location:changePassword.php'); exit();
}

}
?>
    <div class="az-content pd-y-20 pd-lg-y-30 pd-xl-y-40">
      <div class="container">

        <?php include("myProfileLeft.php")?>
<script type="text/javascript">
var password = document.getElementById("nwPass"); 
var confirm_password = document.getElementById("coPass");

function validatePassword(){
  if(password.value != confirm_password.value) {
    alert("Passwords Don't Match");
  	return false;
  } else { return true; }
}

//nwPass.onchange = validatePassword;
//coPass.onkeyup = validatePassword;
</script>		
        <div class="az-content-body pd-lg-l-40 d-flex flex-column">
          <?php /*?><div class="az-content-breadcrumb">
            <span>Components</span>
            <span>Forms</span>
            <span>Form Elements</span>
          </div><?php */?>
          <h2 class="az-content-title">Change Password</h2>
		  <form action="changePassword.php" method="post" onSubmit="return validatePassword();" >
		 	<div class="row">
	<div class="col-lg-6" align="center">
	<table width="100%" border="0" cellspacing="4" cellpadding="4">
	<tr>
	<td><strong>Current Password:</strong></td>
	<td><input type="password" required name="cuPass" id="cuPass" tabindex="1" ></td>
	</tr>
	<tr>
	<td><strong>New Password:</strong></td>
	<td><input type="password" required name="nwPass" id="nwPass" tabindex="2"  pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters" ></td>
	</tr>
	<tr>
	<td><strong>Confirm Password:</strong></td>
	<td><input type="password" required name="coPass" id="coPass" tabindex="3"  pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" ></td>
	</tr>

	<tr>
	<td><input type="submit" value="Submit" class="btn btn-success" tabindex="4" name="SaveUpdate" > <input tabindex="5" type="button" onClick="window.location.href='myProfile.php';" value="Back" class="btn btn-danger" ></td>
	<td></td>
	</tr>
	
	</table>
	</div>
	
  </div>	
	      </form>	
  		 
          

          <div class="ht-40"></div>

			<?php include("footer.php")?>
          <!-- az-footer -->
        </div><!-- az-content-body -->
      </div><!-- container -->
    </div><!-- az-content -->


  </body>
</html>
