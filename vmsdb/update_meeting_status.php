<?php
include('db.php');
require 'PHPmailer/PHPmailer.php';
require 'PHPmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $meetingId = $conn->real_escape_string($_POST['meetingId']);
    $status = $conn->real_escape_string($_POST['status']);

    // Validate the status value
    $validStatuses = ['Approved', 'Disapproved', 'On Hold']; // Allowed ENUM values
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
        exit;
    }

    // Update the meeting status
    $sql = "UPDATE meetings SET meetingAprroved = ? WHERE meeting_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $meetingId);

    if ($stmt->execute()) {
        // Fetch user email
        $sql2 = "
            SELECT u.userEmail 
            FROM meetings m
            JOIN tbl_user u ON m.visitor_id = u.id
            WHERE m.meeting_id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $meetingId);
        $stmt2->execute();
        $result = $stmt2->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $userEmail = $row['userEmail'];

            // Send email notification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.office365.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'rakesh.swami@nuvoco.com';
                $mail->Password = 'bfmmvmmtzmqzsrfl';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('rakesh.swami@nuvoco.com', 'Meeting Approval');
                $mail->addAddress($userEmail);

                $mail->isHTML(true);
                $mail->Subject = 'Meeting Status Update: Your Action is Recorded';
                $mail->Body = "<p>Dear User,</p>
                               <p>The meeting you reviewed has been <strong>$status</strong>.</p>
                               <p>If this was not your intended action or if you have further questions, please contact the VMS team for assistance.</p>
                               <p>Thank you for your time and cooperation.</p>
                               <p>Best regards,</p>
                               <p>VMS TEAM</p>";
                $mail->send();

                echo json_encode(['status' => 'success', 'message' => 'Meeting status updated and email sent']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send email: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User email not found']);
        }
        $stmt2->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update status']);
    }
    $stmt->close();
}

$conn->close();
?>
