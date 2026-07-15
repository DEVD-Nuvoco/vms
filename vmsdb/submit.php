<?php
include('db.php');
require 'PHPmailer/PHPmailer.php';
require 'PHPmailer/SMTP.php';
date_default_timezone_set('Asia/Kolkata');  

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as e;
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
 
    // Validate inputs
    if (empty($input['visitorId']) || empty($input['searchMeetTo']) || empty($input['visitType']) || empty($input['poVisit']) || empty($input['startTime']) || empty($input['endTime']) || empty($input['empCode']) || empty($input['meetLocation'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    $visitor_id = $input['visitorId'];
    $searchMeetTo = $input['searchMeetTo'];
    $visitType = $input['visitType'];
    $poVisit = $input['poVisit'];
    $startTime   = date('Y-m-d H:i:s', strtotime($input['startTime']));
    $endTime     = date('Y-m-d H:i:s', strtotime($input['endTime']));
    $empCode = $input['empCode'];
    $empLocation = $input['meetLocation'];
    $meetingDate = date('Y-m-d', strtotime($startTime));
    $meetingDays = $input['meetingDays'] ?? 'S'; // Default to 'S' if not provided

 // Check for existing meeting on the same day and location
// try {
//     $stmt = $conn->prepare("SELECT COUNT(*) FROM meetings WHERE visitor_id = ? AND meeting_location = ? AND DATE(meeting_start_time) = ?");
//     $stmt->bind_param("sss", $visitor_id, $empLocation, $meetingDate);
//     $stmt->execute();
//     $stmt->bind_result($existingMeetingCount);
//     $stmt->fetch();
//     $stmt->close();

//     if ($existingMeetingCount > 0) {
//         throw new Exception('The visitor already has a meeting scheduled at this location on the same day.');
//     }
// } catch (Exception $e) {
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
//     exit;
// }

// Validate meetingDays value
// if (!in_array($meetingDays, ['S', 'M'])) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid meetingDays value.']);
//     exit;
// }

// // Check for conflicting bookings
// try {
//     $stmt = $conn->prepare("
//         SELECT COUNT(*)
//         FROM meetings
//         WHERE
//             (meeting_person = ? OR visitor_id = ?)
//             AND (
//                 (meeting_start_time <= ? AND meeting_end_time >= ?) OR
//                 (meeting_start_time >= ? AND meeting_start_time < ?) OR
//                 (meeting_end_time > ? AND meeting_end_time <= ?)
//             )
//     ");
//     $stmt->bind_param("ssssssss", $searchMeetTo, $visitor_id, $endTime, $startTime, $startTime, $endTime, $startTime, $endTime);
//     $stmt->execute();
//     $stmt->bind_result($conflictCount);
//     $stmt->fetch();
//     $stmt->close();

//     if ($conflictCount > 0) {
//         throw new Exception('The user is already booked during this time.');
//     }
// } catch (Exception $e) {
//     echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
//     exit;
// }

// Proceed with the transaction if no conflicts
$conn->begin_transaction();
 
    try {
        // Insert meeting details
        $stmt = $conn->prepare("
    INSERT INTO meetings (meeting_location, meetperson_id, visitor_id, meeting_person, visit_type, visit_purpose, meeting_start_time, meeting_end_time, meetingDays, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param(
    "sssssssss",
    $empLocation,
    $empCode,
    $visitor_id,
    $searchMeetTo,
    $visitType,
    $poVisit,
    $startTime,
    $endTime,
    $meetingDays
);

if (!$stmt->execute()) {
    throw new Exception("Error inserting meeting: " . $stmt->error);
}

 
        $meeting_id = $stmt->insert_id; // Get the last inserted meeting ID
 
        // Handle group members if visit type is 'Group'
        if ($visitType === 'Group' && isset($input['groupMembers']) && is_array($input['groupMembers'])) {
            foreach ($input['groupMembers'] as $member) {
                $name = $member['groupMemberName'];
                $email = $member['groupMemberEmail'];
                $mobile = $member['groupMemberMobile'];
                $password = mt_rand(100000, 999999);
 
                // Insert into tbl_logindetail
                // Check if the group member already exists
                $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE userEmail = ? OR userMobile = ?");
                $stmt->bind_param("ss", $email, $mobile);
                $stmt->execute();
                $stmt->bind_result($registered_user_id);
                $stmt->store_result();
 
                if ($stmt->num_rows > 0) {
                    $stmt->fetch(); // Get the existing user ID
                } else {
                    // Insert new user into tbl_user
                    $stmt = $conn->prepare("INSERT INTO tbl_user (userName, userEmail, userMobile, userCity, userState, userCreatedOn)
                                            VALUES (?, ?, ?, '', '', NOW())");
                    $stmt->bind_param("sss", $name, $email, $mobile);
                    if (!$stmt->execute()) {
                        throw new Exception("Error inserting group member into tbl_user: " . $stmt->error);
                    }
                    $registered_user_id = $stmt->insert_id; // Get the new user ID
                }
 
                $insertLoginQuery = "INSERT INTO `tbl_logindetail` (`logType`, `activationStatus`, `userName`, `userPassword`)
                VALUES ('V', 'f', ?, ?)";
                $stmt2 = $conn->prepare($insertLoginQuery);
                if (!$stmt2) {
                    throw new Exception('Error preparing login insert query: ' . $conn->error);
                }
                $stmt2->bind_param('ss', $email, $password);
                if (!$stmt2->execute()) {
                    throw new Exception('Failed to create login details: ' . $stmt2->error);
                }
 
                // Insert group member into group_members table
                $stmt = $conn->prepare("INSERT INTO group_members (meeting_id, group_member_name, group_member_email, group_member_mobile, registered_user_id, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("isssi", $meeting_id, $name, $email, $mobile, $registered_user_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting group member into group_members: " . $stmt->error);
                }
            }
        }
        $stmt = $conn->prepare("SELECT userEmail FROM tbl_user WHERE id = ?");
        $stmt->bind_param("s", $visitor_id);
        $stmt->execute();
        $stmt->bind_result($managerEmail);
        $stmt->fetch();
        $stmt->close();

        $approvalLink = "https://vms.nuvoco.in/vmsdb/approve_meeting.php?meeting_id=" . $meeting_id;

        // Send email
        $mail = new PHPMailer(true);
        $sql = "
        SELECT e.empBusiEmail 
        FROM meetings m
        JOIN tbl_nuvo_employee e ON m.meetperson_id = e.empCode
        WHERE m.meeting_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $meeting_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $empBusiEmail = $row['empBusiEmail'];
   
    } else {
        echo "No email found for the given meeting ID.";
    }
    

        try {

            $mail->isSMTP();
            $mail->Host = 'smtp.office365.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'vmssupport@nuvoco.com';
            $mail->Password = 'mpjqqbtmkgrbrvpb';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('vmssupport@nuvoco.com', 'Meeting Approval');
            $mail->addAddress($empBusiEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Action Required: Approval Needed for Scheduled Meeting';
            $mail->Body = "<p>Dear User,</p>
                           <p>A new meeting has been scheduled and requires your review. Kindly click the link below to approve or disapprove the meeting:</p>
                           <p><a href='$approvalLink'>Approve/Disapprove Meeting</a></p>
                           <p>Thank you for your prompt attention.</p>
                           <p>Best regards,</p>
                           <p>VMS TEAM</p>";
            $mail->send();
        } catch (Exception $e) {
            throw new Exception("Failed to send email: " . $e->getMessage());
        }

        // Commit the transaction
        $conn->commit();

        echo json_encode(['status' => 'success', 'message' => 'Meeting and group members added successfully, email sent']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
$conn->close();
?>