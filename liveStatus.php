<?php include("db.php");
$update5Min  = mysqli_query($mysqli,"update tbl_logindetail set liveStatus = 'p' where NOW() > (`lastLiveDateTime` + INTERVAL 5 MINUTE) and `lastLiveDateTime` != '0000-00-00 00:00:00'");
$update10Min = mysqli_query($mysqli,"update tbl_logindetail set liveStatus = 'd' where NOW() > (`lastLiveDateTime` + INTERVAL 10 MINUTE) and `lastLiveDateTime` != '0000-00-00 00:00:00'");
?>