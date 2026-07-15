<?php

include('db.php');
header('Content-Type: application/json');
require 'PHPmailer/PHPmailer.php';
require 'PHPmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? null;

    // Validate email input
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email not provided.']);
        exit;
    }

    // Check if the email exists in the database
    $query = "SELECT * FROM tbl_logindetail WHERE userName = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare SELECT query.', 'error' => $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to execute SELECT query.', 'error' => $stmt->error]);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        // Email not found in the database
        echo json_encode(['status' => 'error', 'message' => 'Invalid email.']);
        exit;
    }

    // Generate a random 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Update the userPassword to the new OTP
    $updateQuery = "UPDATE tbl_logindetail SET userPassword = ? WHERE userName = ?";
    $updateStmt = $conn->prepare($updateQuery);
    if (!$updateStmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare UPDATE query.', 'error' => $conn->error]);
        exit;
    }

    $updateStmt->bind_param("ss", $otp, $email);
    if (!$updateStmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to execute UPDATE query.', 'error' => $updateStmt->error]);
        exit;
    }

    if ($updateStmt->affected_rows > 0) {
        // Send OTP via PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.office365.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vmssupport@nuvoco.com';
            $mail->Password = 'mpjqqbtmkgrbrvpb'; // Use app password if MFA is enabled
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('vmssupport@nuvoco.com', 'Meeting Approval');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request: Your OTP Inside';
            $mail->Body = "<p>Dear User,</p>
                           <p>You requested to reset your password. Please use the One-Time Password (OTP) below to proceed:</p>
                           <p><strong>$otp</strong></p>
                           <p>If you did not request a password reset, please disregard this email. For assistance, contact our support team.</p>
                           <p>Best regards,<br>VMS TEAM</p>";

            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'OTP sent to email.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'OTP generated but email not sent.', 'error' => $mail->ErrorInfo]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Password not updated in database.']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>
