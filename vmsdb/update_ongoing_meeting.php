<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('db.php');
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['meeting_id'], $_POST['forwardUser']) || empty($_POST['meeting_id']) || empty($_POST['forwardUser'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$meeting_id = $_POST['meeting_id'];
$forwardUser = $_POST['forwardUser'];

// Fetch meeting details (for example, to get the meeting person for the email)
$stmt = $conn->prepare("SELECT meeting_person FROM meetings WHERE meeting_id = ?");
$stmt->bind_param("i", $meeting_id);
$stmt->execute();
$stmt->bind_result($meeting_person);
if (!$stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Meeting not found']);
    exit;
}
$stmt->close();

// Fetch employee details from your employee table
$fwdStmt = $conn->prepare("SELECT empName, empBusiEmail FROM tbl_nuvo_employee WHERE empCode = ?");
$fwdStmt->bind_param("s", $forwardUser);
$fwdStmt->execute();
$fwdResult = $fwdStmt->get_result();
if ($fwdResult->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found']);
    exit;
}
$fwdRow = $fwdResult->fetch_assoc();
$forwardName = $fwdRow['empName'];
$forwardEmail = $fwdRow['empBusiEmail'];
$fwdStmt->close();

// Log the forward event by inserting into tbl_meeting_forwards
$insertStmt = $conn->prepare("INSERT INTO tbl_meeting_forwards (meeting_id, forwarded_to, forwarded_email, forwarded_by) VALUES (?, ?, ?,?)");
$insertStmt->bind_param("isss", $meeting_id, $forwardName, $forwardEmail,$meeting_person);
if (!$insertStmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to record forwarding: ' . $conn->error]);
    exit;
}
$insertStmt->close();

// (Optional) Update the meetings table with the latest forward information
// Update the meetings table with the latest forward information
$updFwdStmt = $conn->prepare("UPDATE meetings SET forwarded_to = ? WHERE meeting_id = ?");
$updFwdStmt->bind_param("si", $forwardName, $meeting_id);
if (!$updFwdStmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $conn->error]);
    exit;
}

// If affected_rows is 0, it might be because the value is already the same.
// You can choose to treat this as success.
if ($updFwdStmt->affected_rows === 0) {
    // Optionally, log that no change was necessary.
    // echo json_encode(['status' => 'success', 'message' => 'Meeting already forwarded to this employee']);
    // exit;
}
$updFwdStmt->close();


// Send email notification to the forwarded employee
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'rakesh.swami@nuvoco.com';
    $mail->Password = 'bfmmvmmtzmqzsrfl';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    
    $mail->setFrom('rakesh.swami@nuvoco.com', 'Meeting Forward');
    $mail->addAddress($forwardEmail);
    $mail->isHTML(true);
    $mail->Subject = 'Meeting Forwarded to You';
    $mail->Body = "
        <p>Dear {$forwardName},</p>
        <p>You have been forwarded a meeting by <strong>{$meeting_person}</strong>.</p>
      
        <p>Regards,</p>
        <p>VMS TEAM</p>
    ";
    
    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'Meeting forwarded successfully']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email: ' . $mail->ErrorInfo]);
}

$conn->close();
?>
