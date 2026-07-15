<?php
// Debugging: Enable error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'PHPmailer/PHPMailer.php';
require 'PHPmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as e;

// Include database connection
include("db.php");

// Set header to JSON so the client knows to expect JSON data
header('Content-Type: application/json');
ob_start(); // Start output buffering

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON payload.');
    }
    
    $name = trim($input['vName'] ?? '');
    $email = trim($input['vEmail'] ?? '');
    $mobile = trim($input['vMobile'] ?? '');
    $company = trim($input['vCompany'] ?? '');
    $location = trim($input['vLocation'] ?? '');
    $Designation = trim($input['vDesignation'] ?? '');
    $Dob = trim($input['vBirthday'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($mobile) || empty($company) || empty($location) || empty($Designation) || empty($Dob)) {
        $missingFields = [];
        if (empty($name)) $missingFields[] = 'Name';
        if (empty($email)) $missingFields[] = 'Email';
        if (empty($mobile)) $missingFields[] = 'Mobile';
        if (empty($company)) $missingFields[] = 'Company';
        if (empty($location)) $missingFields[] = 'Location';
        if (empty($Designation)) $missingFields[] = 'Designation';
        if (empty($Dob)) $missingFields[] = 'Date of Birth';
        throw new Exception('Missing fields: ' . implode(', ', $missingFields));
    }
    
    // Check if user already exists
    $checkQuery = "SELECT * FROM `tbl_logindetail` WHERE `userName` = ?";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        throw new Exception('Error preparing check query: ' . $conn->error);
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        throw new Exception('User already exists.');
    }

    // Generate a random password
    $password = mt_rand(100000, 999999);

    // Insert into tbl_logindetail
    $insertLoginQuery = "INSERT INTO `tbl_logindetail` (`logType`, `activationStatus`, `userName`, `userPassword`) 
                         VALUES ('V', 't', ?, ?)";
    $stmt = $conn->prepare($insertLoginQuery);
    if (!$stmt) {
        throw new Exception('Error preparing login insert query: ' . $conn->error);
    }
    $stmt->bind_param('ss', $email, $password);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create login details: ' . $stmt->error);
    }

    // Insert into tbl_user
    $insertUserQuery = "INSERT INTO `tbl_user` (`userName`, `userCompany`, `userEmail`, `userMobile`, `userCreatedOn`, `userDesignation`, `userAddress`, `userIP`, `uDob`) 
                        VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertUserQuery);
    $userIP = $_SERVER['REMOTE_ADDR'];
    if (!$stmt) {
        throw new Exception('Error preparing user insert query: ' . $conn->error);
    }
    $stmt->bind_param('ssssssss', $name, $company, $email, $mobile, $Designation, $location, $userIP, $Dob);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create user details: ' . $stmt->error);
    }
    
    // Send email
    $mail = new PHPMailer(true);
   try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.office365.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'vmssupport@nuvoco.com';
    $mail->Password   = 'mpjqqbtmkgrbrvpb';   // ← your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('vmssupport@nuvoco.com', 'Account Created');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Account Created with Credentials';

    // Build the body
    $body  = "<p>Your temporary password is <strong>{$password}</strong>.<br>"
           . "Please change it as soon as you log in.</p>";

    // Generalized safety notice
    $body .= "<p>If you’ll be visiting any of our facilities, please observe these safety rules:</p>"
           . "<ol>"
           . "<li>Use pedestrian walkways and zebra crossings when moving around.</li>"
           . "<li>Hold onto hand railings when using staircases.</li>"
           . "<li>Wear all four mandatory PPEs: Helmet, Safety shoes, Reflective jacket, and Safety goggles.</li>"
           . "<li>Avoid using your mobile phone while walking or crossing roads.</li>"
           . "</ol>";

    $mail->Body = $body;
    $mail->send();
} catch (Exception $e) {
    throw new Exception("Failed to send email: " . $e->getMessage());
}

    // Commit the transaction
    $conn->commit();

    ob_clean();
    echo json_encode([
         'success' => true, 
         'message' => 'User registered successfully. Redirecting to login page.',
         'redirect' => 'https://vms.nuvoco.in/vms/signup.php?action=signin'
    ]);
    exit();
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
} finally {
    ob_end_flush();
}
?>
