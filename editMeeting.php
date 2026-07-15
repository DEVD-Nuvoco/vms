<?php
header('Content-Type: application/json');
session_start();
include('db.php'); // Include your DB connection file if not already included

$meeting_id = $_POST['meeting_id'] ?? '';
$visitor_id = $_POST['visitor_id'] ?? '';
$meeting_start_time = $_POST['meeting_start_time'] ?? '';
$meeting_end_time = $_POST['meeting_end_time'] ?? '';
$userName = $_POST['userName'] ?? '';
$userEmail = $_POST['userEmail'] ?? '';
$userMobile = $_POST['userMobile'] ?? '';
$userCompany = $_POST['userCompany'] ?? '';
$userDesignation = $_POST['userDesignation'] ?? '';

if (empty($meeting_id) || empty($visitor_id) || empty($meeting_start_time) || empty($meeting_end_time) || empty($userName) || empty($userEmail) || empty($userMobile)) {
    echo json_encode(['status' => 'error', 'message' => 'All required fields must be filled.']);
    exit;
}

// Update meetings table
$updateMeeting = $mysqli->prepare("UPDATE meetings SET meeting_start_time = ?, meeting_end_time = ? WHERE meeting_id = ?");
$updateMeeting->bind_param("ssi", $meeting_start_time, $meeting_end_time, $meeting_id);

if (!$updateMeeting->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update meeting details.']);
    exit;
}

// Update tbl_user
$updateUser = $mysqli->prepare("UPDATE tbl_user SET userName = ?, userEmail = ?, userMobile = ?, userCompany = ?, userDesignation = ? WHERE id = ?");
$updateUser->bind_param("sssssi", $userName, $userEmail, $userMobile, $userCompany, $userDesignation, $visitor_id);

if (!$updateUser->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update user details.']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Meeting and user details updated successfully.']);
