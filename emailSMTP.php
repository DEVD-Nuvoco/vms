<?php 
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\SMTP; //<- This fixed most of my issues but not always required per Sychro
    require 'PHPMailer/Exception.php';
    require 'PHPMailer/PHPMailer.php';
    require 'PHPMailer/SMTP.php';

function sent_email($to, $toName = NULL, $toCC = NULL, $toBCC = NULL, $subject, $body, $attachment = NULL){
    

//    if(isset($_POST['submit'])){ 
	$mail = new PHPMailer();

    //Note: Hosting Service should provide this infor to you.
    //----------    
    $mail->isSMTP(); 
    $mail->SMTPDebug = 0; // 0 = off (for production use) - 1 = client messages - 2 = client and server messages
    $mail->Host = 'smtp.office365.com'; 
    $mail->SMTPSecure = 'TLS'; //<-Could be TLS or SSL
    $mail->Port = 587; //<- Could be 587 or 25
    $mail->SMTPAuth = true;
	$mail->IsHTML(true);

	$mail->Username = 'vmssupport@nuvoco.com'; //<-To access your Hosting email
	$mail->Password = 'mpjqqbtmkgrbrvpb'; 
    
	//$mail->Username = 'noreply@nuvoco.com'; //<-To access your Hosting email
    //$mail->Password = 'gbhvbdlccjmlpccp'; 
	
	
	$mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => false)); 
     
    $mail->setFrom('vmssupport@nuvoco.com'); //<- From Myself
    //$mail->addBCC('rakesh.swami@nuvoco.com'); //<- From Myself
    # To Email
	for($i=0;$i<count($to);$i++){	 $mail->addAddress($to[$i]); 	}
    //$mail->addBCC('rakesh.swami@nuvoco.com'); //<- From Myself

    # To CC-Email
	//if(count($toCC)>0){
	//for($i=0;$i<count($toCC);$i++){	 $mail->addAddress($toCC[$i]); 	}
    //}
	# To BCC-Email
	//if(count($toBCC)>0){
	//for($i=0;$i<count($toBCC);$i++){ $mail->addAddress($toBCC[$i]); }
	//}
	
	if($attachment!=''){
			$mail->addAttachment('hash-inv/'.$attachment, $attachment);
	}

	
    $mail->Subject = $subject;

    $mail->isHTML(TRUE);
    //$mail->Body = htmlentities($body, ENT_NOQUOTES);
	$mail->Body = $body;
    $mail->send();
}

?>