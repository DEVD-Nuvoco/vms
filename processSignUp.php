<?php 
include("db.php");
include("emailSMTP.php");

if(isset($_POST['SignUpBtn'])) {
    // Check if the account already exists with active status
    $checkData = mysqli_query($mysqli, "SELECT * FROM `tbl_logindetail` WHERE userName = '".trim($_POST['vEmail'])."' AND activationStatus = 't'");
    
    if(mysqli_num_rows($checkData) > 0) {
        $getData = mysqli_fetch_array($checkData);

        $to = array($getData['userName']); 
        $toName = array($getData['userName']); 

        $subject = $Address . ':: Password Details';
        $body = '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td>Dear Sir/Ma`am,<br><br>
        Your account already exists.<br>
        Kindly arrange to collect your password detail: '.$getData['userPassword'].'<br><br>from support team,<br>'.$Address.'<br /><br /><br />
        <em><strong>Note:</strong> It’s a system-generated email. Please do not reply.</em>
        </td></tr></table>';

        sent_email($to, $toName, [], [], $subject, $body, null);
        $_SESSION['mess'] = 'Your account already exists. Password details are shared at email {'.$getData['userName'].'}.'; 
        header("location:signup.php?action=signup"); 
        exit();  
    } else {  
        // Pre-check for inactive accounts
        $checkData = mysqli_query($mysqli, "SELECT * FROM `tbl_logindetail` WHERE userName = '".trim($_POST['vEmail'])."'");
        $getData = mysqli_fetch_array($checkData);
        
        $pass = mt_rand(100000, 999999);
        $atCo = mt_rand(100000, 999999);

        if(mysqli_num_rows($checkData) == 0) {
            // Insert new login details
            $doInsert = mysqli_query($mysqli, "INSERT INTO tbl_logindetail SET 
                logType = 'V', 
                activationStatus = 'f', 
                activationCode = '".$atCo."', 
                enCryptUrl = '".md5($atCo)."', 
                userName = '".trim($_POST['vEmail'])."', 
                userPassword = '".$pass."'");
        } else {
            // Update existing inactive login details
            $doUpdate = mysqli_query($mysqli, "UPDATE tbl_logindetail SET 
                logType = 'V', 
                activationCode = '".$atCo."', 
                enCryptUrl = '".md5($atCo)."', 
                userPassword = '".$pass."' 
                WHERE userName = '".trim($_POST['vEmail'])."' AND activationStatus = 'f'");
        }

        // Calculate age from DOB
        $dob = DateTime::createFromFormat('d-m-Y', $_POST['vDOB']);
        if ($dob) {
            $currentDate = new DateTime();
            $age = $currentDate->diff($dob)->y; // Calculate the age
            $formattedDOB = $dob->format('Y-m-d'); // Format DOB for database
        } else {
            $_SESSION['mess'] = 'Invalid Date of Birth format.';
            header("location:signup.php?action=signup");
            exit();
        }

        // Insert visitor details
        $insertUser = mysqli_query($mysqli, "INSERT INTO tbl_user SET 
            userName = '".trim($_POST['vName'])."', 
            userCompany = '".trim($_POST['vCompany'])."', 
            userEmail = '".trim($_POST['vEmail'])."', 
            userMobile = '".trim($_POST['vMobile'])."', 
            userDesignation = '".trim($_POST['vDesignation'])."', 
            userDOB = '".$formattedDOB."', 
            userAge = '".$age."', 
            userCity = '".trim($_POST['city'])."', 
            userState = '".trim($_POST['state'])."', 
            userCreatedOn = NOW(), 
            userIP = '".$_SERVER['REMOTE_ADDR']."'");

        // Send account activation email
        $to = array(trim($_POST['vEmail'])); 
        $toName = array(trim($_POST['vEmail'])); 

        $subject = $Address . ':: Account Activation';
        $body = '<table width="100%" border="0" cellspacing="0" cellpadding="0"><tr><td>Dear Sir/Ma`am,<br><br>
        Please follow the mentioned URL and submit the activation code to activate your account.<br>
        <div align="left" style="font-size:25px; font-weight:bold; color:#FF0000;">'.$atCo.'</div><br />
        URL: '.$appURL.'/activateAccount.php?acDet='.md5($atCo).'
        <br><br>from support team,<br>'.$Address.'<br /><br /><br />
        <em><strong>Note:</strong> It’s a system-generated email. Please do not reply.</em>
        </td></tr></table>';

        sent_email($to, $toName, [], [], $subject, $body, null);

        $_SESSION['mess'] = 'Please check, we have sent you an activation code at email {'.$_POST['vEmail'].'}.'; 
        header("location:signup.php?action=signup");
        exit(); 
    }
}
?>
