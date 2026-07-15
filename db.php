<?php
ob_start();
session_start();

$ProjectName = "Visitor Management System";
$Address = "Nuvoco: ".$ProjectName;
$appURL = 'https://vms.nuvoco.in/vms/';

//header('Content-Type: text/html; charset=ISO-8859-1');
//header("Access-Control-Allow-Origin: *");
//header("Access-Control-Allow-Headers: Origin, Content-Type");

global $mysqli; global $mysqli2;
$mysqli = new mysqli("localhost","powerBI","uh(*6l7AQJ@qM.@7","vms");
//$mysqli = new mysqli($host, $username, $passwd, $dbName, $port, $socket);
// Check connection
if ($mysqli -> connect_errno) {
echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
exit();
}

$mysqli2 = new mysqli("172.16.20.91","powerBI","uh(*6l7AQJ@qM.@7","vms");
//$mysqli = new mysqli($host, $username, $passwd, $dbName, $port, $socket);
// Check connection
if ($mysqli2 -> connect_errno) {
echo "Failed to connect to MySQL: " . $mysqli2 -> connect_error;
exit();
}


function copyAllEmpfromAMS(){
global $mysqli;
$getCheck 	= mysqli_query($mysqli,"select id from tbl_todayload where loadDate = '".date('Y-m-d')."' ");
if(mysqli_num_rows($getCheck)==0){
$preTrun 	= mysqli_query($mysqli,"truncate vms.tbl_nuvo_employee ");
$doCopy 	= mysqli_query($mysqli,"insert into vms.tbl_nuvo_employee select * from nuvoco_emp.tbl_nuvo_employee ");
$inserLog 	= mysqli_query($mysqli,"insert into tbl_todayload set loadDate = '".date('Y-m-d')."', updateTime = now() ");
}
#Log Insert
$getCheck  = mysqli_query($mysqli,"select empBusiEmail from vms.tbl_nuvo_employee where 1=1 and empStatus = 'Active' and empBusiEmail !='' and empBusiEmail not in (select userName from tbl_logindetail where 1=1 and logType = 'E')");
if(mysqli_num_rows($getCheck)>0){
while($emp = mysqli_fetch_array($getCheck)){
$pass 	  = '';
$pass 	  = mt_rand(100000,999999);
$doInsert = mysqli_query($mysqli,"insert into tbl_logindetail set logType = 'E', activationStatus = 't', userName = '".$emp['empBusiEmail']."', userPassword = '".$pass."' ");
}}
$ageUpdte = mysqli_query($mysqli,"update vms.tbl_nuvo_employee set `empAge` = DATE_FORMAT(NOW(), '%Y') - DATE_FORMAT(`empDOB`, '%Y') - (DATE_FORMAT(NOW(), '00-%m-%d') < DATE_FORMAT(`empDOB`, '00-%m-%d')) WHERE `empDOB` != '0000-00-00'");
}
// copyAllEmpfromAMS();


function autoSetLiveStatus($userId){
global $mysqli;
if($userId>0){
$doUpdate = mysqli_query($mysqli,"update tbl_logindetail set liveStatus = 'a', lastLiveDateTime = now() where id = '".$userId."' ");
}}

function getCurrentLive($userId){
global $mysqli;
if($userId>0){
$doFetch = mysqli_fetch_array(mysqli_query($mysqli,"select liveStatus from tbl_logindetail where id = '".$userId."' "));
$retVal = '';
if($doFetch['liveStatus']=='a'){ $retVal = 'active'; }
else if($doFetch['liveStatus']=='p'){ $retVal = 'passive'; }
else if($doFetch['liveStatus']=='d'){ $retVal = ''; }
return $retVal;
}}

  
?>