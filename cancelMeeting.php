<?php
header('Content-Type: application/json');
session_start();
include('db.php'); // Ensure your DB connection file is properly included

$meeting_id = $_POST['meeting_id'] ?? '';

if (empty($meeting_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Meeting ID is required.']);
    exit;
}

// Update the meetingAprroved field to Approved
$updateQuery = $mysqli->prepare("UPDATE meetings SET meetingAprroved = 'Canceled' WHERE meeting_id = ?");
if (!$updateQuery) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the statement.']);
    exit;
}
$updateQuery->bind_param("i", $meeting_id);

if ($updateQuery->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Meeting cancelled successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to cancelled meeting.']);
}
