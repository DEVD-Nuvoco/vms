<?php include("db.php");
include("emailSMTP.php");
if(isset($_POST['GetPass'])){
$checkData = mysqli_query($mysqli, "select * from `tbl_logindetail` where userName = '".$_POST['emailVal']."' ");
//$to = array(); $toName = array(); $toCC = array(); $toBCC = array(); $subject = '';  $body = '';
if(mysqli_num_rows($checkData)>0){
$getData = mysqli_fetch_array($checkData);

$to = array($getData['userName']); 
$toName = array($getData['userName']); 

//$toCC = array($getData['memEmail']); 
//$toBCC = array($getData['memEmail']); 
$subject = $Address.':: Get Your Password';
$body = '';
$body .= '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td>Dear Sir/Ma`am,<br><br>We have receive a request regarding forget password for your account.<br>Kindly arrange to collect your password detail: '.$getData['userPassword'].'<br><br>from support team,<br>'.$Address.'<br /><br /><br />
<em><strong>Note:</strong> Its a system generated email please do not reply.</em>
</td></tr></table>';
//echo "<br>".$subject."<br>".$body; die();
sent_email($to, $toName, $toCC, $toBCC, $subject, $body, $attachment);
$_SESSION['mess'] = 'We have sent your password at email {'.$getData['userName'].'}.'; 
header("location:signup.php?action=getYourPassword"); exit();  
} else {  $_SESSION['mess'] = 'Your submitted details are not match within database, please contact system administrator!'; 
header("location:signup.php?action=getYourPassword"); exit(); 
}}

?>