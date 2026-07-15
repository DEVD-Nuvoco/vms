<?php
include('db.php');
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

header('Content-Type: application/json');

$location = $_POST['location'] ?? '';
$reason = $_POST['reason'] ?? '';
$today = date('Y-m-d');

if (empty($location) || empty($reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Location and reason are required.']);
    exit;
}

try {
    // Fetch meeting IDs and email details
    $sql = "
        SELECT 
            m.meeting_id,
            u.userEmail AS visitorEmail,
            e.empBusiEmail AS meetPersonEmail
        FROM meetings m
        LEFT JOIN tbl_user u ON m.visitor_id = u.id
        LEFT JOIN tbl_nuvo_employee e ON m.meetperson_id = e.empCode
        WHERE m.meeting_location = ? AND DATE(m.meeting_start_time) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $location, $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No meetings found for today at this location.']);
        exit;
    }

    // Cancel all meetings
    $updateSql = "
        UPDATE meetings 
        SET meetingAprroved = 'Canceled' 
        WHERE meeting_location = ? AND DATE(meeting_start_time) = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ss', $location, $today);
    $updateStmt->execute();

    // Send emails to visitor and meeting person
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.office365.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'vmssupport@nuvoco.com';
    $mail->Password = 'mpjqqbtmkgrbrvpb';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('vmssupport@nuvoco.com', 'Meeting Cancellation');

    while ($row = $result->fetch_assoc()) {
        $visitorEmail = $row['visitorEmail'];
        $meetPersonEmail = $row['meetPersonEmail'];
        $meetingId = $row['meeting_id'];

        $mail->clearAddresses();
        $mail->addAddress($visitorEmail);
        $mail->addAddress($meetPersonEmail);

        $mail->isHTML(true);
        $mail->Subject = "Meeting #$meetingId Cancelled";
        $mail->Body = "
            <p>Dear Attendees,</p>
            <p>We regret to inform you that your meeting (ID: $meetingId) scheduled for today has been cancelled for the following reason:</p>
            <p><strong>$reason</strong></p>
            <p>If you have any questions, please contact us.</p>";

        if (!$mail->send()) {
            throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
        }
    }

    echo json_encode(['status' => 'success', 'message' => 'All meetings for today have been cancelled, and emails have been sent.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
}
