<?php
include('db.php');
date_default_timezone_set('Asia/Kolkata'); 
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw input for JSON payload
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validate and sanitize the meeting ID
    $meetingId = isset($data['meeting_id']) ? trim($conn->real_escape_string($data['meeting_id'])) : null;

    // Log received data for debugging
    file_put_contents('debug_log.txt', "Received Meeting ID: " . $meetingId . PHP_EOL, FILE_APPEND);

    if (!$meetingId) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid meeting ID']);
        exit;
    }

    // Get the current timestamp
    $currentTime = date('Y-m-d H:i:s');

    // Check the current value of gate_in
    $checkSql = "SELECT gate_in FROM meetings WHERE meeting_id = '$meetingId'";
    $result = $conn->query($checkSql);

    if ($result && $row = $result->fetch_assoc()) {
        if ($row['gate_in'] === '0000-00-00 00:00:00') {
            // gate_in is default, update it
            $updateSql = "
                UPDATE meetings
                SET gate_in = '$currentTime'
                WHERE meeting_id = '$meetingId'
            ";

            if ($conn->query($updateSql) === TRUE) {
                echo json_encode(['status' => 'success', 'message' => 'Gate-in time updated']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update gate-in time: ' . $conn->error]);
            }
        } else {
            // gate_in is already populated, update gate_out
            $updateSql = "
                UPDATE meetings
                SET gate_out = '$currentTime'
                WHERE meeting_id = '$meetingId'
            ";

            if ($conn->query($updateSql) === TRUE) {
                echo json_encode(['status' => 'success', 'message' => 'Gate-out time updated']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update gate-out time: ' . $conn->error]);
            }
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Meeting ID not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

// Close the database connection
$conn->close();
?>
