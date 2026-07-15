<?php include("db.php");
if(isset($_POST['login'])){
if(($_POST['emailVal']!='') and ($_POST['passVal']!='') and ($_POST['logType']!='')){
if($_POST['logType']=='nuvocan'){
$getCheck = mysqli_query($mysqli,"select * from tbl_logindetail where userName = '".$_POST['emailVal']."' 
								  and activationStatus = 't' and userPassword = '".$_POST['passVal']."'
								  and userName in (select empBusiEmail from tbl_nuvo_employee where 1=1 and empStatus = 'Active' and empBusiEmail != '') ");

if(mysqli_num_rows($getCheck)>0){
$getFetData = mysqli_fetch_array($getCheck);
$getEmpData = mysqli_fetch_array(mysqli_query($mysqli,"select * from tbl_nuvo_employee where empBusiEmail = '".$getFetData['userName']."'"));
$_SESSION['userDetails'] = $getEmpData;
$_SESSION['loginId']   	 = $getFetData['id'];
$_SESSION['profilePic']  = $getFetData['profilePic'];
$_SESSION['loginType']   = $getFetData['logType'];
$_SESSION['mess'] = 'Welcome to VMS.';
header('location:index.php'); exit();
} else {
$_SESSION['mess'] = 'Please check your submitted login details.';
header('location:signup.php'); exit();

}
} else if($_POST['logType']=='visitor'){
$getCheck = mysqli_query($mysqli,"select * from tbl_logindetail where userName = '".$_POST['emailVal']."' 
								  and activationStatus = 't' and userPassword = '".$_POST['passVal']."'and logType = 'V'
								  and userName in (select userEmail from tbl_user where 1=1 and userBlock != 't' ) ");

if(mysqli_num_rows($getCheck)>0){
$getFetData = mysqli_fetch_array($getCheck);
$getEmpData = mysqli_fetch_array(mysqli_query($mysqli,"select * from tbl_user where userEmail = '".$getFetData['userName']."'"));
$_SESSION['userDetails'] = $getEmpData;
$_SESSION['loginId']   	 = $getFetData['id'];
$_SESSION['profilePic']  = $getFetData['profilePic'];
$_SESSION['loginType']   = $getFetData['logType'];
$_SESSION['mess'] = 'Welcome to VMS.';
header('location:index.php'); exit();
} else {
$_SESSION['mess'] = 'Please check your submitted login details.';
header('location:signup.php'); exit();
}
} else if($_POST['logType']=='security'){
	$getCheck = mysqli_query($mysqli,"select * from tbl_logindetail where userName = '".$_POST['emailVal']."' 
									  and activationStatus = 't' and userPassword = '".$_POST['passVal']."' and logType = 'S'
									  and userName in (select userEmail from tbl_user where 1=1 and userBlock != 't' ) ");
	
	if(mysqli_num_rows($getCheck)>0){
	$getFetData = mysqli_fetch_array($getCheck);
	$getEmpData = mysqli_fetch_array(mysqli_query($mysqli,"select * from tbl_user where userEmail = '".$getFetData['userName']."'"));
	$_SESSION['userDetails'] = $getEmpData;
	$_SESSION['loginId']   	 = $getFetData['id'];
	$_SESSION['profilePic']  = $getFetData['profilePic'];
	$_SESSION['loginType']   = $getFetData['logType'];
	$_SESSION['mess'] = 'Welcome to VMS.';
	header('location:index.php'); exit();
	} else {
	$_SESSION['mess'] = 'Please check your submitted login details.';
	header('location:signup.php'); exit();
	}
	}}}
?>