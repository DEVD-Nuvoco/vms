<?php
session_start(); // Start session to use session variables
include("db.php"); // Assuming you have a file for DB connection
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Fetch the logged-in user's ID from the session
    if (isset($_SESSION['userDetails']['id'])) {
        $visitor_id = $_SESSION['userDetails']['id']; // Use the logged-in user's ID from session
    } else {
        echo json_encode(["status" => "error", "message" => "User not logged in."]);
        exit;
    }

    // Fetch form data
    $searchMeetTo = $_POST['searchMeetTo'];
    $visitType = $_POST['visitType'];
    $poVisit = $_POST['poVisit'];
    $startTime = $_POST['startTime'];
    $endTime = $_POST['endTime'];
    $vehicleNumber = $_POST['vehicleNumber'];
    $vehiclePermit = $_POST['vehiclePermit'];
    $city = $_POST['stt'];
    $state = $_POST['state'];
    $baggageDetails = $_POST['baggageDetails'];

    // Convert startTime and endTime to MySQL DATETIME format
    $startTime = date('Y-m-d H:i:s', strtotime($startTime));
    $endTime = date('Y-m-d H:i:s', strtotime($endTime));

    // Insert meeting into meetings table
    $insertMeetingQuery = "INSERT INTO meetings (visitor_id, meeting_person, visit_type, visit_purpose, meeting_start_time, meeting_end_time, vehicle_permit, baggage_details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $mysqli->prepare($insertMeetingQuery);
    $stmt->bind_param("isssssss", $visitor_id, $searchMeetTo, $visitType, $poVisit, $startTime, $endTime, $vehiclePermit, $baggageDetails);
    if ($stmt->execute()) {
        $meeting_id = $stmt->insert_id;
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
        exit;
    }

    // If visit type is group, add group members to group_members table
    if ($visitType == 'Group') {
        $groupMemberNames = $_POST['groupMemberName'];
        $groupMemberEmails = $_POST['groupMemberEmail'];
        $groupMemberMobiles = $_POST['groupMemberMobile'];

        foreach ($groupMemberNames as $index => $name) {
            $email = $groupMemberEmails[$index];
            $mobile = $groupMemberMobiles[$index];

            // Check if group member is already registered
            $registered_user_id = null;
            $memberCheckQuery = "SELECT id FROM tbl_user WHERE userEmail = ? OR userMobile = ?";
            $stmt = $mysqli->prepare($memberCheckQuery);
            $stmt->bind_param("ss", $email, $mobile);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Member exists, get the registered_user_id
                $stmt->bind_result($registered_user_id);
                $stmt->fetch();
            } else {
                // Insert new group member into tbl_user
                $insertMemberQuery = "INSERT INTO tbl_user (userName, userEmail, userMobile, userCity, userState, userCreatedOn) VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $mysqli->prepare($insertMemberQuery);
                $stmt->bind_param("sssss", $name, $email, $mobile, $city, $state);
                if ($stmt->execute()) {
                    $registered_user_id = $stmt->insert_id;
                } else {
                    echo json_encode(["status" => "error", "message" => $stmt->error]);
                    exit;
                }
            }

            // Insert group member details into group_members table
            $insertGroupMemberQuery = "INSERT INTO group_members (meeting_id, group_member_name, group_member_email, group_member_mobile, registered_user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $mysqli->prepare($insertGroupMemberQuery);
            $stmt->bind_param("isssi", $meeting_id, $name, $email, $mobile, $registered_user_id);
            if (!$stmt->execute()) {
                echo json_encode(["status" => "error", "message" => $stmt->error]);
                exit;
            }
        }
    }

    echo json_encode(["status" => "success", "message" => "Meeting scheduled successfully!"]);
    exit;
}
